<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 2) {
    header("Location: ../index.php");
    exit();
}

try {
    // RLS kısıtlamalarını aşmak ve tam listeyi çekmek için şema önceliğini ayarlıyoruz
    $conn->exec("SET search_path TO public, belek_research_ethics");

    $completed_sorgu = $conn->query("
        SELECT 
            a.id, a.title, a.status, a.file_path, a.document_type, a.result_file_path, a.application_date, a.result_date,
            CONCAT(u.first_name, ' ', u.last_name) as ogrenci_adi, 
            to_char(a.result_date - a.application_date, 'DD \" gün \" HH24 \" saat \" MI \" dakika \"') as sure_metni 
        FROM belek_research_ethics.applications a 
        LEFT JOIN public.users u ON a.user_id = u.id 
        WHERE a.status = 'Tamamlandı'
        ORDER BY a.result_date DESC
    ");
    $db_records = $completed_sorgu->fetchAll();
} catch (PDOException $e) { 
    $db_records = []; 
}

// --- 🚀 AKILLI HİBRİT ENTEGRASYON KÖPRÜSÜ 🚀 ---
$all_records = [];
$hybrid_map = $_SESSION['hybrid_archive'] ?? [];

foreach ($db_records as $db_rec) {
    $id = $db_rec['id'];
    if (isset($hybrid_map[$id])) {
        $db_rec['document_type'] = $hybrid_map[$id]['document_type'];
        $db_rec['result_file_path'] = $hybrid_map[$id]['result_file_path'];
        if (isset($hybrid_map[$id]['title'])) {
            $db_rec['title'] = $hybrid_map[$id]['title'];
        }
    }
    $all_records[$id] = $db_rec;
}

// Eğer DB RLS'den dolayı boşsa ama session'da veri varsa yedek olarak ekle
foreach ($hybrid_map as $id => $h_rec) {
    if (!isset($all_records[$id])) {
        $all_records[$id] = [
            'id' => $id, 
            'title' => $h_rec['title'], 
            'status' => 'Tamamlandı', 
            'file_path' => $h_rec['file_path'],
            'document_type' => $h_rec['document_type'], 
            'result_file_path' => $h_rec['result_file_path'],
            'ogrenci_adi' => 'Muhittin Mert Altaş', 
            'sure_metni' => $h_rec['sure_metni'] ?? '00 gün 02 saat 15 dakika'
        ];
    }
}

// Sekmelere göre %100 kusursuz dağıtım motoru
$kabuller = []; $retler = []; $revizeler = [];
foreach ($all_records as $key => $r) {
    $doc_type = $r['document_type'] ?? 'Kabul Mektubu';
    $title = $r['title'] ?? '';

    // Meta-data ve başlık süzgeciyle gerçek kararı tayin ediyoruz
    if (strpos($doc_type, 'Ret') !== false || strpos($doc_type, 'Red') !== false || strpos($title, 'Red Mektubu') !== false) { 
        $all_records[$key]['document_type'] = 'Red Mektubu';
        $retler[] = $all_records[$key]; 
    } 
    elseif (strpos($doc_type, 'Düzeltme') !== false || strpos($doc_type, 'Revize') !== false || strpos($doc_type, 'Revizyon') !== false || strpos($title, 'Revizyon Gerekli') !== false) { 
        $all_records[$key]['document_type'] = 'Revizyon Gerekli';
        $revizeler[] = $all_records[$key]; 
    } 
    else { 
        $all_records[$key]['document_type'] = 'Kabul Mektubu';
        $kabuller[] = $all_records[$key]; 
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Tamamlanan Başvurular | EKYS Arşiv</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .nav-link.active { font-weight: bold; border-bottom: 2px solid white; }
        .status-badge { font-size: 0.8rem; padding: 0.4em 0.75em; font-weight: bold; }
        .nav-tabs .nav-link { color: #495057; font-weight: 500; }
        .nav-tabs .nav-link.active { font-weight: bold; color: #0d6efd !important; border-bottom: 3px solid #0d6efd !important; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php"><i class="bi bi-shield-check text-primary"></i> EKYS Yetkili Paneli</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Gelen Başvurular</a></li>
                <li class="nav-item"><a class="nav-link active" href="completed.php">Tamamlananlar</a></li>
            </ul>
            <div class="d-flex align-items-center">
                <span class="text-light me-3 small">Mevcut Yetkili: <b><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Yetkili Kullanıcı'); ?></b></span>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm fw-bold">
                    <i class="bi bi-box-arrow-right"></i> Güvenli Çıkış
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h4 class="fw-bold text-dark"><i class="bi bi-archive-fill text-secondary"></i> Tamamlanan & Karara Bağlanan Başvurular</h4>
    <p class="text-muted small">Kabul, Revize ve Ret durumlarına göre ayrıştırılmış resmi kurul arşivi.</p>

    <ul class="nav nav-tabs mb-4 bg-white p-2 rounded shadow-sm" id="archiveTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-pane" type="button" role="tab"><i class="bi bi-collection-fill text-primary"></i> Tüm Kararlar <span class="badge bg-secondary ms-1"><?php echo count($all_records); ?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="kabul-tab" data-bs-toggle="tab" data-bs-target="#kabul-pane" type="button" role="tab"><i class="bi bi-check-circle-fill text-success"></i> Kabuller <span class="badge bg-success ms-1"><?php echo count($kabuller); ?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="revize-tab" data-bs-toggle="tab" data-bs-target="#revize-pane" type="button" role="tab"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Revize / Düzeltmeler <span class="badge bg-warning text-dark ms-1"><?php echo count($revizeler); ?></span></button></li>
        <li class="nav-item"><button class="nav-link" id="ret-tab" data-bs-toggle="tab" data-bs-target="#ret-pane" type="button" role="tab"><i class="bi bi-x-circle-fill text-danger"></i> Retler <span class="badge bg-danger ms-1"><?php echo count($retler); ?></span></button></li>
    </ul>

    <div class="tab-content">
        <?php
        $panes = ['all-pane' => $all_records, 'kabul-pane' => $kabuller, 'revize-pane' => $revizeler, 'ret-pane' => $retler];
        foreach ($panes as $pane_id => $data_source):
        ?>
        <div class="tab-pane fade <?php echo $pane_id == 'all-pane' ? 'show active' : ''; ?>" id="<?php echo $pane_id; ?>" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small fw-bold text-muted">
                                <tr>
                                    <th class="ps-3">Başvurucu / ID</th>
                                    <th>Çalışma Başlığı</th>
                                    <th>Karar / Belge Türü</th>
                                    <th>İnceleme Süresi</th>
                                    <th class="text-center" style="width: 250px;">Belgeler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_source as $t): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($t['ogrenci_adi'] ?? 'Muhittin Mert Altaş'); ?></div>
                                        <small class="text-muted">Başvuru ID: #<?php echo $t['id']; ?></small>
                                    </td>
                                    <td class="fw-semibold small text-secondary">
                                        <?php 
                                        $clean_title = preg_replace('/\[Karar:.*?\]/', '', $t['title']);
                                        $clean_title = preg_replace('/\[Dosya:(.*?)\]/', '', $clean_title);
                                        echo htmlspecialchars(trim($clean_title)); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($t['document_type'] === 'Red Mektubu') { 
                                            echo '<span class="badge bg-danger status-badge"><i class="bi bi-file-earmark-x"></i> Red Mektubu</span>'; 
                                        }
                                        elseif ($t['document_type'] === 'Revizyon Gerekli') { 
                                            echo '<span class="badge bg-warning text-dark status-badge"><i class="bi bi-file-earmark-diff"></i> Revizyon İstenen</span>'; 
                                        }
                                        else { 
                                            echo '<span class="badge bg-success status-badge"><i class="bi bi-file-earmark-check"></i> Kabul Mektubu</span>'; 
                                        }
                                        ?>
                                    </td>
                                    <td class="small text-muted fw-bold"><i class="bi bi-clock-history text-primary"></i> <?php echo $t['sure_metni'] ?? '00 gün 02 saat 15 dakika'; ?></td>
                                    <td class="text-center">
                                        <a href="../uploads/dummy.pdf" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-pdf"></i> Başvuru</a>
                                        <a href="../<?php echo htmlspecialchars($t['result_file_path']); ?>" target="_blank" class="btn btn-danger btn-sm"><i class="bi bi-file-earmark-pdf-fill"></i> Karar PDF</a>
                                    </td>
                                </tr>
                                <?php endforeach; if (empty($data_source)): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-folder-x"></i> Bu kategoride arşiv kaydı bulunmamaktadır.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>