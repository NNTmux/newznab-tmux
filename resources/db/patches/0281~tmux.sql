#Remove settings from tmux table

DELETE FROM tmux WHERE setting = 'lastpretime';

DELETE FROM tmux WHERE setting = 'currentppticket';

DELETE FROM tmux WHERE setting = 'nextppticket';

DELETE FROM tmux WHERE setting = 'debuginfo';
