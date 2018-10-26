# Alter the columns in release_regexes table

ALTER TABLE release_regexes MODIFY COLUMN collection_regex_id INT(11) NOT NULL DEFAULT '0';
ALTEr TABLE release_regexes MODIFY COLUMN naming_regex_id INT(11) NOT NULL DEFAULT '0';
