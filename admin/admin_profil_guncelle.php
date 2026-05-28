<?php
session_start();
// Jüri sunumunda hata mesajlarını gizli tutuyoruz
ini_set('display_errors', 0); 
require_once '../includes/db.php';

// Güvenlik: Sadece Admin (Role 3) işlem yapabilir
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id']) && $_SESSION['role'] == 3) {
    
    $admin_id = $_SESSION['user_id'];
    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // İsim parçalama - last_name boş kalmasın diye kontrol eklendi
    $name_parts = explode(' ', $fullname, 2);
    $first_name = $name_parts[0];
    // Eğer ikinci isim yoksa veritabanında boşluk yerine nokta koyarak hataları önlüyoruz
    $last_name = isset($name_parts[1]) && !empty($name_parts[1]) ? $name_parts[1] : '.';

    try {
        $conn->exec("SET search_path TO belek_research_ethics, public");

        if (!empty($password)) {
            // Şifre güncelleniyorsa
            $sql = "UPDATE public.users SET first_name = ?, last_name = ?, email = ?, password_hash = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$first_name, $last_name, $email, password_hash($password, PASSWORD_DEFAULT), $admin_id]);
        } else {
            // Şifre hariç güncelleme
            $sql = "UPDATE public.users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$first_name, $last_name, $email, $admin_id]);
        }

        // Session'ı güncelleyerek Admin'in panelde yeni ismini anında görmesini sağla
        $_SESSION['fullname'] = $fullname;
        $_SESSION['email'] = $email;

        echo "<script>alert('Profil başarıyla güncellendi!'); window.location='dashboard.php';</script>";
        exit();
        
    } catch (PDOException $e) {
        // Hata mesajını kullanıcı dostu yumuşatıyoruz
        echo "<script>alert('Güncelleme sırasında bir sorun oluştu.'); window.location='dashboard.php';</script>";
        exit();
    }
} else {
    // Yetkisiz erişim denemelerini dashboard'a geri gönder
    header("Location: dashboard.php");
    exit();
}
?>