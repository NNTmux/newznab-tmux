#We are dropping the existing preid column
ALTER TABLE releases DROP preid;

#Now we rename the existing prehashid column to preid
ALTER TABLE releases CHANGE COLUMN prehashid preid INT(10) UNSIGNED NOT NULL DEFAULT '0';
                       \q
