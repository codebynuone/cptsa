<?php
// api/documents.php
// GET    ?action=list            (public)
// POST   ?action=upload          (admin — multipart/form-data, field: file)
// DELETE ?action=delete&id=      (admin)
// GET    ?action=download&id=    (public)

require_once __DIR__ . '/_core.php';
$action = $_GET['action'] ?? '';
$db     = getDB();

if ($action === 'list') {
    $stmt = $db->query('SELECT id, name, mime_type, file_size, uploaded_at FROM documents ORDER BY uploaded_at DESC');
    respondOk($stmt->fetchAll());
}

if ($action === 'upload') {
    requireAdmin();
    if (empty($_FILES['file'])) respondErr('No file uploaded.');
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) respondErr('Upload error.');
    if ($f['size'] > MAX_DOC_SIZE) respondErr('File too large (max 10 MB).');

    $allowed = ['application/pdf','application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg','image/png'];
    $mime = mime_content_type($f['tmp_name']);
    if (!in_array($mime, $allowed)) respondErr('Unsupported file type.');

    $ext  = pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'bin';
    $name = basename($f['name']);
    $file = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR_DOCS . $file;
    if (!is_dir(UPLOAD_DIR_DOCS)) mkdir(UPLOAD_DIR_DOCS, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], $dest)) respondErr('Failed to save.');

    $db->prepare('INSERT INTO documents (name, file_path, mime_type, file_size) VALUES (?,?,?,?)')
       ->execute([$name, $file, $mime, $f['size']]);
    $id = $db->lastInsertId();
    $stmt = $db->prepare('SELECT id, name, mime_type, file_size, uploaded_at FROM documents WHERE id=?');
    $stmt->execute([$id]);
    respondOk($stmt->fetch(), 'Uploaded');
}

if ($action === 'delete') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $stmt = $db->prepare('SELECT file_path FROM documents WHERE id=?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && file_exists(UPLOAD_DIR_DOCS . $row['file_path'])) {
        @unlink(UPLOAD_DIR_DOCS . $row['file_path']);
    }
    $db->prepare('DELETE FROM documents WHERE id=?')->execute([$id]);
    respondOk(null, 'Deleted');
}

if ($action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respondErr('id required.');
    $stmt = $db->prepare('SELECT * FROM documents WHERE id=?');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) respondErr('Not found.', 404);
    $path = UPLOAD_DIR_DOCS . $doc['file_path'];
    if (!file_exists($path)) respondErr('File missing.', 404);

    header('Content-Type: ' . $doc['mime_type']);
    header('Content-Disposition: attachment; filename="' . $doc['name'] . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

respondErr('Unknown action.');
