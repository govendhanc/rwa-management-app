<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Association letterhead for printable documents (receipts, statements). Works in Dompdf and on screen.
 *
 * @var string $logo  data URI or ''
 * @var bool   $small compact size (A5)
 */
$small = ! empty($small);
$name = association('association_name');
$reg = association('registration_no');
$address = trim(association('address'));
$city_line = trim(association('city').(association('pincode') !== '' ? ' - '.association('pincode') : '').(association('state') !== '' ? ', '.association('state') : ''), ' ,-');
$contact = array();
if (association('mobile') !== '') { $contact[] = 'Mobile: '.association('mobile'); }
if (association('email') !== '') { $contact[] = 'Email: '.association('email'); }
$sub = $small ? 8 : 10;
?>
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <?php if ($logo !== ''): ?>
            <td style="width: <?= $small ? 56 : 80 ?>px; padding: 0; vertical-align: top;"><img src="<?= $logo ?>" alt="" style="width: <?= $small ? 52 : 74 ?>px;"></td>
        <?php endif; ?>
        <td style="text-align: center; padding: 0; vertical-align: top;">
            <div style="font-size: <?= $small ? 13 : 17 ?>px; font-weight: bold; color: #163d70; text-transform: uppercase; letter-spacing: 0.3px; line-height: 1.25;"><?= e($name) ?></div>
            <?php if ($reg !== ''): ?><div style="font-size: <?= $sub ?>px; color: #374151; line-height: 1.45;"><?= e(sys_setting('registration_label', 'Reg. No')) ?>: <?= e($reg) ?></div><?php endif; ?>
            <?php if ($address !== ''): ?><div style="font-size: <?= $sub ?>px; color: #374151; line-height: 1.45;"><?= e($address) ?></div><?php endif; ?>
            <?php if ($city_line !== ''): ?><div style="font-size: <?= $sub ?>px; color: #374151; line-height: 1.45;"><?= e($city_line) ?></div><?php endif; ?>
            <?php if ( ! empty($contact)): ?><div style="font-size: <?= $sub ?>px; color: #374151; line-height: 1.45;"><?= e(implode('  |  ', $contact)) ?></div><?php endif; ?>
        </td>
        <?php if ($logo !== ''): ?>
            <td style="width: <?= $small ? 56 : 80 ?>px; padding: 0;"></td>
        <?php endif; ?>
    </tr>
</table>
<div style="border-top: 2px solid #163d70; margin: <?= $small ? 6 : 10 ?>px 0 0;"></div>
