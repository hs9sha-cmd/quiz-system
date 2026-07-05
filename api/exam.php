<?php
// api/exam.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list_active') {
    $student_id = $_SESSION['user_id'];
    $stmtUser = $pdo->prepare("SELECT class_level, room FROM users WHERE id = ?");
    $stmtUser->execute([$student_id]);
    $student = $stmtUser->fetch();
    $class = $student['class_level'];
    $room = $student['room'];

    // List active exams for student matching their class and room (or empty meaning all)
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.time_limit_minutes, s.subject_name 
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.is_active = TRUE 
        AND (e.target_class_level = '' OR e.target_class_level IS NULL OR e.target_class_level = ?)
        AND (e.target_room = '' OR e.target_room IS NULL OR e.target_room = ?)
    ");
    $stmt->execute([$class, $room]);
    $exams = $stmt->fetchAll();
    
    // Check which ones the student has already taken
    $student_id = $_SESSION['user_id'];
    foreach ($exams as &$exam) {
        $checkStmt = $pdo->prepare("SELECT id, status, is_retake_allowed FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
        $checkStmt->execute([$exam['id'], $student_id]);
        $attempt = $checkStmt->fetch();
        
        $exam['can_take'] = true;
        $exam['status'] = 'not_started';
        
        if ($attempt) {
            $exam['status'] = $attempt['status'];
            if ($attempt['status'] === 'submitted' && !$attempt['is_retake_allowed']) {
                $exam['can_take'] = false;
            } elseif ($attempt['status'] === 'in_progress') {
                $exam['can_take'] = true; // Can resume
                $exam['attempt_id'] = $attempt['id'];
            }
        }
    }
    
    echo json_encode(['success' => true, 'exams' => $exams]);

} elseif ($action === 'start') {
    $exam_id = $_POST['exam_id'] ?? 0;
    $student_id = $_SESSION['user_id'];
    
    // Check if exam exists and get target score
    $stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Exam not found or inactive']);
        exit;
    }
    
    // Check if already taken or in progress
    $checkStmt = $pdo->prepare("SELECT id, status, is_retake_allowed, start_time FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY id DESC LIMIT 1");
    $checkStmt->execute([$exam_id, $student_id]);
    $attempt = $checkStmt->fetch();
    
    if ($attempt && $attempt['status'] === 'submitted' && !$attempt['is_retake_allowed']) {
        echo json_encode(['success' => false, 'message' => 'You have already completed this exam']);
        exit;
    }
    
    if ($attempt && $attempt['status'] === 'in_progress') {
        // Resume
        $attempt_id = $attempt['id'];
    } else {
        // Start new attempt
        $insertStmt = $pdo->prepare("INSERT INTO exam_attempts (exam_id, student_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')");
        $insertStmt->execute([$exam_id, $student_id]);
        $attempt_id = $pdo->lastInsertId();
    }
    
    echo json_encode([
        'success' => true, 
        'attempt_id' => $attempt_id,
        'time_limit_minutes' => $exam['time_limit_minutes']
    ]);

} elseif ($action === 'get_questions') {
    $attempt_id = $_GET['attempt_id'] ?? 0;
    $student_id = $_SESSION['user_id'];
    
    // Validate attempt
    $stmt = $pdo->prepare("
        SELECT ea.id, ea.exam_id, e.target_raw_score, e.subject_id, e.topic 
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.status = 'in_progress'
    ");
    $stmt->execute([$attempt_id, $student_id]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        echo json_encode(['success' => false, 'message' => 'Invalid attempt']);
        exit;
    }
    
    // Algorithm to select questions based on Blueprint (or legacy topic)
    $blueprint = json_decode($attempt['topic'], true);
    if (!is_array($blueprint)) {
        // Legacy fallback
        $blueprint = [
            ['topic' => $attempt['topic'], 'score' => (int)$attempt['target_raw_score']]
        ];
    }
    
    $selected_questions = [];
    
    foreach ($blueprint as $bp) {
        $topicName = $bp['topic'] ?? '';
        $target = (int)($bp['score'] ?? 0);
        
        if ($target <= 0) continue;
        
        $topicCond = (!empty($topicName) && $topicName !== '*') ? "AND topic = ?" : "";
        $qStmt = $pdo->prepare("SELECT * FROM questions WHERE subject_id = ? $topicCond");
        
        if (!empty($topicName) && $topicName !== '*') {
            $qStmt->execute([$attempt['subject_id'], $topicName]);
        } else {
            $qStmt->execute([$attempt['subject_id']]);
        }
        
        $all_questions = $qStmt->fetchAll();
        
        // Separate into 1-point and 2-point pools
        $pool_1 = [];
        $pool_2 = [];
        foreach ($all_questions as $q) {
            // Check if not already selected (to prevent duplicates if wildcard is used alongside specific topics)
            $is_duplicate = false;
            foreach($selected_questions as $sq) {
                if ($sq['id'] == $q['id']) { $is_duplicate = true; break; }
            }
            if ($is_duplicate) continue;
            
            if ($q['points'] == 1) $pool_1[] = $q;
            else if ($q['points'] == 2) $pool_2[] = $q;
        }
        
        shuffle($pool_1);
        shuffle($pool_2);
        
        $topic_score = 0;
        
        while ($topic_score < $target) {
            $diff = $target - $topic_score;
            $picked = null;
            
            if ($diff >= 2 && count($pool_2) > 0 && (rand(0, 1) == 1 || count($pool_1) == 0)) {
                $picked = array_pop($pool_2);
                $topic_score += 2;
            } elseif (count($pool_1) > 0) {
                $picked = array_pop($pool_1);
                $topic_score += 1;
            } elseif (count($pool_2) > 0 && $diff >= 2) {
                 $picked = array_pop($pool_2);
                 $topic_score += 2;
            } else {
                break; // Cannot fulfill exactly for this topic
            }
            
            if ($picked) {
                $selected_questions[] = $picked;
            }
        }
    }
    
    // Shuffle the selected questions
    shuffle($selected_questions);
    
    // Shuffle options for each question (A-E)
    $final_questions = [];
    foreach ($selected_questions as $idx => $q) {
        $options = [
            'A' => $q['option_a'],
            'B' => $q['option_b'],
            'C' => $q['option_c'],
            'D' => $q['option_d'],
            'E' => $q['option_e']
        ];
        
        $keys = array_keys($options);
        shuffle($keys);
        
        $shuffled_options = [];
        $mapping = []; // Maps original correct option to new option key
        
        $new_keys = ['A', 'B', 'C', 'D', 'E'];
        foreach ($new_keys as $i => $nk) {
            $old_key = $keys[$i];
            $shuffled_options[$nk] = $options[$old_key];
            if ($old_key === $q['correct_option']) {
                $mapping['correct'] = $nk; // Store in session later
            }
        }
        
        // Save mapping to session to prevent cheating (don't send correct answer to frontend)
        $_SESSION['exam_mapping'][$attempt_id][$q['id']] = $mapping['correct'];
        
        $final_questions[] = [
            'id' => $q['id'],
            'question_text' => $q['question_text'],
            'points' => $q['points'],
            'options' => $shuffled_options
        ];
    }
    
    echo json_encode(['success' => true, 'questions' => $final_questions]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
