<?php
include __DIR__ . '/../config/ketnoi.php';

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->errorInfo());
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Nhận manguoidung từ URL
$manguoidung = isset($_GET['manguoidung']) ? trim($_GET['manguoidung']) : '';

if ($manguoidung) {
    try {
        // Kiểm tra xem chủ đề có tồn tại không
        $checkSql = 'SELECT COUNT(*) as total FROM tbl_nguoidung WHERE manguoidung = ?';
        $checkCmd = $conn->prepare($checkSql);
        $checkCmd->bindValue(1, $manguoidung, PDO::PARAM_INT);
        $checkCmd->execute();
        if ($checkCmd->fetch()['total'] > 0) {
            // Xóa chủ đề
            $sql = 'DELETE FROM tbl_nguoidung WHERE manguoidung = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $manguoidung, PDO::PARAM_INT);
            $cmd->execute();
            $message = 'Xóa người dùng thành công!';
            $message_type = 'success';
        } else {
            $message = 'Không tìm thấy người với mã: ' . htmlspecialchars($manguoidung);
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Lỗi khi xóa người dùng: ' . $e->getMessage();
        $message_type = 'danger';
    }
} else {
    $message = 'Không có mã người dùng được cung cấp.';
    $message_type = 'danger';
}

// Chuyển hướng về danh mục với thông báo
header('Location: nguoidung.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
exit;
?>