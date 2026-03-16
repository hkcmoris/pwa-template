CREATE TABLE IF NOT EXISTS addresses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_code CHAR(2) NOT NULL DEFAULT 'CZ',
    state VARCHAR(120) NOT NULL,
    city VARCHAR(120) NOT NULL,
    street VARCHAR(150) NOT NULL,
    street_number VARCHAR(30) NOT NULL,
    post_code VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_addresses_country_state_city (country_code, state, city),
    KEY idx_addresses_post_code (post_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
