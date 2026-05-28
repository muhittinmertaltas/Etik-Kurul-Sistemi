<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etik Kurul Sistemi - Kayıt Ol</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0 fw-bold"><i class="bi bi-person-plus-fill"></i> Yeni Kayıt Oluştur</h4>
                </div>
                <div class="card-body p-4">
                    <form action="register_islem.php" method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Ad Soyad:</label>
                            <input type="text" name="fullname" class="form-control" placeholder="Örn: Ahmet Yılmaz" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">E-posta Adresi:</label>
                            <input type="email" name="email" class="form-control" placeholder="ornek@universite.edu.tr" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Şifre:</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold small">Üyelik Tipi:</label>
                            <select name="role" class="form-select fw-semibold text-dark" required>
                                <option value="1" selected>Etik Kurul Başvurucusu</option>
                                <option value="2">Etik Kurul Yetkilisi</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">
                            <i class="bi bi-check-lg"></i> Kayıt Ol
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-white text-center py-3 border-top-0">
                    <a href="index.php" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> Giriş Ekranına Dön</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>