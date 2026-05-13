<?php
/**
 * PrestaShop Post-Install Cleanup
 *
 * Removes the /install folder and disables SSL enforcement.
 * Run this AFTER completing the PrestaShop installation wizard.
 *
 * Usage: Visit https://your-site.com/cleanup.php in your browser.
 */

// Prevent running before install is complete
$settingsFile = __DIR__ . '/config/settings.inc.php';
if (!file_exists($settingsFile)) {
    die('Installation not complete yet. Please finish the install wizard first, then revisit this page.');
}

// Safety: require a confirmation token to prevent accidental execution
$CONFIRM = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$CONFIRM) {
    echo '<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>PrestaShop Cleanup</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#fff3cd;border:2px solid #ffc107;border-radius:12px;color:#333}
h1{color:#856404}
a{color:#004085;text-decoration:underline}
.warning{padding:16px;background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;color:#155724;margin-top:16px}
</style></head><body>
<h1>⚡ PrestaShop Post-Install Cleanup</h1>
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

// ── 1. Remove /install folder ──
$installDir = __DIR__ . '/install';
if (is_dir($installDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($installDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($installDir);
    $installDone = true;
} else {
    $installDone = false;
}

// ── 2. Disable SSL in database ──
$configContent = file_get_contents($settingsFile);

// Extract DB credentials from settings.inc.php
preg_match("/_DB_SERVER_.*'([^']+)'/", $configContent, $m); $dbServer = $m[1] ?? 'localhost';
preg_match("/_DB_USER_.*'([^']+)'/", $configContent, $m);   $dbUser   = $m[1] ?? 'root';
preg_match("/_DB_PASSWD_.*'([^']+)'/", $configContent, $m); $dbPass   = $m[1] ?? '';
preg_match("/_DB_NAME_.*'([^']+)'/", $configContent, $m);   $dbName   = $m[1] ?? 'prestashop';
preg_match("/_DB_PREFIX_.*'([^']+)'/", $configContent, $m); $prefix   = $m[1] ?? 'ps_';

// Build DSN from config
$dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("UPDATE {$prefix}configuration SET value='0' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE')");
    $stmt->execute();
    $sslDone = $stmt->rowCount() >= 0;
} catch (PDOException $e) {
    $sslDone = false;
    $dbError = $e->getMessage();
}

// ── 3. Show result ──
$siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Cleanup Complete</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#d4edda;border:2px solid #28a745;border-radius:12px;color:#155724}
h1{color:#155724}
.done{color:green;font-weight:bold}
.fail{color:red;font-weight:bold}
a{color:#004085}
</style></head><body>
<h1>✅ Cleanup Complete</h1>

<p><span class="<?= $installDone ? 'done' : 'fail' ?>">✔ /install folder removed</span></p>
<p><span class="<?= $sslDone ? 'done' : 'fail' ?>">✔ SSL enforcement disabled in database</span></p>

<?php if (isset($dbError)): ?>
<p class="fail">DB error: <?= htmlspecialchars($dbError) ?></p>
<?php endif; ?>

<p style="margin-top:24px"><a href="<?= $siteUrl ?>">← Back to your site</a></p>
</body></html>
<?php
exit;