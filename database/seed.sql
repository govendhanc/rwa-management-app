INSERT IGNORE INTO roles (name, description) VALUES
  ('Admin', 'Full access to all modules'),
  ('Manager', 'Can manage maintenance, expenses, income, and reports'),
  ('Treasurer', 'Can manage payments, expenses, income, and reports'),
  ('Read Only', 'Can view dashboards and reports');

INSERT INTO association (
  id, name, address, registration_number, mobile, email, bank_name, bank_account_number, bank_ifsc,
  bank_branch, monthly_maintenance_amount, late_fee_amount, gst_percent, logo_url
)
SELECT
  UUID(),
  'Sree Amitra''s InfraCity Owners Welfare Association',
  '530/1, Sree Amitra''s InfraCity Phase - I, Thudiyalur Road, Chinavedampatti, Coimbatore - 641049.',
  'TN Reg No: SRG/Coimbatore North/123/2025',
  '63803 18705',
  'saicowa@outlook.com',
  'State Bank of India',
  '12345678901',
  'SBIN0001234',
  'Chennai Main',
  500,
  50,
  0,
  '/uploads/association-logo.jpg'
WHERE NOT EXISTS (SELECT 1 FROM association);

UPDATE association SET
  name = 'Sree Amitra''s InfraCity Owners Welfare Association',
  address = '530/1, Sree Amitra''s InfraCity Phase - I, Thudiyalur Road, Chinavedampatti, Coimbatore - 641049.',
  registration_number = 'TN Reg No: SRG/Coimbatore North/123/2025',
  mobile = '63803 18705',
  email = 'saicowa@outlook.com',
  logo_url = '/uploads/association-logo.jpg';

INSERT IGNORE INTO users (id, role_id, name, email, password_hash, mobile)
SELECT UUID(), r.id, 'System Admin', 'admin@rwa.local', SHA2('Admin@123', 256), '9000000001'
FROM roles r WHERE r.name = 'Admin';

INSERT IGNORE INTO users (id, role_id, name, email, password_hash, mobile)
SELECT UUID(), r.id, 'Treasurer', 'treasurer@rwa.local', SHA2('Treasurer@123', 256), '9000000002'
FROM roles r WHERE r.name = 'Treasurer';

INSERT INTO owners (id, owner_name, father_or_husband_name, mobile_number, email, address, occupancy_status, tenant_name, tenant_mobile, remarks)
SELECT UUID(), 'Ravi Kumar', 'S Kumar', '9840011111', 'ravi@example.com', 'Chennai', 'Owner Occupied', NULL, NULL, 'Owns multiple plots'
WHERE NOT EXISTS (SELECT 1 FROM owners WHERE mobile_number = '9840011111');

INSERT INTO owners (id, owner_name, father_or_husband_name, mobile_number, email, address, occupancy_status, tenant_name, tenant_mobile)
SELECT UUID(), 'Meena Krishnan', 'R Krishnan', '9840022222', 'meena@example.com', 'Coimbatore', 'Tenant', 'Arun', '9840099999'
WHERE NOT EXISTS (SELECT 1 FROM owners WHERE mobile_number = '9840022222');

INSERT INTO owners (id, owner_name, father_or_husband_name, mobile_number, email, address, occupancy_status)
SELECT UUID(), 'Suresh Babu', 'N Babu', '9840033333', 'suresh@example.com', 'Bengaluru', 'Vacant'
WHERE NOT EXISTS (SELECT 1 FROM owners WHERE mobile_number = '9840033333');

INSERT INTO owners (id, owner_name, father_or_husband_name, mobile_number, email, address, occupancy_status)
SELECT UUID(), 'Lakshmi Narayanan', 'K Narayanan', '9840044444', 'lakshmi@example.com', 'Chennai', 'Owner Occupied'
WHERE NOT EXISTS (SELECT 1 FROM owners WHERE mobile_number = '9840044444');

INSERT IGNORE INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
SELECT UUID(), id, 'A-001', 'A', '1st Street', '1200 sqft', true, true
FROM owners WHERE mobile_number = '9840011111';

INSERT IGNORE INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
SELECT UUID(), id, 'A-003', 'A', '1st Street', '1200 sqft', true, true
FROM owners WHERE mobile_number = '9840011111';

INSERT IGNORE INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
SELECT UUID(), id, 'A-002', 'A', '1st Street', '1500 sqft', true, true
FROM owners WHERE mobile_number = '9840022222';

INSERT IGNORE INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
SELECT UUID(), id, 'B-015', 'B', 'Park Road', '1800 sqft', true, true
FROM owners WHERE mobile_number = '9840033333';

INSERT IGNORE INTO plots (id, owner_id, plot_number, block, street, plot_size, water_connection, eb_connection)
SELECT UUID(), id, 'C-022', 'C', 'Temple Street', '1200 sqft', false, true
FROM owners WHERE mobile_number = '9840044444';

INSERT IGNORE INTO maintenance (
  id, plot_id, owner_id, month, year, monthly_amount, previous_due, late_fee, discount,
  total_amount, paid_amount, balance, payment_date, payment_mode, transaction_number,
  receipt_number, status, remarks
)
SELECT UUID(), p.id, o.id, 6, 2026, 500, 0, 0, 0, 500, 500, 0, '2026-06-05', 'UPI', 'UPI12345', 'RWA-2026-0001', 'Paid', 'June payment'
FROM plots p JOIN owners o ON o.id = p.owner_id WHERE p.plot_number = 'A-001';

INSERT IGNORE INTO maintenance (
  id, plot_id, owner_id, month, year, monthly_amount, previous_due, late_fee, discount,
  total_amount, paid_amount, balance, payment_date, payment_mode, transaction_number,
  receipt_number, status, remarks
)
SELECT UUID(), p.id, o.id, 6, 2026, 500, 100, 50, 0, 650, 300, 350, '2026-06-15', 'Cash', NULL, 'RWA-2026-0002', 'Partially Paid', 'Part payment'
FROM plots p JOIN owners o ON o.id = p.owner_id WHERE p.plot_number = 'A-002';

INSERT IGNORE INTO receipts (id, maintenance_id, receipt_number, receipt_date, qr_payload)
SELECT UUID(), id, receipt_number, COALESCE(payment_date, CURRENT_DATE), CONCAT('Receipt ', receipt_number)
FROM maintenance
WHERE receipt_number IS NOT NULL;

INSERT IGNORE INTO expenses (id, expense_date, category, vendor_name, amount, payment_mode, invoice_number, description)
VALUES
  (UUID(), '2026-06-01', 'Security Salary', 'Secure Guard Services', 25000, 'Bank Transfer', 'SGS-0626', 'Monthly security salary'),
  (UUID(), '2026-06-10', 'Park Maintenance', 'Green Works', 4500, 'UPI', 'GW-441', 'Lawn trimming and watering');

INSERT IGNORE INTO income (id, income_date, source, amount, remarks)
VALUES
  (UUID(), '2026-06-12', 'Donation', 10000, 'Community hall improvement'),
  (UUID(), '2026-06-20', 'Interest', 1250, 'Bank interest');

INSERT INTO settings (`key`, value) VALUES
  ('receipt_prefix', JSON_QUOTE('RWA-2026')),
  ('penalty_percentage', '0'),
  ('upi_id', JSON_QUOTE('rwa@upi')),
  ('backup_schedule', JSON_QUOTE('daily')),
  ('dark_mode_enabled', 'true')
ON DUPLICATE KEY UPDATE value = VALUES(value);
