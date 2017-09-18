# change xref columns in multigroup and standard collections table

ALTER TABLE collections CHANGE xref xref VARCHAR(2000) NOT NULL DEFAULT '';
ALTER TABLE multigroup_collections CHANGE xref xref VARCHAR(2000) NOT NULL DEFAULT '';