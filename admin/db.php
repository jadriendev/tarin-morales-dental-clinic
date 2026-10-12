<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_name = 'tarin_morales_dental_clinic';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {

    error_log("Database Connection Error: " . $e->getMessage());

    die("Database connection failed. Please try again later.");
}


$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['full_name'] ?? "Admin";


if (isset($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    $sql = "
        SELECT admin_id, CONCAT(first_name, ' ', last_name) AS full_name 
        FROM tbl_admins 
        WHERE admin_id = :admin_id 
        LIMIT 1
    ";

    $stmtAdmin = $pdo->prepare($sql);
    $stmtAdmin->execute(['admin_id' => $_SESSION['admin_id']]);
    $admin_data = $stmtAdmin->fetch();

    if ($admin_data) {
        $admin_id   = $admin_data['admin_id'];
        $admin_name = $admin_data['full_name'];
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>