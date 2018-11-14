# Alter binaries and parts tables, add foreign keys

ALTER TABLE binaries ADD CONSTRAINT FK_collections FOREIGN KEY (collections_id) REFERENCES collections(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE parts ADD CONSTRAINT FK_binaries FOREIGN KEY (binaries_id) REFERENCES binaries(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE multigroup_binaries ADD CONSTRAINT FK_multigroup_collections FOREIGN KEY (collections_id) REFERENCES multigroup_collections(id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE multigroup_parts ADD CONSTRAINT FK_multigroup_binaries FOREIGN KEY (binaries_id) REFERENCES multigroup_binaries(id) ON DELETE CASCADE ON UPDATE CASCADE;