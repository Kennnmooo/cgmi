-- Add item-level classification for IoT shelf eligibility.
-- Storage Type: IoT Shelves = item can be assigned to IoT shelves.
-- Storage Type: Warehouse = item remains regular inventory/POS stock and cannot be assigned to shelves.

ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS shelf_tracking ENUM('shelves','not_shelves') NOT NULL DEFAULT 'not_shelves' AFTER subcategory;

UPDATE inventory_items
SET shelf_tracking = 'shelves'
WHERE shelf_id IS NOT NULL;

UPDATE inventory_items
SET shelf_id = NULL
WHERE shelf_tracking = 'not_shelves';
