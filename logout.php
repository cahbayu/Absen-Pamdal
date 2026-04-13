<?php
// logout.php
require_once 'config.php';

// Hapus semua session
session_destroy();

// Set success message untuk halaman login
session_start();
$_SESSION['success_message'] = "Anda telah berhasil logout. Silakan login kembali untuk mengakses sistem.";

// Redirect ke halaman login
header("Location: login.php");
exit();
?>