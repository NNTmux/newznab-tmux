#!/usr/bin/env bash
set -e

source ../edit_these.sh
eval $( sed -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" $NEWZPATH/www/config.php )

export MYSQL_CMD="UPDATE groups set backfill_target=backfill_target+1 where active=1 and backfill_target<$MAXDAYS;"
export MYSQL_REL="select count(*) from releases r left join category c on c.ID = r.categoryID where ((r.passwordstatus between -6 and -1) or (r.haspreview = -1 and c.disablepreview = 0))";

export RELEASE_COUNT=`$MYSQL -u$DB_USER -h $DB_HOST --password=$DB_PASSWORD $DB_NAME -s -N -e "${MYSQL_REL}"`

if [ "$THREADS" == "true"  -a "$INNODB" == "true" ]; then
	while :
	 do

               #make active groups current
		if [[ $BINARIES == "true" ]] ; then
        		cd $NEWZNAB_PATH
                	[ -f update_binaries_threaded.php ] && $PHP update_binaries_threaded.php
	        fi

		if [ $RELEASE_COUNT -le $MAX_RELEASES ]; then
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
			echo "Unprocessed releases exceeds your threshold of $MAX_RELEASES..."
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

		if [ $RELEASE_COUNT -le $MAX_RELEASES ]; then
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
                        echo "Unprocessed releases exceeds your threshold of $MAX_RELEASES..."
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

                if [ $RELEASE_COUNT -le $MAX_RELEASES ]; then
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
                        echo "Unprocessed releases exceeds your threshold of $MAX_RELEASES..."
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

                if [ $RELEASE_COUNT -le $MAX_RELEASES ]; then
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
                        echo "Unprocessed releases exceeds your threshold of $MAX_RELEASES..."
		fi

		echo "Import scripts waiting $NEWZNAB_IMPORT_SLEEP_TIME seconds..."
		sleep $NEWZNAB_IMPORT_SLEEP_TIME

	done

fi

