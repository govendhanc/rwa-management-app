CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) UNIQUE NOT NULL,
  description TEXT
) ENGINE=InnoDB;

CREATE TABLE users (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  role_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  mobile VARCHAR(20),
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE association (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  name VARCHAR(180) NOT NULL,
  address TEXT NOT NULL,
  registration_number VARCHAR(100),
  bank_name VARCHAR(140),
  bank_account_number VARCHAR(80),
  bank_ifsc VARCHAR(30),
  bank_branch VARCHAR(120),
  financial_year_start_month INT NOT NULL DEFAULT 4 CHECK (financial_year_start_month BETWEEN 1 AND 12),
  monthly_maintenance_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  late_fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  gst_percent DECIMAL(5,2) DEFAULT 0,
  logo_url TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE plots (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  plot_number VARCHAR(40) UNIQUE NOT NULL,
  block VARCHAR(40),
  street VARCHAR(120),
  plot_size VARCHAR(60),
  water_connection BOOLEAN NOT NULL DEFAULT FALSE,
  eb_connection BOOLEAN NOT NULL DEFAULT FALSE,
  document_url TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE owners (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  plot_id CHAR(36) UNIQUE NOT NULL,
  owner_name VARCHAR(140) NOT NULL,
  father_or_husband_name VARCHAR(140),
  mobile_number VARCHAR(20) NOT NULL,
  email VARCHAR(160),
  address TEXT,
  occupancy_status ENUM('Vacant', 'Owner Occupied', 'Tenant') NOT NULL DEFAULT 'Owner Occupied',
  tenant_name VARCHAR(140),
  tenant_mobile VARCHAR(20),
  remarks TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_owners_plot FOREIGN KEY (plot_id) REFERENCES plots(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE maintenance (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  plot_id CHAR(36) NOT NULL,
  owner_id CHAR(36) NOT NULL,
  month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
  year INT NOT NULL CHECK (year BETWEEN 2000 AND 2100),
  monthly_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  previous_due DECIMAL(12,2) NOT NULL DEFAULT 0,
  late_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_date DATE,
  payment_mode ENUM('Cash', 'UPI', 'Bank Transfer', 'Cheque'),
  transaction_number VARCHAR(120),
  receipt_number VARCHAR(80) UNIQUE,
  status ENUM('Paid', 'Partially Paid', 'Unpaid') NOT NULL DEFAULT 'Unpaid',
  remarks TEXT,
  created_by CHAR(36),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_maintenance_plot_period (plot_id, month, year),
  CONSTRAINT fk_maintenance_plot FOREIGN KEY (plot_id) REFERENCES plots(id),
  CONSTRAINT fk_maintenance_owner FOREIGN KEY (owner_id) REFERENCES owners(id),
  CONSTRAINT fk_maintenance_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  expense_date DATE NOT NULL,
  category VARCHAR(80) NOT NULL,
  vendor_name VARCHAR(160),
  amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
  payment_mode ENUM('Cash', 'UPI', 'Bank Transfer', 'Cheque') NOT NULL,
  invoice_number VARCHAR(100),
  bill_url TEXT,
  description TEXT,
  created_by CHAR(36),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenses_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE income (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  income_date DATE NOT NULL,
  source VARCHAR(80) NOT NULL,
  amount DECIMAL(12,2) NOT NULL CHECK (amount >= 0),
  remarks TEXT,
  created_by CHAR(36),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_income_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE receipts (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  maintenance_id CHAR(36) UNIQUE NOT NULL,
  receipt_number VARCHAR(80) UNIQUE NOT NULL,
  receipt_date DATE NOT NULL DEFAULT (CURRENT_DATE),
  qr_payload TEXT,
  upi_qr_payload TEXT,
  printed_at TIMESTAMP NULL,
  emailed_at TIMESTAMP NULL,
  whatsapp_sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_receipts_maintenance FOREIGN KEY (maintenance_id) REFERENCES maintenance(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE settings (
  `key` VARCHAR(80) PRIMARY KEY,
  value JSON NOT NULL,
  updated_by CHAR(36),
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  user_id CHAR(36),
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80),
  entity_id CHAR(36),
  ip_address VARCHAR(45),
  metadata JSON,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_plots_plot_number ON plots(plot_number);
CREATE INDEX idx_owners_name ON owners(owner_name);
CREATE INDEX idx_owners_mobile ON owners(mobile_number);
CREATE INDEX idx_maintenance_period ON maintenance(year, month);
CREATE INDEX idx_maintenance_status ON maintenance(status);
CREATE INDEX idx_maintenance_plot ON maintenance(plot_id);
CREATE INDEX idx_expenses_date_category ON expenses(expense_date, category);
CREATE INDEX idx_income_date_source ON income(income_date, source);
CREATE INDEX idx_receipts_number ON receipts(receipt_number);
CREATE INDEX idx_audit_logs_user_date ON audit_logs(user_id, created_at);
