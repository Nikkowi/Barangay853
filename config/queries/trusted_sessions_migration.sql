-- ============================================================
-- trusted_sessions table
-- Stores per-device trusted session tokens for 2FA bypass.
-- One row per browser/device per user.
-- ============================================================

CREATE TABLE IF NOT EXISTS trusted_sessions (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    token         VARCHAR(128)    NOT NULL,           -- 64-char hex stored raw (no hashing needed, long enough)
    device_label  VARCHAR(255)    DEFAULT NULL,       -- e.g. "Chrome on Windows"
    ip_address    VARCHAR(45)     DEFAULT NULL,       -- IPv4 or IPv6
    user_agent    TEXT            DEFAULT NULL,
    duration_days TINYINT UNSIGNED NOT NULL DEFAULT 30, -- 30 / 60 / 90
    expires_at    DATETIME        NOT NULL,
    last_used_at  DATETIME        DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked       TINYINT(1)      NOT NULL DEFAULT 0,

    PRIMARY KEY (id),
    UNIQUE  KEY uk_token       (token),
    INDEX   idx_user_id        (user_id),
    INDEX   idx_expires        (expires_at),
    INDEX   idx_user_token     (user_id, token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
