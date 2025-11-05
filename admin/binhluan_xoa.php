<?php
include __DIR__ . '/../config/ketnoi.php';


if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại: " . $conn->errorInfo());
}

// Biến để lưu thông báo
$message = '';
$message_type = '';

// Nhận mabinhluan từ URL
$mabinhluan = isset($_GET['mabinhluan']) ? trim($_GET['mabinhluan']) : '';

if ($mabinhluan) {
    try {
        // Kiểm tra xem chủ đề có tồn tại không
        $checkSql = 'SELECT COUNT(*) as total FROM tbl_binhluankienthuc WHERE mabinhluan = ?';
        $checkCmd = $conn->prepare($checkSql);
        $checkCmd->bindValue(1, $mabinhluan, PDO::PARAM_INT);
        $checkCmd->execute();
        if ($checkCmd->fetch()['total'] > 0) {
            // Xóa chủ đề
            $sql = 'DELETE FROM tbl_binhluankienthuc WHERE mabinhluan = ?';
            $cmd = $conn->prepare($sql);
            $cmd->bindValue(1, $mabinhluan, PDO::PARAM_INT);
            $cmd->execute();
            $message = 'Xóa chủ đề thành công!';
            $message_type = 'success';
        } else {
            $message = 'Không tìm thấy chủ đề với mã: ' . htmlspecialchars($mabinhluan);
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
header('Location: binhluan.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
exit;
?>