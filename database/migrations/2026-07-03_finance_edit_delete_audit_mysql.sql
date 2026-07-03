ALTER TABLE maintenance
  ADD COLUMN IF NOT EXISTS updated_by CHAR(36) NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS deleted_by CHAR(36) NULL AFTER updated_by,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL AFTER deleted_by;

ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS updated_by CHAR(36) NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS deleted_by CHAR(36) NULL AFTER updated_by,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL AFTER deleted_by,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE income
  ADD COLUMN IF NOT EXISTS updated_by CHAR(36) NULL AFTER created_by,
  ADD COLUMN IF NOT EXISTS deleted_by CHAR(36) NULL AFTER updated_by,
  ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL AFTER deleted_by,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

CREATE INDEX idx_maintenance_deleted_at ON maintenance(deleted_at);
CREATE INDEX idx_expenses_deleted_at ON expenses(deleted_at);
CREATE INDEX idx_income_deleted_at ON income(deleted_at);

INSERT IGNORE INTO roles (name, description) VALUES
  ('Manager', 'Can manage maintenance, expenses, income, and reports');
