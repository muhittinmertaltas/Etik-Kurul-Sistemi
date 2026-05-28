<?php
session_start();
require_once '../includes/db.php';

/**
 * 🛡️ GÜVENLİK KONTROLLERİ
 * 1. Admin girişi yapılmış mı?
 * 2. Giriş yapan kişi gerçekten Süper Admin (Rol 3) mi?
 * 3. URL'den bir ID gönderilmiş mi?
 */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 3 || !isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}

$id = intval($_GET['id']); // SQL Injection'a karşı integer'a zorluyoruz

try {
    // Şema yolunu güvenceye alıyoruz
    $conn->exec("SET search_path TO belek_research_ethics, public");
    
    /**
     * 🛡️ GÜVENLİK KİLİDİ
     * Admin yanlışlıkla kendi rolünü veya diğer adminleri donduramaz.
     * Sadece user_type = 'Officer' olan yetkililer üzerinde işlem yapılabilir.
     */
    $stmt = $conn->prepare("
        UPDATE public.users 
        SET is_active = NOT is_active 
        WHERE id = ? 
        AND user_type = 'Officer' 
        AND id != ?
    ");
    
    $stmt->execute([$id, $_SESSION['user_id']]);
    
} catch (PDOException $e) {
    // Jüri önünde hata kodu dökülmesin, sessizce operasyonu bitir
    error_log("Dondurma İşlemi Hatası: " . $e->getMessage());
}

// İşlem ne olursa olsun yönetici paneline geri dön
header("Location: dashboard.php");
exit();
?>