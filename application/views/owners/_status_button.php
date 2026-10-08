<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Deactivate / Reactivate button for one owner (opens #ownerStatusModal).
 *
 * @var array<string, mixed> $o       Owner row: id, owner_code, owner_name, status, plots, house_nos, outstanding, advance_credit
 * @var bool                 $compact Icon-only button for table rows
 */
$deactivate = $o['status'] === 'Active';
$due = to_paise_signed($o['outstanding']);
$credit = to_paise_signed($o['advance_credit']);
$label = $deactivate ? 'Deactivate' : 'Reactivate';
?>
<button type="button"
        class="btn <?= $compact ? 'btn-sm ' : '' ?><?= $deactivate ? 'btn-outline-danger' : 'btn-outline-success' ?> js-owner-status"
        data-action="<?= $deactivate ? 'deactivate' : 'reactivate' ?>"
        data-url="<?= e(site_url('owners/'.($deactivate ? 'deactivate' : 'reactivate').'/'.$o['id'])) ?>"
        data-name="<?= e($o['owner_name']) ?>"
        data-code="<?= e($o['owner_code']) ?>"
        data-plots="<?= e((string) $o['plots'] !== '' ? (string) $o['plots'] : '-') ?>"
        data-houses="<?= e((string) $o['house_nos'] !== '' ? (string) $o['house_nos'] : '-') ?>"
        data-outstanding="<?= e(money($o['outstanding'])) ?>"
        data-has-due="<?= $due > 0 ? '1' : '0' ?>"
        data-advance="<?= e(money($o['advance_credit'])) ?>"
        data-has-advance="<?= $credit > 0 ? '1' : '0' ?>"
        <?php if ($compact): ?>data-bs-toggle="tooltip" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"<?php endif; ?>>
    <i class="fa-solid <?= $deactivate ? 'fa-user-slash' : 'fa-user-check' ?><?= $compact ? '' : ' me-1' ?>"></i><?= $compact ? '' : ' '.e($label) ?>
</button>
