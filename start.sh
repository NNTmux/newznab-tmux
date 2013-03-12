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

# Make sure only root can run our script
#if [[ $EUID -ne 0 ]]; then
    #echo "This script must be run as root"
    #This was removed by popular request, so don't complain
    #exit 1
#fi

source config.sh
source defaults.sh

eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

if [[ $AGREED == "no" ]]; then
    echo "Please edit the defaults.sh file"
    exit
fi

#check if tmux session exists, attach if exists, create new if not exist
if $TMUXCMD -q has-session -t $TMUX_SESSION; then
    $TMUXCMD attach-session -t $TMUX_SESSION
else
    printf "The above is just a TMUX notice, it is saying TMUX, that you do not have a TMUX session currently running. It is not an error. It is TMUX"
    printf "\033]0; $TMUX_SESSION\007\003\n"
    $TMUXCMD -f $TMUX_CONF new-session -d -s $TMUX_SESSION -n Monitor 'printf "\033]2;Monitor\033\\" && cd bin && echo "Monitor Started" && echo "It might take a minute for everything to spinup......" && $NICE -n$NICENESS $PHP monitor.php'

    if [ ! -f $NEWZPATH/www/lib/postprocess.php.orig ]; then
        cp $NEWZPATH/www/lib/postprocess.php $NEWZPATH/www/lib/postprocess.php.orig
    fi
    if ! grep -q '//$this->processAdditional();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processAdditional();/\/\/$this->processAdditional();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processNfos();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processNfos();/\/\/$this->processNfos();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processSpotNab();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processSpotNab();/\/\/$this->processSpotNab();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processUnwanted();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processUnwanted();/\/\/$this->processUnwanted();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processMovies();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processMovies();/\/\/$this->processMovies();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processMusic();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processMusic();/\/\/$this->processMusic();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processBooks();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processBooks();/\/\/$this->processBooks();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processGames();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processGames();/\/\/$this->processGames();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processTv();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processTv();/\/\/$this->processTv();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processMusicFromMediaInfo();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processMusicFromMediaInfo();/\/\/$this->processMusicFromMediaInfo();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processOtherMiscCategory();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processOtherMiscCategory();/\/\/$this->processOtherMiscCategory();/' $NEWZPATH/www/lib/postprocess.php
    fi
    if ! grep -q '//$this->processUnknownCategory();' "$NEWZPATH/www/lib/postprocess.php" ; then
        $SED -i -e 's/$this->processUnknownCategory();/\/\/$this->processUnknownCategory();/' $NEWZPATH/www/lib/postprocess.php
    fi

    #create mysql my.conf
    #this keeps your password from being displayed in ps, htop and others
    echo -e '[client]\npassword='\"$DB_PASSWORD\" > ./conf/my.cnf
    chmod 600 ./conf/my.cnf

    #create powerline default.sh
    if [ ! -f "powerline/powerline/themes/default.sh" ]; then
        cp powerline/powerline/themes/default.start.sh powerline/powerline/themes/default.sh
    fi

    #Get the path to tmpunrar
    TMPUNRAR_QUERY="SELECT value from site where setting = \"tmpunrarpath\";"
    TMPUNRAR_PATH=`$MYSQL --defaults-file=conf/my.cnf -u$DB_USER -h$DB_HOST $DB_NAME -s -N -e "${TMPUNRAR_QUERY}"`
    TMPUNRAR_PATH=$TMPUNRAR_PATH"1"

    #determine if ramdisk is in fstab
    if [[ $RAMDISK == "true" ]]; then
        if [[ `grep "$TMPUNRAR_PATH" /etc/fstab` ]]; then
            if [[ ! `mount | grep "$TMPUNRAR_PATH"` ]]; then
                mount "$TMPUNRAR_PATH"
            fi
        fi
    elif [[ $RAMDISK == "true" ]]; then
        if [[ ! `mount | grep "$TMPUNRAR_PATH"` ]]; then
            mount -t tmpfs -o size=256M tmpfs $TMPUNRAR_PATH
        fi
    fi

    #remove postprocessing scripts
    rm -f bin/lib/post*
    rm -f bin/lib/book.php
    rm -f bin/lib/console.php
    rm -f bin/lib/movie.php
    rm -f bin/lib/music.php
    rm -f bin/lib/music1.php
    rm -f bin/lib/nfo.php
    rm -f bin/lib/tvrage.php
    rm -f bin/processAdditional*
    rm -f bin/processAlternate*

    #create postprocessing scripts
    for (( c=2; c<=32; c++ ))
    do
        d=$((($c - 1) * 100))
        cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess$c.php
        $SED -i -e "s/PostProcess/PostProcess$c/g" bin/lib/postprocess$c.php
        $SED -i -e "s/echo \$iteration.*$/echo \$iteration --.\"    \".\$rel['ID'].\" : \".\$rel['name'].\"\\\n\";/" bin/lib/postprocess$c.php
        $SED -i -e "s/processAdditional/processAdditional$c/g" bin/lib/postprocess$c.php
        $SED -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
                        \$tmpPath .= '1\/tmp$c';/g" bin/lib/postprocess$c.php
        $SED -i -e "s/order by r.postdate desc limit %d.*\$/order by r.guid asc limit %d, %d \", (\$maxattemptstocheckpassworded + 1) * -1, $c * \$numtoProcess, \$numtoProcess));/g" bin/lib/postprocess$c.php
        $SED -i -e "s/PostPrc : Performing additional post processing.*\$/PostPrc : Performing additional post processing by guid on \".\$rescount.\" releases, starting at $d ...\";/g" bin/lib/postprocess$c.php

        cp bin/lib/additional bin/processAdditional$c.php
        $SED -i -e "s/1/$c/g" bin/processAdditional$c.php
    done

    cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess1.php
    cp bin/lib/additional bin/processAdditional1.php

    #edit postprocessing scripts
    $SED -i -e 's/PostProcess/PostProcess1/g' bin/lib/postprocess1.php
    $SED -i -e "s/echo \$iteration.*$/echo \$iteration --.\"    \".\$rel['ID'].\" : \".\$rel['name'].\"\\\n\";/" bin/lib/postprocess1.php
    $SED -i -e 's/processAdditional/processAdditional1/g' bin/lib/postprocess1.php
    $SED -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
                    \$tmpPath .= '1\/tmp1';/g" bin/lib/postprocess1.php
    $SED -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid desc limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess));/g' bin/lib/postprocess1.php
    $SED -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...";/g' bin/lib/postprocess1.php


    cp -f $NEWZPATH/www/lib/nfo.php bin/lib/nfo.php
    #cp -f $NEWZPATH/www/lib/tvrage.php bin/lib/tvrage.php
    cp -f $NEWZPATH/www/lib/movie.php bin/lib/movie.php
    cp -f $NEWZPATH/www/lib/music.php bin/lib/music.php
    cp -f $NEWZPATH/www/lib/music.php bin/lib/music1.php
    cp -f $NEWZPATH/www/lib/console.php bin/lib/console.php
    cp -f $NEWZPATH/www/lib/book.php bin/lib/book.php

    $SED -i -e "s/500/250/" bin/lib/postprocess1.php
    $SED -i -e "s/500/250/" bin/lib/postprocess2.php

    $SED -i -e "s/class Nfo/class Nfo1/" bin/lib/nfo.php
    #$SED -i -e "s/class TvRage/class TvRage1/" bin/lib/tvrage.php
    $SED -i -e "s/class Movie/class Movie1/" bin/lib/movie.php
    $SED -i -e "s/class Music/class Music1/" bin/lib/music.php
    $SED -i -e "s/class Music/class Music2/" bin/lib/music1.php
    $SED -i -e "s/class Console/class Console1/" bin/lib/console.php
    $SED -i -e "s/class Book/class Book1/" bin/lib/book.php

    $SED -i -e "s/function Nfo/function Nfo1/" bin/lib/nfo.php
    #$SED -i -e "s/function TvRage/function TvRage1/" bin/lib/tvrage.php
    $SED -i -e "s/function Movie/function Movie1/" bin/lib/movie.php
    $SED -i -e "s/function Music/function Music1/" bin/lib/music.php
    $SED -i -e "s/function Music/function Music2/" bin/lib/music1.php
    $SED -i -e "s/function Console/function Console1/" bin/lib/console.php
    $SED -i -e "s/function Book/function Book1/" bin/lib/book.php

    $SED -i -e "s/processNfoFiles/processNfoFiles1/" bin/lib/nfo.php
    $SED -i -e "s/processMovieReleases/processMovieReleases1/" bin/lib/movie.php
    $SED -i -e "s/processMusicReleases/processMusicReleases1/" bin/lib/music.php
    $SED -i -e "s/processMusicReleases/processMusicReleases2/" bin/lib/music1.php
    $SED -i -e "s/processBookReleases/processBookReleases1/" bin/lib/book.php
    $SED -i -e "s/processConsoleReleases/processConsoleReleases1/" bin/lib/console.php

    $SED -i -e "s/nfoHandleError/nfoHandleError1/" bin/lib/nfo.php
    $SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" bin/lib/nfo.php

    $SED -i -e 's/WWW_DIR."\/lib\/nfo.php"/"nfo.php"/g' bin/lib/postprocess2.php
    $SED -i -e 's/WWW_DIR."\/lib\/movie.php"/"movie.php"/g' bin/lib/postprocess2.php
    $SED -i -e 's/WWW_DIR."\/lib\/music.php"/"music.php"/g' bin/lib/postprocess1.php
    $SED -i -e 's/WWW_DIR."\/lib\/music.php"/"music1.php"/g' bin/lib/postprocess2.php
    $SED -i -e 's/WWW_DIR."\/lib\/console.php"/"console.php"/g' bin/lib/postprocess2.php
    $SED -i -e 's/WWW_DIR."\/lib\/book.php"/"book.php"/g' bin/lib/postprocess2.php
    #$SED -i -e 's/WWW_DIR."\/lib\/tvrage.php"/"tvrage.php"/g' bin/lib/postprocess2.php

    $SED -i -e "s/processNfos()/processNfos1()/g" bin/lib/postprocess2.php
    $SED -i -e "s/processMovies()/processMovies1()/g" bin/lib/postprocess2.php
    $SED -i -e "s/processMusic()/processMusic1()/g" bin/lib/postprocess1.php
    $SED -i -e "s/processMusic()/processMusic2()/g" bin/lib/postprocess2.php
    $SED -i -e "s/processBooks()/processBooks1()/g" bin/lib/postprocess2.php
    $SED -i -e "s/processGames()/processGames1()/g" bin/lib/postprocess2.php
    $SED -i -e "s/processTv()/processTv1()/g" bin/lib/postprocess2.php

    $SED -i -e "s/new Nfo/new Nfo1/" bin/lib/postprocess2.php
    $SED -i -e "s/new Movie/new Movie1/" bin/lib/postprocess2.php
    $SED -i -e "s/new Music/new Music1/" bin/lib/postprocess1.php
    $SED -i -e "s/new Music/new Music2/" bin/lib/postprocess2.php
    $SED -i -e "s/new Book/new Book1/" bin/lib/postprocess2.php
    $SED -i -e "s/new Console/new Console1/" bin/lib/postprocess2.php

    $SED -i -e "s/processNfoFiles/processNfoFiles1/" bin/lib/postprocess2.php
    $SED -i -e "s/processMovieReleases()/processMovieReleases1()/" bin/lib/postprocess2.php
    $SED -i -e "s/processMusicReleases()/processMusicReleases1()/" bin/lib/postprocess1.php
    $SED -i -e "s/processMusicReleases()/processMusicReleases2()/" bin/lib/postprocess2.php
    $SED -i -e "s/processBookReleases()/processBookReleases1()/" bin/lib/postprocess2.php
    $SED -i -e "s/processConsoleReleases()/processConsoleReleases1()/" bin/lib/postprocess2.php

    $SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" bin/lib/nfo.php
    $SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" bin/lib/movie.php
    $SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate ASC/" bin/lib/movie.php
    $SED -i -e "s/ORDER BY postdate DESC LIMIT 1000/ORDER BY postdate DESC LIMIT 100/" bin/lib/music.php
    $SED -i -e "s/ORDER BY postdate DESC LIMIT 1000/ORDER BY postdate ASC LIMIT 100/" bin/lib/music1.php
    $SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate DESC/" bin/lib/music.php
    $SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate ASC/" bin/lib/music1.php
    $SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" bin/lib/book.php
    $SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" bin/lib/console.php
    $SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate ASC/" bin/lib/console.php

