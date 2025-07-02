<?php
require_once 'config.php';
require_once 'functions.php';

// Giriş kontrolü
requireLogin();

$error = '';
$success = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $theme = $_POST['theme'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // CSRF token kontrolü
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Güvenlik hatası! Lütfen tekrar deneyin.';
    } else {
        // Temayı güncelle
        if (updateUserTheme($db, $_SESSION['user_id'], $theme)) {
            $success = 'Tema ayarlarınız başarıyla güncellendi!';
        } else {
            $error = 'Tema güncellenirken bir hata oluştu.';
        }
    }
}

// Mevcut tema ve mevcut tema listesi
$currentTheme = getUserTheme($db, $_SESSION['user_id']);
$availableThemes = getAvailableThemes();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar - Günlüğüm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <?php echo generateThemeCSS($currentTheme); ?>
    <style>
        .theme-preview {
            width: 100%;
            height: 100px;
            border-radius: 10px;
            margin-bottom: 10px;
            border: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .theme-preview:hover {
            transform: scale(1.05);
            border-color: var(--bs-primary);
        }
        
        .theme-preview.active {
            border-color: var(--bs-primary);
            box-shadow: 0 0 15px rgba(var(--bs-primary-rgb), 0.5);
        }
        
        .theme-card {
            text-align: center;
            margin-bottom: 20px;
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
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?php echo clean($_SESSION['username']); ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-gear"></i> Ayarlar
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <?php echo showError($error); ?>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <?php echo showSuccess($success); ?>
                        <?php endif; ?>

                        <h5 class="mb-4">
                            <i class="bi bi-palette"></i> Arka Plan Teması
                        </h5>

                        <form method="POST" action="" id="themeForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="theme" id="selectedTheme" value="<?php echo $currentTheme; ?>">

                            <div class="row">
                                <?php foreach ($availableThemes as $themeKey => $themeData): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="theme-card">
                                            <div class="theme-preview <?php echo $themeKey === $currentTheme ? 'active' : ''; ?>" 
                                                 style="background: <?php echo $themeData['background']; ?>;"
                                                 data-theme="<?php echo $themeKey; ?>"
                                                 onclick="selectTheme('<?php echo $themeKey; ?>')">
                                                <div class="d-flex align-items-center justify-content-center h-100">
                                                    <div style="color: <?php echo $themeData['text']; ?>;">
                                                        <i class="bi bi-journal-text" style="font-size: 2rem;"></i>
                                                        <br>
                                                        <small>Günlüğüm</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <h6><?php echo $themeData['name']; ?></h6>
                                            <?php if ($themeKey === $currentTheme): ?>
                                                <small class="text-success">
                                                    <i class="bi bi-check-circle"></i> Mevcut Tema
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center mt-4">
                                <a href="dashboard.php" class="btn btn-secondary me-2">
                                    <i class="bi bi-arrow-left"></i> Geri Dön
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Temayı Kaydet
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
        function selectTheme(theme) {
            // Tüm önizlemelerdeki active sınıfını kaldır
            document.querySelectorAll('.theme-preview').forEach(function(el) {
                el.classList.remove('active');
            });
            
            // Seçilen temayı active yap
            document.querySelector(`[data-theme="${theme}"]`).classList.add('active');
            
            // Hidden input'u güncelle
            document.getElementById('selectedTheme').value = theme;
        }
        
        // Form gönderilmeden önce onay iste
        document.getElementById('themeForm').addEventListener('submit', function(e) {
            const selectedTheme = document.getElementById('selectedTheme').value;
            const currentTheme = '<?php echo $currentTheme; ?>';
            
            if (selectedTheme === currentTheme) {
                e.preventDefault();
                alert('Aynı tema zaten seçili!');
                return false;
            }
            
            return confirm('Temayı değiştirmek istediğinize emin misiniz?');
        });
    </script>
</body>
</html>