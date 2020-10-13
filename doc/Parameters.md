# Available environment variables

## <a name="EMS_ELASTICSEARCH_HOSTS">EMS_ELASTICSEARCH_HOSTS</a>
Define the elasticsearch cluster as an array (JSON encoded) of hosts:
- Default value: EMS_ELASTICSEARCH_HOSTS='["http://localhost:9200"]'

## ELASTICSEARCH_CLUSTER (Deprecated)
See [EMS_ELASTICSEARCH_HOSTS](#EMS_ELASTICSEARCH_HOSTS)

## EMS_HASH_ALGO
Refers to the [PHP hash_algos](https://www.php.net/manual/fr/function.hash-algos.php) function. Specify the algorithms to used in order to hash and identify files. It's also sued to hash the docment indexed in elasticsearch.
- Default value: EMS_HASH_ALGO='sha1'