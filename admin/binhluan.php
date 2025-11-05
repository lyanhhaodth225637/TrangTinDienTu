<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập: chỉ Admin (0) và Tác giả (1)
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], [0, 1])) {
    header('Location: login.php?error=please_login');
    exit;
}

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Biến thông báo
$message = '';
$message_type = '';

// Phân trang
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Truy vấn lấy bình luận + thông tin người dùng + tiêu đề bài viết
$sql = "
    SELECT 
        bl.mabinhluan, bl.noidung, bl.ngaybinhluan,
        nd.manguoidung, nd.tendangnhap, nd.ten, nd.anhdaidien,
        kt.makienthuc, kt.tieude
    FROM tbl_binhluankienthuc bl
    JOIN tbl_nguoidung nd ON bl.manguoidung = nd.manguoidung
    JOIN tbl_kienthuc kt ON bl.makienthuc = kt.makienthuc
";

$countSql = "
    SELECT COUNT(*) as total 
    FROM tbl_binhluankienthuc bl
    JOIN tbl_nguoidung nd ON bl.manguoidung = nd.manguoidung
    JOIN tbl_kienthuc kt ON bl.makienthuc = kt.makienthuc
";

$params = [];
$countParams = [];

if ($keyword !== '') {
    $sql .= " WHERE bl.noidung LIKE ? OR nd.ten LIKE ? OR nd.tendangnhap LIKE ? OR kt.tieude LIKE ?";
    $countSql .= " WHERE bl.noidung LIKE ? OR nd.ten LIKE ? OR nd.tendangnhap LIKE ? OR kt.tieude LIKE ?";
    $like = "%$keyword%";
    for ($i = 0; $i < 4; $i++) {
        $params[] = $like;
        $countParams[] = $like;
    }
}

$sql .= " ORDER BY bl.ngaybinhluan DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

// Thực thi truy vấn chính
$cmd = $conn->prepare($sql);
foreach ($params as $i => $param) {
    $cmd->bindValue($i + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$cmd->execute();
$result = $cmd->fetchAll(PDO::FETCH_ASSOC);

// Đếm tổng số
$countCmd = $conn->prepare($countSql);
foreach ($countParams as $i => $param) {
    $countCmd->bindValue($i + 1, $param, PDO::PARAM_STR);
}
$countCmd->execute();
$totalRecords = $countCmd->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Bình Luận</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        .comment-content {
            font-size: 0.95rem;
            line-height: 1.4;
        }
        .article-title {
            font-size: 0.9rem;
            color: #495057;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content w-100">
            <!-- Top Navbar -->
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Quản Lý Bình Luận </h5>
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

            <!-- Content Area -->
            <div class="content-area" id="content-area">
                <div id="comments-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Bình luận (Tổng: <?php echo $totalRecords; ?>)</h4>
                    </div>

                    <!-- Hiển thị thông báo -->
                    <?php if ($message || isset($_GET['message'])): ?>
                        <div class="alert alert-<?php echo $message_type ?: htmlspecialchars($_GET['type']); ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message ?: $_GET['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Form tìm kiếm -->
                    <form method="get" class="mb-3 d-flex">
                        <input type="text" name="keyword" class="form-control me-2"
                            placeholder="Tìm theo nội dung, người bình luận, tiêu đề bài viết..."
                            value="<?php echo htmlspecialchars($keyword); ?>">
                        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                    </form>

                    <!-- Bảng danh sách bình luận -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">STT</th>
                                    <th width="10%">Người bình luận</th>
                                    <th width="35%">Nội dung</th>
                                    <th width="25%">Bài viết</th>
                                    <th width="15%">Thời gian</th>
                                    <th width="10%">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($result)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Không tìm thấy bình luận nào.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $stt = $offset + 1; ?>
                                    <?php foreach ($result as $row): ?>
                                        <?php
                                        $avatar = !empty($row['anhdaidien']) && file_exists(__DIR__ . '/../' . $row['anhdaidien'])
                                            ? '../' . htmlspecialchars($row['anhdaidien'])
                                            : '../uploads/avatars/avt.jpg';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $stt++; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?php echo $avatar; ?>" alt="Avatar" class="avatar-img">
                                                    <div>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars($row['ten'] ?: $row['tendangnhap']); ?></div>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($row['tendangnhap']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="comment-content">
                                                <?php echo nl2br(htmlspecialchars(substr($row['noidung'], 0, 150))); ?>
                                                <?php echo strlen($row['noidung']) > 150 ? '...' : ''; ?>
                                            </td>
                                            <td>
                                                <a href="../kienthuc_chitiet.php?id=<?php echo $row['makienthuc']; ?>" 
                                                   class="article-title text-decoration-none" target="_blank">
                                                    <?php echo htmlspecialchars($row['tieude']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                <small><?php echo date('d/m/Y H:i', strtotime($row['ngaybinhluan'])); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <a href="binhluan_xoa.php?id=<?php echo $row['mabinhluan']; ?>&makienthuc=<?php echo $row['makienthuc']; ?>"
                                                   class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Xóa bình luận này? Hành động không thể hoàn tác.');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Phân trang -->
                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($keyword); ?>">Trước</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($keyword); ?>">Sau</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>