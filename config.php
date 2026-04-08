<?php
// config.php - Konfigurasi Database dan Security
// Sistem Absensi Pamdal

// =============================================
// KONFIGURASI DATABASE
// =============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'absen');

// =============================================
// KONFIGURASI ROLE
// =============================================
define('ROLE_KEPALA', 'kepala');
define('ROLE_PAMDAL', 'pamdal');

// =============================================
// KONFIGURASI STATUS ABSENSI
// =============================================
define('STATUS_HADIR',       'hadir');
define('STATUS_TIDAK_HADIR', 'tidak_hadir');

// =============================================
// KONFIGURASI STATUS LAPORAN
// =============================================
define('LAPORAN_DRAFT',   'draft');
define('LAPORAN_PENDING', 'pending');
define('LAPORAN_ACC',     'acc');
define('LAPORAN_REVISI',  'revisi');

// =============================================
// KONEKSI DATABASE
// =============================================
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        die("Koneksi database gagal: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

// =============================================
// FUNGSI INPUT & KEAMANAN
// =============================================
function cleanInput($data) {
    if ($data === null || $data === '') return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// =============================================
// FUNGSI SESSION & AUTH
// =============================================
function isLoggedIn() {
    return isset($_SESSION['log']) && isset($_SESSION['role']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function isKepala() {
    return hasRole(ROLE_KEPALA);
}

function isPamdal() {
    return hasRole(ROLE_PAMDAL);
}

// Fungsi untuk cek akses role (mendukung array atau string)
function hasAccess($requiredRole) {
    if (!isLoggedIn()) return false;

    $userRole = $_SESSION['role'];

    // Kepala punya akses ke semua
    if ($userRole === ROLE_KEPALA) return true;

    if (is_array($requiredRole)) {
        return in_array($userRole, $requiredRole);
    }

    return $userRole === $requiredRole;
}

// =============================================
// FUNGSI PROTEKSI HALAMAN
// =============================================
function requireLogin() {
    if (isset($_SESSION['log'])) {
        // sudah login, lanjut
    } else {
        header('location:login.php');
        exit();
    }
}

function requireKepala() {
    if (isset($_SESSION['log'])) {
        if (!isKepala()) {
            $_SESSION['error_message'] = "Anda tidak memiliki akses ke halaman ini!";
            header("Location: dashboard.php");
            exit();
        }
    } else {
        header('location:login.php');
        exit();
    }
}

function requirePamdal() {
    if (isset($_SESSION['log'])) {
        if (!isPamdal()) {
            $_SESSION['error_message'] = "Halaman ini hanya untuk pamdal.";
            header("Location: dashboard.php");
            exit();
        }
    } else {
        header('location:login.php');
        exit();
    }
}

// Fungsi generik untuk require role tertentu (seperti SELARAS)
function requireRole($role) {
    if (isset($_SESSION['log'])) {
        if (!hasAccess($role)) {
            $_SESSION['error_message'] = "Anda tidak memiliki akses ke halaman ini!";
            header("Location: dashboard.php");
            exit();
        }
    } else {
        header('location:login.php');
        exit();
    }
}

// =============================================
// FUNGSI NAMA TAMPILAN
// =============================================
function getRoleName($role) {
    switch ($role) {
        case ROLE_KEPALA: return 'Kepala Kantor';
        case ROLE_PAMDAL: return 'Pamdal';
        default:          return 'Unknown';
    }
}

function getStatusLaporanLabel($status) {
    switch ($status) {
        case LAPORAN_DRAFT:   return 'Draft';
        case LAPORAN_PENDING: return 'Menunggu Review';
        case LAPORAN_ACC:     return 'ACC';
        case LAPORAN_REVISI:  return 'Perlu Revisi';
        default:              return '-';
    }
}

// Fungsi untuk mendapatkan list role untuk dropdown (seperti SELARAS)
function getRoleOptions() {
    return [
        ROLE_KEPALA => 'Kepala Kantor',
        ROLE_PAMDAL => 'Pamdal',
    ];
}

// =============================================
// FUNGSI VALIDASI WAKTU (BACKEND)
// =============================================

/**
 * Cek apakah pamdal boleh mengisi laporan.
 * Syarat:
 * 1. Sudah absen masuk hari ini
 * 2. Belum absen keluar
 * 3. Waktu sekarang masih sebelum batas_laporan shift
 */
function bolehIsiLaporan($user_id, $conn) {
    $today    = date('Y-m-d');
    $sekarang = date('H:i:s');

    $sql = "SELECT a.id, a.waktu_keluar, s.batas_laporan, s.lintas_hari
            FROM absensi a
            JOIN shift s ON a.shift_id = s.id
            WHERE a.user_id = ? AND a.tanggal = ? AND a.waktu_masuk IS NOT NULL
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['boleh' => false, 'pesan' => 'Anda belum absen masuk hari ini.'];
    }

    $data = $result->fetch_assoc();

    if (!empty($data['waktu_keluar'])) {
        return ['boleh' => false, 'pesan' => 'Anda sudah absen keluar, laporan tidak bisa diubah.'];
    }

    if ($sekarang > $data['batas_laporan'] && $data['lintas_hari'] == 0) {
        return ['boleh' => false, 'pesan' => 'Waktu pengisian laporan sudah habis.'];
    }

    return ['boleh' => true, 'absensi_id' => $data['id'], 'pesan' => ''];
}

/**
 * Cek apakah pamdal boleh absen masuk.
 */
function bolehAbsenMasuk($user_id, $shift_id, $conn) {
    $today = date('Y-m-d');

    $sql  = "SELECT id FROM absensi 
             WHERE user_id = ? AND shift_id = ? AND tanggal = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $user_id, $shift_id, $today);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        return ['boleh' => false, 'pesan' => 'Anda sudah absen masuk untuk shift ini.'];
    }

    return ['boleh' => true, 'pesan' => ''];
}

/**
 * Cek apakah pamdal boleh absen keluar.
 */
function bolehAbsenKeluar($user_id, $conn) {
    $today = date('Y-m-d');

    $sql  = "SELECT id FROM absensi 
             WHERE user_id = ? AND tanggal = ? 
             AND waktu_masuk IS NOT NULL AND waktu_keluar IS NULL LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return ['boleh' => false, 'pesan' => 'Tidak ada absen masuk aktif hari ini.'];
    }

    $data = $result->fetch_assoc();
    return ['boleh' => true, 'absensi_id' => $data['id'], 'pesan' => ''];
}

// =============================================
// START SESSION & KONEKSI GLOBAL
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = getConnection();
?>