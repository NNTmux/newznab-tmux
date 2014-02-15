ALTER TABLE  `releases`
    DROP INDEX `ix_releases_status`,
    ADD INDEX `ix_releases_bitwise` (`bitwise`),
    ADD INDEX `ix_releases_passwordstatus` (`passwordstatus`),
    ADD INDEX `ix_releases_dehashstatus` (`dehashstatus`),
    ADD INDEX `ix_releases_haspreview` (`haspreview` ASC) USING HASH,
    ADD INDEX `ix_releases_postdate_name` (`postdate`, `name`),
    ADD INDEX `ix_releases_status` (`ID`, `nfostatus`, `bitwise`, `passwordstatus`, `dehashstatus`, `reqidstatus`, `musicinfoID`, `consoleinfoID`, `bookinfoID`, `haspreview`, `categoryID`, `imdbID`, `rageID`, `groupID`);

