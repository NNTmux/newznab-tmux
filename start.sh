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
#TMPUNRAR_QUERY="SELECT value from site where ID = 66;"
#TMPUNRAR_PATH=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${TMPUNRAR_PATH}"`
#echo "$TMPUNRAR_PATH";

#echo $POST_TO_RUN
#echo $NEWZPATH/lib

#remove postprocessing scripts
rm -f bin/lib/post*

#create postprocessing scripts
cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess2.php
cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess3.php
cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess4.php
cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess5.php

#edit postprocessing scripts
sed -i -e 's/PostProcess/PostProcess2/g' bin/lib/postprocess2.php
sed -i -e 's/processAdditional/processAdditional2/g' bin/lib/postprocess2.php
sed -i -e 's/$tmpPath = $this->site->tmpunrarpath;/$tmpPath = $this->site->tmpunrarpath; \
               $tmpPath .= '2';/g' bin/lib/postprocess2.php
sed -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid desc limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess));/g' bin/lib/postprocess2.php
sed -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...";/g' bin/lib/postprocess2.php

sed -i -e 's/PostProcess/PostProcess3/g' bin/lib/postprocess3.php
sed -i -e 's/processAdditional/processAdditional3/g' bin/lib/postprocess3.php
sed -i -e 's/$tmpPath = $this->site->tmpunrarpath;/$tmpPath = $this->site->tmpunrarpath; \
               $tmpPath .= '3';/g' bin/lib/postprocess3.php
sed -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid asc limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess));/g' bin/lib/postprocess3.php
sed -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...";/g' bin/lib/postprocess3.php

sed -i -e 's/PostProcess/PostProcess4/g' bin/lib/postprocess4.php
sed -i -e 's/processAdditional/processAdditional4/g' bin/lib/postprocess4.php
sed -i -e 's/$tmpPath = $this->site->tmpunrarpath;/$tmpPath = $this->site->tmpunrarpath; \
               $tmpPath .= '4';/g' bin/lib/postprocess4.php
sed -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid asc limit %d, %d ", ($maxattemptstocheckpassworded + 1) * -1, 2 * $numtoProcess, $numtoProcess));/g' bin/lib/postprocess4.php
sed -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...";/g' bin/lib/postprocess4.php

sed -i -e 's/PostProcess/PostProcess5/g' bin/lib/postprocess5.php
sed -i -e 's/processAdditional/processAdditional5/g' bin/lib/postprocess5.php
sed -i -e 's/$tmpPath = $this->site->tmpunrarpath;/$tmpPath = $this->site->tmpunrarpath; \
               $tmpPath .= '5';/g' bin/lib/postprocess5.php
sed -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid asc limit %d, %d ", ($maxattemptstocheckpassworded + 1) * -1, 4 * $numtoProcess, $numtoProcess));/g' bin/lib/postprocess5.php
sed -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...";/g' bin/lib/postprocess5.php

cp conf/tmux.conf conf/tmux_user.conf
$SED -i 's,'changeme,"$NZBS"',' "conf/tmux_user.conf"

printf "\033]0; $TMUX_SESSION\007\003\n"
$TMUX -f conf/tmux_user.conf new-session -d -s $TMUX_SESSION -n $TMUX_SESSION 'cd bin && echo "monitor Working......" && nice -n 19 $PHP monitor.php -i 2> /dev/null'
$TMUX selectp -t 0
$TMUX splitw -h -p 72 'echo "..."'
$TMUX splitw -h -p 50 'echo "..."'
$TMUX selectp -t 0
$TMUX splitw -v -p 65 'echo "..."'
$TMUX splitw -v -p 80 'echo "..."'
$TMUX splitw -v -p 75 'echo "..."'
$TMUX splitw -v -p 67 'echo "..."'
$TMUX splitw -v -p 50 'echo "..."'
$TMUX selectp -t 6
$TMUX splitw -v -p 83 'echo "..."'
$TMUX splitw -v -p 80 'echo "..."'
$TMUX splitw -v -p 75 'echo "..."'
$TMUX splitw -v -p 67 'echo "..."'
$TMUX splitw -v -p 50 'echo "..."'
$TMUX selectp -t 12
$TMUX splitw -v -p 75 'echo "..."'
$TMUX splitw -v -p 67 'echo "..."'
$TMUX splitw -v -p 50 'echo "..."'
$TMUX new-window -n cleanup 'echo "..."'
$TMUX selectp -t 0
$TMUX splitw -h -p 67 'echo "..."'
$TMUX splitw -h -p 50 'echo "..."'
$TMUX selectp -t 0
$TMUX splitw -v -p 50 'echo "..."'
$TMUX selectp -t 2
$TMUX splitw -v -p 50 'echo "..."'
$TMUX selectp -t 4
$TMUX splitw -v -p 50 'echo "..."'

if [[ $USE_HTOP == "true" ]]; then
      $TMUX new-window -n htop '$HTOP'
fi

if [[ $USE_NMON == "true" ]]; then
      $TMUX new-window -n nmon '$NMON -t'
fi

if [[ $USE_BWMNG == "true" ]]; then
      $TMUX new-window -n bwm-ng '$BWMNG'
fi

if [[ $USE_IOTOP == "true" ]]; then
      $TMUX new-window -n iotop '$IOTOP -o'
fi

if [[ $USE_MYTOP == "true" ]]; then
      $TMUX new-window -n mytop '$MYTOP -u $DB_USER -p $DB_PASSWORD -d $DB_NAME -h $DB_HOST'
fi

if [[ $USE_VNSTAT == "true" ]]; then
      $TMUX new-window -n vnstat 'watch $VNSTAT'
fi

$TMUX new-window -n Console 'bash -i'
$TMUX select-window -t$TMUX_SESSION:0
$TMUX attach-session -d -t$TMUX_SESSION

exit
