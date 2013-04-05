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

source config.sh
source defaults.sh

eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

    if [ -f user_scripts/$USER_DEF_ONE ]; then
	cd user_scripts && ./$USER_DEF_ONE
	cd ../
    fi

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
    echo "[client]" > ./conf/my.cnf
    echo "password=$DB_PASSWORD" >> ./conf/my.cnf
    chmod 600 ./conf/my.cnf

    #create powerline tmux.sh
    if [ ! -f "powerline/powerline/themes/tmux.sh" ]; then
        cp powerline/powerline/themes/default.sh powerline/powerline/themes/tmux.sh
    fi

    #Get the path to tmpunrar
    TMPUNRAR_QUERY="SELECT value from site where setting = \"tmpunrarpath\";"
    TMPUNRAR_PATH=`$MYSQL --defaults-file=conf/my.cnf -u$DB_USER -h$DB_HOST $DB_NAME -s -N -e "${TMPUNRAR_QUERY}"`
    TMPUNRAR_PATH=$TMPUNRAR_PATH"1"

    if [ ! -d "$TMPUNRAR_PATH" ]; then
        mkdir -p $TMPUNRAR_PATH
    fi

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
    rm -f bin/lib/nntp2.php

    #create postprocessing scripts
    for (( c=2; c<=16; c++ ))
    do
        d=$((($c - 1) * 100))
        cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess$c.php
        $SED -i -e "s/PostProcess/PostProcess$c/g" bin/lib/postprocess$c.php
        $SED -i -e "s/echo \$iteration.*$/echo \$iteration --.\"    \".\$rel['ID'].\" : \".\$rel['name'].\"\\\n\";/" bin/lib/postprocess$c.php
        $SED -i -e "s/processAdditional/processAdditional$c/g" bin/lib/postprocess$c.php
        $SED -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
                        \$tmpPath .= '1\/tmp$c';/g" bin/lib/postprocess$c.php
        $SED -i -e "s/order by r.postdate desc limit %d.*\$/order by r.guid asc limit %d, %d \", (\$maxattemptstocheckpassworded + 1) * -1, $c * 100, \$numtoProcess));/g" bin/lib/postprocess$c.php
        $SED -i -e "s/PostPrc : Performing additional post processing.*\$/PostPrc : Performing additional post processing by guid on \".\$rescount.\" releases, starting at $d ...\\n\";/g" bin/lib/postprocess$c.php
	$SED -i -e "s/\/\/echo \"PostPrc : Fetching/echo \"PostPrc : Fetching/g" bin/lib/postprocess$c.php
	if [[ $USE_TWO_NNTP == "true" ]] && [[ $USE_TWO_PP != "true" ]]; then
		$SED -i -e "s/require_once(WWW_DIR.\"\/lib\/nntp.php\");/require(dirname(__FILE__).\"\/nntp2.php\");/g" bin/lib/postprocess$c.php
        	$SED -i -e "s/new Nntp;/new GetConnected;/g" bin/lib/postprocess$c.php
	        $SED -i -e "s/doConnect/doConnect2/g" bin/lib/postprocess$c.php
	fi

        cp bin/lib/additional bin/processAdditional$c.php
        $SED -i -e "s/1/$c/g" bin/processAdditional$c.php
        $SED -i -e "s/$numtoProcess = 100;/$numtoProcess = 20;/g" bin/lib/postprocess$c.php
    done

    for (( c=17; c<=32; c++ ))
    do
        d=$((($c - 1) * 100))
        cp $NEWZPATH/www/lib/postprocess.php bin/lib/postprocess$c.php
        $SED -i -e "s/PostProcess/PostProcess$c/g" bin/lib/postprocess$c.php
        $SED -i -e "s/echo \$iteration.*$/echo \$iteration --.\"    \".\$rel['ID'].\" : \".\$rel['name'].\"\\\n\";/" bin/lib/postprocess$c.php
        $SED -i -e "s/processAdditional/processAdditional$c/g" bin/lib/postprocess$c.php
        $SED -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
                        \$tmpPath .= '1\/tmp$c';/g" bin/lib/postprocess$c.php
        $SED -i -e "s/order by r.postdate desc limit %d.*\$/order by r.guid asc limit %d, %d \", (\$maxattemptstocheckpassworded + 1) * -1, ($c + 16) * 100, \$numtoProcess));/g" bin/lib/postprocess$c.php
        $SED -i -e "s/PostPrc : Performing additional post processing.*\$/PostPrc : Performing additional post processing by guid on \".\$rescount.\" releases, starting at $d ...\\n\";/g" bin/lib/postprocess$c.php
        $SED -i -e "s/\/\/echo \"PostPrc : Fetching/echo \"PostPrc : Fetching/g" bin/lib/postprocess$c.php
        if [[ $USE_TWO_NNTP == "true" ]] && [[ $USE_TWO_PP == "true" ]]; then
                $SED -i -e "s/require_once(WWW_DIR.\"\/lib\/nntp.php\");/require(dirname(__FILE__).\"\/nntp2.php\");/g" bin/lib/postprocess$c.php
        	$SED -i -e "s/new Nntp;/new GetConnected;/g" bin/lib/postprocess$c.php
	        $SED -i -e "s/doConnect/doConnect2/g" bin/lib/postprocess$c.php
        fi

        cp bin/lib/additional bin/processAdditional$c.php
        $SED -i -e "s/1/$c/g" bin/processAdditional$c.php
        $SED -i -e "s/$numtoProcess = 100;/$numtoProcess = 20;/g" bin/lib/postprocess$c.php
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
    $SED -i -e 's/PostPrc : Performing additional post processing.*$/PostPrc : Performing additional post processing by guid on ".$rescount." releases ...\\n";/g' bin/lib/postprocess1.php
    $SED -i -e "s/\/\/echo \"PostPrc : Fetching/echo \"PostPrc : Fetching/g" bin/lib/postprocess1.php
    $SED -i -e "s/$numtoProcess = 100;/$numtoProcess = 20;/g" bin/lib/postprocess1.php
    if [[ $USE_TWO_NNTP == "true" ]] && [[ $USE_TWO_PP != "true" ]]; then
	    $SED -i -e "s/require_once(WWW_DIR.\"\/lib\/nntp.php\");/require(dirname(__FILE__).\"\/nntp2.php\");/g" bin/lib/postprocess1.php
            $SED -i -e "s/new Nntp;/new GetConnected;/g" bin/lib/postprocess1.php
            $SED -i -e "s/doConnect/doConnect2/g" bin/lib/postprocess1.php
    fi


    cp -f $NEWZPATH/www/lib/nfo.php bin/lib/nfo.php
    #cp -f $NEWZPATH/www/lib/tvrage.php bin/lib/tvrage.php
    cp -f $NEWZPATH/www/lib/movie.php bin/lib/movie.php
    cp -f $NEWZPATH/www/lib/music.php bin/lib/music.php
    cp -f $NEWZPATH/www/lib/music.php bin/lib/music1.php
    cp -f $NEWZPATH/www/lib/console.php bin/lib/console.php
    cp -f $NEWZPATH/www/lib/book.php bin/lib/book.php
    cp -f $NEWZPATH/www/lib/nntp.php bin/lib/nntp2.php


    $SED -i -e "s/function doConnect/function doConnect2/" bin/lib/nntp2.php
    $SED -i -e "s/NNTP_USERNAME/NNTP_USERNAME2/g" bin/lib/nntp2.php
    $SED -i -e "s/NNTP_PASSWORD/NNTP_PASSWORD2/g" bin/lib/nntp2.php
    $SED -i -e "s/NNTP_SERVER/NNTP_SERVER2/g" bin/lib/nntp2.php
    $SED -i -e "s/NNTP_PORT/NNTP_PORT2/g" bin/lib/nntp2.php
    $SED -i -e "s/NNTP_SSLENABLED/NNTP_SSLENABLED2/g" bin/lib/nntp2.php
    $SED -i -e "s/NNTPException/NNTPException2/g" bin/lib/nntp2.php
    $SED -i -e "s/class Nntp/class GetConnected/g" bin/lib/nntp2.php

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


