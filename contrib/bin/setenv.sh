#!/bin/bash

if [[ -n ${DATABASE_SERVICE_NAME} ]]; then
  # Uppercase the service name
  DATABASE_SERVICE_NAME=$(echo $DATABASE_SERVICE_NAME | awk '{print toupper($DATABASE_SERVICE_NAME)}')
  DATABASE_SERVICE_NAME=$(echo ${DATABASE_SERVICE_NAME//-/_})

  echo "Try to retrieve environment variables relative to database service : ${DATABASE_SERVICE_NAME} ..."

  DB_HOST=$(eval "echo \$${DATABASE_SERVICE_NAME}_PORT_3306_TCP_ADDR")
  DB_PORT=$(eval "echo \$${DATABASE_SERVICE_NAME}_PORT_3306_TCP_PORT")
  DB_USER=$(eval "echo \$${DATABASE_SERVICE_NAME}_ENV_MYSQL_USER")
  DB_PASS=$(eval "echo \$${DATABASE_SERVICE_NAME}_ENV_MYSQL_PASSWORD")
  DB_NAME=$(eval "echo \$${DATABASE_SERVICE_NAME}_ENV_MYSQL_DATABASE")
fi


if [[ -n ${TIKA_SERVICE_NAME} ]]; then
   TIKA_SERVER=$(eval "echo  '\\'\'http'\\':'\\'/'\\'/\$${TIKA_SERVICE_NAME}_HOST'\\':\$${TIKA_SERVICE_NAME}_PORT'\\'\'")
fi


if [[ -n ${MYSQL_SERVICE_PORT_3306_TCP_ADDR} ]]; then
  DB_HOST=${DB_HOST:-${MYSQL_SERVICE_PORT_3306_TCP_ADDR}}
  DB_PORT=${DB_PORT:-${MYSQL_SERVICE_PORT_3306_TCP_PORT}}
  DB_USER=${DB_USER:-${MYSQL_SERVICE_ENV_MYSQL_USER}}
  DB_PASS=${DB_PASS:-${MYSQL_SERVICE_ENV_MYSQL_PASSWORD}}
  DB_NAME=${DB_NAME:-${MYSQL_SERVICE_ENV_MYSQL_DATABASE}}
fi

if [[ -n ${MYSQL_SERVICE_HOST} ]]; then
	DB_HOST=${DB_HOST:-${MYSQL_SERVICE_HOST}}
fi

if [[ -n ${MYSQL_SERVICE_PORT} ]]; then
	DB_PORT=${DB_PORT:-${MYSQL_SERVICE_PORT}}
fi

if [ -f "/opt/secrets/mysql.dbname" ] ; then
	DB_NAME=$(cat /opt/secrets/mysql.dbname)
fi
if [ -f "/opt/secrets/mysql.host" ] ; then
    DB_HOST=$(cat /opt/secrets/mysql.host)
fi
if [ -f "/opt/secrets/mysql.password" ] ; then
    DB_PASS=$(cat /opt/secrets/mysql.password)
fi
if [ -f "/opt/secrets/mysql.port" ] ; then
    DB_PORT=$(cat /opt/secrets/mysql.port)
fi
if [ -f "/opt/secrets/mysql.username" ] ; then
    DB_USER=$(cat /opt/secrets/mysql.username)
fi
if [ -f "/opt/secrets/mysql.driver" ] ; then
    DB_DRIVER=$(cat /opt/secrets/mysql.driver)
fi
if [ -f "/opt/secrets/sql.dbname" ] ; then
	DB_NAME=$(cat /opt/secrets/sql.dbname)
fi
if [ -f "/opt/secrets/sql.host" ] ; then
    DB_HOST=$(cat /opt/secrets/sql.host)
fi
if [ -f "/opt/secrets/sql.password" ] ; then
    DB_PASS=$(cat /opt/secrets/sql.password)
fi
if [ -f "/opt/secrets/sql.port" ] ; then
    DB_PORT=$(cat /opt/secrets/sql.port)
fi
if [ -f "/opt/secrets/sql.username" ] ; then
    DB_USER=$(cat /opt/secrets/sql.username)
fi
if [ -f "/opt/secrets/sql.driver" ] ; then
    DB_DRIVER=$(cat /opt/secrets/sql.driver)
fi
if [ -f "/opt/secrets/sql.schema" ] ; then
    DB_SCHEMA=$(cat /opt/secrets/sql.schema)
fi
if [ -f "/opt/secrets/elasticsearch.cluster" ] ; then
    ES_CLUSTER=$(cat /opt/secrets/elasticsearch.cluster)
fi

if [[ -n ${ES_SERVICE_PORT_9200_TCP_ADDR} ]]; then
  ES_SERVER_1=${ES_SERVER_1:-${ES_SERVICE_PORT_9200_TCP_ADDR}}
  ES_PORT_1=${ES_PORT_1:-9200}
  ES_SERVER_2=${ES_SERVER_2:-${ES_SERVICE_PORT_9200_TCP_ADDR}}
  ES_PORT_2=${ES_PORT_2:-9200}
fi

if [[ -n ${ELASTICSEARCH_SERVICE_PORT_9200_TCP_ADDR} ]]; then
  ES_SERVER_1=${ES_SERVER_1:-${ELASTICSEARCH_SERVICE_PORT_9200_TCP_ADDR}}
  ES_PORT_1=${ES_PORT_1:-9200}
  ES_SERVER_2=${ES_SERVER_2:-${ELASTICSEARCH_SERVICE_PORT_9200_TCP_ADDR}}
  ES_PORT_2=${ES_PORT_2:-9200}
fi

if [[ -n ${ES_CLIENT_SERVICE_HOST} ]]; then
  ES_SERVER_1=${ES_SERVER_1:-${ES_CLIENT_SERVICE_HOST}}
  ES_SERVER_2=${ES_SERVER_2:-${ES_CLIENT_SERVICE_HOST}}
fi

if [[ -n ${ES_CLIENT_SERVICE_PORT_HTTP} ]]; then
  ES_PORT_1=${ES_PORT_1:-${ES_CLIENT_SERVICE_PORT_HTTP}}
  ES_PORT_2=${ES_PORT_2:-${ES_CLIENT_SERVICE_PORT_HTTP}}
fi


export ES_CLUSTER=${ES_CLUSTER:-\\[\\\'http:\\/\\/${ES_SERVER_1}\\:${ES_PORT_1}\\\'\\,\\\'http:\\/\\/${ES_SERVER_2}\\:${ES_PORT_2}\\\'\\]}
echo ES Cluster defined to ${ES_CLUSTER}

echo set default value if needed
export DB_HOST=${DB_HOST:-127.0.0.1}
export DB_NAME=${DB_NAME:-symfony}
export DB_USER=${DB_USER:-root}
export DB_DRIVER=${DB_DRIVER:-pdo_mysql}
export DB_PORT=${DB_PORT:-25}
export DB_SCHEMA=${DB_SCHEMA:-public}

export ASSET_CONFIG_TYPE=${ASSET_CONFIG_TYPE:-null}
export ASSET_CONFIG_INDEX=${ASSET_CONFIG_INDEX:-null}

