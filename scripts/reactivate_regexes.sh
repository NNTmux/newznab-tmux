SOURCE="${BASH_SOURCE[0]}"
DIR="$( dirname "$SOURCE" )"
while [ -h "$SOURCE" ]
do
    SOURCE="$(readlink "$SOURCE")"
    [[ $SOURCE != /* ]] && SOURCE="$DIR/$SOURCE"
    DIR="$( cd -P "$( dirname "$SOURCE"  )" && pwd )"
done
DIR="$( cd -P "$( dirname "$SOURCE" )" && pwd )"

cd $DIR"/../conf"
while read p; do
	mysql --defaults-file=my.cnf -uroot newznab -e "update releaseregex set status=1 where status=0 and ID=$p"
done < active_regexes.txt

rm active_regexes.txt
