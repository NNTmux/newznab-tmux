#!/usr/bin/env bash
#this file can be used to save a put a time editing and reediting your defaults.sh
#this file must be renamed to fix.sh, or else it will be overwritten with the next git pull
#then edit, add, remove anything see necessary

#useage before updating the git repo run:
#./fix.sh
#git pull
#fix.sh 

#now you have edited the file to update, updated and put you changes back.

if grep -q '/path/to/nzbs' "../defaults.sh" ; then
  sed -i -e 's/export NZBS=.*$/\export NZBS="\/home\/jonnyboy\/nzbs\/batch"/' ../defaults.sh
  sed -i -e 's/export OPTIMISE=.*$/export OPTIMISE="true"/' ../defaults.sh
  sed -i -e 's/export AGREED=.*$/export AGREED="yes"/' ../defaults.sh
  sed -i -e 's/export INNODB=.*$/export INNODB="false"/' ../defaults.sh
  sed -i -e 's/SLEEP=.*$/SLEEP="1"/' ../defaults.sh
  sed -i -e 's/export SHOW_WHY=.*$/export SHOW_WHY="false"/' ../defaults.sh
  sed -i -e 's/export PARSING=.*$/export PARSING="true"/' ../defaults.sh
  sed -i -e 's/export PASSWORD=.*$/export PASSWORD="password"/' update_svn.sh
  sed -i -e 's/RELEASES_SLEEP=.*$/RELEASES_SLEEP="20"/' ../defaults.sh
else
  rm ../defaults.sh
  sed -i -e 's/export PASSWORD=.*$/export PASSWORD="password"/' update_svn.sh
fi
