<?php
// api/auth.php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please provide username and password']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_first_login'] = (bool)$user['is_first_login'];
        
        echo json_encode([
            'success' => true,
            'role' => $user['role'],
            'is_first_login' => $user['is_first_login'],
            'redirect' => $user['is_first_login'] ? 'change_password.html' : ($user['role'] === 'teacher' ? 'admin/dashboard.html' : 'dashboard.html')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} elseif ($action === 'change_password') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($new_password) || strlen($new_password) < 4) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 4 characters']);
        exit;
    }

    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_first_login = FALSE WHERE id = ?");
    $stmt->execute([$hash, $_SESSION['user_id']]);
    
    $_SESSION['is_first_login'] = false;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password changed successfully',
        'redirect' => $_SESSION['role'] === 'teacher' ? 'admin/dashboard.html' : 'dashboard.html'
    ]);
} elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'redirect' => 'index.html']);
} elseif ($action === 'me') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['authenticated' => false]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, username, role, first_name, last_name, class_level, room, roll_number, is_first_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo json_encode(['authenticated' => true, 'user' => $user]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
