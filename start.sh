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

if [ ! -f defaults.sh ]; then
    clear
    echo "Please copy config.sh to defaults.sh"
    exit
fi

source defaults.sh

eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

if [[ $AGREED == "no" ]]; then
	echo "Please edit the defaults.sh file"
	exit
fi

$DIR/bin/scripts/check_vars.sh &
pid=$!
wait $!
script_exit_value=$?
if [ "${script_exit_value}" -ne "0" ] ; then
        exit 1
fi

#check if tmux session exists, attach if exists, create new if not exist
if $TMUXCMD has-session -t $TMUX_SESSION; then
	$TMUXCMD attach-session -t $TMUX_SESSION
else

    $DIR/bin/scripts/preflight.sh &

    printf "The above is just a TMUX notice, it is saying TMUX, that you do not have a TMUX session currently running. It is not an error. It is TMUX"
    printf "\033]0; $TMUX_SESSION\007\003\n"

    $TMUXCMD -f $TMUX_CONF new-session -d -s $TMUX_SESSION -n Monitor 'printf "\033]2;Monitor\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 67 'printf "\033]2;update_binaries\033\\"'

    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 30 'printf "\033]2;postprocessing\033\\"'

    $TMUXCMD selectp -t 2
    $TMUXCMD splitw -v -p 75 'printf "\033]2;backfill\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;import-nzb\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;update_releases\033\\"'

    $TMUXCMD new-window -n Utils 'printf "\033]2;update_predb\033\\"'
	$TMUXCMD selectp -t 0
	$TMUXCMD splitw -v -p 75 'printf "\033]2;sphinx\033\\"' 
	$TMUXCMD splitw -v -p 67 'printf "\033]2;update_missing_movie_info\033\\"' 
	$TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 50 'printf "\033]2;update_tv\033\\"' 
	$TMUXCMD selectp -t 2
    $TMUXCMD splitw -h -p 50 'printf "\033]2;delete_parts\033\\"' 
	$TMUXCMD selectp -t 4
    $TMUXCMD splitw -h -p 50 'printf "\033]2;nzbcount\033\\"'

    $TMUXCMD new-window -n PostProcessing 'printf "\033]2;processNfos1\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;processGames\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 80 'printf "\033]2;processTV\033\\"'
    $TMUXCMD splitw -v -p 75 'printf "\033]2;processMovies\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;processMusic\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;processAnime\033\\"'
    $TMUXCMD selectp -t 5
    $TMUXCMD splitw -v -p 80 'printf "\033]2;processSpotnab\033\\"'
    $TMUXCMD splitw -v -p 75 'printf "\033]2;processBooks\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;processOther\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;processUnwanted\033\\"'
	
	$TMUXCMD new-window -n FixNames 'printf "\033]2;Fix_Release_Names\033\\"'
	$TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 50 'printf "\033]2;RemoveCrap\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 50 'printf "\033]2;PreDB_Hash_Decrypt\033\\"'
    $TMUXCMD selectp -t 1
    $TMUXCMD splitw -v -p 50 'printf "\033]2;RequestID\033\\"'
    $TMUXCMD selectp -t 3
    $TMUXCMD splitw -h -p 50 'printf "\033]2;PrehashUpdate\033\\"'

	
   

    if [[ $USE_HTOP == "true" ]]; then
        $TMUXCMD new-window -n htop '$HTOP'
    fi

    if [[ $USE_NMON == "true" ]]; then
        $TMUXCMD new-window -n nmon '$NMON -t'
    fi

    if [[ $USE_BWMNG == "true" ]]; then
        $TMUXCMD new-window -n bwm-ng '$BWMNG'
    fi

    if [[ $USE_IOTOP == "true" ]]; then
        $TMUXCMD new-window -n iotop '$IOTOP -o'
    fi

    if [[ $USE_TOP == "true" ]]; then
        $TMUXCMD new-window -n top '$TOP -m io -zto total'
    fi

    if [[ $USE_MYTOP == "true" ]]; then
        $TMUXCMD new-window -n mytop '$MYTOP -u $DB_USER -p $DB_PASSWORD -d $DB_NAME -h $DB_HOST -s1'
    fi

    if [[ $USE_VNSTAT == "true" ]]; then
        $TMUXCMD new-window -n vnstat 'watch $VNSTAT $VNSTAT_ARGS'
    fi

    if [[ $USE_IFTOP == "true" ]]; then
        $TMUXCMD new-window -n iftop '$IFTOP -i $INTERFACE'
    fi

    if [[ $USE_ATOP == "true" ]]; then
        $TMUXCMD new-window -n atop '$ATOP'
    fi

    if [[ $USE_TCPTRACK == "true" ]]; then
        $TMUXCMD new-window -n tcptrack '$TCPTRACK $TRCPTRACK_ARGS'
    fi

    if [[ $USE_CONSOLE == "true" ]]; then
        $TMUXCMD new-window -n Console 'bash -i'
    fi

    $TMUXCMD select-window -t$TMUX_SESSION:0
    $TMUXCMD respawnp -t 0 'cd bin && $NICE -n$NICENESS $PHP monitor.php'
    $TMUXCMD attach-session -d -t$TMUX_SESSION

fi
exit



