<?php
session_start();
include __DIR__ . '/config/ketnoi.php'; // Đảm bảo đường dẫn đúng

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Biến để lưu thông báo
$message = '';
$message_type = '';
$user = null; // Khởi tạo biến user
$posts = []; // Khởi tạo biến posts

// Kiểm tra trạng thái đăng nhập
if (!isset($_SESSION['user']['manguoidung'])) {
    // Lưu URL hiện tại để redirect sau khi đăng nhập (tùy chọn)
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php?message=" . urlencode("Vui lòng đăng nhập để xem hồ sơ!") . "&type=warning");
    exit;
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user']['manguoidung'];
try {
    $sql_user = "SELECT manguoidung, ten, anhdaidien, vaitro, mail 
                 FROM tbl_nguoidung 
                 WHERE manguoidung = ?";
    $cmd_user = $conn->prepare($sql_user);
    $cmd_user->execute([$user_id]);
    $user = $cmd_user->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        // Nếu không tìm thấy user dù đã đăng nhập -> lỗi lạ -> đăng xuất
        unset($_SESSION['user']);
        header("Location: login.php?message=" . urlencode("Lỗi thông tin người dùng, vui lòng đăng nhập lại.") . "&type=danger");
        exit;
    }
} catch (PDOException $e) {
    $message = 'Lỗi khi lấy thông tin người dùng: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
    error_log("User Info Error: " . $e->getMessage());
    // Không nên dừng hẳn trang ở đây, có thể user vẫn null và hiển thị lỗi
}

