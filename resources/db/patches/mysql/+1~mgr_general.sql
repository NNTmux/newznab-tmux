# Create mgr tables
DROP TABLE IF EXISTS mgr_collections;
CREATE TABLE mgr_collections LIKE collections;

DROP TABLE IF EXISTS mgr_binaries;
CREATE TABLE mgr_binaries LIKE binaries;

DROP TABLE IF EXISTS mgr_parts;
CREATE TABLE mgr_parts LIKE parts;

DROP TABLE IF EXISTS mgr_missed_parts;
CREATE TABLE mgr_missed_parts LIKE missed_parts;
