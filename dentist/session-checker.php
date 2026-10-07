<?php
// session-checker.php
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['usertype'])) {
    header("location: login.php");
    exit();
}
$is_view_only = strtolower($_SESSION['usertype'] ?? '') === 'user';
?>