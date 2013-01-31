#!/usr/bin/env bash
SOURCE="${BASH_SOURCE[0]}"
DIR="$( dirname "$SOURCE" )"
while [ -h "$SOURCE" ]
do
  SOURCE="$(readlink "$SOURCE")"
  [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
  DIR="$( cd -P "$( dirname "$SOURCE"  )" && pwd )"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

source edit_these.sh

eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

if [[ $AGREED == "no" ]]; then
        echo "Please edit the edit_these.sh file"
        exit
fi

#create mysql my.conf
#this keeps your password from being displayed in ps, htop and others
echo -e '[client]\npassword='$DB_PASSWORD > ./conf/my.cnf
chmod 600 ./conf/my.cnf

#create powerline default.sh
if [ ! -f "powerline/powerline/themes/default.sh" ]; then
  cp powerline/powerline/themes/default.start.sh powerline/powerline/themes/default.sh
fi

if [[ $RAMDISK == "true" ]]; then
  TMPUNRAR_QUERY="SELECT value from site where ID = 66;"
  TMPUNRAR_PATH=`$MYSQL --defaults-extra-file=conf/my.cnf -u$DB_USER -h$DB_HOST $DB_NAME -s -N -e "${TMPUNRAR_QUERY}"`
  umount $TMPUNRAR_PATH &> /dev/null
  rm -fr $TMPUNRAR_PATH
  mkdir $TMPUNRAR_PATH
  chmod 777 $TMPUNRAR_PATH
  mount -t tmpfs -o size=256M tmpfs $TMPUNRAR_PATH 2>&1 > /dev/null
fi

#remove postprocessing scripts
rm -f bin/lib/post*
rm -f bin/processAlternate*

#create postprocessing scripts
for (( c=2; c<=30; c++ ))
  do
  d=$((($c - 1) * 100))
  cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess$c.php
  sed -i -e "s/PostProcess/PostProcess$c/g" bin/lib/postprocess$c.php
  sed -i -e "s/processAdditional/processAdditional$c/g" bin/lib/postprocess$c.php
  sed -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
                  \$tmpPath .= '\/tmp$c';/g" bin/lib/postprocess$c.php
  sed -i -e "s/order by r.postdate desc limit %d.*\$/order by r.guid asc limit %d, %d \", (\$maxattemptstocheckpassworded + 1) * -1, $c * \$numtoProcess, \$numtoProcess));/g" bin/lib/postprocess$c.php
  sed -i -e "s/PostPrc : Performing additional post processing.*\$/PostPrc : Performing additional post processing by guid on \".\$rescount.\" releases, starting at $d ...\";/g" bin/lib/postprocess$c.php

  cp bin/lib/alternate bin/processAlternate$c.php
  sed -i -e "s/1/$c/g" bin/processAlternate$c.php
done

cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess1.php
cp bin/lib/alternate bin/processAlternate1.php

#edit postprocessing scripts
sed -i -e 's/PostProcess/PostProcess1/g' bin/lib/postprocess1.php
sed -i -e 's/processAdditional/processAdditional1/g' bin/lib/postprocess1.php
sed -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
                \$tmpPath .= '\/tmp1';/g" bin/lib/postprocess1.php
sed -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid desc limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess));/g' bin/lib/postprocess1.php
sed -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...";/g' bin/lib/postprocess1.php

chmod -R 777 $TMPUNRAR_PATH

printf "\033]0; $TMUX_SESSION\007\003\n"
$TMUXCMD -f $TMUX_CONF new-session -d -s $TMUX_SESSION -n $TMUX_SESSION 'cd bin && echo "Monitor Started" && echo "It might take a minute for everything to spinup......" && $NICE -n 19 $PHP speedy.php'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 72 'echo "..."'
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -v -p 60 'echo "..."'
if [[ $NZB_THREADS == "true" ]]; then
  $TMUXCMD splitw -v -p 60 'echo "..."'
else
  $TMUXCMD splitw -v -p 85 'echo "..."'
fi
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 4
$TMUXCMD splitw -v -p 83 'echo "..."'
$TMUXCMD splitw -v -p 80 'echo "..."'
$TMUXCMD splitw -v -p 75 'echo "..."'
$TMUXCMD splitw -v -p 67 'echo "..."'
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 10
$TMUXCMD splitw -v -p 75 'echo "..."'
$TMUXCMD splitw -v -p 67 'echo "..."'
$TMUXCMD splitw -v -p 50 'echo "..."'

$TMUXCMD new-window -n postprocessing1 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 2
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 2
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 4
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 6
$TMUXCMD splitw -h -p 50 'echo "..."'

$TMUXCMD new-window -n postprocessing2 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 2
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 2
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 4
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 6
$TMUXCMD splitw -h -p 50 'echo "..."'

$TMUXCMD new-window -n postprocessing3 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 2
$TMUXCMD splitw -v -p 50 'echo "..."'
$TMUXCMD selectp -t 0
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 2
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 4
$TMUXCMD splitw -h -p 50 'echo "..."'
$TMUXCMD selectp -t 6
$TMUXCMD splitw -h -p 50 'echo "..."'

$TMUXCMD select-window -t$TMUX_SESSION:0
$TMUXCMD attach-session -d -t$TMUX_SESSION

exit
