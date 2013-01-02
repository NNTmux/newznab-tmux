# SETUP

 * These scripts were written and tested on Ubuntu 12.10 where bash is located at /bin/bash. You may need to create a symlink or edit these script accordingly.

 * Please backup your database first. Something like this should do it.

    `mysqldump --opt -u root -p newznab > ~/newznab_backup.sql`

 * This first step will modify your setup using [andrewit's github](https://github.com/itandrew/Newznab-InnoDB-Dropin.git), if you want. If you do not convert, then use then use myisam when editing.
 * convert database to innodb, edit path if necessary

    `cd /var/www/newznab/misc/testing/`
    
    `git clone git://github.com/itandrew/Newznab-InnoDB-Dropin.git innodb`
    
    `php5 innodb/lib/innodb/convertToInnoDB.php`

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

 * If something looks stalled, it probably isn't. If all 7 panes are still there, it is most likely, as it should be.
 
 * update_cleanup needs to be uncommented to actually do something, and update_parsing is good for fixing a few releases everytime it runs, not a silver bullet though

 * You have two choices for monitoring activity. Mytop monitors the sctivity of you mysql database and bwm-ng monitors your current traffic. Theses are selected in the edit_these.sh file. Only one can be selected at a time.



 * If you are running this on an OVH or kimsufi server, you may need to run sudo ./start.sh because they built grsecurity into the kernel.
    
 * Now all panes should be running smoothly. If any of the scriptns in the top 3 panes crash, the panes should remain open and you should be able to view the error messages. I hope this helps.



<hr>
 * Donations help to pay my hosting fees and server costs. Thank you

<center>![My donate button](https://github.com/ttscoff/JekyllPlugins/blob/master/Donation/DonateButton.gif?raw=true)</center>
{% donate N4AJV5FHZDBFE "Buy me some coffee, ok?" %}
<center><a href='http://www.pledgie.com/campaigns/18980'><img alt='Click here to lend your support to: Newznab-tmux and make a donation at www.pledgie.com !' src='http://www.pledgie.com/campaigns/18980.png?skin_name=chrome' border='0' /></a></center>

