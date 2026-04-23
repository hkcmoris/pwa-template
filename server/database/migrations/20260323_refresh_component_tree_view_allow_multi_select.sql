-- Migration: include allow_multi_select in component_tree view
-- Run against MySQL 8.0+

CREATE OR REPLACE VIEW component_tree AS
WITH RECURSIVE tree AS (
SELECT
    c.id,
    c.definition_id,
    c.parent_id,
    c.alternate_title,
    c.description,
    c.images,
    c.color,
    c.allow_multi_select,
    c.properties,
    c.dependency_tree,
    c.position,
    c.created_at,
    c.updated_at,
    c.id AS root_id,
    CAST(c.id AS CHAR(1024)) AS id_path,
    CAST(c.position AS CHAR(1024)) AS pos_path,
    0 AS depth
FROM components c
WHERE c.parent_id IS NULL

UNION ALL

SELECT
    ch.id,
    ch.definition_id,
    ch.parent_id,
    ch.alternate_title,
    ch.description,
    ch.images,
    ch.color,
    ch.allow_multi_select,
    ch.properties,
    ch.dependency_tree,
    ch.position,
    ch.created_at,
    ch.updated_at,
    tree.root_id,
    CONCAT(tree.id_path, '/', ch.id) AS id_path,
    CONCAT(tree.pos_path, '-', ch.position) AS pos_path,
    tree.depth + 1 AS depth
FROM components ch
INNER JOIN tree ON tree.id = ch.parent_id
)
SELECT
    id,
    definition_id,
    parent_id,
    alternate_title,
    description,
    images,
    color,
    allow_multi_select,
    properties,
    dependency_tree,
    position,
    created_at,
    updated_at,
    root_id,
    id_path,
    pos_path,
    depth
FROM tree;
