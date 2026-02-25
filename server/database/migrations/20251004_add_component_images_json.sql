-- Migration: add JSON images list for components
-- Run against MySQL 8.0+

ALTER TABLE components
  ADD COLUMN IF NOT EXISTS images JSON NOT NULL DEFAULT (JSON_ARRAY());

SET @has_component_image_column := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'components'
    AND COLUMN_NAME = 'image'
);

SET @component_image_migration_sql := IF(
  @has_component_image_column = 0,
  'SELECT 1',
  "UPDATE components SET images = CASE WHEN COALESCE(JSON_LENGTH(images), 0) = 0 THEN JSON_ARRAY(image) ELSE images END WHERE image IS NOT NULL AND image <> ''"
);

PREPARE component_image_stmt FROM @component_image_migration_sql;
EXECUTE component_image_stmt;
DEALLOCATE PREPARE component_image_stmt;

ALTER TABLE components
  DROP COLUMN IF EXISTS image;