// Xử lý hành động bài viết (duyệt, từ chối, xóa)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) { // Chỉ xử lý nếu user tồn tại
    $post_id = isset($_POST['ma_bai_viet']) ? (int) $_POST['ma_bai_viet'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($post_id > 0) {
        try {
            // Kiểm tra quyền truy cập (Admin hoặc chủ sở hữu bài viết)
            $sql_check = "SELECT ma_nguoi_dang, hinhanh FROM tbl_diendan WHERE ma_bai_viet = ?"; // Giả sử bảng diendan có cột hinhanh
            $cmd_check = $conn->prepare($sql_check);
            $cmd_check->execute([$post_id]);
            $post_check = $cmd_check->fetch(PDO::FETCH_ASSOC);

            if (!$post_check) {
                $message = 'Bài viết không tồn tại!';
                $message_type = 'danger';
                // Sửa: Dùng $user['vaitro'] thay vì $user['quyen']
            } elseif ($post_check['ma_nguoi_dang'] != $user_id && $user['vaitro'] != 0) {
                $message = 'Bạn không có quyền thực hiện hành động này!';
                $message_type = 'danger';
            } else {
                // Chỉ Admin mới có quyền duyệt/từ chối
                if ($action === 'approve' && $user['vaitro'] == 0) {
                    $sql = "UPDATE tbl_diendan SET trang_thai = 1 WHERE ma_bai_viet = ?";
                    $cmd = $conn->prepare($sql);
                    $cmd->execute([$post_id]);
                    $_SESSION['flash_message'] = 'Bài viết đã được duyệt!'; // Dùng flash message
                    $_SESSION['flash_type'] = 'success';
                } elseif ($action === 'reject' && $user['vaitro'] == 0) {
                    // CSDL của bạn không có trạng thái 2 (từ chối), nên ta quay về 0 (chưa duyệt)
                    $sql = "UPDATE tbl_diendan SET trang_thai = 0 WHERE ma_bai_viet = ?";
                    $cmd = $conn->prepare($sql);
                    $cmd->execute([$post_id]);
                    $_SESSION['flash_message'] = 'Bài viết đã được đưa về trạng thái chờ duyệt!'; // Sửa thông báo
                    $_SESSION['flash_type'] = 'info'; // Dùng info
                }
                // Admin hoặc chủ sở hữu có quyền xóa
                elseif ($action === 'delete') {
                    // Xóa ảnh nếu có (Giả sử bạn có cột hinhanh và lưu đường dẫn tương đối)
                    // if (!empty($post_check['hinhanh']) && file_exists(__DIR__ . '/' . $post_check['hinhanh'])) {
                    //     unlink(__DIR__ . '/' . $post_check['hinhanh']);
                    // }

                    // Thực hiện xóa cứng bài viết khỏi CSDL
                    $sql_delete_comments = "DELETE FROM tbl_binhluandiendan WHERE ma_bai_viet = ?";
                    $cmd_delete_comments = $conn->prepare($sql_delete_comments);
                    $cmd_delete_comments->execute([$post_id]);

                    $sql_delete_post = "DELETE FROM tbl_diendan WHERE ma_bai_viet = ?";
                    $cmd_delete_post = $conn->prepare($sql_delete_post);

                    if ($cmd_delete_post->execute([$post_id])) {
                        $_SESSION['flash_message'] = 'Xóa bài viết thành công!';
                        $_SESSION['flash_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = 'Không thể xóa bài viết.';
                        $_SESSION['flash_type'] = 'danger';
                    }
                }
                // Redirect sau khi xử lý POST để tránh F5 gửi lại form
                header("Location: hoso.php");
                exit;
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi xử lý bài viết: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Post Action Error: " . $e->getMessage());
        }
    }
}

// Lấy danh sách bài viết diễn đàn của người dùng (Nếu user tồn tại)
if ($user) {
    try {
        // === SỬA SQL: Thêm cột ma_nguoi_dang ===
        $sql_posts = "SELECT ma_bai_viet, tieu_de, noi_dung, ngay_tao, luot_xem, trang_thai, ma_nguoi_dang 
                      FROM tbl_diendan 
                      WHERE ma_nguoi_dang = ? 
                      ORDER BY ngay_tao DESC"; // Bỏ điều kiện trang_thai IN (0,1) để xem cả bài đã bị đánh dấu xóa (nếu bạn dùng soft delete)
        $cmd_posts = $conn->prepare($sql_posts);
        $cmd_posts->execute([$user_id]);
        $posts = $cmd_posts->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (empty($message)) { // Chỉ gán lỗi nếu chưa có
            $message = 'Lỗi khi lấy danh sách bài viết diễn đàn: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
        error_log("Forum Posts Error: " . $e->getMessage());
        $posts = []; // Đảm bảo posts là mảng rỗng nếu lỗi
    }
}

// Lấy thông báo flash từ session (nếu có) và xóa nó đi
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
// Hoặc lấy từ URL (cho các lỗi ban đầu)
elseif (empty($message) && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = isset($_GET['type']) ? urldecode($_GET['type']) : 'success';
}

$default_avatar = 'uploads/avatars/avt.jpg'; // Avatar mặc định
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hồ Sơ <?php echo $user ? htmlspecialchars($user['ten']) : ''; ?> - Cộng Đồng Xanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .profile-card {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
        }

        .profile-avatar {
            width: 120px;
            /* Tăng kích thước */
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            /* Thêm viền trắng */
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
            /* Thêm bóng */
            margin-bottom: 1rem;
            /* Tăng khoảng cách */
        }

        .profile-info h3 {
            font-weight: bold;
            color: #050505;
        }

        .profile-info p {
            color: #65676b;
            margin-bottom: 0.5rem;
        }

        .profile-info strong {
            color: #050505;
        }

        .profile-actions a {
            margin-top: 1.5rem;
        }

        .post-list-section h4 {
            margin-top: 2rem;
            margin-bottom: 1.5rem;
            font-weight: bold;
        }

        .post-card {
            background: #fff;
            padding: 1.25rem;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-green);

        }

        .post-card h5 a {
            color: #050505;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .post-card h5 a:hover {
            color: var(--primary-green);
            text-decoration: underline;
        }

        .post-meta {
            font-size: 0.85rem;
            color: #65676b;
            margin-top: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .post-meta span {
            margin-right: 15px;
        }

        .post-content-preview {
            color: #333;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .post-actions .btn {
            font-size: 0.85rem;
            padding: 0.3rem 0.7rem;
        }

        .post-status {
            font-weight: bold;
        }

        .status-approved {
            color: #198754;
        }

        .status-pending {
            color: #ffc107;
        }


        .status-deleted {
            color: #6c757d;
            font-style: italic;
        }


        .sidebar {
            position: sticky;
            top: 80px;
            align-self: flex-start;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                position: static;
                margin-top: 2rem;
            }
        }

        .sidebar-widget {
            background: #fff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .widget-title {
            font-weight: bold;
            color: #050505;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--bs-border-color-translucent);
            padding-bottom: 0.5rem;
        }

        .widget-title i {
            color: var(--primary-green);
            margin-right: 0.5rem;
        }


        .widget-post-item {
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .widget-post-item a {
            color: #050505;
            text-decoration: none;
        }

        .widget-post-item a:hover {
            color: var(--primary-green);
        }

        .widget-post-date {
            font-size: 0.75rem;
            color: #65676b;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="bi bi-person-circle"></i> Hồ Sơ Người Dùng</h1>
            <p class="mb-0">Xem và quản lý thông tin cá nhân của bạn</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content: Profile Info & Posts -->
            <div class="col-lg-8">
                <!-- Profile Information Card -->
                <?php if ($user): ?>
                    <div class="profile-card text-center text-md-start">
                        <div class="row align-items-center">
                            <div class="col-md-3 text-center mb-3 mb-md-0">
                                <?php
                                $avatar = (!empty($user['anhdaidien']) && file_exists($user['anhdaidien']))
                                    ? htmlspecialchars($user['anhdaidien'])
                                    : $default_avatar;
                                ?>
                                <img src="<?php echo $avatar; ?>" class="profile-avatar" alt="Avatar">
                            </div>
                            <div class="col-md-9 profile-info">
                                <h3><?php echo htmlspecialchars($user['ten']); ?></h3>
                                <p><i class="bi bi-envelope-fill text-muted me-2"></i><strong>Email:</strong>
                                    <?php echo htmlspecialchars($user['mail']); ?></p>
                                <p><i class="bi bi-person-badge-fill text-muted me-2"></i><strong>Vai trò:</strong>
                                    <span class="badge bg-<?php
                                    if ($user['vaitro'] === 0)
                                        echo 'danger';
                                    elseif ($user['vaitro'] === 1)
                                        echo 'success';
                                    else
                                        echo 'primary';
                                    ?>">
                                        <?php
                                        echo $user['vaitro'] == 0 ? 'Quản trị viên' :
                                            ($user['vaitro'] == 1 ? 'Tác giả' : 'Thành viên');
                                        ?>
                                    </span>
                                </p>
                                <div class="profile-actions">
                                    <a href="hoso_sua.php" class="btn btn-outline-primary"><i
                                            class="bi bi-pencil-square"></i> Chỉnh sửa hồ sơ</a>
                                    
                                    <?php if ($user['vaitro'] != 0): // chỉ hiện nếu chưa là admin 
                                                ?>
                                        <a href="hoso_xinquyen.php?manguoidung=<?php echo $user['manguoidung']; ?>&from_vaitro=<?php echo $user['vaitro']; ?>"
                                            class="btn btn-outline-success ms-2">
                                            <i class="bi bi-shield-lock-fill"></i> Đăng ký Editor
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">Không thể tải thông tin hồ sơ.</div>
                <?php endif; ?>

                <!-- User's Forum Posts Section -->
                <div class="post-list-section" hidden>
                    <h4 class="mb-3">Bài viết diễn đàn của bạn</h4>
                    <?php if (empty($posts)): ?>
                        <div class="alert alert-light border text-center">Bạn chưa có bài viết diễn đàn nào. <a
                                href="baiviet_diendan_dang.php">Tạo bài viết mới?</a></div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="post-card">
                                <h5>
                                    <a
                                        href="baiviet_diendan_chitiet.php?ma_bai_viet=<?php echo htmlspecialchars($post['ma_bai_viet']); ?>">
                                        <?php echo htmlspecialchars($post['tieu_de']); ?>
                                    </a>
                                </h5>
                                <div class="post-meta">
                                    <span><i class="bi bi-calendar3"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($post['ngay_tao'])); ?></span>
                                    <span><i class="bi bi-eye"></i> <?php echo number_format($post['luot_xem']); ?></span>
                                    <span>
                                        Trạng thái:
                                        <?php
                                        if ($post['trang_thai'] == 1) {
                                            echo '<span class="post-status status-approved">Đã duyệt</span>';
                                        } elseif ($post['trang_thai'] == 0) {
                                            echo '<span class="post-status status-pending">Chờ duyệt</span>';
                                        } else {
                                            echo '<span class="post-status status-deleted">Đã ẩn/xóa</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <p class="post-content-preview">
                                    <?php echo htmlspecialchars(mb_substr($post['noi_dung'], 0, 150, 'UTF-8')) . (mb_strlen($post['noi_dung'], 'UTF-8') > 150 ? '...' : ''); ?>
                                </p>

                                <!-- Nút Sửa/Xóa -->
                                <div class="post-actions mt-2">
                                    <a href="baiviet_diendan_sua.php?ma_bai_viet=<?php echo htmlspecialchars($post['ma_bai_viet']); ?>"
                                        class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </a>
                                    <form action="hoso.php" method="post" style="display: inline;"
                                        onsubmit="return confirm('Bạn có chắc chắn muốn xóa bài viết này? Hành động này không thể hoàn tác!');">
                                        <input type="hidden" name="ma_bai_viet" value="<?php echo $post['ma_bai_viet']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-outline-danger btn-sm ms-2">
                                            <i class="bi bi-trash"></i> Xóa
                                        </button>
                                    </form>
                                    <!-- Nút Duyệt/Bỏ duyệt chỉ cho Admin -->
                                    <?php if ($user && $user['vaitro'] == 0): ?>
                                        <?php if ($post['trang_thai'] == 0): ?>
                                            <form action="hoso.php" method="post" style="display: inline;">
                                                <input type="hidden" name="ma_bai_viet" value="<?php echo $post['ma_bai_viet']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-outline-success btn-sm ms-2">
                                                    <i class="bi bi-check-circle"></i> Duyệt
                                                </button>
                                            </form>
                                        <?php elseif ($post['trang_thai'] == 1): ?>
                                            <form action="hoso.php" method="post" style="display: inline;">
                                                <input type="hidden" name="ma_bai_viet" value="<?php echo $post['ma_bai_viet']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-outline-warning btn-sm ms-2">
                                                    <i class="bi bi-x-circle"></i> Bỏ duyệt
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 sidebar">
                <!-- Widget Hoạt động đã tham gia-->
                <div class="sidebar-widget" hidden>
                    <div class="widget-title"><i class="bi bi-calendar-check-fill"></i> Hoạt động đã tham gia</div>
                    <?php
                    $joined_activities = [];
                    if ($user_id > 0) {
                        try {
                            $sql_joined = "SELECT h.mahoatdong, h.tenhoatdong FROM tbl_thamgia t JOIN tbl_hoatdong h ON t.mahoatdong = h.mahoatdong WHERE t.manguoidung = ? AND t.trangthai = 1 AND h.trangthai = 1 ORDER BY h.thoigianbatdau DESC LIMIT 5";
                            $cmd_joined = $conn->prepare($sql_joined);
                            $cmd_joined->execute([$user_id]);
                            $joined_activities = $cmd_joined->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            error_log("Joined Activities Error: " . $e->getMessage());
                        }
                    }
                    ?>
                    <?php if (empty($joined_activities)): ?>
                        <p class="text-muted small">Bạn chưa tham gia hoạt động nào.</p>
                    <?php else: ?>
                        <?php foreach ($joined_activities as $j_act): ?>
                            <div class="widget-post-item">
                                <a
                                    href="hoatdong_chitiet.php?mahoatdong=<?php echo $j_act['mahoatdong']; ?>"><?php echo htmlspecialchars($j_act['tenhoatdong']); ?></a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Widget Bài viết kiến thức đã đăng  -->
                <div class="sidebar-widget">
                    <div class="widget-title"><i class="bi bi-journal-text"></i> Bài viết</div>
                    <?php
                    $knowledge_posts = [];
                    if ($user_id > 0 && $user && $user['vaitro'] != 2) { // Chỉ Admin và Tác giả
                        try {
                            $sql_knowledge = "SELECT makienthuc, tieude FROM tbl_kienthuc WHERE manguoidung = ? AND trangthai = 1 ORDER BY ngaytao DESC LIMIT 5";
                            $cmd_knowledge = $conn->prepare($sql_knowledge);
                            $cmd_knowledge->execute([$user_id]);
                            $knowledge_posts = $cmd_knowledge->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            error_log("Knowledge Posts Error: " . $e->getMessage());
                        }
                    }
                    ?>
                    <?php if (empty($knowledge_posts)): ?>
                        <p class="text-muted small">
                            <?php echo ($user && $user['vaitro'] != 2) ? 'Bạn chưa có bài viết kiến thức nào được duyệt.' : 'Đăng ký quyền tác giả để được đăng tin.'; ?>
                        </p>
                    <?php else: ?>
                        <?php foreach ($knowledge_posts as $k_post): ?>
                            <div class="widget-post-item">
                                <a
                                    href="kienthuc_chitiet.php?makienthuc=<?php echo $k_post['makienthuc']; ?>"><?php echo htmlspecialchars($k_post['tieude']); ?></a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>