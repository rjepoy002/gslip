-- Run using a MySQL/MariaDB client that supports DELIMITER.
USE gsccxzp_pal_db_test;
-- Inspected locally: users.id is signed INT(11), users engine is InnoDB.
-- No FK: retain compatibility with installations whose users engine differs.
CREATE TABLE IF NOT EXISTS remember_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    verifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    INDEX (user_id),
    INDEX (expires_at)
) ENGINE=InnoDB;

-- Minimal invalidation mechanism: no extra users column or data rewrite.
-- Revoke on security changes even through legacy admin pages or direct SQL.
-- Re-running replaces only this application's trigger, never user records.
DELIMITER $$
DROP TRIGGER IF EXISTS gslip_revoke_remember_security$$
CREATE TRIGGER gslip_revoke_remember_security BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF NOT (OLD.password <=> NEW.password)
       OR NOT (OLD.status <=> NEW.status)
       OR NOT (OLD.username <=> NEW.username)
       OR NOT (OLD.role <=> NEW.role)
       OR NOT (OLD.department_id <=> NEW.department_id)
       OR NOT (OLD.area_id <=> NEW.area_id) THEN
        UPDATE remember_tokens SET revoked_at=UTC_TIMESTAMP()
        WHERE user_id=OLD.id AND revoked_at IS NULL;
        SET NEW.session_token=NULL;
    END IF;
END$$
DELIMITER ;
