UPDATE prehash SET predate = CONCAT('2014', substr(predate, 5)) WHERE predate LIKE '%2015-12%';
UPDATE prehash SET predate = CONCAT('2015', substr(predate, 5)) WHERE predate LIKE '%2016-01%';
