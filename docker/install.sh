#!/bin/bash
read -p "Are you sure you want to run this script (take a look at it first)? (y/n): " yn
case $yn in
    [Nn]* ) exit 1
esac

mysqladmin ping --wait=30 --host=$DB_HOST --port=$DB_PORT --user=root --password=$DB_ROOTPASSWORD

mysql --host=$DB_HOST --user=root --password=$DB_ROOTPASSWORD -e "INSTALL SONAME 'ha_sphinx';"
mysql --host=$DB_HOST --user=root --password=$DB_ROOTPASSWORD -e "GRANT ALL PRIVILEGES ON *.* TO $DB_USERNAME@'%' WITH GRANT OPTION;"

php artisan nntmux:install

php /site/misc/sphinxsearch/create_se_tables.php $SPHINX_HOST 9312
php /site/misc/sphinxsearch/populate_rt_indexes.php releases_rt
php /site/misc/sphinxsearch/populate_rt_indexes.php predb_rt

read -p "Do you want to batch import predb now (this will take an hour or two)? (y/n): " yn
case $yn in
    [Yy]* ) php /site/cli/data/predb_import_daily_batch.php 0 remote false
esac
