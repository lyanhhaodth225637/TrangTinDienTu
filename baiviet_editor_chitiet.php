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
$articles = [];

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

// Lấy mã bài viết từ URL
$makienthuc = isset($_GET['makienthuc']) ? (int) $_GET['makienthuc'] : 0;

// Lấy thông tin bài viết
if ($makienthuc > 0) {
    try {
        $sql_article = "SELECT k.makienthuc, k.tieude, k.machude, k.noidung, k.nguon, k.duongdan, k.hinhanh, 
                               k.ngaytao, k.ngaycapnhat, k.trangthai, k.luotxem, k.luotchiase, c.tenchude 
                        FROM tbl_kienthuc k 
                        LEFT JOIN tbl_chude c ON k.machude = c.machude 
                        WHERE k.makienthuc = ? AND k.manguoidung = ? AND k.trangthai IN (0, 1)";
        $cmd_article = $conn->prepare($sql_article);
        $cmd_article->execute([$makienthuc, $user_id]);
        $article = $cmd_article->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            $_SESSION['flash_message'] = 'Bài viết không tồn tại hoặc bạn không có quyền xem!';
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

// Xử lý xóa bài viết
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['makienthuc'])) {
    $makienthuc = (int) $_POST['makienthuc'];
    try {
        // Kiểm tra bài viết thuộc về người dùng
        $sql = "SELECT manguoidung, hinhanh FROM tbl_kienthuc WHERE makienthuc = ?";
        $cmd = $conn->prepare($sql);
        $cmd->execute([$makienthuc]);
        $article_check = $cmd->fetch(PDO::FETCH_ASSOC);

        if ($article_check && $article_check['manguoidung'] == $user_id) {
            // Xóa ảnh nếu có
            if (!empty($article_check['hinhanh']) && file_exists($article_check['hinhanh'])) {
                unlink($article_check['hinhanh']);
            }
            // Xóa bài viết (đặt trang_thai = 0)
            $sql = "UPDATE tbl_kienthuc SET trangthai = 0 WHERE makienthuc = ? AND manguoidung = ?";
            $cmd = $conn->prepare($sql);
            $cmd->execute([$makienthuc, $user_id]);
            $_SESSION['flash_message'] = 'Xóa bài viết thành công!';
            $_SESSION['flash_type'] = 'success';
            header("Location: baiviet_editor.php");
            exit;
        } else {
            $_SESSION['flash_message'] = 'Bạn không có quyền xóa bài viết này!';
            $_SESSION['flash_type'] = 'danger';
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Lỗi khi xóa bài viết: ' . htmlspecialchars($e->getMessage());
        $_SESSION['flash_type'] = 'danger';
        error_log("Delete Article Error: " . $e->getMessage());
    }
    header("Location: baiviet_chitiet_editor.php?makienthuc=" . $makienthuc);
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

$default_image = 'uploads/kienthuc/default.jpg';

?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chi Tiết Bài Viết Kiến Thức - Cộng Đồng Xanh</title>
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
            overflow: hidden;
        }

        .article-card-img {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
        }

        .article-card-body {
            padding: 1.5rem;
        }

        .article-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #050505;
        }

        .article-meta {
            font-size: 0.9rem;
            color: #65676b;
            margin-bottom: 1.5rem;
        }

        .article-meta span {
            margin-right: 15px;
        }

        .article-meta i {
            color: var(--primary-green);
        }

        .article-content {
            color: #050505;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }

        .article-actions {
            border-top: 1px solid #dee2e6;
            padding-top: 1.5rem;
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
            <h1><i class="bi bi-file-text-fill"></i> Chi Tiết Bài Viết Kiến Thức</h1>
            <p class="mb-0">Xem chi tiết bài viết của bạn</p>
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
            <!-- Main Content: Article Details -->
            <div class="col-lg-8">
                <?php if ($article): ?>
                    <div class="article-card">
                        <?php
                        $article_image = (!empty($article['hinhanh']) && file_exists($article['hinhanh']))
                            ? htmlspecialchars($article['hinhanh'])
                            : $default_image;
                        ?>
                        <img src="<?php echo $article_image; ?>" class="article-card-img" alt="Ảnh bài viết">
                        <div class="article-card-body">
                            <h2 class="article-title"><?php echo htmlspecialchars($article['tieude']); ?></h2>
                            <div class="article-meta">
                                <span><i class="bi bi-bookmark-fill"></i> Chủ đề:
                                    <?php echo htmlspecialchars($article['tenchude'] ?? 'Không rõ'); ?></span>
                                <span><i class="bi bi-calendar-check"></i> Ngày tạo:
                                    <?php echo date('d/m/Y H:i', strtotime($article['ngaytao'])); ?></span>
                                <?php if (!empty($article['ngaycapnhat'])): ?>
                                    <span><i class="bi bi-calendar-check"></i> Ngày cập nhật:
                                        <?php echo date('d/m/Y H:i', strtotime($article['ngaycapnhat'])); ?></span>
                                <?php endif; ?>
                                <span><i class="bi bi-circle-fill"></i> Trạng thái:
                                    <?php echo ($article['trangthai'] == 1) ? '<span class="badge bg-success">Đã duyệt</span>' :
                                        '<span class="badge bg-warning">Chưa duyệt</span>'; ?>
                                </span>
                                <span><i class="bi bi-eye-fill"></i> Lượt xem:
                                    <?php echo htmlspecialchars($article['luotxem']); ?></span>
                                <span><i class="bi bi-share-fill"></i> Lượt chia sẻ:
                                    <?php echo htmlspecialchars($article['luotchiase']); ?></span>
                            </div>
                            <div class="article-content"><?php echo nl2br(htmlspecialchars($article['noidung'])); ?></div>
                            <div class="article-meta">
                                <span><i class="bi bi-link-45deg"></i> Nguồn: <a
                                        href="<?php echo htmlspecialchars($article['duongdan']); ?>"
                                        target="_blank"><?php echo htmlspecialchars($article['nguon']); ?></a></span>
                            </div>
                            <div class="article-actions d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="baiviet_editor_sua.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>"
                                        class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-pencil"></i> Chỉnh sửa
                                    </a>
                                    <form
                                        action="baiviet_editor_chitiet.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>"
                                        method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="makienthuc"
                                            value="<?php echo $article['makienthuc']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                            onclick="return confirm('Bạn có chắc chắn muốn xóa bài viết này?');">
                                            <i class="bi bi-trash"></i> Xóa
                                        </button>
                                    </form>
                                </div>
                                <a href="baiviet_editor.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-arrow-left"></i> Quay lại
                                </a>
                            </div>
                        </div>
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
                                    <a
                                        href="baiviet_editor_chitiet.php?makienthuc=<?php echo htmlspecialchars($article_item['makienthuc']); ?>">
                                        <?php echo htmlspecialchars($article_item['tieude']); ?>
                                    </a>
                                </div>
                                <div class="article-item-date">
                                    <i class="bi bi-bookmark-fill"></i>
                                    <?php echo htmlspecialchars($article_item['tenchude'] ?? 'Không rõ'); ?>
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