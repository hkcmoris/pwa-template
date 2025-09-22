-- Sample data for configurator definitions
-- Uses the "Nástavba" tree from the product brief
SET NAMES utf8mb4;

INSERT INTO definitions (id, parent_id, title, position, meta) VALUES
  (1, NULL, 'Nástavba', 0, NULL),
  (2, 1, 'Valník', 0, NULL),
  (3, 1, 'Skříňová nástavba', 1, NULL),
  (4, 1, 'Sklápěcí nástavba', 2, NULL),
  (5, 1, 'Odtahová nástavba', 3, NULL)
ON DUPLICATE KEY UPDATE
  parent_id = VALUES(parent_id),
  title = VALUES(title),
  position = VALUES(position),
  meta = VALUES(meta);
