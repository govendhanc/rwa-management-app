<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ($timed_out): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-center py-2">
        <i class="fa-solid fa-clock"></i>
        <div>Your session expired due to inactivity. Please sign in again.</div>
    </div>
<?php endif; ?>

<?php if ($error !== NULL): ?>
    <div class="alert alert-danger d-flex gap-2 align-items-center py-2" role="alert">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<?= form_open('auth/login', array('autocomplete' => 'on', 'novalidate' => 'novalidate', 'class' => 'needs-validation')) ?>
    <div class="mb-3">
        <label for="identifier" class="form-label">Username or E-mail</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
            <input type="text" class="form-control" id="identifier" name="identifier" value="<?= e($identifier) ?>"
                   required maxlength="150" autocomplete="username" autofocus>
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" class="form-control" id="password" name="password" required maxlength="72" autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password">
                <i class="fa-solid fa-eye"></i>
            </button>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
            <label class="form-check-label" for="remember">Remember me for 30 days</label>
        </div>
        <a href="<?= e(site_url('auth/forgot-password')) ?>" class="small">Forgot password?</a>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2" data-loading-text="Signing in...">
        <i class="fa-solid fa-right-to-bracket me-1"></i> Sign In
    </button>
<?= form_close() ?>
