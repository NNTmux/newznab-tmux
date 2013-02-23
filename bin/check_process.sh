#!/usr/bin/env bash
source ../config.sh
source ../defaults.sh

threshold=$2
process=$1

#set path
export alienx="$DIR/alienx"

#commands for start/stop newzdash tracking
export ds1="cd $alienx && $PHP tmux_to_newzdash.php";
export ds4="killed";



time=($(ps -eao "%C %U %c %t" | awk "/$process/"'{print $4}' | awk -F":" '{{a=$1*60} {b=a+$2}; if ( NF != 2 ) print b ; else print $1 }'))
real_time=($(ps -eao "%C %U %c %t" | awk "/$process/"'{print $4}'))
pid=($(ps -eao "%P %C %U %c %t" | awk "/$process/"'{print $1}'))

i=0
for proc in "${time[@]}"
do
  if [ $proc -ge $threshold ]; then
    if [ ${pid[$i]} -gt "1000" ]; then
      echo "\033[1;31m $process pid ${pid[$i]} has been running for ${real_time[$i]} and will be killed.\033[0m"
      kill -s 9 ${pid[$i]} && $ds1 $ds4
    fi
  fi
  i=$i+1
done

