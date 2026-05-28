<?php
session_start();
require_once '../includes/db.php';

// Güvenlik: Admin girişi doğrula
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 3) {
    die("HATA: Yetkisiz erişim.");
}

if (!isset($_GET['id'])) {
    die("HATA: ID eksik.");
}

$id = intval($_GET['id']);

try {
    // 1. Şemayı public'e sabitle
    $conn->exec("SET search_path TO public");

    // 2. Silme işlemini yap
    // 'Officer' değerini bulduğumuz için artık gönül rahatlığıyla kullanabiliriz
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND user_type = 'Officer'");
    $stmt->execute([$id]);

    // 3. Kontrol
    if ($stmt->rowCount() > 0) {
        // Silme başarılı, dashboard'a dön
        header("Location: dashboard.php?status=success");
        exit();
    } else {
        // ID doğru ama silinemedi (Belki başka bir şemada veya kısıt var)
        die("HATA: ID $id bulundu ama veritabanı silme işlemini reddetti (Foreign Key kısıtı olabilir).");
    }

} catch (PDOException $e) {
    die("VERİTABANI HATASI: " . $e->getMessage());
}
?>