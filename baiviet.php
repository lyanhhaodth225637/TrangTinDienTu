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
$totalRecords = 0; // Khởi tạo để tránh lỗi undefined
$totalPages = 0; // Khởi tạo để tránh lỗi undefined

// Lấy danh sách chủ đề
try {
    $sql = 'SELECT machude, tenchude FROM tbl_chude ORDER BY tenchude';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $chude_list = $cmd->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = 'Lỗi khi lấy danh sách chủ đề: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
    $chude_list = [];
    error_log("Chude Error: " . $e->getMessage());
}

// Lấy tham số từ URL
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$chude = isset($_GET['chude']) && is_numeric($_GET['chude']) ? (int)$_GET['chude'] : 0;
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'views_desc', 'views_asc']) ? $_GET['sort'] : 'newest';

// Kiểm tra chude tồn tại
if ($chude > 0) {
    try {
        $sql = 'SELECT COUNT(*) FROM tbl_chude WHERE machude = ?';
        $cmd = $conn->prepare($sql);
        $cmd->bindValue(1, $chude, PDO::PARAM_INT);
        $cmd->execute();
        if ($cmd->fetchColumn() == 0) {
            $message = 'Chủ đề không tồn tại';
            $message_type = 'danger';
            $chude = 0;
        }
    } catch (PDOException $e) {
        $message = 'Lỗi khi kiểm tra chủ đề: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
        error_log("Check Chude Error: " . $e->getMessage());
    }
}

// Chuẩn bị câu SQL
$sql_base = ' FROM tbl_kienthuc kt LEFT JOIN tbl_chude cd ON kt.machude = cd.machude WHERE kt.trangthai = 1';
$sql_where = '';
$params = [];

