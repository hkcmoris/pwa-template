-- Returns the full component hierarchy ordered by parent/position.
-- Optional :root_id parameter limits to a subtree when provided.

WITH RECURSIVE component_tree AS (
  SELECT
    c.id,
    c.definition_id,
    c.parent_id,
    c.alternate_title,
    c.description,
    c.image,
    c.dependency_tree,
    c.position,
    d.title AS definition_title,
    c.id AS root_id,
    CAST(c.id AS CHAR(1024)) AS path,
    0 AS depth
  FROM components c
  INNER JOIN definitions d ON d.id = c.definition_id
  WHERE (:root_id IS NULL AND c.parent_id IS NULL)
     OR (:root_id IS NOT NULL AND c.id = :root_id)

  UNION ALL

  SELECT
    child.id,
    child.definition_id,
    child.parent_id,
    child.alternate_title,
    child.description,
    child.image,
    child.dependency_tree,
    child.position,
    def.title AS definition_title,
    component_tree.root_id,
    CONCAT(component_tree.path, '/', child.id) AS path,
    component_tree.depth + 1 AS depth
  FROM components child
  INNER JOIN component_tree ON component_tree.id = child.parent_id
  INNER JOIN definitions def ON def.id = child.definition_id
)
SELECT
  id,
  definition_id,
  parent_id,
  alternate_title,
  description,
  image,
  dependency_tree,
  position,
  definition_title,
  root_id,
  path,
  depth
FROM component_tree
ORDER BY path;
