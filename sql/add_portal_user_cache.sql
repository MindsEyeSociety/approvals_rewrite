-- Local cache of MES Portal user data to eliminate the cross-schema JOIN
-- in UserService on every page load. Populated/refreshed on each login
-- via oauth_callback.php. Data may be up to one login-cycle stale, which
-- is acceptable for an admin-facing list; actual access is still gated by
-- portal OAuth.
CREATE TABLE IF NOT EXISTS portal_user_cache (
    ww_number             VARCHAR(20)  NOT NULL,
    first_name            VARCHAR(100) DEFAULT NULL,
    last_name             VARCHAR(100) DEFAULT NULL,
    email                 VARCHAR(255) DEFAULT NULL,
    membership_expiration DATETIME     DEFAULT NULL,
    cached_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ww_number),
    INDEX idx_last_name  (last_name),
    INDEX idx_first_name (first_name),
    INDEX idx_expiry     (membership_expiration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
