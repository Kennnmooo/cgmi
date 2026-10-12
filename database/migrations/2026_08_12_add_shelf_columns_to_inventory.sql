-- Add shelf-related columns to inventory_items for IoT shelf integration.
-- These columns allow items to be assigned to physical shelves and track weight.

ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS shelf_id INT(10) UNSIGNED DEFAULT NULL AFTER shelf_tracking;

ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS shelf_quantity INT NOT NULL DEFAULT 0 AFTER shelf_id;

ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS estimated_unit_weight_kg DECIMAL(10,2) DEFAULT NULL AFTER shelf_quantity;

-- Add FK constraint for shelf_id → shelves.id (if not already present)
-- Using a safe approach: only add if the constraint doesn't exist
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'inventory_items' 
    AND CONSTRAINT_NAME = 'fk_inventory_items_shelf');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE inventory_items ADD CONSTRAINT fk_inventory_items_shelf FOREIGN KEY (shelf_id) REFERENCES shelves(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
