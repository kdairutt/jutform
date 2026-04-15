#!/bin/bash
usage() {
    echo "Usage: ./scripts/logs.sh <log>"
    echo ""
    echo "Available logs:"
    echo "  app          PHP application error log"
    echo "  fpm-slow     PHP-FPM slow request log (requests > 3s)"
    echo "  fpm-access   PHP-FPM access log with timing"
    echo "  nginx        Nginx access log with response times"
    echo "  nginx-error  Nginx error log"
    echo "  mysql-slow   MySQL slow query log"
    echo ""
    echo "Add -f to follow (tail) the log, e.g.: ./scripts/logs.sh app -f"
}

if [ -z "$1" ]; then
    usage
    exit 1
fi

FOLLOW=""
if [ "$2" = "-f" ]; then
    FOLLOW="-f"
fi

case "$1" in
    app)
        docker compose exec php-fpm tail -n 200 $FOLLOW /var/log/php/error.log
        ;;
    fpm-slow)
        docker compose exec php-fpm tail -n 200 $FOLLOW /var/log/php/php-fpm-slow.log
        ;;
    fpm-access)
        docker compose exec php-fpm tail -n 200 $FOLLOW /var/log/php/php-fpm-access.log
        ;;
    nginx)
        docker compose exec nginx tail -n 200 $FOLLOW /var/log/nginx/access.log
        ;;
    nginx-error)
        docker compose exec nginx tail -n 200 $FOLLOW /var/log/nginx/error.log
        ;;
    mysql-slow)
        docker compose exec mysql tail -n 200 $FOLLOW /var/log/mysql/slow.log 2>/dev/null || echo "Slow query log may not be available at this path. Try: docker compose exec mysql mysqladmin -u root -proot_secret processlist"
        ;;
    *)
        echo "Unknown log: $1"
        usage
        exit 1
        ;;
esac
