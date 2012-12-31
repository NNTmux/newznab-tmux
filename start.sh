#!/bin/bash
source edit_these.sh


if [[ "$AGREED" == "no" ]]; then
	echo "Please edit the edit_these.sh file"
	exit
fi

$TMUX new-session -d -s NewzNab -n NewzNab 'echo "processNfos Working......" && sleep 30 && $PHP bin/postprocess_nfo.php'
$TMUX selectp -t 0
$TMUX splitw -v -p 82 'echo "monitor Working......" && $PHP bin/monitor.php'


if [ "$THREADS" == "true"  -a "$INNODB" == "true" ]; then
	$TMUX splitw -v -p 66 'cd bin && echo "imports Working......" && sleep 45 && ./innodb_import_full_threaded.sh'
elif [ "$THREADS" != "true" -a "$INNODB" == "true" ]; then
	$TMUX splitw -v -p 66 'cd bin && echo "imports Working......" && sleep 45 && ./innodb_import_threaded.sh'
elif [ "$THREADS" == "true" -a "$INNODB" != "true" ]; then
	$TMUX splitw -v -p 66 'cd bin && echo "imports Working......" && sleep 45 && ./myisam_import_full_threaded.sh'
else
	$TMUX splitw -v -p 66 'cd bin && echo "imports Working......" && sleep 45 && ./myisam_import_threaded.sh'
fi

$TMUX selectp -t 0
$TMUX splitw -h -p 66 'echo "processAdditional Working......" && sleep 35 && $PHP bin/processAlternate.php'
$TMUX splitw -h -p 50 'echo "postProcessing Working......" && sleep 40 && $PHP bin/postprocessing.php'
$TMUX selectp -t 3
$TMUX splitw -h -p 50 #'$BWMNG'
$TMUX selectp -t 5
$TMUX splitw -h -p 50 'cd bin && echo "create Releases Working......" && ./cleanup_scripts.sh'


$TMUX select-window -tNewzNab:0
$TMUX attach-session -d -tNewzNab
