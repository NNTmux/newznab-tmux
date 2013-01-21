#!/usr/bin/env bash

if grep -q '/path/to/nzbs' "edit_these.sh" ; then
  sed -i -e 's/export NZBS=.*$/\export NZBS="\/home\/jonnyboy\/nzbs\/batch"/' edit_these.sh
  sed -i -e 's/export OMPTIMISE=.*$/export OMPTIMISE="true"/' edit_these.sh
  sed -i -e 's/export AGREED=.*$/export AGREED="yes"/' edit_these.sh
  sed -i -e 's/export INNODB=.*$/export INNODB="false"/' edit_these.sh
  sed -i -e 's/SLEEP=.*$/SLEEP="40"/' edit_these.sh
  sed -i -e 's/export SHOW_WHY=.*$/export SHOW_WHY="false"/' edit_these.sh
else
  sed -i -e 's/export NZBS=.*$/export NZBS="\/path\/to\/nzbs"/' edit_these.sh
  sed -i -e 's/export OMPTIMISE=.*$/export OMPTIMISE="false"/' edit_these.sh
  sed -i -e 's/export AGREED=.*$/export AGREED="no"/' edit_these.sh
  sed -i -e 's/export INNODB=.*$/export INNODB="true"/' edit_these.sh
  sed -i -e 's/SLEEP=.*$/SLEEP="20"/' edit_these.sh
  sed -i -e 's/export SHOW_WHY=.*$/export SHOW_WHY="true"/' edit_these.sh
fi
