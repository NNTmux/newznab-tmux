#!/bin/bash
source edit_these.sh
eval $( sed -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" ../../../../www/config.php )

if [[ $AGREED == "no" ]]; then
	echo "Please edit the edit_these.sh file"
	exit
fi

$TMUX new-session -d -s NewzNab -n NewzNab 'echo "processNfos Working......" && $PHP bin/postprocess_nfo.php'
$TMUX selectp -t 0
$TMUX splitw -v -p 82 'echo "monitor Working......" && $PHP bin/monitor.php'
$TMUX splitw -v -p 66 'cd bin && echo "imports Working......" && ./workhorse.sh'
$TMUX selectp -t 0
$TMUX splitw -h -p 66 'echo "processAdditional Working......" && $PHP bin/processAlternate.php'
$TMUX splitw -h -p 50 'echo "postProcessing Working......" && $PHP bin/postprocessing.php'
$TMUX selectp -t 3
if [[ $CHOICE_APP == "mytop" ]]; then
        $TMUX splitw -h -p 50 '$MYTOP -u $DB_USER -p $DB_PASSWORD -d $DB_NAME -h $DB_HOST'
else
	$TMUX splitw -h -p 50 '$BWMNG'
fi
$TMUX selectp -t 5
$TMUX splitw -h -p 50 'cd bin && echo "create Releases Working......" && ./cleanup_scripts.sh'


$TMUX select-window -tNewzNab:0
$TMUX attach-session -d -tNewzNab
