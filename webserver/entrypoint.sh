#!/bin/bash
set -e

echo "▶ Checking for gpx.sqlite..."

if [ ! -f /var/www/html/db/gpx.sqlite ] || [ ! -s /var/www/html/db/gpx.sqlite ]; then
    echo "🔧 Initializing SQLite database..."
    php /var/www/html/init_db.php
    chmod 664 /var/www/html/db/gpx.sqlite
else
    echo "✅ SQLite database already exists."
fi

echo "▶ Setting DB Permissions."
chown -R www-data:www-data /var/www/html/db
chmod -R 775 /var/www/html/db

echo "▶ Creatig uploads/ directory and setting permissions"
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

echo "▶ Starting Apache..."
exec apache2-foreground
