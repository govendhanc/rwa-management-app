-- =====================================================================================
--  ROWA Portal - migration: association opening balance (bank + cash brought forward)
--
--  For a database installed BEFORE this change. New installations do NOT need it
--  (01_schema.sql and 02_seed_master.sql already include it).
--
--  1. Take a backup first (Settings -> Database Backup, and download it).
--  2. Run:  mysql -u USER -p --default-character-set=utf8mb4 rowa_portal < database/06_opening_balance.sql
--  3. Log in as Super Admin / Admin -> Finance -> Opening Balance and enter the VERIFIED
--     bank and cash balances as of 31-Oct-2026.
--
--  Safe to run more than once: it only creates the table and permission when missing.
--  It does NOT insert any money figures - the opening balance is entered by an
--  authorised administrator in the portal (one active entry, enforced by the database).
--
--  ROLLBACK (only if no opening balance should be kept):
--      DROP TABLE IF EXISTS fund_opening_balances;
--      DELETE FROM permissions WHERE perm_key = 'finance.opening_balance';   -- grants cascade
--      DELETE FROM document_sequences WHERE seq_type = 'OPENING';
--  and restore the previous application files. Payments, receipts, maintenance and
--  expenses are not touched by this change, so nothing else needs to be rolled back.
-- =====================================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS fund_opening_balances (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    reference_no       VARCHAR(20)   NOT NULL,
    opening_date       DATE          NOT NULL,
    bank_amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cash_amount        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount       DECIMAL(12,2) GENERATED ALWAYS AS (bank_amount + cash_amount) STORED,
    remarks            VARCHAR(500)  NULL,
    status             ENUM('Active','Superseded') NOT NULL DEFAULT 'Active',
    active_key         TINYINT       GENERATED ALWAYS AS (IF(status = 'Active', 1, NULL)) STORED,
    corrects_id        INT UNSIGNED  NULL,
    correction_reason  VARCHAR(255)  NULL,
    superseded_at      DATETIME      NULL,
    superseded_by      INT UNSIGNED  NULL,
    request_token      CHAR(32)      NULL,
    created_by         INT UNSIGNED  NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fund_opening_reference (reference_no),
    UNIQUE KEY uq_fund_opening_active (active_key),
    UNIQUE KEY uq_fund_opening_request (request_token),
    KEY ix_fund_opening_corrects (corrects_id),
    CONSTRAINT chk_fund_opening_amounts CHECK (bank_amount >= 0 AND cash_amount >= 0),
    CONSTRAINT fk_fund_opening_corrects FOREIGN KEY (corrects_id) REFERENCES fund_opening_balances (id),
    CONSTRAINT fk_fund_opening_superseded_by FOREIGN KEY (superseded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_fund_opening_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (perm_key, module, description) VALUES
('finance.opening_balance', 'finance', 'Record and correct the association opening balance (bank + cash)');

-- Super Admin (role 1) and Admin (role 2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.perm_key = 'finance.opening_balance'
WHERE r.id IN (1, 2);
