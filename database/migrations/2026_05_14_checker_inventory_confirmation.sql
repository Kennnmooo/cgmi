-- Checker inventory-item selection and incoming confirmation workflow.
-- Checker now selects existing inventory items for incoming/outgoing records.
-- Admin/Inventory confirms incoming records before stock is added to inventory.

ALTER TABLE checker_records
  ADD COLUMN IF NOT EXISTS approval_status VARCHAR(40) NOT NULL DEFAULT 'pending' AFTER check_status,
  ADD COLUMN IF NOT EXISTS confirmation_notes TEXT NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS confirmed_by INT UNSIGNED NULL AFTER checked_by,
  ADD COLUMN IF NOT EXISTS confirmed_at DATETIME NULL AFTER checked_at;

UPDATE checker_records
SET approval_status = 'pending'
WHERE record_type = 'incoming' AND (approval_status IS NULL OR approval_status = '');
