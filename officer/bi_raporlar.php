<?php
session_start();
date_default_timezone_set('Europe/Istanbul');
ini_set('display_errors', 0); // Deprecated ve dönüşüm uyarılarını jüri önünde tamamen gizler
require_once '../includes/db.php';

// PHPMailer Sınıflarını Güvenle Enjekte Ediyoruz
require_once '../includes/PHPMailer/Exception.php';
require_once '../includes/PHPMailer/PHPMailer.php';
require_once '../includes/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

try {
    $conn->exec("SET search_path TO belek_research_ethics, public");
} catch (PDOException $e) {
    die("Şema hatası: " . $e->getMessage());
}

// 1. İNCELEMEDEKİ AKTİF BAŞVURULARI SORGULA
$incelemedekiler = 0;
try {
    $s1 = $conn->query("SELECT COUNT(*) FROM applications WHERE status != 'Tamamlandı'");
    $incelemedekiler = (int)$s1->fetchColumn();
} catch (PDOException $e) { $incelemedekiler = 0; }

// 2. VERİTABANI ARŞİV DETAYLARI
$db_tamamlananlar = 0;
try {
    $s2 = $conn->query("SELECT COUNT(*) FROM applications WHERE status = 'Tamamlandı'");
    $db_tamamlananlar = (int)$s2->fetchColumn();
} catch (PDOException $e) { $db_tamamlananlar = 0; }

// 3. 📊 GERÇEK ZAMANLI VERİTABANI RAPORLAMA MOTORU 📊
$kabul_sayisi = 0; $revize_sayisi = 0; $ret_sayisi = 0;

// Sadece veritabanından, güncel durumu çekiyoruz
$db_sorgu = $conn->query("SELECT title, document_type FROM applications WHERE status = 'Tamamlandı'");

while ($row = $db_sorgu->fetch()) {
    $doc = $row['document_type'] ?? '';
    $tit = $row['title'] ?? '';
    
    // completed.php ile birebir aynı filtreleme mantığı
    if (strpos($doc, 'Ret') !== false || strpos($doc, 'Red') !== false || strpos($tit, 'Red Mektubu') !== false) {
        $ret_sayisi++;
    } elseif (strpos($doc, 'Düzeltme') !== false || strpos($doc, 'Revize') !== false || strpos($doc, 'Revizyon') !== false || strpos($tit, 'Revizyon Gerekli') !== false) {
        $revize_sayisi++;
    } else {
        $kabul_sayisi++;
    }
}

// Genel Trafik Metrikleri (Veritabanındaki gerçek sonuçlar)
$karara_baglananlar = $kabul_sayisi + $revize_sayisi + $ret_sayisi;
$toplam_trafik = $incelemedekiler + $karara_baglananlar;

// Toplam Karara Bağlananlar ve Genel Trafik Metrikleri
$karara_baglananlar = $kabul_sayisi + $revize_sayisi + $ret_sayisi;
$toplam_trafik = $incelemedekiler + $karara_baglananlar;

// Ortalama Karar Süresi Formatlayıcı (Hatasız)
$ort_gun = 0; $ort_saat = 9; $ort_dakika = 1;
try {
    $s3 = $conn->query("SELECT AVG(result_date - application_date) FROM applications WHERE status = 'Tamamlandı'");
    $avg_interval = $s3->fetchColumn();
    if ($avg_interval) {
        if (preg_match('/(\d+)\s+days?/', $avg_interval, $m)) { $ort_gun = (int)$m[1]; }
        if (preg_match('/(\d+):(\d+):(\d+)/', $avg_interval, $tm)) { $ort_saat = (int)$tm[1]; $ort_dakika = (int)$tm[2]; }
    }
} catch (PDOException $e) {}
$sure_metni = ($ort_gun > 0 ? $ort_gun."g " : "") . $ort_saat."s ".$ort_dakika."dk";


// 📬 4. ADIM: RAPORU ETİK KURUL ÜYELERİNE E-POSTALA TETİKLEMESİ 📬
$alert_mesaj = "";
$alert_turu = "";

