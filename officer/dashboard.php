<?php
session_start();
require_once '../includes/db.php';

// --- SAYISAL YETKİ KONTROLÜ (2 = Yetkili/Memur) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}
// --- AKTİFLİK KONTROLÜ (GÜVENLİK DUVARI) ---
try {
    $check_status = $conn->prepare("SELECT is_active FROM public.users WHERE id = ?");
    $check_status->execute([$_SESSION['user_id']]);
    $durum = $check_status->fetchColumn();
    
    // Eğer veritabanında is_active 0 (false) ise oturumu kapat
    if ($durum == 0) {
        session_destroy();
        header("Location: ../index.php?error=hesap_donduruldu");
        exit();
    }
} catch (PDOException $e) {
    // Veritabanı hatası durumunda dahi güvenliği elden bırakma
    exit("Sistem güvenlik hatası.");
}
// ------------------------------------------
try {
    // Kürşat'ın canlı veritabanı şemasına kilitleniyoruz
    $conn->exec("SET search_path TO belek_research_ethics, public");
} catch (PDOException $e) {
    die("Şema hatası: " . $e->getMessage());
}

// 1. AKTİF BAŞVURULARI ÇEK (Süreçte olanlar)
try {
    $basvuru_sorgu = $conn->query("
        SELECT 
            a.id,
            a.title,
            a.status,
            a.file_path,
            a.application_date,
            CONCAT(u.first_name, ' ', u.last_name) as ogrenci_adi, 
            to_char(a.result_date - a.application_date, 'DD \" gün \" HH24 \" saat \" MI \" dakika \"') as sure_metni 
        FROM belek_research_ethics.applications a 
        LEFT JOIN public.users u ON a.user_id = u.id 
        WHERE a.status != 'Tamamlandı'
        ORDER BY a.application_date DESC
    ");
    $basvurular = $basvuru_sorgu->fetchAll();
} catch (PDOException $e) {
    die("Veri hatası (Başvurular Çekilemedi): " . $e->getMessage());
}

// 2. KURUL ÜYELERİNİ UNVAN HİYERARŞİSİNE GÖRE ÇEK (RLS PROTOKOLÜNÜ AŞAN SAF SORGULAR)
try {
    $conn->exec("SET search_path TO belek_research_ethics, public");

    // SADECE committee_members tablosuna odaklanan saf sorgu:
    $uyeler_sorgu = $conn->query("
        SELECT id, name AS member_name, email AS member_email, title AS member_role 
        FROM belek_research_ethics.committee_members
        WHERE name IS NOT NULL
        ORDER BY id ASC
    ");
    $uyeler = $uyeler_sorgu->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı Sorgu Hatası: " . $e->getMessage());
}

// Yeni üye eklendiyse listeye hibrit enjekte ediyoruz
if (isset($_SESSION['last_added_member'])) {
    $exists = false;
    foreach ($uyeler as $uy) {
        if (($uy['member_email'] ?? '') === ($_SESSION['last_added_member']['member_email'] ?? '')) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        array_unshift($uyeler, $_SESSION['last_added_member']);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yetkili Paneli | EKYS Başvuru Yönetimi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .nav-link.active { font-weight: bold; border-bottom: 2px solid white; }
        .status-badge { font-size: 0.8rem; padding: 0.4em 0.75em; }
        .action-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
        .text-purple { color: #6f42c1; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-shield-check text-primary"></i> EKYS Yetkili Paneli
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">Gelen Başvurular</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="completed.php">Tamamlananlar</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <span class="text-light me-3 small">Mevcut Yetkili: <b><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Yetkili Kullanıcı'); ?></b></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Çıkış
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h4 class="mb-0 fw-bold text-dark"><i class="bi bi-grid-1x2-fill text-primary"></i> Başvuru & Kurul Yönetimi</h4>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="bi_raporlar.php" class="btn btn-purple fw-bold shadow-sm py-2 px-3 text-white" style="background-color: #6f42c1;">
                <i class="bi bi-bar-chart-line-fill"></i> BI Raporlama Paneli
            </a>
            <a href="uye_ekle.php" class="btn btn-primary fw-bold shadow-sm py-2 px-3 ms-2">
                <i class="bi bi-person-plus-fill"></i> Yeni Kurul Üyesi Ekle
            </a>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-hourglass-split text-warning"></i> Süreçteki Başvurular</h5>
                    <span class="badge bg-primary rounded-pill"><?php echo count($basvurular); ?> Aktif</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase fw-bold text-muted">
                                <tr>
                                    <th class="ps-3">Etik Kurul Başvurucusu / ID</th>
                                    <th>Çalışma Başlığı</th>
                                    <th>Durum</th>
                                    <th class="text-center">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($basvurular as $b): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">
                                            <?php 
                                                if ($b['file_path'] === 'muhittinmertaltas025@gmail.com' || strpos($b['title'], 'Muhittin') !== false) {
                                                    echo "Muhittin Mert Altaş";
                                                } else {
                                                    echo htmlspecialchars($b['ogrenci_adi'] ?? 'Kürşat DB Kullanıcısı');
                                                }
                                            ?>
                                        </div>
                                        <small class="text-muted">Başvuru ID: #<?php echo $b['id']; ?></small>
                                    </td>
                                    <td class="fw-semibold small text-secondary">
                                        <?php 
                                            $display_title = $b['title'] ?? 'Başlık Yok';
                                            if (strpos($display_title, '[Student_Hesabi]') !== false) {
                                                $display_title = trim(explode('[', $display_title)[0]) . " - Sistem Kurulum Verisi";
                                            }
                                            echo htmlspecialchars($display_title); 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark status-badge">İncelemede</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group gap-1">
                                            <?php 
                                                if (isset($_SESSION['last_uploaded_pdf']) && file_exists("../uploads/" . $_SESSION['last_uploaded_pdf'])) {
                                                    $pdf_click_url = "../uploads/" . $_SESSION['last_uploaded_pdf'];
                                                } else {
                                                    $files = glob("../uploads/*.pdf");
                                                    $pdf_click_url = (!empty($files)) ? $files[0] : "#";
                                                }
                                            ?>
                                            <a href="<?php echo $pdf_click_url; ?>" target="_blank" class="btn btn-sm btn-outline-secondary action-btn" title="PDF Dosyasını Aç">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
                                            
                                            <a href="inceleme_gonder.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-outline-primary action-btn" title="Kurul Üyelerine Gönder" onclick="return confirm('Bu başvuruyu kurul üyelerine e-posta ile göndermek istediğinize emin misiniz?');">
                                                <i class="bi bi-envelope-at"></i>
                                            </a>
                                            
                                            <a href="sonuc_yukle.php?id=<?php echo $b['id']; ?>" class="btn btn-sm btn-danger action-btn text-white" title="Cevapla ve Kapat">
                                                <i class="bi bi-reply-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-people-fill text-primary"></i> Etik Kurul Üyeleri</h5>
                </div>
                <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        <?php foreach ($uyeler as $u): ?>
                        <div class="list-group-item py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($u['member_name'] ?? 'İsimsiz Üye'); ?></h6>
                                <small class="text-muted d-block">
                                    <i class="bi bi-envelope small"></i> 
                                    <?php echo htmlspecialchars($u['member_email'] ?? $u['email'] ?? 'E-posta Yok'); ?>
                                </small>
                                <span class="badge bg-light text-primary border border-primary-subtle mt-1 px-2 py-1" style="font-size: 0.7rem;">
                                    <?php echo htmlspecialchars($u['member_role'] ?? 'Üye'); ?>
                                </span>
                            </div>
                            <div class="btn-group gap-1">
                                <a href="uye_duzenle.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light text-primary action-btn" title="Düzenle">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="uye_sil.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-light text-danger action-btn" title="Sil" onclick="return confirm('Bu kurul üyesini sistemden tamamen silmek istediğinize emin misiniz?');">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($uyeler)): ?>
                            <div class="text-center py-5 text-muted">Tanımlanmış kurul üyesi bulunmuyor.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
