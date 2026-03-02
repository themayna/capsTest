#!/usr/bin/env bash
composer install -n --optimize-autoloader
chown -R www-data:www-data var/cache
  shutdown() {
    echo "Received shutdown signal, stopping supervisord..."
    supervisorctl stop all
    supervisorctl shutdown
    wait
    exit 0
  }

  trap shutdown SIGTERM SIGINT

/usr/bin/supervisord -n >/dev/null 2>&1 &
touch /tmp/ready
exec "$@"
