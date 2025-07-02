<?php
require_once 'config.php';
require_once 'functions.php';

// Fonksiyon tanımlı değilse geçici tanımlama
if (!function_exists('renderLockIcon')) {
    function renderLockIcon($entry) {
        if (isset($entry['is_locked']) && $entry['is_locked'] == 1) {
            return '<i class="bi bi-lock-fill text-warning" title="Kilitli Günlük"></i> ';
        }
        return '';
    }
}

if (!function_exists('isEntryLocked')) {
    function isEntryLocked($entry) {
        return isset($entry['is_locked']) && $entry['is_locked'] == 1;
    }
}

if (!function_exists('hideLockedContent')) {
    function hideLockedContent($entry, $unlocked = false) {
        if (!isEntryLocked($entry) || $unlocked) {
            return $entry;
        }
        $entry['content'] = '🔒 Bu günlük kilitli. İçeriği görmek için şifre gerekli.';
        return $entry;
    }
}

if (!function_exists('isEntryUnlockedInSession')) {
    function isEntryUnlockedInSession($entryId) {
        return isset($_SESSION['unlocked_entries']) && in_array($entryId, $_SESSION['unlocked_entries']);
    }
}

if (!function_exists('getUserLockedEntriesCount')) {
    function getUserLockedEntriesCount($db, $userId) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM entries WHERE user_id = ? AND is_locked = 1");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'];
    }
}

// Giriş kontrolü
requireLogin();

// Kullanıcının temasını getir
$userTheme = getUserTheme($db, $_SESSION['user_id']);

// Arama parametrelerini al
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$mood_filter = $_GET['mood'] ?? '';
$lock_filter = $_GET['lock'] ?? '';
$date_filter = $_GET['date'] ?? '';

// SQL sorgusu hazırla
$sql = "SELECT * FROM entries WHERE user_id = ?";
$params = [$_SESSION['user_id']];

// Arama filtresi ekle
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR content LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Ruh hali filtresi ekle
if (!empty($mood_filter)) {
    $sql .= " AND mood = ?";
    $params[] = $mood_filter;
}

// Kilit filtresi ekle
if (!empty($lock_filter)) {
    if ($lock_filter === 'locked') {
        $sql .= " AND is_locked = 1";
    } elseif ($lock_filter === 'unlocked') {
        $sql .= " AND is_locked = 0";
    }
}

// Tarih filtresi ekle
if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $sql .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'yesterday':
            $sql .= " AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $sql .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $sql .= " AND YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
            break;
        case 'this_month':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        case 'last_month':
            $sql .= " AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
        case 'this_year':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE())";
            break;
    }
}

// Sıralama ekle
switch ($sort) {
    case 'oldest':
        $sql .= " ORDER BY created_at ASC";
        break;
    case 'updated':
        $sql .= " ORDER BY updated_at DESC";
        break;
    case 'title':
        $sql .= " ORDER BY title ASC";
        break;
    default: // newest
        $sql .= " ORDER BY created_at DESC";
        break;
}

// Günlükleri getir
$stmt = $db->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

// Toplam günlük sayısı
$totalStmt = $db->prepare("SELECT COUNT(*) as total FROM entries WHERE user_id = ?");
$totalStmt->execute([$_SESSION['user_id']]);
$totalEntries = $totalStmt->fetch()['total'];

// Ruh hali istatistikleri
$moodStats = getUserMoodStats($db, $_SESSION['user_id'], 30);
$availableMoods = getAvailableMoods();

// Kilitli günlük sayısı
$lockedEntriesCount = getUserLockedEntriesCount($db, $_SESSION['user_id']);

// Başarı mesajı kontrolü
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Hata mesajlarını tanımla
$errorMessages = [
    'csrf' => 'Güvenlik hatası! Lütfen tekrar deneyin.',
    'empty_password' => 'Şifre boş olamaz.',
    'password_mismatch' => 'Şifreler eşleşmiyor.',
    'password_short' => 'Şifre en az 4 karakter olmalıdır.',
    'not_found' => 'Günlük bulunamadı.',
    'already_locked' => 'Günlük zaten kilitli.',
    'not_locked' => 'Günlük zaten kilitli değil.',
    'wrong_password' => 'Hatalı şifre!',
    'lock_failed' => 'Günlük kilitlenirken hata oluştu.',
    'unlock_failed' => 'Kilit açılırken hata oluştu.',
    'invalid_request' => 'Geçersiz istek.'
];

