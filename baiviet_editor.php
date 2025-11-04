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
$articles = [];
$topics = [];

// Lấy thông tin người dùng hiện tại
$user_id = isset($_SESSION['user']['manguoidung']) ? (int) $_SESSION['user']['manguoidung'] : 0;
$user_role = isset($_SESSION['user']['vaitro']) ? (int) $_SESSION['user']['vaitro'] : 2; // Mặc định Thành viên

// Kiểm tra quyền truy cập
if ($user_id == 0 || !in_array($user_role, [0, 1])) {
    $_SESSION['flash_message'] = 'Bạn không có quyền truy cập trang này!';
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit;
}

// Lấy danh sách chủ đề
try {
    $sql_topics = "SELECT machude, tenchude FROM tbl_chude ORDER BY tenchude ASC";
    $cmd_topics = $conn->prepare($sql_topics);
    $cmd_topics->execute();
    $topics = $cmd_topics->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Lỗi khi lấy danh sách chủ đề: ' . htmlspecialchars($e->getMessage());
    $_SESSION['flash_type'] = 'danger';
    error_log("Topics Error: " . $e->getMessage());
    $topics = [];
}

// Xử lý thêm bài viết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $tieude = isset($_POST['tieude']) ? trim($_POST['tieude']) : '';
    $machude = isset($_POST['machude']) ? (int) $_POST['machude'] : 0;
    $noidung = isset($_POST['noidung']) ? trim($_POST['noidung']) : '';
    $nguon = isset($_POST['nguon']) ? trim($_POST['nguon']) : '';
    $duongdan = isset($_POST['duongdan']) ? trim($_POST['duongdan']) : '';
    $hinhanh = '';

    if (empty($tieude) || $machude == 0 || empty($noidung) || empty($nguon) || empty($duongdan)) {
        $_SESSION['flash_message'] = 'Vui lòng điền đầy đủ các trường bắt buộc!';
        $_SESSION['flash_type'] = 'danger';
    } else {
        try {
            // Xử lý upload ảnh
            if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/kienthuc/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $file_path)) {
                    $hinhanh = $file_path;
                }
            }

            // Thêm bài viết
            $sql = "INSERT INTO tbl_kienthuc (tieude, manguoidung, machude, noidung, nguon, duongdan, hinhanh, ngaytao, trangthai, luotxem, luotchiase) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0, 0)";
            $cmd = $conn->prepare($sql);
            $cmd->execute([$tieude, $user_id, $machude, $noidung, $nguon, $duongdan, $hinhanh]);
            $_SESSION['flash_message'] = 'Thêm bài viết thành công! Đang chờ duyệt.';
            $_SESSION['flash_type'] = 'success';
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = 'Lỗi khi thêm bài viết: ' . htmlspecialchars($e->getMessage());
            $_SESSION['flash_type'] = 'danger';
            error_log("Add Article Error: " . $e->getMessage());
        }
    }
    header("Location: baiviet_editor.php");
    exit;
}

// Xử lý xóa bài viết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['makienthuc'])) {
    $makienthuc = (int) $_POST['makienthuc'];
    try {
        // Kiểm tra bài viết thuộc về người dùng
        $sql = "SELECT manguoidung, hinhanh FROM tbl_kienthuc WHERE makienthuc = ?";
        $cmd = $conn->prepare($sql);
        $cmd->execute([$makienthuc]);
        $article = $cmd->fetch(PDO::FETCH_ASSOC);

        if ($article && $article['manguoidung'] == $user_id) {
            // Xóa ảnh nếu có
            if (!empty($article['hinhanh']) && file_exists($article['hinhanh'])) {
                unlink($article['hinhanh']);
            }
            // Xóa bài viết (đặt trang_thai = 0)
            $sql = "UPDATE tbl_kienthuc SET trangthai = 0 WHERE makienthuc = ? AND manguoidung = ?";
            $cmd = $conn->prepare($sql);
            $cmd->execute([$makienthuc, $user_id]);
            $_SESSION['flash_message'] = 'Xóa bài viết thành công!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Bạn không có quyền xóa bài viết này!';
            $_SESSION['flash_type'] = 'danger';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Lỗi khi xóa bài viết: ' . htmlspecialchars($e->getMessage());
        $_SESSION['flash_type'] = 'danger';
        error_log("Delete Article Error: " . $e->getMessage());
    }
    header("Location: baiviet_editor.php");
    exit;
}

// Lấy danh sách bài viết của người dùng
try {
    $sql_articles = "SELECT k.makienthuc, k.tieude, k.ngaytao, k.trangthai, c.tenchude 
                     FROM tbl_kienthuc k 
                     LEFT JOIN tbl_chude c ON k.machude = c.machude 
                     WHERE k.manguoidung = ? AND k.trangthai IN (0, 1) 
                     ORDER BY k.ngaytao DESC";
    $cmd_articles = $conn->prepare($sql_articles);
    $cmd_articles->execute([$user_id]);
    $articles = $cmd_articles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['flash_message'] = 'Lỗi khi lấy danh sách bài viết: ' . htmlspecialchars($e->getMessage());
    $_SESSION['flash_type'] = 'danger';
    error_log("Articles Error: " . $e->getMessage());
    $articles = [];
}

