#!/bin/bash
set -e

# ============================================================
# PrestaShop entrypoint — overlay2 fix + post-install helpers
# ============================================================

# 1. Force overlay2 copy-up so PHP rename() works across layers
cp -a /var/www/html/. /tmp/www_fix/
rm -rf /var/www/html/*
cp -a /tmp/www_fix/. /var/www/html/
rm -rf /tmp/www_fix

# 2. Auto-delete /install folder once the shop is configured
if [ -f "/var/www/html/config/settings.inc.php" ] && [ -d "/var/www/html/install" ]; then
    echo "[entrypoint] Removing /install folder (already installed)..."
    rm -rf /var/www/html/install
fi

# 3. Disable SSL by default (Cloudflare terminates TLS upstream)
if [ -f "/var/www/html/config/settings.inc.php" ]; then
    DB_SERVER="${DB_SERVER:-localhost}"
    DB_USER="${DB_USER:-root}"
    DB_PASSWD="${DB_PASSWD:-}"
    DB_NAME="${DB_NAME:-prestashop}"

    # Extract table prefix from settings.inc.php
    PREFIX=$(grep -oP "_DB_PREFIX_\\K[^';]+" /var/www/html/config/settings.inc.php 2>/dev/null || echo "ps_")

    echo "[entrypoint] Disabling SSL enforcement in database..."
    mysql -h "$DB_SERVER" -u "$DB_USER" -p"$DB_PASSWD" "$DB_NAME" <<SQL
UPDATE ${PREFIX}configuration SET value='0' WHERE name='PS_SSL_ENABLED';
UPDATE ${PREFIX}configuration SET value='0' WHERE name='PS_SSL_ENABLED_EVERYWHERE';
SQL
fi

# 4. Start Apache
chown -R www-data:www-data /var/www/html
exec "$@"