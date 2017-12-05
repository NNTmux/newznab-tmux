# Add donation column to user_roles table

ALTER TABLE user_roles ADD donation INT(10) NOT NULL DEFAULT '0';