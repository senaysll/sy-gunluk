<?php
require_once 'config.php';
require_once 'functions.php';

// Giriş kontrolü
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entry_id = $_POST['entry_id'] ?? 0;
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // CSRF token kontrolü
    if (!verifyCSRFToken($csrf_token)) {
        header('Location: dashboard.php?error=csrf');
        exit();
    }
    
    // Validasyon
    if (empty($password)) {
        header('Location: dashboard.php?error=empty_password');
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
    
    // Kilitli değilse hata
    if (!isEntryLocked($entry)) {
        header('Location: dashboard.php?error=not_locked');
        exit();
    }
    
    // Şifre kontrolü
    if (!verifyEntryPassword($entry, $password)) {
        header('Location: dashboard.php?error=wrong_password');
        exit();
    }
    
    // İşlem türüne göre
    if ($action === 'unlock_temp') {
        // Geçici kilit açma - session'a ekle
        addUnlockedEntry($entry_id);
        header('Location: dashboard.php?success=unlocked_temp');
        exit();
        
    } elseif ($action === 'unlock_permanent') {
        // Kalıcı kilit kaldırma
        $stmt = $db->prepare("UPDATE entries SET is_locked = 0, lock_password = NULL WHERE id = ? AND user_id = ?");
        
        try {
            $stmt->execute([$entry_id, $_SESSION['user_id']]);
            
            // Session'dan da kaldır
            if (isset($_SESSION['unlocked_entries'])) {
                $key = array_search($entry_id, $_SESSION['unlocked_entries']);
                if ($key !== false) {
                    unset($_SESSION['unlocked_entries'][$key]);
                }
            }
            
            header('Location: dashboard.php?success=unlocked_permanent');
            exit();
        } catch (PDOException $e) {
            header('Location: dashboard.php?error=unlock_failed');
            exit();
        }
    }
}

// Hatalı istek
header('Location: dashboard.php?error=invalid_request');
exit();
?>