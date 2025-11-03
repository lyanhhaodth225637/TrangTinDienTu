<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập (chỉ admin)
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], [0, 1])) {
    header('Location: login.php?error=please_login');
    exit;
}
// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Kiểm tra manguoidung
if (!isset($_GET['manguoidung']) || !is_numeric($_GET['manguoidung']) || $_GET['manguoidung'] <= 0) {
    header('Location: nguoidung.php?message=ID người dùng không hợp lệ&type=danger');
    exit;
}

$manguoidung = (int) $_GET['manguoidung'];

// Lấy thông tin người dùng
try {
    $sql = 'SELECT manguoidung, tendangnhap, ten, mail, vaitro, trangthai, anhdaidien 
            FROM tbl_nguoidung 
            WHERE manguoidung = ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, $manguoidung, PDO::PARAM_INT);
    $cmd->execute();
    $user = $cmd->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: nguoidung.php?message=Không tìm thấy người dùng&type=danger');
        exit;
    }
} catch (PDOException $e) {
    header('Location: nguoidung.php?message=Lỗi truy vấn cơ sở dữ liệu: ' . htmlspecialchars($e->getMessage()) . '&type=danger');
    exit;
}

// Xử lý form chỉnh sửa
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ten = trim($_POST['ten'] ?? '');
    $tendangnhap = trim($_POST['tendangnhap'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $vaitro = (int) ($_POST['vaitro'] ?? 2);
    $trangthai = (int) ($_POST['trangthai'] ?? 1);
    $matkhau = trim($_POST['matkhau'] ?? '');
    $anhdaidien = $user['anhdaidien'];

    // Validate dữ liệu
    if (empty($ten)) {
        $errors[] = 'Tên không được để trống.';
    }
    if (empty($tendangnhap)) {
        $errors[] = 'Tài khoản không được để trống.';
    }
    if (empty($mail) || !preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $mail)) {
        $errors[] = 'Email phải hợp lệ và có đuôi @gmail.com.';
    }
    if (!in_array($vaitro, [0, 1, 2])) {
        $errors[] = 'Vai trò không hợp lệ.';
    }
    if (!in_array($trangthai, [0, 1])) {
        $errors[] = 'Trạng thái không hợp lệ.';
    }
    if (!empty($matkhau) && (strlen($matkhau) < 8 || !preg_match('/[0-9]/', $matkhau) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $matkhau))) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự, chứa số và ký tự đặc biệt.';
    }

    // Kiểm tra trùng tendangnhap hoặc mail (ngoại trừ chính người dùng hiện tại)
    $sql = 'SELECT COUNT(*) as total FROM tbl_nguoidung WHERE (tendangnhap = ? OR mail = ?) AND manguoidung != ?';
    $cmd = $conn->prepare($sql);
    $cmd->bindValue(1, $tendangnhap, PDO::PARAM_STR);
    $cmd->bindValue(2, $mail, PDO::PARAM_STR);
    $cmd->bindValue(3, $manguoidung, PDO::PARAM_INT);
    $cmd->execute();
    if ($cmd->fetch()['total'] > 0) {
        $errors[] = 'Tài khoản hoặc email đã tồn tại.';
    }

    // Xử lý upload ảnh đại diện
    if (isset($_FILES['anhdaidien']) && $_FILES['anhdaidien']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['anhdaidien'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'Ảnh đại diện phải là JPEG, PNG hoặc GIF.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'Ảnh đại diện không được vượt quá 2MB.';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $manguoidung . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            $uploadPath = $uploadDir . $filename;

            // Tạo thư mục nếu chưa tồn tại
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $anhdaidien = 'uploads/avatars/' . $filename;
                // Xóa ảnh cũ nếu tồn tại
                if ($user['anhdaidien'] && file_exists(__DIR__ . '/../' . $user['anhdaidien'])) {
                    unlink(__DIR__ . '/../' . $user['anhdaidien']);
                }
            } else {
                $errors[] = 'Lỗi khi tải lên ảnh đại diện.';
            }
        }
    }

    // Nếu không có lỗi, cập nhật dữ liệu
    if (empty($errors)) {
        try {
            $sql = 'UPDATE tbl_nguoidung SET ten = ?, tendangnhap = ?, mail = ?, vaitro = ?, trangthai = ?, anhdaidien = ?' . (!empty($matkhau) ? ', matkhau = ?' : '') . ' WHERE manguoidung = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $ten, PDO::PARAM_STR);
            $cmd->bindValue(2, $tendangnhap, PDO::PARAM_STR);
            $cmd->bindValue(3, $mail, PDO::PARAM_STR);
            $cmd->bindValue(4, $vaitro, PDO::PARAM_INT);
            $cmd->bindValue(5, $trangthai, PDO::PARAM_INT);
            $cmd->bindValue(6, $anhdaidien, PDO::PARAM_STR);
            $paramIndex = 7;
            if (!empty($matkhau)) {
                $hashedPassword = password_hash($matkhau, PASSWORD_BCRYPT);
                $cmd->bindValue($paramIndex++, $hashedPassword, PDO::PARAM_STR);
            }
            $cmd->bindValue($paramIndex, $manguoidung, PDO::PARAM_INT);
            $cmd->execute();

            header('Location: nguoidung.php?message=Cập nhật người dùng thành công&type=success');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Lỗi khi cập nhật người dùng: ' . $e->getMessage();
        }
    }
}

