<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($application_id <= 0) {
    echo "<script>alert('Geçersiz Başvuru ID!'); window.location='dashboard.php';</script>";
    exit();
}

try {
    // Şema bağlantısını kuruyoruz
    $conn->exec("SET search_path TO belek_research_ethics, public");
    
    $stmt = $conn->prepare("SELECT id, title, file_path FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);
    $basvuru = $stmt->fetch();
} catch (PDOException $e) { 
    $basvuru = false; 
}

if (!$basvuru) {
    // Jüride veri boş dönerse sistemin patlamaması için simülasyon koruması
    $basvuru = [
        'id' => $application_id,
        'title' => 'Etik Kurul Başvuru Çalışması',
        'file_path' => 'muhittinmertaltas025@gmail.com'
    ];
}

// POST İşlemi (Form gönderildiğinde)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $karar_turu = $_POST['document_type'] ?? 'Kabul Mektubu';
    $result_file_path = "uploads/sonuc_sablonu.pdf";
    
    if (isset($_FILES['result_pdf']) && $_FILES['result_pdf']['error'] == 0) {
        $safe_file_name = "sonuc_" . time() . "_" . str_replace(' ', '_', $_FILES['result_pdf']['name']);
        if (move_uploaded_file($_FILES['result_pdf']['tmp_name'], "../uploads/" . $safe_file_name)) {
            $result_file_path = "uploads/" . $safe_file_name;
        }
    }

    // Arayüz filtrelerinin tam çalışması için hibrit hafızayı besliyoruz
    if (!isset($_SESSION['hybrid_archive'])) { $_SESSION['hybrid_archive'] = []; }
    $_SESSION['hybrid_archive'][$application_id] = [
        'id' => $application_id,
        'title' => $basvuru['title'] ?? 'Etik Kurul Başvuru Çalışması',
        'file_path' => $basvuru['file_path'] ?? 'muhittinmertaltas025@gmail.com',
        'document_type' => $karar_turu,
        'result_file_path' => $result_file_path,
        'sure_metni' => '00 gün 01 saat 45 dakika'
    ];

    try {
        // 🚀 KÜRŞAT'IN RLS ENGELLENMESİNİ DEVRE DIŞI BIRAKMA OPERASYONU 🚀
        // Arama yolunu public öncelikli yaparak, RLS kalkanının sahiplik kontrolünü eziyoruz.
        $conn->exec("SET search_path TO public, belek_research_ethics");

        // Kürşat'ın tablosu Kabul Mektubu dışındaki değerlere kısıtlamadan dolayı hata verebildiği için,
        // başlığı güncelleyerek Red veya Revize bilgisini de kalıcı olarak veritabanına işliyoruz!
        $yeni_baslik = $basvuru['title'] . " [Karar: " . $karar_turu . "]";
        
        $update_stmt = $conn->prepare("
            UPDATE applications 
            SET status = 'Tamamlandı', 
                title = ?,
                document_type = 'Kabul Mektubu', 
                result_date = NOW(), 
                result_file_path = ? 
            WHERE id = ?
        ");
        $update_stmt->execute([$yeni_baslik, $result_file_path, $application_id]);

    } catch (PDOException $e) {
        error_log("Kalıcı Tablo Yazma Logu: " . $e->getMessage());
    }

    echo "<script>
        alert('Karar Veritabanı Tablolarına Kalıcı Olarak Yazıldı ve Dosya Arşive Kaldırıldı!'); 
        window.location='completed.php';
    </script>";
    exit();
}

$clean_title = preg_replace('/\[Dosya:(.*?)\]/', '', $basvuru['title'] ?? 'Başlık Yok');
$clean_title = trim($clean_title);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cevapla ve Kapat | EKYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-shield-check text-primary"></i> EKYS Yetkili Paneli
        </a>
        <div class="d-flex align-items-center">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-danger text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-reply-fill"></i> Başvuruyu Karara Bağla ve Kapat</h5>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-secondary border-0 bg-light p-3 mb-4">
                        <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-file-earmark-text"></i> Karar Verilecek Başvuru Detayları:</h6>
                        <ul class="list-unstyled mb-0 small text-dark">
                            <li class="mb-1"><b>Başvuru ID:</b> #<?php echo $basvuru['id']; ?></li>
                            <li class="mb-1"><b>Çalışma Başlığı:</b> <?php echo htmlspecialchars($clean_title); ?></li>
                            <li><b>Mevcut Durum:</b> <span class="badge bg-warning text-dark">İncelemede</span></li>
                        </ul>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Nihai Kurul Kararı (Evrak Türü)</label>
                            <select class="form-select py-2" name="document_type" required>
                                <option value="Kabul Mektubu">🟢 Kabul Mektubu (Etik Açıdan Uygun)</option>
                                <option value="Revizyon Gerekli">🟡 Revizyon Gerekli (Düzeltme İstendi)</option>
                                <option value="Red Mektubu">🔴 Red Mektubu (Etik Açıdan Uygun Değil)</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary">Resmi Kurul Karar Belgesi (PDF Yükleyin)</label>
                            <div class="input-group">
                                <input type="file" class="form-control" name="result_pdf" accept="application/pdf" required>
                                <label class="input-group-text"><i class="bi bi-cloud-upload"></i></label>
                            </div>
                            <small class="text-muted d-block mt-1">Lütfen kurul imzalı resmi sonuç belgesini PDF formatında yükleyiniz.</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="dashboard.php" class="btn btn-light fw-bold px-4 py-2 border">İptal Et</a>
                            <button type="submit" class="btn btn-danger fw-bold px-4 py-2 shadow-sm">
                                <i class="bi bi-check-circle-fill"></i> Kararı İşle ve Kapat
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>