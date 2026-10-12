-- Add weight-based IoT shelf capacity allocation.
-- Overall inventory quantity remains in inventory_items.quantity.
-- Only inventory_items.shelf_quantity is counted as stock currently placed on an IoT shelf.

ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS estimated_unit_weight_kg DECIMAL(10,2) DEFAULT NULL AFTER reorder_level;

ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS shelf_quantity INT NOT NULL DEFAULT 0 AFTER shelf_id;

UPDATE inventory_items
SET shelf_quantity = 0
WHERE shelf_id IS NULL OR shelf_tracking = 'not_shelves' OR shelf_quantity < 0;

UPDATE inventory_items
SET shelf_quantity = quantity
WHERE shelf_quantity > quantity;
