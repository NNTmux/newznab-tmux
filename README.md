I have forked jonnyboys newznab-tmux as he and the dev team have moved to another project (https://github.com/nZEDb/nZEDb). I will try as much as i can to maintain and improve these tmux scripts, where possible and needed, as they are a valuable addendum to newznab+.
Support is given on irc.synirc.net #newznab-tmuxd channel and also on original newznab-tmux channel.

I started adapting some of more interesting scripts from nZEDb, but they require tempering with newznab database, so use them at your own risk. Any update to nn+ db could render them useless. Scripts require PHP version > 5.3.10
There is a test folder in main tmux folder. In that folder you will find three different folders, /db_update/, /initial_setup/ and /predb_dump/. When you are done with initial setup and before you run start.sh for the first time, do the following:

1. Import db.sql from db_update folder into newznab database,

2. Import create_prehash_table.sql from initial_setup folder, be aware that this WILL delete your existing prehash table.

3. OPTIONAL: If you want to populate your prehash table with more than 5 million rows of pre data, run commands from import_predb.txt in mysql. Predb dump can be downloaded from  https://mega.co.nz/#!xggDlBSC!ZDMDPVzbFY_MrzkWqX2EIFgIX8dV0vBkyQ_RF4SAb1A. If you already have prehash table created by aflys init.php, you will need to run commands from clean_duplicates_predb.txt before you run import_predb.txt



Below is the original readme of newznab-tmux, with changes related to my git and removal of donation links:

# Newznab-tmux

 * Screen shots identifying each pane and the process it runs are located at the bottom of this page.
 
 * Before submitting a bug report, please verify that you are running the current revision of Newznab+ and these scripts. Then include as much detail as possible. A screen shot is an extremely telling and very usefull tool.

 * This is a series of scripts that break down the stock processes of a typical Newznab+ installation and perform each task separately and at the same time. Tmux allows for several windows and panes to be created to allow the monitoring of each script as it performs its task. Each task is started and restarted by monitor.php. The post processing has also been enhanced to allow up to 32 simultaneous post processes plus another 8 for each category of releases, for a total of 40 possible post processing at once. The scripts are started by either timers, set in defaults.sh or started automatically and then sleeps for a time set in defaults.sh. Almost everything can be stopped/started from the defaults.sh without restarting the scripts.

 * tmux 1.6 or newer is needed to runs these scripts. These scripts relies on tmux reporting that the "Pane is dead". That is how the script knows that is nothing running in that pane and to restart it for another loop. Seeing "Pane is dead" is normal and expected. If, you are using \*bsd or MacOS, you may need to install [gnu sed(gsed)](http://www.gnu.org/software/software.html) and set the path to it in defaults.sh.

 * I have included a deb package to install tmux 1.8. It is located in the packages folder and it can be installed after cloning my git.

 * To exit the scripts without any worry of causing problems. Click into the Monitor pane, top left and Ctrl-c, or edit defaults.sh and set running="false". This will stop the monitor script. When all of the other panes show dead, then it is ok to run Ctrl-a c and in new window run killall tmux. The only unsafe time to kill these scripts is when optimize is running. Monitor  will display a warning whenever an optimize is in progress.

 * These scripts were written and tested on Ubuntu 12.10 and FreeBSD 9.1. You may need to create a symbolic link or edit these scripts accordingly.

 * Please backup your database first. Something like this should do it.
 
  ```bash
  mysqldump --opt -u username -p newznab > ~/newznab_backup.sql
  ```

 * Now, Clone my github. These scripts need to run from this location and this is where I was asked to put them. If you have decided to use an alternate location, you will need to edit the file bin/config.php to point to the file newznab/www/config.php. If you do not, these scripts will not run.

  ```bash
  cd /var/www/newznab/misc/update_scripts/nix_scripts/
  git clone https://github.com/DariusIII/newznab-tmux.git tmux
  cd tmux
  ```  

 * If, you have just created your Newznab+ database, you can save yourself some time by importing a sql file, written by \_zoggy\_ and updating your TvRage database. **DO NOT** run this if your database has any releases in it.
 
   ```bash
  mysql -u username -p newznab < scripts/tvrage-latest.sql
  ```

 * You can also import [kevin123's binaryblacklist](https://github.com/kevinlekiller/Newznab-Blacklist). This will overwrite all blacklists currently in you db.

   ```bash
  mysql -u username -p newznab < scripts/binaryblacklist.sql
  ```
  
 * Please read the defaults.sh file very carefully. There are a lot of settings and options. Most of the questions asked by new users can be answered by reading this file.

  ```bash
  cp config.sh defaults.sh
  nano defaults.sh
  ```

 * Now, you need to update your Newznab+ installation, copy some files and edit others. This is destructive and will overwrite any changes you have made to you Newznab+ files. If you do not want to update or overwrite you Newznab+ installation, skip this step, but not the next step.

  ```bash
  cd scripts && sudo ./update_svn.sh
  ```

 * Or, if you do not want to update your Newznab+ install, you still need to copy and edit files. Before proceeding you must run this or the previous script.
 
  ```bash
  cd scripts && sudo ./fix_files.sh
  ```

 * These scripts are written and intended that you run them as an unprivileged user.

  ```bash
  cd ../ && ./start.sh
  ```

 * Updating these scripts is very simple.

  ```bash
  git pull
  sudo scripts/update_svn.sh
  or
  sudo scripts/fix_files.sh
  ```

 * If you have grsec compiled into your kernel, you may need root privileges for nmon, bwm-ng and any other app that accesses the /proc folder.

 * Included in the scripts folder is revert.sh. This file will update your Newznab+ installation and overwrite the changes from these scripts.
 
 * It is recommended, with stock scripts, that when backfilling or importing you set header retention to 0. This is not recommended when you are using these scripts. Let me explain why. If you start with empty parts/binaries tables and you import 100 nzbs, and at the same time you start update_releases, when update_releases finishes its loop, it deletes everything in the parts/binaries tables because you have header retention set to 0. But, heres where the problem is, youcontinued to import 200 nzbs while update_releases was running, they did not get imported as a release, they were deleted at the end of update_releases loop. So, I recommend you set header retention to at least .1, that is 2.4 hours, when only backfilling or importing.

 * Almost any variable in defaults.sh can be changed, except the paths to the commands, and the changes will take effect on the next loop of the Monitor.

 * If you connect using **putty**, then under Window/Translation set Remote character set to UTF-8 and check "Copy and paste line drawing characters". To use 256 colors, you must set Connection/Data Terminal-type string to "xterm-256color" and in Window/Colours check the top three boxes, otherwise only 16 colors are displayed. If you are using FreeBSD, you will need to add export TERM=xterm-256color to your .bashrc file to show 256 colors.
 
 * The powerline status bar has many options that are not enabled by default. To enable any of the additional options, simply uncomment the option in the file powerline/powerline/themes/tmux.sh. If you are using the powerline status bar, you will most likely need a patched font. The Consolas ttf from [powerline-fonts](https://github.com/jonnyboy/powerline-fonts) is the only one that I have found to be nearly complete and work with putty and Win7. The otf fonts should be fine, although I am not able to test. For linux users it may not be necessary to install the patched font. You can follow the instructions of [Fontonfig](https://powerline.readthedocs.org/en/latest/installation/linux.html#font-installation) and that could possibly be an easier path. If you still have problems, you can go the source and read [tmux-powerline](https://github.com/erikw/tmux-powerline) for additional help. For OSX users, it is recommended that you use [iterm2](http://www.iterm2.com/#/section/home).

 * I have included a few scripts for mysql, [mysql-tuning-primer](https://launchpad.net/mysql-tuning-primer), [mysqlreport](http://hackmysql.com/mysqlreport) and [mysqltuner.pl](https://github.com/sunfoxcz/MySQLTuner-perl/blob/master/mysqltuner.pl) to assist in tuning your mysql installation. They are located in the scripts folder.

 * I have include 2 scripts to create and remove a ramdisk and put the parts, partrepair and binaries onto it. This should be considered use at your own risk. There are risks involved with running these tables on a ramdisk, but if you should lose the tables, it is not a total loss.

 * A how-to from [nevermind](http://pastebin.com/ibpi71iE) will help your db run a little faster/easier. It can be damaging if you make a mistake while doing this.

 * A couple of autoinstallers are available to completely install everything needed for Ubuntu 12.10 Newznab+ installation. [zombu2](https://github.com/zombu2/nninstall) and [convict](https://github.com/rcconvict/nninstaller) each have simlar installers and can be found in the gits. If you prefor to do things yourself there is an excellent guide [here](http://oizopower.tweakblogs.net/blog/8524/newznab%2B-installatie-%28ubuntu-nginx-percona%29.html).
  
 * Join in the conversation at irc://irc.synirc.net/newznab-tmux.



 * Thanks go to all who have offered their assistance and improvement to these scripts, especially kevin123, zombu2, epsol, DejaVu, ajeffco, pcmerc, zDefect, shat, evermind, coolcheat, sy, ll, crunch, ixio, AlienX, Solution-X, cryogenx, convict, wicked, McFuzz, pyr2044, Kragger and \_zoggy\_. If your nick is missing from this this list, PM and I'll fix it quick.
 
 * These scripts include scripts written by [kevin123's](https://github.com/kevinlekiller), [itandrew's](https://github.com/itandrew/Newznab-InnoDB-Dropin), [tmux-powerline](https://github.com/erikw/tmux-powerline), [thewtex](https://github.com/thewtex/tmux-mem-cpu-load), [cj](https://github.com/NNScripts/nn-custom-scripts) and [\_zoggy\_](http://zoggy.net/tvrage-latest.sql).

<hr>
If you want to donate please click on donate button

<a style="float:right;" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7MQLC6ZLSQMKE"><img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="PayPal - The safer, easier way to pay online!" /></a>
<a href='http://www.pledgie.com/campaigns/21167'><img alt='Click here to lend your support to: Newznab-tmux continued and make a donation at www.pledgie.com !' src='http://www.pledgie.com/campaigns/21167.png?skin_name=chrome' border='0' /></a>





<hr>
![Newznab-tmux](https://raw.github.com/jonnyboy/newznab-tmux/master/image/newznab-tmux-1.png)
![Newznab-tmux](https://raw.github.com/jonnyboy/newznab-tmux/master/image/newznab-tmux-2.png)
![Newznab-tmux](https://raw.github.com/jonnyboy/newznab-tmux/master/image/newznab-tmux-3.png)
![Newznab-tmux](https://raw.github.com/jonnyboy/newznab-tmux/master/image/newznab-tmux-4.png)
![Newznab-tmux](https://raw.github.com/jonnyboy/newznab-tmux/master/image/newznab-tmux-5.png)
![Newznab-tmux](https://raw.github.com/jonnyboy/newznab-tmux/master/image/newznab-tmux-6.png)

