<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Bank / cash transfers: a withdrawal (Bank to Cash) or a deposit (Cash to Bank).
 * They change the bank and cash balances but never the total (see Fund_model).
 * Entries are never deleted - a wrong entry is cancelled with a reason.
 */
class Transfer_model extends MY_Model
{
    protected $table = 'fund_transfers';

    const DIRECTIONS = array('Bank to Cash' => 'Withdrawal (bank to cash)', 'Cash to Bank' => 'Deposit (cash to bank)');

    /**
     * @return array<int, array<string, mixed>> newest first, including cancelled ones
     */
    public function list_all(): array
    {
        return $this->db->query(
            "SELECT t.*, u.full_name AS created_by_name, c.full_name AS cancelled_by_name
             FROM fund_transfers t
             LEFT JOIN users u ON u.id = t.created_by
             LEFT JOIN users c ON c.id = t.cancelled_by
             ORDER BY t.transfer_date DESC, t.id DESC"
        )->result_array();
    }

    /**
     * Record a transfer. Atomic; a repeated form submission (same request_token) saves once.
     *
     * @param array{transfer_date: string, direction: string, amount: string, description: string, reference: ?string, remarks: ?string, request_token: string} $data
     * @return array{success: bool, message: string, id?: int}
     */
    public function record(array $data): array
    {
        $this->load->model(array('sequence_model', 'fund_model'));
        $this->load->library('audit');
        $db = $this->db;

        $repeat = $db->select('id, transfer_code')->where('request_token', $data['request_token'])->get($this->table)->row_array();
        if ($repeat)
        {
            return array('success' => TRUE, 'message' => 'Transfer '.$repeat['transfer_code'].' was already saved.', 'id' => (int) $repeat['id']);
        }

        $db->trans_begin();
        try
        {
            $year = (int) substr($data['transfer_date'], 0, 4);
            $code = $this->sequence_model->formatted('TRANSFER', 'TRF-'.$year.'-', 4, $year);
            $row = array(
                'transfer_code' => $code,
                'transfer_date' => $data['transfer_date'],
                'direction'     => $data['direction'],
                'amount'        => $data['amount'],
                'description'   => $data['description'],
                'reference'     => $data['reference'],
                'remarks'       => $data['remarks'],
                'status'        => 'Active',
                'request_token' => $data['request_token'],
                'created_by'    => user_id(),
            );
            $db->insert($this->table, $row);
            $id = (int) $db->insert_id();
            if ($id === 0)
            {
                throw new RuntimeException('The transfer could not be saved.');
            }
            unset($row['request_token']);
            $this->audit->log('Transfer Recorded', 'finance', $id, NULL, $row);

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The transfer could not be saved.');
            }
            $db->trans_commit();
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            log_message('error', 'Transfer_model record: '.$e->getMessage().' | DB: '.json_encode($db->error()));
            return array('success' => FALSE, 'message' => 'The transfer could not be saved. Nothing was changed.');
        }

        $bal = $this->fund_model->position()['closing'];
        $message = self::DIRECTIONS[$data['direction']].' '.$code.' of '.money($data['amount']).' recorded. Bank '.money(from_paise($bal['bank'])).', Cash '.money(from_paise($bal['cash'])).', total unchanged at '.money(from_paise($bal['total'])).'.';
        $source = $data['direction'] === 'Bank to Cash' ? 'bank' : 'cash';
        if ($bal[$source] < 0)
        {
            $message .= ' Warning: the '.$source.' balance is now negative - please check the entries.';
        }
        return array('success' => TRUE, 'message' => $message, 'id' => $id);
    }

    /**
     * Cancel a transfer (kept on record, excluded from balances).
     *
     * @return array{success: bool, message: string}
     */
    public function cancel(int $id, string $reason): array
    {
        $this->load->library('audit');
        $db = $this->db;
        $db->trans_begin();
        try
        {
            $row = $this->find_for_update($id);
            if ($row === NULL)
            {
                throw new DomainException('Transfer not found.');
            }
            if ($row['status'] !== 'Active')
            {
                throw new DomainException('Transfer '.$row['transfer_code'].' is already cancelled.');
            }
            $update = array('status' => 'Cancelled', 'cancel_reason' => mb_substr($reason, 0, 255), 'cancelled_by' => user_id(), 'cancelled_at' => date('Y-m-d H:i:s'));
            $db->where('id', $id)->update($this->table, $update);
            $this->audit->log('Transfer Cancelled', 'finance', $id, array('status' => 'Active'), $update);
            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The transfer could not be cancelled.');
            }
            $db->trans_commit();
            return array('success' => TRUE, 'message' => 'Transfer '.$row['transfer_code'].' cancelled; bank and cash balances are restored.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            if ($e instanceof DomainException)
            {
                return array('success' => FALSE, 'message' => $e->getMessage());
            }
            log_message('error', 'Transfer_model cancel: '.$e->getMessage());
            return array('success' => FALSE, 'message' => 'The transfer could not be cancelled.');
        }
    }
}
