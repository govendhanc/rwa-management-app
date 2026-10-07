<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
?>
    </div><!-- /.auth-card -->
    <p class="auth-footer">ROWA Portal v<?= e($CI->config->item('app_version')) ?> &middot; &copy; <?= date('Y') ?></p>
</div><!-- /.auth-wrapper -->

<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
<script type="application/json" id="flashData"><?= flash_messages_json() ?></script>
<script src="<?= e(asset_url('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset_url('js/app.js')) ?>"></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= e(asset_url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
