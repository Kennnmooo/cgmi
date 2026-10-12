-- Migration: Add isActive column to incident_reviews
-- Date: 2026-08-14
-- Description: Supports archiving reviewed reports via isActive flag on incident_reviews
-- isActive = 1 means active (not archived), isActive = 0 means archived

ALTER TABLE `incident_reviews`
  ADD COLUMN `isActive` tinyint(1) NOT NULL DEFAULT 1 AFTER `review_notes`;
