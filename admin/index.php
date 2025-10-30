<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !in_array($_SESSION['user']['role'], [0, 1])) {
    header('Location: login.php?error=please_login');
    exit;
}

// Lấy số liệu thống kê
try {
    $sql = 'SELECT COUNT(*) as total FROM tbl_kienthuc';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_baiviet = $cmd->fetchColumn();

    $sql = 'SELECT COUNT(*) as total FROM tbl_nguoidung';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_nguoidung = $cmd->fetchColumn();

    $sql = 'SELECT COUNT(*) as total FROM tbl_binhluankienthuc';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_binhluan = $cmd->fetchColumn();

    $sql = 'SELECT SUM(luotxem) as total FROM tbl_kienthuc';
    $cmd = $conn->prepare($sql);
    $cmd->execute();
    $total_luotxem = $cmd->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $total_baiviet = $total_nguoidung = $total_binhluan = $total_luotxem = 0;
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php 
            include_once 'sidebar.php';
        ?>
        
        <!-- Main Content -->
        <div class="main-content w-100">
            <!-- Top Navbar -->
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Dashboard</h5>
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

            <!-- Content Area -->
            <div class="content-area" id="content-area">
                <!-- Dashboard Section -->
                <div id="dashboard-section">
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Tổng bài viết</p>
                                            <h3 class="mb-0"><?php echo $total_baiviet; ?></h3>
                                        </div>
                                        <div class="fs-1 text-primary">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Người dùng</p>
                                            <h3 class="mb-0"><?php echo $total_nguoidung; ?></h3>
                                        </div>
                                        <div class="fs-1 text-success">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Bình luận</p>
                                            <h3 class="mb-0"><?php echo $total_binhluan; ?></h3>
                                        </div>
                                        <div class="fs-1 text-warning">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted mb-1">Lượt xem</p>
                                            <h3 class="mb-0"><?php echo number_format($total_luotxem); ?></h3>
                                        </div>
                                        <div class="fs-1 text-info">
                                            <i class="fas fa-eye"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

</html>