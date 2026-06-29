<?php
// api/feedback.php
// GET  ?action=list              (public)
// GET  ?action=summary           (public)
// POST ?action=submit            (public)
// DELETE ?action=delete&id=      (admin)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'list') {
    $stmt = $db->query('SELECT * FROM feedback ORDER BY posted_at DESC');
    respondOk($stmt->fetchAll());
}

if ($action === 'summary') {
    $stmt = $db->query('SELECT rating, COUNT(*) as cnt FROM feedback GROUP BY rating');
    $rows  = $stmt->fetchAll();
    $total = 0; $sum = 0;
    $counts = [1=>0,2=>0,3=>0,4=>0,5=>0];
    foreach ($rows as $r) {
        $counts[(int)$r['rating']] = (int)$r['cnt'];
        $total += $r['cnt'];
        $sum   += $r['rating'] * $r['cnt'];
    }
    $avg = $total ? round($sum / $total, 1) : 0;
    respondOk(['average' => $avg, 'total' => $total, 'counts' => $counts]);
}

if ($action === 'submit') {
    $b = getBody();
    $name     = s($b['name']     ?? '');
    $category = s($b['category'] ?? '');
    $rating   = (int)($b['rating'] ?? 0);
    $message  = s($b['message']  ?? '');
    if (!$name || !$category || $rating < 1 || $rating > 5 || !$message) {
        respondErr('All fields required, rating 1-5.');
    }
    $db->prepare('INSERT INTO feedback (reviewer_name, category, rating, message) VALUES (?,?,?,?)')
       ->execute([$name, $category, $rating, $message]);
    respondOk(null, 'Feedback submitted');
}

if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $db->prepare('DELETE FROM feedback WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

respondErr('Unknown action.');
