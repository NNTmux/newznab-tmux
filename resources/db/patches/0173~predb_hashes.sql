-- This can be a long patch, you can check in mysql (mysql -p yourDatabaseName) what is going on
-- with this command: show processlist;

-- Truncating predbhash table.
TRUNCATE TABLE predbhash;

-- Dropping primary key from predbhash table.
ALTER TABLE predbhash DROP PRIMARY KEY;
-- Dropping the hashes column from the predbhash table.
ALTER TABLE predbhash DROP COLUMN hashes;
-- Adding the hash column to the predbhash table.
ALTER TABLE predbhash ADD hash VARBINARY(20) FIRST;
-- Adding a primary key to the hash column.
ALTER TABLE predbhash ADD PRIMARY KEY(hash);

-- Creating hashes from prehash titles, this can be long.
-- Stage 1: UNHEX(md5(title))
INSERT IGNORE INTO predbhash SELECT UNHEX(md5(title)), id from prehash;
-- Stage 2: UNHEX(md5(md5(title)))
INSERT IGNORE INTO predbhash SELECT UNHEX(md5(md5(title))), id from prehash;
-- Stage 3: UNHEX(sha1(title))
INSERT IGNORE INTO predbhash SELECT UNHEX(sha1(title)), id from prehash;
