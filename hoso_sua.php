<?php
session_start();
include __DIR__ . '/config/ketnoi.php';

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['user']['manguoidung'])) {
    header("Location: dangnhap.php?message=Vui%20lòng%20đăng%20nhập%20để%20chỉnh%20sửa%20hồ%20sơ!&type=warning");
    exit;
}

// Lấy thông tin người dùng hiện tại
// $user_id = $_SESSION['user']['manguoidung']; //update 
// try {
//     $sql_user = "SELECT manguoidung, ten, mail, anhdaidien, vaitro 
//                  FROM tbl_nguoidung 
//                  WHERE manguoidung = ?";
//     $cmd_user = $conn->prepare($sql_user);
//     $cmd_user->execute([$user_id]);
//     $user = $cmd_user->fetch(PDO::FETCH_ASSOC);
//     if (!$user) {
//         $message = 'Không tìm thấy thông tin người dùng!';
//         $message_type = 'danger';
//     }
// } catch (PDOException $e) {
//     $message = 'Lỗi khi lấy thông tin người dùng: ' . htmlspecialchars($e->getMessage());
//     $message_type = 'danger';
//     error_log("User Info Error: " . $e->getMessage());
// }

// Xử lý đăng ký Editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dangky_editor'])) {
    $ly_do = trim($_POST['ly_do'] ?? '');
    if (empty(!$ly_do)) {
        $message = 'Chấp nhận đều khoảng để đăng ký Editor';
        $message_type = 'danger';
    } else {
        try {
            // Kiểm tra xem đã gửi yêu cầu chưa
            $sql_check_request = "SELECT COUNT(*) FROM tbl_editor_requests WHERE manguoidung = ? AND trang_thai = 0";
            $cmd_check_request = $conn->prepare($sql_check_request);
            $cmd_check_request->execute([$user_id]);
            if ($cmd_check_request->fetchColumn() > 0) {
                $message = 'Bạn đã gửi yêu cầu trở thành Editor. Vui lòng chờ duyệt!';
                $message_type = 'warning';
            } else {
                // Lưu yêu cầu Editor
                $sql_request = "INSERT INTO tbl_editor_requests (manguoidung, ly_do, trang_thai, ngay_tao) VALUES (?, ?, 0, NOW())";
                $cmd_request = $conn->prepare($sql_request);
                $cmd_request->execute([$user_id, $ly_do]);
                $message = 'Yêu cầu trở thành Editor đã được gửi. Chờ admin duyệt!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi gửi yêu cầu Editor: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Editor Request Error: " . $e->getMessage());
        }
    }
}

