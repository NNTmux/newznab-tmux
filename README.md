# SETUP

 * tmux 1.6 or newer is needed to runs these scripts. This script relies on tmux reporting that the "Pane is dead". That is how the script knows that is nothing running in that pane and to restart it for another loop. Seeing "Pane is dead" is normal and expected.

 * These scripts were written and tested on Ubuntu 12.10 where bash is located at /bin/bash. You may need to create a symlink or edit these scripts accordingly.

 * Please backup your database first. Something like this should do it.
 
  ```bash
  mysqldump --opt -u root -p newznab > ~/newznab_backup.sql
  ```


 * The first step is to decide whether or not you will convert your database to the InnoDB engine. The InnoDB has a lot of benefits, too many to list here, but more ram is required. How much exactly, depends on too many things to list here.

 * If you decide to convert your database, I recommend using [kevin123's github](https://github.com/kevinlekiller/Newznab-Barracuda.git). I recommend only converting the binaries a parts table, using compressed tables. But, there are many choices. I suggest you read his README and follow his recommendations. Or, simply:

  ```bash
  cd /var/www/newznab/misc/testing
  git clone https://github.com/kevinlekiller/Newznab-InnoDB-Dropin.git kev-innodb
  cd kev-innodb/lib/innodb
  php convertToInnoDB.php
  ```

 * Now, you need to decide which branch you will use, the master branch uses scripts written by andrewit and the dev branch uses scripts written by kevin123. If, you choose the dev branch, skip the next step.

 * If, you stay on the master branch, you will need to clone [andrewit's github](https://github.com/itandrew/Newznab-InnoDB-Dropin.git) and get his scripts.

  ```bash
  cd /var/www/newznab/misc/testing/
  git clone https://github.com/itandrew/Newznab-InnoDB-Dropin.git innodb
  ```  

 * Now, Clone my github. Theses scripts should be able to run from any path, but this location is where I was asked to put it.

  ```bash
  cd /var/www/newznab/misc/update_scripts/nix_scripts/
  git clone https://github.com/jonnyboy/newznab-tmux.git tmux
  cd tmux
  ```  

 * If, you choose the the dev branch, run:

  ```bash
  git checkout dev
  ```

 * Edit the paths, timers, username, what to run and then accept.

  ```bash
  nano edit_these.sh
  ```

 * Edit some permissions and a file, run this as root. If, when you run ./start.sh you see 0 nzb's and you are sure there are more than 0 left to import, verify the path to the nzb's in .tmux_user.conf in the conf folder and is created at first run.

  ```bash
  ./set_perms.sh
  ```

 * Run my script, as user. If, you have grsecurity in you kernel, you will need to run using sudo if you use nmon or bwm-ng.

  ```bash
  ./start.sh
  ```

 * If you connect using **putty**, then under Window/Translation set Remote character set to UTF-8.

 * If something looks stalled, it probably isn't. If all 13 panes are still there, it is most likely, as it should be.
 
 * You must edit **misc/testing/update_cleanup.php** in order for it to actually do something, and update_parsing is good for fixing a few releases everytime it runs, not a silver bullet though.

 * Now all panes should be running smoothly. If any pane crashes, it should remain open and return to the prompt. You should also be able to see the error that caused it to crash.

 * Join in the converstion at irc://moonlight.se.eu.synirc.net/newznab-tmux.




 * Thanks go to all who offered their assistance and improvement to these scripts, especially kevin123, zombu2, epsol, DejaVu, ajeffco, pcmerc, zDefect, shat, evermind, coolcheat, sy, ll, crunch, ixio, AlienX, Solution-X, cryogenx. If, your nick is missing from this this, PM and I'll fix it quick.

<hr>
 * If, you find these scripts useful and would like to offer a donation, they are greatly appreciated. Thank you

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=N4AJV5FHZDBFE"><img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="PayPal - The safer, easier way to pay online!" /></a><a href='http://www.pledgie.com/campaigns/18980'><img alt='Click here to lend your support to: Newznab-tmux and make a donation at www.pledgie.com !' src='http://www.pledgie.com/campaigns/18980.png?skin_name=chrome' border='0' /></a>
