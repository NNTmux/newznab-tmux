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

eval $( sed -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p; }" $NEWZPATH/www/config.php )

if [[ $AGREED == "no" ]]; then
	echo "Please edit the edit_these.sh file"
	exit
fi

export INNODB_PATH=$DIR"/bin/innodb"
export MYISAM_PATH=$DIR"/bin/myisam"
export START_PATH=$DIR

$TMUX new-session -d -s NewzNab -n NewzNab 'echo "monitor Working......" && nice -n 19 $PHP bin/monitor.php;nice -n 19 $PHP bin/monitor.php;exec bash -i'
$TMUX selectp -t 0
$TMUX splitw -h -p 72 'cd bin && echo "Processing Books....." && sleep 12 && nice -n 19 ./postProcessing1.sh;nice -n 19 ./postProcessing1.sh;exec bash -i'
$TMUX splitw -h -p 50 'cd bin && echo "imports Working......" && nice -n 10 ./workhorse.sh;nice -n 10 ./workhorse.sh;exec bash -i'
$TMUX selectp -t 0
$TMUX splitw -v -p 65 'echo "processNfos Working......" && sleep 3 && nice -n 19 $PHP bin/postprocess_nfo.php;nice -n 19 $PHP bin/postprocess_nfo.php;exec bash -i'
$TMUX splitw -v -p 67 'echo "processAdditional Thread #1 Working......" && sleep 6 && nice -n 19 $PHP bin/processAlternate2.php;nice -n 19 $PHP bin/processAlternate2.php;exec bash -i'
$TMUX splitw -v -p 50 'echo "processAdditional Thread #2 Working......" && sleep 9 && nice -n 19 $PHP bin/processAlternate3.php;nice -n 19 $PHP bin/processAlternate3.php;exec bash -i'
$TMUX selectp -t 4
$TMUX splitw -v -p 83 'cd bin && echo "Processing Games....." && sleep 15 && nice -n 19 ./postProcessing2.sh;nice -n 19 ./postProcessing2.sh;exec bash -i'
$TMUX splitw -v -p 80 'cd bin && echo "Processing Movies....." && sleep 18 && nice -n 19  ./postProcessing3.sh;nice -n 19  ./postProcessing3.sh;exec bash -i'
$TMUX splitw -v -p 75 'cd bin && echo "Processing Music....." && sleep 21 && nice -n 19 ./postProcessing4.sh;nice -n 19 ./postProcessing4.sh;exec bash -i'
$TMUX splitw -v -p 67 'cd bin && echo "Processing TV....." && sleep 24 && nice -n 19 ./postProcessing5.sh;nice -n 19 ./postProcessing5.sh;exec bash -i'
$TMUX splitw -v -p 50 'cd bin && echo "Processing Other....." && sleep 27 && nice -n 19 ./postProcessing6.sh;nice -n 19 ./postProcessing6.sh;exec bash -i'
$TMUX selectp -t 10
$TMUX splitw -v -p 50 'cd bin && echo "create Releases Working......" && nice -n 15 ./cleanup_scripts.sh;nice -n 15 ./cleanup_scripts.sh;exec bash -i'


if [[ $USE_HTOP == "true" ]]; then
      $TMUX new-window -n htop '$HTOP'
fi

if [[ $USE_NMON == "true" ]]; then
      $TMUX new-window -n nmom '$NMON'
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

$TMUX select-window -tNewzNab:0
$TMUX attach-session -d -tNewzNab

