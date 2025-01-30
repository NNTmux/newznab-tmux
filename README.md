<p align="center">
    <a href="https://packagist.org/packages/nntmux/newznab-tmux"><img src="https://poser.pugx.org/nntmux/newznab-tmux/v/stable.svg" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/nntmux/newznab-tmux"><img src="https://poser.pugx.org/nntmux/newznab-tmux/license.svg" alt="License"></a>
    <a href="https://github.com/prettier/prettier"><img src=https://img.shields.io/badge/code_style-prettier-ff69b4.svg?style=flat-square"></a>
    <a href="https://www.patreon.com/bePatron?u=6160908"><img src="https://c5.patreon.com/external/logo/become_a_patron_button.png" alt="Become a Patron!" height="20"></a>
</p>



NNTmux automatically scans usenet, similar to the way google search bots scan the internet. It does this by collecting usenet headers and temporarily storing them in a database until they can be collated into posts/releases. It provides a web-based front-end providing search, browse, and programmable (API) functionality.

This project is a fork of the open source usenet indexer newznab plus: https://github.com/anth0/nnplus and open source nZEDb usenet indexer https://github.com/nZEDb/nZEDb

NNTmux improves upon the original design, implementing several new features including:

- Optional multi-threaded processing (header retrieval, release creation, post-processing etc)
- Advanced search features (name, subject, category, post-date etc)
- Intelligent local caching of metadata
- Tmux (terminal session multiplexing) engine that provides thread, database and performance monitoring
- Image and video samples


## Prerequisites

System Administration know-how. NNTmux is not plug-n-play software. Installation and operation requires a moderate amount of administration experience. NNTmux is designed and developed with GNU/Linux operating systems. Certain features are not available on other platforms. A competent Windows administrator should be able to run NNTmux on a Windows OS.

### Hardware

	64GB RAM, 8 cores(16 threads) and 320GB disk space minimum.
If you wish to use more than 5 threads an eight core CPU is beneficial.

The overall speed of NNTmux is largely governed by performance of the database. As many of the database tables should be held within system RAM as possible. See Database Section below.

### Software

	PHP 8.2+ (and various modules)
	MariaDB 10+ or MySQL 8+ (Postgres is not supported)
The installation guides have more detailed software requirements.

### Database

Most (if not all) distributions ship MySQL with a default configuration that will perform well on a Raspberry Pi. If you wish to store more that 500K releases, these default settings will quickly lead to poor performance. Expect this.

As a general rule of thumb the database will need a minimum of 1-2G buffer RAM for every million releases you intend to store. That RAM should be assigned to this parameter:

- innodb_buffer_pool_size

Use [mysqltuner.pl](http://mysqltuner.pl "MySQL tuner - Use it!") for recommendations for these and other important tuner parameters. Also refer to the nZEDb project's wiki page: https://github.com/nZEDb/nZEDb/wiki/Database-tuning. This is particularly important before you start any large imports or backfills.


## Installation

 Follow NNTmux Ubuntu install guide:

 https://github.com/NNTmux/newznab-tmux/wiki/Ubuntu-Install-guide

 For composer install and getting NNTmux follow this guide:

 https://github.com/NNTmux/newznab-tmux/wiki/Installing-Composer

## Docker

 NNTmux uses Laravel Sail to manage docker container. To start docker container, edit your .env and then run:

 ```./sail up -d```

### Support

 Support is given on discord: https://discord.gg/GjgGSzkrjh

### Licenses

 NNTmux is GPL v3. See LICENSE.txt for the full license.

 All external libraries will have their full licenses in their respective folders.
