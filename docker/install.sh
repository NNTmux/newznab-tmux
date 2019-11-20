#!/bin/bash
mysql --host=$DB_HOST --user=root --password=$MYSQL_ROOT_PASSWORD -e "INSTALL SONAME 'ha_sphinx';"
mysql --host=$DB_HOST --user=root --password=$MYSQL_ROOT_PASSWORD -e "GRANT ALL PRIVILEGES ON *.* TO $DB_USERNAME@'%' WITH GRANT OPTION;"
sudo -E -u www-data php artisan nntmux:install
sudo -E -u www-data php /var/www/NNTmux/misc/sphinxsearch/create_se_tables.php $SPHINX_HOST 9312
sudo -E -u www-data php /var/www/NNTmux/misc/sphinxsearch/populate_rt_indexes.php releases_rt
sudo -E -u www-data php /var/www/NNTmux/misc/sphinxsearch/populate_rt_indexes.php predb_rt
read -p "Do you want to batch import predb now (this will take an hour or two)? (y/n)" yn
case $yn in
    [Yy]* ) sudo -E -u notroot php /var/www/NNTmux/cli/data/predb_import_daily_batch.php 0 remote false
esac
