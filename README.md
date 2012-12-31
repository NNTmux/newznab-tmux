# SETUP

 * Please backup your database first. Something like this should do it.

    `mysqldump --opt -u root -p newznab > ~/newznab_backup.sql`

 * This first step will modify your setup using [andrewit's github](https://github.com/itandrew/Newznab-InnoDB-Dropin.git)
 * convert database to innodb, edit path if necessary

    `cd /var/www/newznab/misc/testing/`
    
    `git clone git://github.com/itandrew/Newznab-InnoDB-Dropin.git innodb`
    
    `php5 innodb/lib/innodb/convertToInnoDB.php`

 * Create a folder.
 * Move to that folder.
 * Clone my github

    `cd /var/www/newznab/misc/update_scripts/nix_scripts/`

    `git clone https://github.com/jonnyboy/newznab-tmux.git tmux`
    
    `cd tmux`
    
    `nano edit_these.sh`

 * Edit some permissions, run as root.

    `./set_perms.sh`

 * Run my script, as user.

    `./start.sh`
    
 * If you connect using putty, then under Window/Translation set Remote character set to UTF-8.

 * If something looks stalled, it probably isn't. If all 5 panes are still there, it is most likely, as it should be.

