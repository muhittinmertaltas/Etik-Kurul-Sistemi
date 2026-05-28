<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * PDF Ekli Mail Gönderim Fonksiyonu
 */
function etikMailGonder($alici_mail, $konu, $mesaj, $dosya_yolu = null) {
    $mail = new PHPMailer(true);

    try {
        // --- SMTP AYARLARI ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'muhittinmertaltas025@gmail.com'; // Kendi adresini yaz
        $mail->Password   = 'vxdaiggkzivxvzpy'; // 16 haneli uygulama şifreni yaz
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Localhost SSL Ayarı
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // --- GÖNDERİCİ VE ALICI ---
        $mail->setFrom('muhittinmertaltas025@gmail.com', 'Etik Kurul Sistemi');
        $mail->addAddress($alici_mail);

        // --- PDF EKLEME (YENİ KISIM) ---
        if ($dosya_yolu !== null && file_exists($dosya_yolu)) {
            // Dosyayı e-postaya ekle. "etik-basvuru.pdf" ismiyle görünecektir.
            $mail->addAttachment($dosya_yolu, 'etik-basvuru.pdf');
        }

        // --- İÇERİK ---
        $mail->isHTML(true);
        $mail->Subject = $konu;
        $mail->Body    = $mesaj;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}