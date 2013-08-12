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

source defaults.sh
$SED -i -e 's/export finished="true"/export finished="false"/' defaults.sh

eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

if [ -f user_scripts/$USER_DEF_ONE ]; then
	cd $DIR/user_scripts && ./$USER_DEF_ONE
	cd $DIR
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


#edit powerprocess.php
if [[ $FIX_POSIX  == "true" ]]; then
	$SED -i -e 's/case SIGSTKFLT:/\/\/case SIGSTKFLT:/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/return 'SIGSTKFLT';/\/\/return 'SIGSTKFLT';/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/case SIGCLD:/\/\/case SIGCLD:/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/return 'SIGCLD';/\/\/return 'SIGCLD';/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/case SIGPOLL:/\/\/case SIGPOLL:/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/return 'SIGPOLL';/\/\/return 'SIGPOLL';/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/case SIGPWR:/\/\/case SIGPWR:/' $NEWZPATH/www/lib/powerprocess.php
	$SED -i -e 's/return 'SIGPWR';/\/\/return 'SIGPWR';/' $NEWZPATH/www/lib/powerprocess.php
fi

#attempt to get english only from IMDB
if [[ $EN_IMDB == "true" ]]; then
	$SED -i -e 's/akas.imdb/www.imdb/g' $NEWZPATH/www/lib/movie.php
	$SED -i -e 's/curl_setopt($ch, CURLOPT_URL, $url);/curl_setopt($ch, CURLOPT_URL, $url);\
	$header[] = "Accept-Language: en-us";\
	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);/' $NEWZPATH/www/lib/util.php
fi

#import kevin123's category.php and backfill.php
if [[ $KEVIN_SAFER == "true" || $KEVIN_BACKFILL_PARTS == "true"  || $KEVIN_THREADED == "true" ]]; then
        cd $DIR"/kevin123"
        cp -f backfill.php $NEWZPATH/www/lib/
        cd $DIR
fi

#create mysql my.conf
#this keeps your password from being displayed in ps, htop and others
echo "[client]" > $DIR/conf/my.cnf
echo "password=$DB_PASSWORD" >> $DIR/conf/my.cnf
chmod 600 $DIR/conf/my.cnf

