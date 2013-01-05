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

$TMUX new-session -d -s NewzNab -n NewzNab 'echo "processNfos Working......" && sleep 3 && $PHP bin/postprocess_nfo.php;exec bash -i'
$TMUX selectp -t 0
$TMUX splitw -v -p 80 'echo "monitor Working......" && $PHP bin/monitor.php;exec bash -i'
$TMUX selectp -t 0
$TMUX splitw -h -p 80 'echo "processAdditional Thread #1 Working......" && sleep 6 && $PHP bin/processAlternate2.php;exec bash -i'
$TMUX selectp -t 3
$TMUX splitw -h -p 67 'cd bin && echo "Processing Books....." && sleep 12 && ./postProcessing1.sh;exec bash -i'
$TMUX splitw -h -p 50 'cd bin && echo "Processing Music....." && sleep 21 && ./postProcessing4.sh;exec bash -i'
$TMUX selectp -t 1
$TMUX splitw -v -p 50 'echo "processAdditional Thread #2 Working......" && sleep 9 && $PHP bin/processAlternate3.php;exec bash -i'


$TMUX selectp -t 3
$TMUX splitw -v -p 67 'cd bin && echo "Processing Games....." && sleep 15 && ./postProcessing2.sh;exec bash -i'
$TMUX splitw -v -p 50 'cd bin && echo "Processing Movies....." && sleep 18 && ./postProcessing3.sh;exec bash -i'
$TMUX selectp -t 6
$TMUX splitw -v -p 67 'cd bin && echo "Processing TV....." && sleep 24 && ./postProcessing5.sh;exec bash -i'
$TMUX splitw -v -p 50 'cd bin && echo "Processing Other....." && sleep 27 && ./postProcessing6.sh;exec bash -i'
$TMUX selectp -t 9
$TMUX splitw -v -p 63 'cd bin && echo "imports Working......" && ./workhorse.sh;exec bash -i'
$TMUX selectp -t 9
#$TMUX splitw -h -p 67 'nmon'
$TMUX splitw -h -p 67 '$MYTOP -u $DB_USER -p $DB_PASSWORD -d $DB_NAME -h $DB_HOST'
$TMUX selectp -t 11
$TMUX splitw -h -p 50 'cd bin && echo "create Releases Working......" && ./cleanup_scripts.sh;exec bash -i'

$TMUX select-window -tNewzNab:0
$TMUX attach-session -d -tNewzNab

