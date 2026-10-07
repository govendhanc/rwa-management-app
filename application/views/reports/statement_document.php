<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Owner statement document (screen + Dompdf PDF).
 *
 * @var array<string, mixed> $owner
 * @var array<string, mixed> $statement
 */
$s = $statement;
$closing = to_paise_signed($s['closing']);
$opening = to_paise_signed($s['opening']);
$signed = function (int $paise): string {
    return $paise < 0 ? money(from_paise(-$paise)).' Cr' : money(from_paise($paise));
};
?>
<?php if ($for_pdf): ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Statement <?= e($owner['owner_code']) ?></title></head><body><?php endif; ?>
<style>
    .stmt { font-family: <?= $for_pdf ? '"DejaVu Sans", sans-serif' : 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif' ?>; font-size: <?= $for_pdf ? 10 : 13 ?>px; color: #1f2937; }
    .stmt table { width: 100%; border-collapse: collapse; }
    .stmt .title { text-align: center; margin: 10px 0; font-weight: bold; letter-spacing: 1px; color: #163d70; font-size: <?= $for_pdf ? 12 : 15 ?>px; }
    .stmt .info td { padding: 3px 6px; border: 1px solid #d7dee8; }
    .stmt .info .k { background: #f3f6fa; color: #4b5563; width: 18%; }
    .stmt .info .v { font-weight: bold; width: 32%; }
    .stmt .summary { margin-top: 10px; }
    .stmt .summary td { padding: 6px; border: 1px solid #d7dee8; text-align: center; width: 16.6%; }
    .stmt .summary .lbl { font-size: <?= $for_pdf ? 8 : 11 ?>px; color: #4b5563; text-transform: uppercase; }
    .stmt .summary .val { font-weight: bold; font-size: <?= $for_pdf ? 11 : 15 ?>px; }
    .stmt .ledger { margin-top: 12px; }
    .stmt .ledger th { background: #eef2f8; padding: 5px 6px; border: 1px solid #d7dee8; text-align: left; font-size: <?= $for_pdf ? 8 : 11 ?>px; text-transform: uppercase; }
    .stmt .ledger td { padding: 4px 6px; border: 1px solid #d7dee8; }
    .stmt .num { text-align: right; white-space: nowrap; }
    .stmt .open td, .stmt .close td { background: #f8fafc; font-weight: bold; }
    .stmt .note { margin-top: 10px; font-size: <?= $for_pdf ? 8 : 11 ?>px; color: #6b7280; }
</style>
<div class="stmt" id="statementDocument" data-opening="<?= e($s['opening']) ?>" data-closing="<?= e($s['closing']) ?>">
    <?php $this->load->view('documents/_letterhead', array('logo' => $logo, 'small' => FALSE)); ?>
    <div class="title">OWNER STATEMENT OF ACCOUNT</div>
    <table class="info">
        <tr><td class="k">Owner</td><td class="v"><?= e($owner['owner_name']) ?></td><td class="k">Owner ID</td><td class="v"><?= e($owner['owner_code']) ?></td></tr>
        <tr><td class="k">Plot No</td><td class="v"><?= e($owner['plots'] ?: '-') ?></td><td class="k">Mobile</td><td class="v"><?= e($owner['mobile']) ?></td></tr>
        <tr><td class="k">Period</td><td class="v"><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></td><td class="k">Generated</td><td class="v"><?= e(fmt_datetime(date('Y-m-d H:i:s'))) ?></td></tr>
    </table>

    <table class="summary">
        <tr>
            <td><div class="lbl">Opening Balance</div><div class="val"><?= e($signed($opening)) ?></div></td>
            <td><div class="lbl">Maintenance Charges</div><div class="val"><?= e(money($s['total_charges'])) ?></div></td>
            <td><div class="lbl">Payments</div><div class="val"><?= e(money($s['total_payments'])) ?></div></td>
            <td><div class="lbl">Adjustments</div><div class="val"><?= e(money($s['total_adjustments'])) ?></div></td>
            <td><div class="lbl">Advance</div><div class="val"><?= e(money(from_paise(max(0, -$closing)))) ?></div></td>
            <td><div class="lbl">Closing Balance</div><div class="val" style="color: <?= $closing > 0 ? '#b91c1c' : '#047857' ?>;"><?= e(money(from_paise(max(0, $closing)))) ?></div></td>
        </tr>
    </table>

    <table class="ledger" id="statementLedger">
        <thead><tr><th style="width: 13%;">Date</th><th style="width: 12%;">Type</th><th>Particulars</th><th style="width: 15%;">Reference</th><th class="num" style="width: 12%;">Debit</th><th class="num" style="width: 12%;">Credit</th><th class="num" style="width: 13%;">Balance</th></tr></thead>
        <tbody>
            <tr class="open"><td><?= e(fmt_date($from)) ?></td><td></td><td>Opening balance</td><td></td><td class="num"></td><td class="num"></td><td class="num"><?= e($signed($opening)) ?></td></tr>
            <?php foreach ($s['entries'] as $entry): ?>
                <tr>
                    <td><?= e(fmt_date($entry['date'])) ?></td>
                    <td><?= e($entry['type']) ?></td>
                    <td><?= e($entry['description']) ?></td>
                    <td><?= e($entry['ref']) ?></td>
                    <td class="num"><?= $entry['debit'] !== '' ? e(money($entry['debit'])) : '' ?></td>
                    <td class="num"><?= $entry['credit'] !== '' ? e(money($entry['credit'])) : '' ?></td>
                    <td class="num"><?= e($signed(to_paise_signed($entry['balance']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="close"><td><?= e(fmt_date($to)) ?></td><td></td><td>Closing balance <?= $closing < 0 ? '(advance credit)' : ($closing > 0 ? '(due)' : '') ?></td><td></td>
                <td class="num"><?= e(money($s['total_charges'])) ?></td><td class="num"><?= e(money(from_paise(to_paise_signed($s['total_payments']) + to_paise_signed($s['total_adjustments'])))) ?></td><td class="num"><?= e($signed($closing)) ?></td></tr>
        </tbody>
    </table>
    <div class="note">Debit = maintenance billed; Credit = payments received and waivers/discounts. "Cr" means the owner has paid in advance. Cancelled payments and bills are not shown.</div>
</div>
<?php if ($for_pdf): ?></body></html><?php endif; ?>
