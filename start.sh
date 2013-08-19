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
    $TMUXCMD splitw -v -p 30 'printf "\033]2;nzbcount\033\\"'

    $TMUXCMD selectp -t 2
    $TMUXCMD splitw -v -p 75 'printf "\033]2;backfill\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;import-nzb\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;update_releases\033\\"'

    $TMUXCMD new-window -n other 'printf "\033]2;update_predb\033\\"' 
	$TMUXCMD selectp -t 0
	$TMUXCMD splitw -v -p 75 'printf "\033]2;sphinx\033\\"' 
	$TMUXCMD splitw -v -p 67 'printf "\033]2;update_missing_movie_info\033\\"' 
	$TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 50 'printf "\033]2;update_tv\033\\"' 
	$TMUXCMD selectp -t 2
    $TMUXCMD splitw -h -p 50 'printf "\033]2;delete_parts\033\\"' 
	$TMUXCMD selectp -t 4
    $TMUXCMD splitw -h -p 50 'printf "\033]2;optimize\033\\"' 
	
    $TMUXCMD new-window -n post1a 'printf "\033]2;postprocessing[01]\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 88 'printf "\033]2;postprocessing[03]\033\\"'
    $TMUXCMD splitw -v -p 86 'printf "\033]2;postprocessing[05]\033\\"'
    $TMUXCMD splitw -v -p 83 'printf "\033]2;postprocessing[07]\033\\"'
    $TMUXCMD splitw -v -p 80 'printf "\033]2;postprocessing[09]\033\\"'
    $TMUXCMD splitw -v -p 75 'printf "\033]2;postprocessing[11]\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;postprocessing[13]\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;postprocessing[15]\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[02]\033\\"'
    $TMUXCMD selectp -t 2
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[04]\033\\"'
    $TMUXCMD selectp -t 4
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[06]\033\\"'
    $TMUXCMD selectp -t 6
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[08]\033\\"'
    $TMUXCMD selectp -t 8
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[10]\033\\"'
    $TMUXCMD selectp -t 10
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[12]\033\\"'
    $TMUXCMD selectp -t 12
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[14]\033\\"'
    $TMUXCMD selectp -t 14
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[16]\033\\"'

    $TMUXCMD new-window -n post1b 'printf "\033]2;postprocessing[17]\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 75 'printf "\033]2;postprocessing[21]\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;postprocessing[25]\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;postprocessing[29]\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 75 'printf "\033]2;postprocessing[18]\033\\"'
    $TMUXCMD splitw -h -p 67 'printf "\033]2;postprocessing[19]\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[20]\033\\"'
    $TMUXCMD selectp -t 4
    $TMUXCMD splitw -h -p 75 'printf "\033]2;postprocessing[22]\033\\"'
    $TMUXCMD splitw -h -p 67 'printf "\033]2;postprocessing[23]\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[24]\033\\"'
    $TMUXCMD selectp -t 8
    $TMUXCMD splitw -h -p 75 'printf "\033]2;postprocessing[26]\033\\"'
    $TMUXCMD splitw -h -p 67 'printf "\033]2;postprocessing[27]\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[28]\033\\"'
    $TMUXCMD selectp -t 12
    $TMUXCMD splitw -h -p 75 'printf "\033]2;postprocessing[30]\033\\"'
    $TMUXCMD splitw -h -p 67 'printf "\033]2;postprocessing[31]\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;postprocessing[32]\033\\"'

    $TMUXCMD new-window -n post2a 'printf "\033]2;processNfos1\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;processNfos2\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 75 'printf "\033]2;processGames1\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;processMovies1\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;processMusic1\033\\"'
    $TMUXCMD selectp -t 4
    $TMUXCMD splitw -v -p 75 'printf "\033]2;processGames2\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;processMovies2\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;processMusic2\033\\"'

    $TMUXCMD new-window -n post2b 'printf "\033]2;processSpotnab\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;processAnime\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 75 'printf "\033]2;processTVRage\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;processBooks1\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;processOther\033\\"'
    $TMUXCMD selectp -t 4
    $TMUXCMD splitw -v -p 75 'printf "\033]2;processTheTVDB\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;processBooks2\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;processUnwanted\033\\"'
	
	$TMUXCMD new-window -n other2 'printf "\033]2;Remove_Crap_Releases\033\\"'
	$TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 66 'printf "\033]2;Afly_PreDB\033\\"'
    $TMUXCMD selectp -t 1
    $TMUXCMD splitw -h -p 50 'printf "\033]2;Fix_Release_Names\033\\"'
	
   

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



