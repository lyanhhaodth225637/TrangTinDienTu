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
$article = null;
$topics = [];
$articles = [];

// Lấy thông tin người dùng hiện tại
$user_id = isset($_SESSION['user']['manguoidung']) ? (int)$_SESSION['user']['manguoidung'] : 0;
$user_role = isset($_SESSION['user']['vaitro']) ? (int)$_SESSION['user']['vaitro'] : 2; // Mặc định Thành viên

// Kiểm tra quyền truy cập
if ($user_id == 0 || !in_array($user_role, [0, 1])) {
    $_SESSION['flash_message'] = 'Bạn không có quyền truy cập trang này!';
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit;
}

// Lấy mã bài viết từ URL
$makienthuc = isset($_GET['makienthuc']) ? (int)$_GET['makienthuc'] : 0;

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

// Lấy thông tin bài viết
if ($makienthuc > 0) {
    try {
        $sql_article = "SELECT makienthuc, tieude, machude, noidung, nguon, duongdan, hinhanh, trangthai 
                        FROM tbl_kienthuc 
                        WHERE makienthuc = ? AND manguoidung = ? AND trangthai IN (0, 1)";
        $cmd_article = $conn->prepare($sql_article);
        $cmd_article->execute([$makienthuc, $user_id]);
        $article = $cmd_article->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            $_SESSION['flash_message'] = 'Bài viết không tồn tại hoặc bạn không có quyền chỉnh sửa!';
            $_SESSION['flash_type'] = 'danger';
            header("Location: baiviet_editor.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Lỗi khi lấy thông tin bài viết: ' . htmlspecialchars($e->getMessage());
        $_SESSION['flash_type'] = 'danger';
        error_log("Article Error: " . $e->getMessage());
        header("Location: baiviet_editor.php");
        exit;
    }
} else {
    $_SESSION['flash_message'] = 'Không tìm thấy bài viết!';
    $_SESSION['flash_type'] = 'danger';
    header("Location: baiviet_editor.php");
    exit;
}

// Xử lý cập nhật bài viết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $tieude = isset($_POST['tieude']) ? trim($_POST['tieude']) : '';
    $machude = isset($_POST['machude']) ? (int)$_POST['machude'] : 0;
    $noidung = isset($_POST['noidung']) ? trim($_POST['noidung']) : '';
    $nguon = isset($_POST['nguon']) ? trim($_POST['nguon']) : '';
    $duongdan = isset($_POST['duongdan']) ? trim($_POST['duongdan']) : '';
    $hinhanh = $article['hinhanh'];

    if (empty($tieude) || $machude == 0 || empty($noidung) || empty($nguon) || empty($duongdan)) {
        $_SESSION['flash_message'] = 'Vui lòng điền đầy đủ các trường bắt buộc!';
        $_SESSION['flash_type'] = 'danger';
    } elseif (!filter_var($duongdan, FILTER_VALIDATE_URL)) {
        $_SESSION['flash_message'] = 'Đường dẫn không hợp lệ!';
        $_SESSION['flash_type'] = 'danger';
    } else {
        try {
            // Xử lý upload ảnh mới
            if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/kienthuc/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $file_path)) {
                    // Xóa ảnh cũ nếu có
                    if (!empty($hinhanh) && file_exists($hinhanh)) {
                        unlink($hinhanh);
                    }
                    $hinhanh = $file_path;
                }
            }

            // Cập nhật bài viết
            $sql = "UPDATE tbl_kienthuc 
                    SET tieude = ?, machude = ?, noidung = ?, nguon = ?, duongdan = ?, hinhanh = ?, 
                        trangthai = 0, ngaycapnhat = NOW() 
                    WHERE makienthuc = ? AND manguoidung = ?";
            $cmd = $conn->prepare($sql);
            $cmd->execute([$tieude, $machude, $noidung, $nguon, $duongdan, $hinhanh, $makienthuc, $user_id]);
            $_SESSION['flash_message'] = 'Cập nhật bài viết thành công! Đang chờ duyệt.';
            $_SESSION['flash_type'] = 'success';
            header("Location: baiviet_editor.php");
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = 'Lỗi khi cập nhật bài viết: ' . htmlspecialchars($e->getMessage());
            $_SESSION['flash_type'] = 'danger';
            error_log("Update Article Error: " . $e->getMessage());
        }
    }
    header("Location: baiviet_editor_sua.php?makienthuc=" . $makienthuc);
    exit;
}

// Lấy danh sách bài viết của người dùng cho sidebar
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
    <title>Chỉnh Sửa Bài Viết Kiến Thức - Cộng Đồng Xanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
       
        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .form-edit-article {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .current-image {
            max-width: 200px;
            margin-bottom: 1rem;
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
            <h1><i class="bi bi-pencil-square"></i> Chỉnh Sửa Bài Viết Kiến Thức</h1>
            <p class="mb-0">Chỉnh sửa bài viết của bạn</p>
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
            <!-- Main Content: Edit Article Form -->
            <div class="col-lg-8">
                <?php if ($article): ?>
                    <div class="form-edit-article">
                        <h4 class="mb-3">Chỉnh sửa bài viết</h4>
                        <form action="baiviet_editor_sua.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>" 
                              method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <div class="mb-3">
                                <label for="tieude" class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="tieude" name="tieude" 
                                       value="<?php echo htmlspecialchars($article['tieude']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="machude" class="form-label">Chủ đề <span class="text-danger">*</span></label>
                                <select class="form-select" id="machude" name="machude" required>
                                    <option value="">Chọn chủ đề</option>
                                    <?php foreach ($topics as $topic): ?>
                                        <option value="<?php echo htmlspecialchars($topic['machude']); ?>" 
                                                <?php echo ($topic['machude'] == $article['machude']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($topic['tenchude']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="noidung" class="form-label">Nội dung <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="noidung" name="noidung" rows="6" required>
                                    <?php echo htmlspecialchars($article['noidung']); ?>
                                </textarea>
                            </div>
                            <div class="mb-3">
                                <label for="nguon" class="form-label">Nguồn <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nguon" name="nguon" 
                                       value="<?php echo htmlspecialchars($article['nguon']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="duongdan" class="form-label">Đường dẫn <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="duongdan" name="duongdan" 
                                       value="<?php echo htmlspecialchars($article['duongdan']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="hinhanh" class="form-label">Hình ảnh</label>
                                <?php if (!empty($article['hinhanh']) && file_exists($article['hinhanh'])): ?>
                                    <div>
                                        <img src="<?php echo htmlspecialchars($article['hinhanh']); ?>" 
                                             class="current-image" alt="Hình ảnh hiện tại">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                            </div>
                            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Cập nhật bài viết</button>
                            <a href="baiviet_editor.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-left"></i> Quay lại
                            </a>
                        </form>
                    </div>
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
                        <?php foreach ($articles as $article_item): ?>
                            <div class="article-item">
                                <div class="article-item-title">
                                    <a href="baiviet_chitiet.php?makienthuc=<?php echo htmlspecialchars($article_item['makienthuc']); ?>">
                                        <?php echo htmlspecialchars($article_item['tieude']); ?>
                                    </a>
                                </div>
                                <div class="article-item-date">
                                    <i class="bi bi-bookmark-fill"></i> <?php echo htmlspecialchars($article_item['tenchude'] ?? 'Không rõ'); ?>
                                    <span> · <?php echo date('d/m/Y H:i', strtotime($article_item['ngaytao'])); ?></span>
                                    <span> · 
                                        <?php echo ($article_item['trangthai'] == 1) ? '<span class="badge bg-success">Đã duyệt</span>' : 
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