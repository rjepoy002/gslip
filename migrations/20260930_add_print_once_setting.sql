CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
);

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('print_once', '0')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
