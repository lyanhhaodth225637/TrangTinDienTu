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
$baiviet = null;
$chude_list = [];

// Kiểm tra makienthuc từ URL
$makienthuc = isset($_GET['makienthuc']) && is_numeric($_GET['makienthuc']) && $_GET['makienthuc'] > 0 ? (int) $_GET['makienthuc'] : 0;

try {
    // 1. Lấy danh sách chủ đề để fill vào dropdown
    $sql_chude = 'SELECT * FROM tbl_chude ORDER BY tenchude ASC';
    $cmd_chude = $conn->prepare($sql_chude);
    $cmd_chude->execute();
    $chude_list = $cmd_chude->fetchAll(PDO::FETCH_ASSOC);

    // 2. Lấy thông tin bài viết hiện tại
    if ($makienthuc) {
        $sql = 'SELECT * FROM tbl_kienthuc WHERE makienthuc = ?';
        $cmd = $conn->prepare($sql);
        $cmd->bindValue(1, $makienthuc, PDO::PARAM_INT);
        $cmd->execute();
        $baiviet = $cmd->fetch(PDO::FETCH_ASSOC);

        if (!$baiviet) {
            $message = 'Không tìm thấy bài viết với mã: ' . htmlspecialchars($makienthuc);
            $message_type = 'danger';
        }
    } else {
        $message = 'Không có mã bài viết được cung cấp.';
        $message_type = 'danger';
    }
} catch (PDOException $e) {
    $message = 'Lỗi khi lấy thông tin: ' . $e->getMessage();
    $message_type = 'danger';
}

