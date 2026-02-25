-- Ensure legacy installs have username column for auth APIs.
SET @alter_username := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE users ADD COLUMN username VARCHAR(255) NOT NULL DEFAULT '''' AFTER id',
    'SET @skip_username_add := 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'username'
);

PREPARE stmt FROM @alter_username;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users
SET username = email
WHERE username IS NULL OR username = '';

ALTER TABLE users
    MODIFY COLUMN username VARCHAR(255) NOT NULL;
