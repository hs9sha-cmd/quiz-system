<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // We create a new connection to allow multiple statements if needed, or just use exec()
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    
    $sql = "
    SET FOREIGN_KEY_CHECKS = 0;
    TRUNCATE TABLE student_answers;
    TRUNCATE TABLE exam_attempts;
    TRUNCATE TABLE exams;
    TRUNCATE TABLE questions;
    TRUNCATE TABLE score_structures;
    TRUNCATE TABLE subjects;
    DELETE FROM users WHERE role != 'teacher';
    
    INSERT INTO subjects (id, subject_code, subject_name, teacher_id) 
    SELECT 1, 'GEN', 'วิชาทั่วไป', id FROM users WHERE role = 'teacher' ORDER BY id ASC LIMIT 1;
    
    INSERT INTO score_structures (id, subject_id, title, max_raw_score, max_net_score, order_index)
    VALUES (1, 1, 'ทั่วไป', 100, 100.00, 1);

    SET FOREIGN_KEY_CHECKS = 1;
    ";
    
    $pdo->exec($sql);
    
    echo json_encode(['success' => true, 'message' => 'ฐานข้อมูลถูกล้างเรียบร้อยแล้ว']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
