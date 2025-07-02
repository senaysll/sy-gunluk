<?php
require_once 'config.php';
require_once 'functions.php';

// Giriş kontrolü
requireLogin();

$id = $_GET['id'] ?? 0;
$token = $_GET['token'] ?? '';

// CSRF token kontrolü
if (!verifyCSRFToken($token)) {
    die('Güvenlik hatası!');
}

if ($id) {
    // Önce günlüğün kullanıcıya ait olduğunu kontrol et
    $stmt = $db->prepare("SELECT id FROM entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        // Günlüğü sil
        $stmt = $db->prepare("DELETE FROM entries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        
        header('Location: dashboard.php?success=deleted');
        exit();
    }
}

// Hatalı istek veya yetkisiz erişim
header('Location: dashboard.php');
exit();
?>