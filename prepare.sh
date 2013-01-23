#!/usr/bin/env bash
#this file can be used to save a put a time editing and reediting your edit_these.sh
#this file must be renamed to fix.sh, or else it will be overwritten with the next git pull
#then edit, add, remove anything see necessary

#useage before updating the git repo run:
#./fix.sh
#git pull
#fix.sh 

#now you have edited the file to update, updated and put you changes back.

if grep -q '/path/to/nzbs' "edit_these.sh" ; then
  sed -i -e 's/export NZBS=.*$/\export NZBS="\/home\/jonnyboy\/nzbs\/batch"/' edit_these.sh
  sed -i -e 's/export OPTIMISE=.*$/export OPTIMISE="true"/' edit_these.sh
  sed -i -e 's/export AGREED=.*$/export AGREED="yes"/' edit_these.sh
  sed -i -e 's/export INNODB=.*$/export INNODB="false"/' edit_these.sh
  sed -i -e 's/SLEEP=.*$/SLEEP="1"/' edit_these.sh
  sed -i -e 's/export SHOW_WHY=.*$/export SHOW_WHY="false"/' edit_these.sh
  sed -i -e 's/export PARSING=.*$/export PARSING="true"/' edit_these.sh
  sed -i -e 's/export PASSWORD=.*$/export PASSWORD="svnplu5"/' svn.sh
  sed -i -e 's/RELEASES_SLEEP=.*$/RELEASES_SLEEP="20"/' edit_these.sh
else
  sed -i -e 's/export NZBS=.*$/export NZBS="\/path\/to\/nzbs"/' edit_these.sh
  sed -i -e 's/export OPTIMISE=.*$/export OPTIMISE="false"/' edit_these.sh
  sed -i -e 's/export AGREED=.*$/export AGREED="no"/' edit_these.sh
  sed -i -e 's/export INNODB=.*$/export INNODB="true"/' edit_these.sh
  sed -i -e 's/SLEEP=.*$/SLEEP="20"/' edit_these.sh
  sed -i -e 's/export SHOW_WHY=.*$/export SHOW_WHY="true"/' edit_these.sh
  sed -i -e 's/export PARSING=.*$/export PARSING="false"/' edit_these.sh
  sed -i -e 's/export PASSWORD=.*$/export PASSWORD="password"/' svn.sh
fi
