<?php
session_start();
require_once '../includes/db.php';

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

// URL'den gelen silinecek üye ID'sini al
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    try {
        // Fiziksel olarak silme işlemi
        $sorgu = $conn->prepare("DELETE FROM belek_research_ethics.committee_members WHERE id = ?");
        $sorgu->execute([$id]);

        // Silme başarılıysa panele dön
        header("Location: dashboard.php?durum=silindi");
        exit();

    } catch (PDOException $e) {
        // Bir hata olursa hatayı göster
        die("Silme Hatası: " . $e->getMessage());
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>
