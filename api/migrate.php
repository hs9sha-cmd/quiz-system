<?php
// api/migrate.php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/plain');

try {
    // 1. Add 'topic' to questions
    try {
        $pdo->exec("ALTER TABLE questions ADD COLUMN topic VARCHAR(255) DEFAULT 'ทั่วไป' AFTER subject_id");
        echo "Added 'topic' to questions.\n";
    } catch(PDOException $e) { echo "Skip adding 'topic' (may already exist).\n"; }

    // 2. Update exams to have target_class_level and target_room
    try {
        $pdo->exec("ALTER TABLE exams ADD COLUMN target_class_level VARCHAR(50) DEFAULT NULL AFTER time_limit_minutes");
        $pdo->exec("ALTER TABLE exams ADD COLUMN target_room VARCHAR(50) DEFAULT NULL AFTER target_class_level");
        echo "Added target_class_level and target_room to exams.\n";
    } catch(PDOException $e) { echo "Skip adding targets to exams (may already exist).\n"; }

    // 3. Create manual_scores table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS manual_scores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            score_structure_id INT NOT NULL,
            score DECIMAL(5,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (score_structure_id) REFERENCES score_structures(id) ON DELETE CASCADE,
            UNIQUE KEY unique_manual_score (student_id, score_structure_id)
        )
    ");
    echo "Ensured manual_scores table exists.\n";
    
    // Ensure 8 slots exist for Subject 1 (CS101) as default
    // Check current count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM score_structures WHERE subject_id = 1");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count < 8) {
        $needed = 8 - $count;
        $startIndex = $count + 1;
        for ($i = 0; $i < $needed; $i++) {
            $idx = $startIndex + $i;
            $title = "ช่องคะแนนที่ " . $idx;
            // Provide dummy max scores (e.g. 10 per slot)
            $pdo->exec("INSERT INTO score_structures (subject_id, title, max_raw_score, max_net_score, order_index) VALUES (1, '$title', 10, 10, $idx)");
        }
        echo "Filled score_structures up to 8 slots for Subject 1.\n";
    } else {
        echo "Score structures already have at least 8 slots for Subject 1.\n";
    }

    echo "Migration completed successfully.";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
