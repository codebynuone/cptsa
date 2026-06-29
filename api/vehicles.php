<?php
// api/vehicles.php
// GET    ?action=list            (public)
// POST   ?action=create          (admin)
// DELETE ?action=delete&id=      (admin — non-defaults only)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'list') {
    $stmt = $db->query('SELECT * FROM vehicle_categories ORDER BY is_default DESC, id');
    respondOk($stmt->fetchAll());
}

if ($action === 'create') {
    requireAdmin();
    $b     = getBody();
    $id    = strtolower(preg_replace('/[^a-z0-9_]/', '', s($b['id']    ?? '')));
    $label = s($b['label'] ?? '');
    if (!$id || !$label) respondErr('id and label required.');
    try {
        $db->prepare('INSERT INTO vehicle_categories (id, label) VALUES (?,?)')->execute([$id, $label]);
    } catch (PDOException $e) {
        respondErr('Vehicle code already exists or DB error.');
    }
    respondOk(['id' => $id, 'label' => $label], 'Category added');
}

if ($action === 'delete') {
    requireAdmin();
    $id = s($_GET['id'] ?? '');
    if (!$id) respondErr('id required.');
    $stmt = $db->prepare('SELECT is_default FROM vehicle_categories WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) respondErr('Not found.', 404);
    if ($row['is_default']) respondErr('Cannot delete a system default category.');
    $db->prepare('DELETE FROM vehicle_categories WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

respondErr('Unknown action.');
