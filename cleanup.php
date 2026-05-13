<?php
/**
 * PrestaShop Post-Install Cleanup
 * Removes the /install folder and disables SSL enforcement.
 * Run this AFTER completing the PrestaShop installation wizard.
 * Usage: Visit https://your-site.com/cleanup.php in your browser.
 */

// Detect if install is complete by checking for the install folder.
// The PrestaShop installer creates /install/ on start and deletes it on finish.
// We also check for config.inc.php as a backup indicator.
$installDir = '/var/www/html/install';
$configFile = '/var/www/html/config/config.inc.php';

$installDone = !is_dir($installDir);
$hasConfig = file_exists($configFile);

if (!$installDone && !$hasConfig) {
    die('Installation not complete yet. Please finish the install wizard first, then revisit this page.');
}

// Read database credentials from Docker environment variables
$dbServer = getenv('DB_SERVER') ?: 'site_32';
$dbUser   = getenv('DB_USER')   ?: 'admin';
$dbPass   = getenv('DB_PASSWD') ?: '';
$dbName   = getenv('DB_NAME')   ?: 'prestashop';

// Try to extract table prefix from config.inc.php if it exists
$prefix = 'ps_';
if ($hasConfig) {
    $configContent = file_get_contents($configFile);
    if (preg_match("/_DB_PREFIX_.*'([^']+)'/", $configContent, $m)) {
        $prefix = $m[1];
    }
}

// Safety: require confirmation
$CONFIRM = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$CONFIRM) {
    $installStatus = $installDone ? '<span style="color:green;font-weight:bold">✔ Already removed</span>'
                                   : '<span style="color:#856404;font-weight:bold">⚠ Still present</span>';
    echo '<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>PrestaShop Cleanup</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#fff3cd;border:2px solid #ffc107;border-radius:12px;color:#333}
h1{color:#856404}
a{color:#004085;text-decoration:underline}
.warning{padding:16px;background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;color:#155724;margin-top:16px}
.info{background:#cce5ff;border:1px solid #b8daff;border-radius:6px;padding:12px;margin-top:12px;color:#004085;font-size:13px}
</style></head><body>
<h1>⚡ PrestaShop Post-Install Cleanup</h1>
<div class="info">
<strong>DB Server:</strong> ' . htmlspecialchars($dbServer) . '<br>
<strong>DB User:</strong> ' . htmlspecialchars($dbUser) . '<br>
<strong>DB Name:</strong> ' . htmlspecialchars($dbName) . '<br>
<strong>Install folder:</strong> ' . $installStatus . '
</div>
<p>This will:</p>
<ul>
  <li>Delete the <code>/install</code> folder (if not already gone)</li>
  <li>Disable SSL enforcement in the database</li>
</ul>
<p>Only run this <strong>after</strong> you have completed the installation wizard.</p>
<div class="warning">
<strong>Ready?</strong> Click here to proceed:<br>
<a href="?confirm=yes" style="font-size:18px;padding:12px 24px;background:#004085;color:#fff;border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px">Run Cleanup →</a>
</div>
</body></html>';
    exit;
}

// ── 1. Remove /install folder ──
$installDir = '/var/www/html/install';
if (is_dir($installDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($installDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($installDir);
    $results[] = ['✔', '/install folder removed'];
} else {
    $results[] = ['⚪', '/install folder already gone'];
}

// ── 2. Disable SSL in database ──
$dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE {$prefix}configuration SET value='0' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE')");
    $stmt->execute();
    $rows = $stmt->rowCount();
    $results[] = ['✔', "SSL disabled in database ({$rows} rows affected)"];
} catch (PDOException $e) {
    $results[] = ['✘', 'DB error: ' . $e->getMessage()];
}

// ── 3. Show result ──
$siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Cleanup Complete</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#d4edda;border:2px solid #28a745;border-radius:12px;color:#155724}
h1{color:#155724}
.item{font-size:16px}
.ok{color:green}
.fail{color:red}
a{color:#004085}
</style></head><body>
<h1>✅ Cleanup Complete</h1>

<p>Results:</p>
<?php foreach ($results as [$icon, $msg]): ?>
<p class="item"><span class="<?= str_contains($icon, '✔') ? 'ok' : (str_contains($icon, '✘') ? 'fail' : '') ?>"><?= $icon ?> <?= $msg ?></span></p>
<?php endforeach; ?>

<p style="margin-top:24px"><a href="<?= $siteUrl ?>">← Back to your site</a></p>
</body></html>
<?php
exit;