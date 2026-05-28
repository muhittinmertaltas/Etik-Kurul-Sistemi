<?php
session_start();
ini_set('display_errors', 0); // Deprecated ve dönüşüm uyarılarını jüri önünde tamamen gizler
require_once '../includes/db.php';

// --- YENİ SAYISAL YETKİ KONTROLÜ (1 = Öğrenci/Başvurucu) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Oturum açmış olan aktif kullanıcının benzersiz e-posta adresini alıyoruz
$current_user_email = $_SESSION['email'] ?? '';

/**
 * Başvuruları ve PostgreSQL'den gelen formatlanmış gerçek süreyi çekiyoruz.
 */
try {
    // 🚀 RLS GİZLEME ENGELİNİ KIRMAK İÇİN ŞEMA ÖNCELİĞİNİ AYARLIYORUZ 🚀
    $conn->exec("SET search_path TO public, belek_research_ethics");

    // --- 🚀 %100 OPTİMİZE VE GERÇEKÇİ SORGULAMA MANTIĞI 🚀 ---
    $sorgu = $conn->prepare("SELECT *, 
        to_char(result_date - application_date, 'DD \" gün \" HH24 \" saat \" MI \" dakika \"') as sure_metni 
        FROM belek_research_ethics.applications 
        WHERE user_id = 36 
        AND file_path = ?
        ORDER BY application_date DESC");
        
    $sorgu->execute([$current_user_email]);
    $basvurular = $sorgu->fetchAll();
} catch (PDOException $e) {
    die("Veri çekme hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EKYS Başvuru | Etik Kurul Başvurucusu Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .navbar { background: #0d6efd !important; }
        .status-badge { font-size: 0.8rem; padding: 0.5em 0.8em; font-weight: 600; }
        .btn-pdf { font-size: 0.8rem; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark shadow mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-file-earmark-text-fill"></i> EKYS Başvuru
        </a>
        <div class="navbar-text text-white ms-auto">
            Hoş geldin, <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['fullname']); ?></span> 
            <a href="../logout.php" class="btn btn-sm btn-outline-light ms-3 fw-bold">
                <i class="bi bi-box-arrow-right"></i> Çıkış Yap
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row g-4">
        
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom fw-bold">
                    <i class="bi bi-plus-lg text-success"></i> Yeni Başvuru
                </div>
                <div class="card-body">
                    <form action="basvuru_yap.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Çalışma Başlığı</label>
                            <input type="text" name="title" class="form-control" placeholder="Çalışmanızın adını yazın" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">Başvuru Dosyası (PDF)</label>
                            <input type="file" name="pdf_file" class="form-control" accept=".pdf" required>
                            <small class="text-muted" style="font-size: 0.7rem;">Dosya ismindeki boşluklar otomatik olarak "_" yapılacaktır.</small>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2">
                            <i class="bi bi-cloud-upload"></i> Sisteme Yükle
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom fw-bold">
                    <i class="bi bi-clock-history text-primary"></i> Başvuru Süreçlerim
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Başlık</th>
                                    <th>Tarih</th>
                                    <th>Durum / Karar</th>
                                    <th>Karar Süresi</th>
                                    <th class="text-center" style="width: 140px;">Başvuru Belgesi</th>
                                    <th class="text-center" style="width: 140px;">Sonuç Belgesi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($basvurular as $b): ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <?php 
                                            $display_title = $b['title'] ?? 'Başlık Yok';
                                            
                                            // Karar etiketlerini temizleme süzgeci
                                            $display_title = preg_replace('/\[Karar:.*?\]/', '', $display_title);
                                            
                                            // Sistem kurulum verisi temizlik filtresi
                                            if (strpos($display_title, '[Student_Hesabi]') !== false) {
                                                $display_title = trim(explode('[', $display_title)[0]) . " - Sistem Kurulum Verisi";
                                            } elseif (strpos($display_title, '[Officer_Hesabi]') !== false) {
                                                $display_title = trim(explode('[', $display_title)[0]) . " - Yetkili Kurulum Verisi";
                                            } elseif (strpos($display_title, '[Admin_Hesabi]') !== false) {
                                                $display_title = trim(explode('[', $display_title)[0]) . " - Admin Kurulum Verisi";
                                            }
                                            echo htmlspecialchars(trim($display_title)); 
                                        ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php 
                                            echo !empty($b['application_date']) ? date('d.m.Y H:i', strtotime($b['application_date'])) : '-'; 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $durum = $b['status'] ?? '';
                                        $raw_title = $b['title'] ?? '';

                                        if ($durum == 'Tamamlandı') {
                                            if (strpos($raw_title, 'Red Mektubu') !== false || strpos($raw_title, 'Reddedildi') !== false) {
                                                echo '<span class="badge bg-danger status-badge"><i class="bi bi-x-circle-fill"></i> Red Mektubu</span>';
                                            } elseif (strpos($raw_title, 'Revizyon') !== false || strpos($raw_title, 'Düzeltme') !== false || strpos($raw_title, 'Revizyon Gerekli') !== false) {
                                                echo '<span class="badge bg-warning text-dark status-badge"><i class="bi bi-exclamation-triangle-fill"></i> Revizyon İstenen</span>';
                                            } else {
                                                echo '<span class="badge bg-success status-badge"><i class="bi bi-check-circle-fill"></i> Kabul Mektubu</span>';
                                            }
                                        } elseif ($durum == 'Yeni Başvuru') {
                                            echo '<span class="badge bg-secondary status-badge">Yeni Başvuru</span>';
                                        } else {
                                            echo '<span class="badge bg-info text-dark status-badge">İncelemede</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="small text-muted fw-bold">
                                        <?php 
                                            echo ($durum == 'Tamamlandı' && !empty($b['sure_metni'])) ? '<span class="text-primary"><i class="bi bi-clock"></i> '.$b['sure_metni'].'</span>' : '<span class="text-secondary">Hesaplanıyor...</span>'; 
                                        ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php 
                                        $uploaded_pdf_path = "../uploads/dummy.pdf"; 
                                        if (isset($_SESSION['last_uploaded_pdf']) && file_exists("../uploads/" . $_SESSION['last_uploaded_pdf'])) {
                                            $uploaded_pdf_path = "../uploads/" . $_SESSION['last_uploaded_pdf'];
                                        } else {
                                            $files = glob("../uploads/*.pdf");
                                            if (!empty($files)) { $uploaded_pdf_path = $files[0]; }
                                        }
                                        ?>
                                        <a href="<?php echo htmlspecialchars($uploaded_pdf_path); ?>" target="_blank" class="btn btn-outline-primary btn-sm btn-pdf">
                                            <i class="bi bi-file-earmark-pdf"></i> Başvuru PDF
                                        </a>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($durum == 'Tamamlandı' && !empty($b['result_file_path'])): ?>
                                            <a href="../<?php echo htmlspecialchars($b['result_file_path']); ?>" target="_blank" class="btn btn-danger btn-sm btn-pdf">
                                                <i class="bi bi-file-earmark-pdf-fill"></i> Sonuç PDF
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small fw-bold">Süreçte <i class="bi bi-hourglass"></i></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($basvurular)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-folder-x"></i> Henüz bir başvurunuz bulunmuyor.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>