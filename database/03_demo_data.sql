-- =====================================================================================
--  ROWA Portal
--  File    : database/03_demo_data.sql
--  Purpose : OPTIONAL demo data (dummy names, example.com e-mails, 90000xxxxx numbers).
--            Do NOT load on a production database.
--  Run after 01_schema.sql and 02_seed_master.sql.
--
--  Demo logins (password for all three: Demo@123)
--      secretary  -> Admin / Secretary
--      treasurer  -> Treasurer
--      viewer     -> Viewer
--
--  Rates: ₹300 per plot per month for both Vacant Plot and Constructed, from Aug 2026.
--  Billing Aug, Sep, Oct 2026 (one bill per plot per month); "today" = 07-Oct-2026.
--
--      OWN00001  paid every month                                      outstanding    0
--      OWN00002  paid Aug+Sep together, Oct pending                    outstanding  300
--      OWN00003  ADVANCE ₹1200 on 08-Aug -> Aug, then auto-adjusted Sep & Oct,
--                ₹300 credit left for November; house let to a tenant  outstanding    0 (credit 300)
--      OWN00004  Aug paid, Sep PARTIAL ₹200 of ₹300, Oct pending        outstanding  400
--      OWN00005  TWO PLOTS (house 5 + vacant plot 11); pays both plots
--                together for Aug and Sep, Oct pending on both          outstanding  600
--      OWN00006  Aug WAIVED (renovation), Sep & Oct pending             outstanding  600
--      OWN00007  paid Aug+Sep together, Oct paid; house let to a tenant outstanding    0
--      OWN00008  Aug paid, Sep payment CANCELLED, Oct pending           outstanding  600
--      OWN00009  joined Sep (no Aug bill), Sep paid, Oct pending        outstanding  300
--      OWN00010  paid Aug+Sep on 02-Oct, Oct pending                    outstanding  300
--      ------------------------------------------------------------------------------
--      Total outstanding 3,100 · Active collections 6,500 · Other income 1,045.50
--      Expenses 5,250 · Balance 2,295.50
-- =====================================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- -------------------------------------------------------------------------------------
-- Demo users (password: Demo@123)
-- -------------------------------------------------------------------------------------
INSERT INTO users (id, role_id, full_name, username, email, mobile, password_hash, status, must_change_password, password_changed_at, created_by) VALUES
(2, 2, 'Demo Secretary', 'secretary', 'secretary@example.com', '9000000201', '$2y$10$yttj1qX6HSq4Lnp7P6.1/.fhCzoCySW9KaDqYgywTm0LHu7aUh.sS', 'Active', 0, '2026-07-20 10:00:00', 1),
(3, 3, 'Demo Treasurer', 'treasurer', 'treasurer@example.com', '9000000202', '$2y$10$yttj1qX6HSq4Lnp7P6.1/.fhCzoCySW9KaDqYgywTm0LHu7aUh.sS', 'Active', 0, '2026-07-20 10:00:00', 1),
(4, 4, 'Demo Viewer',    'viewer',    'viewer@example.com',    '9000000203', '$2y$10$yttj1qX6HSq4Lnp7P6.1/.fhCzoCySW9KaDqYgywTm0LHu7aUh.sS', 'Active', 0, '2026-07-20 10:00:00', 1);

-- -------------------------------------------------------------------------------------
-- Maintenance rates (replace the install-month opening rates with history from Aug 2026)
-- -------------------------------------------------------------------------------------
DELETE FROM maintenance_rates;
INSERT INTO maintenance_rates (id, plot_category, amount, effective_from, remarks, created_by, created_at) VALUES
(1, 'Vacant Plot', 300.00, '2026-08-01', 'Opening rate', 1, '2026-07-25 09:00:00'),
(2, 'Constructed', 300.00, '2026-08-01', 'Opening rate', 1, '2026-07-25 09:00:00');

