<?php
/**
 * PrestaShop Post-Install Cleanup
 * Removes the /install folder and disables SSL enforcement.
 * Usage: Visit https://your-site.com/cleanup.php in your browser.
 */

// ── Detect install status ──
$installDir  = '/var/www/html/install';
$settingsFile = '/var/www/html/config/settings.inc.php';

$installPresent  = is_dir($installDir);
$hasSettings     = file_exists($settingsFile);
$hasConfig       = file_exists('/var/www/html/config/config.inc.php');

// Check if admin was renamed (clear sign install completed)
$adminRenamed = false;
$dh = @opendir('/var/www/html');
if ($dh) {
    while (($f = readdir($dh)) !== false) {
        if (preg_match('/^admin_\w+$/', $f) && is_dir('/var/www/html/' . $f)) {
            $adminRenamed = true;
            break;
        }
    }
    closedir($dh);
}

// Truly not installed: install folder present AND no settings AND no renamed admin
$notInstalled = $installPresent && !$hasSettings && !$adminRenamed;

if ($notInstalled) {
    die('Installation not complete yet. Please finish the install wizard first, then revisit this page.');
}

// ── DB credentials from environment (always available in Docker) ──
$dbServer = getenv('DB_SERVER') ?: 'localhost';
$dbUser   = getenv('DB_USER')   ?: 'root';
$dbPass   = getenv('DB_PASSWD') ?: '';
$dbName   = getenv('DB_NAME')   ?: 'prestashop';

// Try to get table prefix from settings.inc.php or fallback to ps_
$prefix = 'ps_';
if ($hasSettings) {
    $content = file_get_contents($settingsFile);
    if (preg_match("/_DB_PREFIX_.*'([^']+)'/", $content, $m)) {
        $prefix = $m[1];
    }
}

// ── Confirmation page ──
$CONFIRM = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$CONFIRM) {
    $installStat = $installPresent
        ? '<span style="color:#856404;font-weight:bold">⚠ Still present — will be removed</span>'
        : '<span style="color:green;font-weight:bold">✔ Already removed</span>';
    $settingsStat = $hasSettings
        ? '<span style="color:green;font-weight:bold">✔ Found</span>'
        : '<span style="color:#856404;font-weight:bold">⚠ Not found (install may have failed)</span>';
    $adminStat = $adminRenamed
        ? '<span style="color:green;font-weight:bold">✔ Renamed</span>'
        : '<span style="color:#856404;font-weight:bold">⚠ Not renamed</span>';

    echo '<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>PrestaShop Cleanup</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:640px;margin:60px auto;padding:24px;background:#fff3cd;border:2px solid #ffc107;border-radius:12px;color:#333}
h1{color:#856404;margin-top:0}
a{color:#004085;text-decoration:underline}
.warning{padding:16px;background:#d4edda;border:1px solid #c3e6cb;border-radius:6px;color:#155724;margin-top:16px}
table{width:100%;border-collapse:collapse;margin:12px 0}
td{padding:6px 10px;border-bottom:1px solid #ffeaa7;font-size:14px}
</style></head><body>
<h1>⚡ PrestaShop Post-Install Cleanup</h1>
<table>
<tr><td><strong>Install folder</strong></td><td>' . $installStat . '</td></tr>
<tr><td><strong>settings.inc.php</strong></td><td>' . $settingsStat . '</td></tr>
<tr><td><strong>Admin folder renamed</strong></td><td>' . $adminStat . '</td></tr>
</table>
<p>This will:</p>
<ul>
<li>Delete the <code>/install</code> folder</li>
<li>Disable SSL enforcement in the database</li>
</ul>
<p style="margin-top:12px;font-size:13px;color:#666">DB: ' . htmlspecialchars($dbServer) . ' / ' . htmlspecialchars($dbName) . '</p>
<div class="warning">
<strong>Ready?</strong> Click here to proceed:<br>
<a href="?confirm=yes" style="font-size:18px;padding:12px 24px;background:#004085;color:#fff;border-radius:6px;text-decoration:none;display:inline-block;margin-top:8px">Run Cleanup →</a>
</div>
</body></html>';
    exit;
}

// ── Run cleanup ──

// 1. Remove /install folder (shell if PHP can't handle overlay2)
$installDir = '/var/www/html/install';
if (is_dir($installDir)) {
    // Use shell mv+rm to handle overlay2 cross-device issues
    $rmOk = shell_exec("rm -rf /var/www/html/install 2>&1");
    $installDone = !is_dir($installDir);
    $results[] = [$installDone ? '✔' : '✘', '/install folder ' . ($installDone ? 'removed' : 'FAILED to remove: ' . $rmOk)];
} else {
    $results[] = ['⚪', '/install folder already gone'];
}

// 2. Disable SSL in database
$dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare("UPDATE {$prefix}configuration SET value='0' WHERE name IN ('PS_SSL_ENABLED', 'PS_SSL_ENABLED_EVERYWHERE')");
    $stmt->execute();
    $results[] = ['✔', "SSL disabled in database"];
} catch (PDOException $e) {
    $results[] = ['✘', 'DB error: ' . $e->getMessage()];
}

// 3. Show result
$siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Cleanup Complete</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#d4edda;border:2px solid #28a745;border-radius:12px;color:#155724}
h1{color:#155724} .item{font-size:16px} .ok{color:green} .fail{color:red} a{color:#004085}
</style></head><body>
<h1>✅ Cleanup Complete</h1>
<p>Results:</p>
<?php foreach ($results as [$icon, $msg]): ?>
<p class="item"><span class="<?= str_contains($icon,'✔')?'ok':'fail' ?>"><?= $icon ?> <?= $msg ?></span></p>
<?php endforeach; ?>
<p style="margin-top:24px"><a href="<?= $siteUrl ?>">← Back to your site</a></p>
</body></html>
<?php
exit;