# Add email_verified_at column to users table

ALTER TABLE users ADD email_verified_at TIMESTAMP NULL DEFAULT NULL;
