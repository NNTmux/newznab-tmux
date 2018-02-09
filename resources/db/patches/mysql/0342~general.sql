# Drop release_search_data table

DROP TABLE release_search_data;

# Remove bad indexes from bookinfo, consoleinfo, gamesinfo, musicinfo, predb tables

ALTER TABLE bookinfo DROP INDEX ix_bookinfo_author_title_ft;
ALTER TABLE consoleinfo DROP INDEX ix_consoleinfo_title_platform_ft;
ALTER TABLE gamesinfo DROP INDEX ix_title_ft;
ALTER TABLE musicinfo DROP INDEX ix_musicinfo_artist_title_ft;
ALTER TABLE predb DROP INDEX ft_predb_filename;
ALTER TABLE steam_apps DROP INDEX ix_name_ft;

# ADD proper fulltext indexes to tables

ALTER TABLE bookinfo ADD FULLTEXT ix_bookinfo_author_title_ft (author, title);
ALTER TABLE consoleinfo ADD FULLTEXT ix_consoleinfo_title_platform_ft (title, platform);
ALTER TABLE gamesinfo ADD FULLTEXT ix_title_ft (title);
ALTER TABLE musicinfo ADD FULLTEXT ix_musicinfo_artist_title_ft (artist, title);
ALTER TABLE predb ADD FULLTEXT ft_predb_filename (filename);
ALTER TABLE steam_apps ADD FULLTEXT ix_name_ft (name);
