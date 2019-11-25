# ElasticMS
## About
A minimal CMS to manage generic content in order to publish it in several Elasticsearch index (based on Symfony 3, and AdminLTE).


There are 4 differents roles in this CMS:


The author is able to create and edit a document. He is also able to publish a document


The admin is able to do all previous action but also to manage Elasticsearch indexes, such as:
- define index aliases
- create/delete an elasticsearch index

The Webmaster

The Author


## Setup
### Requirements
- bower
- composer
- elasticsearch
- mysql
- npm
- symfony 3

### Installation
Navigate to the root of the project `ElasticMS` and execute the following command:
> composer update

Add the end you will get a list of questions to configure user database and database user. While the user should exist in your mysql environment, you can automatically create the database and schema with the following commands:
>  php bin/console doctrine:database:create
>  php bin/console doctrine:migrations:migrate
>  y

You should also install the bower plugins:
> bower install

Verify the project's elasticsearch configuration in `src\AppBundle\Resources\config\parameters.yml`.
And now we can launch Symfony's build in server:
> php bin/console server:run

Then you have to create a super-admin user: 
> php bin/console fos:user:create admin --super-admin


//Todo add information about the elasticsearch cluster

And voila, ElasticMS is up and running!




If you want to load some test data in the DB run this: 
> php bin/console doctrine:fix:l

## SOAP connection
For the soapRequest twig function to work, the following line should be activated in your php.ini
> extension=php_soap.dll

## Database updates (schema and content)
Whenever a new version is released, the database might change. This can be done automatically with migrations.
You can easily get a status of the migrations in relation to your database scheme with the following command:
> php bin/console doctrine:migrations:status

To execute all migrations:
> php bin/console doctrine:migrations:migrate

In the case your database is already set up you should not try to run the initial migration, add it to the "already run" list with the following command:
> php bin/console doctrine:migrations:version --add 20160528181644

So that it does not get executed. 
The second migration in this project is safe to run multiple times as it changes data in our database, and not the table schema. 
It is adviced to always use migrations for changes so that:
- we can easily build a DB from scratch and get future changes (use doctrine:migrations:migrate in stead of doctrine:schema:create)
- everyone can update to a newer version without dataloss (auto generate migrations with doctrine:migrations:diff for schema updates && write migrations for content changes)
//TODO: decide on a naming convention for migrations