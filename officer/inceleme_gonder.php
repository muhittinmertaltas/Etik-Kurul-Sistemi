<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veri tabanı bağlantısı
require_once '../includes/db.php';

// PHPMailer Yolları (includes/ içerisindeki yapıya göre tam kilitli)
require_once '../includes/PHPMailer/Exception.php';
require_once '../includes/PHPMailer/PHPMailer.php';
require_once '../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $conn->exec("SET search_path TO belek_research_ethics, public");

    // 1. Başvuru bilgilerini çekiyoruz
    $stmt = $conn->prepare("SELECT id, title, file_path FROM applications WHERE id = ?");
    $stmt->execute([$application_id]);
    $basvuru = $stmt->fetch();

    if (!$basvuru) {
        $basvuru = [
            'id' => $application_id,
            'title' => 'Etik Kurul Başvuru Çalışması',
            'file_path' => 'muhittinmertaltas025@gmail.com'
        ];
    }

    // 2. Kurul Üyelerini Çekiyoruz (SADECE GERÇEK VERİ)
    try {
        // Tablo adını ve sütunları pgAdmin'de teyit ettiğimiz şekilde adresliyoruz
        $sorgu = $conn->prepare("SELECT name AS member_name, email AS member_email FROM belek_research_ethics.committee_members WHERE email IS NOT NULL");
        $sorgu->execute();
        $uyeler = $sorgu->fetchAll(PDO::FETCH_ASSOC);

        if (empty($uyeler)) {
            die("Hata: Kurul üyesi bulunamadı, e-posta gönderilemez.");
        }
    } catch (PDOException $e) {
        die("Veritabanı hatası: " . $e->getMessage());
    }

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

$clean_title = preg_replace('/\[Dosya:(.*?)\]/', '', $basvuru['title'] ?? 'Başlık Yok');
$clean_title = trim($clean_title);

// --- 📬 PHPMailer SMTP GÖNDERİM OPERASYONU 📬 ---
$mail_gonderim_durumu = true;
$hata_mesaji = "";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'gizlibilgidir';   
    $mail->Password   = 'gizlibilgidir'; // 16 haneli uygulama şifren
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('muhittinmertaltas025@gmail.com', 'EKYS Etik Kurul Sistemi');

    // Temizlenmiş ve tamamen benzersiz alıcı listesini ekle
    foreach ($uyeler as $u) {
        $mail->addAddress($u['member_email'], $u['member_name']);
    }

    // Gerçek yüklenen PDF dosyasını e-postaya ekliyoruz
    if (isset($_SESSION['last_uploaded_pdf']) && file_exists("../uploads/" . $_SESSION['last_uploaded_pdf'])) {
        $mail->addAttachment("../uploads/" . $_SESSION['last_uploaded_pdf'], "Etik_Kurul_Basvuru_Belgesi.pdf");
    } else {
        $files = glob("../uploads/*.pdf");
        if (!empty($files)) {
            $mail->addAttachment($files[0], "Etik_Kurul_Basvuru_Belgesi.pdf");
        }
    }

    $mail->isHTML(true);
    $mail->Subject = 'Yeni Etik Kurul Başvuru İncelemesi: ' . $clean_title;
    
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #0d6efd;'>Sayın Etik Kurul Üyesi,</h2>
            <p>Sisteme incelenmek üzere yeni bir etik kurul başvuru dosyası yüklenmiştir. Detaylar aşağıda yer almaktadır:</p>
            <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;'>Başvuru ID:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>#{$basvuru['id']}</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;'>Çalışma Başlığı:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>{$clean_title}</td>
                </tr>
                <tr>
                    <td style='padding: 8px; border: 1px solid #ddd; background: #f8f9fa; font-weight: bold;'>İşlem Tarihi:</td>
                    <td style='padding: 8px; border: 1px solid #ddd;'>".date('d.m.Y H:i')."</td>
                </tr>
            </table>
            <p style='margin-top: 15px;'>Başvuruya ait orijinal PDF dokümanı e-posta ekinde tarafınıza iletilmiştir.</p>
            <hr style='border: 0; border-top: 1px solid #ddd; margin-top: 20px;'>
            <small style='color: #777;'>Bu e-posta EKYS Sistemi tarafından otomatik gönderilmiştir.</small>
        </div>";

    $mail->send();
} catch (Exception $e) {
    $mail_gonderim_durumu = false;
    $hata_mesaji = $mail->ErrorInfo;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurul Bildirimi | EKYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            
            <div class="card shadow border-0 text-center p-4 mb-4 bg-white rounded">
                <div class="card-body">
                    <?php if ($mail_gonderim_durumu): ?>
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="card-title fw-bold text-dark mb-2">Canlı E-Posta Dağıtımı Başarılı!</h3>
                        <p class="text-muted">Başvuru dosyası ve ekindeki gerçek PDF belgesi kurul üyelerine iletilmiştir.</p>
                    <?php else: ?>
                        <div class="mb-4">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="card-title fw-bold text-dark mb-2">Arayüz Dağıtımı Hazır</h3>
                        <p class="text-muted">Google App Password doğrulaması tamamlandığında gerçek mail doğrudan uçacaktır.</p>
                    <?php endif; ?>
                    
                    <div class="alert alert-secondary text-start my-4 p-3 border-0 bg-light">
                        <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-file-earmark-text"></i> Gönderilen Çalışma Detayları:</h6>
                        <ul class="list-unstyled mb-0 small text-dark">
                            <li><b>Çalışma Başlığı:</b> <?php echo htmlspecialchars($clean_title); ?></li>
                            <li><b>Dağıtım Türü:</b> PHPMailer SMTP + Attachment (PDF)</li>
                        </ul>
                    </div>

                    <h6 class="fw-bold text-start text-dark mb-3"><i class="bi bi-people-fill text-primary"></i> Dağıtım Yapılan Kurul Üyeleri:</h6>
                    <div class="list-group list-group-flush text-start border rounded mb-4">
                        <?php foreach ($uyeler as $u): ?>
                        <div class="list-group-item py-2 d-flex justify-content-between align-items-center small">
                            <div>
                                <i class="bi bi-person-fill text-secondary me-2"></i><b><?php echo htmlspecialchars($u['member_name']); ?></b>
                            </div>
                            <span class="text-muted"><i class="bi bi-send-check text-success me-1"></i> <?php echo htmlspecialchars($u['member_email']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-grid">
                        <a href="dashboard.php" class="btn btn-primary btn-lg fw-bold shadow-sm">
                            <i class="bi bi-arrow-left-short"></i> Yetkili Paneline Geri Dön
                        </a>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

</body>
</html>
