<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Monthly maintenance business rules.
 *
 *  - preview()/generate(): one bill per active plot with an active owner whose maintenance
 *    has started, at the rate in force for the plot's category that month.
 *    A month can be generated once; plots added later are billed with "generate remaining",
 *    which adds only the missing bills to the same batch. Unique keys block duplicates.
 *  - Advance credit (unallocated money on advance payments) is applied automatically to the
 *    owner's oldest open bills, oldest payment first.
 *  - waive() / reverse_adjustment(): waivers and discounts never exceed the balance.
 *  - cancel() / restore(): only bills with no payments and no adjustments can be cancelled.
 *
 * All amounts are handled as integer paise. Every write method runs in a transaction.
 */
class Maintenance_engine
{
    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('maintenance_model', 'rate_model', 'notification_model'));
        $this->CI->load->library('audit');
    }

    /**
     * Payment status for amounts in paise (single source of truth, also used by Step 7 payments).
     */
    public static function status_for(int $amount, int $paid, int $waived): string
    {
        if ($amount > 0 && $waived >= $amount)
        {
            return 'Waived';
        }
        if ($amount - $paid - $waived <= 0)
        {
            return 'Paid';
        }
        if ($paid > 0 || $waived > 0)
        {
            return 'Partially Paid';
        }
        return 'Pending';
    }

    /* ==================================================================
     * Generation
     * ================================================================ */

    /**
     * What generating a month would do, without writing anything.
     *
     * @return array<string, mixed> {
     *   period_label, batch_exists, already_generated (nothing left to bill), errors[],
     *   rows[] (plot, owner, category, rate, amount, action: bill|billed|skip, reason),
     *   totals {plots, owners, amount, by_category{cat: {plots, rate, amount}}}, skipped, already_billed
     * }
     */
    public function preview(int $year, int $month): array
    {
        $period_start = sprintf('%04d-%02d-01', $year, $month);
        $rates = $this->CI->rate_model->rates_for_month($period_start);
        $category_sql = $this->CI->rate_model->category_sql('h.built_status');

        $houses = $this->CI->db->query(
            "SELECT h.id AS house_id, h.plot_no, h.house_no, h.block, h.built_status, h.status AS house_status,
                    ($category_sql) AS plot_category,
                    o.id AS owner_id, o.owner_code, o.owner_name, o.status AS owner_status, o.is_deleted,
                    o.maintenance_start_date,
                    m.id AS existing_bill_id, m.record_status AS existing_status
             FROM houses h
             LEFT JOIN owners o ON o.id = h.owner_id
             LEFT JOIN maintenance m ON m.house_id = h.id AND m.billing_year = ? AND m.billing_month = ? AND m.charge_type = 'Monthly'
             WHERE h.status = 'Active' OR m.id IS NOT NULL
             ORDER BY h.block, CAST(h.plot_no AS UNSIGNED), h.plot_no",
            array($year, $month)
        )->result_array();

        $result = array(
            'period_label'     => period_label($year, $month),
            'period_start'     => $period_start,
            'batch_exists'     => $this->CI->db->where(array('billing_year' => $year, 'billing_month' => $month))->count_all_results('maintenance_batches') > 0,
            'errors'           => array(),
            'rows'             => array(),
            'rates'            => array(),
            'totals'           => array('plots' => 0, 'owners' => 0, 'amount' => '0.00', 'by_category' => array()),
            'skipped'          => 0,
            'already_billed'   => 0,
        );
        foreach ($rates as $category => $rate)
        {
            $result['rates'][$category] = $rate !== NULL ? array('amount' => $rate['amount'], 'effective_from' => $rate['effective_from'], 'id' => (int) $rate['id']) : NULL;
        }

        $owners = array();
        $total = 0;
        $missing_rate = array();
        foreach ($houses as $h)
        {
            $row = array(
                'house_id'      => (int) $h['house_id'],
                'plot_no'       => $h['plot_no'],
                'house_no'      => $h['house_no'],
                'block'         => $h['block'],
                'owner_id'      => $h['owner_id'] !== NULL ? (int) $h['owner_id'] : NULL,
                'owner_code'    => $h['owner_code'],
                'owner_name'    => $h['owner_name'],
                'plot_category' => $h['plot_category'],
                'rate_id'       => NULL,
                'amount'        => '0.00',
                'action'        => 'skip',
                'reason'        => '',
            );

            if ($h['existing_bill_id'] !== NULL)
            {
                $row['action'] = 'billed';
                $row['reason'] = $h['existing_status'] === 'Cancelled' ? 'Bill exists (cancelled) - restore it from the bill page' : 'Already billed';
                $result['already_billed']++;
            }
            elseif ($h['house_status'] !== 'Active')
            {
                $row['reason'] = 'Plot inactive';
            }
            elseif ($h['owner_id'] === NULL || (int) $h['is_deleted'] === 1)
            {
                $row['reason'] = 'No owner assigned';
            }
            elseif ($h['owner_status'] !== 'Active')
            {
                $row['reason'] = 'Owner inactive';
            }
            elseif ($h['maintenance_start_date'] !== NULL && date('Y-m-01', strtotime($h['maintenance_start_date'])) > $period_start)
            {
                $row['reason'] = 'Maintenance starts '.date('M Y', strtotime($h['maintenance_start_date']));
            }
            elseif ( ! isset($rates[$h['plot_category']]) || $rates[$h['plot_category']] === NULL)
            {
                $row['reason'] = 'No '.$h['plot_category'].' rate for this month';
                $missing_rate[$h['plot_category']] = TRUE;
            }
            else
            {
                $rate = $rates[$h['plot_category']];
                $row['action'] = 'bill';
                $row['rate_id'] = (int) $rate['id'];
                $row['amount'] = $rate['amount'];
                $owners[(int) $h['owner_id']] = TRUE;
                $paise = to_paise_signed($rate['amount']);
                $total += $paise;
                $cat = &$result['totals']['by_category'][$h['plot_category']];
                if ( ! isset($cat))
                {
                    $cat = array('plots' => 0, 'rate' => $rate['amount'], 'amount' => 0);
                }
                $cat['plots']++;
                $cat['amount'] += $paise;
                unset($cat);
                $result['totals']['plots']++;
            }

            if ($row['action'] === 'skip')
            {
                $result['skipped']++;
            }
            $result['rows'][] = $row;
        }

        foreach ($result['totals']['by_category'] as &$cat)
        {
            $cat['amount'] = from_paise($cat['amount']);
        }
        unset($cat);
        $result['totals']['owners'] = count($owners);
        $result['totals']['amount'] = from_paise($total);

        foreach (array_keys($missing_rate) as $category)
        {
            $result['errors'][] = 'No '.$category.' rate is in force for '.$result['period_label'].'. Add one under Maintenance Rates before generating.';
        }
        $result['already_generated'] = $result['batch_exists'] && $result['totals']['plots'] === 0;
        return $result;
    }

    /**
     * Generate bills for a month (or the remaining plots of an already generated month).
     *
     * @return array{success: bool, message: string, batch_id?: int, created?: int, amount?: string, advance_applied?: int}
     */
    public function generate(int $year, int $month, string $due_date): array
    {
        $label = period_label($year, $month);
        $db = $this->CI->db;
        $db_debug = $db->db_debug;
        $db->db_debug = FALSE;
        $db->trans_begin();
        try
        {
            // Lock the batch row (if any) so two people cannot generate the same month at once
            $batch = $db->query('SELECT * FROM maintenance_batches WHERE billing_year = ? AND billing_month = ? FOR UPDATE', array($year, $month))->row_array();

            $preview = $this->preview($year, $month);
            if ( ! empty($preview['errors']))
            {
                throw new DomainException(implode(' ', $preview['errors']));
            }
            if ($preview['totals']['plots'] === 0)
            {
                throw new DomainException($batch
                    ? 'Maintenance for '.$label.' has already been generated.'
                    : 'There are no billable plots for '.$label.' (check owners, plots and maintenance start dates).');
            }

            $now = date('Y-m-d H:i:s');
            if ( ! $batch)
            {
                if ( ! $db->insert('maintenance_batches', array(
                    'billing_year'  => $year,
                    'billing_month' => $month,
                    'due_date'      => $due_date,
                    'total_records' => 0,
                    'total_amount'  => '0.00',
                    'generated_by'  => user_id(),
                    'generated_at'  => $now,
                )))
                {
                    $this->throw_db('Maintenance for '.$label.' has already been generated.');
                }
                $batch_id = (int) $db->insert_id();
                $remaining_run = FALSE;
            }
            else
            {
                $batch_id = (int) $batch['id'];
                $due_date = $batch['due_date'];
                $remaining_run = TRUE;
            }

            $created = 0;
            $total = 0;
            $owners = array();
            foreach ($preview['rows'] as $row)
            {
                if ($row['action'] !== 'bill')
                {
                    continue;
                }
                $ok = $db->insert('maintenance', array(
                    'batch_id'       => $batch_id,
                    'owner_id'       => $row['owner_id'],
                    'house_id'       => $row['house_id'],
                    'charge_type'    => 'Monthly',
                    'plot_category'  => $row['plot_category'],
                    'rate_id'        => $row['rate_id'],
                    'billing_year'   => $year,
                    'billing_month'  => $month,
                    'period_start'   => $preview['period_start'],
                    'due_date'       => $due_date,
                    'amount'         => $row['amount'],
                    'payment_status' => to_paise_signed($row['amount']) > 0 ? 'Pending' : 'Paid',
                    'record_status'  => 'Active',
                    'generated_at'   => $now,
                    'created_by'     => user_id(),
                ));
                if ( ! $ok)
                {
                    $this->throw_db('Plot '.$row['plot_no'].' is already billed for '.$label.'. Nothing was generated; please refresh the preview.');
                }
                $created++;
                $total += to_paise_signed($row['amount']);
                $owners[$row['owner_id']] = TRUE;
            }

            $this->refresh_batch_totals($batch_id);

            $advance_applied = 0;
            foreach (array_keys($owners) as $owner_id)
            {
                $advance_applied += $this->apply_advance_credit((int) $owner_id);
            }

            $this->CI->audit->log($remaining_run ? 'Maintenance Generated (Remaining)' : 'Maintenance Generated', 'maintenance', $batch_id, NULL, array(
                'billing_year'     => $year,
                'billing_month'    => $month,
                'due_date'         => $due_date,
                'records'          => $created,
                'total_amount'     => from_paise($total),
                'advance_adjusted' => $advance_applied,
            ));

            if ($db->trans_status() === FALSE)
            {
                $this->throw_db('Maintenance could not be generated.');
            }
            $db->trans_commit();
            $db->db_debug = $db_debug;

            $message = ($remaining_run ? 'Added ' : 'Generated ').$created.' bill(s) for '.$label.', total '.money(from_paise($total)).'.';
            if ($advance_applied > 0)
            {
                $message .= ' '.$advance_applied.' bill(s) settled automatically from advance credit.';
            }
            $this->CI->notification_model->notify_users('maintenance', $label.' maintenance '.($remaining_run ? 'updated' : 'generated'), $message, 'maintenance?period='.sprintf('%04d-%02d', $year, $month), 'maintenance.view');

            return array('success' => TRUE, 'message' => $message, 'batch_id' => $batch_id, 'created' => $created, 'amount' => from_paise($total), 'advance_applied' => $advance_applied);
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            $db->db_debug = $db_debug;
            return $this->failure($e, 'generate');
        }
    }

    /**
     * Use an owner's unallocated advance money on their open bills (oldest bill first, oldest payment first).
     * Runs inside the caller's transaction. Returns the number of bills fully settled.
     */
    public function apply_advance_credit(int $owner_id): int
    {
        $db = $this->CI->db;
        $credits = $db->query(
            "SELECT id, amount, allocated_amount FROM payments
             WHERE owner_id = ? AND status = 'Active' AND is_advance = 1 AND unallocated_amount > 0
             ORDER BY payment_date, id FOR UPDATE",
            array($owner_id)
        )->result_array();
        if (empty($credits))
        {
            return 0;
        }

        $bills = $db->query(
            "SELECT id, amount, paid_amount, waived_amount FROM maintenance
             WHERE owner_id = ? AND record_status = 'Active' AND balance_amount > 0
             ORDER BY period_start, id FOR UPDATE",
            array($owner_id)
        )->result_array();

        $settled = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($bills as $bill)
        {
            $amount = to_paise_signed($bill['amount']);
            $paid = to_paise_signed($bill['paid_amount']);
            $waived = to_paise_signed($bill['waived_amount']);
            $balance = $amount - $paid - $waived;

            foreach ($credits as &$credit)
            {
                if ($balance <= 0)
                {
                    break;
                }
                $available = to_paise_signed($credit['amount']) - to_paise_signed($credit['allocated_amount']);
                if ($available <= 0)
                {
                    continue;
                }
                $use = min($available, $balance);
                $db->insert('payment_allocations', array(
                    'payment_id'        => (int) $credit['id'],
                    'maintenance_id'    => (int) $bill['id'],
                    'amount'            => from_paise($use),
                    'allocation_source' => 'Advance Adjustment',
                    'allocated_at'      => $now,
                    'status'            => 'Active',
                    'created_by'        => user_id(),
                ));
                $credit['allocated_amount'] = from_paise(to_paise_signed($credit['allocated_amount']) + $use);
                $db->where('id', (int) $credit['id'])->update('payments', array('allocated_amount' => $credit['allocated_amount']));
                $paid += $use;
                $balance -= $use;
            }
            unset($credit);

            if ($paid !== to_paise_signed($bill['paid_amount']))
            {
                $status = self::status_for($amount, $paid, $waived);
                $db->where('id', (int) $bill['id'])->update('maintenance', array('paid_amount' => from_paise($paid), 'payment_status' => $status));
                if ($status === 'Paid')
                {
                    $settled++;
                }
            }
        }
        return $settled;
    }

    /* ==================================================================
     * Waivers, discounts, cancellation
     * ================================================================ */

    /**
     * @return array{success: bool, message: string}
     */
    public function waive(int $maintenance_id, string $type, string $amount, string $date, string $reason): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $bill = $this->lock_bill($maintenance_id);
            if ($bill['record_status'] !== 'Active')
            {
                throw new DomainException('A cancelled bill cannot be waived.');
            }
            $amt = (int) to_paise($amount);
            $total = to_paise_signed($bill['amount']);
            $paid = to_paise_signed($bill['paid_amount']);
            $waived = to_paise_signed($bill['waived_amount']);
            $balance = $total - $paid - $waived;
            if ($amt <= 0)
            {
                throw new DomainException('The amount must be greater than zero.');
            }
            if ($amt > $balance)
            {
                throw new DomainException('The '.strtolower($type).' cannot exceed the balance of '.money(from_paise($balance)).'.');
            }

            $db->insert('maintenance_adjustments', array(
                'maintenance_id'  => $maintenance_id,
                'adjustment_type' => $type,
                'amount'          => from_paise($amt),
                'adjustment_date' => $date,
                'reason'          => $reason,
                'status'          => 'Active',
                'created_by'      => user_id(),
            ));
            $adjustment_id = (int) $db->insert_id();
            $new_waived = $waived + $amt;
            $status = self::status_for($total, $paid, $new_waived);
            $db->where('id', $maintenance_id)->update('maintenance', array('waived_amount' => from_paise($new_waived), 'payment_status' => $status));

            $this->CI->audit->log('Maintenance '.($type === 'Waiver' ? 'Waived' : 'Discounted'), 'maintenance', $maintenance_id,
                array('waived_amount' => $bill['waived_amount'], 'payment_status' => $bill['payment_status']),
                array('waived_amount' => from_paise($new_waived), 'payment_status' => $status, 'adjustment_id' => $adjustment_id, 'reason' => $reason));

            $this->commit_or_fail('The '.strtolower($type).' could not be saved.');
            return array('success' => TRUE, 'message' => $type.' of '.money(from_paise($amt)).' recorded. Bill is now '.$status.'.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'waive');
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function reverse_adjustment(int $adjustment_id, string $reason): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $adj = $db->query('SELECT * FROM maintenance_adjustments WHERE id = ? FOR UPDATE', array($adjustment_id))->row_array();
            if ( ! $adj || $adj['status'] !== 'Active')
            {
                throw new DomainException('This adjustment is already reversed.');
            }
            $bill = $this->lock_bill((int) $adj['maintenance_id']);
            $total = to_paise_signed($bill['amount']);
            $paid = to_paise_signed($bill['paid_amount']);
            $new_waived = to_paise_signed($bill['waived_amount']) - to_paise_signed($adj['amount']);
            $status = self::status_for($total, $paid, max(0, $new_waived));

            $db->where('id', $adjustment_id)->update('maintenance_adjustments', array('status' => 'Reversed'));
            $db->where('id', (int) $bill['id'])->update('maintenance', array('waived_amount' => from_paise(max(0, $new_waived)), 'payment_status' => $status));
            $this->CI->audit->log('Adjustment Reversed', 'maintenance', $bill['id'],
                array('adjustment_id' => $adjustment_id, 'waived_amount' => $bill['waived_amount']),
                array('waived_amount' => from_paise(max(0, $new_waived)), 'payment_status' => $status, 'reason' => $reason));

            $this->commit_or_fail('The adjustment could not be reversed.');
            return array('success' => TRUE, 'message' => $adj['adjustment_type'].' of '.money($adj['amount']).' reversed. Bill is now '.$status.'.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'reverse adjustment');
        }
    }

    /**
     * Cancel a bill raised in error (only when nothing has been paid or adjusted on it).
     *
     * @return array{success: bool, message: string}
     */
    public function cancel(int $maintenance_id, string $reason): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $bill = $this->lock_bill($maintenance_id);
            if ($bill['record_status'] === 'Cancelled')
            {
                throw new DomainException('This bill is already cancelled.');
            }
            $active_allocations = $db->where(array('maintenance_id' => $maintenance_id, 'status' => 'Active'))->count_all_results('payment_allocations');
            if ($active_allocations > 0 || to_paise_signed($bill['paid_amount']) > 0)
            {
                throw new DomainException('This bill has payments against it and cannot be cancelled. Cancel the payment first, or waive the balance.');
            }
            if (to_paise_signed($bill['waived_amount']) > 0)
            {
                throw new DomainException('Reverse the waiver/discount on this bill before cancelling it.');
            }

            $note = 'Cancelled '.date('d-M-Y').': '.$reason;
            $description = trim(($bill['description'] ? $bill['description'].' | ' : '').$note);
            $db->where('id', $maintenance_id)->update('maintenance', array('record_status' => 'Cancelled', 'description' => mb_substr($description, 0, 255)));
            if ($bill['batch_id'] !== NULL)
            {
                $this->refresh_batch_totals((int) $bill['batch_id']);
            }
            $this->CI->audit->log('Maintenance Cancelled', 'maintenance', $maintenance_id, array('record_status' => 'Active'), array('record_status' => 'Cancelled', 'reason' => $reason));

            $this->commit_or_fail('The bill could not be cancelled.');
            return array('success' => TRUE, 'message' => 'Bill cancelled. It no longer counts towards dues.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'cancel');
        }
    }

    /**
     * Restore a cancelled bill (and apply any advance credit the owner has).
     *
     * @return array{success: bool, message: string}
     */
    public function restore(int $maintenance_id): array
    {
        $db = $this->CI->db;
        $db->trans_begin();
        try
        {
            $bill = $this->lock_bill($maintenance_id);
            if ($bill['record_status'] !== 'Cancelled')
            {
                throw new DomainException('This bill is not cancelled.');
            }
            $db->where('id', $maintenance_id)->update('maintenance', array('record_status' => 'Active'));
            if ($bill['batch_id'] !== NULL)
            {
                $this->refresh_batch_totals((int) $bill['batch_id']);
            }
            $this->apply_advance_credit((int) $bill['owner_id']);
            $this->CI->audit->log('Maintenance Restored', 'maintenance', $maintenance_id, array('record_status' => 'Cancelled'), array('record_status' => 'Active'));

            $this->commit_or_fail('The bill could not be restored.');
            return array('success' => TRUE, 'message' => 'Bill restored.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            return $this->failure($e, 'restore');
        }
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function lock_bill(int $id): array
    {
        $bill = $this->CI->db->query('SELECT * FROM maintenance WHERE id = ? FOR UPDATE', array($id))->row_array();
        if ( ! $bill)
        {
            throw new DomainException('Bill not found.');
        }
        return $bill;
    }

    private function refresh_batch_totals(int $batch_id): void
    {
        $this->CI->db->query(
            "UPDATE maintenance_batches b
             SET total_records = (SELECT COUNT(*) FROM maintenance m WHERE m.batch_id = b.id AND m.record_status = 'Active'),
                 total_amount  = (SELECT COALESCE(SUM(amount), 0) FROM maintenance m WHERE m.batch_id = b.id AND m.record_status = 'Active')
             WHERE b.id = ?",
            array($batch_id)
        );
    }

    private function commit_or_fail(string $message): void
    {
        if ($this->CI->db->trans_status() === FALSE)
        {
            $this->throw_db($message);
        }
        $this->CI->db->trans_commit();
    }

    /**
     * @return never
     */
    private function throw_db(string $message): void
    {
        $error = $this->CI->db->error();
        if ((int) $error['code'] !== 0)
        {
            log_message('error', 'Maintenance_engine DB error '.$error['code'].': '.$error['message']);
        }
        throw new DomainException($message);
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
        log_message('error', 'Maintenance_engine '.$context.': '.$e->getMessage());
        return array('success' => FALSE, 'message' => 'An unexpected error occurred. Nothing was changed.');
    }
}
