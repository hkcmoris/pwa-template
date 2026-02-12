-- Migration: add configurable component properties
-- Run against MySQL 8.0+

ALTER TABLE components
  ADD COLUMN IF NOT EXISTS properties JSON NOT NULL DEFAULT (JSON_ARRAY()) AFTER color;
