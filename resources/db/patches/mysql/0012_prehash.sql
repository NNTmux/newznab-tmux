/* Is this pre nuked? 0 no 1 yes 2 un nuked 3 mod nuked */
ALTER TABLE prehash ADD COLUMN nuked TINYINT(1) NOT NULL DEFAULT '0';

/* If this pre is nuked, what is the reason? */
ALTER TABLE prehash ADD COLUMN nukereason VARCHAR(255) NULL;

/* How many files does this pre have ? */
ALTER TABLE prehash ADD COLUMN files VARCHAR(50) NULL;