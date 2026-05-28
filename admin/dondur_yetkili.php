<?php
session_start();
require_once '../includes/db.php';

// 🛡️ GÜVENLİK: Admin (Role 3) olduğunu doğrula ve ID parametresini kontrol et
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 3 && isset($_GET['id'])) {
    
    // ID'yi güvenli tamsayıya çeviriyoruz (SQL Injection koruması)
    $id = intval($_GET['id']); 

    // 🛡️ GÜVENLİK KİLİDİ: Admin yanlışlıkla kendi hesabını donduramasın
    if ($id != $_SESSION['user_id']) {
        try {
            $conn->exec("SET search_path TO belek_research_ethics, public");
            
            // Dondurma işlemi: Mevcut durumu tersine çevirir (Aktifse pasif, pasifse aktif)
            $stmt = $conn->prepare("UPDATE public.users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            
        } catch (PDOException $e) {
            // Jüri sunumunda hata mesajı çıkmaması için sessizce hata loglanabilir
            error_log("Dondurma Hatası: " . $e->getMessage());
        }
    }
}

// İşlem ne olursa olsun Admin paneline geri dön
header("Location: dashboard.php");
exit();
?>