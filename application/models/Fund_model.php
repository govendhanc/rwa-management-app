<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Association funds (bank + cash): the opening balance brought forward and the fund ledger.
 *
 * The portal has no separate ledger table - funds are derived from the existing transaction
 * tables (payments, other_incomes, expenses; Active rows only). This model adds the opening
 * balance on top of them with one rule:
 *
 *   With an opening balance dated D:  funds = opening (bank + cash)
 *                                            + payments + other income - expenses dated AFTER D
 *   Transactions dated on or before D are already contained in the verified opening figure and
 *   are not counted again. Without an opening balance the original behaviour is unchanged
 *   (funds = all payments + other income - expenses).
 *
 * Bank / cash: payment mode "Cash" is cash; every other mode (UPI, Bank Transfer, Cheque, NEFT,
 * RTGS, Other) is the association's single configured bank account. Transfers (fund_transfers)
 * move money between the two: a withdrawal (Bank to Cash) lowers bank and raises cash, a deposit
 * does the reverse; the total never changes, so a withdrawal and the expense paid from that cash
 * are not counted twice.
 *
 * Outstanding maintenance is never part of funds - only money actually received or spent.
 */
class Fund_model extends MY_Model
{
    protected $table = 'fund_opening_balances';

    /** @var array<string, string> source table => date column */
    const SOURCES = array('payments' => 'payment_date', 'other_incomes' => 'income_date', 'expenses' => 'expense_date');

    /** @var array<string, mixed>|null|false cached active row (FALSE = not loaded) */
    private $active = FALSE;

    /**
     * @return array<string, mixed>|null The opening balance in force.
     */
    public function active_opening(): ?array
    {
        if ($this->active === FALSE)
        {
            $row = $this->db->query(
                "SELECT f.*, u.full_name AS created_by_name FROM fund_opening_balances f
                 LEFT JOIN users u ON u.id = f.created_by WHERE f.status = 'Active' LIMIT 1"
            )->row_array();
            $this->active = $row ?: NULL;
        }
        return $this->active;
    }

    /**
     * Every entry, newest first (the active one and any it replaced).
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        return $this->db->query(
            "SELECT f.*, u.full_name AS created_by_name, s.full_name AS superseded_by_name
             FROM fund_opening_balances f
             LEFT JOIN users u ON u.id = f.created_by
             LEFT JOIN users s ON s.id = f.superseded_by
             ORDER BY f.id DESC"
        )->result_array();
    }

    /**
     * Current fund position: opening, collections, other income, expenses and closing, each split
     * into bank / cash / total (paise), plus collections by month since the opening date.
     *
     * @return array<string, mixed>
     */
    public function position(): array
    {
        $ob = $this->active_opening();
        $after = $ob !== NULL ? (string) $ob['opening_date'] : NULL;

        $opening = array(
            'bank'  => $ob !== NULL ? to_paise_signed($ob['bank_amount']) : 0,
            'cash'  => $ob !== NULL ? to_paise_signed($ob['cash_amount']) : 0,
        );
        $opening['total'] = $opening['bank'] + $opening['cash'];

        $collections = $this->sums('payments', $after, NULL);
        $other = $this->sums('other_incomes', $after, NULL);
        $expenses = $this->sums('expenses', $after, NULL);
        $transfers = $this->transfer_sums($after, NULL);

        $closing = array();
        foreach (array('bank', 'cash', 'total') as $k)
        {
            $closing[$k] = $opening[$k] + $collections[$k] + $other[$k] - $expenses[$k] + $transfers[$k];
        }

        return array(
            'has_opening'  => $ob !== NULL,
            'opening_row'  => $ob,
            'since'        => $after !== NULL ? date('Y-m-d', strtotime($after.' +1 day')) : NULL,
            'opening'      => $opening,
            'collections'  => $collections,
            'other_income' => $other,
            'expenses'     => $expenses,
            'transfers'    => $transfers,
            'closing'      => $closing,
            'by_month'     => $after !== NULL ? $this->collections_by_month($after) : array(),
            'before'       => $after !== NULL ? $this->recorded_up_to($after) : NULL,
        );
    }

