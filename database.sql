-- 1. users table (Students and Teachers)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE, -- For students: Student ID
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher') NOT NULL DEFAULT 'student',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    class_level VARCHAR(50), -- e.g., ม.1, ม.2
    room VARCHAR(50), -- e.g., 1, 2, 3
    roll_number INT,
    is_first_login BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. subjects table
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_code VARCHAR(20) NOT NULL,
    subject_name VARCHAR(255) NOT NULL,
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. score_structures table
CREATE TABLE IF NOT EXISTS score_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    title VARCHAR(255) NOT NULL, -- e.g., "บทที่ 1", "กลางภาค"
    max_raw_score INT NOT NULL, -- Max raw points (e.g., 20)
    max_net_score DECIMAL(5,2) NOT NULL, -- Target scaled points (e.g., 10)
    order_index INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- 4. questions table
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    option_e TEXT NOT NULL,
    correct_option CHAR(1) NOT NULL, -- A, B, C, D, or E
    points INT NOT NULL DEFAULT 1, -- 1 or 2 points
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- 5. exams table
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    score_structure_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    target_raw_score INT NOT NULL, -- The target sum of points for the exam
    time_limit_minutes INT NOT NULL, -- 30, 45, 60, 90
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (score_structure_id) REFERENCES score_structures(id) ON DELETE CASCADE
);

-- 6. exam_attempts table
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME,
    status ENUM('in_progress', 'submitted', 'time_up') NOT NULL DEFAULT 'in_progress',
    raw_score INT DEFAULT 0,
    is_retake_allowed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. student_answers table
CREATE TABLE IF NOT EXISTS student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option CHAR(1), -- A, B, C, D, E (can be null if unattempted)
    is_correct BOOLEAN DEFAULT FALSE,
    points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- Insert Default Teacher Account for Testing
-- Password is '12345' (bcrypt hash)
INSERT INTO users (username, password_hash, role, first_name, last_name, is_first_login) 
VALUES ('krum', '$2y$10$Gk8VH/Uuip.7O2qj0tmLbOl8JWfEBlGs4HVtTU188rPdh.R66PpwW', 'teacher', 'Admin', 'Teacher', FALSE);

-- Insert Dummy Subject
INSERT INTO subjects (subject_code, subject_name, teacher_id) VALUES ('CS101', 'Computer Science 101', 1);

-- Insert Dummy Score Structure
INSERT INTO score_structures (subject_id, title, max_raw_score, max_net_score, order_index) VALUES (1, 'Midterm', 20, 10.00, 1);
