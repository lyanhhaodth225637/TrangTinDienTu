<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';
// if (!isset($_SESSION['user']['vaitro']) || $_SESSION['user']['vaitro'] != 0) {
//     header("Location: dangnhap.php?message=Chỉ%20admin%20có%20thể%20truy%20cập!&type=warning");
//     exit;
// }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ma_yeu_cau = (int) ($_POST['ma_yeu_cau'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'approve') {
        $sql = "UPDATE tbl_editor_requests SET trang_thai = 1 WHERE ma_yeu_cau = ?";
        $cmd = $conn->prepare($sql);
        $cmd->execute([$ma_yeu_cau]);
        $sql_update_user = "UPDATE tbl_nguoidung SET vaitro = 1 WHERE manguoidung = (SELECT manguoidung FROM tbl_editor_requests WHERE ma_yeu_cau = ?)";
        $cmd_update_user = $conn->prepare($sql_update_user);
        $cmd_update_user->execute([$ma_yeu_cau]);
    } elseif ($action === 'reject') {
        $sql = "UPDATE tbl_editor_requests SET trang_thai = 2 WHERE ma_yeu_cau = ?";
        $cmd = $conn->prepare($sql);
        $cmd->execute([$ma_yeu_cau]);
    }
}
$sql_requests = "SELECT r.ma_yeu_cau, r.manguoidung, r.ly_do, r.trang_thai, r.ngay_tao, n.ten 
                 FROM tbl_editor_requests r 
                 JOIN tbl_nguoidung n ON r.manguoidung = n.manguoidung 
                 WHERE r.trang_thai = 0";
$cmd_requests = $conn->prepare($sql_requests);
$cmd_requests->execute();
$requests = $cmd_requests->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý yêu cầu Editor</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">

</head>

<body>
    <div class="d-flex">
        <?php include_once 'sidebar.php'; ?>

        <div class="main-content w-100">
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Chi Tiết Hoạt Động</h5>
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

            <div class="content-area" id="content-area">
                <div id="posts-section">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Chi Tiết Hoạt Động</h4>
                        <a href="hoatdong.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Quay lại danh
                            sách</a>
                    </div>

                    <div class="table-container">

                        <div class="card">
                            <div class="card-body">
                                <div class="container mt-4">
                                    <h2>Quản lý yêu cầu Editor</h2>
                                    <?php foreach ($requests as $request): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <h5><?php echo htmlspecialchars($request['ten']); ?></h5>
                                                <p><strong>Lý do:</strong>
                                                    <?php echo htmlspecialchars($request['ly_do']); ?></p>
                                                <p><strong>Ngày gửi:</strong> <?php echo $request['ngay_tao']; ?></p>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="ma_yeu_cau"
                                                        value="<?php echo $request['ma_yeu_cau']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-success btn-sm">Duyệt</button>
                                                </form>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="ma_yeu_cau"
                                                        value="<?php echo $request['ma_yeu_cau']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-danger btn-sm ms-2">Từ
                                                        chối</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>




    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>