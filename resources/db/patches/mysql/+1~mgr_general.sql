# Create mgr tables
DROP TABLE IF EXISTS mgr_collections;
CREATE TABLE mgr_collections LIKE collections;

DROP TABLE IF EXISTS mgr_binaries;
CREATE TABLE mgr_binaries LIKE binaries;
