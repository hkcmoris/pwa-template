-- Migration: create hierarchical definitions storage
-- Run against MySQL 8.0+

CREATE TABLE IF NOT EXISTS definitions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED DEFAULT NULL,
  title VARCHAR(191) NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  meta JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_definitions_parent FOREIGN KEY (parent_id) REFERENCES definitions(id) ON DELETE CASCADE,
  UNIQUE KEY uq_definitions_parent_position (parent_id, position),
  KEY idx_definitions_parent_title (parent_id, title),
  KEY idx_definitions_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS definition_components (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  definition_id BIGINT UNSIGNED NOT NULL,
  component_key VARCHAR(191) NOT NULL,
  props JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_component_definition_key (definition_id, component_key),
  CONSTRAINT fk_definition_components_def FOREIGN KEY (definition_id) REFERENCES definitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW definition_tree AS
WITH RECURSIVE tree AS (
  SELECT
    d.id,
    d.parent_id,
    d.title,
    d.position,
    d.meta,
    d.created_at,
    d.updated_at,
    d.id AS root_id,
    CAST(d.id AS CHAR(1024)) AS path,
    0 AS depth
  FROM definitions d
  WHERE d.parent_id IS NULL
  UNION ALL
  SELECT
    c.id,
    c.parent_id,
    c.title,
    c.position,
    c.meta,
    c.created_at,
    c.updated_at,
    tree.root_id,
    CONCAT(tree.path, '/', c.id) AS path,
    tree.depth + 1 AS depth
  FROM definitions c
  INNER JOIN tree ON tree.id = c.parent_id
)
SELECT
  id,
  parent_id,
  title,
  position,
  meta,
  created_at,
  updated_at,
  root_id,
  path,
  depth
FROM tree;

