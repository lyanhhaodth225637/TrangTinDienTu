<!-- Navbar - Chưa đăng nhập -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top <?php echo isset($_SESSION['user']) ? 'd-none' : ''; ?> "
    id="navbarGuest">
    <div class="container   ">
        <a class="navbar-brand" href="index.php">
            <h2 class="">iNews</h2>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent"
            aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link " href="index.php"><i class="bi bi-house-door me-1"></i>Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="baiviet.php"><i class="bi bi-book me-1"></i>Bài viết</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link" href="hoatdong.php"><i class="bi bi-calendar-event me-1"></i>Hoạt động xanh</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link" href="baiviet_diendan.php"><i class="bi bi-chat-dots me-1" hidden></i>Diễn
                        đàn</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-info-circle me-1"></i> Liên hệ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="vechungtoi.php"><i class="bi bi-people me-1"></i> Về chúng
                                tôi</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="phanhoi.php"><i class="bi bi-envelope-paper me-1"></i> Phản
                                hồi</a></li>
                    </ul>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <!-- Login Button -->
                <a href="login.php" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Navbar - Đã đăng nhập -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top <?php echo !isset($_SESSION['user']) ? 'd-none' : ''; ?>"
    id="navbarUser">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <h2 class="">iNews</h2>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContentUser"
            aria-controls="navbarContentUser" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContentUser">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php"><i class="bi bi-house-door me-1"></i>Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="baiviet.php"><i class="bi bi-book me-1"></i>Bài viết</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link" href="baiviet_diendan.php"><i class="bi bi-chat-dots me-1"></i>Diễn đàn</a>
                </li>
                <li class="nav-item" hidden>
                    <a class="nav-link" href="hoatdong.php"><i class="bi bi-calendar-event me-1"></i>Hoạt động</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-info-circle me-1"></i> Liên hệ
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="vechungtoi.php"><i class="bi bi-people me-1"></i> Về chúng
                                tôi</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="phanhoi.php"><i class="bi bi-envelope-paper me-1"></i> Phản
                                hồi</a>
                        </li>
                    </ul>
                </li>
                <?php if (isset($_SESSION['user']) && in_array($_SESSION['user']['vaitro'], [0, 1])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear-fill me-1"></i> Quản lý
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="baiviet_editor.php"><i
                                        class="bi bi-journal-plus me-1"></i> Bài viết</a></li>
                            <liư hidden><a class="dropdown-item" href="hoatdong_editor.php"><i class="bi bi-calendar-plus me-1"></i>
                                    hoạt động</a></liư>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">

                <!-- Notification Bell -->
                <button class="notification-icon" onclick="alert('Chức năng đang phát triển!'); return false;">
                    <i class="bi bi-bell-fill"></i>
                    <span class="notification-badge">3</span>
                </button>
                <!-- User Menu -->
                <div class="dropdown">
                    <button class="user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <?php
                        // Phần này giữ nguyên, chỉ thêm icon vào dropdown bên dưới
                        if (isset($_SESSION['user'])) {
                            echo htmlspecialchars($_SESSION['user']['ten']);
                        }
                        ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="hoso.php"><i class="bi bi-person-circle me-1"></i> Hồ sơ</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#"
                                onclick="alert('Chức năng đang phát triển!'); return false;">
                                <i class="bi bi-sliders me-1"></i> Cài đặt
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Đăng
                                xuất</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>


<style>
    .navbar-nav .nav-link {
        display: inline-flex;
        /* Giúp icon và chữ thẳng hàng */
        align-items: center;
        /* Căn giữa theo chiều dọc */
        gap: 0.3rem;
        /* Khoảng cách nhỏ giữa icon và chữ */
    }

    .dropdown-item {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        width: 100%;
        /* Đảm bảo dropdown item chiếm đủ rộng */
    }

    .navbar .user-menu {
        /* CSS cho nút user menu */
        background: none;
        border: none;
        color: white;
        padding: 0.3rem 0.6rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .navbar .user-menu:hover,
    .navbar .user-menu:focus {
        background-color: rgba(255, 255, 255, 0.1);
    }
</style>