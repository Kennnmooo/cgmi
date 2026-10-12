-- Checker warehouse workflow update
-- Run this after the first checker migration.

ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS storage_location ENUM('store','warehouse') NOT NULL DEFAULT 'store' AFTER fulfillment_type;

-- Existing completed sales default to store, so they will not appear in Checker outgoing list.
-- Only new transactions marked Warehouse Storage in POS will appear for checker release.
