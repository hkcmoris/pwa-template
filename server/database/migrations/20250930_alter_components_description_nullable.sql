-- Migration: allow NULL descriptions on components
-- Run against MySQL 8.0+

ALTER TABLE components
  MODIFY description TEXT NULL;

