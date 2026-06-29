<?php
// api/messages.php
// POST ?action=send               (admin)
// GET  ?action=inbox              (student)
// GET  ?action=list               (admin — all messages)
// DELETE ?action=delete&id=       (admin)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'send') {
    requireAdmin();
    $b = getBody();
    $title      = s($b['title']      ?? '');
    $body       = s($b['body']       ?? '');
    $recipients = $b['recipients']   ?? [];  // array of student IDs

    if (!$title || !$body) respondErr('title and body required.');
    if (empty($recipients)) respondErr('At least one recipient required.');

    $db->prepare('INSERT INTO messages (title, body) VALUES (?,?)')->execute([$title, $body]);
    $msgId = $db->lastInsertId();

    $ins = $db->prepare('INSERT IGNORE INTO message_recipients (message_id, student_id) VALUES (?,?)');
    foreach ($recipients as $sid) {
        $ins->execute([$msgId, (int)$sid]);
    }
    respondOk(['message_id' => $msgId, 'recipient_count' => count($recipients)], 'Message sent');
}

if ($action === 'inbox') {
    $me = requireStudent();
    $stmt = $db->prepare(
        'SELECT m.* FROM messages m
         JOIN message_recipients mr ON mr.message_id = m.id
         WHERE mr.student_id = ?
         ORDER BY m.sent_at DESC'
    );
    $stmt->execute([$me['id']]);
    respondOk($stmt->fetchAll());
}

if ($action === 'list') {
    requireAdmin();
    $stmt = $db->query(
        'SELECT m.*, COUNT(mr.student_id) as recipient_count
         FROM messages m
         LEFT JOIN message_recipients mr ON mr.message_id = m.id
         GROUP BY m.id ORDER BY m.sent_at DESC'
    );
    respondOk($stmt->fetchAll());
}

if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM messages WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

respondErr('Unknown action.');
