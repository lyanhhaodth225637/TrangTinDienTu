<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], [0, 1])) {
    header('Location: login.php?error=please_login');
    exit;
}

if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

$message = '';
$message_type = '';

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

if ($keyword != '') {
    $sql = 'SELECT * FROM tbl_chude WHERE tenchude LIKE ? LIMIT ? OFFSET ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, "%$keyword%", PDO::PARAM_STR);
    $cmd->bindValue(2, $perPage, PDO::PARAM_INT);
    $cmd->bindValue(3, $offset, PDO::PARAM_INT);
    $cmd->execute();
} else {
    $sql = 'SELECT * FROM tbl_chude LIMIT ? OFFSET ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, $perPage, PDO::PARAM_INT);
    $cmd->bindValue(2, $offset, PDO::PARAM_INT);
    $cmd->execute();
}
$result = $cmd->fetchAll(PDO::FETCH_ASSOC);

$countSql = 'SELECT COUNT(*) as total FROM tbl_chude' . ($keyword != '' ? ' WHERE tenchude LIKE ?' : '');
$countCmd = $conn->prepare($countSql);
if ($keyword != '') {
    $countCmd->bindValue(1, "%$keyword%", PDO::PARAM_STR);
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
    <title>Quản Lý Danh Mục</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <div class="d-flex">
        <?php include_once 'sidebar.php'; ?>
        <div class="main-content w-100">
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Quản Lý Danh Mục</h5>
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
                <div id="categories-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Quản lý danh mục</h4>
                        <a href="danhmuc_them.php" class="btn btn-primary"><i class="fas fa-plus"></i> Thêm danh mục</a>
                    </div>
                    <div class="table-container">
                        <?php if ($message || isset($_GET['message'])): ?>
                            <div class="alert alert-<?php echo $message_type ?: htmlspecialchars($_GET['type']); ?> alert-dismissible fade show"
                                role="alert">
                                <?php echo htmlspecialchars($message ?: $_GET['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <form method="get" class="mb-3 d-flex">
                            <input type="text" name="keyword" class="form-control me-2"
                                placeholder="Tìm kiếm danh mục..." value="<?php echo htmlspecialchars($keyword); ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                        </form>
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>STT</th>
                                    <th>Mã danh mục</th>
                                    <th>Tên danh mục</th>
                                    <th>Ngày tạo</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (empty($result)) {
                                    echo '<tr><td colspan="5" class="text-center">Không tìm thấy chủ đề nào.</td></tr>';
                                } else {
                                    $stt = $offset + 1;
                                    foreach ($result as $value) {
                                        echo '<tr>';
                                        echo '<td>' . $stt++ . '</td>';
                                        echo '<td>' . htmlspecialchars($value['machude']) . '</td>';
                                        echo '<td>' . htmlspecialchars($value['tenchude']) . '</td>';
                                        echo '<td>' . htmlspecialchars($value['ngaytao']) . '</td>';
                                        echo '<td>';
                                        echo '<a href="danhmuc_sua.php?machude=' . htmlspecialchars($value['machude']) . '" class="btn btn-sm btn-info"><i class="fas fa-edit"></i></a> ';
                                        echo '<a href="danhmuc_xoa.php?machude=' . htmlspecialchars($value['machude']) . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Bạn có chắc muốn xóa chủ đề này? Lưu ý: Chủ đề có bài viết liên quan không thể xóa.\');"><i class="fas fa-trash"></i></a>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
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