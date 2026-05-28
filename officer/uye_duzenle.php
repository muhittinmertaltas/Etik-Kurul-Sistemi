<?php
session_start();
require_once '../includes/db.php';

$id = $_GET['id'];
$sorgu = $conn->prepare("SELECT * FROM committee_members WHERE id = ?");
$sorgu->execute([$id]);
$uye = $sorgu->fetch();

if ($_POST) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $title = $_POST['title'];

    $update = $conn->prepare("UPDATE committee_members SET member_name = ?, member_email = ?, member_title = ? WHERE id = ?");
    $update->execute([$name, $email, $title, $id]);
    header("Location: dashboard.php");
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
            <h5>Üye Bilgilerini Güncelle</h5>
            <form method="POST">
                <input type="text" name="name" class="form-control mb-2" value="<?php echo $uye['member_name']; ?>" required>
                <input type="email" name="email" class="form-control mb-2" value="<?php echo $uye['member_email']; ?>" required>
                <select name="title" class="form-control mb-3">
                    <option <?php echo $uye['member_title'] == 'Üye' ? 'selected' : ''; ?>>Üye</option>
                    <option <?php echo $uye['member_title'] == 'Başkan' ? 'selected' : ''; ?>>Başkan</option>
                    <option <?php echo $uye['member_title'] == 'Başkan Yardımcısı' ? 'selected' : ''; ?>>Başkan Yardımcısı</option>
                </select>
                <button type="submit" class="btn btn-primary w-100">Güncelle</button>
                <a href="dashboard.php" class="btn btn-link w-100 mt-2 text-decoration-none">Vazgeç</a>
            </form>
        </div>
    </div>
</body>
</html>