<?php
// api/students.php
// GET    ?action=list                          (admin)
// GET    ?action=get&id=                       (admin | self)
// POST   ?action=create                        (admin)
// PUT    ?action=update&id=                    (admin)
// DELETE ?action=delete&id=                    (admin)
// POST   ?action=change_password               (student self)
// POST   ?action=upload_photo                  (student self — multipart)
// GET    ?action=dashboard                     (student self)
// GET    ?action=hours&id=                     (admin | self)
// POST   ?action=pay_restart&id=&vehicle=      (admin)
// POST   ?action=toggle_status&id=             (admin)

require_once __DIR__ . '/_core.php';

$action = $_GET['action'] ?? '';
$db     = getDB();

// ── Helpers ───────────────────────────────────────────────
function computeHours($db, $studentId, $vehicleId) {
    // booking hours
    $stmt = $db->prepare(
        'SELECT COUNT(*) as cnt FROM slot_bookings WHERE student_id = ? AND vehicle_id = ?'
    );
    $stmt->execute([$studentId, $vehicleId]);
    $bookingHrs = round(($stmt->fetchColumn() ?: 0) * 0.5, 1);

    // manual & deduction
    $stmt = $db->prepare(
        'SELECT manual_hours, deduction, extra_paid FROM student_vehicles
         WHERE student_id = ? AND vehicle_id = ?'
    );
    $stmt->execute([$studentId, $vehicleId]);
    $row = $stmt->fetch() ?: ['manual_hours' => 0, 'deduction' => 0, 'extra_paid' => 0];

    $limit     = $row['extra_paid'] ? 10 : 17;
    $effective = max(0, round(($bookingHrs - $row['deduction'] + $row['manual_hours']), 1));

    return [
        'vehicle_id'   => $vehicleId,
        'booking_hrs'  => $bookingHrs,
        'manual_hrs'   => (float)$row['manual_hours'],
        'deduction_hrs'=> (float)$row['deduction'],
        'used'         => $effective,
        'limit'        => $limit,
        'extra_paid'   => (bool)$row['extra_paid'],
        'remaining'    => max(0, $limit - $effective),
    ];
}

function getStudentVehicles($db, $studentId) {
    $stmt = $db->prepare('SELECT vehicle_id FROM student_vehicles WHERE student_id = ?');
    $stmt->execute([$studentId]);
    return array_column($stmt->fetchAll(), 'vehicle_id');
}

function enrichStudent($db, $s) {
    $vehicles = getStudentVehicles($db, $s['id']);
    $s['vehicles'] = $vehicles;
    $s['hours']    = array_map(function($v) use ($db, $s) { return computeHours($db, $s['id'], $v); }, $vehicles);
    unset($s['password']);
    if ($s['photo_path']) {
        $s['photo_url'] = UPLOAD_URL_PHOTOS . $s['photo_path'];
    }
    return $s;
}

// ── LIST ──────────────────────────────────────────────────
if ($action === 'list') {
    requireAdmin();
    $search = $_GET['search'] ?? '';
    $sql = 'SELECT * FROM students';
    $params = [];
    if ($search) {
        $sql .= ' WHERE nic LIKE ? OR name_init LIKE ? OR phone LIKE ?';
        $like = "%$search%";
        $params = [$like, $like, $like];
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = array_map(function($s) use ($db) { return enrichStudent($db, $s); }, $stmt->fetchAll());
    respondOk($students);
}

// ── GET SINGLE ────────────────────────────────────────────
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('ID required.');

    // admin can fetch any; student can only fetch self
    if (empty($_SESSION['role'])) respondErr('Unauthorised.', 401);
    if ($_SESSION['role'] === 'student' && (int)$_SESSION['student_id'] !== $id) {
        respondErr('Forbidden.', 403);
    }

    $stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) respondErr('Student not found.', 404);
    respondOk(enrichStudent($db, $s));
}

