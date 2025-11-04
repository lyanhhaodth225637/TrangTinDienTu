<?php
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->errorInfo());
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Nhận machude từ URL
$makienthuc = isset($_GET['makienthuc']) ? trim($_GET['makienthuc']) : '';

if ($makienthuc) {
    try {
        // Kiểm tra xem bài viết kiên thức có tồn tại không
        $checkSql = 'SELECT COUNT(*) as total FROM tbl_kienthuc WHERE makienthuc = ?';
        $checkCmd = $conn->prepare($checkSql);
        $checkCmd->bindValue(1, $makienthuc, PDO::PARAM_INT);
        $checkCmd->execute();
        if ($checkCmd->fetch()['total'] > 0) {
            // Xóa bài viết kiên thức
            $sql = 'DELETE FROM tbl_kienthuc WHERE makienthuc = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $makienthuc, PDO::PARAM_INT);
            $cmd->execute();
            $message = 'Xóa bài viết kiên thức thành công!';
            $message_type = 'success';
        } else {
            $message = 'Không tìm thấy bài viết kiên thức với mã: ' . htmlspecialchars($makienthuc);

            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Lỗi khi xóa bài viết kiên thức: ' . $e->getMessage();
        //làm lại nè
        $message_type = 'danger';
    }
} else {
    $message = 'Không có mã bài viết kiên thức được cung cấp.';
    $message_type = 'danger';
}

// Chuyển hướng về danh mục với thông báo
header('Location: baiviet.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
exit;
?>