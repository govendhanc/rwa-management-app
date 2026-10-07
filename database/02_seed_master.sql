-- =====================================================================================
--  ROWA Portal
--  File    : database/02_seed_master.sql
--  Purpose : Mandatory master data - roles, permissions, role grants, Super Admin user,
--            association settings, system settings, expense categories, WhatsApp templates.
--  Run after 01_schema.sql. Safe for production (no demo people).
--
--  Default login (forced password change at first login):
--      Username : admin
--      Password : Admin@123
-- =====================================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- -------------------------------------------------------------------------------------
-- Roles
-- -------------------------------------------------------------------------------------
INSERT INTO roles (id, role_key, role_name, description, is_system) VALUES
(1, 'super_admin', 'Super Admin',               'Full access to every module, users, settings, audit logs and backups', 1),
(2, 'admin',       'Admin / Secretary',         'Owners, houses, maintenance generation, payments, receipts, WhatsApp, reports', 1),
(3, 'treasurer',   'Treasurer',                 'Payments, receipts, cancellations, expenses, other income, financial reports', 1),
(4, 'viewer',      'Viewer',                    'Read-only access', 1);

-- -------------------------------------------------------------------------------------
-- Permissions
-- -------------------------------------------------------------------------------------
INSERT INTO permissions (perm_key, module, description) VALUES
('dashboard.view',        'dashboard',   'View dashboard'),
('owners.view',           'owners',      'View owners'),
('owners.create',         'owners',      'Add owners'),
('owners.edit',           'owners',      'Edit owners'),
('owners.delete',         'owners',      'Delete (soft) owners without financial history'),
('owners.import',         'owners',      'Import owners from CSV/Excel'),
('houses.view',           'houses',      'View houses/plots'),
('houses.create',         'houses',      'Add houses/plots'),
('houses.edit',           'houses',      'Edit houses/plots and assign owners'),
('houses.delete',         'houses',      'Deactivate houses/plots'),
('tenants.view',          'tenants',     'View tenants (contact and agreement dates)'),
('tenants.manage',        'tenants',     'Add / edit tenants, record move-out'),
('tenants.id_proof',      'tenants',     'View tenant ID proof details and documents'),
('maintenance.view',      'maintenance', 'View maintenance records'),
('maintenance.generate',  'maintenance', 'Generate monthly maintenance'),
('maintenance.waive',     'maintenance', 'Waive / discount maintenance, cancel unpaid charges'),
('maintenance.rates',     'maintenance', 'Set maintenance rates (vacant plot / constructed)'),
('payments.view',         'payments',    'View payments and payment history'),
('payments.create',       'payments',    'Collect payments (incl. partial and advance)'),
('payments.cancel',       'payments',    'Cancel / reverse payments'),
('receipts.view',         'receipts',    'View receipts'),
('receipts.download',     'receipts',    'Download / print receipt PDF'),
('receipts.generate',     'receipts',    'Generate / regenerate receipt PDF'),
('whatsapp.send',         'whatsapp',    'Send WhatsApp receipts and reminders'),
('whatsapp.templates',    'whatsapp',    'Edit WhatsApp message templates'),
('expenses.view',         'expenses',    'View expenses'),
('expenses.create',       'expenses',    'Add expenses'),
('expenses.edit',         'expenses',    'Edit expenses'),
('expenses.cancel',       'expenses',    'Cancel expenses'),
('incomes.view',          'incomes',     'View other income'),
('incomes.create',        'incomes',     'Add other income'),
('incomes.edit',          'incomes',     'Edit other income'),
('incomes.cancel',        'incomes',     'Cancel other income'),
('reports.view',          'reports',     'Outstanding, monthly collection and owner statement reports'),
('reports.financial',     'reports',     'Income & expense reports'),
('users.manage',          'users',       'Manage users and roles'),
('settings.manage',       'settings',    'Association and system settings'),
('audit.view',            'audit',       'View audit logs'),
('backup.manage',         'backup',      'Create and download database backups');

