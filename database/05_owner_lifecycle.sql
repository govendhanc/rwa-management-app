-- =====================================================================================
--  ROWA Portal - migration: owner lifecycle (deactivate / reactivate)
--
--  Run ONCE on a database that was installed before this change:
--      mysql -u USER -p --default-character-set=utf8mb4 rowa_portal < database/05_owner_lifecycle.sql
--  New installations do NOT need it (01_schema.sql and 02_seed_master.sql already include it).
--  Take a backup first (Settings -> Database Backup).
--
--  What it adds:
--    owners.deactivated_at / deactivated_by / deactivation_reason  - who deactivated an owner, when and why
--    owners.deleted_by                                             - who removed an owner entered by mistake
--    permission owners.deactivate (Super Admin and Admin)
--  No existing data is changed or deleted.
-- =====================================================================================

SET NAMES utf8mb4;

ALTER TABLE owners
    ADD COLUMN deactivated_at      DATETIME     NULL AFTER status,
    ADD COLUMN deactivated_by      INT UNSIGNED NULL AFTER deactivated_at,
    ADD COLUMN deactivation_reason VARCHAR(255) NULL AFTER deactivated_by,
    ADD COLUMN deleted_by          INT UNSIGNED NULL AFTER deleted_at,
    ADD CONSTRAINT fk_owners_deactivated_by FOREIGN KEY (deactivated_by) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_owners_deleted_by FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL;

-- Owners already marked Inactive before this change: keep the last update time as the deactivation time
UPDATE owners
SET deactivated_at = updated_at, deactivated_by = updated_by
WHERE status = 'Inactive' AND is_deleted = 0 AND deactivated_at IS NULL;

INSERT IGNORE INTO permissions (perm_key, module, description) VALUES
('owners.deactivate', 'owners', 'Deactivate and reactivate owners');

UPDATE permissions
SET description = 'Remove owners entered by mistake (no financial history; soft delete)'
WHERE perm_key = 'owners.delete';

-- Super Admin (role 1) and Admin (role 2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.perm_key = 'owners.deactivate'
WHERE r.id IN (1, 2);
