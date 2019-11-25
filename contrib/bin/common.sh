#!/bin/bash
source /opt/bin/setenv.sh

echo Update distribution files
rm -f /opt/src/app/config/parameters.yml
#sed -i "s/\\\\\\\\/\//g" /opt/src/var/bootstrap.php.cache
#rm -f /opt/src/var/bootstrap.php.cache
cp /opt/src/app/config/parameters.yml.dist /opt/src/app/config/parameters.yml
dos2unix /opt/src/app/config/parameters.yml


echo Update PHP.ini
sed -i "s/^\(\s*memory_limit\s*=\s*\).*/\1512M/g" /etc/php/php.ini

echo Update DB parameters
# With keys naming directly
sed -i "s/^\(\s*database_host\s*:\s*\).*/\1${DB_HOST}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*database_driver\s*:\s*\).*/\1${DB_DRIVER}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*database_name\s*:\s*\).*/\1${DB_NAME}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*database_port\s*:\s*\).*/\1${DB_PORT}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*database_user\s*:\s*\).*/\1${DB_USER}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*database_password\s*:\s*\).*/\1${DB_PASS}/g" /opt/src/app/config/parameters.yml


echo Update mailer parameters
sed -i "s/^\(\s*mailer_transport\s*:\s*\).*/\1${MAILER_TRANSPORT:-smtp}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*mailer_host\s*:\s*\).*/\1${MAILER_HOST:-127\.0\.0\.1}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*mailer_user\s*:\s*\).*/\1${MAILER_USER:-\~}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*mailer_password\s*:\s*\).*/\1${MAILER_PASSWORD:-\~}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*mailer_port\s*:\s*\).*/\1${MAILER_PORT:-25}/g" /opt/src/app/config/parameters.yml


sed -i "s/^\(\s*tika_server\s*:\s*\).*/\1${TIKA_SERVER:-\~}/g" /opt/src/app/config/parameters.yml

echo Update secret
sed -i "s/^\(\s*secret\s*:\s*\).*/\1${EMS_SECRET:-ThisTokenIsNotSoSecretChangeIt}/g" /opt/src/app/config/parameters.yml

echo update elasticms parameters
sed -i "s/^\(\s*from_email_address\s*:\s*\).*/\1\'${MAIL_SENDER_ADDRESS:-noreply\@example\.com}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*from_email_name\s*:\s*\).*/\1\'${MAIL_SENDER_NAME:-elasticMS}\'/g" /opt/src/app/config/parameters.yml

sed -i "s/^\(\s*elasticsearch_cluster\s*:\s*\).*/\1${ES_CLUSTER}/g" /opt/src/app/config/parameters.yml

sed -i "s/^\(\s*filesystem_storage_folder\s*:\s*\).*/\1${EMS_STORAGE_FOLDER}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*theme_color\s*:\s*\).*/\1\'${EMS_COLOR:-red}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*instance_id\s*:\s*\).*/\1\'${EMS_INSTANCEID:-ems\_}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*circles_object\s*:\s*\).*/\1\'${EMS_CIRCLES_OBJECT}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*uploading_folder\s*:\s*\).*/\1${EMS_UPLOADING_FOLDER}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*audit_index\s*:\s*\).*/\1${EMS_AUDIT_INDEX}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*ems_name\s*:\s*\).*/\1\'${EMS_NAME:-\<b\>elastic\<\/b\>MS}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*ems_shortname\s*:\s*\).*/\1\'${EMS_SHORTNAME:-\<b\>e\<\/b\>MS}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*ems_private_key\s*:\s*\).*/\1${EMS_PRIVATE_KEY:-\~}/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*single_type_index\s*:\s*\).*/\1${SINGLE_TYPE_INDEX:-false}/g" /opt/src/app/config/parameters.yml

echo update environment urls
sed -i "s/^\(\s*live_url\s*:\s*\).*/\1\'${LIVE_URL}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*preview_url\s*:\s*\).*/\1\'${PREVIEW_URL}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*staging_url\s*:\s*\).*/\1\'${STAGING_URL}\'/g" /opt/src/app/config/parameters.yml

echo update asset config
sed -i "s/^\(\s*ems_asset_config_type\s*:\s*\).*/\1\'${ASSET_CONFIG_TYPE}\'/g" /opt/src/app/config/parameters.yml
sed -i "s/^\(\s*ems_asset_config_index\s*:\s*\).*/\1\'${ASSET_CONFIG_INDEX}\'/g" /opt/src/app/config/parameters.yml

chmod 750 /opt/src/app/config/parameters.yml
rm -Rf /opt/src/var/cache/*

php /opt/src/bin/console cache:clear -e prod
php /opt/src/bin/console cache:warm -e prod


#UTF-8
