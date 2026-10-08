<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Sidebar navigation
| -------------------------------------------------------------------------
| Each item:  label, icon (Font Awesome class), url (site_url segment),
|             perm (permission key; NULL = any logged-in user),
|             children (optional array of items).
| Items whose permission the user lacks are hidden; a parent is hidden
| when none of its children are visible. 'section' entries are headings.
| New modules are added here - no layout code changes are required.
*/

$config['sidebar_menu'] = array(
    array('label' => 'Dashboard', 'icon' => 'fa-solid fa-gauge-high', 'url' => 'dashboard', 'perm' => 'dashboard.view'),

    array('section' => 'Residents'),
    array('label' => 'Owners', 'icon' => 'fa-solid fa-users', 'children' => array(
        array('label' => 'Owner List',   'url' => 'owners',         'perm' => 'owners.view'),
        array('label' => 'Inactive Owners','url' => 'owners/inactive','perm' => 'owners.view'),
        array('label' => 'Add Owner',    'url' => 'owners/create',  'perm' => 'owners.create'),
        array('label' => 'Import Owners','url' => 'imports',        'perm' => 'owners.import'),
    )),
    array('label' => 'Houses / Plots', 'icon' => 'fa-solid fa-house', 'url' => 'houses', 'perm' => 'houses.view'),
    array('label' => 'Tenants', 'icon' => 'fa-solid fa-people-roof', 'url' => 'tenants', 'perm' => 'tenants.view'),

    array('section' => 'Collections'),
    array('label' => 'Maintenance', 'icon' => 'fa-solid fa-file-invoice', 'children' => array(
        array('label' => 'Maintenance List', 'url' => 'maintenance',           'perm' => 'maintenance.view'),
        array('label' => 'Generate Monthly', 'url' => 'maintenance/generate',  'perm' => 'maintenance.generate'),
        array('label' => 'Maintenance Rates','url' => 'rates',                 'perm' => 'maintenance.view'),
        array('label' => 'Pending Reminders','url' => 'maintenance/reminders', 'perm' => 'whatsapp.send'),
    )),
    array('label' => 'Payments', 'icon' => 'fa-solid fa-indian-rupee-sign', 'children' => array(
        array('label' => 'Collect Payment', 'url' => 'payments/collect',  'perm' => 'payments.create'),
        array('label' => 'Payment List',    'url' => 'payments',          'perm' => 'payments.view'),
        array('label' => 'Advance Credits', 'url' => 'payments/advances', 'perm' => 'payments.view'),
        array('label' => 'Receipts',        'url' => 'receipts',          'perm' => 'receipts.view'),
    )),

    array('section' => 'Finance'),
    array('label' => 'Expenses',     'icon' => 'fa-solid fa-money-bill-wave', 'url' => 'expenses', 'perm' => 'expenses.view'),
    array('label' => 'Other Income', 'icon' => 'fa-solid fa-hand-holding-dollar', 'url' => 'incomes', 'perm' => 'incomes.view'),
    array('label' => 'Reports', 'icon' => 'fa-solid fa-chart-column', 'children' => array(
        array('label' => 'Outstanding',        'url' => 'reports/outstanding',    'perm' => 'reports.view'),
        array('label' => 'Monthly Collection', 'url' => 'reports/collection',     'perm' => 'reports.view'),
        array('label' => 'Owner Statement',    'url' => 'reports/statement',      'perm' => 'reports.view'),
        array('label' => 'Income & Expense',   'url' => 'reports/income-expense', 'perm' => 'reports.financial'),
    )),

    array('section' => 'Communication'),
    array('label' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp', 'children' => array(
        array('label' => 'Message Templates', 'url' => 'whatsapp/templates', 'perm' => 'whatsapp.templates'),
        array('label' => 'Message Log',       'url' => 'whatsapp/log',       'perm' => 'whatsapp.send'),
        array('label' => 'WhatsApp Settings', 'url' => 'whatsapp/settings',  'perm' => 'settings.manage'),
    )),

    array('section' => 'Administration'),
    array('label' => 'Users',    'icon' => 'fa-solid fa-user-shield', 'url' => 'users', 'perm' => 'users.manage'),
    array('label' => 'Settings', 'icon' => 'fa-solid fa-gear', 'children' => array(
        array('label' => 'Association',     'url' => 'settings',        'perm' => 'settings.manage'),
        array('label' => 'System',          'url' => 'settings/system', 'perm' => 'settings.manage'),
        array('label' => 'Database Backup', 'url' => 'backup',          'perm' => 'backup.manage'),
    )),
    array('label' => 'Audit Logs', 'icon' => 'fa-solid fa-clipboard-list', 'url' => 'audit-logs', 'perm' => 'audit.view'),
);
