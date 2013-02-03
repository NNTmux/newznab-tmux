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

# Make sure only root can run our script
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

source ../defaults.sh
clear

if [[ $AGREED == "no" ]]; then
        echo "Please edit the defaults.sh file"
        exit
fi

echo "Fixing permisions, this can take some time if you have a large set of releases"
sudo chmod 777 $NEWZPATH/www/lib/smarty/templates_c
sudo chmod -R 777 $NEWZPATH/www/covers
sudo chmod 777 $NEWZPATH/www
sudo chmod 777 $NEWZPATH/www/install

echo -e "\033[38;5;160mCompleted\033[39m"

echo -e "\033[1;33m\n\nIf the nmon, bwg-nm windows close when you select networking, then you will need to  use sudo or su."
echo -e "Tmux is very easy to use. To detach from the current session, use Ctrl-a d. You can select"
echo -e "simply by clicking in it and you can resize by dragging the borders."
echo -e "To reattach to a running session, tmux att."
echo -e "To navigate between panes, Ctrl-a q then the number of the pane."
echo -e "To navigate between windows, Ctrl-a then the number of the window."
echo -e "To create a new window, Ctrl-a c \n\n\033[0m"
exit

