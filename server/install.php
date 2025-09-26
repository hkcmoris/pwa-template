<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/config-root.php';

try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
    $pdo = new PDO($dsn, DB_A_USER, DB_A_PASS, $options);

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . DB_NAME . '`');

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT "user",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Best-effort migration for existing installs lacking the role column
    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT "user"');
    } catch (PDOException $e) {
        // Ignore if column already exists
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS refresh_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        revoked TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        revoked_at DATETIME NULL,
        INDEX (user_id),
        CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $definitionsTableSql = <<<'SQL'
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
SQL;
    $pdo->exec($definitionsTableSql);

    $definitionComponentsSql = <<<'SQL'
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
SQL;
    $pdo->exec($definitionComponentsSql);

    $componentsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS components (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  definition_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED DEFAULT NULL,
  alternate_title VARCHAR(191) DEFAULT NULL,
  description TEXT NOT NULL,
  image VARCHAR(191) DEFAULT NULL,
  dependency_tree JSON NOT NULL,
  position INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_components_definition FOREIGN KEY (definition_id) REFERENCES definitions(id) ON DELETE CASCADE,
  CONSTRAINT fk_components_parent FOREIGN KEY (parent_id) REFERENCES components(id) ON DELETE SET NULL,
  UNIQUE KEY uq_components_parent_position (parent_id, position),
  KEY idx_components_definition (definition_id),
  KEY idx_components_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $pdo->exec($componentsSql);

    $definitionTreeViewSql = <<<'SQL'
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
SQL;
    $pdo->exec($definitionTreeViewSql);

    echo "Database setup complete\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

