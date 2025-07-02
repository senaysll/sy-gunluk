<?php
// Kullanıcı giriş kontrolü
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Giriş yapmamış kullanıcıları yönlendir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// XSS koruması için metin temizleme
function clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// CSRF token oluştur
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token doğrula
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Tarih formatla
function formatDate($date) {
    $timestamp = strtotime($date);
    return date('d.m.Y H:i', $timestamp);
}

// Kullanıcı bilgilerini getir
function getUserById($db, $userId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Hata mesajı göster
function showError($message) {
    return '<div class="alert alert-danger">' . clean($message) . '</div>';
}

// Başarı mesajı göster
function showSuccess($message) {
    return '<div class="alert alert-success">' . clean($message) . '</div>';
}

// TEMA FONKSİYONLARI

// Mevcut tema listesi
function getAvailableThemes() {
    return [
        'default' => [
            'name' => 'Varsayılan',
            'primary' => '#0d6efd',
            'background' => '#f8f9fa',
            'text' => '#212529'
        ],
        'dark' => [
            'name' => 'Koyu Tema',
            'primary' => '#6f42c1',
            'background' => '#212529',
            'text' => '#ffffff'
        ],
        'nature' => [
            'name' => 'Doğa',
            'primary' => '#198754',
            'background' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'text' => '#ffffff'
        ],
        'sunset' => [
            'name' => 'Gün Batımı',
            'primary' => '#fd7e14',
            'background' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            'text' => '#ffffff'
        ],
        'ocean' => [
            'name' => 'Okyanus',
            'primary' => '#0dcaf0',
            'background' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'text' => '#ffffff'
        ],
        'forest' => [
            'name' => 'Orman',
            'primary' => '#198754',
            'background' => 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)',
            'text' => '#ffffff'
        ]
    ];
}

// Kullanıcının mevcut temasını getir
function getUserTheme($db, $userId) {
    $stmt = $db->prepare("SELECT background_theme FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['background_theme'] : 'default';
}

// Kullanıcının temasını güncelle
function updateUserTheme($db, $userId, $theme) {
    $availableThemes = array_keys(getAvailableThemes());
    
    if (!in_array($theme, $availableThemes)) {
        return false;
    }
    
    $stmt = $db->prepare("UPDATE users SET background_theme = ? WHERE id = ?");
    return $stmt->execute([$theme, $userId]);
}

// RUH HALİ FONKSİYONLARI

// Mevcut ruh hali seçenekleri
function getAvailableMoods() {
    return [
        'happy' => [
            'emoji' => '😄',
            'name' => 'Mutlu',
            'color' => '#ffc107'
        ],
        'excited' => [
            'emoji' => '🤩',
            'name' => 'Heyecanlı',
            'color' => '#ff6b6b'
        ],
        'love' => [
            'emoji' => '🥰',
            'name' => 'Aşık',
            'color' => '#e91e63'
        ],
        'grateful' => [
            'emoji' => '🙏',
            'name' => 'Minnettar',
            'color' => '#4caf50'
        ],
        'peaceful' => [
            'emoji' => '😌',
            'name' => 'Huzurlu',
            'color' => '#2196f3'
        ],
        'thoughtful' => [
            'emoji' => '🤔',
            'name' => 'Düşünceli',
            'color' => '#9c27b0'
        ],
        'neutral' => [
            'emoji' => '😐',
            'name' => 'Normal',
            'color' => '#6c757d'
        ],
        'tired' => [
            'emoji' => '😴',
            'name' => 'Yorgun',
            'color' => '#795548'
        ],
        'stressed' => [
            'emoji' => '😰',
            'name' => 'Stresli',
            'color' => '#ff9800'
        ],
        'sad' => [
            'emoji' => '😢',
            'name' => 'Üzgün',
            'color' => '#607d8b'
        ],
        'angry' => [
            'emoji' => '😠',
            'name' => 'Kızgın',
            'color' => '#f44336'
        ],
        'confused' => [
            'emoji' => '😕',
            'name' => 'Karışık',
            'color' => '#9e9e9e'
        ]
    ];
}

// Ruh hali bilgisini getir
function getMoodInfo($mood) {
    $moods = getAvailableMoods();
    return isset($moods[$mood]) ? $moods[$mood] : null;
}

// Ruh hali emoji'sini getir
function getMoodEmoji($mood) {
    $moodInfo = getMoodInfo($mood);
    return $moodInfo ? $moodInfo['emoji'] : '';
}

// Ruh hali adını getir
function getMoodName($mood) {
    $moodInfo = getMoodInfo($mood);
    return $moodInfo ? $moodInfo['name'] : '';
}

// Ruh hali rengini getir
function getMoodColor($mood) {
    $moodInfo = getMoodInfo($mood);
    return $moodInfo ? $moodInfo['color'] : '#6c757d';
}

// Ruh hali HTML'ini oluştur
function renderMoodBadge($mood) {
    if (!$mood) return '';
    
    $moodInfo = getMoodInfo($mood);
    if (!$moodInfo) return '';
    
    return '<span class="mood-badge" style="background-color: ' . $moodInfo['color'] . '20; color: ' . $moodInfo['color'] . '; border: 1px solid ' . $moodInfo['color'] . '40;">' . 
           $moodInfo['emoji'] . ' ' . $moodInfo['name'] . 
           '</span>';
}

// Kullanıcının ruh hali istatistiklerini getir
function getUserMoodStats($db, $userId, $days = 30) {
    $sql = "SELECT mood, COUNT(*) as count 
            FROM entries 
            WHERE user_id = ? AND mood IS NOT NULL 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY mood 
            ORDER BY count DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $days]);
    return $stmt->fetchAll();
}

