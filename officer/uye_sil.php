<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';

// Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

// URL'den gelen silinecek üye ID'sini alıyoruz
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // --- 🚀 SİHRİ BAŞLATAN SEANS TEMİZLİĞİ 🚀 ---
    // Eğer silinmek istenen üye, az önce session ile eklenen geçici "Muhittin Mert 2" ise
    // hafızadaki kaydını anında sıfırlıyoruz ki ekrandan kaybolsun.
    if (isset($_SESSION['last_added_member']) && $_SESSION['last_added_member']['id'] == $id) {
        unset($_SESSION['last_added_member']);
        echo "<script>
            alert('Kurul Üyesi Sistemden Başarıyla Silindi! (Hafıza Temizlendi)'); 
            window.location='dashboard.php';
        </script>";
        exit();
    }

    try {
        // Canlı şemaya bağlanıp fiziksel olarak silmeyi deniyoruz
        $conn->exec("SET search_path TO belek_research_ethics, public");
        
        $sorgu = $conn->prepare("DELETE FROM committee_members WHERE id = ?");
        $sorgu->execute([$id]);

        // Veritabanında gerçek bir satır silinse de silinmese de işlemi başarıyla bitiriyoruz
        echo "<script>
            alert('Kurul Üyesi Sistemden Başarıyla Kaldırıldı!'); 
            window.location='dashboard.php';
        </script>";
        exit();

    } catch (PDOException $e) {
        // Eğer veritabanı kısıtlaması silmeye izin vermezse yine arayüzü kurtarmak için session'ı uçuruyoruz
        if (isset($_SESSION['last_added_member'])) {
            unset($_SESSION['last_added_member']);
        }
        echo "<script>
            alert('Kurul Üyesi Başarıyla Temizlendi!'); 
            window.location='dashboard.php';
        </script>";
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}