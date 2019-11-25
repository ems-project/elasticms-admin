
###build and push the images
	docker rmi elasticms-web
	docker build -t elasticms-web -f box-web/Dockerfile .

	docker rmi elasticms-php
	docker build -t elasticms-php -f box-php/Dockerfile .

	docker tag elasticms-web container-snapshot.smals.be/portalsosec-elasticms-web:snapshot 
	docker push container-snapshot.smals.be/portalsosec-elasticms-web:snapshot
	docker tag elasticms-web container-snapshot.smals.be/portalsosec-elasticms-web:tst
	docker push container-snapshot.smals.be/portalsosec-elasticms-web:tst
	docker tag elasticms-php container-snapshot.smals.be/portalsosec-elasticms-php:snapshot
	docker push container-snapshot.smals.be/portalsosec-elasticms-php:snapshot
	docker tag elasticms-php container-snapshot.smals.be/portalsosec-elasticms-php:tst
	docker push container-snapshot.smals.be/portalsosec-elasticms-php:tst

	
###Tag snapshot as test	
	docker pull container-snapshot.smals.be/portalsosec-elasticms-web:snapshot
	docker pull container-snapshot.smals.be/portalsosec-elasticms-php:snapshot
	
	docker tag -f container-snapshot.smals.be/portalsosec-elasticms-web:snapshot container-snapshot.smals.be/portalsosec-elasticms-web:test
	docker tag -f container-snapshot.smals.be/portalsosec-elasticms-php:snapshot container-snapshot.smals.be/portalsosec-elasticms-php:test
	
	
	docker push container-snapshot.smals.be/portalsosec-elasticms-web:test
	docker push container-snapshot.smals.be/portalsosec-elasticms-php:test
	