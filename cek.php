<?php
session_start(); // WAJIB ada di baris pertama

if (!isset($_SESSION['log'])) {
    header('Location: login.php');
    exit(); // Penting! Hentikan eksekusi setelah redirect
}
?>