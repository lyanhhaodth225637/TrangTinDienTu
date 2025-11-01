<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra nếu đã đăng nhập thì chuyển hướng về dashboard
if (isset($_SESSION['user']) && isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], [0, 1])) {
    header('Location: index.php');
    exit;
}

// Biến để lưu thông báo
$message = '';
$message_type = 'danger';
$active_tab = isset($_POST['form_type']) && $_POST['form_type'] === 'register' ? 'register' : 'login';

// Xử lý form đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'login') {
    $tendangnhap = isset($_POST['tendangnhap']) ? trim($_POST['tendangnhap']) : '';
    $matkhau = isset($_POST['matkhau']) ? trim($_POST['matkhau']) : '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($tendangnhap) || empty($matkhau)) {
        $message = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        try {
            // Truy vấn kiểm tra người dùng
            $sql = 'SELECT manguoidung, ten, tendangnhap, matkhau, vaitro, trangthai 
                    FROM tbl_nguoidung 
                    WHERE tendangnhap = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $tendangnhap, PDO::PARAM_STR);
            $cmd->execute();
            $user = $cmd->fetch(PDO::FETCH_ASSOC);

            // Kiểm tra người dùng tồn tại và mật khẩu
            if ($user && password_verify($matkhau, $user['matkhau'])) {
                // Kiểm tra trạng thái tài khoản
                if ($user['trangthai'] == 0) {
                    $message = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
                } elseif (!in_array($user['vaitro'], [0, 1])) {
                    $message = 'Bạn không có quyền truy cập khu vực admin.';
                } else {
                    // Lưu thông tin vào session
                    $_SESSION['user'] = [
                        'manguoidung' => $user['manguoidung'],
                        'ten' => $user['ten'],
                        'tendangnhap' => $user['tendangnhap'],
                        'role' => $user['vaitro'], // 0: admin, 1: tác giả
                    ];
                    // Chuyển hướng đến dashboard
                    header('Location: index.php');
                    exit;
                }
            } else {
                $message = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi đăng nhập: ' . $e->getMessage();
        }
    }
}

