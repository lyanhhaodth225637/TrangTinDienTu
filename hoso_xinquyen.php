<?php
session_start();
include __DIR__ . '/config/ketnoi.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']['manguoidung'])) {
    header("Location: dangnhap.php?message=Vui%20lòng%20đăng%20nhập%20để%20xin%20quyền!&type=warning");
    exit;
}

$user_id = $_SESSION['user']['manguoidung'];
$message = '';
$message_type = '';

// Lấy thông tin người dùng
try {
    $sql_user = "SELECT manguoidung, ten, mail, anhdaidien, vaitro 
                 FROM tbl_nguoidung WHERE manguoidung = ?";
    $cmd_user = $conn->prepare($sql_user);
    $cmd_user->execute([$user_id]);
    $user = $cmd_user->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("Không tìm thấy người dùng.");
    }
} catch (PDOException $e) {
    die("Lỗi truy vấn người dùng: " . htmlspecialchars($e->getMessage()));
}

// Biến giữ lại dữ liệu sau khi submit
$ly_do = $_POST['ly_do'] ?? '';
$dongy = isset($_POST['dong_y']);

// Xử lý đăng ký Editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dangky_editor'])) {
    $ly_do = trim($_POST['ly_do'] ?? '');
    $dongy = isset($_POST['dong_y']);

    if (!$dongy) {
        $message = 'Bạn cần đồng ý với các quy định trước khi gửi yêu cầu!';
        $message_type = 'danger';
    } else {
        try {
            // Kiểm tra xem đã gửi yêu cầu chưa
            $sql_check_request = "SELECT COUNT(*) FROM tbl_editor_requests WHERE manguoidung = ? AND trang_thai = 0";
            $cmd_check_request = $conn->prepare($sql_check_request);
            $cmd_check_request->execute([$user_id]);
            if ($cmd_check_request->fetchColumn() > 0) {
                $message = 'Bạn đã gửi yêu cầu trở thành Editor. Vui lòng chờ duyệt!';
                $message_type = 'warning';
            } else {
                // Lưu yêu cầu Editor
                $sql_request = "INSERT INTO tbl_editor_requests (manguoidung, ly_do, trang_thai, ngay_tao) VALUES (?, ?, 0, NOW())";
                $cmd_request = $conn->prepare($sql_request);
                $cmd_request->execute([$user_id, $ly_do]);
                $message = 'Yêu cầu trở thành Editor đã được gửi. Chờ admin duyệt!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Lỗi khi gửi yêu cầu Editor: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            error_log("Editor Request Error: " . $e->getMessage());
        }
    }
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xin Quyền Editor - Cộng Đồng Xanh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--primary-green, #4caf50), var(--light-green, #81c784));
            padding: 2rem 0;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(60, 121, 91, 0.3);
        }

        .profile-card {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .noiquy-box {
            max-height: 250px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #dcdcdc;
            padding: 1rem;
            border-radius: 6px;
        }

        textarea {
            resize: vertical;
        }
    </style>
</head>

<body>
    <?php include_once 'navbar.php'; ?>

    <div class="page-header text-center">
        <div class="container">
            <h1><i class="bi bi-shield-lock-fill"></i> Xin Quyền Editor</h1>
            <p>Đọc kỹ nội quy và đồng ý trước khi gửi yêu cầu trở thành Editor.</p>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                        role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="profile-card">
                    <div class="d-flex align-items-center mb-4">
                        <img src="<?php echo htmlspecialchars($user['anhdaidien'] ?: 'Uploads/avatars/avt.jpg'); ?>"
                            alt="Avatar" class="rounded-circle me-3" width="80" height="80">
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($user['ten']); ?></h5>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($user['mail']); ?></p>
                            <span class="badge bg-secondary">
                                Vai trò hiện tại:
                                <?php echo $user['vaitro'] == 2 ? 'Thành viên' : ($user['vaitro'] == 1 ? 'Editor' : 'Admin'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Form đăng ký Editor (chỉ hiển thị cho Thành viên) -->
                    <?php if ($user['vaitro'] == 2): ?>
                        <hr class="my-4">
                        <form method="post">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Đăng ký trở thành Editor</h5>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-journal-check"></i> Nội Quy Editor</h5>
                                    <div class="noiquy-box">
                                        <h6>Quy định dành cho Editor</h6>
                                        <ul>
                                            <li>Tôn trọng nội dung cộng đồng, không đăng bài vi phạm pháp luật.</li>
                                            <li>Đảm bảo nội dung bài viết và hoạt động chính xác, có nguồn đáng tin cậy.
                                            </li>
                                            <li>Không sử dụng quyền Editor để quảng cáo hoặc truyền bá thông tin sai lệch.
                                            </li>
                                            <li>Tuân thủ các quy định về bản quyền và quyền riêng tư.</li>
                                            <li>Thường xuyên đóng góp bài viết và hoạt động có giá trị cho cộng đồng.</li>
                                        </ul>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="dongy" name="dong_y" required>
                                        <label class="form-check-label" for="dongy">
                                            Tôi đã đọc và đồng ý với các quy định
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="ly_do" class="form-label">Lý do xin quyền Editor (nếu có)</label>
                                <textarea class="form-control" id="ly_do" name="ly_do" rows="2"
                                    placeholder="Nhập lý do xin quyền Editor (nếu có)"><?php echo htmlspecialchars($ly_do); ?></textarea>
                            </div>

                            <button type="submit" name="dangky_editor" class="btn btn-success">
                                <i class="bi bi-pen-fill"></i> Gửi yêu cầu
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Bạn hiện tại đã là <?php echo $user['vaitro'] == 1 ? 'Editor' : 'Admin'; ?>, không cần xin quyền
                            Editor.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gắn thông báo tùy chỉnh khi người dùng chưa tick mà nhấn submit
        const checkbox = document.getElementById('dongy');
        checkbox.oninvalid = function (event) {
            event.target.setCustomValidity('Bạn cần đồng ý điều khoản trước khi gửi yêu cầu.');
        };
        checkbox.oninput = function (event) {
            event.target.setCustomValidity('');
        };
    </script>
</body>

</html>