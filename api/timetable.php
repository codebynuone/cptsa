<?php
// api/timetable.php
// GET    ?action=slots                         (public — upcoming slots)
// POST   ?action=create_slot                   (admin)
// DELETE ?action=delete_slot&id=               (admin)
// GET    ?action=breakdown&vehicle=&date=       (admin)
// POST   ?action=book&slot_id=                  (student)
// DELETE ?action=cancel&slot_id=                (student)
// GET    ?action=my_bookings                    (student)

require_once __DIR__ . '/_core.php';

$action = $_GET['action'] ?? '';
$db     = getDB();

function slotWithBookings($db, $slot) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM slot_bookings WHERE slot_id = ?');
    $stmt->execute([$slot['id']]);
    $slot['booked_count'] = (int)$stmt->fetchColumn();
    return $slot;
}

// ── LIST UPCOMING SLOTS (public) ─────────────────────────
if ($action === 'slots') {
    $vehicle = $_GET['vehicle'] ?? '';
    $date    = $_GET['date']    ?? '';
    // Use yesterday as cutoff so timezone differences never hide today's slots
    $cutoff  = date('Y-m-d', strtotime('-1 day'));

    $sql    = 'SELECT * FROM time_slots WHERE slot_date > ?';
    $params = [$cutoff];
    if ($vehicle) { $sql .= ' AND vehicle_id = ?'; $params[] = $vehicle; }
    if ($date)    { $sql .= ' AND slot_date = ?';  $params[] = $date; }
    $sql .= ' ORDER BY slot_date, time_start';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $slots = array_map(function($s) use ($db) { return slotWithBookings($db, $s); }, $stmt->fetchAll());
    respondOk($slots);
}

