<?php
/**
 * PrestaShop Post-Install Cleanup
 * Usage: Visit https://your-site.com/cleanup.php in your browser.
 *
 * Detects install completion by looking for the renamed admin folder
 * (e.g. admin_abcdef123456) which PrestaShop creates during setup.
 * Uses Docker environment variables for DB credentials.
 * Uses shell rm for overlay2-compatible file deletion.
 */

// ── Detect if install is complete ──
$installDir = '/var/www/html/install';
$installDone = !is_dir($installDir);
$adminRenamed = false;

$dh = @opendir('/var/www/html');
if ($dh) {
    while (($f = readdir($dh)) !== false) {
        if (strpos($f, 'admin') === 0 && $f !== 'admin' && is_dir('/var/www/html/' . $f)) {
            $adminRenamed = true;
            break;
        }
    }
    closedir($dh);
}

// If install folder is gone OR admin was renamed → install is done
if ($installDone || $adminRenamed) {
    $installComplete = true;
} else {
    $installComplete = false;
}

if (!$installComplete) {
    die('Installation not complete yet. Please finish the install wizard first, then revisit this page.');
}

// ── DB credentials from Docker environment ──
$dbServer = getenv('DB_SERVER') ?: 'localhost';
$dbUser   = getenv('DB_USER')   ?: 'root';
$dbPass   = getenv('DB_PASSWD') ?: '';
$dbName   = getenv('DB_NAME')   ?: 'prestashop';
$prefix   = 'ps_'; // default; overridden below if we can read it

// Try to read table prefix from known config files
foreach ([
    '/var/www/html/app/config/parameters.php',
    '/var/www/html/app/config/parameters.yml',
    '/var/www/html/config/config.inc.php',
    '/var/www/html/config/defines.inc.php',
    '/var/www/html/config/settings.inc.php',
    '/var/www/html/config/bootstrap.php',
] as $f) {
    if (!file_exists($f)) continue;
    $content = file_get_contents($f);

    // YAML: database_prefix: phzag_
    if (preg_match("/database_prefix:\s*['\"]?([^'\"\n]+)/", $content, $m)) {
        $prefix = trim($m[1]);
        break;
    }

    // PHP array: 'database_prefix' => 'phzag_'
    if (preg_match("/'database_prefix'\s*=>\s*'([^']+)'/", $content, $m)) {
        $prefix = $m[1];
        break;
    }

    // define('_DB_PREFIX_', 'phzag_')
    if (preg_match("/define\s*\(\s*['\"]_DB_PREFIX_['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m)) {
        $prefix = $m[1];
        break;
    }

    // _DB_PREFIX_ => 'ps_' (config.inc.php array format)
    if (preg_match("/_DB_PREFIX_[^'\"]*['\"]([^'\"]+)['\"]/", $content, $m)) {
        $prefix = $m[1];
        break;
    }
}

// ── Confirmation page ──
$CONFIRM = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

if (!$CONFIRM) {
    $installStat = $installDone ? '<span style="color:green;font-weight:bold">✔ Removed</span>'
                                : '<span style="color:#856404;font-weight:bold">⚠ Still present — will be removed</span>';
    $adminStat   = $adminRenamed
        ? '<span style="color:green;font-weight:bold">✔ Renamed (install completed)</span>'
        : '<span style="color:#856404;font-weight:bold">⚠ Not renamed</span>';

    echo '<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>PrestaShop Cleanup</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:600px;margin:60px auto;padding:24px;background:#fff3cd;border:2px solid #ffc107;border-radius:12px;color:#333}
h1{color:#856404;margin-top:0} a{color:#004085;text-decoration:underline}
.row{margin-top:12px;line-height:2} table{width:100%;border-collapse:collapse}
td{padding:6px 10px;border-bottom:1px solid #ffeaa7;font-size:14px}
.btn{display:inline-block;font-size:18px;padding:12px 24px;background:#004085;color:#fff;border-radius:6px;text-decoration:none;margin-top:12px}
</style></head><body>
<h1>⚡ PrestaShop Post-Install Cleanup</h1>
<table>
<tr><td><strong>Install folder</strong></td><td>' . $installStat . '</td></tr>
<tr><td><strong>Admin folder renamed</strong></td><td>' . $adminStat . '</td></tr>
</table>
<p>This will:</p>
<ul>
<li>Delete the <code>/install</code> folder</li>
<li>Configure SSL and shop_url for HTTPS</li>
</ul>
<p style="margin-top:12px;font-size:13px;color:#666">DB: ' . htmlspecialchars($dbServer) . ' / ' . htmlspecialchars($dbName) . ' (prefix: ' . htmlspecialchars($prefix) . ')</p>
<div class="row"><strong>Ready? <a href="?confirm=yes" class="btn">Run Cleanup →</a></strong></div>
</body></html>';
    exit;
}

// ── Run cleanup ──
$results = [];

// 1. Remove /install folder (shell rm for overlay2 compatibility)
if (is_dir($installDir)) {
    shell_exec("rm -rf " . escapeshellarg($installDir));
    $results[] = [is_dir($installDir) ? '✘' : '✔',
                  '/install folder ' . (is_dir($installDir) ? 'FAILED' : 'removed')];
} else {
    $results[] = ['⚪', '/install folder already gone'];
}

// 2. Fix SSL and shop_url for proper HTTPS redirect
$domain = $_SERVER['HTTP_HOST'];
$dsn = "mysql:host=$dbServer;dbname=$dbName;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("UPDATE {$prefix}configuration SET value='0' WHERE name='PS_SSL_ENABLED'");
    $pdo->exec("UPDATE {$prefix}configuration SET value='0' WHERE name='PS_SSL_ENABLED_EVERYWHERE'");
    $pdo->exec("UPDATE {$prefix}shop_url SET domain_ssl='$domain' WHERE main=1");
    $results[] = ['✔', 'SSL and shop_url configured for HTTPS'];
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
