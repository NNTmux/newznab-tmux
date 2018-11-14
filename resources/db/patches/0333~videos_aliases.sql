# Add foreign key to videos_aliases table that references videos table

ALTER TABLE videos_aliases ADD CONSTRAINT FK_videos_id FOREIGN KEY (videos_id) REFERENCES videos(id) ON DELETE CASCADE ON UPDATE CASCADE;