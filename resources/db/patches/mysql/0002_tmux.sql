DELETE FROM tmux WHERE setting = 'collections_kill';
DELETE FROM tmux WHERE setting = 'sorter';
DELETE FROM tmux WHERE setting = 'sorter_timer';
DELETE FROM tmux WHERE setting = 'optimize';
DELETE FROM tmux WHERE setting = 'optimize_timer';
INSERT IGNORE INTO tmux (setting, value) VALUES ('sphinx', '0'),
    ('sphinx_timer', '600'),
    ('delete_parts', '0'),
    ('delete_timer', '43200');