// Xử lý form đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'register') {
    $ten = isset($_POST['ten']) ? trim($_POST['ten']) : '';
    $tendangnhap = isset($_POST['tendangnhap']) ? trim($_POST['tendangnhap']) : '';
    $mail = isset($_POST['mail']) ? trim($_POST['mail']) : '';
    $matkhau = isset($_POST['matkhau']) ? trim($_POST['matkhau']) : '';
    $matkhau_confirm = isset($_POST['matkhau_confirm']) ? trim($_POST['matkhau_confirm']) : '';

    // Kiểm tra dữ liệu đầu vào
    if (empty($ten) || empty($tendangnhap) || empty($mail) || empty($matkhau) || empty($matkhau_confirm)) {
        $message = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif ($matkhau !== $matkhau_confirm) {
        $message = 'Mật khẩu xác nhận không khớp.';
    } elseif (strlen($matkhau) < 8 || !preg_match('/[0-9]/', $matkhau) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $matkhau)) {
        $message = 'Mật khẩu phải có ít nhất 8 ký tự, chứa ít nhất 1 số và 1 ký tự đặc biệt.';
    } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Email không hợp lệ.';
    } else {
        try {
            // Kiểm tra tên đăng nhập đã tồn tại
            $sql = 'SELECT COUNT(*) FROM tbl_nguoidung WHERE tendangnhap = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $tendangnhap, PDO::PARAM_STR);
            $cmd->execute();
            if ($cmd->fetchColumn() > 0) {
                $message = 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
            } else {
                // Kiểm tra email đã tồn tại
                $sql = 'SELECT COUNT(*) FROM tbl_nguoidung WHERE mail = ?';
                $cmd = $conn->prepare($sql);
                $cmd->bindValue(1, $mail, PDO::PARAM_STR);
                $cmd->execute();
                if ($cmd->fetchColumn() > 0) {
                    $message = 'Email đã được sử dụng. Vui lòng chọn email khác.';
                } else {
                    // Thêm người dùng mới
                    $hashed_password = password_hash($matkhau, PASSWORD_DEFAULT);
                    $sql = 'INSERT INTO tbl_nguoidung (ten, tendangnhap, mail, matkhau, vaitro, trangthai) 
                            VALUES (?, ?, ?, ?, ?, ?)';
                    $cmd = $conn->prepare($sql);
                    $cmd->bindValue(1, $ten, PDO::PARAM_STR);
                    $cmd->bindValue(2, $tendangnhap, PDO::PARAM_STR);
                    $cmd->bindValue(3, $mail, PDO::PARAM_STR);
                    $cmd->bindValue(4, $hashed_password, PDO::PARAM_STR);
                    $cmd->bindValue(5, 1, PDO::PARAM_INT); // Mặc định vai trò là tác giả (1)
                    $cmd->bindValue(6, 1, PDO::PARAM_INT); // Mặc định trạng thái là hoạt động (1)
                    $cmd->execute();

                    $message = 'Đăng ký tài khoản thành công! Vui lòng đăng nhập.';
                    $message_type = 'success';
                    $active_tab = 'login'; // Chuyển về tab đăng nhập
                }
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi đăng ký: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập / Đăng Ký Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h3 class="text-center mb-4">Đăng Nhập / Đăng Ký Admin</h3>

        <!-- Hiển thị thông báo -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab === 'login' ? 'active' : ''; ?>" id="login-tab"
                    data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab" aria-controls="login"
                    aria-selected="<?php echo $active_tab === 'login' ? 'true' : 'false'; ?>">
                    Đăng Nhập
                </button>
            </li>
            <li class="nav-item" role="presentation " hidden>
                <button class="nav-link <?php echo $active_tab === 'register' ? 'active' : ''; ?>" id="register-tab"
                    data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab" aria-controls="register"
                    aria-selected="<?php echo $active_tab === 'register' ? 'true' : 'false'; ?>">
                    Đăng Ký
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="authTabContent">
            <!-- Đăng Nhập -->
            <div class="tab-pane fade <?php echo $active_tab === 'login' ? 'show active' : ''; ?>" id="login"
                role="tabpanel" aria-labelledby="login-tab">
                <form method="post">
                    <input type="hidden" name="form_type" value="login">
                    <div class="mb-3">
                        <label for="login_tendangnhap" class="form-label">Tên đăng nhập</label>
                        <input type="text" class="form-control" id="login_tendangnhap" name="tendangnhap"
                            value="<?php echo isset($_POST['tendangnhap']) && $active_tab === 'login' ? htmlspecialchars($_POST['tendangnhap']) : ''; ?>"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="login_matkhau" class="form-label">Mật khẩu</label>
                        <input type="password" class="form-control" id="login_matkhau" name="matkhau" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt"></i> Đăng
                        nhập</button>
                </form>
            </div>

            <!-- Đăng Ký -->
            <div class="tab-pane fade <?php echo $active_tab === 'register' ? 'show active' : ''; ?>" id="register"
                role="tabpanel" aria-labelledby="register-tab">
                <form method="post">
                    <input type="hidden" name="form_type" value="register">
                    <div class="mb-3">
                        <label for="register_ten" class="form-label">Họ và tên</label>
                        <input type="text" class="form-control" id="register_ten" name="ten"
                            value="<?php echo isset($_POST['ten']) && $active_tab === 'register' ? htmlspecialchars($_POST['ten']) : ''; ?>"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="register_tendangnhap" class="form-label">Tên đăng nhập</label>
                        <input type="text" class="form-control" id="register_tendangnhap" name="tendangnhap"
                            value="<?php echo isset($_POST['tendangnhap']) && $active_tab === 'register' ? htmlspecialchars($_POST['tendangnhap']) : ''; ?>"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="register_mail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="register_mail" name="mail"
                            value="<?php echo isset($_POST['mail']) && $active_tab === 'register' ? htmlspecialchars($_POST['mail']) : ''; ?>"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="register_matkhau" class="form-label">Mật khẩu</label>
                        <input type="password" class="form-control" id="register_matkhau" name="matkhau" required>
                    </div>
                    <div class="mb-3">
                        <label for="register_matkhau_confirm" class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" class="form-control" id="register_matkhau_confirm" name="matkhau_confirm"
                            required>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><i class="fas fa-user-plus"></i> Đăng
                        ký</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>