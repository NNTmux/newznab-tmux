I have forked jonnyboys newznab-tmux as he and the dev team have moved to another project (https://github.com/nZEDb/nZEDb). I will try as much as i can to maintain and improve these tmux scripts, where possible and needed, as they are a valuable addendum to newznab+.
Support is given on irc.synirc.net #tmux channel.

I started adapting some of more interesting scripts from nZEDb, but they require tempering with newznab database, so use them at your own risk. Any update to nn+ db could render them useless. Scripts require PHP version > 5.3.10 and Python 2.7x or newer.

Now, clone my github. These scripts need to run from this location and this is where I was asked to put them. If you have decided to use an alternate location, you will need to edit the file bin/config.php to point to the file newznab/www/config.php. If you do not, these scripts will not run.

  cd /var/www/newznab/misc/update_scripts/nix_scripts/
  git clone https://github.com/DariusIII/newznab-tmux.git tmux
  cd tmux

There is a lib folder in main tmux folder. In that folder you will find two folders, /DB/ and /copy_this/. if you are setting up tmux for the first time, import db.sql using cli (mysql -u {┤your username} -p newznab < db.sql).

1. Copy files from /lib/copy_this/ folder into your newznab installation /www folder. I have created the nntmux theme where all the settings are available.

2. Most of the scripts are now threaded, they need python installed. Install instructions are in threaded_scripts_readme.txt

3. You need to create sample folder in you covers folder (aka. /www/covers/sample) so you can have samples taken from releases, mostly XXX, and can be viewed in releases. For video previews you need video folder /www/covers/video/.

Tmux is started with start.php (php start.php command). Scripts not used anymore are still available for your use, but you need to run them manualy.

