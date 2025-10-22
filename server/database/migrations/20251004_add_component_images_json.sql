-- Migration: add JSON images list for components
-- Run against MySQL 8.0+

ALTER TABLE components
  ADD COLUMN images JSON NOT NULL DEFAULT (JSON_ARRAY());

UPDATE components
SET images = JSON_ARRAY(image)
WHERE image IS NOT NULL AND image <> '';
