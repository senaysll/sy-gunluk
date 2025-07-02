<?php
require_once 'config.php';
require_once 'functions.php';

// Giriş kontrolü
requireLogin();

// Kullanıcının temasını getir
$userTheme = getUserTheme($db, $_SESSION['user_id']);

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $mood = $_POST['mood'] ?? null;
    $is_locked = isset($_POST['is_locked']) ? 1 : 0;
    $lock_password = $_POST['lock_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // CSRF token kontrolü
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Güvenlik hatası! Lütfen tekrar deneyin.';
    } elseif (empty($title) || empty($content)) {
        $error = 'Başlık ve içerik alanları zorunludur.';
    } elseif ($is_locked && empty($lock_password)) {
        $error = 'Kilitli günlük için şifre gereklidir.';
    } elseif ($is_locked && strlen($lock_password) < 4) {
        $error = 'Kilit şifresi en az 4 karakter olmalıdır.';
    } else {
        // Kilit şifresini hash'le
        $hashedPassword = $is_locked ? hashEntryPassword($lock_password) : null;
        
        // Günlüğü kaydet
        $stmt = $db->prepare("INSERT INTO entries (user_id, title, content, mood, is_locked, lock_password) VALUES (?, ?, ?, ?, ?, ?)");
        
        try {
            $stmt->execute([$_SESSION['user_id'], $title, $content, $mood, $is_locked, $hashedPassword]);
            header('Location: dashboard.php?success=added');
            exit();
        } catch (PDOException $e) {
            $error = 'Günlük eklenirken bir hata oluştu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Günlük Ekle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <?php echo generateThemeCSS($userTheme); ?>
    <style>
        .mood-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .mood-option {
            cursor: pointer;
            padding: 8px 12px;
            border: 2px solid transparent;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            text-align: center;
            min-width: 80px;
        }
        .mood-option:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.2);
        }
        .mood-option.selected {
            border-color: var(--bs-primary);
            background: rgba(var(--bs-primary-rgb), 0.1);
            box-shadow: 0 0 10px rgba(var(--bs-primary-rgb), 0.3);
        }
        .mood-emoji {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 2px;
        }
        .mood-name {
            font-size: 0.8rem;
            font-weight: 500;
        }
        .lock-section {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            display: none;
        }
        .lock-section.show {
            display: block;
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-pencil-square"></i> Yeni Günlük Ekle
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <?php echo showError($error); ?>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Başlık</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo clean($_POST['title'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="content" class="form-label">İçerik</label>
                                <textarea class="form-control" id="content" name="content" rows="10" 
                                          required><?php echo clean($_POST['content'] ?? ''); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ruh Halim</label>
                                <small class="text-muted d-block mb-2">Bu günlüğü yazarken nasıl hissediyorsunuz?</small>
                                <input type="hidden" id="selectedMood" name="mood" value="<?php echo clean($_POST['mood'] ?? ''); ?>">
                                <div class="mood-selector">
                                    <?php foreach (getAvailableMoods() as $moodKey => $moodData): ?>
                                        <div class="mood-option" 
                                             data-mood="<?php echo $moodKey; ?>"
                                             style="color: <?php echo $moodData['color']; ?>;"
                                             onclick="selectMood('<?php echo $moodKey; ?>')">
                                            <span class="mood-emoji"><?php echo $moodData['emoji']; ?></span>
                                            <span class="mood-name"><?php echo $moodData['name']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="lockEntry" name="is_locked" 
                                           value="1" <?php echo isset($_POST['is_locked']) ? 'checked' : ''; ?>
                                           onchange="toggleLockSection()">
                                    <label class="form-check-label" for="lockEntry">
                                        <i class="bi bi-lock"></i> Bu günlüğü kilitle
                                    </label>
                                    <small class="text-muted d-block">Kilitli günlükler şifre ile korunur</small>
                                </div>
                                
                                <div class="lock-section" id="lockSection">
                                    <h6><i class="bi bi-key"></i> Kilit Ayarları</h6>
                                    <div class="mb-3">
                                        <label for="lockPassword" class="form-label">Şifre</label>
                                        <input type="password" class="form-control" id="lockPassword" name="lock_password" 
                                               placeholder="En az 4 karakter" minlength="4">
                                        <small class="text-warning">
                                            <i class="bi bi-exclamation-triangle"></i> 
                                            Şifreyi unutursanız günlüğe erişemezsiniz!
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> İptal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectMood(mood) {
            // Tüm ruh hali seçeneklerindeki selected sınıfını kaldır
            document.querySelectorAll('.mood-option').forEach(function(el) {
                el.classList.remove('selected');
            });
            
            // Eğer aynı ruh hali tekrar seçildiyse, seçimi kaldır
            const currentMood = document.getElementById('selectedMood').value;
            if (currentMood === mood) {
                document.getElementById('selectedMood').value = '';
                return;
            }
            
            // Seçilen ruh halini active yap
            document.querySelector(`[data-mood="${mood}"]`).classList.add('selected');
            
            // Hidden input'u güncelle
            document.getElementById('selectedMood').value = mood;
        }
        
        // Sayfa yüklendiğinde önceden seçili ruh halini göster
        document.addEventListener('DOMContentLoaded', function() {
            const selectedMood = document.getElementById('selectedMood').value;
            if (selectedMood) {
                document.querySelector(`[data-mood="${selectedMood}"]`).classList.add('selected');
            }
            
            // Kilit bölümünü kontrol et
            toggleLockSection();
        });
        
        function toggleLockSection() {
            const lockCheckbox = document.getElementById('lockEntry');
            const lockSection = document.getElementById('lockSection');
            const lockPassword = document.getElementById('lockPassword');
            
            if (lockCheckbox.checked) {
                lockSection.classList.add('show');
                lockPassword.required = true;
            } else {
                lockSection.classList.remove('show');
                lockPassword.required = false;
                lockPassword.value = '';
            }
        }
    </script>
</body>
</html>