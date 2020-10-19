# Available environment variables

The environment variables have been grouped by bundles and for the Symfony framework itself.

## Symfony variables

## Elasticms Core Bundle

## Elasticms Common Bundle

### EMS_ELASTICSEARCH_HOSTS
Define the elasticsearch cluster as an array (JSON encoded) of hosts:
- Default value: EMS_ELASTICSEARCH_HOSTS='["http://localhost:9200"]'

### EMS_HASH_ALGO
Refers to the [PHP hash_algos](https://www.php.net/manual/fr/function.hash-algos.php) function. Specify the algorithms to used in order to hash and identify files. It's also sued to hash the docment indexed in elasticsearch.
- Default value: EMS_HASH_ALGO='sha1'


## Deprecated variables

### ELASTICSEARCH_CLUSTER (Deprecated)
See [EMS_ELASTICSEARCH_HOSTS](#ems_elasticsearch_hosts)

### ELASTICSEARCH_VERSION (Deprecated)
This variable doesn't have any replacement, the value is directly get form the elasticsearch cluster itself.


