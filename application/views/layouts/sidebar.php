<?php defined('BASEPATH') OR exit('No direct script access allowed');
$brand_name = association('short_name') !== '' ? association('short_name') : 'ROWA Portal';
$logo = logo_url();
?>
<aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
    <a class="sidebar-brand" href="<?= e(site_url('dashboard')) ?>">
        <?php if ($logo !== ''): ?>
            <img src="<?= e($logo) ?>" alt="" class="sidebar-logo">
        <?php else: ?>
            <span class="sidebar-logo sidebar-logo-icon"><i class="fa-solid fa-city"></i></span>
        <?php endif; ?>
        <span class="sidebar-brand-text">
            <strong><?= e($brand_name) ?></strong>
            <small>Owners Welfare Portal</small>
        </span>
    </a>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
        <?php foreach ($menu as $index => $item): ?>
            <?php if (isset($item['section'])): ?>
                <li class="sidebar-section"><?= e($item['section']) ?></li>
            <?php elseif (isset($item['children'])):
                $open = menu_group_is_active($item['children']);
                $collapse_id = 'menu-group-'.$index;
            ?>
                <li class="nav-item">
                    <a class="nav-link sidebar-toggle<?= $open ? '' : ' collapsed' ?>" data-bs-toggle="collapse" href="#<?= e($collapse_id) ?>" role="button" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= e($collapse_id) ?>">
                        <i class="<?= e($item['icon']) ?> fa-fw"></i>
                        <span><?= e($item['label']) ?></span>
                        <i class="fa-solid fa-chevron-down sidebar-caret"></i>
                    </a>
                    <div class="collapse<?= $open ? ' show' : '' ?>" id="<?= e($collapse_id) ?>">
                        <ul class="nav flex-column sidebar-submenu">
                        <?php foreach ($item['children'] as $child): ?>
                            <li class="nav-item">
                                <a class="nav-link<?= menu_is_active($child['url']) ? ' active' : '' ?>" href="<?= e(site_url($child['url'])) ?>"><?= e($child['label']) ?></a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link<?= menu_is_active($item['url']) ? ' active' : '' ?>" href="<?= e(site_url($item['url'])) ?>">
                        <i class="<?= e($item['icon']) ?> fa-fw"></i>
                        <span><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
        </ul>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
