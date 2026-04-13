<?php
// config.php — Konfigurasi Database & Keamanan
// Sistem Absensi Pamdal

// ─── Konfigurasi Database ─────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'absen');

// ─── Role / Hak Akses ────────────────────────────────────────────────────────
define('ROLE_KEPALA',  'kepala');
define('ROLE_PAMDAL',  'pamdal');

// ─── Koneksi Database ────────────────────────────────────────────────────────
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        // Jangan tampilkan detail error di production
        error_log("Koneksi database gagal: " . $conn->connect_error);
        die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}

// ─── Sanitasi Input ──────────────────────────────────────────────────────────
function cleanInput($data) {
    if ($data === null || $data === '') return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ─── Hash & Verifikasi Password ──────────────────────────────────────────────
/**
 * Hash password baru menggunakan password_hash (bcrypt).
 * Gunakan ini saat membuat / mengubah password user.
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifikasi password terhadap hash yang tersimpan.
 * Mendukung hash lama (MD5) maupun hash baru (bcrypt).
 *
 * Hash MD5 legacy di database: d6e709cbafc0e8844e6acbbe05aa5f20
 */
function verifyPassword(string $password, string $hash): bool {
    // Deteksi hash MD5 legacy (32 karakter hex)
    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
        return md5($password) === $hash;
    }
    // Verifikasi bcrypt / password_hash standar
    return password_verify($password, $hash);
}

// ─── Manajemen Sesi ──────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function hasRole(string $role): bool {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

function isKepala(): bool {
    return hasRole(ROLE_KEPALA);
}

function isPamdal(): bool {
    return hasRole(ROLE_PAMDAL);
}

/**
 * Periksa apakah user memiliki akses berdasarkan role yang dibutuhkan.
 * $requiredRole bisa berupa string tunggal atau array role.
 */
function hasAccess($requiredRole): bool {
    if (!isLoggedIn()) return false;
    if (is_array($requiredRole)) {
        return in_array($_SESSION['role'], $requiredRole, true);
    }
    return $_SESSION['role'] === $requiredRole;
}

// ─── Guard / Middleware ───────────────────────────────────────────────────────
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header("Location: login.php");
        exit();
    }
}

function requireRole($role): void {
    requireLogin();
    if (!hasAccess($role)) {
        $_SESSION['error_message'] = "Anda tidak memiliki akses ke halaman ini.";
        header("Location: dashboard.php");
        exit();
    }
}

function requireKepala(): void {
    requireRole(ROLE_KEPALA);
}

// ─── Hak Akses Per Fitur ─────────────────────────────────────────────────────
/**
 * Kepala: bisa review laporan, kelola shift, lihat semua absensi.
 * Pamdal: hanya bisa absen sendiri & kelola laporan miliknya.
 */
function canReviewLaporan(): bool {
    return isLoggedIn() && isKepala();
}

function canManageShift(): bool {
    return isLoggedIn() && isKepala();
}

function canManagePengaturan(): bool {
    return isLoggedIn() && isKepala();
}

function canViewAllAbsensi(): bool {
    return isLoggedIn() && isKepala();
}

function canSubmitAbsensi(): bool {
    return isLoggedIn(); // Semua yang login bisa absen
}

function canSubmitLaporan(): bool {
    return isLoggedIn(); // Semua yang login bisa submit laporan
}

// ─── Informasi Role ───────────────────────────────────────────────────────────
function getRoleName(string $role): string {
    return match($role) {
        ROLE_KEPALA => 'Kepala',
        ROLE_PAMDAL => 'Pamdal',
        default     => 'Unknown',
    };
}

function getRoleOptions(): array {
    return [
        ROLE_KEPALA => 'Kepala',
        ROLE_PAMDAL => 'Pamdal',
    ];
}

// ─── Info Shift ───────────────────────────────────────────────────────────────
/**
 * Mengembalikan nama shift berdasarkan ID.
 * Cocok dengan data seed di database.
 */
function getShiftName(int $shiftId): string {
    return match($shiftId) {
        1 => 'Pagi (07:30–16:30)',
        2 => 'Siang (11:00–20:00)',
        3 => 'Malam (20:00–07:30)',
        default => 'Shift #' . $shiftId,
    };
}

// ─── Pengaturan Sistem ────────────────────────────────────────────────────────
/**
 * Ambil nilai pengaturan dari tabel `pengaturan` berdasarkan kunci.
 * Mengembalikan $default jika kunci tidak ditemukan.
 */
function getPengaturan(mysqli $conn, string $kunci, $default = null) {
    $stmt = $conn->prepare("SELECT nilai FROM pengaturan WHERE kunci = ? LIMIT 1");
    $stmt->bind_param("s", $kunci);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($row = $result->fetch_assoc()) {
        return $row['nilai'];
    }
    return $default;
}

// Shortcut untuk pengaturan umum
function getToleransiMasuk(mysqli $conn): int {
    return (int) getPengaturan($conn, 'toleransi_masuk_menit', 15);
}

function getMaksShiftBerturut(mysqli $conn): int {
    return (int) getPengaturan($conn, 'maks_shift_berturut', 2);
}

function getBatasEditLaporan(mysqli $conn): int {
    return (int) getPengaturan($conn, 'batas_edit_laporan_menit', 60);
}

// ─── Utilitas ─────────────────────────────────────────────────────────────────
function formatTanggal(string $date): string {
    $bulan = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember',
    ];
    [$y, $m, $d] = explode('-', $date);
    return "$d {$bulan[$m]} $y";
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return $diff . ' detik lalu';
    if ($diff < 3600)   return floor($diff/60) . ' menit lalu';
    if ($diff < 86400)  return floor($diff/3600) . ' jam lalu';
    return floor($diff/86400) . ' hari lalu';
}

// ─── Inisialisasi ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$conn = getConnection();