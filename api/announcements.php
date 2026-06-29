<?php
// api/announcements.php
// GET    ?action=list           (public)
// POST   ?action=create         (admin)
// DELETE ?action=delete&id=     (admin)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'list') {
    $stmt = $db->query('SELECT * FROM announcements ORDER BY posted_at DESC');
    respondOk($stmt->fetchAll());
}

if ($action === 'create') {
    requireAdmin();
    $b = getBody();
    $title   = s($b['title']   ?? '');
    $content = s($b['content'] ?? '');
    if (!$title || !$content) respondErr('title and content required.');
    $db->prepare('INSERT INTO announcements (title, content) VALUES (?,?)')->execute([$title, $content]);
    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM announcements WHERE id=?');
    $stmt->execute([$id]);
    respondOk($stmt->fetch(), 'Announcement created');
}

if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

respondErr('Unknown action.');
