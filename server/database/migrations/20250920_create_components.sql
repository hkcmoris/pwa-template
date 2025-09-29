-- Migration: create hierarchical components storage linked to definitions
-- Run against MySQL 8.0+

CREATE TABLE IF NOT EXISTS components (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  definition_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED DEFAULT NULL,
  alternate_title VARCHAR(191) DEFAULT NULL,
  description TEXT NULL,
  image VARCHAR(191) DEFAULT NULL,
  color VARCHAR(21) DEFAULT NULL,
  dependency_tree JSON NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_components_definition FOREIGN KEY (definition_id) REFERENCES definitions(id) ON DELETE CASCADE,
  CONSTRAINT fk_components_parent FOREIGN KEY (parent_id) REFERENCES components(id) ON DELETE CASCADE,
  UNIQUE KEY uq_components_parent_position (parent_id, position),
  KEY idx_components_definition (definition_id),
  KEY idx_components_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

