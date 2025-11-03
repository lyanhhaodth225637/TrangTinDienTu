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
$machude = isset($_GET['machude']) ? trim($_GET['machude']) : '';

if ($machude) {
    try {
        // Kiểm tra xem chủ đề có tồn tại không
        $checkSql = 'SELECT COUNT(*) as total FROM tbl_chude WHERE machude = ?';
        $checkCmd = $conn->prepare($checkSql);
        $checkCmd->bindValue(1, $machude, PDO::PARAM_INT);
        $checkCmd->execute();
        if ($checkCmd->fetch()['total'] > 0) {
            // Xóa chủ đề
            $sql = 'DELETE FROM tbl_chude WHERE machude = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $machude, PDO::PARAM_INT);
            $cmd->execute();
            $message = 'Xóa chủ đề thành công!';
            $message_type = 'success';
        } else {
            $message = 'Không tìm thấy chủ đề với mã: ' . htmlspecialchars($machude);
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Lỗi khi xóa chủ đề: ' . $e->getMessage();
        $message_type = 'danger';
    }
} else {
    $message = 'Không có mã chủ đề được cung cấp.';
    $message_type = 'danger';
}

// Chuyển hướng về danh mục với thông báo
header('Location: danhmuc.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
exit;
?>