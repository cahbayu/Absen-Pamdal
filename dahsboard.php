<?php
require_once 'config.php';

if (isset($_SESSION['log'])) {
    // konten dashboard
} else {
    header('location:login.php');
    exit();
}
?>