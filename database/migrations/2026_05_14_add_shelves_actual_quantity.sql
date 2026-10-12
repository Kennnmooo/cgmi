-- Add a separate actual amount recorded on each IoT shelf.
-- inventory_items.shelf_quantity remains the Inventory IoT Shelves Total.
-- shelves.shelf_quantity is the amount recorded in the Shelves module.

ALTER TABLE shelves
  ADD COLUMN IF NOT EXISTS shelf_quantity INT NOT NULL DEFAULT 0 AFTER level_label;

UPDATE shelves sh
JOIN inventory_items i ON i.shelf_id = sh.id
SET sh.shelf_quantity = i.shelf_quantity
WHERE sh.shelf_quantity = 0 AND i.shelf_quantity > 0;

UPDATE shelves
SET shelf_quantity = 0
WHERE shelf_quantity < 0;
