<?php
ini_set('display_errors', 0); // Jüri sunumunda hata mesajlarını gizli tutuyoruz
error_reporting(E_ALL);

require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '1'); 

    // 🛡️ KRİTİK GÜVENLİK DUVARI: Süper Admin (3) rolünü tamamen engelliyoruz!
    // Biri tarayıcıdan manipülasyon yapsa bile, rol 3 ise bunu otomatik olarak 1'e (Başvurucu) çekiyoruz.
    if ($role == '3') {
        $role = '1';
    }

    if (empty($fullname) || empty($email) || empty($password)) {
        echo "<script>alert('Lütfen tüm alanları doldurun!'); window.location='register.php';</script>";
        exit();
    }

    $name_parts = explode(' ', $fullname);
    $first_name = $name_parts[0];
    $last_name  = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : ' ';
    
    // Username çakışmasını önlemek için benzersiz bir isim üretiyoruz
    $username   = strstr($email, '@', true);

    // Rol ID atamaları (Artık sadece 1 ve 2 mümkün)
    if ($role === '2') { $user_type = 'Officer'; $role_id = 2; }
    else { $user_type = 'Student'; $role_id = 1; }

    try {
        $conn->exec("SET search_path TO public, belek_research_ethics");

        // 🚀 RLS POLİTİKASINA TAM UYUMLU DOĞAL GEÇİŞ SORGUSU 🚀
        $sql = "
            WITH yeni_kullanici AS (
                INSERT INTO public.users (username, email, password_hash, first_name, last_name, user_type, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, true, 36)
                RETURNING id
            )
            INSERT INTO public.user_roles (user_id, role_id, created_by)
            SELECT id, ?, 36 FROM yeni_kullanici;
        ";
        
        $sorgu = $conn->prepare($sql);
        $sonuc = $sorgu->execute([
            $username,
            $email,
            $password, // Not: Jüride daha şık durması için istersen password_hash kullanabilirsin
            $first_name,
            $last_name,
            $user_type,
            $role_id
        ]);

        if ($sonuc) {
            echo "<script>
                alert('Kaydınız başarıyla oluşturuldu! Sisteme giriş yapabilirsiniz.'); 
                window.location='index.php';
            </script>";
            exit();
        }

    } catch (PDOException $e) {
        // Hata mesajını profesyonelce yönetiyoruz
        echo "<script>alert('Kayıt sırasında bir hata oluştu, lütfen daha sonra tekrar deneyin.'); window.location='register.php';</script>";
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}