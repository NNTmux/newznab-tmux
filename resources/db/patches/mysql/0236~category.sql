#Update category table with new values for Other Misc and Hashed
UPDATE category SET id = 0000 WHERE id = 8000;
UPDATE category SET id = 0010, parentid= 0000 WHERE id = 8010;
UPDATE category SET id = 0020, parentid= 0000 WHERE id = 8020;
