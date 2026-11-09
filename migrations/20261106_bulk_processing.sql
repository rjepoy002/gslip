-- Repeatable, additive migration. Run against the selected application database.
-- Existing users.id and gas_slips.id must be signed INT and tables InnoDB.
CREATE TABLE IF NOT EXISTS bulk_processing_groups (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 owner_user_id INT NOT NULL,
 group_name VARCHAR(100) NOT NULL,
 normalized_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY owner_group_name (owner_user_id, normalized_name),
 UNIQUE KEY group_owner (id, owner_user_id),
 FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bulk_processing_group_names (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 group_id INT NOT NULL,
 owner_user_id INT NOT NULL,
 requested_by_name VARCHAR(100) NOT NULL,
 normalized_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY owner_requested_name (owner_user_id, normalized_name),
 FOREIGN KEY (group_id, owner_user_id) REFERENCES bulk_processing_groups(id, owner_user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bulk_processing_exclusions (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 user_id INT NOT NULL,
 gas_slip_id INT NOT NULL,
 reason VARCHAR(255) DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY user_slip (user_id, gas_slip_id),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (gas_slip_id) REFERENCES gas_slips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
