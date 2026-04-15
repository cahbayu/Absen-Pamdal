<?php
// config.php - Konfigurasi Database dan Security
date_default_timezone_set('Asia/Jakarta');
// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'absen');

// Konfigurasi role/level akses
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_USER', 'user');

// Membuat koneksi database
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Cek koneksi
    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Fungsi untuk membersihkan input
function cleanInput($data) {
    if ($data === null || $data === '') {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk validasi username
function isValidUsername($username) {
    // Minimal 3 karakter, hanya huruf, angka, underscore, dan titik
    return preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username);
}

// Fungsi untuk hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Fungsi untuk verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Fungsi untuk cek apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Fungsi untuk cek role user
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Fungsi untuk cek apakah user punya akses ke halaman tertentu
function hasAccess($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['role'];
    
    // Super admin punya akses ke semua
    if ($userRole === ROLE_SUPER_ADMIN) {
        return true;
    }
    
    // Cek role sesuai kebutuhan
    if (is_array($requiredRole)) {
        return in_array($userRole, $requiredRole);
    }
    
    return $userRole === $requiredRole;
}

// Fungsi untuk redirect jika tidak punya akses
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Fungsi untuk require role tertentu
function requireRole($role) {
    requireLogin();
    
    if (!hasAccess($role)) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses ke halaman ini!";
        header("Location: dashboard.php");
        exit();
    }
}

// Fungsi untuk mendapatkan nama role yang user-friendly
function getRoleName($role) {
    switch($role) {
        case ROLE_SUPER_ADMIN:
            return 'Super Admin';
        case ROLE_USER:
            return 'User';
        default:
            return 'Unknown';
    }
}

// Fungsi untuk cek permission action (Create, Read, Update, Delete)
function canCreate($section = 'all') {
    if (!isLoggedIn()) return false;
    
    $role = $_SESSION['role'];
    
    // Super Admin bisa create semua
    if ($role === ROLE_SUPER_ADMIN) return true;
    
    // Admin hanya bisa create di non-ATK
    if ($role === ROLE_ADMIN && $section === 'non_atk') return true;
    
    // User tidak bisa create
    return false;
}

function canUpdate($section = 'all') {
    if (!isLoggedIn()) return false;
    
    $role = $_SESSION['role'];
    
    // Super Admin bisa update semua
    if ($role === ROLE_SUPER_ADMIN) return true;
    
    // Admin hanya bisa update di non-ATK
    if ($role === ROLE_ADMIN && $section === 'non_atk') return true;
    
    // User tidak bisa update
    return false;
}

function canDelete($section = 'all') {
    if (!isLoggedIn()) return false;
    
    $role = $_SESSION['role'];
    
    // Super Admin bisa delete semua
    if ($role === ROLE_SUPER_ADMIN) return true;
    
    // Admin hanya bisa delete di non-ATK
    if ($role === ROLE_ADMIN && $section === 'non_atk') return true;
    
    // User tidak bisa delete
    return false;
}

function canRead($section = 'all') {
    if (!isLoggedIn()) return false;
    
    // Semua role bisa read
    // Tapi admin hanya bisa akses non-ATK
    $role = $_SESSION['role'];
    
    if ($role === ROLE_SUPER_ADMIN || $role === ROLE_USER) {
        return true;
    }
    
    if ($role === ROLE_ADMIN && $section === 'non_atk') {
        return true;
    }
    
    return false;
}

// Fungsi untuk mendapatkan list role untuk dropdown
function getRoleOptions() {
    return [
        ROLE_SUPER_ADMIN => 'Super Admin',
        ROLE_USER => 'User'
    ];
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buat koneksi global
$conn = getConnection();

?>