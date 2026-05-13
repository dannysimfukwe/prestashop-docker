<?php
/**
 * PrestaShop Post-Install Cleanup
 * Removes the /install folder and disables SSL enforcement.
 * Run this AFTER completing the PrestaShop installation wizard.
 * Usage: Visit https://your-site.com/cleanup.php in your browser.
 */

// Try to find settings.inc.php — check all likely locations
$possiblePaths = [
    __DIR__ . '/config/settings.inc.php',
    dirname(__DIR__) . '/config/settings.inc.php',
    '/var/www/html/config/settings.inc.php',
    realpath(__DIR__ . '/../config/settings.inc.php'),
];

$settingsFile = null;
foreach ($possiblePaths as $p) {
    if ($p && file_exists($p)) {
        $settingsFile = $p;
        break;
    }
}

if (!$settingsFile) {
    die('Installation not complete yet. Please finish the install wizard first, then revisit this page.');
}

$configContent = file_get_contents($settingsFile);

// Safety: require confirmation to prevent accidental execution
$CONFIRM = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$CONFIRM) {
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
<p>Settings file found at: <code>' . htmlspecialchars($settingsFile) . '</code></p>
<p>This will:</p>
<ul>
  <li>Delete the <code>/install</code> folder</li>
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

// Read DB credentials from settings.inc.php
preg_match("/_DB_SERVER_.*'([^']+)'/", $configContent, $m); $dbServer = $m[1] ?? 'localhost';
preg_match("/_DB_USER_.*'([^']+)'/", $configContent, $m);   $dbUser   = $m[1] ?? 'root';
preg_match("/_DB_PASSWD_.*'([^']+)'/", $configContent, $m); $dbPass   = $m[1] ?? '';
preg_match("/_DB_NAME_.*'([^']+)'/", $configContent, $m);   $dbName   = $m[1] ?? 'prestashop';
preg_match("/_DB_PREFIX_.*'([^']+)'/", $configContent, $m); $prefix   = $m[1] ?? 'ps_';

$results = [];

// ── 1. Remove /install folder ──
$installDir = dirname($settingsFile) . '/install';
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