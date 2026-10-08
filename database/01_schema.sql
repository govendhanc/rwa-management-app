-- =====================================================================================
--  ROWA Portal - Residential Owners Welfare Association Management Portal
--  File    : database/01_schema.sql
--  Purpose : Complete MySQL 8 schema (30 tables + 2 reporting views)
--  Requires: MySQL 8.0.16+ (CHECK constraints enforced), InnoDB, utf8mb4
--
--  Install order:
--      1. 01_schema.sql        (this file - DROPS and recreates all tables)
--      2. 02_seed_master.sql   (roles, permissions, admin user, settings, templates)
--      3. 03_demo_data.sql     (optional demo data - dummy people only)
--      4. 04_verify.sql        (optional - financial consistency checks)
--
--  Usage:
--      CREATE DATABASE rowa_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--      mysql -u <user> -p rowa_portal < 01_schema.sql
-- =====================================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW  IF EXISTS v_monthly_collection;
DROP VIEW  IF EXISTS v_owner_balances;

DROP TABLE IF EXISTS database_backups;
DROP TABLE IF EXISTS import_batches;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS whatsapp_messages;
DROP TABLE IF EXISTS other_incomes;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS receipts;
DROP TABLE IF EXISTS payment_allocations;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS maintenance_adjustments;
DROP TABLE IF EXISTS maintenance;
DROP TABLE IF EXISTS maintenance_batches;
DROP TABLE IF EXISTS maintenance_rates;
DROP TABLE IF EXISTS tenants;
DROP TABLE IF EXISTS houses;
DROP TABLE IF EXISTS owners;
DROP TABLE IF EXISTS document_sequences;
DROP TABLE IF EXISTS whatsapp_templates;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS association_settings;
DROP TABLE IF EXISTS ci_sessions;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS user_remember_tokens;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================================
--  4.1 SECURITY & ACCESS
-- =====================================================================================

