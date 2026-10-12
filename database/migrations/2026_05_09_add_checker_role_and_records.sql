-- Add Checker role and checker records table
-- Run this once in phpMyAdmin if you already imported the old database.

ALTER TABLE users MODIFY role ENUM('admin','cashier','inventory','checker') NOT NULL;

CREATE TABLE IF NOT EXISTS checker_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  record_type ENUM('incoming','outgoing') NOT NULL,
  inventory_item_id INT UNSIGNED NULL,
  sale_id INT UNSIGNED NULL,
  sale_item_id INT UNSIGNED NULL,
  sku VARCHAR(60) NULL,
  item_name VARCHAR(160) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
  unit VARCHAR(40) NULL,
  check_status ENUM('received','released','damaged','replacement','missing','pending','ok') NOT NULL DEFAULT 'ok',
  notes TEXT NULL,
  checked_by INT UNSIGNED NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_checker_type_date (record_type, checked_at),
  INDEX idx_checker_item (inventory_item_id),
  INDEX idx_checker_sale (sale_id, sale_item_id),
  UNIQUE KEY uq_checker_outgoing_sale_item (record_type, sale_item_id),
  CONSTRAINT fk_checker_inventory_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_checker_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_checker_sale_item FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_checker_user FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
