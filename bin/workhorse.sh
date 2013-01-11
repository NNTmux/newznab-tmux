#!/usr/bin/env bash
set -e

source ../edit_these.sh
eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

#query for db to increment backfill
MYSQL_CMD="UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<$MAXDAYS;"

#queries for db for totals
book_query="SELECT COUNT(*) from releases where bookinfoID IS NULL and categoryID = 7020;"
console_query="SELECT COUNT(*) from releases where consoleinfoID IS NULL and categoryID in ( select ID from category where parentID = 1000 );"
movie_query="SELECT COUNT(*) from releases where imdbID IS NULL and categoryID in ( select ID from category where parentID = 2000 );"
music_query="SELECT COUNT(*) from releases where musicinfoID IS NULL and categoryID in ( select ID from category where parentID = 3000 );"
pc_query="SELECT COUNT(*) from releases r left join category c on c.ID = r.categoryID where (categoryID in ( select ID from category where parentID = 4000)) and ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0));"
tvrage_query="SELECT COUNT(*) from releases where rageID = -1 and categoryID in ( select ID from category where parentID = 5000 );"
work_remaining_query="SELECT COUNT(*) from releases r left join category c on c.ID = r.categoryID where (r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0);"

#query db for totals
RELEASE_COUNT1=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${book_query}"`
RELEASE_COUNT2=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${console_query}"`
RELEASE_COUNT3=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${movie_query}"`
RELEASE_COUNT4=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${music_query}"`
RELEASE_COUNT5=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${pc_query}"`
RELEASE_COUNT6=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${tvrage_query}"`
RELEASE_COUNT7=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${work_remaining_query}"`

#sum of totals
export TOTAL_COUNT=$(($RELEASE_COUNT1 + $RELEASE_COUNT2 + $RELEASE_COUNT3 + $RELEASE_COUNT4 + $RELEASE_COUNT5 + $RELEASE_COUNT6 + $RELEASE_COUNT7))

if [ "$THREADS" == "true"  -a "$INNODB" == "true" ]; then
	while :
	 do

               #make active groups current
		if [[ $BINARIES == "true" ]] ; then
        		cd $NEWZNAB_PATH
                	[ -f update_binaries_threaded.php ] && $PHP update_binaries_threaded.php
	        fi
		if [ $TOTAL_COUNT -le $MAX_RELEASES ]; then
			#import nzb's
			if [[ $IMPORT == "true" ]] ; then
        			cd $INNODB_PATH
            			[ -f nzb-import.php ] && $PHP nzb-import.php ${NZBS} &
		        fi

			#get backfill for all active groups
			if [[ $BACKFILL == "true" ]] ; then
                		cd $NEWZNAB_PATH
			        [ -f backfill_threaded.php ] && $PHP backfill_threaded.php
        		fi

			wait

			#increment backfill days
		        if [[ $BACKFILL == "true" ]] ; then
                		$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -e "${MYSQL_CMD}"
			fi
		else
			echo "$TOTAL_COUNT unprocessed releases exceeds your threshold of $MAX_RELEASES..."
		fi

		echo "Import scripts waiting $NEWZNAB_IMPORT_SLEEP_TIME seconds..."
		sleep $NEWZNAB_IMPORT_SLEEP_TIME

	done

elif [ "$THREADS" != "true" -a "$INNODB" == "true" ]; then
	while :
	 do

		#make active groups current
		if [[ $BINARIES == "true" ]] ; then
			cd $INNODB_PATH
			[ -f update_binaries.php ] && $PHP update_binaries.php
		fi

		if [ $TOTAL_COUNT -le $MAX_RELEASES ]; then
			#import nzb's
			if [[ $IMPORT == "true" ]] ; then
				cd $INNODB_PATH
				[ -f nzb-import.php ] && $PHP nzb-import.php ${NZBS} &
			fi

			#get backfill for all active groups
			if [[ $BACKFILL == "true" ]] ; then
				cd $INNODB_PATH
				[ -f backfill.php ] && $PHP backfill.php
			fi

			wait

			#increment backfill days
			if [[ $BACKFILL == "true" ]] ; then
				$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -e "${MYSQL_CMD}"
			fi
                else
                        echo "$TOTAL_COUNT unprocessed releases exceeds your threshold of $MAX_RELEASES..."
		fi

		echo "Import scripts waiting $NEWZNAB_IMPORT_SLEEP_TIME seconds..."
		sleep $NEWZNAB_IMPORT_SLEEP_TIME

	done

elif [ "$THREADS" == "true" -a "$INNODB" != "true" ]; then
	while :
	 do

		#make active groups current
		if [[ $BINARIES == "true" ]] ; then
			cd $NEWZNAB_PATH
			[ -f update_binaries_threaded.php ] && $PHP update_binaries_threaded.php
		fi

                if [ $TOTAL_COUNT -le $MAX_RELEASES ]; then
			#import nzb's
			if [[ $IMPORT == "true" ]] ; then
				cd $ADMIN_PATH
				[ -f nzb-importmodified.php ] && $PHP nzb-importmodified.php ${NZBS} &
			fi

			#make active groups current
			if [[ $BINARIES == "true" ]] ; then
				cd $NEWZNAB_PATH
				[ -f update_binaries_threaded.php ] && $PHP update_binaries_threaded.php
			fi

			#get backfill for all active groups
			if [[ $BACKFILL == "true" ]] ; then
				cd $NEWZNAB_PATH
				[ -f backfill_threaded.php ] && $PHP backfill_threaded.php
			fi

			wait

			#increment backfill days
			if [[ $BACKFILL == "true" ]] ; then
				$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -e "${MYSQL_CMD}"
			fi
                else
                        echo "$TOTAL_COUNT unprocessed releases exceeds your threshold of $MAX_RELEASES..."
		fi

		echo "Import scripts waiting $NEWZNAB_IMPORT_SLEEP_TIME seconds..."
		sleep $NEWZNAB_IMPORT_SLEEP_TIME

	done

elif [ "$THREADS" != "true"  -a "$INNODB" != "true" ]; then
	while :
	 do

		#make active groups current
		if [[ $BINARIES == "true" ]] ; then
			cd $NEWZNAB_PATH
			[ -f update_binaries.php ] && $PHP update_binaries.php
		fi

                if [ $TOTAL_COUNT -le $MAX_RELEASES ]; then
			#import nzb's
			if [[ $IMPORT == "true" ]] ; then
				cd $ADMIN_PATH
				[ -f nzb-importmodified.php ] && $PHP nzb-importmodified.php ${NZBS} &
			fi


			#get backfill for all active groups
			if [[ $BACKFILL == "true" ]] ; then
				cd $NEWZNAB_PATH
				[ -f backfill.php ] && $PHP backfill.php
			fi

			wait

			#increment backfill days
			if [[ $BACKFILL == "true" ]] ; then
				$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -e "${MYSQL_CMD}"
			fi

                else
                        echo "$TOTAL_COUNT unprocessed releases exceeds your threshold of $MAX_RELEASES..."
		fi

		echo "Import scripts waiting $NEWZNAB_IMPORT_SLEEP_TIME seconds..."
		sleep $NEWZNAB_IMPORT_SLEEP_TIME

	done

fi

