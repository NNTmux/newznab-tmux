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

printf "\033]0; $TMUX_SESSION\007\003\n"
$TMUX -q -2 new-session -d -s $TMUX_SESSION -n NewzNab-dev 'cd bin && echo "monitor Working......" && nice -n 19 $PHP monitor.php -i'
$TMUX selectp -t 0
$TMUX -q splitw -h -p 72 'echo "..."'
$TMUX -q splitw -h -p 50 'echo "..."'
$TMUX selectp -t 0
$TMUX -q splitw -v -p 65 'echo "..."'
$TMUX -q splitw -v -p 75 'echo "..."'
$TMUX -q splitw -v -p 67 'echo "..."'
$TMUX -q splitw -v -p 50 'echo "..."'
$TMUX selectp -t 5
$TMUX -q splitw -v -p 83 'echo "..."'
$TMUX -q splitw -v -p 80 'echo "..."'
$TMUX -q splitw -v -p 75 'echo "..."'
$TMUX -q splitw -v -p 67 'echo "..."'
$TMUX -q splitw -v -p 50 'echo "..."'
$TMUX selectp -t 11
$TMUX -q splitw -v -p 75 'echo "..."'
$TMUX -q splitw -v -p 67 'echo "..."'
$TMUX -q splitw -v -p 50 'echo "..."'
$TMUX new-window -n cleanup 'echo "..."'
$TMUX selectp -t 0
$TMUX -q splitw -h -p 67 'echo "..."'
$TMUX -q splitw -h -p 50 'echo "..."'

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
