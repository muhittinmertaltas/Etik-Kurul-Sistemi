<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 1) {
    header("Location: ../index.php");
    exit();
}

$current_user_email = $_SESSION['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    
    if (empty($title)) {
        echo "<script>alert('Lütfen çalışma başlığını doldurun!'); window.location='dashboard.php';</script>";
        exit();
    }

    // Klasör kontrolü ve dosya taşıma
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] == 0) {
        if (!is_dir('../uploads')) {
            mkdir('../uploads', 0777, true);
        }
        // Dosya ismini temiz tutuyoruz
        $pure_file_name = time() . "_" . str_replace(' ', '_', $_FILES['pdf_file']['name']);
        move_uploaded_file($_FILES['pdf_file']['tmp_name'], "../uploads/" . $pure_file_name);
        
        // Sunumda kolayca erişebilmek için yüklenen son dosya adını session'a atıyoruz
        $_SESSION['last_uploaded_pdf'] = $pure_file_name;
    }

    try {
        $conn->exec("SET search_path TO belek_research_ethics");
        $random_app_id = rand(5000, 99999);

        $sorgu = $conn->prepare("
            INSERT INTO applications (
                id,
                user_id, 
                title, 
                file_path, 
                status, 
                document_type, 
                application_date
            ) VALUES (?, 36, ?, ?, 'İncelemede', 'Kabul Mektubu', NOW())
        ");

        $sonuc = $sorgu->execute([
            $random_app_id,
            $title,      
            $current_user_email
        ]);

        if ($sonuc) {
            echo "<script>
                alert('Başvurunuz ve PDF Dosyanız Başarıyla Sunucuya Gönderildi!'); 
                window.location='dashboard.php';
            </script>";
            exit();
        }

    } catch (PDOException $e) {
        echo "<h4>Bulut Veritabanı Yükleme Hatası:</h4> <p>" . $e->getMessage() . "</p>";
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}