    /**
     * Funds at the end of $date (paise). Used for report opening / closing balances.
     */
    public function balance_at(string $date): int
    {
        return $this->balances_at($date)['total'];
    }

    /**
     * Bank, cash and total funds at the end of $date (paise).
     *
     * @return array{bank: int, cash: int, total: int}
     */
    public function balances_at(string $date): array
    {
        $ob = $this->active_opening();
        $after = NULL;
        $bal = array('bank' => 0, 'cash' => 0);
        if ($ob !== NULL && $date >= $ob['opening_date'])
        {
            $after = (string) $ob['opening_date'];
            $bal = array('bank' => to_paise_signed($ob['bank_amount']), 'cash' => to_paise_signed($ob['cash_amount']));
        }
        $in1 = $this->sums('payments', $after, $date);
        $in2 = $this->sums('other_incomes', $after, $date);
        $out = $this->sums('expenses', $after, $date);
        $tr = $this->transfer_sums($after, $date);
        foreach (array('bank', 'cash') as $k)
        {
            $bal[$k] += $in1[$k] + $in2[$k] - $out[$k] + $tr[$k];
        }
        $bal['total'] = $bal['bank'] + $bal['cash'];
        return $bal;
    }

    /**
     * Fund ledger for a date range: opening balances, every money movement (Active entries only)
     * with bank / cash in / out and running balances, and closing balances (all paise).
     * With an opening balance the range starts no earlier than the day after its date - earlier
     * entries are already inside the verified opening figure.
     *
     * @return array<string, mixed>
     */
    public function ledger(string $from, string $to): array
    {
        $ob = $this->active_opening();
        $clamped = FALSE;
        if ($ob !== NULL)
        {
            $first = date('Y-m-d', strtotime($ob['opening_date'].' +1 day'));
            if ($from < $first)
            {
                $from = $first;
                $clamped = TRUE;
            }
        }
        if ($to < $from)
        {
            $to = $from;
        }

        $opening = $this->balances_at(date('Y-m-d', strtotime($from.' -1 day')));
        $rows = $this->db->query(
            "SELECT * FROM (
                SELECT 'payment' AS kind, p.id, p.payment_date AS d, COALESCE(r.receipt_no, CONCAT('Payment #', p.id)) AS ref,
                       CONCAT(o.owner_code, ' - ', o.owner_name) AS particulars, p.payment_mode AS mode, p.amount, NULL AS direction, 1 AS sort_order
                FROM payments p JOIN owners o ON o.id = p.owner_id LEFT JOIN receipts r ON r.payment_id = p.id
                WHERE p.status = 'Active' AND p.payment_date BETWEEN ? AND ?
                UNION ALL
                SELECT 'income', i.id, i.income_date, i.income_code, CONCAT(i.source, IF(i.description IS NULL OR i.description = '', '', CONCAT(' - ', i.description))),
                       i.payment_mode, i.amount, NULL, 2
                FROM other_incomes i WHERE i.status = 'Active' AND i.income_date BETWEEN ? AND ?
                UNION ALL
                SELECT 'transfer', t.id, t.transfer_date, t.transfer_code, t.description, NULL, t.amount, t.direction, 3
                FROM fund_transfers t WHERE t.status = 'Active' AND t.transfer_date BETWEEN ? AND ?
                UNION ALL
                SELECT 'expense', e.id, e.expense_date, e.expense_code, CONCAT(c.name, ': ', e.description), e.payment_mode, e.amount, NULL, 4
                FROM expenses e JOIN expense_categories c ON c.id = e.category_id
                WHERE e.status = 'Active' AND e.expense_date BETWEEN ? AND ?
             ) x ORDER BY d, sort_order, id",
            array($from, $to, $from, $to, $from, $to, $from, $to)
        )->result_array();

