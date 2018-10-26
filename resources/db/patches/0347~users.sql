# Rename rsstoken column to api_token

ALTER TABLE users CHANGE rsstoken api_token VARCHAR(64);