-- -------------------------------------------------------------------------------------
-- Role grants (Super Admin also bypasses checks in code)
-- -------------------------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE perm_key IN (
    'dashboard.view',
    'owners.view', 'owners.create', 'owners.edit', 'owners.delete', 'owners.import',
    'houses.view', 'houses.create', 'houses.edit', 'houses.delete',
    'tenants.view', 'tenants.manage', 'tenants.id_proof',
    'maintenance.view', 'maintenance.generate', 'maintenance.waive', 'maintenance.rates',
    'payments.view', 'payments.create',
    'receipts.view', 'receipts.download', 'receipts.generate',
    'whatsapp.send', 'whatsapp.templates',
    'expenses.view', 'incomes.view',
    'reports.view', 'reports.financial'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE perm_key IN (
    'dashboard.view',
    'owners.view', 'houses.view', 'tenants.view', 'maintenance.view',
    'payments.view', 'payments.create', 'payments.cancel',
    'receipts.view', 'receipts.download', 'receipts.generate',
    'whatsapp.send',
    'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.cancel',
    'incomes.view', 'incomes.create', 'incomes.edit', 'incomes.cancel',
    'reports.view', 'reports.financial'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE perm_key IN (
    'dashboard.view',
    'owners.view', 'houses.view', 'tenants.view', 'maintenance.view', 'payments.view',
    'receipts.view', 'receipts.download',
    'expenses.view', 'incomes.view',
    'reports.view', 'reports.financial'
);

-- -------------------------------------------------------------------------------------
-- Super Admin user  (password: Admin@123 - bcrypt via password_hash(PASSWORD_DEFAULT))
-- -------------------------------------------------------------------------------------
INSERT INTO users (id, role_id, full_name, username, email, mobile, password_hash, status, must_change_password, password_changed_at) VALUES
(1, 1, 'System Administrator', 'admin', 'admin@example.com', NULL,
 '$2y$10$DRO8AAeMnYkEaIFynjfjrOSsNxv2WGiPB7rTsFLVYShYeKY6uchxe', 'Active', 1, NULL);

-- -------------------------------------------------------------------------------------
-- Association settings (single row - edit from Settings > Association)
-- -------------------------------------------------------------------------------------
INSERT INTO association_settings (
    id, association_name, short_name, registration_no, address, city, district, state, pincode,
    mobile, email, website, logo_path, bank_name, bank_account_no, bank_ifsc, bank_branch, upi_id,
    default_due_day, receipt_prefix, currency_symbol, receipt_footer_note, updated_by
) VALUES (
    1, 'Sree Amitra’s InfraCity Owners Welfare Association', 'SAICOWA', 'SRG/Coimbatore North/123/2025',
    '530/1, Sree Amitra’s InfraCity Phase – I, Thudiyalur Road, Chinavedampatti', 'Coimbatore', 'Coimbatore', 'Tamil Nadu', '641049',
    '6380318705', 'saicowa@outlook.com', NULL, 'uploads/branding/logo.jpg',
    NULL, NULL, NULL, NULL, NULL,
    10, 'REC', '₹', 'This is a computer generated receipt and does not require a signature.', 1
);

-- -------------------------------------------------------------------------------------
-- System settings (non-secret). WhatsApp Cloud API token lives in .env only.
-- -------------------------------------------------------------------------------------
INSERT INTO system_settings (setting_key, setting_value) VALUES
('whatsapp_mode',                  'click_to_chat'),
('whatsapp_country_code',          '91'),
('whatsapp_cloud_phone_number_id', ''),
('whatsapp_cloud_api_version',     'v21.0'),
('session_timeout_minutes',        '30'),
('max_login_attempts',             '5'),
('lockout_minutes',                '15'),
('receipt_paper',                  'A4'),
('date_format',                    'd-M-Y'),
('registration_label',             'TN Reg No');

-- -------------------------------------------------------------------------------------
-- Opening maintenance rates (₹300 for every plot, from the month of installation).
-- Future increases/decreases are added from Maintenance > Maintenance Rates.
-- -------------------------------------------------------------------------------------
INSERT INTO maintenance_rates (plot_category, amount, effective_from, remarks, created_by) VALUES
('Vacant Plot', 300.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), 'Opening rate', 1),
('Constructed', 300.00, DATE_FORMAT(CURDATE(), '%Y-%m-01'), 'Opening rate', 1);

-- -------------------------------------------------------------------------------------
-- Expense categories
-- -------------------------------------------------------------------------------------
INSERT INTO expense_categories (id, name, description) VALUES
(1,  'Electricity',     'Common area lighting, pumps, gate motors'),
(2,  'Water',           'Water tanker, borewell, water bills'),
(3,  'Cleaning',        'Housekeeping and garbage collection'),
(4,  'Security',        'Security guard services'),
(5,  'Garden',          'Park and landscaping upkeep'),
(6,  'Repairs',         'Repairs to common assets'),
(7,  'Maintenance',     'Scheduled maintenance contracts'),
(8,  'Staff Salary',    'Salaries of association staff'),
(9,  'Office Expenses', 'Stationery, printing, bank charges'),
(10, 'Other',           'Any other expense');

-- -------------------------------------------------------------------------------------
-- WhatsApp templates (editable from WhatsApp > Templates)
-- Placeholders: {OWNER_NAME} {PLOT_NO} {HOUSE_NO} {MONTH} {YEAR} {AMOUNT} {PAYMENT_MODE}
--               {RECEIPT_NO} {BALANCE} {ASSOCIATION_NAME} {PAYMENT_DATE} {DUE_DATE}
-- -------------------------------------------------------------------------------------
INSERT INTO whatsapp_templates (template_key, template_name, message_body, cloud_template_name, cloud_language, is_active, updated_by) VALUES
('payment_receipt', 'Payment Received',
'Dear {OWNER_NAME},

Thank you for your maintenance payment.

{ASSOCIATION_NAME}

Receipt No: {RECEIPT_NO}
Payment Date: {PAYMENT_DATE}
Maintenance: {MONTH}
Amount Paid: ₹{AMOUNT}
Payment Mode: {PAYMENT_MODE}
Balance: ₹{BALANCE}

Please find your payment receipt attached.

Thank you.
Association Management',
 NULL, 'en', 1, 1),

('maintenance_reminder', 'Maintenance Reminder',
'Dear {OWNER_NAME},

This is a reminder that the maintenance payment for {MONTH} {YEAR} is pending.

Plot No: {PLOT_NO}
Amount Due: ₹{BALANCE}

Please make the payment at your convenience.

Thank you,
{ASSOCIATION_NAME}',
 NULL, 'en', 1, 1);

COMMIT;
