[![Code Climate](https://codeclimate.com/github/NNTmux/newznab-tmux/badges/gpa.svg)](https://codeclimate.com/github/NNTmux/newznab-tmux)  [![Build Status](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/badges/build.png?b=dev)](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/build-status/dev) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/badges/quality-score.png?b=dev)](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/?branch=dev)


I have forked jonnyboys newznab-tmux as he and the dev team have moved to another project (https://github.com/nZEDb/nZEDb). I will try as much as i can to maintain and improve these tmux scripts, where possible and needed, as they are a valuable addendum to newznab+.
Support is given on irc.synirc.net #tmux channel.

I started adapting some of more interesting scripts from nZEDb, but they require tempering with newznab database, so use them at your own risk. By using newznab-tmux you will render your indexer incompatible with newznab (you can revert changes by forcing the svn checkout, it will replace the changed files. Newznab is not aware of the changes in database, so you are safe to leave them as they are). Scripts require PHP version >= 5.6.x and Python 2.7x or newer.

# Steps to have a working tmux install:

 CD to the root of your newznab install, ie.  cd /var/www/nntmux
 Next steps are very important:

 		git init
 		git remote add origin https://github.com/NNTmux/newznab-tmux.git
 		git fetch
 		git reset --hard origin/your_wanted_branch, ie. git reset --hard origin/0.x
 		git checkout -t origin/your_wanted_branch, ie. git checkout -t origin/0.x

	Schema for first database update is located in resources/db/schema/ folder. Import it to your database.
	There is another file in that folder nntmux_fi_schema.sql, use that if you don't have any releases and users on your site, AKA, completely fresh install of newznab+.
	BE WARNED: If you import this schema it WILL NUKE YOUR DATABASE.
	If you are updating from latest newznab svn (aka tvmaze version), you need to rename back tvinfoID columns into rageid
	(located in releases and user_series tables, maybe some more), before you import the schema.sql.

	You need to chmod to 777 following folders now:
	resources/*
	resources/smarty/templates_c
	nzbfiles/

	You need to add an alias to your apache/nginx conf of your indexer:
	Alias /covers /path/to/newznab/resources/covers

# Composer

  Note: Newznab-tmux uses composer to install required libraries. Now is time to <a href="https://getcomposer.org/doc/00-intro.md#downloading-the-composer-executable" title="Composer web site">install Composer</a>.
  		Use the global method.
  		When you have downloaded and installed composer,
  		run "./tmux update nntmux" to install dependencies and create required
  		folders and autoloader for them, and to update your database.

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


