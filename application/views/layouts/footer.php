<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
?>
    </main>

    <footer class="app-footer">
        <span>&copy; <?= date('Y') ?> <?= e(association('association_name')) ?></span>
        <span class="text-muted">ROWA Portal v<?= e($CI->config->item('app_version')) ?></span>
    </footer>
</div><!-- /.app-main -->
</div><!-- /.app-shell -->

<?php $this->load->view('partials/ui_components'); ?>
<?php $this->load->view('partials/scripts', array('scripts' => $scripts)); ?>
</body>
</html>
