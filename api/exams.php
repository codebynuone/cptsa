<?php
// api/exams.php
// GET  ?action=list              (admin — all students)
// GET  ?action=my_exams          (student self)
// POST ?action=update            (admin) { student_id, vehicle_id, written, practical, exam_date }

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'list') {
    requireAdmin();
    $search = $_GET['search'] ?? '';
    $sql = 'SELECT s.id, s.name_init, s.last_name, s.nic,
                   sv.vehicle_id, vc.label as vehicle_label,
                   COALESCE(er.written,\'pending\') as written,
                   COALESCE(er.practical,\'pending\') as practical,
                   er.exam_date
            FROM students s
            JOIN student_vehicles sv ON sv.student_id = s.id
            JOIN vehicle_categories vc ON vc.id = sv.vehicle_id
            LEFT JOIN exam_results er ON er.student_id = s.id AND er.vehicle_id = sv.vehicle_id';
    $params = [];
    if ($search) {
        $sql .= ' WHERE s.name_init LIKE ? OR s.nic LIKE ? OR s.phone LIKE ?';
        $like = "%$search%";
        $params = [$like, $like, $like];
    }
    $sql .= ' ORDER BY s.id, sv.vehicle_id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respondOk($stmt->fetchAll());
}

if ($action === 'my_exams') {
    $me = requireStudent();
    $stmt = $db->prepare(
        'SELECT sv.vehicle_id, vc.label as vehicle_label,
                COALESCE(er.written,\'pending\') as written,
                COALESCE(er.practical,\'pending\') as practical,
                er.exam_date
         FROM student_vehicles sv
         JOIN vehicle_categories vc ON vc.id = sv.vehicle_id
         LEFT JOIN exam_results er ON er.student_id = sv.student_id AND er.vehicle_id = sv.vehicle_id
         WHERE sv.student_id = ?'
    );
    $stmt->execute([$me['id']]);
    respondOk($stmt->fetchAll());
}

if ($action === 'update') {
    requireAdmin();
    $b = getBody();
    $sid  = (int)($b['student_id']  ?? 0);
    $vid  = s($b['vehicle_id'] ?? '');
    if (!$sid || !$vid) respondErr('student_id and vehicle_id required.');

    $written   = s($b['written']   ?? 'pending');
    $practical = s($b['practical'] ?? 'pending');
    $date      = dateOrNull($b['exam_date'] ?? null);

    $db->prepare(
        'INSERT INTO exam_results (student_id, vehicle_id, written, practical, exam_date)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE written=VALUES(written), practical=VALUES(practical), exam_date=VALUES(exam_date)'
    )->execute([$sid, $vid, $written, $practical, $date]);

    respondOk(null, 'Updated');
}

respondErr('Unknown action.');
