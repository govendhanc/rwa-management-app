<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** @var string[] $scripts Page-specific scripts under public/assets (e.g. 'js/modules/owners.js') */
?>
<script type="application/json" id="flashData"><?= flash_messages_json() ?></script>
<script src="<?= e(asset_url('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/dataTables.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/dataTables.buttons.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/buttons.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/jszip/jszip.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/pdfmake/pdfmake.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/pdfmake/vfs_fonts.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/buttons.html5.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/buttons.print.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/dataTables.responsive.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/datatables/js/responsive.bootstrap5.min.js')) ?>"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= e(asset_url($script)) ?>"></script>
<?php endforeach; ?>
