# Add rottentomatoes rating score to movieinfo table

ALTER TABLE movieinfo ADD COLUMN rtrating VARCHAR(10) NOT NULL DEFAULT '' COMMENT 'RottenTomatoes rating score';