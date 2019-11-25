#!/bin/bash

_today=$(date +"_%Y_%m_%d")

for filename in /opt/secrets/* /opt/configs/*; do
    if [ "$(basename $filename)" = "*" ]; then
        continue;
    fi
    echo -e "\e[32m\e[7m*******************************************************"
    echo -e "         Install CMS $(basename $filename)             "
    echo -e "*******************************************************\e[0m"
    

    cat >/opt/bin/$(basename $filename) <<EOL
#!/bin/bash
set -o allexport
envsubst < $filename > /tmp/$(basename $filename)
source /tmp/$(basename $filename)
set +o allexport

if [ \${1:-list} = sql ] || [ \${1:-list} = dump ] ; then
    if [ \${DB_DRIVER:-mysql} = mysql ] ; then
        if [ \${1:-list} = sql ] ; then
            echo mysql --port=\$DB_PORT --host=\$DB_HOST --user=\$DB_USER --password=\$DB_PASSWORD \$DB_NAME \${@:2}
            mysql --port=\$DB_PORT --host=\$DB_HOST --user=\$DB_USER --password=\$DB_PASSWORD \$DB_NAME \${@:2}
        else
            echo mysqldump --port=\$DB_PORT --host=\$DB_HOST --user=\$DB_USER --password=\$DB_PASSWORD \$DB_NAME \${@:2}
            mysqldump --port=\$DB_PORT --host=\$DB_HOST --user=\$DB_USER --password=\$DB_PASSWORD \$DB_NAME \${@:2}
        fi;
    elif [ \${DB_DRIVER:-mysql} = pgsql ] ; then
        if [ \${1:-list} = sql ] ; then
            echo PGHOST=\${DB_HOST} PGPORT=\${DB_PORT} PGDATABASE=\${DB_NAME} PGUSER=\${DB_USER} PGPASSWORD=\${DB_PASSWORD} psql \${@:2}
            PGHOST=\${DB_HOST} PGPORT=\${DB_PORT} PGDATABASE=\${DB_NAME} PGUSER=\${DB_USER} PGPASSWORD=\${DB_PASSWORD} psql \${@:2}
        else
            echo PGHOST=\${DB_HOST} PGPORT=\${DB_PORT} PGDATABASE=\${DB_NAME} PGUSER=\${DB_USER} PGPASSWORD=\${DB_PASSWORD} pg_dump \${@:2} -T cache_container  -w --clean -Fp -O --schema=\${DB_SCHEMA:-public} | sed "/^\(DROP\|ALTER\|CREATE\) SCHEMA.*\$/d"
            PGHOST=\${DB_HOST} PGPORT=\${DB_PORT} PGDATABASE=\${DB_NAME} PGUSER=\${DB_USER} PGPASSWORD=\${DB_PASSWORD} pg_dump \${@:2} -T cache_container -w --clean -Fp -O --schema=\${DB_SCHEMA:-public} | sed "/^\(DROP\|ALTER\|CREATE\) SCHEMA.*\$/d"
        fi;
    else
        echo Driver \$DB_DRIVER not supported
    fi;
else
    php -d memory_limit=2018M /opt/src/bin/console \$@
fi;
EOL
    chmod a+x /opt/bin/$(basename $filename)

    _file="/var/lib/ems/dumps/deploy_$(basename $filename)$_today.sql"
    if [ -z ${GENERATE_BACKUP+x} ]; then
        echo Generate backup is turned off
    else
        if [ ! -f $_file ] ; then
            echo dump db: $_file
            /opt/bin/$(basename $filename) dump > $_file
        else
            echo a backup already exists: $_file
        fi;
    fi;

    echo migrate db
    /opt/bin/$(basename $filename) doctrine:migrations:migrate --no-interaction

    echo install assets
    /opt/bin/$(basename $filename) asset:install /opt/src/public --symlink --no-interaction

    echo cache warm up
    /opt/bin/$(basename $filename) cache:warm --no-interaction
done

run

#UTF-8