// Başarı mesajlarını tanımla
$successMessages = [
    'added' => 'Günlük başarıyla eklendi!',
    'deleted' => 'Günlük başarıyla silindi!',
    'updated' => 'Günlük başarıyla güncellendi!',
    'locked' => 'Günlük başarıyla kilitlendi!',
    'unlocked_temp' => 'Günlük kilidi geçici olarak açıldı.',
    'unlocked_permanent' => 'Günlük kilidi kalıcı olarak kaldırıldı!'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Günlüklerim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <?php echo generateThemeCSS($userTheme); ?>
    <style>
        .dropdown-menu {
    z-index: 1060 !important;
    position: fixed !important;
}

.btn-group.show .dropdown-menu {
    z-index: 1060 !important;
    position: fixed !important;
}

.dropdown-menu.show {
    z-index: 1060 !important;
    position: fixed !important;
}

.card-footer .btn-group {
    position: static !important;
}

        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        .entry-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .entry-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,.1);
        }
        .entry-content {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .search-section {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .mood-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            margin: 2px;
        }
        .mood-stats {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .mood-filter-btn {
            margin: 2px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        .mood-filter-btn:hover {
            transform: scale(1.05);
        }
        .mood-filter-btn.active {
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }
        .entry-locked {
            opacity: 0.7;
            position: relative;
        }
        .entry-locked::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 193, 7, 0.1) 10px,
                rgba(255, 193, 7, 0.1) 20px
            );
            pointer-events: none;
            border-radius: 0.375rem;
        }
        .dropdown-menu {
            z-index: 1050 !important;
        }
        .date-filter-section {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .date-filter-btn {
            margin: 3px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        .date-filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .date-filter-btn.active {
            background: var(--bs-primary) !important;
            color: white !important;
            border-color: var(--bs-primary) !important;
            box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.4);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-journal-text"></i> Günlüğüm
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white me-2" href="settings.php">
                    <i class="bi bi-gear"></i> Ayarlar
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?php echo clean($_SESSION['username']); ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success && isset($successMessages[$success])): ?>
            <?php echo showSuccess($successMessages[$success]); ?>
        <?php endif; ?>
        
        <?php if ($error && isset($errorMessages[$error])): ?>
            <?php echo showError($errorMessages[$error]); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col">
                <h2>Günlüklerim 
                    <small class="text-muted">(<?php echo $totalEntries; ?> kayıt)</small>
                    <?php if ($lockedEntriesCount > 0): ?>
                        <span class="badge bg-warning text-dark ms-2">
                            <i class="bi bi-lock-fill"></i> <?php echo $lockedEntriesCount; ?> Kilitli
                        </span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="col text-end">
                <a href="add_entry.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i> Yeni Günlük Ekle
                </a>
            </div>
        </div>

        <!-- Ruh Hali İstatistikleri -->
        <?php if (!empty($moodStats)): ?>
        <div class="mood-stats">
            <h5 class="mb-3">
                <i class="bi bi-emoji-smile"></i> Son 30 Günün Ruh Hali Dağılımı
            </h5>
            <div class="row">
                <?php foreach ($moodStats as $stat): ?>
                    <?php $moodInfo = getMoodInfo($stat['mood']); ?>
                    <?php if ($moodInfo): ?>
                        <div class="col-md-3 col-sm-4 col-6 mb-2">
                            <div class="text-center">
                                <span style="font-size: 1.5rem;"><?php echo $moodInfo['emoji']; ?></span>
                                <div style="font-size: 0.9rem; color: <?php echo $moodInfo['color']; ?>;">
                                    <strong><?php echo $stat['count']; ?></strong> <?php echo $moodInfo['name']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tarih Filtresi Bölümü -->
        <div class="date-filter-section">
            <h6 class="mb-3">
                <i class="bi bi-calendar-range"></i> Tarih Filtresi
            </h6>
            <div class="d-flex flex-wrap">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => ''])); ?>" 
                   class="btn btn-outline-light date-filter-btn <?php echo empty($date_filter) ? 'active' : ''; ?>">
                    <i class="bi bi-calendar"></i> Tümü
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'today'])); ?>" 
                   class="btn btn-outline-primary date-filter-btn <?php echo $date_filter == 'today' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-day"></i> Bugün
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'yesterday'])); ?>" 
                   class="btn btn-outline-secondary date-filter-btn <?php echo $date_filter == 'yesterday' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-minus"></i> Dün
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'this_week'])); ?>" 
                   class="btn btn-outline-success date-filter-btn <?php echo $date_filter == 'this_week' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-week"></i> Bu Hafta
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'last_week'])); ?>" 
                   class="btn btn-outline-info date-filter-btn <?php echo $date_filter == 'last_week' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-week"></i> Geçen Hafta
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'this_month'])); ?>" 
                   class="btn btn-outline-warning date-filter-btn <?php echo $date_filter == 'this_month' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-month"></i> Bu Ay
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'last_month'])); ?>" 
                   class="btn btn-outline-danger date-filter-btn <?php echo $date_filter == 'last_month' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-month"></i> Geçen Ay
                </a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['date' => 'this_year'])); ?>" 
                   class="btn btn-outline-dark date-filter-btn <?php echo $date_filter == 'this_year' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-range"></i> Bu Yıl
                </a>
            </div>
        </div>

        <!-- Arama ve Filtreleme Bölümü -->
        <div class="search-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Başlık veya içerikte ara..." 
                               value="<?php echo clean($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="sort">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>En Yeni</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>En Eski</option>
                        <option value="updated" <?php echo $sort == 'updated' ? 'selected' : ''; ?>>Son Güncellenen</option>
                        <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Başlığa Göre (A-Z)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="mood">
                        <option value="">Tüm Ruh Halleri</option>
                        <?php foreach ($availableMoods as $moodKey => $moodData): ?>
                            <option value="<?php echo $moodKey; ?>" <?php echo $mood_filter == $moodKey ? 'selected' : ''; ?>>
                                <?php echo $moodData['emoji']; ?> <?php echo $moodData['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="lock">
                        <option value="">Tümü</option>
                        <option value="locked" <?php echo $lock_filter == 'locked' ? 'selected' : ''; ?>>🔒 Kilitli</option>
                        <option value="unlocked" <?php echo $lock_filter == 'unlocked' ? 'selected' : ''; ?>>🔓 Kilitli Değil</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filtrele
                    </button>
                    <?php if (!empty($search) || $sort != 'newest' || !empty($mood_filter) || !empty($lock_filter) || !empty($date_filter)): ?>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Temizle
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Ruh Hali Hızlı Filtreleme -->
            <div class="mt-3">
                <small class="text-muted d-block mb-2">Hızlı Ruh Hali Filtresi:</small>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['mood' => ''])); ?>" class="btn btn-outline-secondary mood-filter-btn btn-sm <?php echo empty($mood_filter) ? 'active' : ''; ?>">
                    Hepsi
                </a>
                <?php foreach ($availableMoods as $moodKey => $moodData): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['mood' => $moodKey])); ?>" 
                       class="btn mood-filter-btn btn-sm <?php echo $mood_filter == $moodKey ? 'active' : ''; ?>"
                       style="background-color: <?php echo $moodData['color']; ?>20; color: <?php echo $moodData['color']; ?>; border-color: <?php echo $moodData['color']; ?>;">
                        <?php echo $moodData['emoji']; ?> <?php echo $moodData['name']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($search) || !empty($mood_filter) || !empty($lock_filter) || !empty($date_filter)): ?>
                <div class="mt-3">
                    <small class="text-muted">
                        <?php 
                        $filters = [];
                        if (!empty($search)) $filters[] = '"<strong>' . clean($search) . '</strong>"';
                        if (!empty($mood_filter)) $filters[] = '<strong>' . getMoodEmoji($mood_filter) . ' ' . getMoodName($mood_filter) . '</strong> ruh hali';
                        if (!empty($lock_filter)) $filters[] = '<strong>' . ($lock_filter == 'locked' ? '🔒 Kilitli' : '🔓 Kilitli Değil') . '</strong> günlükler';
                        if (!empty($date_filter)) {
                            $dateLabels = [
                                'today' => 'Bugün',
                                'yesterday' => 'Dün', 
                                'this_week' => 'Bu Hafta',
                                'last_week' => 'Geçen Hafta',
                                'this_month' => 'Bu Ay',
                                'last_month' => 'Geçen Ay',
                                'this_year' => 'Bu Yıl'
                            ];
                            $filters[] = '<strong>' . $dateLabels[$date_filter] . '</strong>';
                        }
                        echo implode(' ve ', $filters);
                        ?> için <?php echo count($entries); ?> sonuç bulundu.
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($entries)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> 
                <?php if (!empty($search)): ?>
                    Arama kriterlerinize uygun günlük bulunamadı.
                    <br>
                    <a href="dashboard.php">Tüm günlükleri göster</a>
                <?php else: ?>
                    Henüz hiç günlük eklemediniz.
                    <br>
                    <a href="add_entry.php">İlk günlüğünüzü ekleyin!</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    // Kilitli günlük kontrolü
                    $isLocked = isEntryLocked($entry);
                    $isUnlocked = isEntryUnlockedInSession($entry['id']);
                    $displayEntry = hideLockedContent($entry, $isUnlocked);
                    
                    // Arama terimini vurgula
                    $highlightedTitle = $displayEntry['title'];
                    $highlightedContent = $displayEntry['content'];
                    
                    if (!empty($search) && (!$isLocked || $isUnlocked)) {
                        $highlightedTitle = preg_replace(
                            '/(' . preg_quote($search, '/') . ')/i', 
                            '<span class="highlight">$1</span>', 
                            $displayEntry['title']
                        );
                        $highlightedContent = preg_replace(
                            '/(' . preg_quote($search, '/') . ')/i', 
                            '<span class="highlight">$1</span>', 
                            $displayEntry['content']
                        );
                    }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card entry-card h-100 <?php echo $isLocked ? 'entry-locked' : ''; ?>">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <?php echo renderLockIcon($entry); ?>
                                    <?php echo $highlightedTitle; ?>
                                    <?php if ($entry['mood']): ?>
                                        <span class="float-end">
                                            <?php echo renderMoodBadge($entry['mood']); ?>
                                        </span>
                                    <?php endif; ?>
                                </h5>
                                <p class="card-text entry-content">
                                    <?php echo nl2br(substr(strip_tags($highlightedContent), 0, 150)); ?>...
                                </p>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> <?php echo formatDate($entry['created_at']); ?>
                                        <?php if ($entry['updated_at'] != $entry['created_at']): ?>
                                            <br>
                                            <i class="bi bi-pencil-square"></i> Güncellendi: <?php echo formatDate($entry['updated_at']); ?>
                                        <?php endif; ?>
                                    </small>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <?php if ($isLocked && !$isUnlocked): ?>
                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                            data-bs-target="#unlockModal<?php echo $entry['id']; ?>">
                                        <i class="bi bi-unlock"></i> Kilidi Aç
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                        <i class="bi bi-pencil"></i> Düzenle (Kilitli)
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#viewModal<?php echo $entry['id']; ?>">
                                        <i class="bi bi-eye"></i> Görüntüle
                                    </button>
                                    <a href="edit_entry.php?id=<?php echo $entry['id']; ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> Düzenle
                                    </a>
                                <?php endif; ?>
                                
                                <div class="btn-group dropup float-end">
                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" 
                                data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                       <ul class="dropdown-menu" style="z-index: 1055 !important;">
                                        <?php if ($isLocked): ?>
                                            <li>
                                                <button class="dropdown-item text-success" 
                                                        onclick="openModal('unlockPermanentModal<?php echo $entry['id']; ?>')">
                                                    <i class="bi bi-unlock"></i> Kilidi Kaldır
                                                </button>
                                            </li>
                                        <?php else: ?>
                                            <li>
                                                <button class="dropdown-item text-warning" 
                                                        onclick="openModal('lockModal<?php echo $entry['id']; ?>')">
                                                    <i class="bi bi-lock"></i> Kilitle
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" 
                                               href="delete_entry.php?id=<?php echo $entry['id']; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                               onclick="return confirm('Bu günlüğü silmek istediğinize emin misiniz?');">
                                                <i class="bi bi-trash"></i> Sil
                                            </a>
                                        </li>
                                    </ul>
                                </div>

                               
                            </div>
                        </div>
                    </div>

                    <!-- Görüntüleme Modal -->
                    <div class="modal fade" id="viewModal<?php echo $entry['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <?php echo renderLockIcon($entry); ?>
                                        <?php echo clean($entry['title']); ?>
                                        <?php if ($entry['mood']): ?>
                                            <?php echo renderMoodBadge($entry['mood']); ?>
                                        <?php endif; ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted">
                                        <i class="bi bi-calendar"></i> <?php echo formatDate($entry['created_at']); ?>
                                        <?php if ($entry['updated_at'] != $entry['created_at']): ?>
                                            <br>
                                            <i class="bi bi-pencil-square"></i> Son güncelleme: <?php echo formatDate($entry['updated_at']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <hr>
                                    <div style="white-space: pre-wrap;"><?php echo clean($displayEntry['content']); ?></div>
                                </div>
                                <div class="modal-footer">
                                    <?php if (!$isLocked || $isUnlocked): ?>
                                        <a href="edit_entry.php?id=<?php echo $entry['id']; ?>" class="btn btn-warning">
                                            <i class="bi bi-pencil"></i> Düzenle
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-warning" disabled>
                                            <i class="bi bi-lock"></i> Düzenle (Kilitli)
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kilit Açma Modal (Geçici) -->
                    <div class="modal fade" id="unlockModal<?php echo $entry['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-unlock text-warning"></i> Kilidi Aç
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="unlock_entry.php" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="action" value="unlock_temp">
                                        
                                        <p class="text-muted">Bu günlüğü görüntülemek için şifre girin:</p>
                                        <div class="mb-3">
                                            <input type="password" class="form-control" name="password" 
                                                   placeholder="Günlük şifresi" required>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-info-circle"></i> Kilit geçici olarak açılacak
                                        </small>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-unlock"></i> Aç
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Kilitleme Modal -->
                    <div class="modal fade" id="lockModal<?php echo $entry['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-lock text-warning"></i> Günlüğü Kilitle
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="lock_entry.php" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="action" value="lock">
                                        
                                        <p class="text-muted">Bu günlük için bir şifre belirleyin:</p>
                                        <div class="mb-3">
                                            <input type="password" class="form-control" name="password" 
                                                   placeholder="Şifre (en az 4 karakter)" minlength="4" required>
                                        </div>
                                        <div class="mb-3">
                                            <input type="password" class="form-control" name="password_confirm" 
                                                   placeholder="Şifre tekrar" required>
                                        </div>
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i> 
                                            Şifreyi kaybederseniz günlüğe erişemezsiniz!
                                        </small>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="bi bi-lock"></i> Kilitle
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Kilit Kaldırma Modal -->
                    <div class="modal fade" id="unlockPermanentModal<?php echo $entry['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi bi-unlock text-success"></i> Kilidi Kaldır
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="unlock_entry.php" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="action" value="unlock_permanent">
                                        
                                        <p class="text-muted">Kilidi kalıcı olarak kaldırmak için şifre girin:</p>
                                        <div class="mb-3">
                                            <input type="password" class="form-control" name="password" 
                                                   placeholder="Günlük şifresi" required>
                                        </div>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle"></i> Kilit kalıcı olarak kaldırılacak
                                        </small>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-unlock"></i> Kaldır
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openModal(modalId) {
            // Önce tüm açık dropdown'ları kapat
            const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
            openDropdowns.forEach(function(dropdown) {
                dropdown.classList.remove('show');
            });
            
            // Dropdown toggle butonlarını da kapat
            const toggleButtons = document.querySelectorAll('.dropdown-toggle[aria-expanded="true"]');
            toggleButtons.forEach(function(button) {
                button.setAttribute('aria-expanded', 'false');
                button.classList.remove('show');
            });
            
            // Kısa bir beklemeden sonra modal'ı aç
            setTimeout(function() {
                const modal = new bootstrap.Modal(document.getElementById(modalId));
                modal.show();
            }, 100);
        }
        
        // Form validasyonu
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('submit', function(e) {
                if (e.target.matches('form[action="lock_entry.php"]')) {
                    const password = e.target.querySelector('input[name="password"]').value;
                    const passwordConfirm = e.target.querySelector('input[name="password_confirm"]').value;
                    
                    if (password !== passwordConfirm) {
                        e.preventDefault();
                        alert('Şifreler eşleşmiyor!');
                        return false;
                    }
                    
                    if (password.length < 4) {
                        e.preventDefault();
                        alert('Şifre en az 4 karakter olmalıdır!');
                        return false;
                    }
                    
                    if (!confirm('Bu günlüğü kilitlemek istediğinize emin misiniz?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>