// ── CREATE (admin) ────────────────────────────────────────
if ($action === 'create') {
    requireAdmin();
    $b = getBody();

    $nic   = s($b['nic']   ?? '');
    $phone = s($b['phone'] ?? '');
    if (!$nic || !$phone) respondErr('NIC and phone are required.');

    // Password is always the student's phone number (plain text)
    $stmt = $db->prepare('INSERT INTO students
        (nic, id_type, password, last_name, other_names, name_init, gender,
         dob, blood_group, phone, address, div_secretariat, restrictions, organ_donor, reg_date, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

    try {
        $stmt->execute([
            $nic,
            s($b['id_type'] ?? 'NIC'),
            $phone,                         // password = phone number
            s($b['last_name']   ?? ''),
            s($b['other_names'] ?? ''),
            s($b['name_init']   ?? ''),
            s($b['gender']      ?? ''),
            dateOrNull($b['dob'] ?? null),
            s($b['blood']       ?? ''),
            $phone,
            s($b['address']     ?? ''),
            s($b['div_secretariat'] ?? ''),
            s($b['restrictions'] ?? 'None'),
            (int)($b['organ_donor'] ?? 0),
            dateOrNull($b['reg_date'] ?? date('Y-m-d')),
            'active',
        ]);
    } catch (PDOException $e) {
        if ((strpos($e->getMessage(), 'Duplicate') !== false)) respondErr('NIC already exists.');
        respondErr('DB error: ' . $e->getMessage());
    }

    $newId = (int)$db->lastInsertId();

    // insert vehicles
    $vehicles = $b['vehicles'] ?? [];
    foreach ($vehicles as $vid) {
        $db->prepare('INSERT IGNORE INTO student_vehicles (student_id, vehicle_id) VALUES (?,?)')
           ->execute([$newId, $vid]);
    }

    $stmt2 = $db->prepare('SELECT * FROM students WHERE id = ?');
    $stmt2->execute([$newId]);
    respondOk(enrichStudent($db, $stmt2->fetch()), 'Student created');
}

// ── UPDATE (admin) ────────────────────────────────────────
if ($action === 'update') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('ID required.');
    $b = getBody();

    $sets   = [];
    $params = [];

    $fields = [
        'nic'            => 'nic',
        'last_name'      => 'last_name',
        'other_names'    => 'other_names',
        'name_init'      => 'name_init',
        'gender'         => 'gender',
        'blood'          => 'blood_group',
        'phone'          => 'phone',
        'address'        => 'address',
        'div_secretariat'=> 'div_secretariat',
        'restrictions'   => 'restrictions',
        'status'         => 'status',
        'reg_date'       => null,   // handled below
    ];

    foreach ($fields as $bodyKey => $col) {
        if (isset($b[$bodyKey])) {
            $dbCol = $col ?? $bodyKey;
            if ($bodyKey === 'reg_date') {
                $sets[]   = 'reg_date = ?';
                $params[] = dateOrNull($b[$bodyKey]);
            } else {
                $sets[]   = "$dbCol = ?";
                $params[] = s($b[$bodyKey]);
            }
        }
    }
    if (isset($b['dob'])) {
        $sets[]   = 'dob = ?';
        $params[] = dateOrNull($b['dob']);
    }
    // When phone is updated, keep password in sync (password = phone)
    if (isset($b['phone']) && $b['phone'] !== '') {
        $sets[]   = 'password = ?';
        $params[] = s($b['phone']);
    }
    if (isset($b['organ_donor'])) {
        $sets[]   = 'organ_donor = ?';
        $params[] = (int)$b['organ_donor'];
    }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE students SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    // update vehicles
    if (isset($b['vehicles'])) {
        $db->prepare('DELETE FROM student_vehicles WHERE student_id = ?')->execute([$id]);
        foreach ((array)$b['vehicles'] as $vid) {
            $db->prepare('INSERT IGNORE INTO student_vehicles (student_id, vehicle_id) VALUES (?,?)')
               ->execute([$id, $vid]);
        }
    }

    // update manual hours / deductions / extra_paid
    if (isset($b['manual_hours_map']) || isset($b['deduction_map']) || isset($b['extra_paid_map'])) {
        $vehicles = getStudentVehicles($db, $id);
        foreach ($vehicles as $vid) {
            $mh  = (float)($b['manual_hours_map'][$vid] ?? 0);
            $ded = (float)($b['deduction_map'][$vid]    ?? 0);
            $ep  = (int)  ($b['extra_paid_map'][$vid]   ?? 0);
            $db->prepare('UPDATE student_vehicles SET manual_hours=?, deduction=?, extra_paid=?
                          WHERE student_id=? AND vehicle_id=?')
               ->execute([$mh, $ded, $ep, $id, $vid]);
        }
    }

    $stmt = $db->prepare('SELECT * FROM students WHERE id = ?');
    $stmt->execute([$id]);
    respondOk(enrichStudent($db, $stmt->fetch()), 'Student updated');
}

// ── DELETE (admin) ────────────────────────────────────────
if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('ID required.');
    $db->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
    respondOk(null, 'Student deleted');
}

// ── TOGGLE STATUS (admin) ─────────────────────────────────
if ($action === 'toggle_status') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('ID required.');
    $stmt = $db->prepare('SELECT status FROM students WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetchColumn();
    $new = $current === 'active' ? 'inactive' : 'active';
    $db->prepare('UPDATE students SET status=? WHERE id=?')->execute([$new, $id]);
    respondOk(['status' => $new]);
}

// ── PAY & RESTART (admin) ─────────────────────────────────
if ($action === 'pay_restart') {
    requireAdmin();
    $id  = (int)($_GET['id'] ?? 0);
    $vid = s($_GET['vehicle'] ?? '');
    if (!$id || !$vid) respondErr('id and vehicle required.');

    // Reset bookings for this student+vehicle
    $db->prepare('DELETE FROM slot_bookings WHERE student_id=? AND vehicle_id=?')->execute([$id, $vid]);
    // Reset manual/deduction, set extra_paid
    $db->prepare('UPDATE student_vehicles SET manual_hours=0, deduction=0, extra_paid=1
                  WHERE student_id=? AND vehicle_id=?')->execute([$id, $vid]);
    respondOk(null, 'Restarted with 10h limit.');
}

// ── CHANGE PASSWORD (student self) ───────────────────────
if ($action === 'change_password') {
    $me = requireStudent();
    $b  = getBody();
    $current = s($b['current_password'] ?? '');
    $new     = s($b['new_password']     ?? '');
    if (!$current || !$new) respondErr('Both current and new password required.');
    if (strlen($new) < 4)   respondErr('Password must be at least 4 characters.');

    $stmt = $db->prepare('SELECT password FROM students WHERE id = ?');
    $stmt->execute([$me['id']]);
    $stored = $stmt->fetchColumn();

    if ($current !== $stored) respondErr('Incorrect current password.', 401);

    $db->prepare('UPDATE students SET password=? WHERE id=?')
       ->execute([$new, $me['id']]);
    respondOk(null, 'Password updated');
}

// ── UPLOAD PHOTO (student self) ───────────────────────────
if ($action === 'upload_photo') {
    $me = requireStudent();

    if (empty($_FILES['photo'])) respondErr('No file uploaded.');
    $file = $_FILES['photo'];
    if ($file['error'] !== UPLOAD_ERR_OK) respondErr('Upload error: ' . $file['error']);
    if ($file['size'] > MAX_PHOTO_SIZE) respondErr('File too large (max 2 MB).');

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'])) {
        respondErr('Invalid image type.');
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = 'student_' . $me['id'] . '_' . time() . '.' . $ext;
    $dest     = UPLOAD_DIR_PHOTOS . $filename;

    if (!is_dir(UPLOAD_DIR_PHOTOS)) mkdir(UPLOAD_DIR_PHOTOS, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) respondErr('Failed to save file.');

    // Delete old photo
    $stmt = $db->prepare('SELECT photo_path FROM students WHERE id=?');
    $stmt->execute([$me['id']]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists(UPLOAD_DIR_PHOTOS . $old)) @unlink(UPLOAD_DIR_PHOTOS . $old);

    $db->prepare('UPDATE students SET photo_path=? WHERE id=?')->execute([$filename, $me['id']]);
    respondOk(['photo_url' => UPLOAD_URL_PHOTOS . $filename], 'Photo uploaded');
}

// ── DASHBOARD (student self) ──────────────────────────────
if ($action === 'dashboard') {
    $me = requireStudent();
    $stmt = $db->prepare('SELECT * FROM students WHERE id=?');
    $stmt->execute([$me['id']]);
    $s = $stmt->fetch();
    if (!$s) respondErr('Not found.', 404);
    respondOk(enrichStudent($db, $s));
}

// ── HOURS (admin or self) ─────────────────────────────────
if ($action === 'hours') {
    if (empty($_SESSION['role'])) respondErr('Unauthorised.', 401);
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('ID required.');
    if ($_SESSION['role'] === 'student' && (int)$_SESSION['student_id'] !== $id) {
        respondErr('Forbidden.', 403);
    }
    $vehicles = getStudentVehicles($db, $id);
    $hours    = array_map(function($v) use ($db, $id) { return computeHours($db, $id, $v); }, $vehicles);
    respondOk($hours);
}

respondErr('Unknown action.', 400);
