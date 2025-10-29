<?php
session_start();
include __DIR__ . '/config/ketnoi.php';

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Kiểm tra nếu cookie chưa tồn tại
if (!isset($_COOKIE['visited'])) {
    try {
        $today = date('Y-m-d');
        $sql = "INSERT INTO tbl_thongke (ngay, luottruycap)
                VALUES (:today, 1)
                ON DUPLICATE KEY UPDATE luottruycap = luottruycap + 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['today' => $today]);

        // Đặt cookie tồn tại trong 24 giờ
        setcookie('visited', 'true', time() + 86400, '/'); // 86400 = 24 giờ
    } catch (PDOException $e) {
        error_log("Lỗi ghi thống kê: " . $e->getMessage());
    }
}

try {
    // Số thành viên
    $sql = 'SELECT COUNT(*) as total FROM tbl_nguoidung WHERE vaitro IN (1, 2)';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_users = $cmd->fetch(PDO::FETCH_ASSOC)['total'];

    // Số hoạt động
    $sql = 'SELECT COUNT(*) as total FROM tbl_hoatdong';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_events = $cmd->fetch(PDO::FETCH_ASSOC)['total'];

    // Số bài viết
    $sql = 'SELECT COUNT(*) as total FROM tbl_kienthuc WHERE trangthai = 1';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_articles = $cmd->fetch(PDO::FETCH_ASSOC)['total'];

    // Tổng lượt truy cập (cộng dồn tất cả ngày)
    $sql = 'SELECT SUM(luottruycap) as total FROM tbl_thongke';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_visits = $cmd->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $message = 'Lỗi truy vấn thống kê: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
}

try {
    $sql = 'SELECT kt.makienthuc, kt.tieude, kt.hinhanh, kt.noidung, kt.nguon, cd.tenchude, 
                   (SELECT COUNT(*) FROM tbl_binhluankienthuc bk WHERE bk.makienthuc = kt.makienthuc) as comment_count 
            FROM tbl_kienthuc kt 
            LEFT JOIN tbl_chude cd ON kt.machude = cd.machude 
            WHERE kt.trangthai = 1 
            ORDER BY kt.ngaytao DESC 
            LIMIT 3';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $articles = $cmd->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $articles = [];
    $message = 'Lỗi truy vấn bài viết: ' . htmlspecialchars($e->getMessage());
}

try {
    $sql = 'SELECT 
                mahoatdong, 
                tenhoatdong AS tieude,  
                hinhanh, 
                noidung, 
                thoigianbatdau AS thoigian
            FROM tbl_hoatdong 
            WHERE duyet = 1               
              AND trangthai = 1         
              AND thoigianbatdau > NOW()  
            ORDER BY thoigianbatdau ASC      
            LIMIT 3';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $events = $cmd->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $events = [];
    $message = 'Lỗi truy vấn hoạt động: ' . htmlspecialchars($e->getMessage());
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT4Earth - Trang Chủ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .avatar-img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <?php include_once 'navbar.php'; ?>
    <?php //include_once 'it4earth_modal.php'; ?>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <h1><i class="bi bi-globe-americas"></i> iNews <?php echo date('Y'); ?></h1>
            <p>Luôn cập nhật tin túc mỗi ngày</p>
            <div class="hero-buttons">
                <a href="<?php echo isset($_SESSION['user']) ? 'hoatdong.php' : 'login.php'; ?>"
                    class="btn btn-hero-primary">
                    <i class="bi bi-rocket-takeoff-fill"></i> Bắt đầu ngay
                </a>
                <a href="vechungtoi.php" class="btn btn-hero-outline">
                    <i class="bi bi-play-circle-fill"></i> Tìm hiểu thêm
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="stats-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <div class="stat-number"><?php echo number_format($total_users); ?></div>
                        <div class="stat-label">Thành viên</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="bi bi-eye-fill"></i></div>
                        <div class="stat-number"><?php echo number_format($total_visits); ?></div>
                        <div class="stat-label">Lượt truy cập</div>
                    </div>
                </div>
                <div class="col-md-3 col-6" hidden>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="bi bi-calendar-event"></i></div>
                        <div class="stat-number"><?php echo number_format($total_events); ?></div>
                        <div class="stat-label">Hoạt động</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-icon"><i class="bi bi-book-fill"></i></div>
                        <div class="stat-number"><?php echo number_format($total_articles); ?></div>
                        <div class="stat-label">Bài viết</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kiến Thức Xanh -->
    <div class="container my-5">
        <div class="section-title">
            <h2><i class="bi bi-book-half"></i> Tin mới nhất</h2>
            <p>Cập nhật những tin tức hot mỗi ngày</p>
        </div>
        <div class="row">
            <?php if (empty($articles)): ?>
                <div class="col-12 text-center">Không có bài viết nào để hiển thị.</div>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-4">
                        <div class="content-card">
                            <img src="<?php echo htmlspecialchars($article['hinhanh'] ?: 'uploads/kienthuc/default.jpg'); ?>"
                                alt="Article Image">
                            <div class="card-body">
                                <span
                                    class="card-category"><?php echo htmlspecialchars($article['tenchude'] ?: 'Không có chủ đề'); ?></span>
                                <h5 class="card-title"><?php echo htmlspecialchars($article['tieude']); ?></h5>
                                <p class="card-text">
                                    <?php echo htmlspecialchars(substr($article['noidung'], 0, 100)) . '...'; ?>
                                </p>
                                <div class="card-meta">
                                    <span><i class="bi bi-chat-fill"></i> <?php echo $article['comment_count']; ?> bình
                                        luận</span>
                                    <a href="baiviet_chitiet.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>"
                                        class="read-more">Xem thêm <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hoạt động sắp tới -->
    <div class="container my-5" hidden>
        <div class="section-title">
            <h2><i class="bi bi-calendar-event-fill"></i> Hoạt Động Xanh</h2>
            <p>Tham gia cùng chúng tôi để tạo nên sự khác biệt</p>
        </div>
        <div class="row">
            <?php if (empty($events)): ?>
                <div class="col-12 text-center">Không có hoạt động nào sắp tới.</div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="col-md-4">
                        <div class="content-card">
                            <img src="<?php echo htmlspecialchars($event['hinhanh'] ?: 'uploads/hoatdong/default.jpg'); ?>"
                                alt="Event Image">
                            <div class="card-body">
                                <span class="card-category">Hoạt động</span>
                                <h5 class="card-title"><?php echo htmlspecialchars($event['tieude']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($event['noidung'], 0, 100)) . '...'; ?>
                                </p>
                                <div class="card-meta">
                                    <span><i class="bi bi-calendar3"></i>
                                        <?php echo date('d/m/Y', strtotime($event['thoigian'])); ?></span>
                                    <a href="hoatdong_chitiet.php?mahoatdong=<?php echo htmlspecialchars($event['mahoatdong']); ?>"
                                        class="read-more">Chi tiết <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script>
        // Hiển thị modal khi truy cập lần đầu
        <?php if ($show_modal): ?>
            document.addEventListener('DOMContentLoaded', function () {
                var introModal = new bootstrap.Modal(document.getElementById('introModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                introModal.show();
            });
        <?php endif; ?>
    </script>
</body>

</html>