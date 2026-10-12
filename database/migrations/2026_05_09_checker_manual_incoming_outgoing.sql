-- Checker module adjustment:
-- 1) Incoming deliveries are manually listed by Checker and do NOT auto-add to inventory.
-- 2) Outgoing includes both POS Warehouse transactions and manual Checker entries.

ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS storage_location ENUM('store','warehouse') NOT NULL DEFAULT 'store';

CREATE TABLE IF NOT EXISTS checker_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_type ENUM('incoming','outgoing') NOT NULL,
  record_source ENUM('pos','manual') NOT NULL DEFAULT 'manual',
  inventory_item_id INT UNSIGNED NULL,
  sale_id INT UNSIGNED NULL,
  sale_item_id INT UNSIGNED NULL,
  sku VARCHAR(60) NULL,
  item_name VARCHAR(160) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit VARCHAR(40) NULL,
  supplier VARCHAR(160) NULL,
  purpose VARCHAR(80) NULL,
  check_status VARCHAR(40) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  checked_by INT UNSIGNED NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_checker_type_date (record_type, checked_at),
  INDEX idx_checker_item (inventory_item_id),
  INDEX idx_checker_sale (sale_id, sale_item_id),
  UNIQUE KEY uq_checker_outgoing_sale_item (record_type, sale_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE checker_records
  ADD COLUMN IF NOT EXISTS record_source ENUM('pos','manual') NOT NULL DEFAULT 'manual' AFTER record_type,
  ADD COLUMN IF NOT EXISTS supplier VARCHAR(160) NULL AFTER unit,
  ADD COLUMN IF NOT EXISTS purpose VARCHAR(80) NULL AFTER supplier;

ALTER TABLE checker_records
  MODIFY COLUMN check_status VARCHAR(40) NOT NULL DEFAULT 'pending';
