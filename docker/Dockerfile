FROM registry.cesan.com.br/cesan/php:7.3-apache-pdo

COPY --chown=www-data:www-data html /var/www/html

RUN \
    apt-get update && \
    apt-get install libldap2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap
