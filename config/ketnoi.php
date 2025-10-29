<?php
//Tạo kết nối đến CSDL
$dns = 'mysql:host=127.0.0.1;dbname=it4earth;charset=utf8mb4';
$user = 'root';
$pass = '';
$option = ['PDO::ATTR_ERRMODE' => 'PDO::ERRMODE_EXCEPTION'];
$conn = null;

try {
    $conn = new PDO($dns, $user, $pass, $option);
    //echo "Kết nối thành công";
} catch (PDOException $e) {
    echo $e->getMessage();
    // echo "Kết nối Thất bại";
}

// // Tên Máy chủ MySQL (Host Name) đã được cấp
// $host = 'sql308.infinityfree.com';

// // Tên Database hoàn chỉnh (DB Name)
// $dbname = 'if0_40238206_it4earth';

// // Tên người dùng MySQL (DB User Name)
// $user = 'if0_40238206';

// // Mật khẩu MySQL (CHÍNH LÀ MẬT KHẨU HOSTING CỦA BẠN)
// $pass = '3XRZfb7UgH'; // Thay thế bằng mật khẩu thực tế của bạn!

// // Tạo chuỗi kết nối DNS
// $dns = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
// $option = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
// $conn = null;

// try {
//     $conn = new PDO($dns, $user, $pass, $option);
//     //echo "Kết nối thành công"; 
// } catch (PDOException $e) {
//     echo "Kết nối Thất bại: " . $e->getMessage();
// }