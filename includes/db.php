<?php
// --- NEON TECH CANLI BULUT VERİTABANI RESMİ BAĞLANTI AYARLARI ---
$host = 'GIZLI_SIFRE'; 
$db   = 'GIZLI_SIFRE';
$user = 'GIZLI_SIFRE';
$pass = 'GIZLI_SIFRE';
$port = 'GIZLI_SIFRE';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $conn = new PDO($dsn, $user, $pass, $options);
     
     // Hem etik kurul tablosunu hem de ortak kullanıcı tablosunu haritalandırıyoruz
     $conn->exec("SET search_path TO belek_research_ethics, public");
     
} catch (\PDOException $e) {
     die("Bulut Veritabanı Bağlantı Hatası: " . $e->getMessage());
}
?>