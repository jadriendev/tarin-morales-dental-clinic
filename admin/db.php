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


if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    $user_id = $_SESSION['user_id'];

    $sql = "
        SELECT admin_id, CONCAT(first_name, ' ', last_name) AS full_name 
        FROM tbl_admins 
        WHERE user_id = :user_id " . ($admin_id !== null ? "OR admin_id = :admin_id " : "") . "
        LIMIT 1
    ";

    $params = ['user_id' => $user_id];
    if ($admin_id !== null) {
        $params['admin_id'] = $admin_id;
    }

    $stmtAdmin = $pdo->prepare($sql);
    $stmtAdmin->execute($params);
    $admin_data = $stmtAdmin->fetch();

    if ($admin_data) {
        $admin_id   = $admin_data['admin_id'];
        $admin_name = $admin_data['full_name'];
        $_SESSION['admin_id'] = $admin_id;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>