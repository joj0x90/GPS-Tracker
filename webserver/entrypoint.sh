#!/bin/bash
set -e

# Ensure db directory exists inside image
mkdir -p /var/www/html/db && chown -R www-data:www-data /var/www/html/db

echo "▶ Setting DB Permissions."
chown -R www-data:www-data /var/www/html/db
chmod -R 775 /var/www/html/db

echo "▶ Checking for gpx.sqlite..."

if [ ! -f /var/www/html/db/gpx.sqlite ]; then
    echo "🔧 Initializing SQLite database..."
    php /var/www/html/init_db.php
else
    echo "✅ SQLite database already exists."
fi

echo "▶ Starting Apache..."
exec apache2-foreground