// Xử lý cập nhật bài viết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $baiviet) {
    // Lấy dữ liệu từ form
    $machude = isset($_POST['machude']) ? (int) $_POST['machude'] : 0;
    $tieude = isset($_POST['tieude']) ? trim($_POST['tieude']) : '';
    $noidung = isset($_POST['noidung']) ? trim($_POST['noidung']) : '';
    $nguon = isset($_POST['nguon']) ? trim($_POST['nguon']) : '';
    $trangthai = isset($_POST['trangthai']) ? (int) $_POST['trangthai'] : 0;
    $hinhanh_cu = isset($_POST['hinhanh_cu']) ? $_POST['hinhanh_cu'] : ''; // Lấy đường dẫn ảnh cũ
    
    $hinhanh_moi = $hinhanh_cu; // Mặc định là ảnh cũ

    // Kiểm tra dữ liệu đầu vào
    if (empty($tieude) || empty($noidung) || $machude == 0) {
        $message = 'Vui lòng nhập đầy đủ tiêu đề, nội dung và chọn chủ đề.';
        $message_type = 'danger';
    } else {
        try {
            // 1. Xử lý upload hình ảnh mới (nếu có)
            if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
                $target_dir = "../uploads/kienthuc/"; // Thư mục lưu ảnh (phải tạo trước)
                // Đảm bảo thư mục tồn tại
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $target_file = $target_dir . basename($_FILES["hinhanh"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $db_path = "uploads/kienthuc/" . basename($_FILES["hinhanh"]["name"]); // Đường dẫn lưu vào DB

                // Kiểm tra file ảnh
                $check = getimagesize($_FILES["hinhanh"]["tmp_name"]);
                if ($check !== false) {
                    // Di chuyển file
                    if (move_uploaded_file($_FILES["hinhanh"]["tmp_name"], $target_file)) {
                        $hinhanh_moi = $db_path;
                        // (Tùy chọn) Xóa ảnh cũ nếu upload thành công và ảnh cũ tồn tại
                        if (!empty($hinhanh_cu) && file_exists("../" . $hinhanh_cu) && $hinhanh_cu != $hinhanh_moi) {
                            unlink("../" . $hinhanh_cu);
                        }
                    } else {
                        $message = 'Lỗi khi tải lên hình ảnh.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'File tải lên không phải là hình ảnh.';
                    $message_type = 'danger';
                }
            }

            // 2. Kiểm tra trùng lặp tiêu đề (trừ bài viết hiện tại)
            $checkSql = 'SELECT COUNT(*) as total FROM tbl_kienthuc WHERE tieude = ? AND makienthuc != ?';
            $checkCmd = $conn->prepare($checkSql);
            $checkCmd->bindValue(1, $tieude, PDO::PARAM_STR);
            $checkCmd->bindValue(2, $makienthuc, PDO::PARAM_INT);
            $checkCmd->execute();

            if ($checkCmd->fetch()['total'] > 0) {
                $message = 'Tiêu đề "' . htmlspecialchars($tieude) . '" đã tồn tại.';
                $message_type = 'danger';
            } elseif ($message_type != 'danger') { // Chỉ cập nhật nếu không có lỗi nào xảy ra (kể cả lỗi upload)
                // 3. Cập nhật bài viết
                $sql_update = 'UPDATE tbl_kienthuc SET 
                                machude = ?, 
                                tieude = ?, 
                                noidung = ?, 
                                nguon = ?, 
                                hinhanh = ?,
                                trangthai = ?
                              WHERE makienthuc = ?';
                $cmd_update = $conn->prepare($sql_update);
                $cmd_update->bindValue(1, $machude, PDO::PARAM_INT);
                $cmd_update->bindValue(2, $tieude, PDO::PARAM_STR);
                $cmd_update->bindValue(3, $noidung, PDO::PARAM_STR);
                $cmd_update->bindValue(4, $nguon, PDO::PARAM_STR);
                $cmd_update->bindValue(5, $hinhanh_moi, PDO::PARAM_STR);
                $cmd_update->bindValue(6, $trangthai, PDO::PARAM_INT);
                $cmd_update->bindValue(7, $makienthuc, PDO::PARAM_INT);
                $cmd_update->execute();

                $message = 'Cập nhật bài viết thành công!';
                $message_type = 'success';

                // Cập nhật lại thông tin bài viết để hiển thị (sau khi đã lưu)
                $sql = 'SELECT * FROM tbl_kienthuc WHERE makienthuc = ?';
                $cmd = $conn->prepare($sql);
                $cmd->bindValue(1, $makienthuc, PDO::PARAM_INT);
                $cmd->execute();
                $baiviet = $cmd->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi cập nhật bài viết: ' . $e->getMessage();
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
    <title>Chỉnh Sửa Bài Viết Kiến Thức</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .preview-img {
            max-width: 200px;
            height: auto;
            margin-top: 10px;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include_once 'sidebar.php' ?>

        <div class="main-content w-100">
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Chỉnh Sửa Bài Viết Kiến Thức</h5>
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
                        <h4>Chỉnh Sửa Bài Viết</h4>
                        <a href="baiviet.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Quay lại</a>
                    </div>
                    <div class="table-container">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($baiviet): ?>
                            <form method="post" class="mb-4" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="makienthuc" class="form-label">Mã bài viết</label>
                                    <input type="text" class="form-control" id="makienthuc"
                                        value="<?php echo htmlspecialchars($baiviet['makienthuc']); ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="machude" class="form-label">Danh mục</label>
                                    <select class="form-select" id="machude" name="machude" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php foreach ($chude_list as $chude): ?>
                                            <option value="<?php echo $chude['machude']; ?>" 
                                                <?php echo ($chude['machude'] == $baiviet['machude']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($chude['tenchude']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="tieude" class="form-label">Tiêu đề</label>
                                    <input type="text" class="form-control" id="tieude" name="tieude"
                                        value="<?php echo htmlspecialchars($baiviet['tieude']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="noidung" class="form-label">Nội dung</label>
                                    <textarea class="form-control" id="noidung" name="noidung" rows="10" required><?php echo htmlspecialchars($baiviet['noidung']); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="nguon" class="form-label">Nguồn</label>
                                    <input type="text" class="form-control" id="nguon" name="nguon"
                                        value="<?php echo htmlspecialchars($baiviet['nguon']); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="hinhanh" class="form-label">Hình ảnh</label>
                                    <div>
                                        <label>Ảnh hiện tại:</label>
                                        <?php if (!empty($baiviet['hinhanh']) && file_exists("../" . $baiviet['hinhanh'])): ?>
                                            <img src="../<?php echo htmlspecialchars($baiviet['hinhanh']); ?>" alt="Ảnh hiện tại" class="preview-img">
                                        <?php else: ?>
                                            <p>Không có ảnh</p>
                                        <?php endif; ?>
                                    </div>
                                    <input type="hidden" name="hinhanh_cu" value="<?php echo htmlspecialchars($baiviet['hinhanh']); ?>">
                                    <label class="form-label mt-2">Tải ảnh mới (bỏ qua nếu không muốn đổi):</label>
                                    <input type="file" class="form-control" id="hinhanh" name="hinhanh">
                                </div>

                                <div class="mb-3">
                                    <label for="trangthai" class="form-label">Trạng thái</label>
                                    <select class="form-select" id="trangthai" name="trangthai">
                                        <option value="0" <?php echo ($baiviet['trangthai'] == 0) ? 'selected' : ''; ?>>Chưa duyệt</option>
                                        <option value="1" <?php echo ($baiviet['trangthai'] == 1) ? 'selected' : ''; ?>>Đã duyệt</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="ngaytao" class="form-label">Ngày tạo</label>
                                    <input type="text" class="form-control" id="ngaytao"
                                        value="<?php echo htmlspecialchars($baiviet['ngaytao']); ?>" disabled>
                                </div>

                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Lưu thay
                                    đổi</button>
                            </form>
                        <?php elseif (empty($message)): // Chỉ hiển thị nếu không có lỗi nào được set ban đầu ?>
                            <p class="text-danger">Không thể hiển thị form vì bài viết không tồn tại.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    </body>

</html>