# Add verified and verification_token columns to users table

ALTER TABLE users ADD verified BOOLEAN DEFAULT FALSE COMMENT 'Has user verified his account?';
ALTER TABLE users ADD verification_token VARCHAR(255) DEFAULT NULL COMMENT 'User verification token';

UPDATE users set verified = 1 WHERE verified = 0;
