#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

if [ -d storage ]; then
    mkdir -p storage/uploads
    chown -R www-data:www-data storage
fi

exec "$@"
