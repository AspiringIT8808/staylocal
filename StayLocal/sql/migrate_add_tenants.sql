USE staylocal_update;

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(140) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_email (email)
) ENGINE=InnoDB;

ALTER TABLE reservations ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id;
ALTER TABLE reservations ADD INDEX idx_reservations_tenant (tenant_id);
ALTER TABLE reservations ADD CONSTRAINT fk_reservations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL ON UPDATE CASCADE;
