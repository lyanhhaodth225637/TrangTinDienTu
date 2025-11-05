<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập (chỉ admin role = 0)
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !isset($_SESSION['user']['manguoidung']) || $_SESSION['user']['role'] != 0) {
    header('Location: login.php?error=please_login');
    exit;
}

// Kiểm tra kết nối
if (!$conn) {
    header('Location: baiviet.php?message=Kết nối cơ sở dữ liệu thất bại&type=danger');
    exit;
}

// Kiểm tra makienthuc
$makienthuc = isset($_GET['makienthuc']) && is_numeric($_GET['makienthuc']) ? (int) $_GET['makienthuc'] : 0;
if ($makienthuc <= 0) {
    header('Location: baiviet.php?message=Mã bài viết không hợp lệ&type=warning');
    exit;
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Lấy chi tiết bài viết
try {
    $sql = 'SELECT kt.makienthuc, kt.manguoidung, kt.machude, kt.tieude, kt.hinhanh, kt.noidung, kt.nguon, kt.duongdan, kt.luotxem, kt.luotchiase, kt.ngaytao, kt.trangthai, cd.tenchude, nd.ten
            FROM tbl_kienthuc kt 
            LEFT JOIN tbl_chude cd ON kt.machude = cd.machude 
            LEFT JOIN tbl_nguoidung nd ON kt.manguoidung = nd.manguoidung 
            WHERE kt.makienthuc = ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, $makienthuc, PDO::PARAM_INT);
    $cmd->execute();
    $post = $cmd->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        header('Location: baiviet.php?message=Bài viết không tồn tại&type=warning');
        exit;
    }
} catch (PDOException $e) {
    $message = 'Lỗi khi lấy chi tiết bài viết: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
}

// Lấy thông báo từ URL
if (empty($message) && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = isset($_GET['type']) ? urldecode($_GET['type']) : 'success';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Bài Viết Kiến Thức</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .table-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .post-image {
            max-width: 300px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .badge {
            font-size: 0.9em;
            padding: 0.5em 1em;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include_once 'sidebar.php'; ?>

        <div class="main-content w-100">
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Chi Tiết Bài Viết Kiến Thức</h5>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']['ten']); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Hồ sơ</a></li>
                            <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                        </ul>
                    </div>
                </div>
            </nav>

            <div class="content-area" id="content-area">
                <div id="posts-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Chi Tiết Bài Viết</h4>
                        <a href="baiviet.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Quay
                            lại danh sách</a>
                    </div>

                    <div class="table-container">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                                role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($post['tieude']); ?></h5>
                                <p class="card-text"><strong>Chủ đề:</strong>
                                    <?php echo htmlspecialchars($post['tenchude'] ?? 'Không có chủ đề'); ?></p>
                                <p class="card-text"><strong>Người đăng:</strong>
                                    <?php echo htmlspecialchars($post['ten'] ?? 'Không xác định'); ?></p>
                                <p class="card-text"><strong>Ngày đăng:</strong>
                                    <?php echo date('d/m/Y H:i', strtotime($post['ngaytao'])); ?></p>
                                <p class="card-text"><strong>Trạng thái:</strong>
                                    <?php
                                    if ($post['trangthai'] == 1) {
                                        echo '<span class="badge bg-success">Đã duyệt</span>';
                                    } else {
                                        echo '<span class="badge bg-warning">Chưa duyệt</span>';
                                    }
                                    ?>
                                </p>
                                <p class="card-text"><strong>Lượt xem:</strong>
                                    <?php echo htmlspecialchars($post['luotxem']); ?></p>
                                <p class="card-text"><strong>Lượt chia sẻ:</strong>
                                    <?php echo htmlspecialchars($post['luotchiase']); ?></p>
                                <p class="card-text"><strong>Nguồn:</strong>
                                    <?php echo htmlspecialchars($post['nguon']); ?></p>
                                <p class="card-text"><strong>Đường dẫn:</strong> <a
                                        href="<?php echo htmlspecialchars($post['duongdan']); ?>"
                                        target="_blank"><?php echo htmlspecialchars($post['duongdan']); ?></a></p>
                                <p class="card-text"><strong>Hình ảnh:</strong><br>
                                    <?php if (!empty($post['hinhanh']) && file_exists(__DIR__ . '/../' . $post['hinhanh'])): ?>
                                        <img src="../<?php echo htmlspecialchars($post['hinhanh']); ?>" alt="Hình ảnh"
                                            class="post-image">
                                    <?php else: ?>
                                        Không có ảnh
                                    <?php endif; ?>
                                </p>
                                <p class="card-text"><strong>Nội dung:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($post['noidung'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <a href=""></a> -->
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>