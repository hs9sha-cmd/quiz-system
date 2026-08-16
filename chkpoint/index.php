<?php
// ChkPoint/index.php
session_start();
require_once '../config/db.php';

// SSO Check: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit;
}

// Redirect teachers to the Admin panel
if (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher') {
    header("Location: admin.php");
    exit;
}

// Ensure first login has changed their password via Quiz
if (isset($_SESSION['is_first_login']) && $_SESSION['is_first_login']) {
    header("Location: ../change_password.html");
    exit;
}

// Fetch student details from Quiz DB
$stmt = $pdo->prepare("SELECT username, first_name, last_name, class_level, room, roll_number FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../index.html");
    exit;
}

$student_id = $user['username'];
$student_fullname = $user['first_name'] . ' ' . $user['last_name'];
$student_class = $user['class_level'] . '/' . $user['room'];

$error = '';
$student_data = null;
$headers = [];

// Get all active subjects (that have a google sheet URL)
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE google_sheet_url IS NOT NULL AND google_sheet_url != '' ORDER BY id ASC");
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($subjects)) {
    $error = 'ระบบยังไม่ได้ตั้งค่าลิงก์รายวิชา กรุณาติดต่อคุณครูผู้สอน';
    $active_subject = null;
} else {
    // Determine active subject from query string or default to first
    $active_subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : $subjects[0]['id'];
    $active_subject = null;
    
    foreach ($subjects as $subject) {
        if ($subject['id'] == $active_subject_id) {
            $active_subject = $subject;
            break;
        }
    }
    
    // Fallback if ID is invalid
    if (!$active_subject) {
        $active_subject = $subjects[0];
        $active_subject_id = $active_subject['id'];
    }
    
    $sheet_url = $active_subject['google_sheet_url'];

    // Fetch CSV
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $csv_data = @file_get_contents($sheet_url, false, $context);
    
    if ($csv_data === false) {
        $error = 'ไม่สามารถดึงข้อมูลจากวิชา ' . htmlspecialchars($active_subject['subject_name']) . ' ได้';
    } else {
        // Parse CSV
        $lines = explode("\n", $csv_data);
        if (count($lines) > 0) {
            $headers = str_getcsv($lines[0]);
            
            // Search for the student by ID (Column B, index 1)
            for ($i = 1; $i < count($lines); $i++) {
                if (trim($lines[$i]) === '') continue;
                
                $row = str_getcsv($lines[$i]);
                if (isset($row[1]) && trim($row[1]) === $student_id) {
                    $student_data = $row;
                    break;
                }
            }
            
            if (!$student_data) {
                $error = 'ไม่พบข้อมูลคะแนนของนักเรียนในวิชา ' . htmlspecialchars($active_subject['subject_name']);
            }
        } else {
            $error = 'รูปแบบไฟล์คะแนนไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการเรียน - ChkPoint</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }
        .tab-btn {
            padding: 8px 16px;
            border-radius: 20px;
            background-color: #f0f0f5;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            background-color: #e5e5ea;
            color: var(--text-primary);
        }
        .tab-btn.active {
            background-color: var(--text-primary);
            color: #ffffff;
        }
        .back-to-quiz {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background-color: #f0f0f5;
            color: var(--text-primary);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }
        .back-to-quiz:hover {
            background-color: #e5e5ea;
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="brand">Student Grade Portal</a>
        <div class="user-menu">
            <span><?= htmlspecialchars($student_fullname) ?> (<?= htmlspecialchars($student_id) ?>)</span>
            <a href="../api/auth.php?action=logout" onclick="event.preventDefault(); fetch('../api/auth.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=logout'}).then(()=>window.location='../index.html');">ออกจากระบบ</a>
        </div>
    </header>
    
    <main>
        <div class="card card-wide">
            <a href="../dashboard.html" class="back-to-quiz">← กลับไปหน้าระบบข้อสอบ (Quiz)</a>
            
            <h2 style="margin-top: 10px;">ผลการเรียนรายวิชา</h2>
            
            <?php if (!empty($subjects)): ?>
                <div class="tabs">
                    <?php foreach ($subjects as $subject): ?>
                        <a href="?subject=<?= $subject['id'] ?>" class="tab-btn <?= ($subject['id'] == $active_subject_id) ? 'active' : '' ?>">
                            <?= htmlspecialchars($subject['subject_code'] . ' ' . $subject['subject_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($student_data && !empty($headers)): ?>
                <div class="student-info">
                    <div class="info-item">
                        <div class="info-label">ชื่อ - สกุล</div>
                        <div class="info-value"><?= htmlspecialchars($student_fullname) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">รหัสประจำตัว</div>
                        <div class="info-value"><?= htmlspecialchars($student_id) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">ชั้น</div>
                        <div class="info-value"><?= htmlspecialchars($student_class) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">เลขที่</div>
                        <div class="info-value"><?= htmlspecialchars($user['roll_number'] ?? $student_data[0] ?? '-') ?></div>
                    </div>
                </div>
                
                <div class="grades-container">
                    <h3 style="text-align: left; margin-bottom: 16px; font-size: 18px;">
                        คะแนนวิชา <?= htmlspecialchars($active_subject['subject_name']) ?>
                    </h3>
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th>หัวข้อการประเมิน</th>
                                <th style="text-align: right;">คะแนนที่ได้</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Loop columns from E (index 4) to O (index 14)
                            for ($i = 4; $i <= 14; $i++): 
                                // Check if the column exists in headers and has a name
                                if (isset($headers[$i]) && trim($headers[$i]) !== ''):
                                    $header_name = trim($headers[$i]);
                                    $score = isset($student_data[$i]) ? trim($student_data[$i]) : '-';
                                    
                                    // Highlight "Grade" or "100" column
                                    $is_total = (stripos($header_name, 'Grade') !== false || stripos($header_name, '100') !== false || stripos($header_name, 'เกรด') !== false);
                            ?>
                                <tr>
                                    <td class="<?= $is_total ? 'grade-total' : '' ?>">
                                        <?= htmlspecialchars($header_name) ?>
                                    </td>
                                    <td class="<?= $is_total ? 'grade-total' : '' ?>" style="text-align: right;">
                                        <?= htmlspecialchars($score) ?>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endfor; 
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
