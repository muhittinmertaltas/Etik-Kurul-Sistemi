<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Etik Kurul Sistemi - Giriş</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center"><h4>Sistem Girişi</h4></div>
                <div class="card-body">
                    <form action="login_islem.php" method="POST">
                        <div class="mb-3">
                            <label>E-posta:</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Şifre:</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
                    </form>
                    <hr>
                    <div class="text-center">
                        <a href="register.php">Henüz hesabınız yok mu? Kayıt Olun</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>