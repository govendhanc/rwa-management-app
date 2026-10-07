<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Receipts: list, view, PDF download (A4 / small), print, WhatsApp share.
 */
class Receipts extends Auth_Controller
{
    protected $permission_map = array(
        'index'    => 'receipts.view',
        'view'     => 'receipts.view',
        'pdf'      => 'receipts.download',
        'print'    => 'receipts.download',
        'whatsapp' => 'whatsapp.send',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('receipt_model');
        $this->load->library('receipt_pdf');
    }

    public function index(): void
    {
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $filters = array(
            'from'   => is_valid_date($from) ? $from : date('Y-m-01', strtotime('-2 months')),
            'to'     => is_valid_date($to) ? $to : date('Y-m-d'),
            'status' => in_array($this->input->get('status'), array('Active', 'Cancelled'), TRUE) ? (string) $this->input->get('status') : '',
        );
        $this->render('receipts/index', array(
            'page_title'  => 'Receipts',
            'breadcrumbs' => array(array('label' => 'Payments', 'url' => 'payments'), array('label' => 'Receipts')),
            'filters'     => $filters,
            'receipts'    => $this->receipt_model->list_receipts($filters),
            'scripts'     => array('js/modules/receipts.js'),
        ));
    }

    public function view(int $id = 0): void
    {
        $receipt = $this->find_or_404($id);
        $this->render('receipts/view', array(
            'page_title'   => 'Receipt '.$receipt['receipt_no'],
            'breadcrumbs'  => array(array('label' => 'Receipts', 'url' => 'receipts'), array('label' => $receipt['receipt_no'])),
            'receipt'      => $receipt,
            'receipt_html' => $this->receipt_pdf->html($receipt, 'a4'),
            'scripts'      => array('js/modules/receipts.js'),
        ));
    }

    /**
     * PDF: /receipts/pdf/{id}[/a4|small][?inline=1]
     */
    public function pdf(int $id = 0, string $format = 'a4'): void
    {
        $receipt = $this->find_or_404($id);
        $format = $format === 'small' ? 'small' : 'a4';
        try
        {
            $file = $this->receipt_pdf->file($receipt, $format);
        }
        catch (Throwable $e)
        {
            log_message('error', 'Receipt PDF failed for '.$receipt['receipt_no'].': '.$e->getMessage());
            show_error('The PDF could not be generated. Please try again or use Print.', 500, 'PDF error');
        }
        $name = $receipt['receipt_no'].($format === 'small' ? '-small' : '').'.pdf';
        $inline = $this->input->get('inline') === '1';

        header('Content-Type: application/pdf');
        header('Content-Length: '.filesize($file));
        header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.$name.'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file);
        exit;
    }

    /**
     * Standalone print page (opens the browser print dialog).
     */
    public function print(int $id = 0, string $format = 'a4'): void
    {
        $receipt = $this->find_or_404($id);
        $format = $format === 'small' ? 'small' : 'a4';
        $this->load->view('receipts/print', array(
            'receipt'      => $receipt,
            'format'       => $format,
            'receipt_html' => $this->receipt_pdf->html($receipt, $format),
        ));
    }

    /**
     * AJAX POST: prepare the WhatsApp "payment received" message (Click-to-Chat).
     */
    public function whatsapp(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();
        $receipt = $this->find_or_404($id);
        if ($receipt['status'] !== 'Active')
        {
            $this->json_error('This receipt is cancelled and cannot be shared.');
        }

        $this->load->library('whatsapp_service');
        // Cloud API mode attaches the receipt PDF; Click-to-Chat cannot carry files
        $document = NULL;
        if ($this->whatsapp_service->mode() === 'cloud_api')
        {
            try
            {
                $document = array('path' => $this->receipt_pdf->file($receipt, 'a4'), 'filename' => $receipt['receipt_no'].'.pdf');
            }
            catch (Throwable $e)
            {
                log_message('error', 'Receipt PDF for WhatsApp failed: '.$e->getMessage());
                $this->json_error('The receipt PDF could not be generated, so the message was not sent.');
            }
        }
        $result = $this->whatsapp_service->deliver('payment_receipt', (string) ($receipt['whatsapp_no'] ?: $receipt['owner_mobile']), array(
            'OWNER_NAME'   => $receipt['owner_name'],
            'PLOT_NO'      => $receipt['plot_no'],
            'HOUSE_NO'     => $receipt['house_no'],
            'MONTH'        => $receipt['period_label'],
            'YEAR'         => substr($receipt['receipt_date'], 0, 4),
            'AMOUNT'       => money($receipt['amount_received'], FALSE),
            'PAYMENT_MODE' => $receipt['payment_mode'],
            'RECEIPT_NO'   => $receipt['receipt_no'],
            'BALANCE'      => money($receipt['balance_after'], FALSE),
            'PAYMENT_DATE' => fmt_date($receipt['receipt_date']),
        ), array('owner_id' => (int) $receipt['owner_id'], 'receipt_id' => (int) $receipt['id']), $document);

        if ( ! $result['success'])
        {
            $this->json_error($result['message']);
        }
        $this->audit->log('Receipt Shared on WhatsApp', 'receipts', $id, NULL, array('receipt_no' => $receipt['receipt_no'], 'mode' => $result['mode']));
        $this->json_success($result['message'], array('mode' => $result['mode'], 'url' => $result['url'] ?? NULL, 'text' => $result['text'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function find_or_404(int $id): array
    {
        $receipt = $this->receipt_model->find_full($id);
        if ($receipt === NULL)
        {
            show_404();
        }
        return $receipt;
    }
}
