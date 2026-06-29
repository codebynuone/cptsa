<?php
// ============================================================
// test_setup.php  —  Run this FIRST to verify everything works
// Open: http://localhost/cptsa/test_setup.php
// DELETE this file from your server after setup is confirmed!
// ============================================================

// Don't show this on a live/public server
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!in_array($host, ['localhost', '127.0.0.1']) && !strpos($host, '192.168.') === 0) {
    // Comment out the next line if you need to run this on a remote server temporarily
    // die('Setup test only runs on localhost.');
}
?>
<!DOCTYPE html>
<html>
<head>
<title>CPTSA Setup Test</title>
<style>
body { font-family: Segoe UI, Arial, sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; background: #f7fafc; color: #1a202c; }
h1 { color: #0f2744; border-bottom: 3px solid #c9a227; padding-bottom: 10px; }
h2 { color: #0f2744; margin-top: 30px; font-size: 1.1rem; }
.ok   { background: #c6f6d5; color: #22543d; padding: 8px 14px; border-radius: 5px; margin: 4px 0; display: block; }
.fail { background: #fed7d7; color: #742a2a; padding: 8px 14px; border-radius: 5px; margin: 4px 0; display: block; }
.warn { background: #feebc8; color: #744210; padding: 8px 14px; border-radius: 5px; margin: 4px 0; display: block; }
.info { background: #bee3f8; color: #2a4365; padding: 8px 14px; border-radius: 5px; margin: 4px 0; display: block; }
pre  { background: #2d3748; color: #e2e8f0; padding: 14px; border-radius: 6px; overflow-x: auto; font-size: 0.88rem; }
.section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
td, th { padding: 8px 12px; border: 1px solid #e2e8f0; font-size: 0.9rem; text-align: left; }
th { background: #edf2f7; font-weight: 700; }
</style>
</head>
<body>

<h1>🔧 CPTSA Backend Setup Test</h1>
<p style="color:#718096;">Run this page to confirm everything is working before using the app. <strong>Delete this file after setup!</strong></p>

<?php
$allOk = true;
function ok($msg)   { echo "<span class='ok'>✅ $msg</span>"; }
function fail($msg) { echo "<span class='fail'>❌ $msg</span>"; global $allOk; $allOk = false; }
function warn($msg) { echo "<span class='warn'>⚠️ $msg</span>"; }
function info($msg) { echo "<span class='info'>ℹ️ $msg</span>"; }
?>

<!-- ── PHP Version ── -->
<div class="section">
<h2>1. PHP Version</h2>
<?php
$ver = PHP_VERSION;
if (version_compare($ver, '7.4.0', '>=')) {
    ok("PHP $ver — Compatible (7.4+ required)");
} else {
    fail("PHP $ver — Too old! Minimum required: PHP 7.4. Please upgrade XAMPP.");
}
?>
</div>

<!-- ── Required Extensions ── -->
<div class="section">
<h2>2. Required PHP Extensions</h2>
<?php
$required = ['pdo', 'pdo_mysql', 'json', 'session', 'fileinfo', 'mbstring'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        ok("ext-$ext loaded");
    } else {
        fail("ext-$ext NOT loaded — Enable in php.ini: extension=$ext");
    }
}
?>
</div>

<!-- ── Database ── -->
<div class="section">
<h2>3. Database Connection</h2>
<?php
$dbConfig = __DIR__ . '/config/database.php';
if (!file_exists($dbConfig)) {
    fail("config/database.php not found — make sure the config/ folder is present");
} else {
    ok("config/database.php found");
    require_once $dbConfig;

    try {
        $pdo = getDB();
        ok("Connected to MySQL successfully (host: " . DB_HOST . ", db: " . DB_NAME . ")");

        // Check tables
        $tables = [
            'admins','students','student_vehicles','vehicle_categories',
            'time_slots','slot_bookings','exam_results','announcements',
            'documents','feedback','messages','message_recipients',
            'instructors','fees','registration_forms','signin_logs','settings'
        ];
        $stmt = $pdo->query("SHOW TABLES");
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missing = [];
        foreach ($tables as $t) {
            if (!in_array($t, $existing)) $missing[] = $t;
        }

        if (empty($missing)) {
            ok("All " . count($tables) . " database tables exist");
        } else {
            fail("Missing tables: " . implode(', ', $missing) . " — Run schema.sql in phpMyAdmin!");
        }

        // Check admin account
        $stmt2 = $pdo->query("SELECT COUNT(*) FROM admins");
        $adminCount = (int)$stmt2->fetchColumn();
        if ($adminCount > 0) {
            ok("Admin accounts: $adminCount found");
        } else {
            warn("No admin accounts in DB — Run schema.sql to seed the default admin (admin/admin123)");
        }

        // Check vehicle categories
        $stmt3 = $pdo->query("SELECT COUNT(*) FROM vehicle_categories");
        $vcCount = (int)$stmt3->fetchColumn();
        if ($vcCount > 0) {
            ok("Vehicle categories: $vcCount found");
        } else {
            warn("No vehicle categories — Run schema.sql to seed defaults");
        }

        // Check settings (reg code)
        $stmt4 = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'reg_code'");
        $stmt4->execute();
        $regCode = $stmt4->fetchColumn();
        if ($regCode) {
            ok("Registration code set: $regCode");
        } else {
            warn("Registration code not set — Run schema.sql");
        }

    } catch (Exception $e) {
        fail("Database connection FAILED: " . htmlspecialchars($e->getMessage()));
        echo "<pre>Hint: Open config/database.php and check DB_HOST, DB_USER, DB_PASS, DB_NAME\n"
           . "XAMPP defaults:\n  DB_HOST = 'localhost'\n  DB_USER = 'root'\n  DB_PASS = '' (empty)\n  DB_NAME = 'cptsa_driving'</pre>";
    }
}
?>
</div>

<!-- ── Upload Directories ── -->
<div class="section">
<h2>4. Upload Directories</h2>
<?php
$dirs = [
    __DIR__ . '/uploads'        => 'uploads/',
    __DIR__ . '/uploads/photos' => 'uploads/photos/',
    __DIR__ . '/uploads/docs'   => 'uploads/docs/',
];
foreach ($dirs as $path => $label) {
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            ok("$label — Created automatically");
        } else {
            fail("$label — Cannot create! Create it manually in your project folder.");
        }
    } else {
        if (is_writable($path)) {
            ok("$label — Exists and writable");
        } else {
            fail("$label — Exists but NOT writable. On Linux: chmod 755 $label");
        }
    }
}
?>
</div>

<!-- ── Session Test ── -->
<div class="section">
<h2>5. PHP Sessions</h2>
<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
$_SESSION['cptsa_test'] = 'ok_' . time();
if (isset($_SESSION['cptsa_test'])) {
    ok("Sessions working — session ID: " . session_id());
} else {
    fail("Sessions NOT working — check your php.ini session.save_path");
}
?>
</div>

<!-- ── API Endpoint Test ── -->
<div class="section">
<h2>6. API Endpoint Test</h2>
<?php
$apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST']
        . str_replace('test_setup.php', '', $_SERVER['REQUEST_URI'])
        . 'api/auth.php?action=session';

echo "<p style='color:#718096;font-size:.9rem;'>Testing: <code>$apiUrl</code></p>";

$ctx = stream_context_create(['http' => [
    'timeout' => 5,
    'ignore_errors' => true,
    'header' => "Cookie: " . session_name() . "=" . session_id() . "\r\n"
]]);
$response = @file_get_contents($apiUrl, false, $ctx);

if ($response === false) {
    warn("Could not reach API endpoint via HTTP — this is normal if PHP is not running as a web server. The API will still work from the browser.");
} else {
    $json = json_decode($response, true);
    if (isset($json['success'])) {
        ok("API endpoint responds correctly: " . htmlspecialchars($response));
    } else {
        fail("API returned unexpected response: " . htmlspecialchars($response));
    }
}
?>
</div>

<!-- ── URL / Path Info ── -->
<div class="section">
<h2>7. URL Configuration</h2>
<?php
$scheme    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$baseUrl   = "$scheme://$host$scriptDir";

info("Project base URL: <strong>$baseUrl/</strong>");
info("Student Portal: <a href='$baseUrl/students.html' target='_blank'>$baseUrl/students.html</a>");
info("Admin Portal: <a href='$baseUrl/admin.html' target='_blank'>$baseUrl/admin.html</a>");
info("API base: $baseUrl/api/");
?>
</div>

<!-- ── Quick Login Test ── -->
<div class="section">
<h2>8. Admin Login Test</h2>
<?php
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        if ($admin) {
            $hashOk = password_verify('admin123', $admin['password_hash']);
            $plainOk = ($admin['password_hash'] === 'admin123');
            if ($hashOk) {
                ok("Admin password 'admin123' verifies correctly against bcrypt hash");
            } elseif ($plainOk) {
                warn("Admin password is stored as plain text — will work but update to bcrypt for security");
            } else {
                fail("Admin password hash does NOT match 'admin123' — re-run schema.sql or manually update the admins table");
                echo "<pre>SQL to fix:\nUPDATE admins SET password_hash = '" . password_hash('admin123', PASSWORD_BCRYPT) . "' WHERE username = 'admin';</pre>";
            }
        } else {
            fail("No admin with username 'admin' found in database");
        }
    } catch (Exception $e) {
        warn("Could not run admin check: " . $e->getMessage());
    }
}
?>
</div>

<!-- ── Summary ── -->
<div class="section" style="background: <?= $allOk ? '#c6f6d5' : '#fed7d7' ?>; border: 2px solid <?= $allOk ? '#38a169' : '#e53e3e' ?>;">
<h2 style="color: <?= $allOk ? '#22543d' : '#742a2a' ?>; margin-top:0;">
    <?= $allOk ? '✅ All checks passed — Your XAMPP setup is ready!' : '❌ Some checks failed — Fix the red items above before using the app.' ?>
</h2>
<?php if ($allOk): ?>
<p style="color:#22543d; margin:0;">
    You can now open:
    <br>• <strong>Admin Portal</strong>: <a href="admin.html" style="color:#22543d;">admin.html</a> — login: <code>admin</code> / <code>admin123</code>
    <br>• <strong>Student Portal</strong>: <a href="students.html" style="color:#22543d;">students.html</a>
    <br>• <strong>Home Page</strong>: <a href="index.html" style="color:#22543d;">index.html</a>
    <br><br><strong>🔴 Remember to delete test_setup.php from your server after setup!</strong>
</p>
<?php endif; ?>
</div>

<!-- ── Server Info ── -->
<details style="margin-top:20px;">
<summary style="cursor:pointer; color:#718096; font-size:.9rem;">▶ PHP / Server Info (click to expand)</summary>
<div class="section" style="margin-top:10px;">
<table>
<tr><th>Key</th><th>Value</th></tr>
<tr><td>PHP Version</td><td><?= PHP_VERSION ?></td></tr>
<tr><td>Server Software</td><td><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></td></tr>
<tr><td>Document Root</td><td><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') ?></td></tr>
<tr><td>Script Path</td><td><?= htmlspecialchars(__FILE__) ?></td></tr>
<tr><td>Session Save Path</td><td><?= htmlspecialchars(session_save_path() ?: ini_get('session.save_path') ?: 'default') ?></td></tr>
<tr><td>Upload Max Filesize</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
<tr><td>Post Max Size</td><td><?= ini_get('post_max_size') ?></td></tr>
<tr><td>Memory Limit</td><td><?= ini_get('memory_limit') ?></td></tr>
<tr><td>Max Execution Time</td><td><?= ini_get('max_execution_time') ?>s</td></tr>
<tr><td>Loaded php.ini</td><td><?= htmlspecialchars(php_ini_loaded_file() ?: 'N/A') ?></td></tr>
</table>
</div>
</details>

</body>
</html>
