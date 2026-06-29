<?php
// ============================================================
// api/_core.php  — shared helpers loaded by every endpoint
// Compatible with PHP 7.4+ and 8.x (XAMPP/WAMP/LAMP)
// ============================================================

require_once __DIR__ . '/../config/database.php';

// ── CORS & content-type ───────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Session — works on XAMPP without php.ini changes ─────
if (session_status() === PHP_SESSION_NONE) {
    // Allow session cookies to work on localhost (no https required)
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// ── Response helpers ──────────────────────────────────────
function respondOk($data = null, $msg = 'OK') {
    echo json_encode(['success' => true, 'message' => $msg, 'data' => $data]);
    exit;
}

function respondErr($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// Read JSON request body
function getBody() {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// Auth guards
function requireAdmin() {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        respondErr('Unauthorised — admin login required.', 401);
    }
}

function requireStudent() {
    if (empty($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        respondErr('Unauthorised — student login required.', 401);
    }
    return [
        'id'  => (int)$_SESSION['student_id'],
        'nic' => $_SESSION['nic'],
    ];
}

// Password helpers
function hashPassword($plain) {
    return password_hash($plain, PASSWORD_BCRYPT);
}

// Trim a string safely
function s($v) {
    return is_string($v) ? trim($v) : '';
}

// Return a Y-m-d string or null
function dateOrNull($d) {
    if (!$d || $d === '') return null;
    $t = strtotime($d);
    return ($t !== false) ? date('Y-m-d', $t) : null;
}
