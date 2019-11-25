#!/bin/bash
source /opt/bin/setenv.sh


echo "Starting sql client"
echo ${DB_DRIVER}
if [ ${DB_DRIVER} = pdo_mysql ]
then
	mysql -h${DB_HOST} -u${DB_USER} -p${DB_PASS} -P${DB_PORT} ${DB_NAME}
elif [ ${DB_DRIVER} = pdo_pgsql ]
then
	PGHOST=${DB_HOST} PGPORT=${DB_PORT} PGDATABASE=${DB_NAME} PGUSER=${DB_USER} PGPASSWORD=${DB_PASS} psql
else
	echo "Driver not supported"
fi

#UTF-8