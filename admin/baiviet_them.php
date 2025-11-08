<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';
// them dong
// Kiểm tra quyền truy cập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !isset($_SESSION['user']['manguoidung']) || !in_array($_SESSION['user']['role'], [0, 1])) {
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

// Lấy danh sách chủ đề
try {
    $sql = 'SELECT machude, tenchude FROM tbl_chude ORDER BY tenchude';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $chudes = $cmd->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Lỗi khi lấy danh sách danh mục: ' . $e->getMessage();
    $message_type = 'danger';
}

// Xử lý form khi gửi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nhận dữ liệu từ form
    $machude = isset($_POST['machude']) ? (int)$_POST['machude'] : 0;
    $tieude = isset($_POST['tieude']) ? trim($_POST['tieude']) : '';
    $noidung = isset($_POST['noidung']) ? trim($_POST['noidung']) : '';
    $nguon = isset($_POST['nguon']) ? trim($_POST['nguon']) : '';
    $duongdan = isset($_POST['duongdan']) ? trim($_POST['duongdan']) : '';
    $manguoidung = (int)$_SESSION['user']['manguoidung'];
    $trangthai = isset($_POST['trangthai']) ? (int)$_POST['trangthai'] : 0;

    // Xử lý upload hình ảnh
    $hinhanh = '';
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
        // Đường dẫn tuyệt đối đến thư mục uploads
        $target_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'kienthuc' . DIRECTORY_SEPARATOR;
        // Tạo thư mục nếu chưa tồn tại
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Kiểm tra định dạng file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            $message = 'Chỉ chấp nhận file ảnh JPG, JPEG, PNG, GIF.';
            $message_type = 'danger';
        } elseif ($_FILES['hinhanh']['size'] > 5 * 1024 * 1024) { // Giới hạn 5MB
            $message = 'File ảnh quá lớn. Vui lòng chọn file dưới 5MB.';
            $message_type = 'danger';
        } elseif (!move_uploaded_file($_FILES['hinhanh']['tmp_name'], $target_file)) {
            $message = 'Lỗi khi upload hình ảnh. Kiểm tra quyền thư mục: ' . $target_file;
            $message_type = 'danger';
        } else {
            $hinhanh = 'uploads/kienthuc/' . $file_name;
        }
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($machude) || empty($tieude) || empty($noidung) || empty($nguon) || empty($duongdan) || empty($manguoidung)) {
        $message = 'Vui lòng nhập đầy đủ thông tin bắt buộc hoặc kiểm tra đăng nhập.';
        $message_type = 'danger';
    } else {
        // Kiểm tra manguoidung có hợp lệ trong tbl_nguoidung
        try {
            $sql = 'SELECT COUNT(*) FROM tbl_nguoidung WHERE manguoidung = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $manguoidung, PDO::PARAM_INT);
            $cmd->execute();
            if ($cmd->fetchColumn() == 0) {
                $message = 'Mã người dùng không hợp lệ.';
                $message_type = 'danger';
            } elseif ($message_type !== 'danger') { // Chỉ thêm nếu không có lỗi
                try {
                    // Thêm bài viết mới
                    $sql = 'INSERT INTO tbl_kienthuc (manguoidung, machude, tieude, hinhanh, noidung, nguon, duongdan, trangthai, ngaytao) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $cmd = $conn->prepare($sql);
                    $cmd->bindValue(1, $manguoidung, PDO::PARAM_INT);
                    $cmd->bindValue(2, $machude, PDO::PARAM_INT);
                    $cmd->bindValue(3, $tieude, PDO::PARAM_STR);
                    $cmd->bindValue(4, $hinhanh ? $hinhanh : null, PDO::PARAM_STR);
                    $cmd->bindValue(5, $noidung, PDO::PARAM_STR);
                    $cmd->bindValue(6, $nguon, PDO::PARAM_STR);
                    $cmd->bindValue(7, $duongdan, PDO::PARAM_STR);
                    $cmd->bindValue(8, $trangthai, PDO::PARAM_INT);
                    $cmd->bindValue(9, date('Y-m-d H:i:s'), PDO::PARAM_STR);
                    $cmd->execute();

                    $message = 'Thêm bài viết thành công!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Lỗi khi thêm bài viết: ' . $e->getMessage();
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi kiểm tra người dùng: ' . $e->getMessage();
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
    <title>Thêm Bài Viết Kiến Thức</title>
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
            <div class="content-area" id="content-area">
                <div id="posts-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Thêm Bài Viết Kiến Thức</h4>
                        <a href="baiviet.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
                    </div>
                    <div class="table-container">
                        <!-- Hiển thị thông báo -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Form thêm bài viết -->
                        <form method="post" enctype="multipart/form-data" class="mb-4">
                            <div class="mb-3">
                                <label for="machude" class="form-label">Danh mục</label>
                                <select class="form-control" id="machude" name="machude" required>
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php foreach ($chudes as $chude): ?>
                                        <option value="<?php echo htmlspecialchars($chude['machude']); ?>"
                                            <?php echo isset($_POST['machude']) && $_POST['machude'] == $chude['machude'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($chude['tenchude']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="tieude" class="form-label">Tiêu đề</label>
                                <input type="text" class="form-control" id="tieude" name="tieude"
                                    value="<?php echo isset($_POST['tieude']) ? htmlspecialchars($_POST['tieude']) : ''; ?>"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="hinhanh" class="form-label">Hình ảnh</label>
                                <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                            </div>
                            <div class="mb-3">
                                <label for="noidung" class="form-label">Nội dung</label>
                                <textarea class="form-control" id="noidung" name="noidung" rows="10" required><?php echo isset($_POST['noidung']) ? htmlspecialchars($_POST['noidung']) : ''; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="nguon" class="form-label">Nguồn</label>
                                <input type="text" class="form-control" id="nguon" name="nguon"
                                    value="<?php echo isset($_POST['nguon']) ? htmlspecialchars($_POST['nguon']) : ''; ?>"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="duongdan" class="form-label">Đường dẫn</label>
                                <input type="text" class="form-control" id="duongdan" name="duongdan"
                                    value="<?php echo isset($_POST['duongdan']) ? htmlspecialchars($_POST['duongdan']) : ''; ?>"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="trangthai" class="form-label">Trạng thái</label>
                                <select class="form-control" id="trangthai" name="trangthai" required>
                                    <option value="0" <?php echo isset($_POST['trangthai']) && $_POST['trangthai'] == 0 ? 'selected' : ''; ?>>Chưa duyệt</option>
                                    <option value="1" <?php echo isset($_POST['trangthai']) && $_POST['trangthai'] == 1 ? 'selected' : ''; ?>>Đã duyệt</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu bài viết</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>