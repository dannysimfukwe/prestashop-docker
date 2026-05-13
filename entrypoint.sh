#!/bin/bash
set -e

# ============================================================
# PrestaShop entrypoint — overlay2 fix + post-install helpers
# ============================================================

# 1. Force overlay2 copy-up ONLY on first boot (before settings.inc.php exists)
#    After install, settings.inc.php exists — don't overwrite it.
if [ ! -f "/var/www/html/config/settings.inc.php" ]; then
    echo "[entrypoint] First boot — copying image layers to volume..."
    cp -a /var/www/html/. /tmp/www_fix/
    rm -rf /var/www/html/*
    cp -a /tmp/www_fix/. /var/www/html/
    rm -rf /tmp/www_fix
fi

chown -R www-data:www-data /var/www/html

# 2. If already installed: remove /install and disable SSL on every boot
if [ -f "/var/www/html/config/settings.inc.php" ]; then
    if [ -d "/var/www/html/install" ]; then
        echo "[entrypoint] Removing /install folder..."
        rm -rf /var/www/html/install
    fi

    # Disable SSL enforcement (Cloudflare handles TLS upstream)
    PREFIX=$(grep -oP "_DB_PREFIX_\K[^';]+" /var/www/html/config/settings.inc.php 2>/dev/null || echo "ps_")
    DB_SERVER="${DB_SERVER:-localhost}"
    DB_USER="${DB_USER:-root}"
    DB_PASSWD="${DB_PASSWD:-}"
    DB_NAME="${DB_NAME:-prestashop}"

    echo "[entrypoint] Disabling SSL enforcement..."
    mysql -h "$DB_SERVER" -u "$DB_USER" -p"$DB_PASSWD" "$DB_NAME" <<SQL 2>/dev/null || true
UPDATE ${PREFIX}configuration SET value='0' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE');
SQL
fi

# 3. Start Apache (foreground)
exec "$@"