CREATE INDEX ix_releases_bitwise on releases(bitwise);
CREATE INDEX ix_releases_passwordstatus on releases(passwordstatus);
CREATE INDEX ix_releases_dehashstatus ON releases(dehashstatus);
CREATE INDEX ix_releases_haspreview ON releases (haspreview ASC) USING HASH;
CREATE INDEX ix_releases_postdate_name ON releases (postdate, name);
DROP INDEX `ix_releases_status` on releases;
CREATE INDEX ix_releases_status ON releases (ID, nfostatus, bitwise, passwordstatus, dehashstatus, reqidstatus, musicinfoID, consoleinfoID, bookinfoID, haspreview, categoryID, imdbID, rageID, groupID);

