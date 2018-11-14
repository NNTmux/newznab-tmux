# Change category id for misc categories

UPDATE categories SET id = 1 WHERE id = 0 OR id = 0000;
UPDATE categories SET parentid = 1 WHERE parentid = 0 OR parentid = 0000;
UPDATE categories SET id = 10 WHERE id = 0010;
UPDATE categories SET id = 20 WHERE id = 0020;