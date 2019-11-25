FROM container-gcloud.smals.be/base-php7-fpm:3.8

MAINTAINER WCMtech <wcmtech@smals.be>

# Environment variables used in application config files.
# The 'setup.sh' script is responsible to replace this variables by her content.
# If secret is used, then the content of variables are filled from secret files.
#ENV MYSQL_USERNAME ${MYSQL_USERNAME}
#ENV MYSQL_PASSWORD ${MYSQL_PASSWORD}
#ENV MYSQL_TCP_ADDR lpssbdbs002a.ssb.dom
#ENV MYSQL_TCP_PORT 3002
#ENV MYSQL_DB_NAME symfony

WORKDIR /

USER root

# Ensure that we can write in /opt/src/var
RUN mkdir -p /opt/src/var/cache /opt/src/var/log /opt/src/var/sessions /opt/src/var/assets && \
    chown -Rf 1001:0 /opt/src/var/cache /opt/src/var/log /opt/src/var/sessions /opt/src/var/assets && \
    chmod a+rwx /opt/src/var/cache /opt/src/var/log /opt/src/var/sessions /opt/src/var/assets

ADD contrib/bin/setup.sh /opt/bin/setup.sh

RUN chown 1001:0 /opt/bin/setup.sh && \
    chmod +x /opt/bin/setup.sh && \
    touch /var/log/php-fpm/fpm-error.log && \
    touch /var/log/php-fpm/fpm-access.log && \
    ln -sf /dev/stdout /var/log/php-fpm/fpm-access.log && \
    ln -sf /dev/stderr /var/log/php-fpm/fpm-error.log

USER 1001

CMD ["setup.sh"]
