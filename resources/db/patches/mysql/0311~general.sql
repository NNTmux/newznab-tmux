# Increase the varchar for xref in collections to 1024
ALTER TABLE collections CHANGE xref xref VARCHAR(1024) NOT NULL DEFAULT '';

# Increase the varchar for xref in multigroup_collections to 1024
ALTER TABLE multigroup_collections CHANGE xref xref VARCHAR(1024) NOT NULL DEFAULT '';

# Change classussed column in xxxinfo table
ALTER TABLE xxxinfo CHANGE classused classused VARCHAR(20) NOT NULL DEFAULT '';