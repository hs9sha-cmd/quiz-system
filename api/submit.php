<?php
// api/submit.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'submit_exam') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    $answers = json_decode($_POST['answers'] ?? '[]', true); // Format: [{"id": 1, "answer": "C"}, ...]
    $student_id = $_SESSION['user_id'];
    
    // Validate attempt
    $stmt = $pdo->prepare("SELECT ea.*, e.target_raw_score FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.id = ? AND ea.student_id = ? AND ea.status = 'in_progress'");
    $stmt->execute([$attempt_id, $student_id]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        echo json_encode(['success' => false, 'message' => 'Invalid or already submitted attempt']);
        exit;
    }
    
    $total_raw_score = 0;
    
    // Process answers
    foreach ($answers as $ans) {
        $q_id = $ans['id'];
        $selected = $ans['answer'];
        
        // Retrieve correct answer from session mapping
        $correct_mapped = $_SESSION['exam_mapping'][$attempt_id][$q_id] ?? null;
        
        // Get question points
        $qStmt = $pdo->prepare("SELECT points FROM questions WHERE id = ?");
        $qStmt->execute([$q_id]);
        $question = $qStmt->fetch();
        
        $is_correct = false;
        $points_earned = 0;
        
        if ($correct_mapped && $selected === $correct_mapped) {
            $is_correct = true;
            $points_earned = $question['points'] ?? 1;
            $total_raw_score += $points_earned;
        }
        
        // Save student answer
        $insertStmt = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct, points_earned) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->execute([$attempt_id, $q_id, $selected, $is_correct ? 1 : 0, $points_earned]);
    }
    
    // Update attempt
    $updateStmt = $pdo->prepare("UPDATE exam_attempts SET status = 'submitted', end_time = NOW(), raw_score = ? WHERE id = ?");
    $updateStmt->execute([$total_raw_score, $attempt_id]);
    
    // Clear session mapping
    unset($_SESSION['exam_mapping'][$attempt_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Exam submitted successfully',
        'raw_score' => $total_raw_score,
        'target_score' => $attempt['target_raw_score']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
