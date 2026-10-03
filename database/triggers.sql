-- =============================================================================
-- Audit protections: hash chain + append-only enforcement.
--
-- Import with the mysql CLI or phpMyAdmin (both understand DELIMITER).
-- Do NOT add a DEFINER clause: the importing account becomes the definer, which
-- is what cPanel requires.
--
-- The hash expressions below MUST stay identical to the ones in
-- app/Services/HashChain.php (the integrity verifier recomputes them).
-- =============================================================================

DROP TRIGGER IF EXISTS trg_audit_logs_bi;
DROP TRIGGER IF EXISTS trg_audit_logs_bu;
DROP TRIGGER IF EXISTS trg_audit_logs_bd;
DROP TRIGGER IF EXISTS trg_db_change_logs_bi;
DROP TRIGGER IF EXISTS trg_db_change_logs_bu;
DROP TRIGGER IF EXISTS trg_db_change_logs_bd;
DROP TRIGGER IF EXISTS trg_audit_chain_head_bd;

DELIMITER $$

-- audit_logs ------------------------------------------------------------------
CREATE TRIGGER trg_audit_logs_bi BEFORE INSERT ON audit_logs
FOR EACH ROW
BEGIN
    DECLARE v_prev CHAR(64);

    -- Row lock on the chain head serialises concurrent writers so two rows can
    -- never claim the same predecessor.
    SELECT last_hash INTO v_prev FROM audit_chain_head WHERE chain = 'audit_logs' FOR UPDATE;

    -- Server clock only: the application cannot back-date an entry.
    SET NEW.created_at = NOW(6);
    SET NEW.prev_hash  = v_prev;
    SET NEW.row_hash   = SHA2(CONCAT_WS('|',
        v_prev,
        IFNULL(NEW.user_id, ''),
        IFNULL(NEW.user_email, ''),
        NEW.action,
        IFNULL(NEW.entity_type, ''),
        IFNULL(NEW.entity_id, ''),
        NEW.description,
        IFNULL(NEW.metadata, ''),
        IFNULL(NEW.ip_address, ''),
        IFNULL(NEW.user_agent, ''),
        DATE_FORMAT(NEW.created_at, '%Y-%m-%d %H:%i:%s.%f')
    ), 256);

    UPDATE audit_chain_head SET last_hash = NEW.row_hash WHERE chain = 'audit_logs';
END$$

CREATE TRIGGER trg_audit_logs_bu BEFORE UPDATE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: UPDATE is not permitted';
END$$

CREATE TRIGGER trg_audit_logs_bd BEFORE DELETE ON audit_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only: DELETE is not permitted';
END$$

-- db_change_logs --------------------------------------------------------------
CREATE TRIGGER trg_db_change_logs_bi BEFORE INSERT ON db_change_logs
FOR EACH ROW
BEGIN
    DECLARE v_prev CHAR(64);

    SELECT last_hash INTO v_prev FROM audit_chain_head WHERE chain = 'db_change_logs' FOR UPDATE;

    SET NEW.created_at = NOW(6);
    SET NEW.prev_hash  = v_prev;
    SET NEW.row_hash   = SHA2(CONCAT_WS('|',
        v_prev,
        NEW.table_name,
        NEW.record_id,
        NEW.operation,
        IFNULL(NEW.old_values, ''),
        IFNULL(NEW.new_values, ''),
        IFNULL(NEW.user_id, ''),
        IFNULL(NEW.ip_address, ''),
        IFNULL(NEW.user_agent, ''),
        DATE_FORMAT(NEW.created_at, '%Y-%m-%d %H:%i:%s.%f')
    ), 256);

    UPDATE audit_chain_head SET last_hash = NEW.row_hash WHERE chain = 'db_change_logs';
END$$

CREATE TRIGGER trg_db_change_logs_bu BEFORE UPDATE ON db_change_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'db_change_logs is append-only: UPDATE is not permitted';
END$$

CREATE TRIGGER trg_db_change_logs_bd BEFORE DELETE ON db_change_logs
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'db_change_logs is append-only: DELETE is not permitted';
END$$

-- The chain head may only move forward via the insert triggers above.
CREATE TRIGGER trg_audit_chain_head_bd BEFORE DELETE ON audit_chain_head
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_chain_head rows cannot be deleted';
END$$

DELIMITER ;
