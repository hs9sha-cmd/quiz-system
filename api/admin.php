<?php
// api/admin.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list_students') {
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, class_level, room, roll_number FROM users WHERE role = 'student' ORDER BY class_level, room, roll_number");
    $stmt->execute();
    echo json_encode(['success' => true, 'students' => $stmt->fetchAll()]);
    
} elseif ($action === 'allow_retake') {
    $attempt_id = $_POST['attempt_id'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE exam_attempts SET is_retake_allowed = TRUE WHERE id = ?");
    $stmt->execute([$attempt_id]);
    
    echo json_encode(['success' => true, 'message' => 'Retake allowed']);
    
} elseif ($action === 'add_student') {
    $username = $_POST['username'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $class_level = $_POST['class_level'] ?? '';
    $room = $_POST['room'] ?? '';
    $roll_number = $_POST['roll_number'] ?? '';
    
    $hash = password_hash('1234', PASSWORD_BCRYPT); // Default password rule
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name, class_level, room, roll_number, is_first_login) VALUES (?, ?, 'student', ?, ?, ?, ?, ?, TRUE)");
        $stmt->execute([$username, $hash, $first_name, $last_name, $class_level, $room, $roll_number]);
        echo json_encode(['success' => true, 'message' => 'Student added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding student. Student ID might already exist.']);
    }
} elseif ($action === 'import_students') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please upload a valid CSV file']);
        exit;
    }

    $fileTmpPath = $_FILES['csv_file']['tmp_name'];
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    $successCount = 0;
    $errorCount = 0;

    if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
        // Check for BOM and remove it if present to prevent header reading issues
        $bom = fread($handle, 3);
        if ($bom != "\xEF\xBB\xBF") {
            rewind($handle);
        }
        
        $header = fgetcsv($handle, 1000, ","); // Skip header
        
        // Use ON DUPLICATE KEY UPDATE to allow re-importing without errors
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name, class_level, room, roll_number, is_first_login) VALUES (?, ?, 'student', ?, ?, ?, ?, ?, TRUE) ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), class_level=VALUES(class_level), room=VALUES(room), roll_number=VALUES(roll_number)");

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) < 4) continue;
            
            $username = trim($data[0]);
            $fullName = trim($data[1]);
            $classroom = trim($data[2]);
            $rollNumber = trim($data[3]);
            
            if (empty($username) || empty($fullName)) continue;

            // Parse Full Name (split by first space)
            // Handle multiple spaces properly
            $nameParts = preg_split('/\s+/', $fullName, 2);
            $firstName = trim($nameParts[0]);
            $lastName = isset($nameParts[1]) ? trim($nameParts[1]) : '';

            // Parse Classroom (split by /)
            $classParts = explode("/", $classroom, 2);
            $classLevel = trim($classParts[0]);
            $room = isset($classParts[1]) ? trim($classParts[1]) : '';

            try {
                $stmt->execute([$username, $hash, $firstName, $lastName, $classLevel, $room, $rollNumber]);
                $successCount++;
            } catch (PDOException $e) {
                $errorCount++;
            }
        }
        fclose($handle);
    }

    echo json_encode(['success' => true, 'message' => "นำเข้าสำเร็จ $successCount รายการ, ผิดพลาด $errorCount รายการ"]);
} elseif ($action === 'delete_students') {
    $ids = $_POST['ids'] ?? '';
    if (empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No students selected']);
        exit;
    }
    
    // IDs are comma separated
    $idArray = explode(',', $ids);
    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
    
    try {
        $pdo->beginTransaction();
        
        // Delete student answers
        $stmt = $pdo->prepare("DELETE FROM student_answers WHERE attempt_id IN (SELECT id FROM exam_attempts WHERE student_id IN ($placeholders))");
        $stmt->execute($idArray);
        
        // Delete exam attempts
        $stmt = $pdo->prepare("DELETE FROM exam_attempts WHERE student_id IN ($placeholders)");
        $stmt->execute($idArray);
        
        // Delete manual scores
        $stmt = $pdo->prepare("DELETE FROM manual_scores WHERE student_id IN ($placeholders)");
        $stmt->execute($idArray);
        
        // Delete from users
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'student'");
        $stmt->execute($idArray);
        
        $deletedCount = $stmt->rowCount();
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => "ลบนักเรียนสำเร็จ $deletedCount รายการ"]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error deleting students: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
