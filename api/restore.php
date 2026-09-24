<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$fileTmpPath = $_FILES['backup_file']['tmp_name'];
$sql = file_get_contents($fileTmpPath);

if (empty(trim($sql))) {
    echo json_encode(['success' => false, 'message' => 'Empty file']);
    exit;
}

try {
    // We create a new connection specifically for restoration to allow multiple statements
    $pdo_restore = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => true, // Crucial for executing multiple statements (a dump file)
    ]);
    
    $pdo_restore->exec($sql);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
