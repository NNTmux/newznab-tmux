# Change director column in xxxinfo table
ALTER TABLE xxxinfo CHANGE director director VARCHAR(255) DEFAULT NULL;

# Change genre column in xxxinfo table
ALTER TABLE xxxinfo CHANGE genre genre VARCHAR(255) NOT NULL;

# Change title column in xxxinfo table
ALTER TABLE xxxinfo CHANGE title title VARCHAR(1024) NOT NULL;