#!/bin/bash
set -e

# ============================================================
# PrestaShop entrypoint — overlay2 fix + post-install cleanup
# ============================================================

# 1. Force overlay2 copy-up so PHP rename() works across layers
cp -a /var/www/html/. /tmp/www_fix/
rm -rf /var/www/html/*
cp -a /tmp/www_fix/. /var/www/html/
rm -rf /tmp/www_fix
chown -R www-data:www-data /var/www/html

# 2. Background: wait for install to complete, then clean up.
#    PrestaShop creates settings.inc.php at the END of installation.
#    Once it exists, the install is done and we can safely disable SSL
#    and remove the /install folder.
(
  echo "[post-install] Waiting for installation to complete..."
  while [ ! -f /var/www/html/config/settings.inc.php ]; do
    sleep 5
  done

  # Give the installer a moment to finish its last file writes
  sleep 10

  echo "[post-install] Installation complete — cleaning up..."

  # Remove the install folder
  rm -rf /var/www/html/install

  # Disable SSL enforcement (Cloudflare handles TLS upstream)
  DB_SERVER="${DB_SERVER:-localhost}"
  DB_USER="${DB_USER:-root}"
  DB_PASSWD="${DB_PASSWD:-}"
  DB_NAME="${DB_NAME:-prestashop}"
  PREFIX=$(grep -oP "_DB_PREFIX_\\K[^';]+" /var/www/html/config/settings.inc.php 2>/dev/null || echo "ps_")

  mysql -h "$DB_SERVER" -u "$DB_USER" -p"$DB_PASSWD" "$DB_NAME" <<SQL
UPDATE ${PREFIX}configuration SET value='0' WHERE name='PS_SSL_ENABLED';
UPDATE ${PREFIX}configuration SET value='0' WHERE name='PS_SSL_ENABLED_EVERYWHERE';
SQL

  echo "[post-install] Done — SSL disabled, install folder removed."
) &

# 3. Start Apache (foreground)
exec "$@"