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

# 4. Generate deployment info page (DB credentials for the installer)
cat > /var/www/html/deploy-info.php << 'PHPEOF'
<?php header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Deployment Info</title>
<style>
*{box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  max-width:640px;margin:60px auto;padding:24px;
  background:#fff3cd;border:2px solid #ffc107;border-radius:12px;color:#333}
h1{color:#856404;margin-top:0}
table{width:100%;border-collapse:collapse;margin:16px 0}
td{padding:8px 12px;border-bottom:1px solid #e0c98a}
code{background:#fff8e1;padding:2px 8px;border-radius:4px;font-size:14px}
.note{background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;
  padding:12px;margin-top:16px;color:#155724}
.warning{background:#fff3cd;border:1px solid #ffc107;border-radius:6px;
  padding:12px;margin-top:16px;color:#856404}
small{color:#666}
</style>
</head>
<body>
<h1>⚡ PrestaShop Deployment Info</h1>

<div class="warning">
<strong>SSL is disabled by default.</strong> Cloudflare handles TLS termination upstream.
After installation, you can re-enable SSL from:
<em>Back Office → Shop Parameters → Traffic &amp; SEO → Enable SSL</em>
</div>

<p>Use these credentials on the <a href="/install/">install page</a>:</p>
<table>
  <tr><td><strong>Database server</strong></td><td><code><?= htmlspecialchars(getenv('DB_SERVER') ?: 'localhost') ?></code></td></tr>
  <tr><td><strong>Database user</strong></td><td><code><?= htmlspecialchars(getenv('DB_USER') ?: 'root') ?></code></td></tr>
  <tr><td><strong>Database password</strong></td><td><code><?= htmlspecialchars(getenv('DB_PASSWD') ?: '') ?></code></td></tr>
  <tr><td><strong>Database name</strong></td><td><code><?= htmlspecialchars(getenv('DB_NAME') ?: 'prestashop') ?></code></td></tr>
</table>

<div class="note">
<strong>Installation steps:</strong><br>
1. Visit <a href="/install/">/install/</a><br>
2. Enter the database credentials above<br>
3. Complete the shop setup<br>
4. The /install folder is auto-removed after first run
</div>

<p><small>Generated: <?= date('Y-m-d H:i:s T') ?></small></p>
</body>
</html>
PHPEOF

chown www-data:www-data /var/www/html/deploy-info.php

# 5. Start Apache
chown -R www-data:www-data /var/www/html
exec "$@"