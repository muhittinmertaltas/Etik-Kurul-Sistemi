<?php
session_start();
require_once '../includes/db.php';

// --- YETKİ KONTROLÜ (Hem Yetkili hem Admin erişebilir) ---
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 2 && $_SESSION['role'] != 3)) {
    die("Bu rapora erişim yetkiniz bulunmamaktadır.");
}

try {
    // 1. Toplam Başvuru Sayısı
    $toplam_sorgu = $conn->query("SELECT COUNT(*) FROM applications");
    $toplam_basvuru = $toplam_sorgu->fetchColumn();

    // 2. Cevaplanan (Tamamlanan) Başvuru Sayısı
    $cevaplanan_sorgu = $conn->query("SELECT COUNT(*) FROM applications WHERE status = 'Tamamlandı'");
    $cevaplanan_basvuru = $cevaplanan_sorgu->fetchColumn();

    // 3. Ortalama Cevaplanma Süresi
    // final_decision_time sütunu PostgreSQL interval tipindeyse ortalamasını alır
    $sure_sorgu = $conn->query("SELECT 
        AVG(final_decision_time) as ortalama_sure 
        FROM applications 
        WHERE status = 'Tamamlandı'");
    $ortalama_res = $sure_sorgu->fetch();
    
    // Süre formatını düzenle (Veri yoksa 'Hesaplanamadı' yazar)
    $ortalama_sure_metni = $ortalama_res['ortalama_sure'] ? $ortalama_res['ortalama_sure'] : "Yeterli veri yok";

    // 4. Kurul Üye Sayısı (Ek bilgi)
    $uye_sayisi_sorgu = $conn->query("SELECT COUNT(*) FROM committee_members");
    $uye_sayisi = $uye_sayisi_sorgu->fetchColumn();

    // --- TXT DOSYA İÇERİĞİNİ OLUŞTUR ---
    $rapor_tarihi = date('d.m.Y H:i:s');
    $ayirici = str_repeat("-", 50);

    $rapor = $ayirici . "\n";
    $rapor .= "   EKYS ETİK KURUL SİSTEMİ İSTATİSTİK RAPORU\n";
    $rapor .= $ayirici . "\n";
    $rapor .= "Rapor Oluşturma Tarihi : " . $rapor_tarihi . "\n";
    $rapor .= "Raporu Hazırlayan      : " . $_SESSION['fullname'] . "\n";
    $rapor .= $ayirici . "\n\n";

    $rapor .= "GENEL İSTATİSTİKLER:\n";
    $rapor .= "--------------------\n";
    $rapor .= "Toplam Gelen Başvuru   : " . $toplam_basvuru . " adet\n";
    $rapor .= "Cevaplanan Başvuru     : " . $cevaplanan_basvuru . " adet\n";
    $rapor .= "Sistemdeki Kurul Üyesi : " . $uye_sayisi . " kişi\n\n";

    $rapor .= "PERFORMANS ANALİZİ:\n";
    $rapor .= "--------------------\n";
    $rapor .= "Ortalama Karar Süresi  : " . $ortalama_sure_metni . "\n";
    $rapor .= "(Başvuru anından sonucun yüklendiği ana kadar geçen süre)\n\n";

    $rapor .= $ayirici . "\n";
    $rapor .= "          *** RAPOR SONU ***\n";
    $rapor .= $ayirici . "\n";

    // --- DOSYAYI TARAYICIYA GÖNDER ---
    $dosya_adi = "EKYS_Rapor_" . date('Ymd_Hi') . ".txt";
    
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $dosya_adi . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $rapor;
    exit();

} catch (PDOException $e) {
    die("Rapor verileri çekilirken veritabanı hatası oluştu: " . $e->getMessage());
}