-- Migration: add multi-select support flag for component option groups
-- Run against MySQL 8.0+

ALTER TABLE components
  ADD COLUMN IF NOT EXISTS allow_multi_select TINYINT(1) NOT NULL DEFAULT 0 AFTER color;
