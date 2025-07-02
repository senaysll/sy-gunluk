<?php
require_once 'config.php';
require_once 'functions.php';

// Giriş kontrolü
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entry_id = $_POST['entry_id'] ?? 0;
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // CSRF token kontrolü
    if (!verifyCSRFToken($csrf_token)) {
        header('Location: dashboard.php?error=csrf');
        exit();
    }
    
    // Validasyon
    if (empty($password) || empty($password_confirm)) {
        header('Location: dashboard.php?error=empty_password');
        exit();
    }
    
    if ($password !== $password_confirm) {
        header('Location: dashboard.php?error=password_mismatch');
        exit();
    }
    
    if (strlen($password) < 4) {
        header('Location: dashboard.php?error=password_short');
        exit();
    }
    
    // Günlüğü getir ve sahiplik kontrolü
    $stmt = $db->prepare("SELECT * FROM entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$entry_id, $_SESSION['user_id']]);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        header('Location: dashboard.php?error=not_found');
        exit();
    }
    
    // Zaten kilitli mi kontrol et
    if (isEntryLocked($entry)) {
        header('Location: dashboard.php?error=already_locked');
        exit();
    }
    
    // Günlüğü kilitle
    if ($action === 'lock') {
        $hashedPassword = hashEntryPassword($password);
        $stmt = $db->prepare("UPDATE entries SET is_locked = 1, lock_password = ? WHERE id = ? AND user_id = ?");
        
        try {
            $stmt->execute([$hashedPassword, $entry_id, $_SESSION['user_id']]);
            header('Location: dashboard.php?success=locked');
            exit();
        } catch (PDOException $e) {
            header('Location: dashboard.php?error=lock_failed');
            exit();
        }
    }
}

// Hatalı istek
header('Location: dashboard.php?error=invalid_request');
exit();
?>