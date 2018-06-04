# Add rate_limit column to users and user_roles tables, for api rate limiting purposes

ALTER TABLE users ADD rate_limit INT DEFAULT 60 COMMENT 'Rate limiting, requests per minute';

ALTER TABLE user_roles ADD rate_limit INT DEFAULT 60 COMMENT 'Rate limiting, requests per minute';
