INSERT IGNORE INTO tmux (setting, value) VALUES
  ('dehash', '0'),
  ('dehash_timer', '30');
UPDATE `site` set `value` = '98' where `setting` = 'sqlpatch';