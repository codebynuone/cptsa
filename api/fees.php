<?php
// api/fees.php
// GET    ?action=list
// POST   ?action=create   (admin)
// DELETE ?action=delete&id=  (admin)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'list') {
    $stmt = $db->query('SELECT * FROM fees ORDER BY created_at DESC');
    respondOk($stmt->fetchAll());
}

if ($action === 'create') {
    requireAdmin();
    $b = getBody();
    $vt  = s($b['vehicle_type']    ?? '');
    $ta  = s($b['total_amount']    ?? '');
    $inf = s($b['installment_fee'] ?? '');
    $ins = s($b['installments']    ?? '');
    $sd  = s($b['stamp_duty']      ?? '');
    if (!$vt || !$ta) respondErr('vehicle_type and total_amount required.');
    $db->prepare('INSERT INTO fees (vehicle_type, total_amount, installment_fee, installments, stamp_duty)
                  VALUES (?,?,?,?,?)')->execute([$vt, $ta, $inf, $ins, $sd]);
    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM fees WHERE id=?');
    $stmt->execute([$id]);
    respondOk($stmt->fetch(), 'Fee added');
}

if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM fees WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

respondErr('Unknown action.');
