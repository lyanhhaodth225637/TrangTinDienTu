<?php
session_start();
include __DIR__ . '/config/ketnoi.php'; // Đảm bảo đường dẫn này đúngsda

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại.");
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Kiểm tra sự tồn tại của bảng tbl_nguoidung
$has_user_table = false;
try {
    $sql_check = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tbl_nguoidung'";
    $cmd_check = $conn->query($sql_check);
    if ($cmd_check) {
        $has_user_table = $cmd_check->rowCount() > 0;
    }
} catch (PDOException $e) {
    error_log("Check tbl_nguoidung Error: " . $e->getMessage());
}

// Lấy makienthuc từ URL
$makienthuc = isset($_GET['makienthuc']) && is_numeric($_GET['makienthuc']) ? (int) $_GET['makienthuc'] : 0;

// Lấy chi tiết bài viết
$article = null;
if ($makienthuc > 0) {
    try {
        // Tăng lượt xem
        $sql_update_views = 'UPDATE tbl_kienthuc SET luotxem = luotxem + 1 WHERE makienthuc = ? AND trangthai = 1';
        $cmd_update = $conn->prepare($sql_update_views);
        $cmd_update->bindValue(1, $makienthuc, PDO::PARAM_INT);
        $cmd_update->execute();

        // Lấy thông tin bài viết, tên và avatar người đăng
        $sql_select = 'SELECT kt.makienthuc, kt.tieude, kt.hinhanh, kt.noidung, kt.nguon, kt.duongdan, kt.luotxem, kt.ngaytao, cd.tenchude, nd.ten , nd.anhdaidien, nd.vaitro
                       FROM tbl_kienthuc kt 
                       LEFT JOIN tbl_chude cd ON kt.machude = cd.machude 
                       LEFT JOIN tbl_nguoidung nd ON kt.manguoidung = nd.manguoidung 
                       WHERE kt.makienthuc = ? AND kt.trangthai = 1';
        $cmd = $conn->prepare($sql_select);
        $cmd->bindValue(1, $makienthuc, PDO::PARAM_INT);
        $cmd->execute();
        $article = $cmd->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            $message = 'Bài viết không tồn tại hoặc đã bị xóa.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        if (empty($message)) {
            $message = 'Lỗi khi lấy chi tiết bài viết: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
        error_log("Article Error: " . $e->getMessage());
    }
} else {
    $message = 'Mã bài viết không hợp lệ.';
    $message_type = 'danger';
}

// Xử lý thêm bình luận
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user']['manguoidung']) && $article) {
    $noidung = isset($_POST['noidung']) ? trim($_POST['noidung']) : '';
    if ($noidung === '') {
        $message = 'Nội dung bình luận không được để trống.';
        $message_type = 'danger';
    } else {
        try {
            $sql_insert = 'INSERT INTO tbl_binhluankienthuc (makienthuc, manguoidung, noidung, ngaybinhluan) 
                           VALUES (?, ?, ?, NOW())';
            $cmd_insert = $conn->prepare($sql_insert);
            $cmd_insert->bindValue(1, $makienthuc, PDO::PARAM_INT);
            $cmd_insert->bindValue(2, $_SESSION['user']['manguoidung'], PDO::PARAM_INT);
            $cmd_insert->bindValue(3, $noidung, PDO::PARAM_STR);
            $cmd_insert->execute();

            $_SESSION['flash_message'] = 'Bình luận đã được thêm thành công!';
            $_SESSION['flash_type'] = 'success';
            header("Location: baiviet_chitiet.php?makienthuc=$makienthuc");
            exit;
        } catch (PDOException $e) {
            $message = 'Lỗi khi thêm bình luận: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Comment Error: " . $e->getMessage());
        }
    }
}

