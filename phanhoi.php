<?php
session_start();
// Không cần include ketnoi.php vì trang này chỉ nhúng Google Form,
// nhưng cần session_start() để kiểm tra đăng nhập.

// Lấy thông tin người dùng (nếu có) để biết đã đăng nhập hay chưa
$is_logged_in = isset($_SESSION['user']['manguoidung']) && $_SESSION['user']['manguoidung'] > 0;

?>
<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT4Earth - Phản Hồi</title>
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
            text-align: center;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 12px rgba(60, 121, 91, 0.2);
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

        .feedback-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .feedback-description {
            color: #555;
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.05rem;
        }

        .google-form-embed {
            /* Giữ nguyên style iframe */
            border: none;
            width: 100%;
            min-height: 800px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .iframe-wrapper {
            /* Giữ nguyên style wrapper */
            position: relative;
            width: 100%;
            padding-bottom: 120%;
            height: 0;
            overflow: hidden;
            margin: auto;
            max-width: 760px;
        }

        .iframe-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        /* Style cho thông báo yêu cầu đăng nhập */
        .login-prompt {
            text-align: center;
            padding: 2rem;
            border: 1px dashed #ced4da;
            border-radius: 8px;
            background-color: #f8f9fa;
        }

        .login-prompt i {
            font-size: 2.5rem;
            color: var(--primary-green);
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <?php include_once 'navbar.php'; // Đảm bảo file này tồn tại và đúng đường dẫn ?>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="bi bi-envelope-paper-heart-fill"></i> Gửi Phản Hồi</h1>
            <p class="mb-0">Chúng tôi luôn lắng nghe ý kiến đóng góp của bạn!</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-12">
                <div class="feedback-container">

                    <!-- === START: Kiểm tra đăng nhập === -->
                    <?php if ($is_logged_in): ?>

                        <p class="feedback-description">
                            Cảm ơn bạn đã dành thời gian chia sẻ ý kiến. Mọi phản hồi đều giúp IT4Earth ngày càng hoàn thiện
                            hơn.
                            Vui lòng điền vào biểu mẫu dưới đây:
                        </p>

                        <!-- Google Form Embed -->
                        <div class="iframe-wrapper">
                            <iframe
                                src="https://docs.google.com/forms/d/e/1FAIpQLScL_mPaLZGc9JDQZzI6OP9KbXDDXLTARV0kapBxwyFbIofnIQ/viewform"
                                class="google-form-embed" frameborder="0" marginheight="0" marginwidth="0">
                                Đang tải…
                            </iframe>
                        </div>
                        <p class="text-center text-muted small mt-3">
                            Nếu biểu mẫu không hiển thị, vui lòng <a
                                href="https://docs.google.com/forms/d/e/1FAIpQLScL_mPaLZGc9JDQZzI6OP9KbXDDXLTARV0kapBxwyFbIofnIQ/viewform"
                                target="_blank">nhấn
                                vào đây</a>.
                        </p>

                    <?php else: ?>

                        <div class="login-prompt">
                            <i class="bi bi-shield-lock-fill"></i>
                            <h4>Vui lòng đăng nhập</h4>
                            <p class="text-muted">Bạn cần đăng nhập vào tài khoản IT4Earth để có thể gửi phản hồi cho chúng
                                tôi.</p>
                            <a href="login.php" class="btn btn-success mt-2">
                                Đi đến trang Đăng nhập
                            </a>
                        </div>
                    <?php endif; ?>


                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include_once 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>