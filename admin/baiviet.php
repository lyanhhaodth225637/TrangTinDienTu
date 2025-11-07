<?php
session_start();
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra quyền truy cập (chỉ admin role = 0)
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || !isset($_SESSION['user']['manguoidung']) || $_SESSION['user']['role'] != 0) {
    header('Location: login.php?error=please_login');
    exit;
}
// Sửa
// Kiểm tra kết nối
if (!$conn) {
    header('Location: baiviet.php?message=Kết nối cơ sở dữ liệu thất bại&type=danger');
    exit;
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Xử lý duyệt/từ chối hàng loạt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'])) {
    $makienthuc_list = isset($_POST['makienthuc']) ? $_POST['makienthuc'] : [];
    $new_trang_thai = $_POST['action'] === 'approve' ? 1 : 0;

    if (!empty($makienthuc_list)) {
        try {
            $sql = 'UPDATE tbl_kienthuc SET trangthai = ? WHERE makienthuc = ?';
            $cmd = $conn->prepare($sql);
            $updated_count = 0;

            foreach ($makienthuc_list as $id) {
                if (is_numeric($id) && $id > 0) {
                    $cmd->bindValue(1, $new_trang_thai, PDO::PARAM_INT);
                    $cmd->bindValue(2, (int) $id, PDO::PARAM_INT);
                    $cmd->execute();
                    $updated_count += $cmd->rowCount();
                }
            }

            if ($updated_count > 0) {
                $message = "Cập nhật trạng thái $updated_count bài viết thành công!";
                $message_type = 'success';
            } else {
                $message = 'Không có bài viết nào được cập nhật (có thể đã ở trạng thái này).';
                $message_type = 'info';
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi cập nhật trạng thái: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    } else {
        $message = 'Vui lòng chọn ít nhất một bài viết.';
        $message_type = 'warning';
    }
}

// Nhận số trang và từ khóa
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Chuẩn bị câu SQL
$sql_base = ' FROM tbl_kienthuc kt LEFT JOIN tbl_chude cd ON kt.machude = cd.machude LEFT JOIN tbl_nguoidung nd ON kt.manguoidung = nd.manguoidung';
$sql_where = '';
$params = [];

if ($keyword != '') {
    $sql_where = ' WHERE kt.tieude LIKE ?';

    $params[] = "%$keyword%";
}

// Đếm tổng số bản ghi
try {
    $countSql = 'SELECT COUNT(kt.makienthuc) as total' . $sql_base . $sql_where;
    $countCmd = $conn->prepare($countSql);
    if ($keyword != '') {
        $countCmd->bindValue(1, "%$keyword%", PDO::PARAM_STR);
    }
    $countCmd->execute();
    $totalRecords = $countCmd->fetch()['total'];
    $totalPages = ceil($totalRecords / $perPage);
} catch (PDOException $e) {
    $message = 'Lỗi khi đếm bài viết: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';

    $totalRecords = 0;
    $totalPages = 0;
}

// Lấy danh sách bài viết
$result = [];
if ($totalRecords > 0) {
    try {
        $sql_select = 'SELECT kt.makienthuc, kt.manguoidung, kt.machude, kt.tieude, kt.hinhanh, kt.noidung, kt.nguon, kt.duongdan, kt.luotxem, kt.luotchiase, kt.ngaytao, kt.trangthai, cd.tenchude, nd.ten';
        $sql_order = ' ORDER BY kt.ngaytao DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;

        $params[] = $offset;

        $cmd = $conn->prepare($sql_select . $sql_base . $sql_where . $sql_order);
        $param_index = 1;
        foreach ($params as $param) {
            $type = is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $cmd->bindValue($param_index++, $param, $type);
        }
        $cmd->execute();
        $result = $cmd->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $message = 'Lỗi khi lấy danh sách bài viết: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Lấy thông báo từ URL
if (empty($message) && isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $message_type = isset($_GET['type']) ? urldecode($_GET['type']) : 'success';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Bài Viết Kiến Thức</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        .table th,
        .table td {
            vertical-align: middle;
        }

        .table-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .preview-img {
            max-width: 100px;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .form-check-input {
            transform: scale(1.2);
        }

        .badge {
            font-size: 0.9em;
            padding: 0.5em 1em;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php include_once 'sidebar.php'; ?>

        <div class="main-content w-100">
            <nav class="top-navbar d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0" id="page-title">Quản Lý Bài Viết Kiến Thức</h5>
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
                        <h4>Danh Sách Bài Viết (Tổng: <?php echo $totalRecords; ?>)</h4>
                        <a href="baiviet_them.php" class="btn btn-primary"><i class="fas fa-plus"></i> Thêm bài
                            viết</a>
                    </div>

                    <div class="table-container">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                                role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post"
                            action="baiviet.php?page=<?php echo $page; ?>&keyword=<?php echo urlencode($keyword); ?>">
                            <div class="d-flex justify-content-between mb-3">
                                <div class="input-group" style="max-width: 400px;">
                                    <input type="text" name="keyword" class="form-control"
                                        placeholder="Tìm kiếm bài viết theo tiêu đề..."
                                        value="<?php echo htmlspecialchars($keyword); ?>">
                                    <button class="btn btn-outline-primary" type="submit" formmethod="get"
                                        formaction="baiviet.php">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <div>
                                    <button type="submit" name="action" value="approve" class="btn btn-success me-2"><i
                                            class="fas fa-check-double"></i> Duyệt mục đã chọn</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger"><i
                                            class="fas fa-times"></i> Từ chối mục đã chọn</button>
                                </div>
                            </div>

                            <table class="table table-hover table-striped table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">
                                            <input class="form-check-input" type="checkbox" id="checkAll">
                                        </th>
                                        <th style="width: 5%;">STT</th>
                                        <th style="width: 15%;">Người đăng</th>
                                        <th style="width: 15%;">Danh mục</th>
                                        <th style="width: 20%;">Tiêu đề</th>
                                        <th style="width: 10%;">Ngày đăng</th>
                                        <th style="width: 10%;">Hình ảnh</th>
                                        <th style="width: 15%;">Nội dung</th>
                                        <th style="width: 10%;">Nguồn</th>
                                        <th style="width: 10%; text-align: center;">Trạng thái</th>
                                        <th style="width: 15%; text-align: center;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($result)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center">Không tìm thấy bài viết nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $stt = $offset + 1; ?>
                                        <?php foreach ($result as $value): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <input class="form-check-input" type="checkbox" name="makienthuc[]"
                                                        value="<?php echo htmlspecialchars($value['makienthuc']); ?>">
                                                </td>
                                                <td><?php echo $stt++; ?></td>
                                                <td><?php echo htmlspecialchars($value['ten'] ?? 'Không xác định'); ?></td>
                                                <td><?php echo htmlspecialchars($value['tenchude'] ?? 'Không có chủ đề'); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($value['tieude']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($value['ngaytao'])); ?></td>
                                                <td>
                                                    <?php if (!empty($value['hinhanh']) && file_exists(__DIR__ . '/../' . $value['hinhanh'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($value['hinhanh']); ?>"
                                                            alt="Hình ảnh" class="preview-img">
                                                    <?php else: ?>
                                                        Không có ảnh
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a
                                                        href="baiviet_chitiet.php?makienthuc=<?php echo htmlspecialchars($value['makienthuc']); ?>">
                                                        Chi tiết...
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($value['nguon']); ?></td>
                                                <td class="text-center">
                                                    <?php
                                                    if ($value['trangthai'] == 1) {
                                                        echo '<span class="badge bg-success">Đã duyệt</span>';
                                                    } else {
                                                        echo '<span class="badge bg-warning">Chưa duyệt</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="baiviet_sua.php?makienthuc=<?php echo htmlspecialchars($value['makienthuc']); ?>"
                                                        class="btn btn-sm btn-info me-1" title="Sửa"><i
                                                            class="fas fa-edit"></i></a>
                                                    <a href="baiviet_xoa.php?makienthuc=<?php echo htmlspecialchars($value['makienthuc']); ?>"
                                                        class="btn btn-sm btn-danger me-1" title="Xóa"
                                                        onclick="return confirm('Bạn muốn xóa bài viết &quot;<?php echo htmlspecialchars(addslashes($value['tieude'])); ?>&quot; không?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>


                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </form>

                        <?php if ($totalPages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="?page=<?php echo $page - 1; ?>&keyword=<?php echo urlencode($keyword); ?>">Trước</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link"
                                                href="?page=<?php echo $i; ?>&keyword=<?php echo urlencode($keyword); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="?page=<?php echo $page + 1; ?>&keyword=<?php echo urlencode($keyword); ?>">Sau</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkAll = document.getElementById('checkAll');
            if (checkAll) {
                checkAll.addEventListener('click', function (e) {
                    const checkboxes = document.querySelectorAll('input[name="makienthuc[]"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = e.target.checked;
                    });
                });
            }
        });
    </script>
</body>

</html>