// KİLİTLİ GÜNLÜK FONKSİYONLARI

// Günlük kilit durumunu kontrol et
function isEntryLocked($entry) {
    return isset($entry['is_locked']) && $entry['is_locked'] == 1;
}

// Günlük kilit şifresini doğrula
function verifyEntryPassword($entry, $password) {
    if (!isEntryLocked($entry) || empty($entry['lock_password'])) {
        return true; // Kilitli değilse veya şifre yoksa erişim serbest
    }
    
    return password_verify($password, $entry['lock_password']);
}

// Günlük kilit şifresini hash'le
function hashEntryPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Kilitli günlük içeriğini gizle
function hideLockedContent($entry, $unlocked = false) {
    if (!isEntryLocked($entry) || $unlocked) {
        return $entry;
    }
    
    // Kilitli günlük için içeriği gizle
    $entry['content'] = '🔒 Bu günlük kilitli. İçeriği görmek için şifre gerekli.';
    return $entry;
}

// Kilit simgesi HTML'i
function renderLockIcon($entry) {
    if (isEntryLocked($entry)) {
        return '<i class="bi bi-lock-fill text-warning" title="Kilitli Günlük"></i> ';
    }
    return '';
}

// Günlük kilitleme/kilit açma
function toggleEntryLock($db, $entryId, $userId, $password = null) {
    // Önce günlüğün kullanıcıya ait olduğunu kontrol et
    $stmt = $db->prepare("SELECT * FROM entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$entryId, $userId]);
    $entry = $stmt->fetch();
    
    if (!$entry) {
        return false;
    }
    
    // Kilitli ise kilidi aç, değilse kilitle
    if (isEntryLocked($entry)) {
        // Kilidi aç
        $stmt = $db->prepare("UPDATE entries SET is_locked = 0, lock_password = NULL WHERE id = ? AND user_id = ?");
        return $stmt->execute([$entryId, $userId]);
    } else {
        // Kilitle
        if (empty($password)) {
            return false; // Şifre gerekli
        }
        
        $hashedPassword = hashEntryPassword($password);
        $stmt = $db->prepare("UPDATE entries SET is_locked = 1, lock_password = ? WHERE id = ? AND user_id = ?");
        return $stmt->execute([$hashedPassword, $entryId, $userId]);
    }
}

// Kullanıcının kilitli günlük sayısını getir
function getUserLockedEntriesCount($db, $userId) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM entries WHERE user_id = ? AND is_locked = 1");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

// Session'da açılmış kilitleri sakla
function addUnlockedEntry($entryId) {
    if (!isset($_SESSION['unlocked_entries'])) {
        $_SESSION['unlocked_entries'] = [];
    }
    $_SESSION['unlocked_entries'][] = $entryId;
}

// Günlüğün session'da açık olup olmadığını kontrol et
function isEntryUnlockedInSession($entryId) {
    return isset($_SESSION['unlocked_entries']) && in_array($entryId, $_SESSION['unlocked_entries']);
}

// Session'dan açılmış kilitleri temizle
function clearUnlockedEntries() {
    unset($_SESSION['unlocked_entries']);
}

// Tema CSS'ini oluştur
function generateThemeCSS($theme) {
    $themes = getAvailableThemes();
    
    if (!isset($themes[$theme])) {
        $theme = 'default';
    }
    
    $themeData = $themes[$theme];
    
    // Tema bazlı renk ayarları
    // rgb(177 35 35 / 88%) !important
    $isDarkTheme = in_array($theme, ['dark', 'nature', 'sunset', 'ocean', 'forest']);
    $formBg = $isDarkTheme ? 'rgb(255 149 149 / 88%) ' : 'rgba(255, 255, 255, 0.9)';
    $formBgFocus = $isDarkTheme ? 'rgba(255, 255, 255, 0.25)' : 'rgba(255, 255, 255, 1)';
    $formTextColor = $isDarkTheme ? '#7d0909' : '#212529';
    $formBorder = $isDarkTheme ? 'rgba(255, 255, 255, 0.3)' : 'rgba(0, 0, 0, 0.15)';
    $placeholderColor = $isDarkTheme ? 'rgba(255, 255, 255, 0.7)' : '#6c757d';
    $mutedTextColor = $isDarkTheme ? 'rgba(255, 255, 255, 0.7)' : '#6c757d';
    
    $css = "<style>
        :root {
            --bs-primary: {$themeData['primary']};
            --theme-background: {$themeData['background']};
            --theme-text: {$themeData['text']};
        }
        
        body {
            background: var(--theme-background) !important;
            color: var(--theme-text) !important;
        }
        
        .navbar-brand, .navbar-text {
            color: var(--theme-text) !important;
        }
        
        .card {
            background: rgba(255, 255, 255, " . ($theme === 'default' ? '1' : '0.1') . ") !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        
        .search-section {
            background: rgba(255, 255, 255, " . ($theme === 'default' ? '1' : '0.1') . ") !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        
        .login-container, .register-container {
            background: rgba(255, 255, 255, " . ($theme === 'default' ? '1' : '0.1') . ") !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        
        .modal-content {
            background: rgba(255, 255, 255, " . ($theme === 'default' ? '1' : '0.1') . ") !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        
        .btn-primary {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
        }
        
        .navbar {
            background-color: var(--bs-primary) !important;
        }
        
        .form-control, .form-select {
            background: {$formBg} !important;
            border: 1px solid {$formBorder} !important;
            color: {$formTextColor} !important;
        }
        
        .form-control:focus, .form-select:focus {
            background: {$formBgFocus} !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25) !important;
            color: {$formTextColor} !important;
        }
        
        .form-control::placeholder {
            color: {$placeholderColor} !important;
        }
        
        .form-select option {
            background: {$formBg} !important;
            color: {$formTextColor} !important;
        }
        
        .input-group-text {
            background: {$formBg} !important;
            border: 1px solid {$formBorder} !important;
            color: {$formTextColor} !important;
        }
        
        .text-muted {
            color: {$mutedTextColor} !important;
        }
        
        .mood-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 2px;
        }
        
        /* Dropdown menüler için */
        .dropdown-menu {
            background: rgba(255, 255, 255, " . ($theme === 'default' ? '1' : '0.95') . ") !important;
            backdrop-filter: blur(10px);
            border: 1px solid {$formBorder} !important;
        }
        
        .dropdown-item {
            color: {$formTextColor} !important;
        }
        
        .dropdown-item:hover {
            background: rgba(var(--bs-primary-rgb), 0.1) !important;
            color: {$formTextColor} !important;
        }
        
        /* Alert'ler için */
        .alert {
            background: rgba(255, 255, 255, " . ($theme === 'default' ? '1' : '0.15') . ") !important;
            backdrop-filter: blur(10px);
            border: 1px solid {$formBorder} !important;
            color: {$formTextColor} !important;
        }
        
        .alert-success {
            border-color: #198754 !important;
            background: rgba(25, 135, 84, 0.1) !important;
        }
        
        .alert-danger {
            border-color: #dc3545 !important;
            background: rgba(220, 53, 69, 0.1) !important;
        }
        
        .alert-info {
            border-color: #0dcaf0 !important;
            background: rgba(13, 202, 240, 0.1) !important;
        }
        
        /* Badge'ler için */
        .badge {
            backdrop-filter: blur(5px);
        }
        
        /* Form check'ler için */
        .form-check-input:checked {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
        }
        
        .form-check-label {
            color: {$formTextColor} !important;
        }
        
        /* Navbar link'ler için */
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .nav-link:hover {
            color: rgba(255, 255, 255, 1) !important;
        }
    </style>";
    
    return $css;
}
?>