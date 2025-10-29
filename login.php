<?php
session_start();
include __DIR__ . '/config/ketnoi.php';

// Kiểm tra nếu đã đăng nhập, chuyển hướng về index.php
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Xử lý đăng nhập
$login_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'login') {
    $tendangnhap = trim($_POST['tendangnhap'] ?? '');
    $matkhau = trim($_POST['matkhau'] ?? '');

    // Validate dữ liệu
    if (empty($tendangnhap)) {
        $login_errors[] = 'Tài khoản không được để trống.';
    }
    if (empty($matkhau)) {
        $login_errors[] = 'Mật khẩu không được để trống.';
    }

    // Kiểm tra tài khoản
    if (empty($login_errors)) {
        try {
            $sql = 'SELECT manguoidung, tendangnhap, ten, mail, matkhau, vaitro, trangthai, anhdaidien 
                    FROM tbl_nguoidung 
                    WHERE tendangnhap = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $tendangnhap, PDO::PARAM_STR);
            $cmd->execute();
            $user = $cmd->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($user['trangthai'] == 0) {
                    $login_errors[] = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
                } elseif (password_verify($matkhau, $user['matkhau'])) {
                    // Lưu thông tin người dùng vào session
                    $_SESSION['user'] = [
                        'manguoidung' => $user['manguoidung'],
                        'tendangnhap' => $user['tendangnhap'],
                        'ten' => $user['ten'],
                        'mail' => $user['mail'],
                        'vaitro' => $user['vaitro'],
                        'trangthai' => $user['trangthai'],
                        'anhdaidien' => $user['anhdaidien']
                    ];
                    header('Location: index.php?message=Đăng nhập thành công&type=success');
                    exit;
                } else {
                    $login_errors[] = 'Mật khẩu không đúng.';
                }
            } else {
                $login_errors[] = 'Tài khoản không tồn tại.';
            }
        } catch (PDOException $e) {
            $login_errors[] = 'Lỗi truy vấn cơ sở dữ liệu: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Xử lý đăng ký
$register_errors = [];
$register_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'register') {
    $tendangnhap = trim($_POST['tendangnhap'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $mail = trim($_POST['mail'] ?? '');
    $matkhau = $_POST['matkhau'] ?? '';
    $confirm_matkhau = $_POST['confirm_matkhau'] ?? '';

    // Validate dữ liệu
    if (empty($tendangnhap)) {
        $register_errors[] = 'Tên đăng nhập không được để trống.';
    } elseif (strlen($tendangnhap) < 3 || strlen($tendangnhap) > 50) {
        $register_errors[] = 'Tên đăng nhập phải từ 3 đến 50 ký tự.';
    }

    if (empty($ten)) {
        $register_errors[] = 'Họ tên không được để trống.';
    } elseif (strlen($ten) < 2 || strlen($ten) > 100) {
        $register_errors[] = 'Họ tên phải từ 2 đến 100 ký tự.';
    }

    if (empty($mail)) {
        $register_errors[] = 'Email không được để trống.';
    } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $register_errors[] = 'Email không hợp lệ.';
    }

    if (empty($matkhau)) {
        $register_errors[] = 'Mật khẩu không được để trống.';
    } elseif (strlen($matkhau) < 6) {
        $register_errors[] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }

    if ($matkhau !== $confirm_matkhau) {
        $register_errors[] = 'Mật khẩu xác nhận không khớp.';
    }

    // Kiểm tra trùng tên đăng nhập hoặc email
    if (empty($register_errors)) {
        try {
            $sql = 'SELECT COUNT(*) as total FROM tbl_nguoidung WHERE tendangnhap = ? OR mail = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $tendangnhap, PDO::PARAM_STR);
            $cmd->bindValue(2, $mail, PDO::PARAM_STR);
            $cmd->execute();
            if ($cmd->fetch(PDO::FETCH_ASSOC)['total'] > 0) {
                $register_errors[] = 'Tên đăng nhập hoặc email đã được sử dụng.';
            }
        } catch (PDOException $e) {
            $register_errors[] = 'Lỗi kiểm tra tài khoản: ' . htmlspecialchars($e->getMessage());
        }
    }

    // Lưu vào cơ sở dữ liệu
    if (empty($register_errors)) {
        try {
            $sql = 'INSERT INTO tbl_nguoidung (tendangnhap, ten, mail, matkhau, vaitro, trangthai, anhdaidien) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)';
            $cmd = $conn->prepare($sql);
            $hashed_password = password_hash($matkhau, PASSWORD_DEFAULT);
            $cmd->bindValue(1, $tendangnhap, PDO::PARAM_STR);
            $cmd->bindValue(2, $ten, PDO::PARAM_STR);
            $cmd->bindValue(3, $mail, PDO::PARAM_STR);
            $cmd->bindValue(4, $hashed_password, PDO::PARAM_STR);
            $cmd->bindValue(5, 2, PDO::PARAM_INT); // vaitro = 2 (User)
            $cmd->bindValue(6, 1, PDO::PARAM_INT); // trangthai = 1 (Active)
            $cmd->bindValue(7, 'uploads/avatars/avt.jpg', PDO::PARAM_STR); // Default avatar
            $cmd->execute();
            $register_success = 'Đăng ký thành công! Vui lòng đăng nhập.';
        } catch (PDOException $e) {
            $register_errors[] = 'Lỗi lưu tài khoản: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>iNews - Đăng Nhập / Đăng Ký</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .modal-content {
            border-radius: 10px;
        }

        .modal-header {
            border-bottom: none;
        }

        .modal-title {
            font-weight: 700;
            color: #3c795b;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-primary {
            background-color: #3c795b;
            border-color: #3c795b;
        }

        .btn-primary:hover {
            background-color: #2f614a;
            border-color: #2f614a;
        }

        .text-center a {
            color: #3c795b;
            text-decoration: none;
        }

        .text-center a:hover {
            text-decoration: underline;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 75%;
            transform: translateY(-50%);
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <!-- Thông báo -->
    <?php if (!empty($login_errors) || !empty($register_errors) || $register_success || isset($_GET['error'])): ?>
        <div class="container mt-3">
            <div class="alert alert-<?php echo $register_success ? 'success' : 'danger'; ?> alert-dismissible fade show"
                role="alert">
                <ul class="mb-0">
                    <?php if (isset($_GET['error'])): ?>
                        <li><?php echo htmlspecialchars($_GET['error'] === 'please_login' ? 'Vui lòng đăng nhập để tiếp tục.' : $_GET['error']); ?>
                        </li>
                    <?php endif; ?>
                    <?php foreach ($login_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                    <?php foreach ($register_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                    <?php if ($register_success): ?>
                        <li><?php echo htmlspecialchars($register_success); ?></li>
                    <?php endif; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Đăng Nhập </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="loginForm">
                        <input type="hidden" name="form_type" value="login">
                        <div class="mb-3">
                            <label for="login_tendangnhap" class="form-label">Tài khoản <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="login_tendangnhap" name="tendangnhap"
                                value="<?php echo isset($tendangnhap) ? htmlspecialchars($tendangnhap) : ''; ?>"
                                required>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="login_matkhau" class="form-label">Mật khẩu <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="login_matkhau" name="matkhau" required>
                            <i class="fas fa-eye password-toggle" id="toggleLoginPassword"></i>
                        </div>
                        <div class="mb-3 text-end">
                            <a href="quen_mk.php" class="text-muted">Quên mật khẩu?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt"></i> Đăng
                            nhập</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Chưa có tài khoản? <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal"
                                data-bs-dismiss="modal">Đăng ký ngay</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel">Đăng Ký</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="registerForm">
                        <input type="hidden" name="form_type" value="register">
                        <div class="mb-3">
                            <label for="register_tendangnhap" class="form-label">Tên đăng nhập <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="register_tendangnhap" name="tendangnhap"
                                value="<?php echo isset($tendangnhap) ? htmlspecialchars($tendangnhap) : ''; ?>"
                                required>
                        </div>
                        <div class="mb-3">
                            <label for="ten" class="form-label">Họ tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten" name="ten"
                                value="<?php echo isset($ten) ? htmlspecialchars($ten) : ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="mail" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="mail" name="mail"
                                value="<?php echo isset($mail) ? htmlspecialchars($mail) : ''; ?>" required>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="register_matkhau" class="form-label">Mật khẩu <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="register_matkhau" name="matkhau" required>
                            <i class="fas fa-eye password-toggle" id="toggleRegisterPassword"></i>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="confirm_matkhau" class="form-label">Xác nhận mật khẩu <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_matkhau" name="confirm_matkhau"
                                required>
                            <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-user-plus"></i> Đăng
                            ký</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Đã có tài khoản? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal"
                                data-bs-dismiss="modal">Đăng nhập</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <h1>🌿 Cộng Đồng Xanh</h1>
            <p>Cùng nhau hành động vì một hành tinh xanh - sạch - đẹp</p>
            <div class="hero-buttons">
                <button class="btn btn-hero-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                    <i class="bi bi-rocket-takeoff-fill"></i> Bắt đầu ngay
                </button>
                <a href="vechungtoi.php" class="btn btn-hero-outline">
                    <i class="bi bi-play-circle-fill"></i> Tìm hiểu thêm
                </a>
            </div>
        </div>
    </div>

    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
    <script src="assets/js/search.js"></script>
    <script>
        // Tự động hiển thị modal khi trang tải
        document.addEventListener('DOMContentLoaded', function () {
            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'), { keyboard: false });
            var registerModal = new bootstrap.Modal(document.getElementById('registerModal'), { keyboard: false });
            <?php if ($register_success): ?>
                loginModal.show(); // Show login modal after successful registration
            <?php elseif (!empty($register_errors)): ?>
                registerModal.show(); // Show register modal if registration fails
            <?php else: ?>
                loginModal.show(); // Default to login modal
            <?php endif; ?>
        });

        // Toggle password visibility
        document.getElementById('toggleLoginPassword').addEventListener('click', function () {
            const passwordField = document.getElementById('login_matkhau');
            const icon = this;
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleRegisterPassword').addEventListener('click', function () {
            const passwordField = document.getElementById('register_matkhau');
            const icon = this;
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            const passwordField = document.getElementById('confirm_matkhau');
            const icon = this;
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Client-side validation for register form
        document.getElementById('registerForm').addEventListener('submit', function (event) {
            const password = document.getElementById('register_matkhau').value;
            const confirmPassword = document.getElementById('confirm_matkhau').value;
            const email = document.getElementById('mail').value;
            const username = document.getElementById('register_tendangnhap').value;
            const name = document.getElementById('ten').value;
            let errors = [];

            if (username.length < 3 || username.length > 50) {
                errors.push('Tên đăng nhập phải từ 3 đến 50 ký tự.');
            }
            if (name.length < 2 || name.length > 100) {
                errors.push('Họ tên phải từ 2 đến 100 ký tự.');
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Email không hợp lệ.');
            }
            if (password.length < 6) {
                errors.push('Mật khẩu phải có ít nhất 6 ký tự.');
            }
            if (password !== confirmPassword) {
                errors.push('Mật khẩu xác nhận không khớp.');
            }

            if (errors.length > 0) {
                event.preventDefault();
                alert(errors.join('\n'));
            }
        });
    </script>
</body>

</html>