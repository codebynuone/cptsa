<?php
// ============================================================
// CPTSA Driving School — Database Configuration
// ============================================================
// XAMPP default settings:
//   DB_HOST = 'localhost'
//   DB_USER = 'root'
//   DB_PASS = ''          (empty — XAMPP has no root password by default)
//   DB_NAME = 'cptsa_driving'
// ============================================================

// define('DB_HOST',    'localhost');
// define('DB_PORT',    '3306');
// define('DB_NAME',    'cptsa_driving');
// define('DB_USER',    'root');       // XAMPP default username
// define('DB_PASS',    '');           // XAMPP default: no password
// define('DB_CHARSET', 'utf8mb4');

define('DB_HOST', 'sql202.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'if0_42298002_cptsa_database');
define('DB_USER', 'if0_42298002');
define('DB_PASS', 'C9BaUiJQAkpgjR');  // ← your actual vPanel password
define('DB_CHARSET', 'utf8mb4');

// ── Upload paths ─────────────────────────────────────────
// __DIR__ resolves to  C:\xampp\htdocs\cptsa\config
// so __DIR__.'/../uploads/photos/' = C:\xampp\htdocs\cptsa\uploads\photos\
define('UPLOAD_DIR_PHOTOS', __DIR__ . '/../uploads/photos/');
define('UPLOAD_DIR_DOCS',   __DIR__ . '/../uploads/docs/');

// URL paths (relative, so they work on any subdirectory)
define('UPLOAD_URL_PHOTOS', 'uploads/photos/');
define('UPLOAD_URL_DOCS',   'uploads/docs/');

// Max file sizes
define('MAX_PHOTO_SIZE', 2  * 1024 * 1024);   // 2 MB
define('MAX_DOC_SIZE',   10 * 1024 * 1024);   // 10 MB

// ── PDO connection (singleton) ───────────────────────────
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed. Check config/database.php — '
                    . $e->getMessage()
            ]);
            exit;
        }
    }
    return $pdo;
}
