SOURCE="${BASH_SOURCE[0]}"
DIR="$( dirname "$SOURCE" )"

cd $DIR"/../conf"
for fn in `cat active_regexes.txt`; do
        echo "Resetting $fn"
        mysql --defaults-file=my.cnf -uroot newznab -e "update releaseregex set status=1 where ID=$fn"
done


#rm active_regexes.txt