// Xử lý flash message
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT4Earth - Quản Lý Bài Viết Kiến Thức</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            padding: 2.5rem 0;
            color: white;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 12px rgba(60, 121, 91, 0.2);
            border-radius: 0 0 15px 15px;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .article-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            padding: 1.25rem;
        }

        .article-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #050505;
            text-decoration: none;
        }

        .article-title:hover {
            color: var(--primary-green);
        }

        .article-meta {
            font-size: 0.85rem;
            color: #65676b;
            margin-bottom: 0.5rem;
        }

        .article-actions {
            margin-top: 1rem;
        }

        .form-add-article {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
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

        .article-item {
            margin-bottom: 0.75rem;
        }

        .article-item-title a {
            color: #050505;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .article-item-title a:hover {
            text-decoration: underline;
            color: var(--primary-green);
        }

        .article-item-date {
            font-size: 0.8rem;
            color: #65676b;
        }

        .sidebar-widget ul {
            padding-left: 1.2rem;
            color: #65676b;
            line-height: 1.8;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="bi bi-pencil-square"></i> Quản Lý Bài Viết Kiến Thức</h1>
            <p class="mb-0">Tạo và quản lý các bài viết kiến thức của bạn</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Main Content: Add Article Form & Article List -->
            <div class="col-lg-8">
                <!-- Form thêm bài viết -->
                <div class="form-add-article">
                    <h4 class="mb-3">Thêm bài viết mới</h4>
                    <form action="baiviet_editor.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="tieude" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tieude" name="tieude" required>
                        </div>
                        <div class="mb-3">
                            <label for="machude" class="form-label">Chủ đề <span class="text-danger">*</span></label>
                            <select class="form-select" id="machude" name="machude" required>
                                <option value="">Chọn chủ đề</option>
                                <?php foreach ($topics as $topic): ?>
                                    <option value="<?php echo htmlspecialchars($topic['machude']); ?>">
                                        <?php echo htmlspecialchars($topic['tenchude']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="noidung" class="form-label">Nội dung <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="noidung" name="noidung" rows="6" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="nguon" class="form-label">Nguồn <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nguon" name="nguon" required>
                        </div>
                        <div class="mb-3">
                            <label for="duongdan" class="form-label">Đường dẫn <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="duongdan" name="duongdan" required >
                        </div>
                        <div class="mb-3">
                            <label for="hinhanh" class="form-label">Hình ảnh</label>
                            <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle"></i> Thêm bài
                            viết</button>
                    </form>
                </div>

                <!-- Danh sách bài viết -->
                <h4 class="mb-4">Danh sách bài viết của bạn</h4>
                <?php if (empty($articles)): ?>
                    <div class="alert alert-info text-center">Bạn chưa có bài viết nào.</div>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                        <div class="article-card">
                            <a href="baiviet_editor_chitiet.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>"
                                class="article-title">
                                <?php echo htmlspecialchars($article['tieude']); ?>
                            </a>
                            <div class="article-meta">
                                <span><i class="bi bi-bookmark-fill"></i> Chủ đề:
                                    <?php echo htmlspecialchars($article['tenchude'] ?? 'Không rõ'); ?></span>
                                <span><i class="bi bi-calendar-check"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($article['ngaytao'])); ?></span>
                                <span><i class="bi bi-circle-fill"></i> Trạng thái:
                                    <?php echo ($article['trangthai'] == 1) ? '<span class="badge bg-success">Đã duyệt</span>' :
                                        '<span class="badge bg-warning">Chưa duyệt</span>'; ?>
                                </span>
                            </div>
                            <div class="article-actions">
                                <a href="baiviet_editor_sua.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>"
                                    class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil"></i> Sửa
                                </a>
                                <form action="baiviet_editor.php" method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="makienthuc" value="<?php echo $article['makienthuc']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Bạn có chắc chắn muốn xóa bài viết này?');">
                                        <i class="bi bi-trash"></i> Xóa
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4 sidebar">
                <div class="sidebar-widget">
                    <div class="widget-title"><i class="bi bi-file-text-fill"></i> Bài viết của bạn</div>
                    <?php if (empty($articles)): ?>
                        <div class="text-center text-muted" style="font-size: 0.9rem;">
                            Bạn chưa có bài viết nào.
                        </div>
                    <?php else: ?>
                        <?php foreach ($articles as $article): ?>
                            <div class="article-item">
                                <div class="article-item-title">
                                    <a
                                        href="baiviet_editor_chitiet.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>">
                                        <?php echo htmlspecialchars($article['tieude']); ?>
                                    </a>
                                </div>
                                <div class="article-item-date">
                                    <i class="bi bi-bookmark-fill"></i>
                                    <?php echo htmlspecialchars($article['tenchude'] ?? 'Không rõ'); ?>
                                    <span> · <?php echo date('d/m/Y H:i', strtotime($article['ngaytao'])); ?></span>
                                    <span> ·
                                        <?php echo ($article['trangthai'] == 1) ? '<span class="badge bg-success">Đã duyệt</span>' :
                                            '<span class="badge bg-warning">Chưa duyệt</span>'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="sidebar-widget">
                    <div class="widget-title"><i class="bi bi-info-circle-fill"></i> Quy tắc chung</div>
                    <ul>
                        <li>Viết nội dung chính xác, hữu ích</li>
                        <li>Tôn trọng bản quyền hình ảnh</li>
                        <li>Không đăng nội dung vi phạm pháp luật</li>
                        <li>Tuân thủ quy định của cộng đồng</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>