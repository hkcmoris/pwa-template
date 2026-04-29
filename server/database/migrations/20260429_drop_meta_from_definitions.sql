-- Migration: remove legacy JSON meta column from definitions
-- Run against MySQL 8.0+

ALTER TABLE definitions
  DROP COLUMN meta;

CREATE OR REPLACE VIEW definition_tree AS
WITH RECURSIVE tree AS (
  SELECT
    d.id,
    d.parent_id,
    d.title,
    d.position,
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
  created_at,
  updated_at,
  root_id,
  path,
  depth
FROM tree;