// ── CREATE SLOT (admin) ───────────────────────────────────
if ($action === 'create_slot') {
    requireAdmin();
    $b = getBody();

    $date     = s($b['date']         ?? '');
    $start    = s($b['time_start']   ?? '');
    $end      = s($b['time_end']     ?? '');
    $vehicle  = s($b['vehicle_id']   ?? '');
    $capacity = (int)($b['capacity'] ?? 4);
    $display  = s($b['display_time'] ?? ($start . ' – ' . $end));

    if (!$date || !$start || !$end || !$vehicle) respondErr('date, time_start, time_end, vehicle_id required.');

    $slotId = bin2hex(random_bytes(8));

    try {
        $db->prepare('INSERT INTO time_slots (id, slot_date, time_start, time_end, display_time, vehicle_id, capacity)
                      VALUES (?,?,?,?,?,?,?)')
           ->execute([$slotId, $date, $start, $end, $display, $vehicle, $capacity]);
    } catch (PDOException $e) {
        respondErr('DB error: ' . $e->getMessage());
    }

    $stmt = $db->prepare('SELECT * FROM time_slots WHERE id = ?');
    $stmt->execute([$slotId]);
    respondOk(slotWithBookings($db, $stmt->fetch()), 'Slot created');
}

// ── DELETE SLOT (admin) ───────────────────────────────────
if ($action === 'delete_slot') {
    requireAdmin();
    $id = s($_GET['id'] ?? '');
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM time_slots WHERE id = ?')->execute([$id]);
    respondOk(null, 'Slot deleted');
}

// ── BREAKDOWN — who booked which slot (admin) ─────────────
if ($action === 'breakdown') {
    requireAdmin();
    $vehicle = $_GET['vehicle'] ?? '';
    $date    = $_GET['date']    ?? '';

    $sql    = 'SELECT ts.*, vc.label as vehicle_label FROM time_slots ts
               JOIN vehicle_categories vc ON vc.id = ts.vehicle_id WHERE 1=1';
    $params = [];
    if ($vehicle) { $sql .= ' AND ts.vehicle_id = ?'; $params[] = $vehicle; }
    if ($date)    { $sql .= ' AND ts.slot_date = ?';  $params[] = $date; }
    $sql .= ' ORDER BY ts.slot_date, ts.time_start';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $slots = $stmt->fetchAll();

    $result = [];
    foreach ($slots as $slot) {
        $bStmt = $db->prepare(
            'SELECT sb.*, s.name_init, s.last_name, s.phone
             FROM slot_bookings sb
             JOIN students s ON s.id = sb.student_id
             WHERE sb.slot_id = ?'
        );
        $bStmt->execute([$slot['id']]);
        $bookings = $bStmt->fetchAll();

        $slot['bookings']     = $bookings;
        $slot['booked_count'] = count($bookings);
        $result[] = $slot;
    }
    respondOk($result);
}

// ── BOOK (student) ────────────────────────────────────────
if ($action === 'book') {
    $me     = requireStudent();
    $slotId = s($_POST['slot_id'] ?? getBody()['slot_id'] ?? '');
    if (!$slotId) respondErr('slot_id required.');

    // Fetch slot
    $stmt = $db->prepare('SELECT * FROM time_slots WHERE id = ?');
    $stmt->execute([$slotId]);
    $slot = $stmt->fetch();
    if (!$slot) respondErr('Slot not found.', 404);

    $vehicleId = $slot['vehicle_id'];

    // Is student registered for this vehicle?
    $stmt2 = $db->prepare('SELECT 1 FROM student_vehicles WHERE student_id=? AND vehicle_id=?');
    $stmt2->execute([$me['id'], $vehicleId]);
    if (!$stmt2->fetchColumn()) respondErr('You are not registered for this vehicle.');

    // Hours limit check
    $hStmt = $db->prepare('SELECT COUNT(*) FROM slot_bookings WHERE student_id=? AND vehicle_id=?');
    $hStmt->execute([$me['id'], $vehicleId]);
    $bookingHrs = round(($hStmt->fetchColumn() ?: 0) * 0.5, 1);

    $svStmt = $db->prepare('SELECT manual_hours, deduction, extra_paid FROM student_vehicles WHERE student_id=? AND vehicle_id=?');
    $svStmt->execute([$me['id'], $vehicleId]);
    $sv = $svStmt->fetch() ?: ['manual_hours'=>0,'deduction'=>0,'extra_paid'=>0];

    $limit   = $sv['extra_paid'] ? 10 : 17;
    $used    = max(0, $bookingHrs - $sv['deduction'] + $sv['manual_hours']);
    if ($used >= $limit) respondErr('Hour limit reached. Contact admin to renew.');

    // Capacity check
    $capStmt = $db->prepare('SELECT COUNT(*) FROM slot_bookings WHERE slot_id=?');
    $capStmt->execute([$slotId]);
    if ((int)$capStmt->fetchColumn() >= (int)$slot['capacity']) respondErr('Slot is full.');

    // Duplicate check
    $dupStmt = $db->prepare('SELECT 1 FROM slot_bookings WHERE slot_id=? AND student_id=?');
    $dupStmt->execute([$slotId, $me['id']]);
    if ($dupStmt->fetchColumn()) respondErr('Already booked.');

    $db->prepare('INSERT INTO slot_bookings (slot_id, student_id, vehicle_id) VALUES (?,?,?)')
       ->execute([$slotId, $me['id'], $vehicleId]);

    respondOk(null, 'Slot booked — 0.5h deducted.');
}

// ── CANCEL (student) ──────────────────────────────────────
if ($action === 'cancel') {
    $me     = requireStudent();
    $slotId = s($_GET['slot_id'] ?? getBody()['slot_id'] ?? '');
    if (!$slotId) respondErr('slot_id required.');

    $db->prepare('DELETE FROM slot_bookings WHERE slot_id=? AND student_id=?')
       ->execute([$slotId, $me['id']]);
    respondOk(null, 'Booking cancelled.');
}

// ── MY BOOKINGS (student) ─────────────────────────────────
if ($action === 'my_bookings') {
    $me = requireStudent();
    $stmt = $db->prepare(
        'SELECT sb.*, ts.slot_date, ts.display_time, ts.vehicle_id, vc.label as vehicle_label
         FROM slot_bookings sb
         JOIN time_slots ts ON ts.id = sb.slot_id
         JOIN vehicle_categories vc ON vc.id = ts.vehicle_id
         WHERE sb.student_id = ?
         ORDER BY ts.slot_date, ts.time_start'
    );
    $stmt->execute([$me['id']]);
    respondOk($stmt->fetchAll());
}

respondErr('Unknown action.', 400);