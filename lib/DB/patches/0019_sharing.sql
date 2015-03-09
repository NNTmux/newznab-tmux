UPDATE releasecomment SET username = (SELECT username FROM users WHERE users.id = releasecomment.userID);

DELETE FROM users WHERE email = 'sharing@nZEDb.com' AND role = 0;

UPDATE tmux SET value = '19' WHERE setting = 'sqlpatch';