-- -------------------------------------------------------------------------------------
-- Owners (10)
-- -------------------------------------------------------------------------------------
INSERT INTO owners (id, owner_code, owner_name, co_owner_name, mobile, whatsapp_no, email, permanent_address, residential_address, owner_type, joining_date, maintenance_start_date, status, remarks, created_by, created_at) VALUES
(1,  'OWN00001', 'Arun Prakash',    NULL,            '9000000101', '9000000101', 'owner01@example.com', '12 Demo Street, Sample City', 'A-101, InfraCity Layout',     'Individual', '2025-04-01', '2026-08-01', 'Active', NULL, 1, '2026-07-25 10:00:00'),
(2,  'OWN00002', 'Bhavani Shankar', 'Meena Shankar', '9000000102', '9000000102', 'owner02@example.com', '45 Test Avenue, Sample City', 'A-102, InfraCity Layout',     'Joint',      '2025-04-01', '2026-08-01', 'Active', NULL, 1, '2026-07-25 10:05:00'),
(3,  'OWN00003', 'Chitra Devi',     NULL,            '9000000103', '9000000103', 'owner03@example.com', '7 Example Road, Sample Town', '7 Example Road, Sample Town', 'NRI',        '2025-05-10', '2026-08-01', 'Active', 'House let out to tenant', 1, '2026-07-25 10:10:00'),
(4,  'OWN00004', 'Dinesh Raj',      NULL,            '9000000104', '9000000114', 'owner04@example.com', 'A-104, InfraCity Layout',     'A-104, InfraCity Layout',     'Individual', '2025-06-15', '2026-08-01', 'Active', NULL, 1, '2026-07-25 10:15:00'),
(5,  'OWN00005', 'Elango Murugan',  NULL,            '9000000105', '9000000105', 'owner05@example.com', 'A-105, InfraCity Layout',     'A-105, InfraCity Layout',     'Individual', '2025-04-01', '2026-08-01', 'Active', 'Owns house 5 and vacant plot 11', 1, '2026-07-25 10:20:00'),
(6,  'OWN00006', 'Fathima Begum',   NULL,            '9000000106', '9000000106', 'owner06@example.com', '3 Sample Lane, Sample City',  '3 Sample Lane, Sample City',  'Individual', '2025-08-01', '2026-08-01', 'Active', 'House under renovation', 1, '2026-07-25 10:25:00'),
(7,  'OWN00007', 'Ganesh Babu',     NULL,            '9000000107', '9000000107', 'owner07@example.com', '9 Demo Nagar, Sample City',   '9 Demo Nagar, Sample City',   'Individual', '2025-09-01', '2026-08-01', 'Active', 'House let out to tenant', 1, '2026-07-25 10:30:00'),
(8,  'OWN00008', 'Hema Latha',      'Ravi Kumar',    '9000000108', '9000000108', 'owner08@example.com', 'B-103, InfraCity Layout',     'B-103, InfraCity Layout',     'Joint',      '2025-10-01', '2026-08-01', 'Active', NULL, 1, '2026-07-25 10:35:00'),
(9,  'OWN00009', 'Imran Khan',      NULL,            '9000000109', '9000000109', 'owner09@example.com', 'B-104, InfraCity Layout',     'B-104, InfraCity Layout',     'Individual', '2026-09-01', '2026-09-01', 'Active', 'Joined September 2026', 1, '2026-08-28 11:00:00'),
(10, 'OWN00010', 'Jayanthi Rao',    NULL,            '9000000110', '9000000110', 'owner10@example.com', 'B-105, InfraCity Layout',     'B-105, InfraCity Layout',     'Individual', '2025-04-01', '2026-08-01', 'Active', NULL, 1, '2026-07-25 10:45:00');

