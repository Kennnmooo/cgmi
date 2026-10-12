-- Add Super Admin role to the users.role ENUM
-- Run this once in phpMyAdmin if you already imported the database.
-- NOTE: the role value uses a hyphen ("super-admin") to match the existing
-- database ENUM. All app code maps to this exact value.

ALTER TABLE users
  MODIFY role ENUM('super-admin','admin','inventory','checker','cashier') NOT NULL;
