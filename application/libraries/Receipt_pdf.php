<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Receipt PDFs via Dompdf (bundled in application/third_party/dompdf - no Composer needed).
 *
 * Formats: 'a4' (A4 portrait) and 'small' (A5 portrait, half sheet).
 * PDFs are cached in storage/receipts/<year>/ and rebuilt when missing.
 * Cancelled receipts are always rendered fresh with a CANCELLED watermark.
 */
class Receipt_pdf
{
    const FORMATS = array('a4' => 'A4', 'small' => 'A5');

    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('receipt_model');
        $this->CI->load->library('pdf_renderer');
    }

    /**
     * Receipt HTML (shared by the screen view, print view and PDF).
     *
     * @param array<string, mixed> $receipt Row from Receipt_model::find_full()
     */
    public function html(array $receipt, string $format = 'a4', bool $for_pdf = FALSE): string
    {
        return $this->CI->load->view('receipts/document', array(
            'r'       => $receipt,
            'lines'   => $this->CI->receipt_model->lines((int) $receipt['payment_id']),
            'format'  => isset(self::FORMATS[$format]) ? $format : 'a4',
            'for_pdf' => $for_pdf,
            'logo'    => $this->CI->pdf_renderer->logo_data_uri(),
        ), TRUE);
    }

    /**
     * Absolute path of the PDF file for a receipt, rendering it if needed.
     *
     * @param array<string, mixed> $receipt
     */
    public function file(array $receipt, string $format = 'a4'): string
    {
        $format = isset(self::FORMATS[$format]) ? $format : 'a4';
        $year = substr((string) $receipt['receipt_date'], 0, 4);
        // The fingerprint ties the cached file to this exact receipt row (so a file left over from a
        // restored/reset database is never served), and to the receipt template and association
        // details (so a new layout, address or logo produces a fresh PDF).
        $fingerprint = substr(hash('sha256', implode('|', array(
            $receipt['id'], $receipt['receipt_no'], $receipt['created_at'], $receipt['amount_received'], $receipt['status'],
            (string) @filemtime(VIEWPATH.'receipts'.DIRECTORY_SEPARATOR.'document.php'),
            association('updated_at'), association('logo_path'),
        ))), 0, 10);
        $relative = 'receipts/'.$year.'/'.preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $receipt['receipt_no']).'-'.$fingerprint.'-'.$format.'.pdf';
        $absolute = STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if ( ! is_file($absolute))
        {
            $dir = dirname($absolute);
            if ( ! is_dir($dir) && ! mkdir($dir, 0750, TRUE) && ! is_dir($dir))
            {
                throw new RuntimeException('Receipt folder is not writable: '.$dir);
            }
            file_put_contents($absolute, $this->render($receipt, $format), LOCK_EX);
        }
        if ($format === 'a4' && $receipt['pdf_path'] !== $relative)
        {
            $this->CI->db->where('id', (int) $receipt['id'])->update('receipts', array('pdf_path' => $relative));
        }
        return $absolute;
    }

    /**
     * Remove cached PDFs (A4 and small) of a receipt, e.g. after cancellation.
     * Accepts the stored relative path of the A4 file.
     */
    public function forget(?string $relative): void
    {
        if ($relative === NULL || ! preg_match('#^receipts/\d{4}/[A-Za-z0-9_-]+-a4\.pdf$#', $relative))
        {
            return;
        }
        $base = STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        foreach (array($base, substr($base, 0, -7).'-small.pdf') as $file)
        {
            if (is_file($file))
            {
                @unlink($file);
            }
        }
    }

    /**
     * Render PDF bytes.
     *
     * @param array<string, mixed> $receipt
     */
    public function render(array $receipt, string $format = 'a4'): string
    {
        return $this->CI->pdf_renderer->render($this->html($receipt, $format, TRUE), self::FORMATS[$format] ?? 'A4', 'portrait', 'Receipt '.$receipt['receipt_no']);
    }
}
