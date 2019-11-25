#!/bin/bash
source /opt/bin/setenv.sh

_now=$(date +"%a_%H")
_tag=${DB_NAME}
_file="/var/lib/ems/dumps/dump_$_tag$_now.sql"

if [ ! -f $_file ] || test `find "$_file" -mmin +180`
then
	echo "Starting backup to $_file"
	echo ${DB_DRIVER}
	if [ ${DB_DRIVER} = pdo_mysql ]
	then
		mysqldump -h${DB_HOST} -u${DB_USER} -p${DB_PASS} -P${DB_PORT} ${DB_NAME} > "$_file"
		echo "mysql dump OK"
	elif [ ${DB_DRIVER} = pdo_pgsql ]
	then
		PGHOST=${DB_HOST} PGPORT=${DB_PORT} PGDATABASE=${DB_NAME} PGUSER=${DB_USER} PGPASSWORD=${DB_PASS} pg_dump -w --clean -Fp -O --schema=${DB_SCHEMA} | sed "/^\(DROP\|ALTER\|CREATE\) SCHEMA.*$/d" > "$_file"
		echo "PG dump ok"
	else
		echo "Driver not supported"
	fi
else
	echo "Backup in the last 3 hours"
fi

#UTF-8