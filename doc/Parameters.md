# Available environment variables

The environment variables have been grouped by bundles and for the Symfony framework itself.

## Symfony variables

### APP_ENV

[Possible values](https://symfony.com/doc/current/configuration.html#selecting-the-active-environment): `dev`, `prod`, `test`
 - Example `APP_ENV=dev`
But there is 2 more possible values, specific to elasticms:
 - `db` : It's equivalent to a `prod` environment, but PHP sessions are persisted in the RDBMS (does not work with SQLite databases).
 - `redis` : It's equivalent to a `prod` environment, but PHP sessions are saved in a Redis server.

### APP_SECRET

A secret seed.
 - Example `APP_SECRET=7b19a4a6e37b9303e4f6bca1dc6691ed`
 
### Behind a Load Balancer or a Reverse Proxy

```dotenv
TRUSTED_PROXIES=127.0.0.1,127.0.0.2
TRUSTED_HOSTS=localhost,example.com
HTTP_CUSTOM_FORWARDED_PROTO=HTTP_X_COMPANY_FORWARDED_PROTO #Default value HTTP_X_FORWARDED_PROTO
```

## Swift Mailer

### MAILER_URL
Configure [Swift Mailer](https://symfony.com/doc/current/email.html#configuration)


## Doctrine variables

Default values (sqlite): 
```dotenv
DB_DRIVER='sqlite'
DB_USER='user'
DB_PASSWORD='user'
DB_PORT='1234'
DB_NAME='app.db'
```

### DB_HOST

DB's host. 
 - Default value: `127.0.0.1`
 - Example: `DB_DRIVER='db-server.tl'`
 
### DB_DRIVER

Driver (Type of the DB server). Accepted values are `mysql`, `pgsql` and `sqlite`
 - Default value: `mysql`
 - Example: `DB_DRIVER='pgsql'`
  
### DB_USER

 - Default value `user`
 - Example: `DB_USER='demo'`
  
### DB_PASSWORD

 - Default value `user`
 - Example: `DB_PASSWORD='password'`
  
### DB_PORT

For information the default mysql/mariadb port is 3306 and 5432 for Postgres
 - Default value `3306`
 - Example: `DB_PORT='5432'`
  
### DB_NAME

 - Default value `elasticms`
 - Example: `DB_NAME='demo'`
  
### DB_SCHEMA

This variable is not used by Doctrine but by the dump script with postgres in the docker image of elasticms. 
 - Default value: not defined
 - Example: `DB_SCEMA='schema_demo_adm'`

## Redis
Should be defined only if Redis is defined as session manager.
```dotenv
REDIS_HOST=localhost
REDIS_PORT=6379
```

## Elasticms Core Bundle variables
 
### EMSCO_TIKA_SERVER

Url of a Tika server (string).
 - Default value: empty string
 - Example `EMSCO_TIKA_SERVER=http://tika:9998`
 
### EMSCO_TIKA_DOWNLOAD_URL

Url or path to an Apache Tika jar file (string).
 - Default value: http://apache.belnet.be/tika/tika-app-1.22.jar
 - Example `EMSCO_TIKA_DOWNLOAD_URL=http://apache.belnet.be/tika/tika-app-1.22.jar'`
 
### Activate document signature

 - `EMSCO_PUBLIC_KEY` (mandatory): Path to a public key. The public key file needs to be in OpenSSH's format. It should look something like: `ssh-rsa AAAAB3NzaC1yc2EAAA....NX6sqSnHA8= rsa-key-20121110`
 - `EMSCO_PRIVATE_KEY` (mandatory): Path to a private key. 
 
### EMSCO_INSTANCE_ID

Used to prefix all elasticsearch indexes and aliases managed by the current instance of elasticms.  
 - Example `EMSCO_INSTANCE_ID=demo_v1_`
 
### EMSCO_NAME

HTML used as title in the interface
 - Default `<i class="ems-icon-ball"></i> elastic<b>ms</b>`
 - Example `EMSCO_NAME='<i class="ems-icon-ball"></i> My elasticms'`
 
### EMSCO_SHORTNAME

HTML used as short title in the interface
 - Default `<i class="ems-icon-ball"></i><span class="sr-only">elasticms</span>`
 - Example `EMSCO_SHORTNAME='My-ems'`
 
### EMSCO_SINGLE_TYPE_INDEX

If set to `true` an index will be created for each managed content types. You should avoid turning on this option as it will be deprecated soon. 
 - Default `false`
 - Example `EMSCO_SINGLE_TYPE_INDEX=false`
 
### EMSCO_PAGING_SIZE

Define the number of result per page in the search pages.
 - Default `20`
 
### EMSCO_THEME_COLOR

The color of the skin. The available skins can be found in the [EMSCoreBundle skin folder](https://github.com/ems-project/EMSCoreBundle/tree/master/assets/skins).
 - Default `blue`
 
### EMSCO_DATE_TIME_FORMAT

Format used to display dates in the interface. See [PHP datetime format](https://www.php.net/manual/en/datetime.format.php) for available options.
 - Default `j/m/Y \a\t G:i`
 
### EMSCO_DATEPICKER_FORMAT

Format used to display date in date pickers (See [Java Simple Date Format](https://docs.oracle.com/javase/7/docs/api/java/text/SimpleDateFormat.html).
 - Default `dd/mm/yyyy`
 
### EMSCO_DATEPICKER_WEEKSTART

First day of the week in date pickers. `0` for Sunday, `1` for Monday, ...  and `6` for Saterday.
 - Default `1`

 
### EMSCO_DATEPICKER_DAYSOFWEEK_HIGHLIGHTED

Highlights some days of the week in date pickers. `0` for Sunday, `1` for Monday, ...  and `6` for Saterday.
 - Default `[0, 6]`

 
### From information for email sent 
Default values:
```dotenv
EMSCO_FROM_EMAIL_ADDRESS='elasticms@example.com'
EMSCO_FROM_EMAIL_NAME='elasticms'
```
 
### EMSCO_ALLOW_USER_REGISTRATION
If set to `true` user can perform a self registration
 - Default `false`
 
### EMSCO_ASSET_CONFIG
Used to defined multiple asset processor config.
 - Default `[]`
 
Example:
```dotenv
EMS_ASSET_CONFIG='{"preview":{"_config_type":"image","_width":300,"_height":200,"_resize":"fill","_radius":0,"_radius_geometry":["topleft","bottomright"]}}'
```

### EMSCO_PRE_GENERATED_OUUIDS

If set to `true` new documents will have a OUUID generated before calling post-processors at first document's finalization.
If set to `false` new documents will have a OUUID generated by elasticsearch during first indexation. So OUUIDs are not available for post-processors at first document's finalization.
  - Default `false`
 
### EMS_CIRCLES_OBJECT

Name of the content type used a circle.
 - Default empty string (not defined)
 - Example `EMSCO_CIRCLES_OBJECT=circle`

### EMSCO_TRIGGER_JOB_FROM_WEB

If set to `false` job initiated from the interface are executed on the spot. Use the `ems:job:run` command to run pending jobs. It's recommended to schedule an `ems:job:run` command and turn off this option.
 - Default value `true`
 - Example `EMSCO_TRIGGER_JOB_FROM_WEB=false`
 
  
## Elasticms Common Bundle variables

### EMS_ELASTICSEARCH_HOSTS

Define the elasticsearch cluster as an array (JSON encoded) of hosts:
- Default value: EMS_ELASTICSEARCH_HOSTS='["http://localhost:9200"]'

### EMS_STORAGES

Used to define storage services. Elasticms supports [multiple types of storage services](https://github.com/ems-project/EMSCommonBundle/blob/master/src/Resources/doc/storages.md). 
- Default value: `EMS_STORAGES='[{"type":"fs","path":".\/var\/assets"},{"type":"s3","credentials":[],"bucket":""},{"type":"db","activate":false},{"type":"http","base-url":"","auth-key":""},{"type":"sftp","host":"","path":"","username":"","public-key-file":"","private-key-file":""}]'`
- Example: `EMS_STORAGES='[{"type":"fs","path":"./var/assets"},{"type":"fs","path":"/var/lib/elasticms"}]'`

### EMS_HASH_ALGO

Refers to the [PHP hash_algos](https://www.php.net/manual/fr/function.hash-algos.php) function. Specify the algorithms to used in order to hash and identify files. It's also used to hash the document indexed in elasticsearch.
- Default value: EMS_HASH_ALGO='sha1'


## Deprecated variables

 - ELASTICSEARCH_CLUSTER : See [EMS_ELASTICSEARCH_HOSTS](#ems_elasticsearch_hosts)
 - ELASTICSEARCH_VERSION : This variable doesn't have any replacement, the value is directly get form the elasticsearch cluster itself.
 - S3_BUCKET : See [EMS_STORAGES](#EMS_STORAGES)
 - S3_CREDENTIALS : See [EMS_STORAGES](#EMS_STORAGES)
 - STORAGE_FOLDER : See [EMS_STORAGES](#EMS_STORAGES)
 - EMS_SFTP_SERVEUR : See [EMS_STORAGES](#EMS_STORAGES)
 - EMS_SFTP_PATH : See [EMS_STORAGES](#EMS_STORAGES)
 - EMS_SFTP_USER : See [EMS_STORAGES](#EMS_STORAGES)
 - EMS_EMS_REMOTE_SERVER : See [EMS_STORAGES](#EMS_STORAGES)
 - EMS_EMS_REMOTE_AUTHKEY : See [EMS_STORAGES](#EMS_STORAGES)
 - EMS_SAVE_ASSETS_IN_DB : See [EMS_STORAGES](#EMS_STORAGES)
 - TIKA_SERVER : See [EMSCO_TIKA_SERVER](#EMSCO_TIKA_SERVER)
 - TIKA_DOWNLOAD_URL : See [EMSCO_TIKA_DOWNLOAD_URL](#EMSCO_TIKA_DOWNLOAD_URL)
 - EMS_PRIVATE_KEY : See [EMSCO_PRIVATE_KEY](#Activate document signature)
 - EMS_PUBLIC_KEY : See [EMSCO_PUBLIC_KEY](#Activate document signature)
 - EMS_INSTANCE_ID : See [EMSCO_INSTANCE_ID](#EMSCO_INSTANCE_ID)
 - EMS_NAME : See [EMSCO_NAME](#EMSCO_NAME)
 - EMS_SHORTNAME : See [EMSCO_SHORTNAME](#EMSCO_SHORTNAME)
 - EMS_SINGLE_TYPE_INDEX : See [EMSCO_SINGLE_TYPE_INDEX](#EMSCO_SINGLE_TYPE_INDEX)
 - EMS_PAGING_SIZE : See [EMSCO_PAGING_SIZE](#EMSCO_PAGING_SIZE)
 - EMS_THEME_COLOR : See [EMSCO_THEME_COLOR](#EMSCO_THEME_COLOR)
 - EMS_DATE_TIME_FORMAT : See [EMSCO_DATE_TIME_FORMAT](#EMSCO_DATE_TIME_FORMAT)
 - EMS_DATEPICKER_FORMAT : See [EMSCO_DATEPICKER_FORMAT](#EMSCO_DATEPICKER_FORMAT)
 - EMS_DATEPICKER_WEEKSTART : See [EMSCO_DATEPICKER_WEEKSTART](#EMSCO_DATEPICKER_WEEKSTART)
 - EMS_DATEPICKER_DAYSOFWEEK_HIGHLIGHTED : See [EMSCO_DATEPICKER_DAYSOFWEEK_HIGHLIGHTED](#EMSCO_DATEPICKER_DAYSOFWEEK_HIGHLIGHTED)
 - EMS_FROM_EMAIL_ADDRESS : See [EMSCO_FROM_EMAIL_ADDRESS](#From information for email sent)
 - EMS_FROM_EMAIL_NAME : See [EMSCO_FROM_EMAIL_NAME](#From information for email sent)
 - EMS_ALLOW_USER_REGISTRATION : See [EMSCO_ALLOW_USER_REGISTRATION](#EMSCO_ALLOW_USER_REGISTRATION)
 - EMSCO_ASSET_CONFIG : See [EMSCO_ASSET_CONFIG](#EMSCO_ASSET_CONFIG)
 - EMSCO_CIRCLES_OBJECT : See [EMSCO_CIRCLES_OBJECT](#EMSCO_ALLOW_USER_REGISTRATION)
 - EMS_UPLOAD_FOLDER : Not replaced
 - DATABASE_URL : See [Doctrine](#Doctrine variables))
 - EMS_ASSET_CONFIG_TYPE : Not replaced
 - EMS_ASSET_CONFIG_INDEX : Not replaced

