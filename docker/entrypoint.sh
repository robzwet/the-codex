#!/bin/sh
set -e

# Wait for the database, then apply the schema (idempotent).
php /var/www/html/docker/migrate.php

# Hand off to Apache (or whatever CMD was given).
exec "$@"
