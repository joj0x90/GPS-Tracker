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
WORKDIR /app
RUN echo "ServerName tracking.dev.jirweb.de" >> /etc/apache2/apache2.conf
COPY apache-config.conf /etc/apache2/sites-enabled/000-default.conf
COPY httpd.conf /etc/apache2/ports.conf
EXPOSE 8080
COPY . /app
#### Apache ####

# Use it as the default startup command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
