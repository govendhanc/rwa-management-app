<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Receipt document - shared by the screen view, print view and Dompdf PDF.
 * Uses simple table layout and inline CSS that Dompdf renders reliably.
 *
 * @var array<string, mixed>             $r
 * @var array<int, array<string, mixed>> $lines
 * @var string                           $format  a4|small
 * @var bool                             $for_pdf
 * @var string                           $logo    data URI or ''
 */
$small = $format === 'small';
$cancelled = $r['status'] === 'Cancelled';
$name = association('association_name');
$bank = array();
if (association('bank_name') !== '') { $bank[] = association('bank_name'); }
if (association('bank_account_no') !== '') { $bank[] = 'A/c '.association('bank_account_no'); }
if (association('bank_ifsc') !== '') { $bank[] = 'IFSC '.association('bank_ifsc'); }
if (association('upi_id') !== '') { $bank[] = 'UPI '.association('upi_id'); }
$advance = to_paise_signed($r['advance_amount']) > 0;
$fs = $small ? 9 : 11;
?>
<?php if ($for_pdf): ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Receipt <?= e($r['receipt_no']) ?></title></head><body>
<?php endif; ?>
<style>
    .rcpt { font-family: <?= $for_pdf ? '"DejaVu Sans", sans-serif' : 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif' ?>; font-size: <?= $fs ?>px; color: #1f2937; position: relative; }
    .rcpt table { width: 100%; border-collapse: collapse; }
    .rcpt td, .rcpt th { vertical-align: top; }
    .rcpt .title { text-align: center; margin: <?= $small ? 6 : 10 ?>px 0; }
    .rcpt .title span { display: inline-block; background: #163d70; color: #ffffff; font-weight: bold; letter-spacing: 1px; padding: 4px 18px; border-radius: 3px; font-size: <?= $fs + 1 ?>px; }
    .rcpt .info td { padding: <?= $small ? 2 : 4 ?>px 6px; border: 1px solid #d7dee8; }
    .rcpt .info .k { width: 18%; background: #f3f6fa; color: #4b5563; }
    .rcpt .info .v { width: 32%; font-weight: bold; }
    .rcpt .lines { margin-top: <?= $small ? 6 : 12 ?>px; }
    .rcpt .lines th { background: #eef2f8; color: #374151; text-align: left; padding: <?= $small ? 3 : 5 ?>px 6px; border: 1px solid #d7dee8; font-size: <?= $fs - 1 ?>px; text-transform: uppercase; }
    .rcpt .lines td { padding: <?= $small ? 3 : 5 ?>px 6px; border: 1px solid #d7dee8; }
    .rcpt .num, .rcpt .lines th.num { text-align: right; white-space: nowrap; }
    .rcpt .sum { margin-top: <?= $small ? 6 : 10 ?>px; }
    .rcpt .sum td { padding: <?= $small ? 2 : 4 ?>px 6px; }
    .rcpt .sum .k { text-align: right; color: #4b5563; }
    .rcpt .sum .v { text-align: right; width: 30%; font-weight: bold; white-space: nowrap; }
    .rcpt .sum .total td { border-top: 1px solid #163d70; border-bottom: 1px solid #163d70; font-size: <?= $fs + 1 ?>px; color: #163d70; }
    .rcpt .words { margin-top: <?= $small ? 6 : 10 ?>px; padding: 6px 8px; background: #f3f6fa; border-left: 3px solid #0f9d8a; }
    .rcpt .foot { margin-top: <?= $small ? 14 : 28 ?>px; }
    .rcpt .foot td { font-size: <?= $fs - 1 ?>px; color: #4b5563; }
    .rcpt .sign { text-align: right; }
    .rcpt .sign .line { display: inline-block; border-top: 1px solid #6b7280; padding-top: 3px; min-width: 170px; text-align: center; }
    .rcpt .note { margin-top: <?= $small ? 8 : 14 ?>px; text-align: center; font-size: <?= $fs - 2 ?>px; color: #6b7280; }
    .rcpt .watermark { position: absolute; top: 38%; left: 8%; width: 84%; text-align: center; font-size: <?= $small ? 46 : 72 ?>px; font-weight: bold; color: #dc2626; opacity: 0.18; transform: rotate(-24deg); }
    .rcpt .cancel-box { margin-top: 8px; padding: 6px 8px; border: 1px solid #dc2626; color: #b91c1c; }
</style>

<div class="rcpt">
    <?php if ($cancelled): ?><div class="watermark">CANCELLED</div><?php endif; ?>

    <?php $this->load->view('documents/_letterhead', array('logo' => $logo, 'small' => $small)); ?>

    <div class="title"><span>MAINTENANCE PAYMENT RECEIPT</span></div>

    <table class="info">
        <tr>
            <td class="k">Receipt No</td><td class="v"><?= e($r['receipt_no']) ?></td>
            <td class="k">Receipt Date</td><td class="v"><?= e(fmt_date($r['receipt_date'])) ?></td>
        </tr>
        <tr>
            <td class="k">Owner Name</td><td class="v"><?= e($r['owner_name']) ?></td>
            <td class="k">Owner ID</td><td class="v"><?= e($r['owner_code']) ?></td>
        </tr>
        <tr>
            <td class="k">Plot No</td><td class="v"><?= e($r['plot_no']) ?></td>
            <td class="k">House No</td><td class="v"><?= e($r['house_no']) ?></td>
        </tr>
        <tr>
            <td class="k">Mobile</td><td class="v"><?= e($r['mobile']) ?></td>
            <td class="k">Maintenance Month</td><td class="v"><?= e($r['period_label']) ?></td>
        </tr>
        <tr>
            <td class="k">Payment Mode</td><td class="v"><?= e($r['payment_mode']) ?></td>
            <td class="k">Transaction Ref</td><td class="v"><?= e($r['transaction_ref'] ?: '-') ?></td>
        </tr>
    </table>

    <table class="lines">
        <tr>
            <th style="width: 7%;">#</th>
            <th>Maintenance Month</th>
            <th>Plot / House</th>
            <th class="num">Bill Amount</th>
            <th class="num">Amount Paid</th>
        </tr>
        <?php $i = 0; foreach ($lines as $line): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td><?= e(period_label((int) $line['billing_year'], (int) $line['billing_month'])) ?></td>
                <td>Plot <?= e($line['plot_no']) ?><?= $line['house_no'] ? ' / '.e($line['house_no']) : '' ?></td>
                <td class="num"><?= e(money($line['bill_amount'])) ?></td>
                <td class="num"><?= e(money($line['amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($advance): $i++; ?>
            <tr>
                <td><?= $i ?></td>
                <td colspan="3">Advance - kept as credit and adjusted automatically against upcoming months</td>
                <td class="num"><?= e(money($r['advance_amount'])) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <table class="sum">
        <tr><td class="k">Maintenance Amount (months above)</td><td class="v"><?= e(money($r['maintenance_amount'])) ?></td></tr>
        <tr><td class="k">Previous Outstanding</td><td class="v"><?= e(money($r['previous_outstanding'])) ?></td></tr>
        <tr class="total"><td class="k"><strong>Payment Received</strong></td><td class="v"><?= e(money($r['amount_received'])) ?></td></tr>
        <?php if ($advance): ?><tr><td class="k">of which Advance (credit)</td><td class="v"><?= e(money($r['advance_amount'])) ?></td></tr><?php endif; ?>
        <tr><td class="k">Balance Outstanding</td><td class="v"><?= e(money($r['balance_after'])) ?></td></tr>
    </table>

    <div class="words"><strong>Amount received in words:</strong> <?= e(amount_in_words($r['amount_received'])) ?></div>

    <?php if ($cancelled): ?>
        <div class="cancel-box"><strong>This receipt is CANCELLED</strong><?= $r['cancelled_at'] ? ' on '.e(fmt_date($r['cancelled_at'])) : '' ?><?= ! empty($r['cancel_reason']) ? ' - '.e($r['cancel_reason']) : '' ?>.</div>
    <?php endif; ?>

    <table class="foot">
        <tr>
            <td>
                Received by: <strong><?= e($r['received_by_name'] ?: '-') ?></strong>
                <?php if ( ! empty($bank)): ?><br>Pay to: <?= e(implode(' | ', $bank)) ?><?php endif; ?>
            </td>
            <td class="sign">
                <span class="line">For <?= e(association('short_name') !== '' ? association('short_name') : $name) ?><br>Authorised Signatory</span>
            </td>
        </tr>
    </table>

    <div class="note"><?= e(association('receipt_footer_note')) ?></div>
</div>
<?php if ($for_pdf): ?>
</body></html>
<?php endif; ?>
