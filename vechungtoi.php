<?php
session_start();
include __DIR__ . '/config/ketnoi.php'; // Đảm bảo đường dẫn đúng

// Kiểm tra kết nối
if (!$conn) {
    // Hiển thị lỗi thân thiện hơn
    $page_error = "Lỗi kết nối cơ sở dữ liệu.";
}

// Biến lưu trữ dữ liệu
$nhansu = []; // Mảng chứa thông tin nhân sự từ bảng tbl_thong_tin_nhom
$message = '';
$message_type = '';

// Lấy dữ liệu từ CSDL (nếu kết nối thành công)
if (isset($conn)) {
    try {
        // Lấy thông tin đội ngũ từ tbl_thong_tin_nhom
        $sql_team = "SELECT ma_thanh_vien, ho_ten, chuc_vu, sdt, email, facebook, zalo, instagram, avatar, mo_ta
                     FROM tbl_thong_tin_nhom
                     WHERE trang_thai = 1
                     ORDER BY thu_tu ASC, ma_thanh_vien ASC"; // Sắp xếp theo thứ tự, rồi đến ID
        $cmd_team = $conn->prepare($sql_team);
        $cmd_team->execute();
        $nhansu = $cmd_team->fetchAll(PDO::FETCH_ASSOC); // Gán vào biến $nhansu

    } catch (PDOException $e) {
        $message = 'Lỗi khi lấy dữ liệu thông tin nhóm: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
        error_log("Team Info Data Error: " . $e->getMessage());
    }
} else {
    // Gán thông báo lỗi nếu $conn không tồn tại
    $message = $page_error ?? 'Lỗi kết nối cơ sở dữ liệu.';
    $message_type = 'danger';
}

