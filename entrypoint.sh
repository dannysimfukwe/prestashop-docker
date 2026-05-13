#!/bin/bash
# Fix ownership of /var/www/html at every container start
# This ensures the Apache worker (www-data) can rename/modify files
# even when Docker volumes reset permissions to root:root.
chown -R www-data:www-data /var/www/html
exec "$@"