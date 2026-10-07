<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Payment collection and cancellation.
 *
 * collect():
 *   BEGIN
 *     lock owner row, lock the owner's open bills (oldest first)
 *     guard: one-time form token (double submit) and duplicate mode+reference
 *     allocate the amount over the open bills (all, or only the selected ones), oldest first
 *     extra money -> kept as advance credit only when "advance" is ticked, otherwise refused
 *     insert payment, allocations, update bills, take the next receipt number, insert receipt
 *   COMMIT   (any failure -> ROLLBACK; nothing is saved and no receipt number is used)
 *
 * cancel(): the original payment, allocations and receipt are kept and marked Cancelled/Reversed;
 * the bills it paid become unpaid again.
 *
 * All arithmetic is in integer paise.
 */
class Payment_engine
{
    /** Modes that must carry a transaction reference */
    const MODES_NEEDING_REFERENCE = array('UPI', 'Bank Transfer', 'Cheque', 'NEFT', 'RTGS');

    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('payment_model', 'receipt_model', 'sequence_model', 'notification_model'));
        $this->CI->load->library(array('audit', 'maintenance_engine'));
    }

    /**
     * @param array{owner_id: int, payment_date: string, amount: string, payment_mode: string, transaction_ref: string,
     *              remarks: string, is_advance: bool, bill_ids: int[], request_token: string} $input
     * @return array{success: bool, message: string, payment_id?: int, receipt_id?: int, receipt_no?: string, duplicate?: bool}
     */
    public function collect(array $input): array
    {
        $db = $this->CI->db;

        // Same form submitted twice (refresh / double click): show the payment already recorded
        $existing = $this->CI->payment_model->find_by_token($input['request_token']);
        if ($existing !== NULL)
        {
            return $this->duplicate_result((int) $existing['id']);
        }

        $db_debug = $db->db_debug;
        $db->db_debug = FALSE;
        $db->trans_begin();
        try
        {
            $owner = $db->query('SELECT * FROM owners WHERE id = ? AND is_deleted = 0 FOR UPDATE', array($input['owner_id']))->row_array();
            if ( ! $owner)
            {
                throw new DomainException('Owner not found.');
            }

            $amount = (int) to_paise($input['amount']);
            if ($amount <= 0)
            {
                throw new DomainException('The payment amount must be greater than zero.');
            }

            $reference = trim($input['transaction_ref']);
            if (in_array($input['payment_mode'], self::MODES_NEEDING_REFERENCE, TRUE) && $reference === '')
            {
                throw new DomainException('Please enter the transaction reference / cheque number for a '.$input['payment_mode'].' payment.');
            }
            if ($reference !== '')
            {
                $dup = $this->CI->payment_model->active_with_reference($input['payment_mode'], $reference);
                if ($dup !== NULL)
                {
                    throw new DomainException('A '.$input['payment_mode'].' payment with reference "'.$reference.'" is already recorded ('
                        .($dup['receipt_no'] ?: 'payment #'.$dup['id']).', '.$dup['owner_name'].', '.money($dup['amount']).', '.fmt_date($dup['payment_date']).').');
                }
            }

            $bills = $db->query(
                "SELECT m.*, h.plot_no, h.house_no FROM maintenance m JOIN houses h ON h.id = m.house_id
                 WHERE m.owner_id = ? AND m.record_status = 'Active' AND m.balance_amount > 0
                 ORDER BY m.period_start, m.id FOR UPDATE",
                array($owner['id'])
            )->result_array();

            $previous_outstanding = 0;
            foreach ($bills as $bill)
            {
                $previous_outstanding += to_paise_signed($bill['balance_amount']);
            }

            $selected = array_map('intval', $input['bill_ids']);
            $targets = empty($selected) ? $bills : array_values(array_filter($bills, function ($b) use ($selected) {
                return in_array((int) $b['id'], $selected, TRUE);
            }));
            if ( ! empty($selected) && count($targets) !== count(array_unique($selected)))
            {
                throw new DomainException('One or more selected bills are already paid or no longer open. Please reload the owner and try again.');
            }

            // Plan the allocation (oldest first)
            $plan = array();
            $remaining = $amount;
            foreach ($targets as $bill)
            {
                if ($remaining <= 0)
                {
                    break;
                }
                $balance = to_paise_signed($bill['balance_amount']);
                $use = min($balance, $remaining);
                $plan[] = array('bill' => $bill, 'paise' => $use);
                $remaining -= $use;
            }
            $allocated = $amount - $remaining;
            $excess = $remaining;

            if ($excess > 0 && ! $input['is_advance'])
            {
                $open = 0;
                foreach ($targets as $bill)
                {
                    $open += to_paise_signed($bill['balance_amount']);
                }
                throw new DomainException('The amount exceeds the '.(empty($selected) ? 'outstanding' : 'selected bills').' of '.money(from_paise($open))
                    .'. Tick "Advance payment" to keep the extra '.money(from_paise($excess)).' as credit for future months.');
            }

            // Payment
            $ok = $db->insert('payments', array(
                'owner_id'         => (int) $owner['id'],
                'payment_date'     => $input['payment_date'],
                'amount'           => from_paise($amount),
                'allocated_amount' => from_paise($allocated),
                'is_advance'       => $excess > 0 ? 1 : 0,
                'payment_mode'     => $input['payment_mode'],
                'transaction_ref'  => $reference !== '' ? $reference : NULL,
                'remarks'          => $input['remarks'] !== '' ? $input['remarks'] : NULL,
                'request_token'    => $input['request_token'],
                'status'           => 'Active',
                'received_by'      => user_id(),
                'created_by'       => user_id(),
            ));
            if ( ! $ok)
            {
                $error = $db->error();
                if ((int) $error['code'] === 1062)
                {
                    $db->trans_rollback();
                    $db->db_debug = $db_debug;
                    $again = $this->CI->payment_model->find_by_token($input['request_token']);
                    if ($again !== NULL)
                    {
                        return $this->duplicate_result((int) $again['id']);
                    }
                }
                $this->fail_db('The payment could not be saved.');
            }
            $payment_id = (int) $db->insert_id();

            // Allocations + bill updates
            $now = date('Y-m-d H:i:s');
            $plots = array();
            $house_nos = array();
            $house_ids = array();
            $months = array();
            $maintenance_amount = 0;
            foreach ($plan as $line)
            {
                $bill = $line['bill'];
                $db->insert('payment_allocations', array(
                    'payment_id'        => $payment_id,
                    'maintenance_id'    => (int) $bill['id'],
                    'amount'            => from_paise($line['paise']),
                    'allocation_source' => 'Payment',
                    'allocated_at'      => $now,
                    'status'            => 'Active',
                    'created_by'        => user_id(),
                ));
                $paid = to_paise_signed($bill['paid_amount']) + $line['paise'];
                $status = Maintenance_engine::status_for(to_paise_signed($bill['amount']), $paid, to_paise_signed($bill['waived_amount']));
                $db->where('id', (int) $bill['id'])->update('maintenance', array('paid_amount' => from_paise($paid), 'payment_status' => $status));

                $plots[$bill['plot_no']] = TRUE;
                $house_nos[$bill['plot_no']] = $bill['house_no'] ?: '-';
                $house_ids[(int) $bill['house_id']] = TRUE;
                $months[] = $bill['period_start'];
                $maintenance_amount += to_paise_signed($bill['amount']);
            }

            if (empty($plots))
            {
                // Pure advance payment: show the owner's current plots on the receipt
                foreach ($db->select('id, plot_no, house_no')->where('owner_id', (int) $owner['id'])->order_by('CAST(plot_no AS UNSIGNED)', '', FALSE)->get('houses')->result_array() as $h)
                {
                    $plots[$h['plot_no']] = TRUE;
                    $house_nos[$h['plot_no']] = $h['house_no'] ?: '-';
                    $house_ids[(int) $h['id']] = TRUE;
                }
            }

            // Receipt (number comes from the locked sequence row inside this transaction)
            $year = (int) substr($input['payment_date'], 0, 4);
            $prefix = trim(association('receipt_prefix')) !== '' ? trim(association('receipt_prefix')) : 'REC';
            $receipt_no = $this->CI->sequence_model->formatted('RECEIPT', $prefix.'-'.$year.'-', 6, $year);

            $period_label = months_covered_label($months);
            if ($excess > 0)
            {
                $period_label = $period_label !== '' ? $period_label.' + Advance' : 'Advance';
            }
            $plot_list = array_keys($plots);
            usort($plot_list, 'strnatcmp');
            $house_list = array();
            foreach ($plot_list as $p)
            {
                $house_list[] = $house_nos[$p];
            }
            $user = current_user();

            $receipt = array(
                'receipt_no'           => $receipt_no,
                'payment_id'           => $payment_id,
                'owner_id'             => (int) $owner['id'],
                'house_id'             => count($house_ids) === 1 ? (int) array_key_first($house_ids) : NULL,
                'receipt_date'         => $input['payment_date'],
                'owner_name'           => $owner['owner_name'],
                'plot_no'              => mb_substr(implode(', ', $plot_list), 0, 100),
                'house_no'             => mb_substr(implode(', ', $house_list), 0, 150),
                'mobile'               => $owner['mobile'],
                'period_label'         => mb_substr($period_label, 0, 150),
                'maintenance_amount'   => from_paise($maintenance_amount),
                'previous_outstanding' => from_paise($previous_outstanding),
                'amount_received'      => from_paise($amount),
                'advance_amount'       => from_paise($excess),
                'balance_after'        => from_paise($previous_outstanding - $allocated),
                'payment_mode'         => $input['payment_mode'],
                'transaction_ref'      => $reference !== '' ? $reference : NULL,
                'received_by_name'     => $user['full_name'] ?? NULL,
                'status'               => 'Active',
                'created_by'           => user_id(),
            );
            if ( ! $db->insert('receipts', $receipt))
            {
                $this->fail_db('The receipt could not be created.');
            }
            $receipt_id = (int) $db->insert_id();

            $this->CI->audit->log('Payment Added', 'payments', $payment_id, NULL, array(
                'owner_id'     => (int) $owner['id'],
                'amount'       => from_paise($amount),
                'allocated'    => from_paise($allocated),
                'advance'      => from_paise($excess),
                'payment_mode' => $input['payment_mode'],
                'reference'    => $reference,
                'bills'        => array_map(function ($l) { return (int) $l['bill']['id']; }, $plan),
            ));
            $this->CI->audit->log('Receipt Generated', 'receipts', $receipt_id, NULL, array('receipt_no' => $receipt_no, 'payment_id' => $payment_id));

            if ($db->trans_status() === FALSE)
            {
                $this->fail_db('The payment could not be saved.');
            }
            $db->trans_commit();
            $db->db_debug = $db_debug;

            $this->CI->notification_model->notify_users('payment', 'Payment received - '.$receipt_no,
                $owner['owner_name'].' (Plot '.implode(', ', $plot_list).') paid '.money(from_paise($amount)).' by '.$input['payment_mode'].'.',
                'receipts/view/'.$receipt_id, 'payments.view');

            return array('success' => TRUE, 'message' => 'Payment recorded successfully.', 'payment_id' => $payment_id, 'receipt_id' => $receipt_id, 'receipt_no' => $receipt_no);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            $db->db_debug = $db_debug;
            return $this->failure($e, 'collect');
        }
    }

    /**
     * Cancel a payment (entered in error, cheque bounced, ...). Nothing is deleted.
     *
     * @return array{success: bool, message: string}
     */
    public function cancel(int $payment_id, string $reason): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $payment = $db->query('SELECT * FROM payments WHERE id = ? FOR UPDATE', array($payment_id))->row_array();
            if ( ! $payment)
            {
                throw new DomainException('Payment not found.');
            }
            if ($payment['status'] !== 'Active')
            {
                throw new DomainException('This payment is already '.strtolower($payment['status']).'.');
            }

            $allocations = $db->query("SELECT * FROM payment_allocations WHERE payment_id = ? AND status = 'Active' FOR UPDATE", array($payment_id))->result_array();
            $now = date('Y-m-d H:i:s');
            $bill_ids = array();
            foreach ($allocations as $a)
            {
                $bill_ids[(int) $a['maintenance_id']] = TRUE;
            }
            $db->where('payment_id', $payment_id)->where('status', 'Active')->update('payment_allocations', array('status' => 'Reversed', 'reversed_at' => $now));

            foreach (array_keys($bill_ids) as $bill_id)
            {
                $bill = $db->query('SELECT * FROM maintenance WHERE id = ? FOR UPDATE', array($bill_id))->row_array();
                $paid = (int) to_paise_signed($db->query("SELECT COALESCE(SUM(amount), 0) AS paid FROM payment_allocations WHERE maintenance_id = ? AND status = 'Active'", array($bill_id))->row()->paid);
                $status = Maintenance_engine::status_for(to_paise_signed($bill['amount']), $paid, to_paise_signed($bill['waived_amount']));
                $db->where('id', $bill_id)->update('maintenance', array('paid_amount' => from_paise($paid), 'payment_status' => $status));
            }

            $db->where('id', $payment_id)->update('payments', array(
                'status'        => 'Cancelled',
                'cancel_reason' => $reason,
                'cancelled_by'  => user_id(),
                'cancelled_at'  => $now,
            ));
            $receipt = $db->get_where('receipts', array('payment_id' => $payment_id), 1)->row_array();
            if ($receipt)
            {
                $db->where('id', (int) $receipt['id'])->update('receipts', array('status' => 'Cancelled', 'cancelled_at' => $now, 'pdf_path' => NULL));
            }

            $this->CI->audit->log('Payment Cancelled', 'payments', $payment_id,
                array('status' => 'Active', 'receipt_no' => $receipt['receipt_no'] ?? NULL),
                array('status' => 'Cancelled', 'reason' => $reason, 'bills_reopened' => array_keys($bill_ids)));

            if ($db->trans_status() === FALSE)
            {
                $this->fail_db('The payment could not be cancelled.');
            }
            $db->trans_commit();

            if ($receipt && ! empty($receipt['pdf_path']))
            {
                $this->CI->load->library('receipt_pdf');
                $this->CI->receipt_pdf->forget($receipt['pdf_path']);
            }
            return array('success' => TRUE, 'message' => 'Payment cancelled. Receipt '.($receipt['receipt_no'] ?? '').' is marked CANCELLED and '.count($bill_ids).' bill(s) are unpaid again.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'cancel');
        }
    }

    /**
     * Apply an owner's unused advance credit to their open bills now.
     *
     * @return array{success: bool, message: string}
     */
    public function apply_credit(int $owner_id): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $db->query('SELECT id FROM owners WHERE id = ? FOR UPDATE', array($owner_id));
            $before = $this->credit_of($owner_id);
            $settled = $this->CI->maintenance_engine->apply_advance_credit($owner_id);
            $after = $this->credit_of($owner_id);
            $used = $before - $after;
            if ($used > 0)
            {
                $this->CI->audit->log('Advance Credit Applied', 'payments', $owner_id, array('credit' => from_paise($before)), array('credit' => from_paise($after), 'bills_settled' => $settled));
            }
            if ($db->trans_status() === FALSE)
            {
                $this->fail_db('The credit could not be applied.');
            }
            $db->trans_commit();
            if ($used === 0)
            {
                return array('success' => FALSE, 'message' => 'There are no open bills to apply the credit to. It will be used automatically when the next month is generated.');
            }
            return array('success' => TRUE, 'message' => money(from_paise($used)).' of advance credit applied ('.$settled.' bill(s) fully paid). Remaining credit: '.money(from_paise($after)).'.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'apply credit');
        }
    }

    /* ------------------------------------------------------------------ */

    private function credit_of(int $owner_id): int
    {
        return to_paise_signed($this->CI->db->query("SELECT COALESCE(SUM(unallocated_amount), 0) AS c FROM payments WHERE owner_id = ? AND status = 'Active'", array($owner_id))->row()->c);
    }

    /**
     * @return array<string, mixed>
     */
    private function duplicate_result(int $payment_id): array
    {
        $receipt = $this->CI->db->get_where('receipts', array('payment_id' => $payment_id), 1)->row_array();
        return array(
            'success'    => TRUE,
            'duplicate'  => TRUE,
            'message'    => 'This payment was already recorded'.($receipt ? ' (receipt '.$receipt['receipt_no'].')' : '').'. It was not saved twice.',
            'payment_id' => $payment_id,
            'receipt_id' => $receipt ? (int) $receipt['id'] : 0,
            'receipt_no' => $receipt['receipt_no'] ?? '',
        );
    }

    /**
     * @return never
     */
    private function fail_db(string $message): void
    {
        $error = $this->CI->db->error();
        if ((int) $error['code'] !== 0)
        {
            log_message('error', 'Payment_engine DB error '.$error['code'].': '.$error['message']);
        }
        throw new RuntimeException($message);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function failure(Throwable $e, string $context): array
    {
        if ($e instanceof DomainException)
        {
            return array('success' => FALSE, 'message' => $e->getMessage());
        }
        log_message('error', 'Payment_engine '.$context.': '.$e->getMessage());
        return array('success' => FALSE, 'message' => ($e instanceof RuntimeException ? $e->getMessage().' ' : '').'Nothing was saved. Please try again.');
    }
}