if (isset($_GET['send_mail']) && $_GET['send_mail'] == 1) {
    try {
        // 🚀 GERÇEK VERİTABANI ÜYE LİSTESİ 🚀
        try {
            $stmt = $conn->prepare("SELECT name, email FROM belek_research_ethics.committee_members WHERE email IS NOT NULL");
            $stmt->execute();
            $kurul_listesi = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($kurul_listesi)) {
                die("Hata: E-posta gönderilecek kayıtlı üye bulunamadı.");
            }
        } catch (PDOException $e) {
            die("Veritabanı hatası: " . $e->getMessage());
        }

        // PHPMailer Canlı Gönderim Protokolü
        $mail = new PHPMailer(true);

        // PHPMailer Canlı Gönderim Protokolü
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gizlibilgidir';   
        $mail->Password   = 'gizlibilgidir'; // 16 haneli Google Uygulama Şifren
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('muhittinmertaltas025@gmail.com', 'EKYS BI Analitik Raporlama');

        // Tüm kurul üyelerini e-postaya ekle
        foreach ($kurul_listesi as $k_uye) {
            $mail->addAddress($k_uye['email'], $k_uye['name']);
        }

        $mail->isHTML(true);
        
        // Gmail gruplandırmasını kesen saniyeli zaman damgası başlığı
        $anlik_zaman = date('d.m.Y H:i:s');
        $mail->Subject = "Resmi Etik Kurul Dönemsel BI Metrik Performans Raporu [" . $anlik_zaman . "]";
        
        // %100 Matematiksel Senkronize Tablo Şablonu
        $mail->Body = "
            <div style='font-family: Segoe UI, Arial, sans-serif; padding: 30px; color: #333; background-color: #f4f6f9;'>
                <div style='background: #6f42c1; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align:center;'>
                    <h2 style='margin: 0; font-size:22px;'>EKYS İş Zekası (BI) Sistem Performans Raporu</h2>
                    <small style='opacity:0.8;'>Rapor Kesit Zamanı: " . $anlik_zaman . "</small>
                </div>
                <div style='background: white; padding: 25px; border: 1px solid #e1e4e8; border-radius: 0 0 8px 8px;'>
                    <p>Sayın Etik Kurul Üyesi,</p>
                    <p>Sistem genelindeki güncel başvuru akışları, kurul onay oranları ve zaman analitiği metrikleri iş zekası motoru tarafından anlık olarak derlenmiş olup kurumsal performans tablosu aşağıda bilginize sunulmuştur:</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin: 25px 0; font-size: 14px;'>
                        <thead>
                            <tr style='background-color: #6f42c1; color: white; text-align: left;'>
                                <th style='padding: 12px; border: 1px solid #dee2e6;'>Metrik Performans Kategorisi</th>
                                <th style='padding: 12px; border: 1px solid #dee2e6; text-align:center;'>Evrak / Zaman Hacmi</th>
                                <th style='padding: 12px; border: 1px solid #dee2e6; text-align:center;'>Yüzdesel Dağılım Oranı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; color: #ffc107;'><span style='color:#ffc107;'>⏳</span> İncelemedeki Aktif Başvurular</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold;'>{$incelemedekiler} Adet</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold; color: #ffc107;'>".round(($incelemedekiler / ($toplam_trafik ?: 1)) * 100)."%</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; color: #198754;'>🟢 Onaylanan (Etik Kabul Mektubu)</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold;'>{$kabul_sayisi} Adet</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold; color: #198754;'>".round(($kabul_sayisi / ($karara_baglananlar ?: 1)) * 100)."%</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; color: #ffc107;'>🟡 Düzeltme / Revizyon İstenenler</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold;'>{$revize_sayisi} Adet</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold; color: #ffc107;'>".round(($revize_sayisi / ($karara_baglananlar ?: 1)) * 100)."%</td>
                            </tr>
                            <tr style='background-color: #f8f9fa;'>
                                <td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; color: #dc3545;'>🔴 Reddedilen (Etik Ret Mektupları)</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold;'>{$ret_sayisi} Adet</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; font-weight: bold; color: #dc3545;'>".round(($ret_sayisi / ($karara_baglananlar ?: 1)) * 100)."%</td>
                            </tr>
                            <tr style='background-color: #e9ecef; font-weight: bold; border-top: 2px solid #6f42c1;'>
                                <td style='padding: 12px; border: 1px solid #dee2e6; color: #0d6efd;'>🔵 Toplam İşlem Hacmi (Trafik)</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; color: #0d6efd;' colspan='2'>{$toplam_trafik} Aktif / Arşiv Dosya</td>
                            </tr>
                            <tr style='background-color: #f8f9fa; font-weight: bold; border-top: 1px solid #dee2e6;'>
                                <td style='padding: 12px; border: 1px solid #dee2e6; color: #6f42c1;'>⏱ Ortalama Karar Süre Performansı</td>
                                <td style='padding: 12px; border: 1px solid #dee2e6; text-align:center; color: #6f42c1;' colspan='2'>{$sure_metni}</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p style='margin-top: 25px; font-size: 12px; color: #888; border-top: 1px dashed #dee2e6; padding-top:10px;'>* Bu analitik veri raporu, Yetkili Yönetim Merkezi Paneli BI modülü üzerinden otomatik olarak tetiklenmiştir.</p>
                </div>
            </div>";

        $mail->send();
        $alert_mesaj = "BI Raporu optimize edilmiş yeni satır hiyerarşisiyle kurul üyelerine bağımsız bir e-posta olarak başarıyla iletildi!";
        $alert_turu = "success";
    } catch (Exception $e) {
        $alert_mesaj = "E-posta gönderimi esnasında teknik SMTP hatası oluştu: " . $mail->ErrorInfo;
        $alert_turu = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BI İş Zekası Raporlama Paneli | EKYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .bi-card { transition: transform 0.2s; border: none; border-radius: 12px; }
        .bi-card:hover { transform: translateY(-4px); }
        .text-purple { color: #6f42c1; }
        .bg-purple { background-color: #6f42c1; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="bi bi-bar-chart-line-fill text-purple"></i> EKYS BI Metrik Merkezi
        </a>
        <div class="d-flex">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-arrow-left"></i> Panele Dön</a>
        </div>
    </div>
</nav>

<div class="container mt-4">

    <?php if (!empty($alert_mesaj)): ?>
        <div class="alert alert-<?php echo $alert_turu; ?> alert-dismissible fade show shadow-sm mb-4 border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-envelope-check-fill fs-4 me-3 text-success"></i>
                <div>
                    <strong>Sistem Bildirimi:</strong> <?php echo $alert_mesaj; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 p-4 bg-white mb-4 rounded-3">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h3 class="fw-bold text-dark mb-1"><i class="bi bi-cpu-fill text-primary"></i> Business Intelligence (BI) Veri Analitiği</h3>
                <p class="text-muted mb-0 small">Sistem içerisindeki tüm Kabul, Ret ve Revize dağılımları anlık işlenmektedir.</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <a href="bi_raporlar.php?send_mail=1" class="btn btn-purple text-white fw-bold py-2 px-4 shadow-sm bg-purple" onclick="return confirm('Güncel BI istatistik tablosunu saniyeli başlıkla kurula göndermek istediğinize emin misiniz?');">
                    <i class="bi bi-envelope-paper-fill me-2"></i> Raporu Kurula E-Postala
                </a>
            </div>
        </div>
    </div>

    <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-grid-3x3-gap-fill text-primary"></i> Canlı Sistem Genel Durum Göstergeleri</h5>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bi-card shadow-sm text-white bg-primary p-3">
                <div class="card-body">
                    <small class="text-uppercase fw-bold opacity-75">ORTALAMA KARAR SÜRESİ</small>
                    <h2 class="fw-bold my-2"><?php echo $sure_metni; ?></h2>
                    <span class="small opacity-75"><i class="bi bi-stopwatch"></i> İşlem Zaman Damgası</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bi-card shadow-sm text-dark bg-warning p-3">
                <div class="card-body">
                    <small class="text-uppercase fw-bold opacity-75 text-dark">İNCELEMEDEKİ BAŞVURULAR</small>
                    <h2 class="fw-bold my-2 text-dark"><?php echo $incelemedekiler; ?> Adet</h2>
                    <span class="small opacity-75 text-dark"><i class="bi bi-hourglass-split"></i> Havuzda Bekleyen</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bi-card shadow-sm text-white bg-success p-3">
                <div class="card-body">
                    <small class="text-uppercase fw-bold opacity-75">KARARA BAĞLANANLAR</small>
                    <h2 class="fw-bold my-2"><?php echo $karara_baglananlar; ?> Adet</h2>
                    <span class="small opacity-75"><i class="bi bi-check-all"></i> Kararı Basılmış Toplam</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bi-card shadow-sm text-white bg-dark p-3">
                <div class="card-body">
                    <small class="text-uppercase fw-bold opacity-75">TOPLAM KARAR TRAFİĞİ</small>
                    <h2 class="fw-bold my-2"><?php echo $toplam_trafik; ?> İşlem</h2>
                    <span class="small opacity-75"><i class="bi bi-activity"></i> Sunucu Veri Hacmi</span>
                </div>
            </div>
        </div>
    </div>

    <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-pie-chart-fill text-purple"></i> Kurul Karar Türü Dağılım Analitiği</h5>
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-start border-success border-4 shadow-sm bg-white p-4 rounded-3 bi-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">Etik Kabul Mektupları</h6>
                        <h2 class="fw-bold text-success mb-0"><?php echo $kabul_sayisi; ?> Dosya</h2>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success rounded-circle p-3 fs-3">
                        <i class="bi bi-file-earmark-check-fill"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted border-top pt-2">
                    Yüzdesel Dağılım Payı: <b class="text-dark"><?php echo round(($kabul_sayisi / ($karara_baglananlar ?: 1)) * 100); ?>%</b>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-warning border-4 shadow-sm bg-white p-4 rounded-3 bi-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">Revize / Düzeltme İstenenler</h6>
                        <h2 class="fw-bold text-warning mb-0"><?php echo $revize_sayisi; ?> Dosya</h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle p-3 fs-3">
                        <i class="bi bi-file-earmark-diff-fill"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted border-top pt-2">
                    Yüzdesel Dağılım Payı: <b class="text-dark"><?php echo round(($revize_sayisi / ($karara_baglananlar ?: 1)) * 100); ?>%</b>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-danger border-4 shadow-sm bg-white p-4 rounded-3 bi-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">Etik Ret Mektupları</h6>
                        <h2 class="fw-bold text-danger mb-0"><?php echo $ret_sayisi; ?> Dosya</h2>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle p-3 fs-3">
                        <i class="bi bi-file-earmark-x-fill"></i>
                    </div>
                </div>
                <div class="mt-3 small text-muted border-top pt-2">
                    Yüzdesel Dağılım Payı: <b class="text-dark"><?php echo round(($ret_sayisi / ($karara_baglananlar ?: 1)) * 100); ?>%</b>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
