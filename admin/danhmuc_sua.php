<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập
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
$message_type = ''; // success hoặc danger

// Kiểm tra machude từ URL
$machude = isset($_GET['machude']) && is_numeric($_GET['machude']) && $_GET['machude'] > 0 ? (int) $_GET['machude'] : 0;
$chude = null;

// Lấy thông tin chủ đề
if ($machude) {
    try {
        $sql = 'SELECT * FROM tbl_chude WHERE machude = ?';
        $cmd = $conn->prepare($sql);
        $cmd->bindValue(1, $machude, PDO::PARAM_INT);
        $cmd->execute();
        $chude = $cmd->fetch(PDO::FETCH_ASSOC);

        if (!$chude) {
            $message = 'Không tìm thấy chủ đề với mã: ' . htmlspecialchars($machude);
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Lỗi khi lấy thông tin chủ đề: ' . $e->getMessage();
        $message_type = 'danger';
    }
} else {
    $message = 'Không có mã chủ đề được cung cấp.';
    $message_type = 'danger';
}

// Xử lý cập nhật chủ đề
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chude) {
    $tenchude = isset($_POST['tenchude']) ? trim($_POST['tenchude']) : '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($tenchude)) {
        $message = 'Vui lòng nhập tên chủ đề.';
        $message_type = 'danger';
    } else {
        try {
            // Kiểm tra trùng lặp tên chủ đề (trừ chủ đề hiện tại)
            $checkSql = 'SELECT COUNT(*) as total FROM tbl_chude WHERE tenchude = ? AND machude != ?';
            $checkCmd = $conn->prepare($checkSql);
            $checkCmd->bindValue(1, $tenchude, PDO::PARAM_STR);
            $checkCmd->bindValue(2, $machude, PDO::PARAM_INT);
            $checkCmd->execute();
            if ($checkCmd->fetch()['total'] > 0) {
                $message = 'Tên chủ đề "' . htmlspecialchars($tenchude) . '" đã tồn tại.';
                $message_type = 'danger';
            } else {
                // Cập nhật chủ đề
                $sql = 'UPDATE tbl_chude SET tenchude = ? WHERE machude = ?';
                $cmd = $conn->prepare($sql);
                $cmd->bindValue(1, $tenchude, PDO::PARAM_STR);
                $cmd->bindValue(2, $machude, PDO::PARAM_INT);
                $cmd->execute();

                $message = 'Cập nhật chủ đề thành công!';
                $message_type = 'success';

                // Cập nhật lại thông tin chủ đề để hiển thị
                $sql = 'SELECT * FROM tbl_chude WHERE machude = ?';
                $cmd = $conn->prepare($sql);
                $cmd->bindValue(1, $machude, PDO::PARAM_INT);
                $cmd->execute();
                $chude = $cmd->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi cập nhật chủ đề: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh Sửa Chủ Đề</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include_once 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="main-content w-100">
            <!-- Top Navbar -->
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Chỉnh Sửa Danh Mục</h5>
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
                <div id="categories-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Chỉnh Sửa Danh Mục</h4>
                        <a href="danhmuc.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
                    </div>
                    <div class="table-container">
                        <!-- Hiển thị thông báo -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Form chỉnh sửa chủ đề -->
                        <?php if ($chude): ?>
                            <form method="post" class="mb-4">
                                <div class="mb-3">
                                    <label for="machude" class="form-label">Mã danh mục</label>
                                    <input type="text" class="form-control" id="machude"
                                        value="<?php echo htmlspecialchars($chude['machude']); ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label for="tenchude" class="form-label">Tên danh mục</label>
                                    <input type="text" class="form-control" id="tenchude" name="tenchude"
                                        value="<?php echo htmlspecialchars($chude['tenchude']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ngaytao" class="form-label">Ngày tạo</label>
                                    <input type="text" class="form-control" id="ngaytao"
                                        value="<?php echo htmlspecialchars($chude['ngaytao']); ?>" disabled>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu thay
                                    đổi</button>
                            </form>
                        <?php else: ?>
                            <p class="text-danger">Không thể hiển thị form vì chủ đề không tồn tại.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>