        $labels = array('payment' => 'Maintenance collection', 'income' => 'Other income', 'expense' => 'Expense');
        $bank = $opening['bank'];
        $cash = $opening['cash'];
        $totals = array('bank_in' => 0, 'bank_out' => 0, 'cash_in' => 0, 'cash_out' => 0);
        foreach ($rows as &$row)
        {
            $amt = to_paise_signed($row['amount']);
            $row['bank_in'] = $row['bank_out'] = $row['cash_in'] = $row['cash_out'] = 0;
            if ($row['kind'] === 'transfer')
            {
                $withdrawal = $row['direction'] === 'Bank to Cash';
                $row['type'] = $withdrawal ? 'Withdrawal (bank to cash)' : 'Deposit (cash to bank)';
                $row[$withdrawal ? 'bank_out' : 'cash_out'] = $amt;
                $row[$withdrawal ? 'cash_in' : 'bank_in'] = $amt;
            }
            else
            {
                $row['type'] = $labels[$row['kind']];
                $account = $row['mode'] === 'Cash' ? 'cash' : 'bank';
                $row[$account.($row['kind'] === 'expense' ? '_out' : '_in')] = $amt;
            }
            foreach ($totals as $k => $v)
            {
                $totals[$k] += $row[$k];
            }
            $bank += $row['bank_in'] - $row['bank_out'];
            $cash += $row['cash_in'] - $row['cash_out'];
            $row['bank_balance'] = $bank;
            $row['cash_balance'] = $cash;
            $row['balance'] = $bank + $cash;
        }
        unset($row);

