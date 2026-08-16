<?php
// ChkPoint/admin.php
session_start();
require_once '../config/db.php';

// SSO Check: Ensure user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.html");
    exit;
}

$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_subjects') {
        try {
            $pdo->beginTransaction();
            // We loop through all POSTed subjects
            foreach ($_POST['subject_id'] as $index => $sub_id) {
                $sheet_url = trim($_POST['sheet_url'][$index] ?? '');
                $webhook = trim($_POST['webhook'][$index] ?? '');
                
                $stmt = $pdo->prepare("UPDATE subjects SET google_sheet_url = ?, webhook_url = ? WHERE id = ?");
                $stmt->execute([$sheet_url, $webhook, $sub_id]);
            }
            $pdo->commit();
            $message = 'อัปเดตการเชื่อมต่อ Google Sheets ของรายวิชาสำเร็จ';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'update_mappings') {
        try {
            $pdo->beginTransaction();
            if (isset($_POST['exam_id']) && is_array($_POST['exam_id'])) {
                foreach ($_POST['exam_id'] as $index => $exam_id) {
                    $column_name = trim($_POST['sheet_column'][$index] ?? '');
                    $target_net = trim($_POST['target_net'][$index] ?? '');
                    if ($target_net === '') $target_net = null;
                    
                    $stmt = $pdo->prepare("UPDATE exams SET google_sheet_column = ?, target_net_score = ? WHERE id = ?");
                    $stmt->execute([$column_name, $target_net, $exam_id]);
                }
            }
            $pdo->commit();
            $message = 'บันทึกการจับคู่ชื่อคอลัมน์สำเร็จ';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get all subjects
$stmt = $pdo->prepare("SELECT * FROM subjects ORDER BY id ASC");
$stmt->execute();
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all exams grouped by subject
$examStmt = $pdo->prepare("SELECT e.*, s.subject_name FROM exams e JOIN subjects s ON e.subject_id = s.id ORDER BY s.id ASC, e.id DESC");
$examStmt->execute();
$exams = $examStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบซิงค์คะแนน (Sync Center) - ChkPoint</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .subject-slot, .mapping-slot {
            background-color: #f9f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .subject-slot h4, .mapping-slot h4 {
            margin-bottom: 12px;
            font-size: 16px;
            color: var(--text-primary);
        }
        .subject-row, .mapping-row {
            display: flex;
            gap: 16px;
        }
        .subject-col {
            flex: 1;
        }
        @media (max-width: 600px) {
            .subject-row, .mapping-row {
                flex-direction: column;
                gap: 8px;
            }
        }
        .table-mapping {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .table-mapping th, .table-mapping td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .table-mapping th {
            background-color: #f0f0f5;
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="brand">ศูนย์ควบคุมการซิงค์คะแนน (Sync Center)</a>
        <div class="user-menu">
            <a href="../admin/dashboard.html" style="text-decoration: none; color: var(--text-primary); font-weight: 500; border: 1px solid var(--border-color); padding: 0.5rem 1rem; border-radius: 8px; background: white;">&larr; กลับไปยังหน้าจัดการหลัก (Quiz)</a>
        </div>
    </header>
    
    <main>
        <div class="card card-wide">
            <h2>ตั้งค่าการเชื่อมต่อ Google Sheets</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <div style="margin-bottom: 40px;">
                <h3>1. ผูกรายวิชากับ Google Sheets (Webhook)</h3>
                <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 14px;">
                    ดึงรายวิชามาจากระบบ Quiz. กรุณาใส่ "ลิงก์ CSV" เพื่อให้นักเรียนดูคะแนน และ "ลิงก์ Webhook (Apps Script)" เพื่อให้ระบบส่งคะแนนกลับไปอัตโนมัติ
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_subjects">
                    
                    <?php if (empty($subjects)): ?>
                        <p style="color:red;">ยังไม่มีรายวิชาในระบบ Quiz กรุณาเพิ่มรายวิชาในระบบ Quiz ก่อนครับ</p>
                    <?php endif; ?>
                    
                    <?php foreach ($subjects as $subject): ?>
                        <div class="subject-slot">
                            <h4>วิชา: <?= htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']) ?></h4>
                            <input type="hidden" name="subject_id[]" value="<?= $subject['id'] ?>">
                            <div class="subject-row">
                                <div class="form-group subject-col" style="margin-bottom: 0;">
                                    <label>ลิงก์ Google Sheets CSV (สำหรับนักเรียนดูคะแนน)</label>
                                    <input type="url" name="sheet_url[]" value="<?= htmlspecialchars($subject['google_sheet_url'] ?? '') ?>" placeholder="https://docs.google.com/spreadsheets/...">
                                </div>
                                <div class="form-group subject-col" style="margin-bottom: 0;">
                                    <label>ลิงก์ Webhook API (สำหรับส่งคะแนนอัตโนมัติ)</label>
                                    <input type="url" name="webhook[]" value="<?= htmlspecialchars($subject['webhook_url'] ?? '') ?>" placeholder="https://script.google.com/macros/s/.../exec">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (!empty($subjects)): ?>
                        <button type="submit" class="btn">บันทึกลิงก์รายวิชาทั้งหมด</button>
                    <?php endif; ?>
                </form>
            </div>
            
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">
            
            <div style="margin-bottom: 40px;">
                <h3>2. จับคู่ชื่อการสอบ (Auto-Sync Mapping)</h3>
                <p style="color: var(--text-secondary); margin-bottom: 16px; font-size: 14px;">
                    กรุณาพิมพ์ "ชื่อคอลัมน์ใน Google Sheets" (เช่น 1, 2, สอบกลางภาค) ให้ตรงกับการสอบในระบบ Quiz<br>
                    (หากเว้นว่างไว้ ระบบจะไม่ส่งคะแนนของการสอบนี้ไปยัง Google Sheets)
                </p>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_mappings">
                    
                    <?php if (empty($exams)): ?>
                        <p>ยังไม่มีการสอบในระบบ Quiz</p>
                    <?php else: ?>
                        <table class="table-mapping">
                            <thead>
                                <tr>
                                    <th>วิชา</th>
                                    <th>ชื่อการสอบ (ใน Quiz)</th>
                                    <th>คะแนนเต็ม (ดิบ)</th>
                                    <th style="width: 20%;">คะแนนเก็บ (ที่ต้องการ)</th>
                                    <th style="width: 30%;">ชื่อคอลัมน์ (ใน Google Sheets)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exams as $ex): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ex['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($ex['title']) ?></td>
                                    <td><?= htmlspecialchars($ex['target_raw_score']) ?></td>
                                    <td>
                                        <input type="number" step="0.01" name="target_net[]" value="<?= htmlspecialchars($ex['target_net_score'] ?? '') ?>" placeholder="เช่น 15" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                    </td>
                                    <td>
                                        <input type="hidden" name="exam_id[]" value="<?= $ex['id'] ?>">
                                        <input type="text" name="sheet_column[]" value="<?= htmlspecialchars($ex['google_sheet_column'] ?? '') ?>" placeholder="เช่น '1' หรือ 'กลางภาค'" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <br>
                        <button type="submit" class="btn">บันทึกการจับคู่ทั้งหมด</button>
                    <?php endif; ?>
                </form>
            </div>
            
        </div>
    </main>
</body>
</html>
