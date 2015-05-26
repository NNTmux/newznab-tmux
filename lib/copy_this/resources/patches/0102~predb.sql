ALTER TABLE `predb` drop `MD5`;
UPDATE tmux set value = '102' where setting = 'sqlpatch';