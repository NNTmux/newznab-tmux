UPDATE releases SET preID = NULL, searchname = name, bitwise = ((bitwise & ~5)|0) WHERE LENGTH(searchname) <= 15 AND preID IS NOT NULL;
DELETE FROM prehash WHERE LENGTH(title) <= 15;

