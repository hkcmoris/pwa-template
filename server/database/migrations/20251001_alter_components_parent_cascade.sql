-- Migration: enforce cascading delete for parent-child components
-- Run against MySQL 8.0+

ALTER TABLE components
  DROP FOREIGN KEY fk_components_parent;

ALTER TABLE components
  ADD CONSTRAINT fk_components_parent
    FOREIGN KEY (parent_id)
    REFERENCES components(id)
    ON DELETE CASCADE;
