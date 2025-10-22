-- Migration: add color support to components
-- Run against MySQL 8.0+

ALTER TABLE components
  ADD COLUMN IF NOT EXISTS color VARCHAR(21) DEFAULT NULL;

