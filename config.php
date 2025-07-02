<?php
// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'stayland_sena');
define('DB_PASS', 'kvbe5m86d1hmyfk2');
define('DB_NAME', 'stayland_gunlukgreat');

// PDO ile veritabanı bağlantısı
try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Oturum başlat
session_start();

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');
?>