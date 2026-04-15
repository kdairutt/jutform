#!/bin/bash
echo "Running JutForm test suite..."
docker compose exec php-fpm php vendor/bin/phpunit --testdox
