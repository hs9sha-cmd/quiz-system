<?php
require_once '/Applications/XAMPP/xamppfiles/htdocs/Quiz/config/db.php';
try {
    $stmt = $pdo->prepare("INSERT INTO exams (subject_id, score_structure_id, topic, title, target_raw_score, time_limit_minutes, target_class_level, target_room, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 1, '[]', 'Test Exam PHP', 10, 30, '', '', 1]);
    echo "Success";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
