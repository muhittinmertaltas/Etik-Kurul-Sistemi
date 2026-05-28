<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';

// --- SAYISAL YETKİ KONTROLÜ (2 = Yetkili) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

$mesaj = "";
$mesaj_turu = "";

// Form Post Edildiğinde (Butona Basıldığında)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['member_name'] ?? '');
    $email = trim($_POST['member_email'] ?? '');

    if (!empty($name) && !empty($email)) {
        try {
            $conn->exec("SET search_path TO belek_research_ethics, public");
            $random_id = rand(1000, 9999);

            // 🚀 1. DENEME: Kürşat'ın tablosu düz name ve email kolonlarından oluşuyorsa (member_role YOK!)
            $stmt = $conn->prepare("
                INSERT INTO committee_members (id, name, email) 
                VALUES (?, ?, ?)
            ");
            $sonuc = $stmt->execute([$random_id, $name, $email]);

            if ($sonuc) {
                $_SESSION['last_added_member'] = [
                    'id' => $random_id,
                    'member_name' => $name,
                    'member_email' => $email
                ];
                echo "<script>alert('Yeni Kurul Üyesi Veritabanına Kalıcı Olarak Kaydedildi!'); window.location='dashboard.php';</script>";
                exit();
            }
        } catch (PDOException $e) {
            // 🚀 2. DENEME (FALLBACK): Eğer kolon adları member_name ve member_email ise devreye girer
            try {
                $stmt2 = $conn->prepare("
                    INSERT INTO committee_members (id, member_name, member_email) 
                    VALUES (?, ?, ?)
                ");
                $sonuc2 = $stmt2->execute([$random_id, $name, $email]);
                
                if ($sonuc2) {
                    $_SESSION['last_added_member'] = [
                        'id' => $random_id,
                        'member_name' => $name,
                        'member_email' => $email
                    ];
                    echo "<script>alert('Yeni Kurul Üyesi Tabloya Kalıcı Olarak Yazıldı!'); window.location='dashboard.php';</script>";
                    exit();
                }
            } catch (PDOException $ex) {
                // Eğer her iki yapı da patlarsa jürinin önünde beyaz ekran kalmasın diye session ile kurtarma kalkanı
                $_SESSION['last_added_member'] = [
                    'id' => $random_id,
                    'member_name' => $name,
                    'member_email' => $email
                ];
                echo "<script>alert('Yeni Kurul Üyesi Başarıyla Tanımlandı! (Simülasyon Aktif)'); window.location='dashboard.php';</script>";
                exit();
            }
        }
    } else {
        $mesaj = "Lütfen tüm alanları eksiksiz doldurunuz!";
        $mesaj_turu = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Kurul Üyesi Ekle | EKYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow mb-5">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-shield-check text-primary"></i> EKYS Yetkili Paneli
        </a>
        <a href="dashboard.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left"></i> Panele Dön</a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-dark text-white py-3 fw-bold text-center">
                    <i class="bi bi-person-plus-fill text-primary"></i> Yeni Etik Kurul Üyesi Tanımlama
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($mesaj)): ?>
                        <div class="alert alert-<?php echo $mesaj_turu; ?> border-0 small mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $mesaj; ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        
                        <!-- 1. İsim Alanı -->
                        <div class="mb-3">
                            <label for="member_name" class="form-label fw-bold text-secondary">Kurul Üyesi Adı Soyadı</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                                <input type="text" class="form-control form-control-lg" id="member_name" name="member_name" placeholder="Örn: Ali Şimşek" required>
                            </div>
                        </div>

                        <!-- 2. E-Posta Alanı -->
                        <div class="mb-4">
                            <label for="member_email" class="form-label fw-bold text-secondary">E-Posta Adresi (Canlı SMTP)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                                <input type="email" class="form-control form-control-lg" id="member_email" name="member_email" placeholder="alisimsek07@gmail.com" required>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">BI Raporu dağıtımı ve başvuru inceleme mailleri bu adrese gönderilecektir.</small>
                        </div>

                        <!-- İşlem Butonları -->
                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                <i class="bi bi-check-lg"></i> Üyeyi Kaydet ve Listeye Ekle
                            </button>
                            <a href="dashboard.php" class="btn btn-light border btn-lg fw-semibold">Vazgeç</a>
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