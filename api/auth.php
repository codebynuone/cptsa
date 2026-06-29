<?php
// api/auth.php
// POST ?action=student_login  { id, password }
// POST ?action=admin_login    { username, password }
// POST ?action=logout
// GET  ?action=session

require_once __DIR__ . '/_core.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';
$db     = getDB();

// ── SESSION CHECK ─────────────────────────────────────────
if ($action === 'session') {
    if (!empty($_SESSION['role'])) {
        respondOk([
            'role'       => $_SESSION['role'],
            'student_id' => isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null,
            'name'       => isset($_SESSION['name']) ? $_SESSION['name'] : null,
        ]);
    }
    respondOk(['role' => null]);
}

// ── LOGOUT ───────────────────────────────────────────────
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    respondOk(null, 'Logged out');
}

// ── STUDENT LOGIN ─────────────────────────────────────────
// Username = student NIC (id), Password = phone number (plain text)
if ($action === 'student_login') {
    $body = getBody();
    $id   = s(isset($body['id'])       ? $body['id']       : '');
    $pass = s(isset($body['password']) ? $body['password'] : '');

    if (!$id || !$pass) respondErr('ID and password are required.');

    $stmt = $db->prepare('SELECT * FROM students WHERE nic = ? LIMIT 1');
    $stmt->execute([$id]);
    $student = $stmt->fetch();

    if (!$student) respondErr('Student not found.', 401);
    if ($student['status'] === 'inactive') respondErr('Account is inactive. Contact admin.', 403);

    if ($pass !== $student['password']) respondErr('Invalid password.', 401);

    session_regenerate_id(true);
    $_SESSION['role']       = 'student';
    $_SESSION['student_id'] = $student['id'];
    $_SESSION['nic']        = $student['nic'];
    $_SESSION['name']       = $student['name_init'] ? $student['name_init'] : $student['last_name'];

    respondOk([
        'role'       => 'student',
        'student_id' => $student['id'],
        'name'       => $_SESSION['name'],
    ]);
}

// ── ADMIN LOGIN ───────────────────────────────────────────
if ($action === 'admin_login') {
    $body = getBody();
    $user = s(isset($body['username']) ? $body['username'] : '');
    $pass = s(isset($body['password']) ? $body['password'] : '');

    if (!$user || !$pass) respondErr('Username and password are required.');

    $stmt = $db->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$user]);
    $admin = $stmt->fetch();

    $valid = false;
    if ($admin) {
        if (password_verify($pass, $admin['password_hash'])) {
            $valid = true;
        } elseif ($pass === $admin['password_hash']) {
            // plain-text fallback
            $valid = true;
        }
    }

    // Hard-coded fallback so first login always works on a fresh XAMPP install
    if (!$valid && $user === 'admin' && $pass === 'admin123') {
        $valid = true;
    }

    if (!$valid) respondErr('Invalid credentials.', 401);

    session_regenerate_id(true);
    $_SESSION['role']     = 'admin';
    $_SESSION['admin_id'] = $admin ? $admin['id'] : 1;
    $_SESSION['name']     = $admin ? $admin['name'] : 'Administrator';

    respondOk(['role' => 'admin', 'name' => $_SESSION['name']]);
}

respondErr('Unknown action.', 400);
