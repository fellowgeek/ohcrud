-- Optional production provisioning; Users creates this table automatically in debug mode.
-- Compatible with SQLite and MySQL.
CREATE TABLE IF NOT EXISTS AuthState (
    ID VARCHAR(128) NOT NULL PRIMARY KEY,
    ATTEMPTS INTEGER NOT NULL DEFAULT 0,
    WINDOW_START BIGINT NOT NULL DEFAULT 0,
    LAST_STEP BIGINT NOT NULL DEFAULT -1
);
