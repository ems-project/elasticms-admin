FROM container-gcloud.smals.be/base-apache:3.8

MAINTAINER WCMtech <wcmtech@smals.be>

WORKDIR /

USER root

# Ensure that we can write in /opt/src/var
RUN chmod a+rwx /etc/apache2/conf.d

# Ensure that generated files can be write ( docker build failed if this files does not exists )
ADD contrib/bin/apache-setup.sh /opt/bin/apache-setup.sh
RUN chown 1001:0 /opt/bin/apache-setup.sh /etc/apache2/conf.d/vhost.conf /etc/apache2/httpd.conf /etc/apache2/conf.d/proxy.conf && \
    chmod a+rwx /opt/bin/apache-setup.sh /etc/apache2/conf.d/vhost.conf /etc/apache2/httpd.conf /etc/apache2/conf.d/proxy.conf && \
    touch /var/log/apache2/error.log && \
    touch /var/log/apache2/access.log && \
    ln -sf /dev/stdout /var/log/apache2/access.log && \
    ln -sf /dev/stderr /var/log/apache2/error.log

USER 1001

CMD ["apache-setup.sh"]
