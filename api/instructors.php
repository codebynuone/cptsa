<?php
// api/instructors.php
// GET    ?action=list
// POST   ?action=create          (admin — multipart or JSON)
// PUT    ?action=update&id=      (admin)
// DELETE ?action=delete&id=      (admin)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

function instrRow($r) {
    $r['badges'] = json_decode($r['badges'] ?? '[]', true) ?: [];
    if ($r['photo_path']) {
        $r['photo_url'] = UPLOAD_URL_PHOTOS . $r['photo_path'];
    }
    return $r;
}

if ($action === 'list') {
    $stmt = $db->query('SELECT * FROM instructors ORDER BY id');
    respondOk(array_map('instrRow', $stmt->fetchAll()));
}

if ($action === 'create') {
    requireAdmin();
    // Support both multipart (with photo) and JSON
    $isMultipart = !empty($_FILES['photo']);
    $name  = s($_POST['name']  ?? (getBody()['name']  ?? ''));
    $title = s($_POST['title'] ?? (getBody()['title'] ?? ''));
    $badges= $_POST['badges']  ?? (getBody()['badges'] ?? '');
    $bio   = s($_POST['bio']   ?? (getBody()['bio']   ?? ''));
    if (!$name) respondErr('name required.');

    $badgesJson = is_array($badges) ? json_encode($badges) : json_encode(array_map('trim', explode(',', $badges)));

    $photoPath = null;
    if ($isMultipart && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $f   = $_FILES['photo'];
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $fn  = 'instructor_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!is_dir(UPLOAD_DIR_PHOTOS)) mkdir(UPLOAD_DIR_PHOTOS, 0755, true);
        move_uploaded_file($f['tmp_name'], UPLOAD_DIR_PHOTOS . $fn);
        $photoPath = $fn;
    }

    $db->prepare('INSERT INTO instructors (name, title, badges, bio, photo_path) VALUES (?,?,?,?,?)')
       ->execute([$name, $title, $badgesJson, $bio, $photoPath]);
    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM instructors WHERE id=?');
    $stmt->execute([$id]);
    respondOk(instrRow($stmt->fetch()), 'Created');
}

if ($action === 'update') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');

    $isMultipart = !empty($_FILES['photo']);
    $b      = $isMultipart ? $_POST : getBody();
    $sets   = []; $params = [];

    foreach (['name'=>'name','title'=>'title','bio'=>'bio'] as $k => $col) {
        if (isset($b[$k])) { $sets[] = "$col=?"; $params[] = s($b[$k]); }
    }
    if (isset($b['badges'])) {
        $badges = $b['badges'];
        $bj = is_array($badges) ? json_encode($badges) : json_encode(array_map('trim', explode(',', $badges)));
        $sets[] = 'badges=?'; $params[] = $bj;
    }

    if ($isMultipart && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $f   = $_FILES['photo'];
        $ext = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $fn  = 'instructor_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!is_dir(UPLOAD_DIR_PHOTOS)) mkdir(UPLOAD_DIR_PHOTOS, 0755, true);
        // Delete old
        $old = $db->prepare('SELECT photo_path FROM instructors WHERE id=?');
        $old->execute([$id]);
        $oldP = $old->fetchColumn();
        if ($oldP && file_exists(UPLOAD_DIR_PHOTOS . $oldP)) @unlink(UPLOAD_DIR_PHOTOS . $oldP);

        move_uploaded_file($f['tmp_name'], UPLOAD_DIR_PHOTOS . $fn);
        $sets[] = 'photo_path=?'; $params[] = $fn;
    }

    if ($sets) {
        $params[] = $id;
        $db->prepare('UPDATE instructors SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
    }
    $stmt = $db->prepare('SELECT * FROM instructors WHERE id=?');
    $stmt->execute([$id]);
    respondOk(instrRow($stmt->fetch()), 'Updated');
}

if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $stmt = $db->prepare('SELECT photo_path FROM instructors WHERE id=?');
    $stmt->execute([$id]);
    $p = $stmt->fetchColumn();
    if ($p && file_exists(UPLOAD_DIR_PHOTOS . $p)) @unlink(UPLOAD_DIR_PHOTOS . $p);
    $db->prepare('DELETE FROM instructors WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

respondErr('Unknown action.');
