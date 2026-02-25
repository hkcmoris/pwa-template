-- Pull an ordered subtree for a given definition root
-- Usage: mysql> SET @root_id := 1; -- 0 returns every tree
--        mysql> SOURCE server/database/queries/select_definition_tree.sql;

SET @root_id = COALESCE(@root_id, 0);

SELECT
  id,
  parent_id,
  title,
  position,
  depth,
  path,
  meta
FROM definition_tree
WHERE @root_id = 0 OR root_id = @root_id
ORDER BY root_id, path;