// Xác định role badge
$roleBadgeClass = 'role-user';
$roleBadgeText = '<i class="fas fa-user"></i> Người dùng';
if ($user['vaitro'] == 0) {
    $roleBadgeClass = 'role-admin';
    $roleBadgeText = '<i class="fas fa-crown"></i> Quản trị viên';
} elseif ($user['vaitro'] == 1) {
    $roleBadgeClass = 'role-author';
    $roleBadgeText = '<i class="fas fa-pen"></i> Tác giả';
}

// Avatar mặc định
$avatarUrl = $user['anhdaidien']
    ? '../' . htmlspecialchars($user['anhdaidien'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['ten']) . '&size=150&background=3c795b&color=fff';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hồ Sơ Cá Nhân</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">

</head>

<body>
    <div class="d-flex">
        <?php include_once 'sidebar.php' ?>

        <div class="main-content w-100">
            <nav class="top-navbar d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="mb-0 " id="page-title">Thông tin cá nhân</h5>
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
            <div class="profile-container">
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar-section">
                        <div class="avatar-wrapper">
                            <img src="<?php echo $avatarUrl; ?>" alt="Avatar" class="profile-avatar" id="avatarPreview">
                            <label for="anhdaidien" class="avatar-upload-btn">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <h2 class="profile-name"><?php echo htmlspecialchars($user['ten']); ?></h2>
                        <p class="profile-username">@<?php echo htmlspecialchars($user['tendangnhap']); ?></p>
                        <span class="role-badge <?php echo $roleBadgeClass; ?>" id="roleBadgeDisplay">
                            <?php echo $roleBadgeText; ?>
                        </span>
                    </div>

                    <!-- <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-value">24</span>
                            <span class="stat-label">Bài viết</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">156</span>
                            <span class="stat-label">Lượt thích</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">89</span>
                            <span class="stat-label">Bình luận</span>
                        </div>
                    </div> -->
                </div>

                <!-- Profile Form -->
                <div class="profile-form-card">
                    <!-- Hiển thị thông báo lỗi -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Có lỗi xảy ra:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <!-- Thông tin cá nhân -->
                        <h5 class="form-section-title">
                            <i class="fas fa-user-circle"></i>
                            Thông tin cá nhân
                        </h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ten" class="form-label">
                                    <i class="fas fa-signature text-muted me-1"></i>
                                    Họ và tên <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="ten" name="ten"
                                    value="<?php echo htmlspecialchars($user['ten']); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="tendangnhap" class="form-label">
                                    <i class="fas fa-at text-muted me-1"></i>
                                    Tên đăng nhập <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="tendangnhap" name="tendangnhap"
                                    value="<?php echo htmlspecialchars($user['tendangnhap']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="mail" class="form-label">
                                <i class="fas fa-envelope text-muted me-1"></i>
                                Email <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="mail" name="mail"
                                    value="<?php echo htmlspecialchars($user['mail']); ?>" required>
                            </div>
                        </div>

                        <!-- Bảo mật -->
                        <h5 class="form-section-title mt-4">
                            <i class="fas fa-lock"></i>
                            Bảo mật
                        </h5>

                        <div class="mb-3">
                            <label for="matkhau" class="form-label">
                                <i class="fas fa-key text-muted me-1"></i>
                                Mật khẩu mới
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="matkhau" name="matkhau"
                                    placeholder="Để trống nếu không muốn thay đổi">
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Mật khẩu phải có ít nhất 8 ký tự, bao gồm số và ký tự đặc biệt
                            </small>
                        </div>

                        <!-- Quyền và trạng thái -->
                        <h5 class="form-section-title mt-4">
                            <i class="fas fa-cog"></i>
                            Cài đặt tài khoản
                        </h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vaitro" class="form-label">
                                    <i class="fas fa-user-tag text-muted me-1"></i>
                                    Vai trò <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="vaitro" name="vaitro" required>
                                    <option value="0" <?php echo $user['vaitro'] == 0 ? 'selected' : ''; ?>>
                                        Quản trị viên
                                    </option>
                                    <option value="1" <?php echo $user['vaitro'] == 1 ? 'selected' : ''; ?>>
                                        Tác giả
                                    </option>
                                    <option value="2" <?php echo $user['vaitro'] == 2 ? 'selected' : ''; ?>>
                                        Người dùng
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="trangthai" class="form-label">
                                    <i class="fas fa-toggle-on text-muted me-1"></i>
                                    Trạng thái <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="trangthai" name="trangthai" required>
                                    <option value="1" <?php echo $user['trangthai'] == 1 ? 'selected' : ''; ?>>
                                        Hoạt động
                                    </option>
                                    <option value="0" <?php echo $user['trangthai'] == 0 ? 'selected' : ''; ?>>
                                        Đã khóa
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Ảnh đại diện -->
                        <h5 class="form-section-title mt-4">
                            <i class="fas fa-image"></i>
                            Ảnh đại diện
                        </h5>

                        <div class="mb-4">
                            <input type="file" class="d-none" id="anhdaidien" name="anhdaidien"
                                accept="image/jpeg,image/png,image/gif">
                            <label for="anhdaidien" class="file-upload-label w-100">
                                <div>
                                    <i class="fas fa-cloud-upload-alt d-block"></i>
                                    <strong>Chọn ảnh đại diện</strong>
                                    <p class="text-muted small mb-0">JPG, PNG hoặc GIF (tối đa 2MB)</p>
                                </div>
                            </label>
                            <?php if ($user['anhdaidien']): ?>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">Ảnh hiện tại:</small><br>
                                    <img src="../<?php echo htmlspecialchars($user['anhdaidien']); ?>" alt="Ảnh hiện tại"
                                        class="preview-img">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex gap-3 justify-content-end mt-4">
                            <a href="nguoidung.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>



    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview ảnh khi chọn file
        document.getElementById('anhdaidien').addEventListener('change', function (e) {
            const avatarPreview = document.getElementById('avatarPreview');
            const preview = document.querySelector('.preview-img');

            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function (event) {
                    avatarPreview.src = event.target.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        // Cập nhật role badge dựa trên selection
        document.getElementById('vaitro').addEventListener('change', function () {
            const roleBadge = document.getElementById('roleBadgeDisplay');
            const value = this.value;

            roleBadge.className = 'role-badge';

            if (value == '0') {
                roleBadge.classList.add('role-admin');
                roleBadge.innerHTML = '<i class="fas fa-crown"></i> Quản trị viên';
            } else if (value == '1') {
                roleBadge.classList.add('role-author');
                roleBadge.innerHTML = '<i class="fas fa-pen"></i> Tác giả';
            } else {
                roleBadge.classList.add('role-user');
                roleBadge.innerHTML = '<i class="fas fa-user"></i> Người dùng';
            }
        });
    </script>
</body>

</html>