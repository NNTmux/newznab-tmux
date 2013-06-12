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

source $DIR"/../defaults.sh"

eval $( $SED -n "/^define/ { s/.*('\([^']*\)', '*\([^']*\)'*);/export \1=\"\2\"/; p }" "$NEWZPATH"/www/config.php )

cd $DIR"/conf"
for fn in `cat active_regexes.txt`; do
        echo "Resetting regex $fn"
	mysql --defaults-file=my.cnf -u$DB_USER -h $DB_HOST $DB_NAME -e "update releaseregex set status=1 where ID=$fn"
done

rm active_regexes.txt
