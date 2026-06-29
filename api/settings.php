<?php
// api/settings.php
// GET  ?action=reg_code                    (admin)
// POST ?action=set_reg_code  { code }      (admin)

// api/forms.php is merged here for simplicity
// GET    ?action=form_list                  (admin)
// GET    ?action=form_get&id=               (admin | self by phone)
// POST   ?action=form_submit                (public)
// DELETE ?action=form_delete&id=            (admin)
// GET    ?action=signin_logs                (admin)
// POST   ?action=signin_log                 (public — student_access sign-in)
// DELETE ?action=delete_log&id=             (admin)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

// ── SETTINGS ──────────────────────────────────────────────
if ($action === 'reg_code') {
    requireAdmin();
    $stmt = $db->query("SELECT setting_value FROM settings WHERE setting_key='reg_code'");
    respondOk(['reg_code' => $stmt->fetchColumn() ?: '5566']);
}

if ($action === 'set_reg_code') {
    requireAdmin();
    $b    = getBody();
    $code = s($b['code'] ?? '');
    if (strlen($code) < 4) respondErr('Code must be at least 4 characters.');
    $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('reg_code',?)
                  ON DUPLICATE KEY UPDATE setting_value=?")->execute([$code, $code]);
    respondOk(['reg_code' => $code], 'Code updated');
}

// ── SIGN-IN LOG ───────────────────────────────────────────
if ($action === 'signin_log') {
    $b = getBody();
    $name  = s($b['name']  ?? '');
    $phone = s($b['phone'] ?? '');
    if (!$name || !$phone) respondErr('name and phone required.');
    $db->prepare('INSERT INTO signin_logs (name, phone) VALUES (?,?)')->execute([$name, $phone]);

    // verify reg code
    $token = s($b['reg_code'] ?? '');
    $stmt  = $db->query("SELECT setting_value FROM settings WHERE setting_key='reg_code'");
    $code  = $stmt->fetchColumn() ?: '5566';
    if ($token !== $code) respondErr('Invalid registration code.', 403);

    respondOk(['verified' => true], 'Sign-in logged');
}

if ($action === 'signin_logs') {
    requireAdmin();
    $stmt = $db->query('SELECT * FROM signin_logs ORDER BY signed_at DESC LIMIT 100');
    respondOk($stmt->fetchAll());
}

if ($action === 'delete_log') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM signin_logs WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

// ── REGISTRATION FORMS ────────────────────────────────────
if ($action === 'form_submit') {
    $b = getBody();
    $phone  = s($b['phone'] ?? '');
    $idNo   = s($b['id_no'] ?? '');
    $name   = s($b['name']  ?? '');
    if (!$phone || !$idNo || !$name) respondErr('phone, id_no and name required.');

    // Upsert by phone
    $stmt = $db->prepare('SELECT id FROM registration_forms WHERE student_phone=? LIMIT 1');
    $stmt->execute([$phone]);
    $existing = $stmt->fetchColumn();

    $status = s($b['form_status'] ?? 'Draft');
    $submittedAt = $status === 'Submitted' ? date('Y-m-d H:i:s') : null;

    $cols = [
        'student_phone', 'id_type', 'id_no', 'last_name', 'other_names', 'name_init',
        'gender', 'dob', 'age', 'blood_group', 'address', 'phone', 'div_secretariat',
        'restrictions', 'organ_donor', 'ntmi_date', 'ntmi_no', 'pol_date', 'pol_station',
        'old_lic_no', 'old_lic_issue', 'old_lic_exp', 'sign_text', 'amount',
        'cashier', 'signing_officer', 'form_status', 'submitted_at'
    ];
    $vals = [
        $phone,
        s($b['id_type']      ?? ''),
        $idNo,
        s($b['last_name']    ?? ''),
        s($b['other_names']  ?? ''),
        $name,
        s($b['gender']       ?? ''),
        dateOrNull($b['dob'] ?? null),
        (int)($b['age']      ?? 0) ?: null,
        s($b['blood']        ?? ''),
        s($b['address']      ?? ''),
        $phone,
        s($b['div_secretariat'] ?? ''),
        s($b['restrictions']    ?? 'None'),
        (int)($b['organ_donor'] ?? 0),
        dateOrNull($b['ntmi_date']         ?? null),
        s($b['ntmi_no']          ?? ''),
        dateOrNull($b['pol_date']          ?? null),
        s($b['pol_station']      ?? ''),
        s($b['old_lic_no']       ?? ''),
        dateOrNull($b['old_lic_issue']     ?? null),
        dateOrNull($b['old_lic_exp']       ?? null),
        s($b['sign_text']        ?? ''),
        !empty($b['amount']) ? (float)$b['amount'] : null,
        s($b['cashier']          ?? ''),
        s($b['signing_officer']  ?? ''),
        $status,
        $submittedAt,
    ];

    if ($existing) {
        $sets = array_map(function($c) { return "$c=?"; }, $cols);
        $vals[] = $existing;
        $db->prepare('UPDATE registration_forms SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
        respondOk(['form_id' => $existing], 'Updated');
    } else {
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $db->prepare('INSERT INTO registration_forms (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')')->execute($vals);
        respondOk(['form_id' => $db->lastInsertId()], 'Submitted');
    }
}

if ($action === 'form_get') {
    $id    = (int)($_GET['id']    ?? 0);
    $phone = s($_GET['phone']     ?? '');
    $idNo  = s($_GET['id_no']     ?? '');
    $name  = s($_GET['name']      ?? '');

    if ($id) {
        requireAdmin();
        $stmt = $db->prepare('SELECT * FROM registration_forms WHERE id=?');
        $stmt->execute([$id]);
    } elseif ($phone && $idNo) {
        // student view-own-form
        $stmt = $db->prepare('SELECT * FROM registration_forms WHERE student_phone=? AND id_no=? AND name_init=? LIMIT 1');
        $stmt->execute([$phone, $idNo, $name]);
    } else {
        respondErr('id or (phone + id_no + name) required.');
    }
    $row = $stmt->fetch();
    if (!$row) respondErr('Form not found.', 404);
    respondOk($row);
}

if ($action === 'form_list') {
    requireAdmin();
    $stmt = $db->query('SELECT * FROM registration_forms ORDER BY created_at DESC');
    respondOk($stmt->fetchAll());
}

if ($action === 'form_delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM registration_forms WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

// ── DASHBOARD STATS (admin) ───────────────────────────────
if ($action === 'stats') {
    requireAdmin();
    $stats = [];
    $stats['total_students']  = $db->query('SELECT COUNT(*) FROM students')->fetchColumn();
    $stats['active_students'] = $db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
    $stats['total_bookings']  = $db->query('SELECT COUNT(*) FROM slot_bookings')->fetchColumn();
    $stats['total_feedback']  = $db->query('SELECT COUNT(*) FROM feedback')->fetchColumn();
    respondOk($stats);
}

respondErr('Unknown action.');
