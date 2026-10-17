-- =====================================================================================
--  ROWA Portal - migration: bank / cash transfers (withdrawals and deposits)
--
--  For a database installed BEFORE this change (run 06_opening_balance.sql first if not done).
--  New installations do NOT need it (01_schema.sql and 02_seed_master.sql already include it).
--
--  1. Take a backup (Settings -> Database Backup, and download it).
--  2. Run:  mysql -u USER -p --default-character-set=utf8mb4 rowa_portal < database/07_fund_transfers.sql
--
--  Safe to run more than once. It creates one empty table and two permissions; no existing data
--  (payments, receipts, income, expenses, opening balance) is changed.
--
--  ROLLBACK (only before any transfer that should be kept is recorded - take a backup first):
--      DROP TABLE IF EXISTS fund_transfers;
--      DELETE FROM permissions WHERE perm_key IN ('transfers.view', 'transfers.manage');   -- grants cascade
--      DELETE FROM document_sequences WHERE seq_type = 'TRANSFER';
--  and restore the previous application files.
-- =====================================================================================

SET NAMES utf8mb4;

-- Money moved between the association's bank account and cash in hand. Changes the bank and cash
-- balances but never the total: 'Bank to Cash' = withdrawal, 'Cash to Bank' = deposit. Spending is
-- an expense, so a withdrawal and the expense paid from it are never counted twice. Never deleted.
CREATE TABLE IF NOT EXISTS fund_transfers (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    transfer_code   VARCHAR(20)   NOT NULL,
    transfer_date   DATE          NOT NULL,
    direction       ENUM('Bank to Cash','Cash to Bank') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    description     VARCHAR(255)  NOT NULL,
    reference       VARCHAR(100)  NULL,
    remarks         VARCHAR(500)  NULL,
    status          ENUM('Active','Cancelled') NOT NULL DEFAULT 'Active',
    cancel_reason   VARCHAR(255)  NULL,
    cancelled_by    INT UNSIGNED  NULL,
    cancelled_at    DATETIME      NULL,
    request_token   CHAR(32)      NULL,
    created_by      INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fund_transfers_code (transfer_code),
    UNIQUE KEY uq_fund_transfers_request (request_token),
    KEY ix_fund_transfers_status_date (status, transfer_date),
    CONSTRAINT chk_fund_transfers_amount CHECK (amount > 0),
    CONSTRAINT fk_fund_transfers_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_fund_transfers_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (perm_key, module, description) VALUES
('transfers.view',   'finance', 'View bank and cash balances and the fund ledger'),
('transfers.manage', 'finance', 'Record and cancel bank / cash withdrawals and deposits');

-- Super Admin (1) and Treasurer (3): view + manage; Admin (2) and Viewer (4): view
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.perm_key = 'transfers.view' WHERE r.id IN (1, 2, 3, 4);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.perm_key = 'transfers.manage' WHERE r.id IN (1, 3);
