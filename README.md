[![Code Climate](https://codeclimate.com/github/NNTmux/newznab-tmux/badges/gpa.svg)](https://codeclimate.com/github/NNTmux/newznab-tmux)  [![Build Status](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/badges/build.png?b=dev)](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/build-status/dev) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/badges/quality-score.png?b=dev)](https://scrutinizer-ci.com/g/NNTmux/newznab-tmux/?branch=dev)



NNTmux automatically scans usenet, similar to the way google search bots scan the internet. It does this by collecting usenet headers and temporarily storing them in a database until they can be collated into posts/releases. It provides a web-based front-end providing search, browse, and programmable (API) functionality.

This project is a fork of the open source usenet indexer newznab plus: https://github.com/anth0/nnplus and open source nZEDb usenet indexer https://github.com/nZEDb/nZEDb

NNTmux improves upon the original design, implementing several new features including:

- Optional multi-threaded processing (header retrieval, release creation, post-processing etc)
- Advanced search features (name, subject, category, post-date etc)
- Intelligent local caching of metadata
- Optional sharing of comments with other NNTmux and newznab sites
- Optional tmux (terminal session multiplexing) engine that provides thread, database and performance monitoring
- Image and video samples
- SABnzbd/NZBGet integration (web, API and pause/resume)
- CouchPotato integration (web and API)


## Prerequisites

System Administration know-how. NNTmux is not plug-n-play software. Installation and operation requires a moderate amount of administration experience. NNTmux is designed and developed with GNU/Linux operating systems. Certain features are not available on other platforms. A competent Windows administrator should be able to run NNTmux on a Windows OS.

### Hardware

	4GB RAM, 2 cores(threads) and 20GB disk space minimum.

If you wish to use more than 5 threads a quad core CPU is beneficial.

The overall speed of NNTmux is largely governed by performance of the database. As many of the database tables should be held within system RAM as possible. See Database Section below.

### Software

	PHP 5.6+ (and various modules)
	MySQL 5.6+ (Postgres is not supported)
	Python 2.7 or 3.0 (and various modules)(Optional. Most useful on Windows.)
The installation guides have more detailed software requirements.

### Database

Most (if not all) distributions ship MySQL with a default configuration that will perform well on a Raspberry Pi. If you wish to store more that 500K releases, these default settings will quickly lead to poor performance. Expect this.

As a general rule of thumb the database will need a minimum of 1-2G buffer RAM for every million releases you intend to store. That RAM should be assigned to either of these two parameters:
- key_buffer_size			(MyISAM)
- innodb_buffer_pool_size	(InnoDB)

Use [mysqltuner.pl](http://mysqltuner.pl "MySQL tuner - Use it!") for recommendations for these and other important tuner parameters. Also refer to the nZEDb project's wiki page: https://github.com/nZEDb/nZEDb/wiki/Database-tuning. This is particularly important before you start any large imports or backfills.

MySQL is normally shipped using MyISAM tables by default. This is fine for running with one or a few threads and is a good way to start using NNTmux. You should migrate to the InnoDB table format if NNTmux is configured to use one of the following:

	thread counts > 5
	TPG (Table Per Group) mode
	tmux mode

This conversion script is helpful:

	misc/testing/DB/convert_mysql_tables.php

Before converting to InnoDB be sure to set:

	innodb_file_per_table

<br>


## Installation

 Follow nZEDb Ubuntu install guide, just replace nZEDb with nntmux:

 https://github.com/nZEDb/nZEDb_Misc/blob/master/Guides/Installation/Ubuntu/Guide.md

 For composer install and getting NNTmux follow this guide:

 https://github.com/NNTmux/newznab-tmux/wiki/Installing-Composer

### Support

 Support is given on irc.synirc.net #tmux channel.

### Licenses

 nZEDb is GPL v3. See /docs/LICENSE.txt for the full license.

 Other licenses by various software used by nZEDb:

 Git.php => MIT and GPL v3

 Net_NNTP => W3C

 PHPMailer => GNU Lesser General Public License

 forkdaemon-php => Barracuda Networks, Inc.

 getid3 => GPL v3

 password_compat => Anthony Ferrara

 rarinfo => Modified BSD

 smarty => GNU Lesser General Public v2.1

 AmazonProductAPI.php => Sameer Borate

 GiantBombAPI.php => MIT

 TMDb PHP API class => BSD

 TVDB PHP API => Moinax

 TVMaze PHP API => JPinkney

 Zip file creation class => No license specified.

 simple_html_dom.php => MIT

 All external libraries will have their full licenses in their respectful folders.

 Some licenses might have been missed in this document for various external software, they will be included in their respectful folders.