CREATE TABLE roles (
    id           TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_key     VARCHAR(30)      NOT NULL,
    role_name    VARCHAR(50)      NOT NULL,
    description  VARCHAR(255)     NULL,
    is_system    TINYINT(1)       NOT NULL DEFAULT 1,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_role_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    perm_key     VARCHAR(60)       NOT NULL,
    module       VARCHAR(40)       NOT NULL,
    description  VARCHAR(150)      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_perm_key (perm_key),
    KEY ix_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id        TINYINT UNSIGNED  NOT NULL,
    permission_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY ix_role_permissions_permission (permission_id),
    CONSTRAINT fk_role_permissions_role       FOREIGN KEY (role_id)       REFERENCES roles (id)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                    INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    role_id               TINYINT UNSIGNED NOT NULL,
    full_name             VARCHAR(100)     NOT NULL,
    username              VARCHAR(50)      NOT NULL,
    email                 VARCHAR(150)     NOT NULL,
    mobile                VARCHAR(15)      NULL,
    password_hash         VARCHAR(255)     NOT NULL,
    status                ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    failed_attempts       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until          DATETIME         NULL,
    must_change_password  TINYINT(1)       NOT NULL DEFAULT 0,
    password_changed_at   DATETIME         NULL,
    last_login_at         DATETIME         NULL,
    last_login_ip         VARCHAR(45)      NULL,
    created_by            INT UNSIGNED     NULL,
    updated_by            INT UNSIGNED     NULL,
    created_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY ix_users_role (role_id),
    KEY ix_users_status (status),
    CONSTRAINT fk_users_role       FOREIGN KEY (role_id)    REFERENCES roles (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_remember_tokens (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    selector        CHAR(24)     NOT NULL,
    validator_hash  CHAR(64)     NOT NULL,
    user_agent      VARCHAR(255) NULL,
    expires_at      DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY ix_remember_user (user_id),
    KEY ix_remember_expires (expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    token_hash   CHAR(64)     NOT NULL,
    expires_at   DATETIME     NOT NULL,
    used_at      DATETIME     NULL,
    request_ip   VARCHAR(45)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_resets_token (token_hash),
    KEY ix_password_resets_user (user_id),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    login_identifier  VARCHAR(150) NOT NULL,
    ip_address        VARCHAR(45)  NOT NULL,
    is_success        TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_login_attempts_ip (ip_address, attempted_at),
    KEY ix_login_attempts_identifier (login_identifier, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CodeIgniter 3 database session driver (sess_match_ip = FALSE)
CREATE TABLE ci_sessions (
    id          VARCHAR(128) NOT NULL,
    ip_address  VARCHAR(45)  NOT NULL,
    `timestamp` INT UNSIGNED NOT NULL DEFAULT 0,
    data        BLOB         NOT NULL,
    PRIMARY KEY (id),
    KEY ci_sessions_timestamp (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
--  4.2 CONFIGURATION
-- =====================================================================================

CREATE TABLE association_settings (
    id                          TINYINT UNSIGNED NOT NULL DEFAULT 1,
    association_name            VARCHAR(200)     NOT NULL,
    short_name                  VARCHAR(60)      NULL,
    registration_no             VARCHAR(60)      NULL,
    address                     VARCHAR(255)     NULL,
    city                        VARCHAR(80)      NULL,
    district                    VARCHAR(80)      NULL,
    state                       VARCHAR(80)      NULL,
    pincode                     VARCHAR(10)      NULL,
    mobile                      VARCHAR(15)      NULL,
    email                       VARCHAR(150)     NULL,
    website                     VARCHAR(150)     NULL,
    logo_path                   VARCHAR(255)     NULL,
    bank_name                   VARCHAR(100)     NULL,
    bank_account_no             VARCHAR(30)      NULL,
    bank_ifsc                   VARCHAR(15)      NULL,
    bank_branch                 VARCHAR(100)     NULL,
    upi_id                      VARCHAR(100)     NULL,
    default_due_day             TINYINT UNSIGNED NOT NULL DEFAULT 10,
    receipt_prefix              VARCHAR(10)      NOT NULL DEFAULT 'REC',
    currency_symbol             VARCHAR(5)       NOT NULL DEFAULT '₹',
    receipt_footer_note         VARCHAR(255)     NULL,
    updated_by                  INT UNSIGNED     NULL,
    updated_at                  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_assoc_single_row   CHECK (id = 1),
    CONSTRAINT chk_assoc_due_day      CHECK (default_due_day BETWEEN 1 AND 28),
    CONSTRAINT fk_assoc_updated_by    FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_settings (
    setting_key    VARCHAR(100) NOT NULL,
    setting_value  TEXT         NULL,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE whatsapp_templates (
    id                   SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key         VARCHAR(50)       NOT NULL,
    template_name        VARCHAR(100)      NOT NULL,
    message_body         TEXT              NOT NULL,
    cloud_template_name  VARCHAR(100)      NULL,
    cloud_language       VARCHAR(10)       NOT NULL DEFAULT 'en',
    is_active            TINYINT(1)        NOT NULL DEFAULT 1,
    updated_by           INT UNSIGNED      NULL,
    created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_whatsapp_templates_key (template_key),
    CONSTRAINT fk_whatsapp_templates_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Race-safe document numbering. Always read with SELECT ... FOR UPDATE inside a transaction.
CREATE TABLE document_sequences (
    id           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    seq_type     VARCHAR(20)       NOT NULL,
    seq_year     SMALLINT UNSIGNED NOT NULL,
    last_number  INT UNSIGNED      NOT NULL DEFAULT 0,
    updated_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_document_sequences (seq_type, seq_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
--  4.3 MASTER DATA
-- =====================================================================================

CREATE TABLE owners (
    id                      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    owner_code              VARCHAR(12)    NULL,
    owner_name              VARCHAR(120)   NOT NULL,
    co_owner_name           VARCHAR(120)   NULL,
    mobile                  VARCHAR(15)    NOT NULL,
    whatsapp_no             VARCHAR(15)    NULL,
    email                   VARCHAR(150)   NULL,
    permanent_address       VARCHAR(255)   NULL,
    residential_address     VARCHAR(255)   NULL,
    owner_type              ENUM('Individual','Joint','Company','NRI','Other') NOT NULL DEFAULT 'Individual',
    joining_date            DATE           NULL,
    maintenance_start_date  DATE           NULL,
    status                  ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    deactivated_at          DATETIME       NULL,
    deactivated_by          INT UNSIGNED   NULL,
    deactivation_reason     VARCHAR(255)   NULL,
    remarks                 VARCHAR(500)   NULL,
    is_deleted              TINYINT(1)     NOT NULL DEFAULT 0,
    deleted_at              DATETIME       NULL,
    deleted_by              INT UNSIGNED   NULL,
    created_by              INT UNSIGNED   NULL,
    updated_by              INT UNSIGNED   NULL,
    created_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_owners_owner_code (owner_code),
    KEY ix_owners_name (owner_name),
    KEY ix_owners_mobile (mobile),
    KEY ix_owners_status (status, is_deleted),
    CONSTRAINT fk_owners_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_owners_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_owners_deactivated_by FOREIGN KEY (deactivated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_owners_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE houses (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    plot_no           VARCHAR(20)   NOT NULL,
    house_no          VARCHAR(20)   NULL,
    block             VARCHAR(20)   NOT NULL DEFAULT '',
    street            VARCHAR(100)  NULL,
    house_type        ENUM('Independent House','Villa','Apartment','Row House','Vacant Plot','Commercial','Other') NOT NULL DEFAULT 'Independent House',
    built_status      ENUM('Built','Under Construction','Not Built') NOT NULL DEFAULT 'Built',
    occupancy_status  ENUM('Owner Occupied','Tenant Occupied','Vacant') NOT NULL DEFAULT 'Vacant',
    owner_id          INT UNSIGNED  NULL,
    area_sqft         DECIMAL(10,2) NULL,
    uds_sqft          DECIMAL(10,2) NULL,
    remarks           VARCHAR(500)  NULL,
    status            ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_by        INT UNSIGNED  NULL,
    updated_by        INT UNSIGNED  NULL,
    created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_houses_plot_no (plot_no),
    UNIQUE KEY uq_houses_block_house (block, house_no),
    KEY ix_houses_owner (owner_id),
    KEY ix_houses_occupancy (occupancy_status),
    KEY ix_houses_status (status),
    CONSTRAINT chk_houses_area CHECK (area_sqft IS NULL OR area_sqft >= 0),
    CONSTRAINT chk_houses_uds  CHECK (uds_sqft IS NULL OR uds_sqft >= 0),
    CONSTRAINT fk_houses_owner      FOREIGN KEY (owner_id)   REFERENCES owners (id) ON DELETE RESTRICT,
    CONSTRAINT fk_houses_created_by FOREIGN KEY (created_by) REFERENCES users (id)  ON DELETE SET NULL,
    CONSTRAINT fk_houses_updated_by FOREIGN KEY (updated_by) REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tenants of rented houses (records only - bills always go to the owner).
-- At most one Active tenant per house (enforced by the generated unique key).
CREATE TABLE tenants (
    id                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    tenant_code               VARCHAR(12)   NULL,
    house_id                  INT UNSIGNED  NOT NULL,
    owner_id                  INT UNSIGNED  NULL,
    tenant_name               VARCHAR(120)  NOT NULL,
    mobile                    VARCHAR(15)   NOT NULL,
    whatsapp_no               VARCHAR(15)   NULL,
    email                     VARCHAR(150)  NULL,
    permanent_address         VARCHAR(255)  NULL,
    emergency_contact_name    VARCHAR(120)  NULL,
    emergency_contact_phone   VARCHAR(15)   NULL,
    emergency_contact_relation VARCHAR(50)  NULL,
    id_proof_type             ENUM('Aadhaar','PAN','Passport','Driving Licence','Voter ID','Other') NULL,
    id_proof_number           VARCHAR(30)   NULL,
    id_proof_file             VARCHAR(255)  NULL,
    id_proof_file_name        VARCHAR(255)  NULL,
    agreement_start           DATE          NULL,
    agreement_end             DATE          NULL,
    agreement_file            VARCHAR(255)  NULL,
    agreement_file_name       VARCHAR(255)  NULL,
    move_in_date              DATE          NOT NULL,
    move_out_date             DATE          NULL,
    status                    ENUM('Active','Moved Out') NOT NULL DEFAULT 'Active',
    active_house_id           INT UNSIGNED  GENERATED ALWAYS AS (IF(status = 'Active', house_id, NULL)) STORED,
    remarks                   VARCHAR(500)  NULL,
    created_by                INT UNSIGNED  NULL,
    updated_by                INT UNSIGNED  NULL,
    created_at                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenants_code (tenant_code),
    UNIQUE KEY uq_tenants_one_active_per_house (active_house_id),
    KEY ix_tenants_house (house_id),
    KEY ix_tenants_owner (owner_id),
    KEY ix_tenants_status (status),
    KEY ix_tenants_agreement_end (agreement_end),
    CONSTRAINT chk_tenants_move_out  CHECK (move_out_date IS NULL OR move_out_date >= move_in_date),
    CONSTRAINT chk_tenants_agreement CHECK (agreement_end IS NULL OR agreement_start IS NULL OR agreement_end >= agreement_start),
    CONSTRAINT chk_tenants_moved_out CHECK (status = 'Active' OR move_out_date IS NOT NULL),
    CONSTRAINT fk_tenants_house      FOREIGN KEY (house_id)   REFERENCES houses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tenants_owner      FOREIGN KEY (owner_id)   REFERENCES owners (id) ON DELETE RESTRICT,
    CONSTRAINT fk_tenants_created_by FOREIGN KEY (created_by) REFERENCES users (id)  ON DELETE SET NULL,
    CONSTRAINT fk_tenants_updated_by FOREIGN KEY (updated_by) REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
--  4.4 MAINTENANCE & COLLECTIONS
-- =====================================================================================

-- Rate history by plot category. The rate applied to a bill is the latest row whose
-- effective_from is on or before the billing month. Past bills never change.
CREATE TABLE maintenance_rates (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    plot_category   ENUM('Vacant Plot','Constructed') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    effective_from  DATE          NOT NULL,
    remarks         VARCHAR(255)  NULL,
    created_by      INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_maintenance_rates_category_month (plot_category, effective_from),
    KEY ix_maintenance_rates_effective (effective_from),
    CONSTRAINT chk_rates_amount    CHECK (amount >= 0),
    CONSTRAINT chk_rates_first_day CHECK (DAYOFMONTH(effective_from) = 1),
    CONSTRAINT fk_rates_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_batches (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    billing_year    SMALLINT UNSIGNED NOT NULL,
    billing_month   TINYINT UNSIGNED  NOT NULL,
    due_date        DATE              NOT NULL,
    total_records   INT UNSIGNED      NOT NULL DEFAULT 0,
    total_amount    DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    generated_by    INT UNSIGNED      NULL,
    generated_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_maintenance_batches_period (billing_year, billing_month),
    CONSTRAINT chk_batches_month  CHECK (billing_month BETWEEN 1 AND 12),
    CONSTRAINT chk_batches_year   CHECK (billing_year BETWEEN 2000 AND 2100),
    CONSTRAINT chk_batches_amount CHECK (total_amount >= 0),
    CONSTRAINT fk_batches_generated_by FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance (
    id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    batch_id        INT UNSIGNED      NULL,
    owner_id        INT UNSIGNED      NOT NULL,
    house_id        INT UNSIGNED      NOT NULL,
    charge_type     ENUM('Monthly','Opening Balance','Other') NOT NULL DEFAULT 'Monthly',
    plot_category   ENUM('Vacant Plot','Constructed') NULL,
    rate_id         INT UNSIGNED      NULL,
    billing_year    SMALLINT UNSIGNED NOT NULL,
    billing_month   TINYINT UNSIGNED  NOT NULL,
    period_start    DATE              NOT NULL,
    due_date        DATE              NOT NULL,
    amount          DECIMAL(12,2)     NOT NULL,
    paid_amount     DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    waived_amount   DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    balance_amount  DECIMAL(12,2)     GENERATED ALWAYS AS (amount - paid_amount - waived_amount) STORED,
    payment_status  ENUM('Pending','Partially Paid','Paid','Waived') NOT NULL DEFAULT 'Pending',
    record_status   ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    description     VARCHAR(255)      NULL,
    generated_at    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      INT UNSIGNED      NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Rule 7: one charge of a type per house per month
    UNIQUE KEY uq_maintenance_house_period (house_id, billing_year, billing_month, charge_type),
    KEY ix_maintenance_owner_status (owner_id, record_status, payment_status),
    KEY ix_maintenance_period (billing_year, billing_month),
    KEY ix_maintenance_period_start (period_start),
    KEY ix_maintenance_batch (batch_id),
    KEY ix_maintenance_payment_status (payment_status),
    CONSTRAINT chk_maintenance_month    CHECK (billing_month BETWEEN 1 AND 12),
    CONSTRAINT chk_maintenance_amount   CHECK (amount >= 0),
    CONSTRAINT chk_maintenance_nonneg   CHECK (paid_amount >= 0 AND waived_amount >= 0),
    CONSTRAINT chk_maintenance_overpaid CHECK (paid_amount + waived_amount <= amount),
    CONSTRAINT fk_maintenance_batch      FOREIGN KEY (batch_id)   REFERENCES maintenance_batches (id) ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_owner      FOREIGN KEY (owner_id)   REFERENCES owners (id)              ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_house      FOREIGN KEY (house_id)   REFERENCES houses (id)              ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_rate       FOREIGN KEY (rate_id)    REFERENCES maintenance_rates (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_created_by FOREIGN KEY (created_by) REFERENCES users (id)               ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_adjustments (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    maintenance_id   INT UNSIGNED  NOT NULL,
    adjustment_type  ENUM('Waiver','Discount') NOT NULL,
    amount           DECIMAL(12,2) NOT NULL,
    adjustment_date  DATE          NOT NULL,
    reason           VARCHAR(255)  NOT NULL,
    status           ENUM('Active','Reversed') NOT NULL DEFAULT 'Active',
    created_by       INT UNSIGNED  NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_adjustments_maintenance (maintenance_id, status),
    KEY ix_adjustments_date (adjustment_date),
    CONSTRAINT chk_adjustments_amount CHECK (amount > 0),
    CONSTRAINT fk_adjustments_maintenance FOREIGN KEY (maintenance_id) REFERENCES maintenance (id) ON DELETE RESTRICT,
    CONSTRAINT fk_adjustments_created_by  FOREIGN KEY (created_by)     REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    owner_id            INT UNSIGNED  NOT NULL,
    payment_date        DATE          NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    allocated_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unallocated_amount  DECIMAL(12,2) GENERATED ALWAYS AS (amount - allocated_amount) STORED,
    is_advance          TINYINT(1)    NOT NULL DEFAULT 0,
    payment_mode        ENUM('Cash','UPI','Bank Transfer','Cheque','NEFT','RTGS','Other') NOT NULL,
    transaction_ref     VARCHAR(100)  NULL,
    remarks             VARCHAR(500)  NULL,
    request_token       CHAR(32)      NULL,
    status              ENUM('Active','Cancelled','Reversed') NOT NULL DEFAULT 'Active',
    cancel_reason       VARCHAR(255)  NULL,
    cancelled_by        INT UNSIGNED  NULL,
    cancelled_at        DATETIME      NULL,
    received_by         INT UNSIGNED  NULL,
    created_by          INT UNSIGNED  NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- One-time form token: blocks double-submit / duplicate payment on refresh or double click
    UNIQUE KEY uq_payments_request_token (request_token),
    KEY ix_payments_owner_status (owner_id, status),
    KEY ix_payments_status_date (status, payment_date),
    KEY ix_payments_transaction_ref (transaction_ref),
    CONSTRAINT chk_payments_amount     CHECK (amount > 0),
    CONSTRAINT chk_payments_allocated  CHECK (allocated_amount >= 0 AND allocated_amount <= amount),
    -- Rule 3: an active payment may carry unallocated money only when it is an advance payment
    CONSTRAINT chk_payments_advance    CHECK (is_advance = 1 OR allocated_amount = amount OR status <> 'Active'),
    CONSTRAINT fk_payments_owner        FOREIGN KEY (owner_id)     REFERENCES owners (id) ON DELETE RESTRICT,
    CONSTRAINT fk_payments_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id)  ON DELETE SET NULL,
    CONSTRAINT fk_payments_received_by  FOREIGN KEY (received_by)  REFERENCES users (id)  ON DELETE SET NULL,
    CONSTRAINT fk_payments_created_by   FOREIGN KEY (created_by)   REFERENCES users (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_allocations (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    payment_id         INT UNSIGNED  NOT NULL,
    maintenance_id     INT UNSIGNED  NOT NULL,
    amount             DECIMAL(12,2) NOT NULL,
    allocation_source  ENUM('Payment','Advance Adjustment') NOT NULL DEFAULT 'Payment',
    allocated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status             ENUM('Active','Reversed') NOT NULL DEFAULT 'Active',
    reversed_at        DATETIME      NULL,
    created_by         INT UNSIGNED  NULL,
    PRIMARY KEY (id),
    KEY ix_allocations_payment (payment_id, status),
    KEY ix_allocations_maintenance (maintenance_id, status),
    CONSTRAINT chk_allocations_amount CHECK (amount > 0),
    CONSTRAINT fk_allocations_payment     FOREIGN KEY (payment_id)     REFERENCES payments (id)    ON DELETE RESTRICT,
    CONSTRAINT fk_allocations_maintenance FOREIGN KEY (maintenance_id) REFERENCES maintenance (id) ON DELETE RESTRICT,
    CONSTRAINT fk_allocations_created_by  FOREIGN KEY (created_by)     REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receipts (
    id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    receipt_no            VARCHAR(30)   NOT NULL,
    payment_id            INT UNSIGNED  NOT NULL,
    owner_id              INT UNSIGNED  NOT NULL,
    house_id              INT UNSIGNED  NULL,
    receipt_date          DATE          NOT NULL,
    owner_name            VARCHAR(120)  NOT NULL,
    plot_no               VARCHAR(100)  NULL,
    house_no              VARCHAR(150)  NULL,
    mobile                VARCHAR(15)   NULL,
    period_label          VARCHAR(150)  NULL,
    maintenance_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    previous_outstanding  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_received       DECIMAL(12,2) NOT NULL,
    advance_amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_mode          VARCHAR(20)   NOT NULL,
    transaction_ref       VARCHAR(100)  NULL,
    received_by_name      VARCHAR(100)  NULL,
    pdf_path              VARCHAR(255)  NULL,
    status                ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    cancelled_at          DATETIME      NULL,
    created_by            INT UNSIGNED  NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Rule 6: receipt numbers are unique; one receipt per payment
    UNIQUE KEY uq_receipts_receipt_no (receipt_no),
    UNIQUE KEY uq_receipts_payment (payment_id),
    KEY ix_receipts_owner (owner_id),
    KEY ix_receipts_date (receipt_date),
    CONSTRAINT chk_receipts_amounts CHECK (amount_received > 0 AND advance_amount >= 0 AND maintenance_amount >= 0),
    CONSTRAINT fk_receipts_payment    FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_receipts_owner      FOREIGN KEY (owner_id)   REFERENCES owners (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_receipts_house      FOREIGN KEY (house_id)   REFERENCES houses (id)   ON DELETE RESTRICT,
    CONSTRAINT fk_receipts_created_by FOREIGN KEY (created_by) REFERENCES users (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
--  4.5 FINANCE
-- =====================================================================================

CREATE TABLE expense_categories (
    id           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(60)       NOT NULL,
    description  VARCHAR(255)      NULL,
    status       ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expense_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
    id                        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    expense_code              VARCHAR(30)       NOT NULL,
    expense_date              DATE              NOT NULL,
    category_id               SMALLINT UNSIGNED NOT NULL,
    description               VARCHAR(255)      NOT NULL,
    amount                    DECIMAL(12,2)     NOT NULL,
    payment_mode              ENUM('Cash','UPI','Bank Transfer','Cheque','NEFT','RTGS','Other') NOT NULL,
    vendor                    VARCHAR(120)      NULL,
    bill_number               VARCHAR(60)       NULL,
    attachment_path           VARCHAR(255)      NULL,
    attachment_original_name  VARCHAR(255)      NULL,
    remarks                   VARCHAR(500)      NULL,
    status                    ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    cancel_reason             VARCHAR(255)      NULL,
    cancelled_by              INT UNSIGNED      NULL,
    cancelled_at              DATETIME          NULL,
    created_by                INT UNSIGNED      NULL,
    updated_by                INT UNSIGNED      NULL,
    created_at                DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expenses_code (expense_code),
    KEY ix_expenses_date (expense_date),
    KEY ix_expenses_category (category_id),
    KEY ix_expenses_status_date (status, expense_date),
    CONSTRAINT chk_expenses_amount CHECK (amount > 0),
    CONSTRAINT fk_expenses_category     FOREIGN KEY (category_id)  REFERENCES expense_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_expenses_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id)              ON DELETE SET NULL,
    CONSTRAINT fk_expenses_created_by   FOREIGN KEY (created_by)   REFERENCES users (id)              ON DELETE SET NULL,
    CONSTRAINT fk_expenses_updated_by   FOREIGN KEY (updated_by)   REFERENCES users (id)              ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE other_incomes (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    income_code   VARCHAR(30)   NOT NULL,
    income_date   DATE          NOT NULL,
    source        VARCHAR(80)   NOT NULL,
    description   VARCHAR(255)  NULL,
    amount        DECIMAL(12,2) NOT NULL,
    payment_mode  ENUM('Cash','UPI','Bank Transfer','Cheque','NEFT','RTGS','Other') NOT NULL,
    reference     VARCHAR(100)  NULL,
    remarks       VARCHAR(500)  NULL,
    status        ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    created_by    INT UNSIGNED  NULL,
    updated_by    INT UNSIGNED  NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_other_incomes_code (income_code),
    KEY ix_other_incomes_status_date (status, income_date),
    CONSTRAINT chk_other_incomes_amount CHECK (amount > 0),
    CONSTRAINT fk_other_incomes_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_other_incomes_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
--  4.6 COMMUNICATION, AUDIT & OPERATIONS
-- =====================================================================================

CREATE TABLE whatsapp_messages (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_id             INT UNSIGNED NOT NULL,
    receipt_id           INT UNSIGNED NULL,
    maintenance_id       INT UNSIGNED NULL,
    template_key         VARCHAR(50)  NOT NULL,
    recipient_number     VARCHAR(20)  NOT NULL,
    message_body         TEXT         NOT NULL,
    channel              ENUM('click_to_chat','cloud_api') NOT NULL DEFAULT 'click_to_chat',
    status               ENUM('Prepared','Sent','Failed') NOT NULL DEFAULT 'Prepared',
    provider_message_id  VARCHAR(100) NULL,
    error_message        VARCHAR(500) NULL,
    created_by           INT UNSIGNED NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_whatsapp_messages_owner (owner_id),
    KEY ix_whatsapp_messages_created (created_at),
    KEY ix_whatsapp_messages_template (template_key),
    CONSTRAINT fk_whatsapp_messages_owner       FOREIGN KEY (owner_id)       REFERENCES owners (id)      ON DELETE RESTRICT,
    CONSTRAINT fk_whatsapp_messages_receipt     FOREIGN KEY (receipt_id)     REFERENCES receipts (id)    ON DELETE SET NULL,
    CONSTRAINT fk_whatsapp_messages_maintenance FOREIGN KEY (maintenance_id) REFERENCES maintenance (id) ON DELETE SET NULL,
    CONSTRAINT fk_whatsapp_messages_created_by  FOREIGN KEY (created_by)     REFERENCES users (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    type        VARCHAR(30)  NOT NULL DEFAULT 'system',
    title       VARCHAR(150) NOT NULL,
    message     VARCHAR(500) NULL,
    link        VARCHAR(255) NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    read_at     DATETIME     NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_notifications_user_read (user_id, is_read),
    KEY ix_notifications_created (created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only: the application exposes no UPDATE/DELETE path for this table.
CREATE TABLE audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED    NULL,
    username    VARCHAR(50)     NULL,
    action      VARCHAR(60)     NOT NULL,
    module      VARCHAR(40)     NOT NULL,
    record_id   VARCHAR(40)     NULL,
    old_value   JSON            NULL,
    new_value   JSON            NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  VARCHAR(255)    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_audit_logs_user (user_id),
    KEY ix_audit_logs_module_record (module, record_id),
    KEY ix_audit_logs_created (created_at),
    KEY ix_audit_logs_action (action),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_batches (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_type         VARCHAR(30)  NOT NULL,
    original_file_name  VARCHAR(255) NULL,
    total_records       INT UNSIGNED NOT NULL DEFAULT 0,
    imported_count      INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count        INT UNSIGNED NOT NULL DEFAULT 0,
    duplicate_count     INT UNSIGNED NOT NULL DEFAULT 0,
    error_file_path     VARCHAR(255) NULL,
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_import_batches_created (created_at),
    CONSTRAINT fk_import_batches_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE database_backups (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    file_name   VARCHAR(150)    NOT NULL,
    file_size   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    method      ENUM('mysqldump','php') NOT NULL DEFAULT 'php',
    created_by  INT UNSIGNED    NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_database_backups_file (file_name),
    CONSTRAINT fk_database_backups_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================================
--  REPORTING VIEWS (SQL SECURITY INVOKER: no DEFINER problems on shared hosting)
-- =====================================================================================

-- Per-owner dues, payments, advance credit and last payment date.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_owner_balances AS
SELECT
    o.id                                AS owner_id,
    o.owner_code,
    o.owner_name,
    o.mobile,
    o.status,
    COALESCE(m.total_due, 0.00)         AS total_due,
    COALESCE(m.total_paid, 0.00)        AS total_paid,
    COALESCE(m.total_waived, 0.00)      AS total_waived,
    COALESCE(m.outstanding, 0.00)       AS outstanding,
    COALESCE(p.advance_credit, 0.00)    AS advance_credit,
    COALESCE(m.outstanding, 0.00) - COALESCE(p.advance_credit, 0.00) AS net_payable,
    p.last_payment_date
FROM owners o
LEFT JOIN (
    SELECT owner_id,
           SUM(amount)         AS total_due,
           SUM(paid_amount)    AS total_paid,
           SUM(waived_amount)  AS total_waived,
           SUM(balance_amount) AS outstanding
    FROM maintenance
    WHERE record_status = 'Active'
    GROUP BY owner_id
) m ON m.owner_id = o.id
LEFT JOIN (
    SELECT owner_id,
           SUM(unallocated_amount) AS advance_credit,
           MAX(payment_date)       AS last_payment_date
    FROM payments
    WHERE status = 'Active'
    GROUP BY owner_id
) p ON p.owner_id = o.id
WHERE o.is_deleted = 0;

-- Month-wise maintenance collection summary (monthly charges only).
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_monthly_collection AS
SELECT
    billing_year,
    billing_month,
    COUNT(DISTINCT owner_id)                       AS total_owners,
    COUNT(*)                                       AS total_records,
    SUM(amount)                                    AS total_maintenance,
    SUM(paid_amount)                               AS total_collected,
    SUM(waived_amount)                             AS total_waived,
    SUM(balance_amount)                            AS total_outstanding,
    ROUND(SUM(paid_amount) * 100 / NULLIF(SUM(amount) - SUM(waived_amount), 0), 2) AS collection_pct,
    SUM(payment_status = 'Paid')                   AS paid_count,
    SUM(payment_status = 'Partially Paid')         AS partial_count,
    SUM(payment_status = 'Pending')                AS pending_count,
    SUM(payment_status = 'Waived')                 AS waived_count
FROM maintenance
WHERE record_status = 'Active'
  AND charge_type   = 'Monthly'
GROUP BY billing_year, billing_month;
