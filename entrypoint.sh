#!/bin/bash
# Fix: Force all /var/www/html contents into the writable overlay2 layer.
# Without this, PHP rename() fails with "Invalid cross-device link"
# because image-layer inodes differ from the volume's upper layer.
cp -a /var/www/html/. /tmp/www_fix/
rm -rf /var/www/html/*
cp -a /tmp/www_fix/. /var/www/html/
rm -rf /tmp/www_fix
chown -R www-data:www-data /var/www/html
exec "$@"