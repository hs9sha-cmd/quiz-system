<?php
// api/krum.php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'add_question') {
    $subject_id = $_POST['subject_id'] ?? 1; // Default to subject 1
    $topic = trim($_POST['topic'] ?? 'ทั่วไป');
    $points = (int)($_POST['points'] ?? 1);
    $text = trim($_POST['question_text'] ?? '');
    $optA = trim($_POST['option_a'] ?? '');
    $optB = trim($_POST['option_b'] ?? '');
    $optC = trim($_POST['option_c'] ?? '');
    $optD = trim($_POST['option_d'] ?? '');
    $optE = trim($_POST['option_e'] ?? '');
    $correct = trim($_POST['correct_option'] ?? 'A');

    if (empty($text) || empty($optA)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO questions (subject_id, topic, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$subject_id, $topic, $text, $optA, $optB, $optC, $optD, $optE, $correct, $points]);
    
    echo json_encode(['success' => true, 'message' => 'เพิ่มข้อสอบสำเร็จ']);

} elseif ($action === 'import_questions') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์']);
        exit;
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    if ($handle !== FALSE) {
        // Skip header row
        fgetcsv($handle, 1000, ",");
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO questions (subject_id, topic, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $count = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Ensure we have enough columns (at least up to Correct Option)
                if (count($data) >= 9) {
                    $topic = trim($data[0] ?: 'ทั่วไป');
                    $points = (int)($data[1] ?: 1);
                    $text = trim($data[2]);
                    $optA = trim($data[3]);
                    $optB = trim($data[4]);
                    $optC = trim($data[5]);
                    $optD = trim($data[6]);
                    $optE = trim($data[7]);
                    $correct = trim(strtoupper($data[8]));
                    
                    if (!empty($text) && !empty($optA)) {
                        $stmt->execute([1, $topic, $text, $optA, $optB, $optC, $optD, $optE, $correct, $points]);
                        $count++;
                    }
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "นำเข้าข้อสอบสำเร็จจำนวน $count ข้อ"]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
        }
        fclose($handle);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถอ่านไฟล์ได้']);
    }

} elseif ($action === 'list_questions') {
    $stmt = $pdo->query("SELECT * FROM questions ORDER BY topic, id DESC");
    echo json_encode(['success' => true, 'questions' => $stmt->fetchAll()]);

} elseif ($action === 'get_form_options') {
    $stmt1 = $pdo->query("SELECT DISTINCT topic FROM questions WHERE topic != '' ORDER BY topic");
    $topics = $stmt1->fetchAll(PDO::FETCH_COLUMN);

    $stmt2 = $pdo->query("SELECT DISTINCT class_level, room FROM users WHERE role='student' AND class_level != '' ORDER BY class_level, room");
    $rooms = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'topics' => $topics, 'rooms' => $rooms]);

} elseif ($action === 'create_exam') {
    $subject_id = 1;
    $score_structure_id = 1; // Default to 1, no longer used in UI
    
    $topic = trim($_POST['topic_blueprint'] ?? $_POST['topic'] ?? '');
    $title = trim($_POST['title'] ?? 'การสอบใหม่');
    $target_raw_score = (int)($_POST['target_raw_score'] ?? 10);
    $time_limit = (int)($_POST['time_limit_minutes'] ?? 30);
    
    $target_room_val = trim($_POST['target_room'] ?? '');
    if ($target_room_val === '' || $target_room_val === 'all') {
        $target_class_level = '';
        $target_room = '';
    } else {
        $parts = explode('/', $target_room_val);
        $target_class_level = $parts[0] ?? '';
        $target_room = $parts[1] ?? '';
    }
    
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO exams (subject_id, score_structure_id, topic, title, target_raw_score, time_limit_minutes, target_class_level, target_room, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$subject_id, $score_structure_id, $topic, $title, $target_raw_score, $time_limit, $target_class_level, $target_room, $is_active]);
    
    echo json_encode(['success' => true, 'message' => 'เปิดการสอบสำเร็จ']);

} elseif ($action === 'list_exams') {
    $stmt = $pdo->query("SELECT e.*, s.title as slot_name FROM exams e JOIN score_structures s ON e.score_structure_id = s.id ORDER BY e.id DESC");
    echo json_encode(['success' => true, 'exams' => $stmt->fetchAll()]);

} elseif ($action === 'toggle_exam') {
    $exam_id = (int)$_POST['exam_id'];
    $is_active = (int)$_POST['is_active'];
    $stmt = $pdo->prepare("UPDATE exams SET is_active = ? WHERE id = ?");
    $stmt->execute([$is_active, $exam_id]);
    echo json_encode(['success' => true]);

} elseif ($action === 'delete_exam') {
    $exam_id = (int)$_POST['exam_id'];
    try {
        $pdo->beginTransaction();
        // Delete related student answers first
        $pdo->prepare("DELETE FROM student_answers WHERE attempt_id IN (SELECT id FROM exam_attempts WHERE exam_id = ?)")->execute([$exam_id]);
        $pdo->prepare("DELETE FROM exam_attempts WHERE exam_id = ?")->execute([$exam_id]);
        $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$exam_id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'ลบการสอบสำเร็จ']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }

} elseif ($action === 'delete_questions') {
    $ids_str = $_POST['ids'] ?? '';
    if (empty($ids_str)) {
        echo json_encode(['success' => false, 'message' => 'ไม่มีข้อสอบที่ถูกเลือก']);
        exit;
    }
    
    // Sanitize to integers
    $ids = array_map('intval', explode(',', $ids_str));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        echo json_encode(['success' => true, 'message' => 'ลบข้อสอบสำเร็จแล้ว']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'get_score_structures') {
    $stmt = $pdo->query("SELECT * FROM score_structures WHERE subject_id = 1 ORDER BY order_index ASC");
    echo json_encode(['success' => true, 'structures' => $stmt->fetchAll()]);

} elseif ($action === 'export_scores') {
    $exam_id = (int)$_GET['exam_id'];
    $room = trim($_GET['room'] ?? 'all');
    
    // Fetch exam info
    $stmt = $pdo->prepare("SELECT title FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        die("Exam not found");
    }
    
    // Fetch scores
    $query = "
        SELECT u.student_number, u.first_name, u.last_name, u.class_level, u.room, MAX(ea.raw_score) as raw_score 
        FROM users u 
        JOIN exam_attempts ea ON u.id = ea.student_id 
        WHERE ea.exam_id = ? AND u.role = 'student' AND ea.status = 'submitted'
    ";
    
    $params = [$exam_id];
    
    if ($room !== 'all' && $room !== '') {
        $parts = explode('/', $room);
        $query .= " AND u.class_level = ? AND u.room = ?";
        $params[] = $parts[0] ?? '';
        $params[] = $parts[1] ?? '';
    }
    
    $query .= " GROUP BY u.id ORDER BY u.class_level, u.room, CAST(u.student_number AS UNSIGNED)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSV
    $filename = "คะแนน_" . $exam['title'] . ($room !== 'all' ? "_$room" : "") . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    // Add UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['เลขที่', 'ชื่อ-สกุล', 'ชั้น', 'ห้อง', 'คะแนน']);
    
    foreach ($results as $row) {
        fputcsv($output, [
            $row['student_number'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['class_level'],
            $row['room'],
            $row['raw_score']
        ]);
    }
    fclose($output);
    exit;

} elseif ($action === 'export_scores_json') {
    $exam_id = (int)$_GET['exam_id'];
    $room = trim($_GET['room'] ?? 'all');
    
    $stmt = $pdo->prepare("SELECT title FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Exam not found']);
        exit;
    }
    
    $query = "
        SELECT CAST(u.roll_number AS UNSIGNED) as 'เลขที่', u.username as 'รหัสนักเรียน', 
               CONCAT(u.first_name, ' ', u.last_name) as 'ชื่อ-สกุล', 
               CONCAT(u.class_level, '/', u.room) as 'ชั้น', 
               MAX(ea.raw_score) as 'คะแนน' 
        FROM users u 
        JOIN exam_attempts ea ON u.id = ea.student_id 
        WHERE ea.exam_id = ? AND u.role = 'student' AND ea.status = 'submitted'
    ";
    
    $params = [$exam_id];
    
    if ($room !== 'all' && $room !== '') {
        $parts = explode('/', $room);
        $query .= " AND u.class_level = ? AND u.room = ?";
        $params[] = $parts[0] ?? '';
        $params[] = $parts[1] ?? '';
    }
    
    $query .= " GROUP BY u.id ORDER BY u.class_level, u.room, CAST(u.roll_number AS UNSIGNED)";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'exam_title' => $exam['title'], 'data' => $results]);

} elseif ($action === 'reset_password') {
    $student_id = (int)$_POST['student_id'];
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_first_login = TRUE WHERE id = ? AND role = 'student'");
    $stmt->execute([$hash, $student_id]);
    echo json_encode(['success' => true, 'message' => 'รีเซ็ตรหัสผ่านเป็น 1234 สำเร็จ']);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
