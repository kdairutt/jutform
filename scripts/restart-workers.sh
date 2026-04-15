#!/bin/bash
echo "Restarting PHP-FPM..."
docker compose restart php-fpm
echo "Restarting queue worker..."
docker compose restart worker
echo "Done. Workers restarted."
