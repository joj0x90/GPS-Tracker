FROM php:8.2-apache

RUN apt update && apt upgrade -y
RUN apt install -y \
        libsqlite3-dev

RUN docker-php-ext-install pdo pdo_sqlite

# enable mod_rewrite
RUN a2enmod rewrite

# Copy entrypoint script into container
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Use it as the default startup command
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
