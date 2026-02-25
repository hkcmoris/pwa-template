-- Migration: create prices history table for components
-- Run against MySQL 8.0+

CREATE TABLE IF NOT EXISTS prices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  component_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'CZK',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_prices_component FOREIGN KEY (component_id) REFERENCES components(id) ON DELETE CASCADE,
  CHECK (amount >= 0),
  KEY idx_prices_component_created (component_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
