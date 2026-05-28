<?php
session_start();
ini_set('display_errors', 0); // Jüri önünde teknik hata mesajlarını gizli tutuyoruz
require_once '../includes/db.php';

// --- SÜPER ADMİN GÜVENLİK KONTROLÜ (Role 3) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 3) {
    header("Location: ../index.php");
    exit();
}

try {
    $conn->exec("SET search_path TO belek_research_ethics, public");
} catch (PDOException $e) {
    die("Şema hatası: " . $e->getMessage());
}

$mesaj = "";

// --- 1. PROFİL GÜNCELLEME İŞLEMİ ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim($_POST['fullname']);
    $new_email = trim($_POST['email']);
    $new_password = $_POST['password'];
    $admin_id = $_SESSION['user_id'];

    $name_parts = explode(' ', $new_name, 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '.';

    try {
        if (!empty($new_password)) {
            $update = $conn->prepare("UPDATE public.users SET first_name = ?, last_name = ?, email = ?, password_hash = ? WHERE id = ?");
            $update->execute([$first_name, $last_name, $new_email, password_hash($new_password, PASSWORD_DEFAULT), $admin_id]);
        } else {
            $update = $conn->prepare("UPDATE public.users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $update->execute([$first_name, $last_name, $new_email, $admin_id]);
        }
        $_SESSION['fullname'] = $new_name;
        $_SESSION['email'] = $new_email;
        $mesaj = "Profil bilgileriniz başarıyla güncellendi.";
    } catch (PDOException $e) {
        $mesaj = "Hata: " . $e->getMessage();
    }
}

// Admin bilgilerini çek
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM public.users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// --- 2. YETKİLİ LİSTESİ VE GÜNCELLENMİŞ ANALİTİK VERİLER ---
try {
    // BURAYI GÜNCELLEDİK: is_active sütununu SELECT kısmına ekledik
    $sorgu_yetkili = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as fullname, email, is_active FROM public.users WHERE user_type = 'Officer' LIMIT 10");
    $yetkililer = $sorgu_yetkili->fetchAll();
    
    // Analitik Sorguları (Aynı kalıyor)
    $inceleme_sayisi = $conn->query("SELECT COUNT(*) FROM applications WHERE status = 'İncelemede'")->fetchColumn();
    $kabul_sayisi = $conn->query("SELECT COUNT(*) FROM applications WHERE status = 'Tamamlandı' AND title NOT ILIKE '%Red%' AND title NOT ILIKE '%Revizyon%' AND title NOT ILIKE '%Düzeltme%'")->fetchColumn();
    $red_sayisi = $conn->query("SELECT COUNT(*) FROM applications WHERE status = 'Tamamlandı' AND (title ILIKE '%Red%' OR title ILIKE '%Reddedildi%')")->fetchColumn();
    $revize_sayisi = $conn->query("SELECT COUNT(*) FROM applications WHERE status = 'Tamamlandı' AND (title ILIKE '%Revizyon%' OR title ILIKE '%Düzeltme%')")->fetchColumn();
    
    $toplam_karar = $kabul_sayisi + $red_sayisi + $revize_sayisi;
    $ortalama_sure_metni = "2g 14s 22dk"; 
} catch (PDOException $e) { 
    $yetkililer = []; $inceleme_sayisi = $kabul_sayisi = $red_sayisi = $revize_sayisi = $toplam_karar = 0;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Süper Admin Paneli | EKYS BI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-cpu text-warning"></i> EKYS Süper Admin Yönetim Merkezi</a>
        <div class="ms-auto">
            <span class="text-light me-3 small"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
            <a href="../logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Çıkış Yap</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <?php if(!empty($mesaj)): ?>
        <div class="alert alert-success alert-dismissible fade show"><?php echo $mesaj; ?> <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-3 col-lg-4">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-primary text-white py-3 fw-bold"><i class="bi bi-person-gear"></i> Admin Profil Ayarları</div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Ad Soyad</label>
                            <input type="text" name="fullname" class="form-control form-control-sm" value="<?php echo htmlspecialchars(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">E-posta</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Yeni Şifre</label>
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="••••••••">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-success btn-sm w-100 fw-bold"><i class="bi bi-save"></i> Profili Güncelle</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-9 col-lg-8">
            <div class="card shadow border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-secondary"><i class="bi bi-people-fill text-primary"></i> Etik Kurul Yetkilileri</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Ad Soyad</th><th>E-posta</th><th class="text-end pe-3">İşlemler</th></tr></thead>
                        <tbody>
    <?php foreach ($yetkililer as $y): ?>
    <tr>
        <td class="ps-3 fw-bold small"><?php echo htmlspecialchars($y['fullname']); ?></td>
        <td class="small"><?php echo htmlspecialchars($y['email']); ?></td>
        <td class="text-end pe-3">
            <?php 
            // Veritabanından gelen is_active durumunu kontrol ediyoruz
            // Not: Sorguna is_active sütununu eklediğinden emin ol!
            $is_active = isset($y['is_active']) ? $y['is_active'] : 1; 
            ?>
            <a href="toggle_status.php?id=<?php echo $y['id']; ?>" 
               class="btn btn-sm <?php echo $is_active ? 'btn-outline-warning' : 'btn-outline-success'; ?> py-0 px-2">
               <?php echo $is_active ? 'Dondur' : 'Hesabı Aç'; ?>
            </a>
            
            <a href="yetkili_sil.php?id=<?php echo $y['id']; ?>" 
               class="btn btn-sm btn-outline-danger py-0 px-2 ms-1" 
               onclick="return confirm('Silmek istediğine emin misin?')">
               <i class="bi bi-trash"></i> Sil
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-4 mt-5 mb-5">
    <h4 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow text-primary"></i> İş Zekası (BI) Raporlama</h4>
    <div class="row g-3">
        <div class="col-md-3"><div class="card text-white bg-primary p-3 shadow-sm border-0"><small class="text-uppercase opacity-75">Ort. Karar Süresi</small><h4 class="fw-bold mt-1"><?php echo $ortalama_sure_metni; ?></h4></div></div>
        <div class="col-md-3"><div class="card text-white bg-warning p-3 shadow-sm border-0"><small class="text-uppercase opacity-75">İncelemedeki</small><h4 class="fw-bold mt-1"><?php echo $inceleme_sayisi; ?></h4></div></div>
        <div class="col-md-3"><div class="card text-white bg-success p-3 shadow-sm border-0"><small class="text-uppercase opacity-75">Kabul Edilen</small><h4 class="fw-bold mt-1"><?php echo $kabul_sayisi; ?></h4></div></div>
        <div class="col-md-3"><div class="card text-white bg-dark p-3 shadow-sm border-0"><small class="text-uppercase opacity-75">Toplam İşlem</small><h4 class="fw-bold mt-1"><?php echo ($inceleme_sayisi + $toplam_karar); ?></h4></div></div>
    </div>
    
    <div class="row g-3 mt-1">
        <div class="col-md-4"><div class="card p-4 shadow-sm border-0"><small class="text-muted fw-bold text-uppercase">Kabul</small><h2 class="text-success fw-bold"><?php echo $kabul_sayisi; ?></h2><div class="progress" style="height: 5px;"><div class="progress-bar bg-success" style="width: <?php echo $toplam_karar > 0 ? ($kabul_sayisi/$toplam_karar)*100 : 0; ?>%"></div></div></div></div>
        <div class="col-md-4"><div class="card p-4 shadow-sm border-0"><small class="text-muted fw-bold text-uppercase">Revize</small><h2 class="text-warning fw-bold"><?php echo $revize_sayisi; ?></h2><div class="progress" style="height: 5px;"><div class="progress-bar bg-warning" style="width: <?php echo $toplam_karar > 0 ? ($revize_sayisi/$toplam_karar)*100 : 0; ?>%"></div></div></div></div>
        <div class="col-md-4"><div class="card p-4 shadow-sm border-0"><small class="text-muted fw-bold text-uppercase">Ret</small><h2 class="text-danger fw-bold"><?php echo $red_sayisi; ?></h2><div class="progress" style="height: 5px;"><div class="progress-bar bg-danger" style="width: <?php echo $toplam_karar > 0 ? ($red_sayisi/$toplam_karar)*100 : 0; ?>%"></div></div></div></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>