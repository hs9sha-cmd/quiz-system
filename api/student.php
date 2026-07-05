<?php
// api/student.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$student_id = $_SESSION['user_id'];

if ($action === 'dashboard') {
    $stmt = $pdo->prepare("
        SELECT e.title, e.topic, e.target_raw_score, MAX(ea.raw_score) as raw_score 
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.status = 'submitted'
        GROUP BY e.id
        ORDER BY MAX(ea.end_time) DESC
    ");
    $stmt->execute([$student_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