-- -------------------------------------------------------------------------------------
-- Houses / plots (11) - plot 11 is a vacant plot owned by OWN00005
-- -------------------------------------------------------------------------------------
INSERT INTO houses (id, plot_no, house_no, block, street, house_type, built_status, occupancy_status, owner_id, area_sqft, uds_sqft, remarks, status, created_by, created_at) VALUES
(1,  '1',  'A-101', 'A', '1st Cross Street', 'Villa',             'Built',     'Owner Occupied',  1,  1200.00, 1200.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(2,  '2',  'A-102', 'A', '1st Cross Street', 'Villa',             'Built',     'Owner Occupied',  2,  1200.00, 1200.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(3,  '3',  'A-103', 'A', '1st Cross Street', 'Villa',             'Built',     'Tenant Occupied', 3,  1200.00, 1200.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(4,  '4',  'A-104', 'A', '1st Cross Street', 'Independent House', 'Built',     'Owner Occupied',  4,  1500.00, 1500.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(5,  '5',  'A-105', 'A', '1st Cross Street', 'Independent House', 'Built',     'Owner Occupied',  5,  2400.00, 2400.00, 'Corner plot', 'Active', 1, '2026-07-25 09:00:00'),
(6,  '6',  'B-101', 'B', '2nd Cross Street', 'Villa',             'Built',     'Vacant',          6,  1200.00, 1200.00, 'Under renovation', 'Active', 1, '2026-07-25 09:00:00'),
(7,  '7',  'B-102', 'B', '2nd Cross Street', 'Villa',             'Built',     'Tenant Occupied', 7,  1200.00, 1200.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(8,  '8',  'B-103', 'B', '2nd Cross Street', 'Independent House', 'Built',     'Owner Occupied',  8,  1500.00, 1500.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(9,  '9',  'B-104', 'B', '2nd Cross Street', 'Villa',             'Built',     'Owner Occupied',  9,  1200.00, 1200.00, NULL, 'Active', 1, '2026-07-25 09:00:00'),
(10, '10', 'B-105', 'B', '2nd Cross Street', 'Independent House', 'Built',     'Owner Occupied',  10, 2400.00, 2400.00, 'Corner plot', 'Active', 1, '2026-07-25 09:00:00'),
(11, '11', NULL,    'B', '2nd Cross Street', 'Vacant Plot',       'Not Built', 'Vacant',          5,  1200.00, 1200.00, 'Empty plot, not constructed', 'Active', 1, '2026-07-25 09:00:00');

-- -------------------------------------------------------------------------------------
-- Tenants (records only - bills go to the owner)
-- -------------------------------------------------------------------------------------
INSERT INTO tenants (id, tenant_code, house_id, owner_id, tenant_name, mobile, whatsapp_no, email, permanent_address,
                     emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                     id_proof_type, id_proof_number, agreement_start, agreement_end,
                     move_in_date, move_out_date, status, remarks, created_by, created_at) VALUES
(1, 'TEN00001', 7, 7, 'Karthik Selvam', '9000000302', '9000000302', 'tenant02@example.com', '22 Old Town Road, Sample District',
    'Selvam K', '9000000392', 'Father', 'Driving Licence', 'TN00 00000000000', '2024-11-01', '2025-10-31',
    '2024-11-01', '2025-10-31', 'Moved Out', 'Agreement ended', 2, '2024-11-01 10:00:00'),
(2, 'TEN00002', 3, 3, 'Suresh Babu', '9000000301', '9000000301', 'tenant01@example.com', '5 River View, Sample Town',
    'Lakshmi Suresh', '9000000391', 'Spouse', 'Aadhaar', '0000', '2026-01-01', '2026-12-31',
    '2026-01-01', NULL, 'Active', NULL, 2, '2026-01-01 10:00:00'),
(3, 'TEN00003', 7, 7, 'Priya Raman', '9000000303', '9000000303', 'tenant03@example.com', '18 Lake Street, Sample City',
    'Raman P', '9000000393', 'Father', 'PAN', 'ABCDE0000F', '2025-11-01', '2026-10-31',
    '2025-11-01', NULL, 'Active', 'Agreement renewal due end of October', 2, '2025-11-01 10:00:00');

-- -------------------------------------------------------------------------------------
-- Maintenance batches (Aug, Sep, Oct 2026)
-- -------------------------------------------------------------------------------------
INSERT INTO maintenance_batches (id, billing_year, billing_month, due_date, total_records, total_amount, generated_by, generated_at) VALUES
(1, 2026, 8,  '2026-08-10', 10, 3000.00, 2, '2026-08-01 09:00:00'),
(2, 2026, 9,  '2026-09-10', 11, 3300.00, 2, '2026-09-01 09:00:00'),
(3, 2026, 10, '2026-10-10', 11, 3300.00, 2, '2026-10-01 09:00:00');

-- -------------------------------------------------------------------------------------
-- Maintenance charges: one per plot per month (balance_amount is generated)
-- rate_id 2 = Constructed ₹300, rate_id 1 = Vacant Plot ₹300
-- -------------------------------------------------------------------------------------
INSERT INTO maintenance (id, batch_id, owner_id, house_id, charge_type, plot_category, rate_id, billing_year, billing_month, period_start, due_date, amount, paid_amount, waived_amount, payment_status, record_status, generated_at, created_by) VALUES
-- August 2026 (owner 9 not billed - maintenance starts September)
(1,  1, 1,  1,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(2,  1, 2,  2,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(3,  1, 3,  3,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(4,  1, 4,  4,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(5,  1, 5,  5,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(6,  1, 6,  6,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 0.00,   300.00, 'Waived',         'Active', '2026-08-01 09:00:00', 2),
(7,  1, 7,  7,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(8,  1, 8,  8,  'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(9,  1, 10, 10, 'Monthly', 'Constructed', 2, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
(10, 1, 5,  11, 'Monthly', 'Vacant Plot', 1, 2026, 8,  '2026-08-01', '2026-08-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-08-01 09:00:00', 2),
-- September 2026
(11, 2, 1,  1,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(12, 2, 2,  2,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(13, 2, 3,  3,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(14, 2, 4,  4,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 200.00, 0.00,   'Partially Paid', 'Active', '2026-09-01 09:00:00', 2),
(15, 2, 5,  5,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(16, 2, 6,  6,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-09-01 09:00:00', 2),
(17, 2, 7,  7,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(18, 2, 8,  8,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-09-01 09:00:00', 2),
(19, 2, 9,  9,  'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(20, 2, 10, 10, 'Monthly', 'Constructed', 2, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
(21, 2, 5,  11, 'Monthly', 'Vacant Plot', 1, 2026, 9,  '2026-09-01', '2026-09-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-09-01 09:00:00', 2),
-- October 2026
(22, 3, 1,  1,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-10-01 09:00:00', 2),
(23, 3, 2,  2,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(24, 3, 3,  3,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-10-01 09:00:00', 2),
(25, 3, 4,  4,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(26, 3, 5,  5,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(27, 3, 6,  6,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(28, 3, 7,  7,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 300.00, 0.00,   'Paid',           'Active', '2026-10-01 09:00:00', 2),
(29, 3, 8,  8,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(30, 3, 9,  9,  'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(31, 3, 10, 10, 'Monthly', 'Constructed', 2, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2),
(32, 3, 5,  11, 'Monthly', 'Vacant Plot', 1, 2026, 10, '2026-10-01', '2026-10-10', 300.00, 0.00,   0.00,   'Pending',        'Active', '2026-10-01 09:00:00', 2);

-- -------------------------------------------------------------------------------------
-- Waiver
-- -------------------------------------------------------------------------------------
INSERT INTO maintenance_adjustments (id, maintenance_id, adjustment_type, amount, adjustment_date, reason, status, created_by, created_at) VALUES
(1, 6, 'Waiver', 300.00, '2026-08-25', 'Waived by committee resolution - house under renovation', 'Active', 2, '2026-08-25 18:00:00');

-- -------------------------------------------------------------------------------------
-- Payments (unallocated_amount is a generated column)
-- -------------------------------------------------------------------------------------
INSERT INTO payments (id, owner_id, payment_date, amount, allocated_amount, is_advance, payment_mode, transaction_ref, remarks, status, cancel_reason, cancelled_by, cancelled_at, received_by, created_by, created_at) VALUES
(1,  1,  '2026-08-05', 300.00,  300.00,  0, 'UPI',           'UPI-DEMO-0001',  NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-08-05 10:15:00'),
(2,  5,  '2026-08-06', 600.00,  600.00,  0, 'Cheque',        'CHQ-000101',     'Plots 5 and 11',              'Active',    NULL, NULL, NULL, 3, 3, '2026-08-06 11:00:00'),
(3,  3,  '2026-08-08', 1200.00, 900.00,  1, 'UPI',           'UPI-DEMO-0003',  'Advance for Aug-Nov 2026',    'Active',    NULL, NULL, NULL, 3, 3, '2026-08-08 09:40:00'),
(4,  8,  '2026-08-10', 300.00,  300.00,  0, 'Cash',          NULL,             NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-08-10 17:30:00'),
(5,  4,  '2026-08-20', 300.00,  300.00,  0, 'Cash',          NULL,             NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-08-20 18:05:00'),
(6,  7,  '2026-09-02', 600.00,  600.00,  0, 'UPI',           'UPI-DEMO-0006',  'Aug and Sep together',        'Active',    NULL, NULL, NULL, 3, 3, '2026-09-02 08:50:00'),
(7,  1,  '2026-09-04', 300.00,  300.00,  0, 'Cash',          NULL,             NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-09-04 19:10:00'),
(8,  5,  '2026-09-07', 600.00,  600.00,  0, 'NEFT',          'NEFT-DEMO-0008', 'Plots 5 and 11',              'Active',    NULL, NULL, NULL, 3, 3, '2026-09-07 12:00:00'),
(9,  8,  '2026-09-12', 300.00,  300.00,  0, 'Cash',          NULL,             NULL,                          'Cancelled', 'Entered by mistake - cash was not received', 3, '2026-09-13 11:20:00', 2, 2, '2026-09-12 18:45:00'),
(10, 2,  '2026-09-15', 600.00,  600.00,  0, 'Bank Transfer', 'IMPS-DEMO-0010', 'Aug and Sep together',        'Active',    NULL, NULL, NULL, 3, 3, '2026-09-15 10:30:00'),
(11, 9,  '2026-09-25', 300.00,  300.00,  0, 'UPI',           'UPI-DEMO-0011',  NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-09-25 20:00:00'),
(12, 10, '2026-10-02', 600.00,  600.00,  0, 'Bank Transfer', 'NEFT-DEMO-0012', 'Arrears for Aug and Sep',     'Active',    NULL, NULL, NULL, 3, 3, '2026-10-02 11:15:00'),
(13, 1,  '2026-10-03', 300.00,  300.00,  0, 'UPI',           'UPI-DEMO-0013',  NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-10-03 09:05:00'),
(14, 4,  '2026-10-05', 200.00,  200.00,  0, 'UPI',           'UPI-DEMO-0014',  'Part payment for September',  'Active',    NULL, NULL, NULL, 3, 3, '2026-10-05 16:40:00'),
(15, 7,  '2026-10-06', 300.00,  300.00,  0, 'UPI',           'UPI-DEMO-0015',  NULL,                          'Active',    NULL, NULL, NULL, 2, 2, '2026-10-06 10:00:00');

-- -------------------------------------------------------------------------------------
-- Payment allocations
-- -------------------------------------------------------------------------------------
INSERT INTO payment_allocations (id, payment_id, maintenance_id, amount, allocation_source, allocated_at, status, reversed_at, created_by) VALUES
(1,  1,  1,  300.00, 'Payment',            '2026-08-05 10:15:00', 'Active',   NULL, 2),
(2,  2,  5,  300.00, 'Payment',            '2026-08-06 11:00:00', 'Active',   NULL, 3),
(3,  2,  10, 300.00, 'Payment',            '2026-08-06 11:00:00', 'Active',   NULL, 3),
(4,  3,  3,  300.00, 'Payment',            '2026-08-08 09:40:00', 'Active',   NULL, 3),
(5,  4,  8,  300.00, 'Payment',            '2026-08-10 17:30:00', 'Active',   NULL, 2),
(6,  5,  4,  300.00, 'Payment',            '2026-08-20 18:05:00', 'Active',   NULL, 2),
(7,  3,  13, 300.00, 'Advance Adjustment', '2026-09-01 09:00:00', 'Active',   NULL, 2),
(8,  6,  7,  300.00, 'Payment',            '2026-09-02 08:50:00', 'Active',   NULL, 3),
(9,  6,  17, 300.00, 'Payment',            '2026-09-02 08:50:00', 'Active',   NULL, 3),
(10, 7,  11, 300.00, 'Payment',            '2026-09-04 19:10:00', 'Active',   NULL, 2),
(11, 8,  15, 300.00, 'Payment',            '2026-09-07 12:00:00', 'Active',   NULL, 3),
(12, 8,  21, 300.00, 'Payment',            '2026-09-07 12:00:00', 'Active',   NULL, 3),
(13, 9,  18, 300.00, 'Payment',            '2026-09-12 18:45:00', 'Reversed', '2026-09-13 11:20:00', 2),
(14, 10, 2,  300.00, 'Payment',            '2026-09-15 10:30:00', 'Active',   NULL, 3),
(15, 10, 12, 300.00, 'Payment',            '2026-09-15 10:30:00', 'Active',   NULL, 3),
(16, 11, 19, 300.00, 'Payment',            '2026-09-25 20:00:00', 'Active',   NULL, 2),
(17, 3,  24, 300.00, 'Advance Adjustment', '2026-10-01 09:00:00', 'Active',   NULL, 2),
(18, 12, 9,  300.00, 'Payment',            '2026-10-02 11:15:00', 'Active',   NULL, 3),
(19, 12, 20, 300.00, 'Payment',            '2026-10-02 11:15:00', 'Active',   NULL, 3),
(20, 13, 22, 300.00, 'Payment',            '2026-10-03 09:05:00', 'Active',   NULL, 2),
(21, 14, 14, 200.00, 'Payment',            '2026-10-05 16:40:00', 'Active',   NULL, 3),
(22, 15, 28, 300.00, 'Payment',            '2026-10-06 10:00:00', 'Active',   NULL, 2);

-- -------------------------------------------------------------------------------------
-- Receipts (snapshots at the time of payment; PDFs are generated on first download).
-- Multi-plot payments have house_id NULL and list all plots in plot_no / house_no.
-- -------------------------------------------------------------------------------------
INSERT INTO receipts (id, receipt_no, payment_id, owner_id, house_id, receipt_date, owner_name, plot_no, house_no, mobile, period_label, maintenance_amount, previous_outstanding, amount_received, advance_amount, balance_after, payment_mode, transaction_ref, received_by_name, pdf_path, status, cancelled_at, created_by, created_at) VALUES
(1,  'REC-2026-000001', 1,  1,  1,    '2026-08-05', 'Arun Prakash',    '1',     'A-101',    '9000000101', 'August 2026',                  300.00, 300.00, 300.00,  0.00,   0.00,   'UPI',           'UPI-DEMO-0001',  'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-08-05 10:15:00'),
(2,  'REC-2026-000002', 2,  5,  NULL, '2026-08-06', 'Elango Murugan',  '5, 11', 'A-105, -', '9000000105', 'August 2026',                  600.00, 600.00, 600.00,  0.00,   0.00,   'Cheque',        'CHQ-000101',     'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-08-06 11:00:00'),
(3,  'REC-2026-000003', 3,  3,  3,    '2026-08-08', 'Chitra Devi',     '3',     'A-103',    '9000000103', 'August 2026 + Advance',        300.00, 300.00, 1200.00, 900.00, 0.00,   'UPI',           'UPI-DEMO-0003',  'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-08-08 09:40:00'),
(4,  'REC-2026-000004', 4,  8,  8,    '2026-08-10', 'Hema Latha',      '8',     'B-103',    '9000000108', 'August 2026',                  300.00, 300.00, 300.00,  0.00,   0.00,   'Cash',          NULL,             'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-08-10 17:30:00'),
(5,  'REC-2026-000005', 5,  4,  4,    '2026-08-20', 'Dinesh Raj',      '4',     'A-104',    '9000000104', 'August 2026',                  300.00, 300.00, 300.00,  0.00,   0.00,   'Cash',          NULL,             'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-08-20 18:05:00'),
(6,  'REC-2026-000006', 6,  7,  7,    '2026-09-02', 'Ganesh Babu',     '7',     'B-102',    '9000000107', 'August 2026 - September 2026', 600.00, 600.00, 600.00,  0.00,   0.00,   'UPI',           'UPI-DEMO-0006',  'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-09-02 08:50:00'),
(7,  'REC-2026-000007', 7,  1,  1,    '2026-09-04', 'Arun Prakash',    '1',     'A-101',    '9000000101', 'September 2026',               300.00, 300.00, 300.00,  0.00,   0.00,   'Cash',          NULL,             'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-09-04 19:10:00'),
(8,  'REC-2026-000008', 8,  5,  NULL, '2026-09-07', 'Elango Murugan',  '5, 11', 'A-105, -', '9000000105', 'September 2026',               600.00, 600.00, 600.00,  0.00,   0.00,   'NEFT',          'NEFT-DEMO-0008', 'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-09-07 12:00:00'),
(9,  'REC-2026-000009', 9,  8,  8,    '2026-09-12', 'Hema Latha',      '8',     'B-103',    '9000000108', 'September 2026',               300.00, 300.00, 300.00,  0.00,   0.00,   'Cash',          NULL,             'Demo Secretary', NULL, 'Cancelled', '2026-09-13 11:20:00', 2, '2026-09-12 18:45:00'),
(10, 'REC-2026-000010', 10, 2,  2,    '2026-09-15', 'Bhavani Shankar', '2',     'A-102',    '9000000102', 'August 2026 - September 2026', 600.00, 600.00, 600.00,  0.00,   0.00,   'Bank Transfer', 'IMPS-DEMO-0010', 'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-09-15 10:30:00'),
(11, 'REC-2026-000011', 11, 9,  9,    '2026-09-25', 'Imran Khan',      '9',     'B-104',    '9000000109', 'September 2026',               300.00, 300.00, 300.00,  0.00,   0.00,   'UPI',           'UPI-DEMO-0011',  'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-09-25 20:00:00'),
(12, 'REC-2026-000012', 12, 10, 10,   '2026-10-02', 'Jayanthi Rao',    '10',    'B-105',    '9000000110', 'August 2026 - September 2026', 600.00, 900.00, 600.00,  0.00,   300.00, 'Bank Transfer', 'NEFT-DEMO-0012', 'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-10-02 11:15:00'),
(13, 'REC-2026-000013', 13, 1,  1,    '2026-10-03', 'Arun Prakash',    '1',     'A-101',    '9000000101', 'October 2026',                 300.00, 300.00, 300.00,  0.00,   0.00,   'UPI',           'UPI-DEMO-0013',  'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-10-03 09:05:00'),
(14, 'REC-2026-000014', 14, 4,  4,    '2026-10-05', 'Dinesh Raj',      '4',     'A-104',    '9000000104', 'September 2026',               300.00, 600.00, 200.00,  0.00,   400.00, 'UPI',           'UPI-DEMO-0014',  'Demo Treasurer', NULL, 'Active',    NULL,                  3, '2026-10-05 16:40:00'),
(15, 'REC-2026-000015', 15, 7,  7,    '2026-10-06', 'Ganesh Babu',     '7',     'B-102',    '9000000107', 'October 2026',                 300.00, 300.00, 300.00,  0.00,   0.00,   'UPI',           'UPI-DEMO-0015',  'Demo Secretary', NULL, 'Active',    NULL,                  2, '2026-10-06 10:00:00');

-- -------------------------------------------------------------------------------------
-- Expenses (Treasurer)
-- -------------------------------------------------------------------------------------
INSERT INTO expenses (id, expense_code, expense_date, category_id, description, amount, payment_mode, vendor, bill_number, remarks, status, created_by, created_at) VALUES
(1, 'EXP-2026-000001', '2026-08-05', 1, 'Common area electricity - July 2026',        850.00,  'Bank Transfer', 'Electricity Board (Demo)',   'EB-DEMO-0726', NULL, 'Active', 3, '2026-08-05 12:00:00'),
(2, 'EXP-2026-000002', '2026-08-10', 3, 'Housekeeping - August 2026',                600.00,  'Cash',          'Demo Cleaning Services',     'CL-0801',      NULL, 'Active', 3, '2026-08-10 12:00:00'),
(3, 'EXP-2026-000003', '2026-08-31', 4, 'Security guard - August 2026',              1000.00, 'Cash',          'Demo Security Agency',       'SEC-0831',     NULL, 'Active', 3, '2026-08-31 12:00:00'),
(4, 'EXP-2026-000004', '2026-09-05', 1, 'Common area electricity - August 2026',      820.00,  'Bank Transfer', 'Electricity Board (Demo)',   'EB-DEMO-0826', NULL, 'Active', 3, '2026-09-05 12:00:00'),
(5, 'EXP-2026-000005', '2026-09-18', 6, 'Street light replacement - 2nd Cross',       450.00,  'UPI',           'Demo Electricals',           'DE-1182',      NULL, 'Active', 3, '2026-09-18 12:00:00'),
(6, 'EXP-2026-000006', '2026-09-30', 4, 'Security guard - September 2026',           1000.00, 'Cash',          'Demo Security Agency',       'SEC-0930',     NULL, 'Active', 3, '2026-09-30 12:00:00'),
(7, 'EXP-2026-000007', '2026-10-04', 5, 'Park lawn trimming',                         350.00,  'Cash',          'Demo Gardeners',             NULL,           NULL, 'Active', 3, '2026-10-04 12:00:00'),
(8, 'EXP-2026-000008', '2026-10-06', 9, 'Receipt book printing and stationery',       180.00,  'Cash',          'Demo Stationers',            'ST-0455',      NULL, 'Active', 3, '2026-10-06 12:00:00');

-- -------------------------------------------------------------------------------------
-- Other income
-- -------------------------------------------------------------------------------------
INSERT INTO other_incomes (id, income_code, income_date, source, description, amount, payment_mode, reference, remarks, status, created_by, created_at) VALUES
(1, 'INC-2026-000001', '2026-09-20', 'Interest', 'Savings account interest - Q2',   45.50,   'Bank Transfer', 'INT-DEMO-Q2', NULL, 'Active', 3, '2026-09-20 12:00:00'),
(2, 'INC-2026-000002', '2026-10-01', 'Donation', 'Donation towards park benches',   1000.00, 'Cash',          NULL,          NULL, 'Active', 3, '2026-10-01 12:00:00');

-- -------------------------------------------------------------------------------------
-- Document sequences continue after demo numbers
-- -------------------------------------------------------------------------------------
INSERT INTO document_sequences (seq_type, seq_year, last_number) VALUES
('RECEIPT', 2026, 15),
('EXPENSE', 2026, 8),
('INCOME',  2026, 2),
('TENANT',  0,    3);

-- -------------------------------------------------------------------------------------
-- WhatsApp log samples
-- -------------------------------------------------------------------------------------
INSERT INTO whatsapp_messages (owner_id, receipt_id, maintenance_id, template_key, recipient_number, message_body, channel, status, created_by, created_at) VALUES
(1, 13, NULL, 'payment_receipt', '919000000101',
'Dear Arun Prakash,

Thank you for your maintenance payment.

Sree Amitra’s InfraCity Owners Welfare Association

Receipt No: REC-2026-000013
Payment Date: 03-Oct-2026
Maintenance: October 2026
Amount Paid: ₹300.00
Payment Mode: UPI
Balance: ₹0.00

Please find your payment receipt attached.

Thank you.
Association Management', 'click_to_chat', 'Prepared', 2, '2026-10-03 09:06:00'),
(6, NULL, 27, 'maintenance_reminder', '919000000106',
'Dear Fathima Begum,

This is a reminder that the maintenance payment for October 2026 is pending.

Plot No: 6
Amount Due: ₹600.00

Please make the payment at your convenience.

Thank you,
Sree Amitra’s InfraCity Owners Welfare Association', 'click_to_chat', 'Prepared', 2, '2026-10-06 18:00:00');

-- -------------------------------------------------------------------------------------
-- Notifications (one row per active user)
-- -------------------------------------------------------------------------------------
INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at)
SELECT u.id, n.type, n.title, n.message, n.link, 0, n.created_at
FROM users u
CROSS JOIN (
    SELECT 'maintenance' AS type, 'October 2026 maintenance generated' AS title,
           '11 bills, total ₹3,300.00. 1 bill settled automatically from advance credit.' AS message,
           'maintenance?period=2026-10' AS link, '2026-10-01 09:00:00' AS created_at
    UNION ALL
    SELECT 'payment', 'Payment received - REC-2026-000015',
           'Ganesh Babu (Plot 7) paid ₹300.00 by UPI.', 'receipts/view/15', '2026-10-06 10:00:00'
) n
WHERE u.status = 'Active';

-- -------------------------------------------------------------------------------------
-- Audit log samples
-- -------------------------------------------------------------------------------------
INSERT INTO audit_logs (user_id, username, action, module, record_id, old_value, new_value, ip_address, user_agent, created_at) VALUES
(2, 'secretary', 'Maintenance Generated', 'maintenance', '1', NULL, '{"billing_year":2026,"billing_month":8,"records":10,"total_amount":"3000.00"}', '127.0.0.1', 'demo-seed', '2026-08-01 09:00:00'),
(2, 'secretary', 'Maintenance Waived',    'maintenance', '6', '{"waived_amount":"0.00","payment_status":"Pending"}', '{"waived_amount":"300.00","payment_status":"Waived","reason":"Waived by committee resolution - house under renovation"}', '127.0.0.1', 'demo-seed', '2026-08-25 18:00:00'),
(2, 'secretary', 'Maintenance Generated', 'maintenance', '2', NULL, '{"billing_year":2026,"billing_month":9,"records":11,"total_amount":"3300.00","advance_adjusted":1}', '127.0.0.1', 'demo-seed', '2026-09-01 09:00:00'),
(3, 'treasurer', 'Payment Cancelled',     'payments',    '9', '{"status":"Active","receipt_no":"REC-2026-000009"}', '{"status":"Cancelled","reason":"Entered by mistake - cash was not received"}', '127.0.0.1', 'demo-seed', '2026-09-13 11:20:00'),
(2, 'secretary', 'Maintenance Generated', 'maintenance', '3', NULL, '{"billing_year":2026,"billing_month":10,"records":11,"total_amount":"3300.00","advance_adjusted":1}', '127.0.0.1', 'demo-seed', '2026-10-01 09:00:00'),
(3, 'treasurer', 'Expense Added',         'expenses',    '8', NULL, '{"expense_code":"EXP-2026-000008","amount":"180.00","category":"Office Expenses"}', '127.0.0.1', 'demo-seed', '2026-10-06 12:00:00'),
(2, 'secretary', 'Payment Added',         'payments',    '15', NULL, '{"owner_id":7,"amount":"300.00","payment_mode":"UPI","receipt_no":"REC-2026-000015"}', '127.0.0.1', 'demo-seed', '2026-10-06 10:00:00');

COMMIT;