// Xử lý cập nhật hồ sơ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['dangky_editor'])) {
    $ten = trim($_POST['ten'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $matkhau = trim($_POST['matkhau'] ?? '');
    $hinhanh = $user['anhdaidien']; // Giữ ảnh hiện tại nếu không upload ảnh mới

    // Xử lý upload hình ảnh
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
        $target_dir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Kiểm tra định dạng và kích thước file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($imageFileType, $allowed_types)) {
            $message = 'Chỉ chấp nhận file ảnh JPG, JPEG, PNG, GIF.';
            $message_type = 'danger';
        } elseif ($_FILES['hinhanh']['size'] > 5 * 1024 * 1024) { // Giới hạn 5MB
            $message = 'File ảnh quá lớn. Vui lòng chọn file dưới 5MB.';
            $message_type = 'danger';
        } elseif (!move_uploaded_file($_FILES['hinhanh']['tmp_name'], $target_file)) {
            $message = 'Lỗi khi upload hình ảnh. Kiểm tra quyền thư mục: ' . htmlspecialchars($target_file);
            $message_type = 'danger';
        } else {
            $hinhanh = 'uploads/avatars/' . $file_name;
            // Xóa ảnh cũ nếu tồn tại và không phải ảnh mặc định
            if ($user['anhdaidien'] && $user['anhdaidien'] !== 'uploads/avatars/avt.jpg' && file_exists(__DIR__ . '/' . $user['anhdaidien'])) {
                unlink(__DIR__ . '/' . $user['anhdaidien']);
            }
        }
    }

    // Kiểm tra dữ liệu đầu vào
    if (empty($ten)) {
        $message = 'Vui lòng nhập tên.';
        $message_type = 'danger';
    } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL) || !str_ends_with($mail, '@gmail.com')) {
        $message = 'Email phải là địa chỉ Gmail hợp lệ (VD: example@gmail.com).';
        $message_type = 'danger';
    } else {
        // Kiểm tra email có bị trùng không
        try {
            $sql_check_mail = "SELECT COUNT(*) FROM tbl_nguoidung WHERE mail = ? AND manguoidung != ?";
            $cmd_check_mail = $conn->prepare($sql_check_mail);
            $cmd_check_mail->execute([$mail, $user_id]);
            if ($cmd_check_mail->fetchColumn() > 0) {
                $message = 'Email này đã được sử dụng.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi kiểm tra email: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Check Email Error: " . $e->getMessage());
        }
    }

    // Kiểm tra mật khẩu (nếu có nhập)
    if (!empty($matkhau)) {
        if (strlen($matkhau) < 8 || !preg_match('/[0-9]/', $matkhau) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $matkhau)) {
            $message = 'Mật khẩu phải có ít nhất 8 ký tự, chứa số và ký tự đặc biệt.';
            $message_type = 'danger';
        }
    }

    // Cập nhật thông tin nếu không có lỗi
    if ($message_type !== 'danger' && !isset($_POST['dangky_editor'])) {
        try {
            if (!empty($matkhau)) {
                // Cập nhật cả mật khẩu
                $hashed_password = password_hash($matkhau, PASSWORD_DEFAULT);
                $sql_update = "UPDATE tbl_nguoidung SET ten = ?, mail = ?, matkhau = ?, anhdaidien = ? WHERE manguoidung = ?";
                $cmd_update = $conn->prepare($sql_update);
                $cmd_update->execute([$ten, $mail, $hashed_password, $hinhanh, $user_id]);
            } else {
                // Cập nhật không bao gồm mật khẩu
                $sql_update = "UPDATE tbl_nguoidung SET ten = ?, mail = ?, anhdaidien = ? WHERE manguoidung = ?";
                $cmd_update = $conn->prepare($sql_update);
                $cmd_update->execute([$ten, $mail, $hinhanh, $user_id]);
            }

            // Cập nhật session
            $_SESSION['user']['ten'] = $ten;
            $_SESSION['user']['mail'] = $mail;
            $_SESSION['user']['anhdaidien'] = $hinhanh;

            $message = 'Cập nhật hồ sơ thành công!';
            $message_type = 'success';

            // Lấy lại thông tin người dùng để hiển thị
            $cmd_user->execute([$user_id]);
            $user = $cmd_user->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $message = 'Lỗi khi cập nhật hồ sơ: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Update Profile Error: " . $e->getMessage());
        }
    }
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chỉnh Sửa Hồ Sơ - Cộng Đồng Xanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            padding: 2rem 0;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(60, 121, 91, 0.3);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-card {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="bi bi-gear-fill"></i> Chỉnh Sửa Hồ Sơ</h1>
            <p class="mb-0">Cập nhật thông tin cá nhân của bạn</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <!-- Message -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                        role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Profile Edit Form -->
                <?php if ($user): ?>
                    <div class="profile-card">
                        <form method="post" enctype="multipart/form-data">
                            <div class="d-flex align-items-center mb-4">
                                <img src="<?php echo htmlspecialchars($user['anhdaidien'] ?: 'uploads/avatars/avt.jpg'); ?>"
                                    class="profile-avatar me-3" alt="Avatar">
                                <div>
                                    <label for="hinhanh" class="form-label">Ảnh đại diện</label>
                                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="ten" class="form-label">Họ và tên</label>
                                <input type="text" class="form-control" id="ten" name="ten"
                                    value="<?php echo htmlspecialchars($user['ten']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="mail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="mail" name="mail"
                                    value="<?php echo htmlspecialchars($user['mail']); ?>" required>
                                <small class="form-text text-muted">Email phải là địa chỉ Gmail (VD:
                                    example@gmail.com).</small>
                            </div>
                            <div class="mb-3">
                                <label for="matkhau" class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                                <input type="password" class="form-control" id="matkhau" name="matkhau">
                                <small class="form-text text-muted">Mật khẩu phải có ít nhất 8 ký tự, chứa số và ký tự đặc
                                    biệt.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vai trò</label>
                                <input type="text" class="form-control" value="<?php
                                echo $user['vaitro'] == 0 ? 'Admin' :
                                    ($user['vaitro'] == 1 ? 'Editor' : 'Thành viên');
                                ?>" disabled>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Lưu thay đổi</button>
                            <a href="profile.php" class="btn btn-secondary ms-2"><i class="bi bi-arrow-left"></i> Quay
                                lại</a>
                        </form>


                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Trending Topics Widget -->
                <div class="sidebar-widget">
                    <div class="widget-title">
                        <i class="bi bi-fire"></i> Chủ đề nổi bật
                    </div>
                    <?php
                    try {
                        $sql_trending = "SELECT d.ma_bai_viet, d.tieu_de, 
                                        (SELECT COUNT(*) FROM tbl_binhluandiendan b WHERE b.ma_bai_viet = d.ma_bai_viet) as comment_count,
                                        d.luot_xem
                                        FROM tbl_diendan d 
                                        WHERE d.trang_thai = 1 
                                        ORDER BY d.luot_xem DESC, d.ngay_tao DESC 
                                        LIMIT 5";
                        $cmd_trending = $conn->prepare($sql_trending);
                        $cmd_trending->execute();
                        $trending_posts = $cmd_trending->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $trending_posts = [];
                        error_log("Trending Posts Error: " . $e->getMessage());
                    }
                    ?>
                    <?php foreach ($trending_posts as $trending): ?>
                        <div class="trending-topic">
                            <div class="trending-topic-title">
                                <a
                                    href="baiviet_diendan_chitiet.php?ma_bai_viet=<?php echo htmlspecialchars($trending['ma_bai_viet']); ?>">
                                    <?php echo htmlspecialchars($trending['tieu_de']); ?>
                                </a>
                            </div>
                            <div class="trending-topic-stats">
                                <i class="bi bi-chat-fill"></i> <?php echo $trending['comment_count']; ?> bình luận ·
                                <i class="bi bi-eye-fill"></i> <?php echo number_format($trending['luot_xem']); ?> lượt xem
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Rules Widget -->
                <div class="sidebar-widget">
                    <div class="widget-title">
                        <i class="bi bi-info-circle-fill"></i> Quy tắc diễn đàn
                    </div>
                    <ul style="padding-left: 1.2rem; color: #666; line-height: 2;">
                        <li>Tôn trọng mọi người</li>
                        <li>Không spam hoặc quảng cáo</li>
                        <li>Chia sẻ nội dung liên quan đến môi trường</li>
                        <li>Không sử dụng ngôn từ xúc phạm</li>
                        <li>Báo cáo nội dung vi phạm</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script>
        const checkbox = document.getElementById('dongy');

        // Gắn thông báo tùy chỉnh khi người dùng chưa tick mà nhấn submit
        checkbox.oninvalid = function (event) {
            event.target.setCustomValidity('Bạn cần đồng ý điều khoản trước khi gửi yêu cầu.');
        }

        // Khi người dùng tick lại → xóa thông báo lỗi
        checkbox.oninput = function (event) {
            event.target.setCustomValidity('');
        }
    </script>

</body>

</html>