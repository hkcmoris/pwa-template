ALTER TABLE configurations
    ADD COLUMN IF NOT EXISTS status ENUM('draft', 'submitted') NOT NULL DEFAULT 'draft' AFTER user_id,
    ADD COLUMN IF NOT EXISTS current_component_id BIGINT UNSIGNED NULL AFTER status;

CREATE TABLE IF NOT EXISTS configuration_selections (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    configuration_id BIGINT NOT NULL,
    component_id BIGINT UNSIGNED NOT NULL,
    definition_id BIGINT UNSIGNED NOT NULL,
    parent_component_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_configuration_selections_configuration
        FOREIGN KEY (configuration_id)
        REFERENCES configurations(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_configuration_selections_component
        FOREIGN KEY (component_id)
        REFERENCES components(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_configuration_selections_definition
        FOREIGN KEY (definition_id)
        REFERENCES definitions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_configuration_selections_parent
        FOREIGN KEY (parent_component_id)
        REFERENCES components(id)
        ON DELETE SET NULL,
    KEY idx_configuration_selections_configuration (configuration_id),
    KEY idx_configuration_selections_component (component_id),
    KEY idx_configuration_selections_definition (definition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
