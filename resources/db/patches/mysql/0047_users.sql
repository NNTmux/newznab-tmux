ALTER TABLE users ADD COLUMN queuetype TINYINT(1) NOT NULL DEFAULT 1;
/* Add a column to pick between Sab and NZBGet. */