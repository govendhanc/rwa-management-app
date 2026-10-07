<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Race-safe document numbering (receipts, expenses, incomes, tenants).
 *
 * MUST be called inside the caller's database transaction: the sequence row is
 * locked with SELECT ... FOR UPDATE, so two users can never get the same number
 * and a rolled-back transaction never consumes a number.
 */
class Sequence_model extends CI_Model
{
    /**
     * Next number for a sequence. $year = 0 for sequences that never reset.
     */
    public function next(string $type, int $year = 0): int
    {
        // Create the row if it does not exist yet (no-op when it does)
        $this->db->query(
            'INSERT INTO document_sequences (seq_type, seq_year, last_number) VALUES (?, ?, 0)
             ON DUPLICATE KEY UPDATE last_number = last_number',
            array($type, $year)
        );

        $row = $this->db->query(
            'SELECT id, last_number FROM document_sequences WHERE seq_type = ? AND seq_year = ? FOR UPDATE',
            array($type, $year)
        )->row_array();
        if ( ! $row)
        {
            throw new RuntimeException('Document sequence '.$type.'/'.$year.' is not available.');
        }

        $next = (int) $row['last_number'] + 1;
        $this->db->where('id', (int) $row['id'])->update('document_sequences', array('last_number' => $next));
        return $next;
    }

    /**
     * Formatted code, e.g. formatted('TENANT', 'TEN', 5) => TEN00004, formatted('RECEIPT', 'REC-2026-', 6, 2026) => REC-2026-000016.
     */
    public function formatted(string $type, string $prefix, int $pad, int $year = 0): string
    {
        return $prefix.str_pad((string) $this->next($type, $year), $pad, '0', STR_PAD_LEFT);
    }
}