$default_avatar = 'uploads/avatars/avt.jpg'; // Avatar mặc định cho nhân sự
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT4Earth - Liên Hệ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="favicon_io/icons.png">
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            padding: 3rem 0;
            color: white;
            margin-bottom: 3rem;
            text-align: center;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 12px rgba(60, 121, 91, 0.2);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .team-section {
            padding-bottom: 3rem;
            /* Thêm khoảng trống dưới cùng */
        }

        .section-title {
            text-align: center;
            margin-bottom: 2.5rem;
            /* Tăng khoảng cách */
        }

        .section-title h2 {
            color: var(--primary-green);
            font-weight: bold;
            display: inline-block;
            border-bottom: 3px solid var(--primary-green);
            padding-bottom: 0.5rem;
            font-size: 2rem;
        }

        .section-title h2 i {
            margin-right: 0.75rem;
        }

        /* CSS cho card nhân sự (Tương tự vechungtoi.php) */
        .team-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            padding: 1.5rem 1rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            /* Đảm bảo card chiếm đủ chiều cao cột */
        }

        .team-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 1rem;
            border: 4px solid var(--primary-green);
        }

        .team-card h5 {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.25rem;
            font-size: 1.15rem;
        }

        .team-card .team-title {
            color: var(--primary-green);
            font-weight: 500;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .team-card .team-description {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 1rem;
            flex-grow: 1;
            /* Cho phép mô tả co giãn */
            min-height: 60px;
            /* Chiều cao tối thiểu */
        }

        .team-social {
            margin-top: auto;
        }

        /* Đẩy social xuống cuối card */
        .team-social a {
            color: #6c757d;
            margin: 0 0.5rem;
            font-size: 1.3rem;
            transition: color 0.3s ease;
            display: inline-block;
            line-height: 1;
        }

        .team-social a:hover {
            color: var(--primary-green);
        }

        .team-social a .bi-envelope-fill:hover {
            color: #dc3545;
        }

        .team-social a .bi-facebook:hover {
            color: #1877f2;
        }

        .team-social a .bi-instagram:hover {
            color: #E1306C;
        }

        .team-social a .bi-telephone-fill:hover {
            color: #0d6efd;
        }

        /* CSS cho video section */
        .video-section {
            background: #f8f9fa;
            padding: 3rem 0;
            margin-top: 4rem;
            border-radius: 20px;
        }

        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            max-width: 100%;
            background: #000;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 15px;
        }

        .video-description {
            text-align: center;
            margin-top: 1.5rem;
            color: #6c757d;
            font-size: 1rem;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="bi bi-people-fill"></i> Đội Ngũ IT4Earth</h1>
            <p class="mb-0">Những thành viên tâm huyết xây dựng cộng đồng xanh</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container team-section">
        <!-- Message -->
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show"
                role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <!-- Video Section -->
        <div class="video-section">
            <div class="container">
                <div class="section-title">
                    <h2><i class="bi bi-play-circle-fill"></i> Giới Thiệu</h2>
                </div>
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="video-container">

                            <iframe src="https://drive.google.com/file/d/1OEAsXsB5EP63cUiVxX1-ZpuTXNQDo8fM/preview"
                                allow="autoplay">
                            </iframe>
                        </div>
                        <p class="video-description">
                            <i class="bi bi-info-circle"></i> Tìm hiểu thêm về đội ngũ và hoạt động của chúng tôi
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Member Cards -->
        <?php if (!empty($nhansu)): ?>
            <div class="section-title mt-5">
                <h2><i class="bi bi-person-vcard-fill"></i> Đội Ngũ Phát Triển</h2>
            </div>
            <div class="row justify-content-center">
                <?php foreach ($nhansu as $member): ?>
                    <?php
                    // Xử lý đường dẫn avatar
                    $avatar_url = $default_avatar;
                    if (!empty($member['avatar'])) {
                        $potential_path = ltrim($member['avatar'], '/');
                        if (file_exists(__DIR__ . '/' . $potential_path)) {
                            $avatar_url = htmlspecialchars($potential_path);
                        } else {
                            error_log("Avatar file not found: " . __DIR__ . '/' . $potential_path);
                        }
                    }
                    ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4 d-flex align-items-stretch">
                        <div class="team-card w-100">
                            <img src="<?php echo $avatar_url; ?>" class="team-avatar"
                                alt="<?php echo htmlspecialchars($member['ho_ten']); ?>">
                            <h5><?php echo htmlspecialchars($member['ho_ten']); ?></h5>
                            <p class="team-title"><?php echo htmlspecialchars($member['chuc_vu'] ?? 'Thành viên'); ?></p>
                            <p class="team-description">
                                <?php echo htmlspecialchars(mb_substr($member['mo_ta'] ?? '', 0, 100, 'UTF-8')) . (mb_strlen($member['mo_ta'] ?? '', 'UTF-8') > 100 ? '...' : ''); ?>
                            </p>

                            <div class="team-social">
                                <?php if (!empty($member['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" target="_blank"
                                        title="Email"><i class="bi bi-envelope-fill"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($member['facebook'])): ?>
                                    <?php $fb_url = strpos($member['facebook'], 'http') === 0 ? $member['facebook'] : 'https://' . $member['facebook']; ?>
                                    <a href="<?php echo htmlspecialchars($fb_url); ?>" target="_blank" title="Facebook"><i
                                            class="bi bi-facebook"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($member['sdt'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($member['sdt']); ?>" title="Gọi điện">
                                        <i class="bi bi-telephone-fill"></i>
                                    </a>
                                <?php elseif (!empty($member['sdt'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($member['sdt']); ?>" title="Phone"><i
                                            class="bi bi-telephone-fill"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($member['instagram'])): ?>
                                    <?php $ig_url = strpos($member['instagram'], 'http') === 0 ? $member['instagram'] : 'https://instagram.com/' . $member['instagram']; ?>
                                    <a href="<?php echo htmlspecialchars($ig_url); ?>" target="_blank" title="Instagram"><i
                                            class="bi bi-instagram"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (isset($conn)): ?>
            <div class="alert alert-light text-center border">Thông tin đội ngũ đang được cập nhật.</div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>