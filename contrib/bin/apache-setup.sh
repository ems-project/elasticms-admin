#!/bin/bash

#in /etc/apache2/httpd.conf
echo activate slotmem_shm and allowmethods modules
sed -i 's/^#LoadModule slotmem_shm_module modules\/mod_slotmem_shm\.so$/LoadModule slotmem_shm_module modules\/mod_slotmem_shm.so/g' /etc/apache2/httpd.conf
sed -i 's/^#LoadModule allowmethods_module modules\/mod_allowmethods\.so$/LoadModule allowmethods_module modules\/mod_allowmethods.so/g' /etc/apache2/httpd.conf

echo activate proxy_balancer and lbmethod_byrequests modules
#in /etc/apache2/conf.d/proxy.conf
sed -i 's/^#LoadModule proxy_balancer_module modules\/mod_proxy_balancer\.so$/LoadModule proxy_balancer_module modules\/mod_proxy_balancer.so/g' /etc/apache2/conf.d/proxy.conf
sed -i 's/^#LoadModule lbmethod_byrequests_module modules\/mod_lbmethod_byrequests\.so$/LoadModule lbmethod_byrequests_module modules\/mod_lbmethod_byrequests.so/g' /etc/apache2/conf.d/proxy.conf




#MEMBER_1=${MEMBER_1:-http:\\\/\\\/localhost:2005}
#MEMBER_2=${MEMBER_2:-${MEMBER_1}}
#MEMBER_3=${MEMBER_3:-${MEMBER_1}}
#BASE_URL=${BASE_URL:-\\\/cluster\\\/}
#ALIAS=${ALIAS:-\\\/ems}

#echo Members defined
#env | grep MEMBER

#echo Base URL defined: ${BASE_URL}


#sed -i "s/\(\s*BalancerMember\s*\)MEMBER_1/\1${MEMBER_1}/g" /etc/apache2/conf.d/vhost.conf
#sed -i "s/\(\s*BalancerMember\s*\)MEMBER_2/\1${MEMBER_2}/g" /etc/apache2/conf.d/vhost.conf
#sed -i "s/\(\s*BalancerMember\s*\)MEMBER_3/\1${MEMBER_3}/g" /etc/apache2/conf.d/vhost.conf

#sed -i "s/\(\s*<Location\s*\)BASE_URL/\1${BASE_URL}/g" /etc/apache2/conf.d/vhost.conf

#sed -i "s/\(\s*Alias\s*\)ALIAS/\1${ALIAS}/g" /etc/apache2/conf.d/vhost.conf



for filename in /opt/secrets/* /opt/configs/*; do
    if [ "$(basename $filename)" = "*" ]; then
        continue;
    fi

    echo -e "\e[32m\e[7m*******************************************************"
    echo -e "         Install CMS $(basename $filename)             "
    echo -e "*******************************************************\e[0m"

    envsubst < $filename > /tmp/$(basename $filename)
    source /tmp/$(basename $filename)

    cat >> /etc/apache2/conf.d/vhost.conf << EOL

<VirtualHost *:8080>

    ServerName $SERVER_NAME
EOL

    if ! [ -z ${SERVER_ALIASES+x} ]; then
        cat >> /etc/apache2/conf.d/vhost.conf << EOL
        ServerAlias $SERVER_ALIASES
EOL
    fi


    cat >> /etc/apache2/conf.d/vhost.conf << EOL

    LimitRequestLine 16384

    # Uncomment the following line to force Apache to pass the Authorization
    # header to PHP: required for "basic_auth" under PHP-FPM and FastCGI
    #
    # SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=\$1

    # For Apache 2.4.9 or higher
    # Using SetHandler avoids issues with using ProxyPassMatch in combination
    # with mod_rewrite or mod_autoindex
    <FilesMatch \.php\$>
        SetHandler "proxy:unix:/var/run/php-fpm/php-fpm.sock|fcgi://localhost/"
    </FilesMatch>

    DocumentRoot /opt/src/public
    <Directory /opt/src/public >
        AllowOverride None
        Order Allow,Deny
        Allow from All
        FallbackResource /index.php
    </Directory>

    <Directory /opt/src/project/public/bundles>
        FallbackResource disabled
    </Directory>

    ErrorLog /var/log/apache2/$(basename $filename)-error.log
    CustomLog /var/log/apache2/$(basename $filename)-access.log common

EOL


    if ! [ -z ${ALIAS+x} ]; then
        echo "An alias is defined: ${ALIAS}"
        echo "caution do not add an alias that exists somewhere in a ems route (i.e. admin)"
        cat >> /etc/apache2/conf.d/vhost.conf << EOL
        Alias $ALIAS /opt/src/public

        RewriteEngine  on
        RewriteCond %{REQUEST_URI} !^$ALIAS/index.php
        RewriteCond %{REQUEST_URI} !^$ALIAS/bundles
        RewriteCond %{REQUEST_URI} !^$ALIAS/favicon.ico\$
        RewriteCond %{REQUEST_URI} !^$ALIAS/apple-touch-icon.png\$
        RewriteCond %{REQUEST_URI} !^$ALIAS/robots.txt\$
        RewriteRule "^$ALIAS" "$ALIAS/index.php\$1" [PT]
EOL
    fi



    cat /tmp/$(basename $filename) | sed '/^\s*$/d' | grep  -v '^#' | sed "s/\([a-zA-Z0-9_]*\)\=\(.*\)/SetEnv \1 \2/g" >> /etc/apache2/conf.d/vhost.conf


    if [ -z ${BASE_URL+x} ]; then
        echo Base URL is not defined: ${BASE_URL}
    else
        echo Base URL is defined: ${BASE_URL}
        cat >> /etc/apache2/conf.d/vhost.conf << EOL
        ProxyRequests On

        <Proxy balancer://myset>
EOL

        echo $ELASTICSEARCH_CLUSTER | sed "s/,/\n/g" | sed "s/[\s\[\"]*\([^\"]*\)\".*/BalancerMember \1/"  >> /etc/apache2/conf.d/vhost.conf

        cat >> /etc/apache2/conf.d/vhost.conf << EOL
                #ProxySet lbmethod=byrequests
        </Proxy>

        <Location $BASE_URL/>
            ProxyPass "balancer://myset/"
            ProxyPassReverse "balancer://myset/"
            AllowMethods GET
        </Location>
EOL
fi;

    if [ -z ${PROTECTED_URL+x} ]; then
        echo Protected URL is not defined: ${PROTECTED_URL}
    else
        echo Protected URL is defined: ${PROTECTED_URL}
        cat >> /etc/apache2/conf.d/vhost.conf << EOL


    <Location "$PROTECTED_URL">
	    AuthType Basic
		AuthName "protected area"
		# (La ligne suivante est facultative)
		AuthBasicProvider file
		AuthUserFile /opt/src/.htpasswd
	    Require valid-user
	</Location>
EOL
	fi;


    cat >> /etc/apache2/conf.d/vhost.conf << EOL

</VirtualHost>
EOL

done


run