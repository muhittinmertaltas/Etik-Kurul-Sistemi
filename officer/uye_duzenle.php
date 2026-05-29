<?php
session_start();
require_once '../includes/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = $_GET['id'];

// 2. VERİ ÇEKME: Veritabanındaki gerçek sütun isimleriyle (name, email, title)
$sorgu = $conn->prepare("SELECT * FROM belek_research_ethics.committee_members WHERE id = ?");
$sorgu->execute([$id]);
$uye = $sorgu->fetch(PDO::FETCH_ASSOC);

if (!$uye) {
    header("Location: dashboard.php?durum=uye_bulunamadi");
    exit();
}

// 4. GÜNCELLEME: Veritabanındaki gerçek sütun isimlerine (name, email, title) güncelle
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $title = $_POST['title'];

    $update = $conn->prepare("UPDATE belek_research_ethics.committee_members SET name = ?, email = ?, title = ? WHERE id = ?");
    $update->execute([$name, $email, $title, $id]);
    
    header("Location: dashboard.php?durum=basarili");
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <title>Üye Düzenle</title>
</head>
<body class="bg-light p-5">
    <div class="card shadow mx-auto" style="max-width: 400px;">
        <div class="card-body">
            <h5 class="mb-3">Üye Bilgilerini Güncelle</h5>
            <form method="POST">
                <input type="text" name="name" class="form-control mb-2" value="<?php echo htmlspecialchars($uye['name']); ?>" required>
                <input type="email" name="email" class="form-control mb-2" value="<?php echo htmlspecialchars($uye['email']); ?>" required>
                
                <select name="title" class="form-control mb-3">
                    <option <?php echo $uye['title'] == 'Üye' ? 'selected' : ''; ?>>Üye</option>
                    <option <?php echo $uye['title'] == 'Başkan' ? 'selected' : ''; ?>>Başkan</option>
                    <option <?php echo $uye['title'] == 'Başkan Yardımcısı' ? 'selected' : ''; ?>>Başkan Yardımcısı</option>
                </select>
                
                <button type="submit" class="btn btn-primary w-100">Güncelle</button>
                <a href="dashboard.php" class="btn btn-link w-100 mt-2 text-decoration-none">Vazgeç</a>
            </form>
        </div>
    </div>
</body>
</html>
