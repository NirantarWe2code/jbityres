-- Add TOTP/2FA columns to users table for Authenticator app support
-- Run in phpMyAdmin or: mysql -u root -p sales < database/users_totp.sql
-- (Ignore "Duplicate column" errors if already applied)

ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) DEFAULT 0;