#    $SED -i -e "s/order by postdate desc/ORDER BY postdate ASC/" bin/lib/tvrage.php
#    $SED -i -e "s/order by rageID asc/order by rageID DESC/" bin/lib/tvrage.php
#    $SED -i -e "s/order by airdate asc/order by airdate DESC/" bin/lib/tvrage.php
#    $SED -i -e "s/order by tvrage.releasetitle asc/order by tvrage.releasetitle DESC/" bin/lib/tvrage.php


    #start tmux
    #printf "\033]0; $TMUX_SESSION\007\003\n"
    #$TMUXCMD -f $TMUX_CONF attach-session - $TMUX_SESSION || new-session -d -s $TMUX_SESSION -n $TMUX_SESSION 'cd bin && echo "Monitor Started" && echo "It might take a minute for everything to spinup......" && $NICE -n 19 $PHP monitor.php'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -h -p 67 'printf "\033]2;update_binaries\033\\"'

    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 40 'printf "\033]2;nzbcount\033\\"'

    $TMUXCMD selectp -t 2
    $TMUXCMD splitw -v -p 75 'printf "\033]2;backfill\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;import-nzb\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;update_releases\033\\"'

    $TMUXCMD new-window -n other 'printf "\033]2;update_predb\033\\"'
    $TMUXCMD splitw -h -p 50 'printf "\033]2;optimise\033\\"'
    $TMUXCMD selectp -t 0
    $TMUXCMD splitw -v -p 75 'printf "\033]2;update_parsing\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;update_cleanup\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;update_tv\033\\"'
    $TMUXCMD selectp -t 4
    $TMUXCMD splitw -v -p 75 'printf "\033]2;sphinx\033\\"'
    $TMUXCMD splitw -v -p 67 'printf "\033]2;delete_parts\033\\"'
    $TMUXCMD splitw -v -p 50 'printf "\033]2;update_missing_movie_info\033\\"'

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
        $TMUXCMD new-window -n mytop '$MYTOP -u $DB_USER -p $DB_PASSWORD -d $DB_NAME -h $DB_HOST'
    fi

    if [[ $USE_VNSTAT == "true" ]]; then
        $TMUXCMD new-window -n vnstat 'watch $VNSTAT'
    fi

    if [[ $USE_IFTOP == "true" ]]; then
        $TMUXCMD new-window -n iftop '$IFTOP -i $INTERFACE'
    fi

    if [[ $USE_ATOP == "true" ]]; then
        $TMUXCMD new-window -n atop '$ATOP'
    fi

    if [[ $USE_CONSOLE == "true" ]]; then
        $TMUXCMD new-window -n Console 'bash -i'
    fi

    $TMUXCMD select-window -t$TMUX_SESSION:0
    $TMUXCMD attach-session -d -t$TMUX_SESSION

fi
exit

