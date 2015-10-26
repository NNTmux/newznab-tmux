# This patch will make the necessary changes to the user_series
# table to support the new videos implementation
# At this time, we are not sure if we can preserve the users shows
# data as the tvrage data is largely useless due to the title insert bug

# Truncate for now
TRUNCATE TABLE userseries;

# Change rageid column to new videos_id
ALTER TABLE userseries
  DROP INDEX ix_userseries_userid,
  CHANGE COLUMN rageid videos_id INT(16) NOT NULL COMMENT 'FK to videos.id',
  ADD INDEX ix_userseries_videos_id (userid, videos_id);