// Xử lý xóa bình luận
if (isset($_GET['delete_comment']) && isset($_SESSION['user']['manguoidung']) && $article) {
    $mabinhluan = isset($_GET['delete_comment']) && is_numeric($_GET['delete_comment']) ? (int) $_GET['delete_comment'] : 0;
    try {
        // Kiểm tra quyền xóa
        $sql_check_owner = 'SELECT manguoidung FROM tbl_binhluankienthuc WHERE mabinhluan = ?';
        $cmd_check_owner = $conn->prepare($sql_check_owner);
        $cmd_check_owner->bindValue(1, $mabinhluan, PDO::PARAM_INT);
        $cmd_check_owner->execute();
        $owner = $cmd_check_owner->fetch(PDO::FETCH_ASSOC);

        if ($owner && $owner['manguoidung'] == $_SESSION['user']['manguoidung']) {
            $sql_delete = 'DELETE FROM tbl_binhluankienthuc WHERE mabinhluan = ?';
            $cmd_delete = $conn->prepare($sql_delete);
            $cmd_delete->bindValue(1, $mabinhluan, PDO::PARAM_INT);
            $cmd_delete->execute();

            $_SESSION['flash_message'] = 'Bình luận đã được xóa thành công!';
            $_SESSION['flash_type'] = 'success';
            header("Location: baiviet_chitiet.php?makienthuc=$makienthuc");
            exit;
        } else {
            $message = 'Bạn không có quyền xóa bình luận này.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Lỗi khi xóa bình luận: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
        error_log("Delete Comment Error: " . $e->getMessage());
    }
}

// Lấy danh sách bình luận
$comments = [];
if ($article) {
    try {
        $sql_comments = $has_user_table
            ? 'SELECT bl.mabinhluan, bl.noidung, bl.ngaybinhluan, bl.manguoidung, u.ten, u.anhdaidien 
               FROM tbl_binhluankienthuc bl 
               JOIN tbl_nguoidung u ON bl.manguoidung = u.manguoidung 
               WHERE bl.makienthuc = ? 
               ORDER BY bl.ngaybinhluan DESC'
            : 'SELECT bl.mabinhluan, bl.noidung, bl.ngaybinhluan, bl.manguoidung, CONCAT("User #", bl.manguoidung) AS ten, NULL AS anhdaidien 
               FROM tbl_binhluankienthuc bl 
               WHERE bl.makienthuc = ? 
               ORDER BY bl.ngaybinhluan DESC';
        $cmd_comments = $conn->prepare($sql_comments);
        $cmd_comments->bindValue(1, $makienthuc, PDO::PARAM_INT);
        $cmd_comments->execute();
        $comments = $cmd_comments->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (empty($message)) {
            $message = 'Lỗi khi lấy danh sách bình luận: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Comments Error: " . $e->getMessage());
        }
        $comments = [];
    }
}

// Lấy thông báo flash từ session
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
} elseif (empty($message) && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = isset($_GET['type']) ? urldecode($_GET['type']) : 'success';
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $article ? htmlspecialchars($article['tieude']) : 'Chi Tiết Bài Viết'; ?> - Cộng Đồng Xanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .article-img {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .article-title {
            font-size: 2.2em;
            color: #28a745;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .article-meta {
            font-size: 0.9em;
            color: #6c757d;
            margin-bottom: 25px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }

        .article-meta span {
            margin-right: 15px;
            display: flex;
            align-items: center;
        }

        .article-meta .author-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 8px;
        }

        .article-content {
            font-size: 1.1em;
            line-height: 1.7;
            color: #212529;
        }

        .comment-section {
            margin-top: 50px;
            border-top: 1px solid #dee2e6;
            padding-top: 30px;
        }

        .comment-section h3 {
            color: #28a745;
            margin-bottom: 25px;
            font-weight: bold;
        }

        .comment-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
        }

        .comment-author img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
            float: left;
        }

        .comment-meta {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 8px;
            margin-left: 50px;
        }

        .comment-meta strong {
            color: #000;
        }

        .comment-content {
            font-size: 1em;
            color: #343a40;
            margin-left: 50px;
            clear: both;
            padding-top: 5px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h2 {
            font-size: 2.2em;
            color: #28a745;
            font-weight: bold;
        }

        .comment-actions {
            margin-left: 50px;
            margin-top: 10px;
        }

        .comment-actions a,
        .comment-actions button {
            margin-right: 10px;
            font-size: 0.9em;
        }

        .back-link {
            margin-top: 30px;
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; ?>

    <!-- Chi Tiết Bài Viết -->
    <div class="container my-5">
        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Article Content -->
        <?php if ($article): ?>
            <div class="row justify-content-center">
                <div class="col-lg-10 col-md-12">

                    <h1 class="article-title"><?php echo htmlspecialchars($article['tieude']); ?></h1>
                    <div class="article-meta">
                        <span><i class="bi bi-folder-fill text-success me-1"></i>
                            <?php echo htmlspecialchars($article['tenchude'] ?: 'Chưa phân loại'); ?></span>

                        <span><i class="bi bi-eye-fill text-info me-1"></i>
                            <?php echo number_format($article['luotxem']); ?></span>
                        <span hidden><i class="bi bi-calendar-check-fill text-secondary me-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($article['ngaytao'])); ?></span>

                        <span><i class="bi bi-link-45deg text-primary"></i> Nguồn:
                            <a
                                href="<?php echo htmlspecialchars($article['duongdan'] ?: 'Không rõ'); ?>"><?php echo htmlspecialchars($article['nguon'] ?: 'Không rõ'); ?></a></span>

                    </div>
                    <img src="<?php echo htmlspecialchars(!empty($article['hinhanh']) && file_exists($article['hinhanh']) ? $article['hinhanh'] : 'uploads/kienthuc/default.jpg'); ?>"
                        alt="<?php echo htmlspecialchars($article['tieude']); ?>" class="article-img shadow-sm">
                    <div class="post-header">
                        <img src="<?php echo !empty($article['anhdaidien']) && file_exists(__DIR__ . '/' . $article['anhdaidien'])
                            ? htmlspecialchars($article['anhdaidien'])
                            : 'uploads/avatars/avt.jpg'; ?>" class="post-avatar me-3" alt="Avatar">
                        <div class="post-user-info">
                            <div class="post-user-name">
                                <?php echo htmlspecialchars($article['ten']); ?>
                                <span
                                    class="badge bg-<?php echo $article['vaitro'] == 0 ? 'danger' : ($article['vaitro'] == 1 ? 'success' : 'primary'); ?>">
                                    <?php echo $article['vaitro'] == 0 ? 'Admin' : ($article['vaitro'] == 1 ? 'Editor' : 'Thành viên'); ?>
                                </span>
                            </div>
                        </div>

                    </div>

                    <div class="article-content">
                        <?php echo nl2br(htmlspecialchars($article['noidung'])); ?>
                    </div>
                </div>
            </div>

            <!-- Comment Section -->
            <div class="row justify-content-center">
                <div class="col-lg-10 col-md-12">
                    <div class="comment-section">
                        <!-- Comment Form -->
                        <?php if (isset($_SESSION['user']['manguoidung'])): ?>
                            <form action="baiviet_chitiet.php?makienthuc=<?php echo $makienthuc; ?>" method="post"
                                class="mt-4 p-4 border rounded bg-light">
                                <h5 class="mb-3">Thêm bình luận của bạn</h5>
                                <div class="mb-3">
                                    <textarea name="noidung" id="noidung" class="form-control" rows="4" required
                                        placeholder="Nhập nội dung bình luận..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="bi bi-send-fill"></i> Gửi bình
                                    luận</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info mt-4">
                                Vui lòng <a href="login.php" class="alert-link">đăng nhập</a> để thêm bình luận.
                            </div>
                        <?php endif; ?>
                        <h3><i class="bi bi-chat-left-text-fill"></i> Bình Luận (<?php echo count($comments); ?>)</h3>

                        <?php if (empty($comments)): ?>
                            <p class="text-muted">Chưa có bình luận nào. Hãy là người đầu tiên bình luận!</p>
                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <?php
                                $comment_avatar = ($has_user_table && !empty($comment['anhdaidien']) && file_exists($comment['anhdaidien']))
                                    ? htmlspecialchars($comment['anhdaidien'])
                                    : 'uploads/avatars/avt.jpg';
                                $is_owner = isset($_SESSION['user']['manguoidung']) && $comment['manguoidung'] == $_SESSION['user']['manguoidung'];
                                $is_editing = isset($_GET['edit_comment']) && $_GET['edit_comment'] == $comment['mabinhluan'] && $is_owner;
                                ?>
                                <div class="comment-card">
                                    <div class="comment-author">
                                        <img src="<?php echo $comment_avatar; ?>" alt="Avatar">
                                    </div>
                                    <div class="comment-meta">
                                        <strong><?php echo htmlspecialchars($comment['ten']); ?></strong> -
                                        <small>
                                            <?php
                                            // Lấy thời gian gốc
                                            $original_time = strtotime($comment['ngaybinhluan']);
                                            // Trừ đi 14 giờ (50400 giây)
                                            $adjusted_time = $original_time - (14 * 3600);
                                            // Hiển thị lại theo định dạng ngày/giờ
                                            echo date('d/m/Y H:i', $adjusted_time);
                                            ?>
                                        </small>
                                    </div>
                                    <?php if ($is_editing): ?>
                                        <form action="baiviet_chitiet.php?makienthuc=<?php echo $makienthuc; ?>" method="post"
                                            class="comment-content">
                                            <input type="hidden" name="mabinhluan" value="<?php echo $comment['mabinhluan']; ?>">
                                            <input type="hidden" name="edit_comment" value="1">
                                            <div class="mb-3">
                                                <textarea name="noidung" class="form-control" rows="3"
                                                    required><?php echo htmlspecialchars($comment['noidung']); ?></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Lưu</button>
                                            <a href="baiviet_chitiet.php?makienthuc=<?php echo $makienthuc; ?>"
                                                class="btn btn-secondary btn-sm"><i class="bi bi-x"></i> Hủy</a>
                                        </form>
                                    <?php else: ?>
                                        <div class="comment-content">
                                            <?php echo nl2br(htmlspecialchars($comment['noidung'])); ?>
                                        </div>
                                        <?php if ($is_owner): ?>
                                            <div class="comment-actions">
                                                <a href="baiviet_chitiet.php?makienthuc=<?php echo $makienthuc; ?>&delete_comment=<?php echo $comment['mabinhluan']; ?>"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Bạn có chắc muốn xóa bình luận này?');"><i
                                                        class="bi bi-trash"></i> Xóa</a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Back Link -->
            <div class="back-link">
                <a href="baiviet.php" class="btn btn-success"><i class="bi bi-arrow-left-circle-fill"></i> Quay lại
                    danh
                    sách bài viết</a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-12 text-center">
                    <div class="alert alert-warning">
                        <?php echo $message ?: 'Bài viết không tồn tại hoặc không khả dụng.'; ?>
                    </div>
                    <a href="baiviet.php" class="btn btn-success"><i class="bi bi-arrow-left-circle-fill"></i> Quay
                        lại
                        danh sách</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>