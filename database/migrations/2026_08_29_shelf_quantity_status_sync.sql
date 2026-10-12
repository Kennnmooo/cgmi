-- Keep shelves.shelf_quantity and shelves.shelf_status in sync automatically.
--
-- Business rules (enforced at the database level so every write path is covered:
-- the shelves form, inventory save, and checker IoT replenishment):
--   1. A shelf with no item assigned always has quantity 0 and cannot be Occupied.
--   2. A shelf whose quantity is greater than 0 is automatically Occupied
--      (a real quantity always wins and promotes the shelf).
--   3. A shelf that is Unused or in Maintenance (with no positive quantity)
--      always has quantity 0.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_shelves_qty_status_bi $$
CREATE TRIGGER trg_shelves_qty_status_bi
BEFORE INSERT ON shelves
FOR EACH ROW
BEGIN
    IF NEW.item_id IS NULL THEN
        -- Rule 1: no item assigned -> empty, never Occupied
        SET NEW.shelf_quantity = 0;
        IF NEW.shelf_status = 'Occupied' THEN
            SET NEW.shelf_status = 'Unused';
        END IF;
    ELSEIF NEW.shelf_quantity IS NOT NULL AND NEW.shelf_quantity > 0 THEN
        -- Rule 2: quantity present -> Occupied (quantity wins)
        SET NEW.shelf_status = 'Occupied';
    ELSEIF NEW.shelf_status IN ('Unused', 'Maintenance') THEN
        -- Rule 3: Unused / Maintenance with no positive quantity -> empty
        SET NEW.shelf_quantity = 0;
    END IF;
END $$

DROP TRIGGER IF EXISTS trg_shelves_qty_status_bu $$
CREATE TRIGGER trg_shelves_qty_status_bu
BEFORE UPDATE ON shelves
FOR EACH ROW
BEGIN
    IF NEW.item_id IS NULL THEN
        -- Rule 1: no item assigned -> empty, never Occupied
        SET NEW.shelf_quantity = 0;
        IF NEW.shelf_status = 'Occupied' THEN
            SET NEW.shelf_status = 'Unused';
        END IF;
    ELSEIF NEW.shelf_quantity IS NOT NULL AND NEW.shelf_quantity > 0 THEN
        -- Rule 2: quantity present -> Occupied (quantity wins)
        SET NEW.shelf_status = 'Occupied';
    ELSEIF NEW.shelf_status IN ('Unused', 'Maintenance') THEN
        -- Rule 3: Unused / Maintenance with no positive quantity -> empty
        SET NEW.shelf_quantity = 0;
    END IF;
END $$

DELIMITER ;

-- Backfill existing rows so stored data already satisfies the invariant.
UPDATE shelves
SET shelf_quantity = 0
WHERE item_id IS NULL
   OR (shelf_status IN ('Unused', 'Maintenance') AND (shelf_quantity IS NULL OR shelf_quantity = 0));

UPDATE shelves
SET shelf_status = 'Occupied'
WHERE item_id IS NOT NULL
  AND shelf_quantity > 0;

UPDATE shelves
SET shelf_status = 'Unused'
WHERE item_id IS NULL
  AND shelf_status = 'Occupied';
