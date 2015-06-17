I have forked jonnyboys newznab-tmux as he and the dev team have moved to another project (https://github.com/nZEDb/nZEDb). I will try as much as i can to maintain and improve these tmux scripts, where possible and needed, as they are a valuable addendum to newznab+.
Support is given on irc.synirc.net #tmux channel.

I started adapting some of more interesting scripts from nZEDb, but they require tempering with newznab database, so use them at your own risk. By using newznab-tmux you will render your indexer incompatible with newznab (you can revert changes by forcing the svn checkout, it will replace the changed files. Newznab is not aware of the changes in database, so you are safe to leave them as they are). Scripts require PHP version >= 5.5.x and Python 2.7x or newer.

# Steps to have a working tmux install:

 CD to the root of your newznab install, ie.  cd /var/www/newznab
 Next steps are very important:
 
 		If you are already a tmux user, you need to remove the .git folder from tmux folder. 
 
 		git init 
 		git remote add origin https://github.com/DariusIII/newznab-tmux.git
 		git fetch
 		git reset --hard origin/your_wanted_branch, ie. git reset --hard origin/master
 		git checkout -t origin/your_wanted_branch, ie. git checkout -t origin/master

	There is a lib folder in main tmux folder. In that folder you will find DB folder. If you are setting up tmux for the first time, import db.sql using cli (mysql -u {┤your username} -p newznab < db.sql).
	After importing db.sql you need to run the patchDB.php from same folder. After the last patch is applied, you need to manualy apply the next patch labeled XXXX~settings.sql (XXXX is a patch number that is different on dev/master(0122) and regexless branch(0149)).	
	When you have manualy applied that patch, you can use the new database updater located in /path/to/newznab/cli/ and run update_db.php true and it will apply the rest of the patches (if they exist).
	After these steps, patchDB is unusable anymore and you need to use the new update_db.php script in cli folder.

	You need to chmod to 777 following folders now:
	resources/*
	libs/smarty/templates_c
	nzbfiles/ 

	You need to add an alias to your apache/nginx conf of your indexer:
	Alias /covers /path/to/newznab/resources/covers

# yEnc:

  Note: You have 3 choices,
        you can install simple_php_yenc_decode which offers the best performance,
        installing yydecode, which offers good performance,
        or using PHP (no install required) which is very slow.
        You can change these at any time if you have issues with any of the 3.

  simple_php_yenc_decode:

       sudo apt-get install git
       cd ~/
       git clone https://github.com/kevinlekiller/simple_php_yenc_decode
       cd simple_php_yenc_decode/
       sh ubuntu.sh
       cd ~/
       rm -rf simple_php_yenc_decode/

  yydecode:

       cd ~/
       mkdir -p yydecode
       cd yydecode/
       wget http://colocrossing.dl.sourceforge.net/project/yydecode/yydecode/0.2.10/yydecode-0.2.10.tar.gz
       tar -xzf yydecode-0.2.10.tar.gz
       cd yydecode-0.2.10/
       ./configure
       make
       sudo make install
       make clean
       cd ~/
       rm -rf yydecode/

  Note: After installing you can change the yEnc setting in tmux edit accordingly.

# Threaded Scripts:

  Tmux comes with php threaded scripts, python scripts are still there, but are not used, and will be removed in future

# Table per group:

  Tmux can now be used with TPG ported from nZEDb, you need to run lib/testing/DB/convert_to_tpg.php to convert binaries, parts and partrepair tables, after this you will be able to use
  threaded releases.php

# Alternate NNTP provider:

  Newznab-tmux has added support for dual NNTP providers, you need to edit the provided config.php.example in www folder.

# Sphinx support:

  Newznab-tmux now comes with its own Sphinx support, ported from nZEDb. Installation readme is in misc/sphinxsearch folder.

  This version of tmux has many core newznab files modified, use at your own risk.

Tmux is started by following command in cli, from tmux folder: php tmux-ui.php start. Tmux can be gracefuly stopped in similar manner php tmux-ui.php stop.


