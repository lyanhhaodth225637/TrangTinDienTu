<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập (chỉ admin và tác giả)
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], [0, 1])) {
    header('Location: login.php?error=please_login');
    exit;
}

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Nhận số trang và từ khóa
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

if ($keyword != '') {
    $sql = 'SELECT manguoidung, tendangnhap, ten, mail, vaitro, trangthai, anhdaidien 
            FROM tbl_nguoidung 
            WHERE ten LIKE ? OR mail LIKE ? OR tendangnhap LIKE ?
            LIMIT ? OFFSET ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, "%$keyword%", PDO::PARAM_STR);
    $cmd->bindValue(2, "%$keyword%", PDO::PARAM_STR);
    $cmd->bindValue(3, "%$keyword%", PDO::PARAM_STR);
    $cmd->bindValue(4, $perPage, PDO::PARAM_INT);
    $cmd->bindValue(5, $offset, PDO::PARAM_INT);
    $cmd->execute();
} else {
    $sql = 'SELECT manguoidung, tendangnhap, ten, mail, vaitro, trangthai, anhdaidien 
            FROM tbl_nguoidung 
            LIMIT ? OFFSET ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, $perPage, PDO::PARAM_INT);
    $cmd->bindValue(2, $offset, PDO::PARAM_INT);
    $cmd->execute();
}
$result = $cmd->fetchAll(PDO::FETCH_ASSOC);

// Đếm tổng số bản ghi
$countSql = 'SELECT COUNT(*) as total FROM tbl_nguoidung' . ($keyword != '' ? ' WHERE ten LIKE ? OR mail LIKE ? OR tendangnhap LIKE ?' : '');
$countCmd = $conn->prepare($countSql);
if ($keyword != '') {
    $countCmd->bindValue(1, "%$keyword%", PDO::PARAM_STR);
    $countCmd->bindValue(2, "%$keyword%", PDO::PARAM_STR);
    $countCmd->bindValue(3, "%$keyword%", PDO::PARAM_STR);
    $countCmd->execute();
} else {
    $countCmd->execute();
}
$totalRecords = $countCmd->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Người Dùng</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .avatar-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
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
                    <h5 class="mb-0" id="page-title">Quản Lý Người Dùng</h5>
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
                <div id="users-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Quản lý người dùng (Tổng: <?php echo $totalRecords; ?>)</h4>
                        <a href="nguoidung_them.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Thêm người dùng</a>
                    </div>
                    <div class="table-container">
                        <!-- Hiển thị thông báo -->
                        <?php if ($message || isset($_GET['message'])): ?>
                            <div class="alert alert-<?php echo $message_type ?: htmlspecialchars($_GET['type']); ?> alert-dismissible fade show"
                                role="alert">
                                <?php echo htmlspecialchars($message ?: $_GET['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Form tìm kiếm -->
                        <form method="get" class="mb-3 d-flex">
                            <input type="text" name="keyword" class="form-control me-2"
                                placeholder="Tìm kiếm theo tên, tài khoản hoặc email..."
                                value="<?php echo htmlspecialchars($keyword); ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                        </form>

                        <!-- Bảng danh sách người dùng -->
                        <table class="table table-hover table-striped table-bordered mt-3">
                            <thead class="table-light">
                                <tr>
                                    <th>STT</th>
                                    <th>Ảnh đại diện</th>
                                    <th>Tài khoản</th>
                                    <th>Tên</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (empty($result)) {
                                    echo '<tr><td colspan="8" class="text-center">Không tìm thấy người dùng nào.</td></tr>';
                                } else {
                                    $stt = $offset + 1;
                                    foreach ($result as $user) {
                                        $avatar = !empty($user['anhdaidien']) && file_exists(__DIR__ . '/../' . $user['anhdaidien'])
                                            ? '../' . htmlspecialchars($user['anhdaidien'])
                                            : '../uploads/avatars/avt.jpg';
                                        echo '<tr>';
                                        echo '<td>' . $stt++ . '</td>';
                                        echo '<td class="text-center"><img src="' . $avatar . '" alt="Ảnh đại diện" class="avatar-img"></td>';
                                        echo '<td>' . htmlspecialchars($user['tendangnhap']) . '</td>';
                                        echo '<td>' . htmlspecialchars($user['ten'] ?? 'Không xác định') . '</td>';
                                        echo '<td>' . htmlspecialchars($user['mail']) . '</td>';
                                        echo '<td class="text-center">';
                                        echo '<span class="badge bg-' . ($user['vaitro'] == 0 ? 'danger' : ($user['vaitro'] == 1 ? 'primary' : 'secondary')) . '">' . ($user['vaitro'] == 0 ? 'Quản trị' : ($user['vaitro'] == 1 ? 'Tác giả' : 'Người dùng')) . '</span>';
                                        echo '</td>';
                                        echo '<td class="text-center">';
                                        echo '<span class="badge bg-' . ($user['trangthai'] == 1 ? 'success' : 'warning') . '">' . ($user['trangthai'] == 1 ? 'Mở' : 'Khóa') . '</span>';
                                        echo '</td>';
                                        echo '<td class="text-center">';
                                        echo '<a href="nguoidung_sua.php?manguoidung=' . htmlspecialchars($user['manguoidung']) . '" class="btn btn-sm btn-info me-1"><i class="fas fa-edit"></i></a>';
                                        if ($user['manguoidung'] != $_SESSION['user']['manguoidung']) {
                                            echo '<a href="nguoidung_xoa.php?manguoidung=' . htmlspecialchars($user['manguoidung']) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Bạn có chắc muốn xóa người dùng ' . htmlspecialchars($user['ten']) . ' không?\')"><i class="fas fa-trash"></i></a>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Phân trang -->
                        <nav>
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($keyword); ?>">Trước</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link"
                                            href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($keyword); ?>">Sau</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

</body>

</html>