        return array(
            'from'    => $from,
            'to'      => $to,
            'clamped' => $clamped,
            'opening' => $opening,
            'rows'    => $rows,
            'totals'  => $totals,
            'closing' => array('bank' => $bank, 'cash' => $cash, 'total' => $bank + $cash),
        );
    }

    /**
     * Latest date of any money movement (so a ledger "to date" never cuts off a recorded entry).
     */
    public function last_entry_date(): string
    {
        $row = $this->db->query(
            "SELECT GREATEST(
                COALESCE((SELECT MAX(payment_date) FROM payments WHERE status = 'Active'), '1000-01-01'),
                COALESCE((SELECT MAX(income_date) FROM other_incomes WHERE status = 'Active'), '1000-01-01'),
                COALESCE((SELECT MAX(expense_date) FROM expenses WHERE status = 'Active'), '1000-01-01'),
                COALESCE((SELECT MAX(transfer_date) FROM fund_transfers WHERE status = 'Active'), '1000-01-01')
             ) AS d"
        )->row();
        return max(date('Y-m-d'), (string) $row->d);
    }

    /**
     * Net of transactions (payments + other income - expenses) in (after, upto], in paise.
     * NULL bounds are open.
     */
    public function net(?string $after, ?string $upto): int
    {
        return $this->sums('payments', $after, $upto)['total']
            + $this->sums('other_incomes', $after, $upto)['total']
            - $this->sums('expenses', $after, $upto)['total'];
    }

    /**
     * Transactions already recorded in the portal on or before the opening date - shown so the
     * administrator knows they are treated as part of the verified opening figure.
     *
     * @return array{payments: int, other_incomes: int, expenses: int, net: int}
     */
    public function recorded_up_to(string $date): array
    {
        $counts = array();
        foreach (self::SOURCES as $table => $col)
        {
            $counts[$table] = (int) $this->db->query(
                "SELECT COUNT(*) AS c FROM $table WHERE status = 'Active' AND $col <= ?", array($date)
            )->row()->c;
        }
        $counts['net'] = $this->net(NULL, $date);
        return $counts;
    }

    /**
     * Record the opening balance (only when none is active). Atomic; audited.
     *
     * @param array{opening_date: string, bank_amount: string, cash_amount: string, remarks: ?string, request_token: string} $data
     * @return array{success: bool, message: string, id?: int}
     */
    public function record(array $data): array
    {
        return $this->save($data, NULL, '');
    }

    /**
     * Correct the active opening balance: a new entry replaces it; the old one is kept as Superseded.
     *
     * @param array{opening_date: string, bank_amount: string, cash_amount: string, remarks: ?string, request_token: string} $data
     * @return array{success: bool, message: string, id?: int}
     */
    public function correct(int $active_id, array $data, string $reason): array
    {
        return $this->save($data, $active_id, $reason);
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, id?: int}
     */
    private function save(array $data, ?int $replaces_id, string $reason): array
    {
        $this->load->model('sequence_model');
        $this->load->library('audit');
        $db = $this->db;

        // Same form submitted again (refresh / double click): nothing new is saved
        $repeat = $db->select('id, reference_no')->where('request_token', $data['request_token'])->get($this->table)->row_array();
        if ($repeat)
        {
            return array('success' => TRUE, 'message' => 'Opening balance '.$repeat['reference_no'].' was already saved.', 'id' => (int) $repeat['id']);
        }

        $db->trans_begin();
        try
        {
            $current = $db->query("SELECT * FROM fund_opening_balances WHERE status = 'Active' LIMIT 1 FOR UPDATE")->row_array() ?: NULL;
            if ($replaces_id === NULL && $current !== NULL)
            {
                throw new DomainException('An opening balance ('.$current['reference_no'].') is already recorded. Use "Correct opening balance" to change it.');
            }
            if ($replaces_id !== NULL && ($current === NULL || (int) $current['id'] !== $replaces_id))
            {
                throw new DomainException('The opening balance was changed by someone else. Reload the page and try again.');
            }

            $year = (int) date('Y', strtotime($data['opening_date']));
            $reference = $this->sequence_model->formatted('OPENING', 'OB-'.$year.'-', 4, $year);

            if ($current !== NULL)
            {
                // Free the single "Active" slot first, keeping the row and who replaced it
                $db->where('id', (int) $current['id'])->update($this->table, array(
                    'status'        => 'Superseded',
                    'superseded_at' => date('Y-m-d H:i:s'),
                    'superseded_by' => user_id(),
                ));
            }

            $row = array(
                'reference_no'      => $reference,
                'opening_date'      => $data['opening_date'],
                'bank_amount'       => $data['bank_amount'],
                'cash_amount'       => $data['cash_amount'],
                'remarks'           => $data['remarks'],
                'status'            => 'Active',
                'corrects_id'       => $current !== NULL ? (int) $current['id'] : NULL,
                'correction_reason' => $current !== NULL ? $reason : NULL,
                'request_token'     => $data['request_token'],
                'created_by'        => user_id(),
            );
            $db->insert($this->table, $row);
            $id = (int) $db->insert_id();
            if ($id === 0)
            {
                throw new RuntimeException('The opening balance could not be saved.');
            }

            $total = from_paise(to_paise($data['bank_amount']) + to_paise($data['cash_amount']));
            if ($current === NULL)
            {
                $this->audit->log('Opening Balance Recorded', 'finance', $id, NULL, $row + array('total_amount' => $total));
            }
            else
            {
                $this->audit->log('Opening Balance Corrected', 'finance', $id,
                    array('reference_no' => $current['reference_no'], 'opening_date' => $current['opening_date'], 'bank_amount' => $current['bank_amount'],
                          'cash_amount' => $current['cash_amount'], 'total_amount' => $current['total_amount'], 'remarks' => $current['remarks']),
                    array('reference_no' => $reference, 'opening_date' => $data['opening_date'], 'bank_amount' => $data['bank_amount'],
                          'cash_amount' => $data['cash_amount'], 'total_amount' => $total, 'remarks' => $data['remarks'], 'correction_reason' => $reason)
                );
            }

            if ($db->trans_status() === FALSE)
            {
                throw new RuntimeException('The opening balance could not be saved.');
            }
            $db->trans_commit();
            $this->active = FALSE;

            $verb = $current === NULL ? 'recorded' : 'corrected (replaces '.$current['reference_no'].')';
            return array('success' => TRUE, 'id' => $id,
                'message' => 'Opening balance '.$reference.' of '.money($total).' as of '.fmt_date($data['opening_date']).' '.$verb.'.');
        }
        catch (Throwable $e)
        {
            $db->trans_rollback();
            $this->active = FALSE;
            if ($e instanceof DomainException)
            {
                return array('success' => FALSE, 'message' => $e->getMessage());
            }
            log_message('error', 'Fund_model save: '.$e->getMessage().' | DB: '.json_encode($db->error()));
            return array('success' => FALSE, 'message' => 'The opening balance could not be saved. Nothing was changed.');
        }
    }

    /**
     * Sums (paise) of one transaction table in (after, upto], split by bank / cash.
     *
     * @return array{bank: int, cash: int, total: int, count: int}
     */
    private function sums(string $table, ?string $after, ?string $upto): array
    {
        $col = self::SOURCES[$table];
        $where = "status = 'Active'";
        $params = array();
        if ($after !== NULL)
        {
            $where .= " AND $col > ?";
            $params[] = $after;
        }
        if ($upto !== NULL)
        {
            $where .= " AND $col <= ?";
            $params[] = $upto;
        }
        $row = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN payment_mode = 'Cash' THEN amount END), 0) AS cash,
                    COALESCE(SUM(CASE WHEN payment_mode <> 'Cash' THEN amount END), 0) AS bank,
                    COUNT(*) AS cnt
             FROM $table WHERE $where",
            $params
        )->row_array();
        $bank = to_paise_signed($row['bank']);
        $cash = to_paise_signed($row['cash']);
        return array('bank' => $bank, 'cash' => $cash, 'total' => $bank + $cash, 'count' => (int) $row['cnt']);
    }

    /**
     * Net effect (paise) of Active bank / cash transfers in (after, upto]: withdrawals move money
     * from bank to cash, deposits from cash to bank. total is always 0.
     *
     * @return array{bank: int, cash: int, total: int, withdrawals: int, deposits: int, count: int}
     */
    private function transfer_sums(?string $after, ?string $upto): array
    {
        $where = "status = 'Active'";
        $params = array();
        if ($after !== NULL)
        {
            $where .= ' AND transfer_date > ?';
            $params[] = $after;
        }
        if ($upto !== NULL)
        {
            $where .= ' AND transfer_date <= ?';
            $params[] = $upto;
        }
        $row = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN direction = 'Bank to Cash' THEN amount END), 0) AS withdrawals,
                    COALESCE(SUM(CASE WHEN direction = 'Cash to Bank' THEN amount END), 0) AS deposits,
                    COUNT(*) AS cnt
             FROM fund_transfers WHERE $where",
            $params
        )->row_array();
        $w = to_paise_signed($row['withdrawals']);
        $d = to_paise_signed($row['deposits']);
        return array('bank' => $d - $w, 'cash' => $w - $d, 'total' => 0, 'withdrawals' => $w, 'deposits' => $d, 'count' => (int) $row['cnt']);
    }

    /**
     * Maintenance collections (Active payments) by payment month after the opening date.
     *
     * @return array<int, array{ym: string, label: string, total: int, count: int}>
     */
    private function collections_by_month(string $after): array
    {
        $rows = $this->db->query(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total, COUNT(*) AS cnt
             FROM payments WHERE status = 'Active' AND payment_date > ?
             GROUP BY ym ORDER BY ym",
            array($after)
        )->result_array();
        $out = array();
        foreach ($rows as $r)
        {
            list($y, $m) = array_map('intval', explode('-', $r['ym']));
            $out[] = array('ym' => $r['ym'], 'label' => period_label($y, $m), 'total' => to_paise_signed($r['total']), 'count' => (int) $r['cnt']);
        }
        return $out;
    }
}
