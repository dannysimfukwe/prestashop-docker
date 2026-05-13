<?php
/**
 * PrestaShop Post-Install Cleanup
 * Usage: Visit https://your-site.com/cleanup.php in your browser.
 *
 * Detects install completion by checking for config.inc.php WITH database
 * credentials (PrestaShop writes real creds during install).
 * Uses shell commands for overlay2-friendly file removal.
 */

$settingsFile = '/var/www/html/config/settings.inc.php';
$configFile   = '/var/www/html/config/config.inc.php';
$installDir   = '/var/www/html/install';

// Detect if install is complete:
// PrestaShop creates config.inc.php with REAL db creds during install.
// A fresh image has placeholder values. We check for real creds.
$installDone = false;
if (file_exists($configFile)) {
    $content = file_get_contents($configFile);
    // Check if DB credentials were actually set by the installer
    if (preg_match("/_DB_SERVER_/'[^']+/'/", $content)
        && preg_match("/_DB_NAME_/'[^']+/'/", $content)
        && !preg_match("/_DB_NAME_/'prestashop'/", $content)) {
        $installDone = true;
    }
}
if (file_exists($settingsFile)) {
    $installDone = true;
}
// Also check if admin folder was renamed (another sign of completed install)
$dh = @opendir('/var/www/html');
if ($dh) {
    while (($f = readdir($dh)) !== false) {
        if (preg_match('/^admin_[a-f0-9]+$/', $f) && is_dir('/var/www/html/' . $f)) {
            $installDone = true;
            break;
        }
    }
    closedir($dh);
}

if (!$installDone) {
    die('Installation not complete yet. Please finish the install wizard first, then revisit this page.');
}

// ── DB credentials: prefer settings.inc.php, fall back to config.inc.php, then env ──
$prefix   = 'ps_';
$dbServer = getenv('DB_SERVER') ?: 'localhost';
$dbUser   = getenv('DB_USER')   ?: 'root';
$dbPass   = getenv('DB_PASSWD') ?: '';
$dbName   = getenv('DB_NAME')   ?: 'prestashop';

$source = 'environment';

if (file_exists($settingsFile)) {
    $source = 'settings.inc.php';
    $content = file_get_contents($settingsFile);
    preg_match("/_DB_SERVER_.*'([^']+)'/", $content, $m); $dbServer = $m[1] ?: $dbServer;
    preg_match("/_DB_USER_.*'([^']+)'/",   $content, $m); $dbUser   = $m[1] ?: $dbUser;
    preg_match("/_DB_PASSWD_.*'([^']+)'/", $content, $m); $dbPass   = $m[1] ?: $dbPass;
    preg_match("/_DB_NAME_.*'([^']+)'/",   $content, $m); $dbName   = $m[1] ?: $dbName;
    preg_match("/_DB_PREFIX_.*'([^']+)'/", $content, $m); $prefix   = $m[1] ?: $prefix;
} elseif (file_exists($configFile)) {
    $source = 'config.inc.php';
    $content = file_get_contents($configFile);
    preg_match("/_DB_SERVER_.*'([^']+)'/", $content, $m); $dbServer = $m[1] ?: $dbServer;
    preg_match("/_DB_USER_.*'([^']+)'/",   $content, $m); $dbUser   = $m[1] ?: $dbUser;
    preg_match("/_DB_PASSWD_.*'([^']+)'/", $content, $m); $dbPass   = $m[1] ?: $dbPass;
    preg_match("/_DB_NAME_.*'([^']+)'/",   $content, $m); $dbName   = $m[1] ?: $dbName;
    preg_match("/_DB_PREFIX_.*'([^']+)'/", $content, $m); $prefix   = $m[1] ?: $prefix;
}

// ── Show confirmation page ──
$CONFIRM = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$CONFIRM) {
    echo '<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>PrestaShop Cleanup</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#fff3cd;border:2px solid #ffc107;border-radius:12px;color:#333}
h1{color:#856404} a{color:#004085;text-decoration:underline}
.ok{color:green;font-weight:bold} .warn{color:#856404;font-weight:bold}
div{margin-top:12px;line-height:1.8} .box{margin-top:16px}
.btn{display:inline-block;font-size:18px;padding:12px 24px;background:#004085;color:#fff;border-radius:6px;text-decoration:none;margin-top:8px}
</style></head><body>
<h1>⚡ PrestaShop Post-Install Cleanup</h1>
<p>Install detected via: <strong>' . $source . '</strong></p>
<div class="box">
<strong>Database:</strong> ' . htmlspecialchars($dbServer) . ' / ' . htmlspecialchars($dbName) . '<br>
<strong>User:</strong> ' . htmlspecialchars($dbUser) . '
</div>
<p>This will:</p>
<ul>
<li>Delete the <code>/install</code> folder</li>
<li>Disable SSL enforcement in the database</li>
</ul>
<div class="warn box"><strong>Ready? <a href="?confirm=yes" class="btn">Run Cleanup →</a></strong></div>
</body></html>';
    exit;
}

// ── Run cleanup ──
$results = [];

// 1. Remove /install folder (shell for overlay2 compatibility)
if (is_dir($installDir)) {
    $out = shell_exec("rm -rf " . escapeshellarg($installDir) . " 2>&1");
    $results[] = [!is_dir($installDir) ? '✔' : '✘',
                  '/install folder ' . (!is_dir($installDir) ? 'removed' : 'FAILED: ' . $out)];
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
    $results[] = ['✔', 'SSL enforcement disabled in database'];
} catch (PDOException $e) {
    $results[] = ['✘', 'DB error: ' . $e->getMessage()];
}

// 3. Output result
$siteUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Cleanup Complete</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#d4edda;border:2px solid #28a745;border-radius:12px;color:#155724}
h1{color:#155724}.item{font-size:16px}.ok{color:green}.fail{color:red}a{color:#004085}
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