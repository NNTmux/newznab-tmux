# SETUP

 * These scripts were written and tested on Ubuntu 12.10 where bash is located at /bin/bash. You may need to create a symlink or edit these script accordingly.

 * Please backup your database first. Something like this should do it.

    `mysqldump --opt -u root -p newznab > ~/newznab_backup.sql`


 * The first step is to decide whether or not you will convert your database to the InnoDB engine. The InnoDB has a lot of benefits, too many to list here, but more ram is required. How much exactly, depends on too many things to list here.

 * If you decide to convert your database, I recommend using [kevin123's github](https://github.com/kevinlekiller/Newznab-Barracuda.git). I recommend only converting the binaries a parts table, using compressed tables. But, there are many choices. I suggest you read his README and follow his recommendations. Or, simply:

    `cd /var/www/newznab/misc/testing/`
 
    `git clone https://github.com/kevinlekiller/Newznab-InnoDB-Dropin.git innodb`

    `cd innodb/lib/innodb`

    `php convertToInnoDB.php`


 * If, you have already converted your database, and didn't to the steps above. You will need to clone [kevin123's github](https://github.com/kevinlekiller/Newznab-InnoDB-Dropin.git) and get the scripts.

    `cd /var/www/newznab/misc/testing/`
    
    `git clone https://github.com/kevinlekiller/Newznab-InnoDB-Dropin.git innodb`
    

 * Clone my github

    `cd /var/www/newznab/misc/update_scripts/nix_scripts/`

    `git clone https://github.com/kevinlekiller/newznab-tmux tmux`
    
    `cd tmux`
    
    `nano edit_these.sh`


 * Edit some permissions, run as root.

    `./set_perms.sh`


 * Run my script, as user.

    `./start.sh`
    

 * If you connect using **putty**, then under Window/Translation set Remote character set to UTF-8.

 * If something looks stalled, it probably isn't. If all 13 panes are still there, it is most likely, as it should be.
 
 * update_cleanup needs to be uncommented to actually do something, and update_parsing is good for fixing a few releases everytime it runs, not a silver bullet though

 * If you are running this on an OVH or kimsufi server, you may need to run sudo ./start.sh because they built grsecurity into the kernel.
    
 * Now all panes should be running smoothly. If any of the scriptns in the top 3 panes crash, the panes should remain open and you should be able to view the error messages. I hope this helps.

 * Join in the converstion at irc://moonlight.se.eu.synirc.net/newznab-tmux.

 * Thanks go to all who offered their assistance and improvement to these scripts.

<hr>
 * If, you find these scripts useful and would like to offer a donation, they are greatly appreciated. Thank you

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=N4AJV5FHZDBFE"><img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="PayPal - The safer, easier way to pay online!" /></a><a href='http://www.pledgie.com/campaigns/18980'><img alt='Click here to lend your support to: Newznab-tmux and make a donation at www.pledgie.com !' src='http://www.pledgie.com/campaigns/18980.png?skin_name=chrome' border='0' /></a>
