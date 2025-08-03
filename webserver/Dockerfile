FROM php:8.3-apache

RUN apt update && apt upgrade -y
RUN apt install -y \
        libsqlite3-dev

RUN docker-php-ext-install pdo pdo_sqlite

# enable mod_rewrite
RUN a2enmod rewrite

# Copy entrypoint script into container
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

#### Apache ####
WORKDIR /var/www/html
RUN mkdir -p /var/www/html/db && chown -R www-data:www-data /var/www/html/db
RUN echo "ServerName tracking.dev.jirweb.de" >> /etc/apache2/apache2.conf
COPY apache-config.conf /etc/apache2/sites-enabled/000-default.conf
COPY httpd.conf /etc/apache2/ports.conf
COPY php.ini /local/etc/php/conf.d/custom.ini
EXPOSE 80
COPY ./src /var/www/html
#### Apache ####

# Use it as the default startup command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
