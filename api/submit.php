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
    
    // --- Google Sheets Auto-Sync Webhook ---
    try {
        // Fetch Exam, Score Structure, Subject, and Student details
        $syncStmt = $pdo->prepare("
            SELECT e.subject_id, e.score_structure_id, 
                   e.target_raw_score as max_raw_score, e.target_net_score as max_net_score, e.google_sheet_column,
                   s.webhook_url,
                   u.username AS student_id
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            JOIN users u ON u.id = ?
            WHERE e.id = ?
        ");
        $syncStmt->execute([$student_id, $attempt['exam_id']]);
        $syncData = $syncStmt->fetch();

        if ($syncData && !empty($syncData['webhook_url']) && !empty($syncData['google_sheet_column'])) {
            $max_raw = (float)$syncData['max_raw_score'];
            $max_net = (float)$syncData['max_net_score'];
            
            // Calculate scaled score
            $scaled_score = 0;
            if ($max_raw > 0) {
                $scaled_score = round(($total_raw_score / $max_raw) * $max_net, 2);
            }
            
            $payload = json_encode([
                'student_id' => $syncData['student_id'],
                'column_name' => $syncData['google_sheet_column'],
                'score' => $scaled_score
            ]);
            
            // Fire-and-forget cURL
            $ch = curl_init($syncData['webhook_url']);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 seconds timeout to prevent freezing the UI
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Exception $e) {
        // Silently ignore webhook errors so the student still sees their score
    }
    // ----------------------------------------

    
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
