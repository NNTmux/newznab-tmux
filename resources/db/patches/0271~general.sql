# Change bookinfo.genreid to bookinfo.genres_id to follow lithium convention.
ALTER TABLE bookinfo CHANGE COLUMN genreid genres_id INT(10) UNSIGNED NOT NULL COMMENT 'FK to genres.id';

# Change musicinfo.genreid to musicinfo.genres_id to follow lithium convention.
ALTER TABLE musicinfo CHANGE COLUMN genreid genres_id INT(10) UNSIGNED NOT NULL COMMENT 'FK to genres.id';

# Change consoleinfo.genreid to consoleinfo.genres_id to follow lithium convention.
ALTER TABLE consoleinfo CHANGE COLUMN genreid genres_id INT(10) UNSIGNED NOT NULL COMMENT 'FK to genres.id';

# Change gamesinfo.genre_id to gamesinfo.genres_id to follow lithium convention.
ALTER TABLE gamesinfo CHANGE COLUMN genre_id genres_id INT(10) UNSIGNED NOT NULL COMMENT 'FK to genres.id';