#create powerline tmux.sh
if [ ! -f "$DIR/powerline/powerline/themes/tmux.sh" ]; then
	cp $DIR/powerline/powerline/themes/default.sh powerline/powerline/themes/tmux.sh
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
rm -f $DIR/bin/lib/post*
rm -f $DIR/bin/lib/book.php
rm -f $DIR/bin/lib/console.php
rm -f $DIR/bin/lib/movie.php
rm -f $DIR/bin/lib/music.php
rm -f $DIR/bin/lib/music1.php
rm -f $DIR/bin/lib/nfo.php
rm -f $DIR/bin/lib/tvrage.php
rm -f $DIR/bin/processAdditional*
rm -f $DIR/bin/processAlternate*
rm -f $DIR/bin/lib/nntp2.php
rm -f $DIR/bin/temp/*

#create postprocessing scripts
for (( c=1; c<=16; c++ ))
do
	d=$((($c - 1) * 100))
	cp $NEWZPATH/www/lib/postprocess.php $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/PostProcess/PostProcess$c/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/echo \$iteration.*$/echo \$iteration --.\"    \".\$rel['ID'].\" : \".\$rel['name'].\"\\\n\";/" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/processAdditional/processAdditional$c/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
		\$tmpPath .= '1\/tmp$c';/g" $DIR/bin/temp/postprocess$c.php
	if [[ $c -eq 1 ]]; then
		$SED -i -e 's/order by r.postdate desc limit %d.*$/order by r.guid desc limit %d ", ($maxattemptstocheckpassworded + 1) * -1, $numtoProcess);/g' $DIR/bin/temp/postprocess$c.php
	else
		$SED -i -e "s/order by r.postdate desc limit %d.*\$/order by r.guid asc limit %d, %d \", (\$maxattemptstocheckpassworded + 1) * -1, $c * 100, \$numtoProcess);/g" $DIR/bin/temp/postprocess$c.php
	fi
	$SED -i -e "s/PostPrc : Performing additional post processing.*\$/PostPrc : Performing additional post processing by guid on \".\$rescount.\" releases, starting at $d ...\\n\";/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/\/\/echo \"PostPrc : Fetching/echo \"PostPrc : Fetching/g" $DIR/bin/temp/postprocess$c.php
	if [[ $USE_TWO_NNTP == "true" ]] && [[ $USE_TWO_PP != "true" ]]; then
		$SED -i -e "s/require_once(WWW_DIR.\"\/lib\/nntp.php\");/require(dirname(__FILE__).\"\/nntp2.php\");/g" $DIR/bin/temp/postprocess$c.php
		$SED -i -e "s/new Nntp;/new GetConnected;/g" $DIR/bin/temp/postprocess$c.php
		$SED -i -e "s/doConnect/doConnect2/g" $DIR/bin/temp/postprocess$c.php
	fi
	cp $DIR/bin/lib/additional $DIR/bin/temp/processAdditional$c.php
	$SED -i -e "s/1/$c/g" $DIR/bin/temp/processAdditional$c.php
	$SED -i -e "s/$numtoProcess = 100;/$numtoProcess = 20;/g" $DIR/bin/temp/postprocess$c.php
done

for (( c=17; c<=32; c++ ))
do
	d=$((($c - 1) * 100))
	cp $NEWZPATH/www/lib/postprocess.php $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/PostProcess/PostProcess$c/g" $DIR/bin/temp/postprocess$c.php 
	$SED -i -e "s/echo \$iteration.*$/echo \$iteration --.\"    \".\$rel['ID'].\" : \".\$rel['name'].\"\\\n\";/" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/processAdditional/processAdditional$c/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/\$tmpPath = \$this->site->tmpunrarpath;/\$tmpPath = \$this->site->tmpunrarpath; \\
		\$tmpPath .= '1\/tmp$c';/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/order by r.postdate desc limit %d.*\$/order by r.guid asc limit %d, %d \", (\$maxattemptstocheckpassworded + 1) * -1, ($c + 16) * 100, \$numtoProcess);/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/PostPrc : Performing additional post processing.*\$/PostPrc : Performing additional post processing by guid on \".\$rescount.\" releases, starting at $d ...\\n\";/g" $DIR/bin/temp/postprocess$c.php
	$SED -i -e "s/\/\/echo \"PostPrc : Fetching/echo \"PostPrc : Fetching/g" $DIR/bin/temp/postprocess$c.php
	if [[ $USE_TWO_NNTP == "true" ]] && [[ $USE_TWO_PP == "true" ]]; then
		$SED -i -e "s/require_once(WWW_DIR.\"\/lib\/nntp.php\");/require(dirname(__FILE__).\"\/nntp2.php\");/g" $DIR/bin/temp/postprocess$c.php
		$SED -i -e "s/new Nntp;/new GetConnected;/g" $DIR/bin/temp/postprocess$c.php
		$SED -i -e "s/doConnect/doConnect2/g" $DIR/bin/temp/postprocess$c.php
	fi
	cp $DIR/bin/lib/additional $DIR/bin/temp/processAdditional$c.php
	$SED -i -e "s/1/$c/g" $DIR/bin/temp/processAdditional$c.php
	$SED -i -e "s/$numtoProcess = 100;/$numtoProcess = 20;/g" $DIR/bin/temp/postprocess$c.php
done

cp -f $NEWZPATH/www/lib/nfo.php $DIR/bin/temp/nfo.php
cp -f $NEWZPATH/www/lib/tvrage.php $DIR/bin/temp/tvrage.php
cp -f $NEWZPATH/www/lib/movie.php $DIR/bin/temp/movie.php
cp -f $NEWZPATH/www/lib/music.php $DIR/bin/temp/music.php
cp -f $NEWZPATH/www/lib/music.php $DIR/bin/temp/music1.php
cp -f $NEWZPATH/www/lib/console.php $DIR/bin/temp/console.php
cp -f $NEWZPATH/www/lib/book.php $DIR/bin/temp/book.php
cp -f $NEWZPATH/www/lib/nntp.php $DIR/bin/temp/nntp2.php


$SED -i -e "s/function doConnect/function doConnect2/" $DIR/bin/temp/nntp2.php
$SED -i -e "s/NNTP_USERNAME/NNTP_USERNAME2/g" $DIR/bin/temp/nntp2.php
$SED -i -e "s/NNTP_PASSWORD/NNTP_PASSWORD2/g" $DIR/bin/temp/nntp2.php
$SED -i -e "s/NNTP_SERVER/NNTP_SERVER2/g" $DIR/bin/temp/nntp2.php
$SED -i -e "s/NNTP_PORT/NNTP_PORT2/g" $DIR/bin/temp/nntp2.php
$SED -i -e "s/NNTP_SSLENABLED/NNTP_SSLENABLED2/g" $DIR/bin/temp/nntp2.php
$SED -i -e "s/NNTPException/NNTPException2/g" $DIR/bin/temp/nntp2.php
$SED -i -e "s/class Nntp/class GetConnected/g" $DIR/bin/temp/nntp2.php

$SED -i -e "s/500/250/" $DIR/bin/temp/postprocess1.php
$SED -i -e "s/500/250/" $DIR/bin/temp/postprocess2.php

$SED -i -e "s/class Nfo/class Nfo1/" $DIR/bin/temp/nfo.php
$SED -i -e "s/class TvRage/class TvRage1/" $DIR/bin/temp/tvrage.php
$SED -i -e "s/class Movie/class Movie1/" $DIR/bin/temp/movie.php
$SED -i -e "s/class Music/class Music1/" $DIR/bin/temp/music.php
$SED -i -e "s/class Music/class Music2/" $DIR/bin/temp/music1.php
$SED -i -e "s/class Console/class Console1/" $DIR/bin/temp/console.php
$SED -i -e "s/class Book/class Book1/" $DIR/bin/temp/book.php

$SED -i -e "s/function Nfo/function Nfo1/" $DIR/bin/temp/nfo.php
$SED -i -e "s/function TvRage/function TvRage1/" $DIR/bin/temp/tvrage.php
$SED -i -e "s/function Movie/function Movie1/" $DIR/bin/temp/movie.php
$SED -i -e "s/function Music/function Music1/" $DIR/bin/temp/music.php
$SED -i -e "s/function Music/function Music2/" $DIR/bin/temp/music1.php
$SED -i -e "s/function Console/function Console1/" $DIR/bin/temp/console.php
$SED -i -e "s/function Book/function Book1/" $DIR/bin/temp/book.php

$SED -i -e "s/processNfoFiles/processNfoFiles1/" $DIR/bin/temp/nfo.php
$SED -i -e "s/processMovieReleases/processMovieReleases1/" $DIR/bin/temp/movie.php
$SED -i -e "s/processMusicReleases/processMusicReleases1/" $DIR/bin/temp/music.php
$SED -i -e "s/processMusicReleases/processMusicReleases2/" $DIR/bin/temp/music1.php
$SED -i -e "s/processBookReleases/processBookReleases1/" $DIR/bin/temp/book.php
$SED -i -e "s/processConsoleReleases/processConsoleReleases1/" $DIR/bin/temp/console.php

$SED -i -e "s/nfoHandleError/nfoHandleError1/" $DIR/bin/temp/nfo.php
$SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" $DIR/bin/temp/nfo.php

$SED -i -e 's/WWW_DIR."\/lib\/nfo.php"/"nfo.php"/g' $DIR/bin/temp/postprocess2.php
$SED -i -e 's/WWW_DIR."\/lib\/movie.php"/"movie.php"/g' $DIR/bin/temp/postprocess2.php
$SED -i -e 's/WWW_DIR."\/lib\/music.php"/"music.php"/g' $DIR/bin/temp/postprocess1.php
$SED -i -e 's/WWW_DIR."\/lib\/music.php"/"music1.php"/g' $DIR/bin/temp/postprocess2.php
$SED -i -e 's/WWW_DIR."\/lib\/console.php"/"console.php"/g' $DIR/bin/temp/postprocess2.php
$SED -i -e 's/WWW_DIR."\/lib\/book.php"/"book.php"/g' $DIR/bin/temp/postprocess2.php
$SED -i -e 's/WWW_DIR."\/lib\/tvrage.php"/"tvrage.php"/g' $DIR/bin/temp/postprocess2.php

$SED -i -e "s/processNfos()/processNfos1()/g" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processMovies()/processMovies1()/g" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processMusic()/processMusic1()/g" $DIR/bin/temp/postprocess1.php
$SED -i -e "s/processMusic()/processMusic2()/g" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processBooks()/processBooks1()/g" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processGames()/processGames1()/g" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processTv()/processTv1()/g" $DIR/bin/temp/postprocess2.php

$SED -i -e "s/new Nfo(/new Nfo1(/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/new Movie/new Movie1/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/new Music/new Music1/" $DIR/bin/temp/postprocess1.php
$SED -i -e "s/new Music/new Music2/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/new Book/new Book1/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/new Console/new Console1/" $DIR/bin/temp/postprocess2.php

$SED -i -e "s/processNfoFiles/processNfoFiles1/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processMovieReleases()/processMovieReleases1()/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processMusicReleases()/processMusicReleases1()/" $DIR/bin/temp/postprocess1.php
$SED -i -e "s/processMusicReleases()/processMusicReleases2()/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processBookReleases()/processBookReleases1()/" $DIR/bin/temp/postprocess2.php
$SED -i -e "s/processConsoleReleases()/processConsoleReleases1()/" $DIR/bin/temp/postprocess2.php

$SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" $DIR/bin/temp/nfo.php
$SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" $DIR/bin/temp/movie.php
$SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate ASC/" $DIR/bin/temp/movie.php
$SED -i -e "s/ORDER BY postdate DESC LIMIT 1000/ORDER BY postdate DESC LIMIT 100/" $DIR/bin/temp/music.php
$SED -i -e "s/ORDER BY postdate DESC LIMIT 1000/ORDER BY postdate ASC LIMIT 100/" $DIR/bin/temp/music1.php
$SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate DESC/" $DIR/bin/temp/music.php
$SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate ASC/" $DIR/bin/temp/music1.php
$SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" $DIR/bin/temp/book.php
$SED -i -e "s/ORDER BY postdate DESC/ORDER BY postdate ASC/" $DIR/bin/temp/console.php
$SED -i -e "s/ORDER BY createddate DESC/ORDER BY createddate ASC/" $DIR/bin/temp/console.php

#$SED -i -e "s/order by postdate desc/ORDER BY postdate ASC/" $DIR/bin/temp/tvrage.php
#$SED -i -e "s/order by rageID asc/order by rageID DESC/" $DIR/bin/temp/tvrage.php
#$SED -i -e "s/order by airdate asc/order by airdate DESC/" $DIR/bin/temp/tvrage.php
#$SED -i -e "s/order by tvrage.releasetitle asc/order by tvrage.releasetitle DESC/" $DIR/bin/temp/tvrage.php


LINE=`grep -Hnm 1 '$this->rarfileregex = ' ../../../../www/lib/nzbinfo.php | cut -d: -f2`
OCCURRENCES=`grep -in '$this->rarfileregex = ' ../../../../www/lib/nzbinfo.php | cut -d: -f1 | wc -l`
REMLINE=`expr $LINE + 1`

if [[ $OCCURRENCES -eq 2 ]]; then
	$SED -i.bak "${REMLINE}d" $NEWZPATH/www/lib/nzbinfo.php
        $SED -i.bak "${LINE}r ${DIR}/bin/scripts/regex.txt" $NEWZPATH/www/lib/nzbinfo.php
fi
if ! grep -q '//$this->rarfileregex =' "$NEWZPATH/www/lib/nzbinfo.php" ; then
        $SED -i -e 's/$this->rarfileregex =/\/\/$this->rarfileregex =/' $NEWZPATH/www/lib/nzbinfo.php
	$SED -i.bak "${LINE}r ${DIR}/bin/scripts/regex.txt" $NEWZPATH/www/lib/nzbinfo.php
fi
