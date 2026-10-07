<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| ROWA Portal - application constants (non-environment, non-business)
| -------------------------------------------------------------------------
| Association details live in the association_settings table.
| Tunable security values (timeouts, lockout) live in system_settings.
*/

$config['app_name']    = 'ROWA Portal';
$config['app_version'] = '1.0.0';

// Minimum runtime requirements checked by the System Check page
$config['min_php_version']   = '8.1.0';
$config['required_php_extensions'] = array('mysqli', 'mbstring', 'json', 'fileinfo', 'openssl', 'dom', 'xml', 'ctype', 'session');
$config['recommended_php_extensions'] = array(
    'zlib' => 'Needed to create compressed database backups',
    'curl' => 'Needed for WhatsApp Cloud API mode',
    'gd'   => 'Needed only if the association logo is a PNG (JPG logos work in PDFs without it)',
);

// Writable directories (relative to STORAGEPATH unless absolute)
$config['writable_paths'] = array(
    'storage/logs'             => STORAGEPATH.'logs',
    'storage/cache'            => STORAGEPATH.'cache',
    'storage/uploads/expenses' => STORAGEPATH.'uploads'.DIRECTORY_SEPARATOR.'expenses',
    'storage/uploads/tenants'  => STORAGEPATH.'uploads'.DIRECTORY_SEPARATOR.'tenants',
    'storage/receipts'         => STORAGEPATH.'receipts',
    'storage/imports'          => STORAGEPATH.'imports',
    'storage/imports/errors'   => STORAGEPATH.'imports'.DIRECTORY_SEPARATOR.'errors',
    'storage/backups'          => STORAGEPATH.'backups',
    'public/uploads/branding'  => FCPATH.'uploads'.DIRECTORY_SEPARATOR.'branding',
);

// Upload rules
$config['upload_expense_types']  = 'pdf|jpg|jpeg|png';
$config['upload_expense_max_kb'] = 5120;
$config['upload_logo_types']     = 'png|jpg|jpeg';
$config['upload_logo_max_kb']    = 1024;
$config['upload_tenant_types']   = 'pdf|jpg|jpeg|png';
$config['upload_tenant_max_kb']  = 5120;
$config['upload_import_types']   = 'csv|txt';
$config['upload_import_max_kb']  = 2048;

// Shared lists (must match ENUMs in database/01_schema.sql)
$config['payment_modes'] = array('Cash', 'UPI', 'Bank Transfer', 'Cheque', 'NEFT', 'RTGS', 'Other');
$config['occupancy_statuses'] = array('Owner Occupied', 'Tenant Occupied', 'Vacant');
$config['owner_types'] = array('Individual', 'Joint', 'Company', 'NRI', 'Other');
$config['house_types'] = array('Independent House', 'Villa', 'Apartment', 'Row House', 'Vacant Plot', 'Commercial', 'Other');
$config['built_statuses'] = array('Built', 'Under Construction', 'Not Built');
$config['maintenance_payment_statuses'] = array('Pending', 'Partially Paid', 'Paid', 'Waived');

// Maintenance rate categories (ENUM in maintenance_rates / maintenance) and how a
// plot's built status maps to a category. A plot is billed at the Constructed rate
// only once it is fully built; under-construction plots pay the vacant-plot rate.
$config['plot_categories'] = array('Vacant Plot', 'Constructed');
$config['plot_category_by_built_status'] = array(
    'Built'              => 'Constructed',
    'Under Construction' => 'Vacant Plot',
    'Not Built'          => 'Vacant Plot',
);

// Tenants (ENUM in tenants.id_proof_type). For Aadhaar only the last 4 digits are stored.
$config['id_proof_types'] = array('Aadhaar', 'PAN', 'Passport', 'Driving Licence', 'Voter ID', 'Other');
$config['agreement_expiry_warning_days'] = 30;

// Defaults used when a system_settings key is missing
$config['default_session_timeout_minutes'] = 30;
$config['default_max_login_attempts']      = 5;
$config['default_lockout_minutes']         = 15;