if ($keyword != '') {
    $sql_where .= ' AND (kt.tieude LIKE ? OR kt.noidung LIKE ?)';
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

if ($chude > 0) {
    $sql_where .= $sql_where ? ' AND kt.machude = ?' : ' AND kt.machude = ?';
    $params[] = $chude;
}

// Sắp xếp
$sql_order = ' ORDER BY kt.ngaytao DESC';
if ($sort == 'views_asc') {
    $sql_order = ' ORDER BY kt.luotxem ASC, kt.ngaytao DESC';
} elseif ($sort == 'views_desc') {
    $sql_order = ' ORDER BY kt.luotxem DESC, kt.ngaytao DESC';
}

// Đếm tổng số bài viết
try {
    $countSql = 'SELECT COUNT(kt.makienthuc) as total' . $sql_base . $sql_where;
    error_log("Count SQL: " . $countSql . ", Params: " . json_encode($params));
    $countCmd = $conn->prepare($countSql);
    $param_index = 1;
    foreach ($params as $param) {
        $countCmd->bindValue($param_index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countCmd->execute();
    $totalRecords = $countCmd->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    $message = 'Lỗi khi đếm bài viết: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
    error_log("Count Error: " . $e->getMessage());
}

// Lấy danh sách bài viết
$articles = [];
if ($totalRecords > 0) {
    try {
        $sql_select = 'SELECT kt.makienthuc, kt.tieude, kt.hinhanh, kt.noidung, kt.nguon, kt.luotxem, kt.ngaytao, cd.tenchude, 
                       (SELECT COUNT(*) FROM tbl_binhluankienthuc bk WHERE bk.makienthuc = kt.makienthuc) as comment_count' 
                       . $sql_base . $sql_where . $sql_order . ' LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;

        error_log("Select SQL: " . $sql_select . ", Params: " . json_encode($params));

        $cmd = $conn->prepare($sql_select);
        $param_index = 1;
        foreach ($params as $param) {
            $cmd->bindValue($param_index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $cmd->execute();
        $articles = $cmd->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = 'Lỗi khi lấy danh sách bài viết: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
        error_log("Articles Error: " . $e->getMessage());
    }
}

// Lấy thông báo từ URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = isset($_GET['type']) ? urldecode($_GET['type']) : 'success';
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT4Earth - Kiến Thức Xanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .content-card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
            background: #fff;
        }
        .content-card:hover {
            transform: translateY(-5px);
        }
        .content-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }
        .content-card .card-body {
            padding: 15px;
        }
        .card-category {
            font-size: 0.85em;
            color: #28a745;
            font-weight: 500;
        }
        .card-title {
            font-size: 1.25em;
            margin: 10px 0;
        }
        .card-text {
            color: #555;
            font-size: 0.9em;
        }
        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-size: 0.85em;
            color: #777;
        }
        .read-more {
            color: #28a745;
            font-weight: 500;
            text-decoration: none;
        }
        .read-more:hover {
            color: #218838;
        }
        .section-title {
            text-align: center;
            margin-bottom: 30px;
        }
        .section-title h2 {
            font-size: 2em;
            color: #28a745;
        }
        .filter-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .filter-form .input-group,
        .filter-form select {
            max-width: 300px;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; ?>

    <!-- Kiến Thức Xanh Section -->
    <div class="container my-5">
        <div class="section-title">
            <h2><i class="bi bi-book-half"></i> Kiến Thức Xanh</h2>
            <p>Khám phá những bài viết hữu ích về môi trường và bền vững</p>
        </div>

        <!-- Filter and Search Form -->
        <form action="baiviet_kienthuc.php" method="get" class="filter-form">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="keyword" class="form-control" placeholder="Tìm kiếm bài viết..."
                            value="<?php echo htmlspecialchars($keyword); ?>">
                        <button class="btn btn-outline-success" type="submit"><i class="bi bi-search"></i></button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="chude" class="form-select" onchange="this.form.submit()">
                        <option value="0">Tất cả chủ đề</option>
                        <?php foreach ($chude_list as $cd): ?>
                            <option value="<?php echo htmlspecialchars($cd['machude']); ?>" 
                                    <?php echo ($chude == $cd['machude']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cd['tenchude']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="views_desc" <?php echo ($sort == 'views_desc') ? 'selected' : ''; ?>>Lượt xem cao nhất</option>
                        <option value="views_asc" <?php echo ($sort == 'views_asc') ? 'selected' : ''; ?>>Lượt xem thấp nhất</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Article List -->
        <div class="row">
            <?php if (empty($articles)): ?>
                <div class="col-12 text-center">Không tìm thấy bài viết nào.</div>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <div class="col-md-4 mb-4">
                        <div class="content-card">
                            <img src="<?php echo htmlspecialchars($article['hinhanh'] ?: 'uploads/kienthuc/default.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($article['tieude']); ?>">
                            <div class="card-body">
                                <span class="card-category"><?php echo htmlspecialchars($article['tenchude'] ?: 'Không có chủ đề'); ?></span>
                                <h5 class="card-title"><?php echo htmlspecialchars($article['tieude']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($article['noidung'], 0, 100)) . '...'; ?></p>
                                <div class="card-meta">
                                    <span><i class="bi bi-chat-fill"></i> <?php echo $article['comment_count']; ?> bình luận</span>
                                    <span><i class="bi bi-eye"></i> <?php echo number_format($article['luotxem']); ?> lượt xem</span>
                                    <a href="baiviet_chitiet.php?makienthuc=<?php echo htmlspecialchars($article['makienthuc']); ?>" 
                                       class="read-more">Xem thêm <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="baiviet.php?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($keyword); ?>&chude=<?php echo $chude; ?>&sort=<?php echo $sort; ?>">Trước</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="baiviet.php?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>&chude=<?php echo $chude; ?>&sort=<?php echo $sort; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="baiviet.php?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($keyword); ?>&chude=<?php echo $chude; ?>&sort=<?php echo $sort; ?>">Sau</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

</body>

</html>