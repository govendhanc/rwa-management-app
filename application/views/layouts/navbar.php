<?php defined('BASEPATH') OR exit('No direct script access allowed');
$user = current_user();
$notification_count = isset($notification_count) ? (int) $notification_count : 0;
?>
<div class="app-main">
    <header class="app-topbar">
        <button type="button" class="btn btn-icon topbar-toggle" id="sidebarToggle" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="topbar-title">
            <h1><?= e($page_title) ?></h1>
            <?php if ( ! empty($breadcrumbs)): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= e(site_url('dashboard')) ?>"><i class="fa-solid fa-house"></i></a></li>
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i === array_key_last($breadcrumbs) || empty($crumb['url'])): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= e($crumb['label']) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?= e(site_url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
        </div>

        <div class="topbar-actions">
            <div class="dropdown">
                <button class="btn btn-icon position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications" id="notificationBell">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="badge rounded-pill bg-danger topbar-badge"><?= $notification_count > 99 ? '99+' : $notification_count ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow notification-menu" id="notificationMenu">
                    <h6 class="dropdown-header">Notifications</h6>
                    <div class="notification-list" data-url="<?= e(site_url('notifications/latest')) ?>">
                        <div class="dropdown-item-text text-muted small">No new notifications</div>
                    </div>
                </div>
            </div>

            <?php if ($user !== NULL): ?>
            <div class="dropdown">
                <button class="btn topbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span>
                    <span class="d-none d-md-inline text-start">
                        <span class="d-block fw-semibold lh-sm"><?= e($user['full_name']) ?></span>
                        <small class="text-muted"><?= e($user['role_name']) ?></small>
                    </span>
                    <i class="fa-solid fa-chevron-down small ms-1"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="<?= e(site_url('auth/change-password')) ?>"><i class="fa-solid fa-key fa-fw me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <?= form_open('auth/logout', array('class' => 'd-inline')) ?>
                            <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-right-from-bracket fa-fw me-2"></i>Logout</button>
                        <?= form_close() ?>
                    </li>
                </ul>
            </div>
            <?php else: ?>
                <span class="badge bg-warning text-dark">Preview mode</span>
            <?php endif; ?>
        </div>
    </header>

    <main class="app-content" id="appContent">
