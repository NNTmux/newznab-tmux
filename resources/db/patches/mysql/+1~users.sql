# Add remember_token column to users TABLE


ALTER TABLE users ADD remember_token VARCHAR(100) NULL DEFAULT NULL;