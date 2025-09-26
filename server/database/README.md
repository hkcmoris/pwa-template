# Database artefacts

This directory collects SQL migrations and helper snippets that can be executed manually or piped into the `install.php` bootstrapper when initializing a new environment.

## Definitions domain

- `migrations/20250919_create_definitions.sql` provisions the hierarchical `definitions` table, the `definition_components` bridge for UI islands, and the `definition_tree` recursive view for reads.
- `seeds/definitions.sample.sql` inserts the sample "Nástavba" hierarchy from the product brief.
- `queries/select_definition_tree.sql` shows how to pull a full tree (ordered) for a given root.

## Components domain

- `migrations/20250920_create_components.sql` creates the `components` hierarchy linked to definitions. Each component references a definition, may nest under another component, and stores descriptive metadata plus a JSON dependency tree.

**Suggested workflow**

1. Run `php server/install.php` after updating database credentials in `.env`. This creates the base schema including the new definitions structures.
2. (Optional) Load seed data: `mysql -u <user> -p <db> < server/database/seeds/definitions.sample.sql`.
3. Query trees or wire the SPA editor against the `definition_tree` view.

The schema keeps siblings ordered via the `position` column and cascades deletes so removing a definition prunes its subtree.

