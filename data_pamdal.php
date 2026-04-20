<?php
// data_pamdal.php – Manajemen Akun Pamdal
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

global $conn;

$pesan_sukses = '';
$pesan_error  = '';

// ── HANDLE POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // BUAT AKUN BARU
    if ($aksi === 'buat_akun') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($username) || empty($password)) {
            $pesan_error = 'Nama, username, dan password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $pesan_error = 'Password minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $pesan_error = 'Konfirmasi password tidak cocok.';
        } else {
            // Cek username sudah ada
            $cek = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $cek->bind_param('s', $username);
            $cek->execute();
            $cek->store_result();
            if ($cek->num_rows > 0) {
                $pesan_error = 'Username sudah digunakan. Pilih username lain.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare(
                    "INSERT INTO users (name, username, password, role, status) VALUES (?, ?, ?, 'user', 'active')"
                );
                $stmt->bind_param('sss', $name, $username, $hash);
                if ($stmt->execute()) {
                    $pesan_sukses = "Akun pamdal <strong>" . htmlspecialchars($name) . "</strong> berhasil dibuat.";
                } else {
                    $pesan_error = 'Gagal membuat akun: ' . $stmt->error;
                }
                $stmt->close();
            }
            $cek->close();
        }
    }

    // EDIT PASSWORD
    elseif ($aksi === 'edit_password') {
        $user_id     = (int)($_POST['user_id'] ?? 0);
        $password    = $_POST['password_baru'] ?? '';
        $confirm     = $_POST['confirm_password_baru'] ?? '';

        if (!$user_id) {
            $pesan_error = 'User tidak valid.';
        } elseif (strlen($password) < 6) {
            $pesan_error = 'Password baru minimal 6 karakter.';
        } elseif ($password !== $confirm) {
            $pesan_error = 'Konfirmasi password tidak cocok.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'user'");
            $stmt->bind_param('si', $hash, $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $user_data = getUserById($user_id);
                $pesan_sukses = "Password pamdal <strong>" . htmlspecialchars($user_data['name'] ?? '') . "</strong> berhasil diperbarui.";
            } else {
                $pesan_error = 'Gagal memperbarui password.';
            }
            $stmt->close();
        }
    }

    // TOGGLE STATUS (AKTIF/NONAKTIF)
    elseif ($aksi === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id) {
            $cek = $conn->prepare("SELECT name, status FROM users WHERE id = ? AND role = 'user' LIMIT 1");
            $cek->bind_param('i', $user_id);
            $cek->execute();
            $res = $cek->get_result()->fetch_assoc();
            $cek->close();
            if ($res) {
                $status_baru = $res['status'] === 'active' ? 'inactive' : 'active';
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->bind_param('si', $status_baru, $user_id);
                $stmt->execute();
                $stmt->close();
                $label = $status_baru === 'active' ? 'diaktifkan' : 'dinonaktifkan';
                $pesan_sukses = "Akun <strong>" . htmlspecialchars($res['name']) . "</strong> berhasil $label.";
            }
        }
    }

    // HAPUS AKUN
    elseif ($aksi === 'hapus_akun') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id) {
            $cek = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'user' LIMIT 1");
            $cek->bind_param('i', $user_id);
            $cek->execute();
            $res = $cek->get_result()->fetch_assoc();
            $cek->close();
            if ($res) {
                // Cek apakah masih punya data absensi
                $cek2 = $conn->prepare("SELECT COUNT(*) AS n FROM absensi WHERE user_id = ?");
                $cek2->bind_param('i', $user_id);
                $cek2->execute();
                $n = (int)$cek2->get_result()->fetch_assoc()['n'];
                $cek2->close();
                if ($n > 0) {
                    $pesan_error = "Akun <strong>" . htmlspecialchars($res['name']) . "</strong> tidak bisa dihapus karena masih memiliki $n data absensi. Nonaktifkan akun ini saja.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $pesan_sukses = "Akun <strong>" . htmlspecialchars($res['name']) . "</strong> berhasil dihapus.";
                }
            }
        }
    }
}

// ── AMBIL DATA ───────────────────────────────────────────────
$filter_status = $_GET['status'] ?? 'semua';
$filter_cari   = trim($_GET['cari'] ?? '');

$where = "role = 'user'";
if ($filter_status === 'active')   $where .= " AND status = 'active'";
if ($filter_status === 'inactive') $where .= " AND status = 'inactive'";
if ($filter_cari) {
    $cari = $conn->real_escape_string($filter_cari);
    $where .= " AND (name LIKE '%$cari%' OR username LIKE '%$cari%')";
}

$sql_pamdal = "SELECT u.*,
    (SELECT COUNT(*) FROM absensi WHERE user_id = u.id) AS total_absensi,
    (SELECT COUNT(*) FROM absensi WHERE user_id = u.id AND keterangan_masuk = 'terlambat') AS total_terlambat,
    (SELECT COUNT(*) FROM laporan l JOIN absensi a ON l.absensi_id = a.id WHERE a.user_id = u.id AND l.status = 'acc') AS total_laporan_acc,
    (SELECT MAX(tanggal) FROM absensi WHERE user_id = u.id) AS last_absensi
FROM users u WHERE $where ORDER BY u.name ASC";

$result = $conn->query($sql_pamdal);
$pamdal_list = [];
while ($row = $result->fetch_assoc()) $pamdal_list[] = $row;

$total_aktif   = 0;
$total_nonaktif = 0;
foreach ($pamdal_list as $p) {
    if ($p['status'] === 'active') $total_aktif++;
    else $total_nonaktif++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Data Pamdal — ANDALAN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy:         #0f1b2d; --navy-mid:  #162236; --navy-card: #1a2b42;
            --navy-hover:   #1f3350; --navy-line: rgba(255,255,255,0.07);
            --accent:       #3b9eff; --accent-dim: rgba(59,158,255,0.15);
            --green:        #22c55e; --green-dim:  rgba(34,197,94,0.15);
            --red:          #f43f5e; --red-dim:    rgba(244,63,94,0.15);
            --amber:        #f59e0b; --amber-dim:  rgba(245,158,11,0.15);
            --purple:       #a78bfa; --purple-dim: rgba(167,139,250,0.15);
            --teal:         #2dd4bf; --teal-dim:   rgba(45,212,191,0.15);
            --gold:         #fbbf24; --gold-dim:   rgba(251,191,36,0.15);
            --text-primary: #e8edf4; --text-secondary: #7a90a8; --text-muted: #4d6278;
            --font-main:    'DM Sans', sans-serif; --font-mono: 'DM Mono', monospace;
            --radius-sm: 6px; --radius-lg: 16px; --radius-xl: 22px;
        }
        html { font-size: 16px; }
        body { font-family: var(--font-main); background: var(--navy); color: var(--text-primary); min-height: 100vh; -webkit-font-smoothing: antialiased; }

        .navbar { position: sticky; top: 0; z-index: 100; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); text-decoration: none; letter-spacing: 0.5px; }
        .brand-icon { width: 32px; height: 32px; background: var(--gold); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0f1b2d; }
        .navbar-right { display: flex; align-items: center; gap: 10px; }
        .btn-nav { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all 0.2s; }
        .btn-nav:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }

        .main { max-width: 1100px; margin: 0 auto; padding: 32px 20px 60px; }

        /* PAGE HEADER */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 14px; }
        .page-title { font-size: 22px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--purple); }
        .page-subtitle { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }
        .btn-primary { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; padding: 9px 20px; border-radius: var(--radius-sm); background: var(--accent); border: none; color: #fff; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-primary:hover { background: #2280d4; }

        /* ALERT */
        .alert { padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px; font-size: 13px; line-height: 1.5; }
        .alert i { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
        .alert-success { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.3); }
        .alert-error   { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.3); }

        /* STAT STRIP */
        .stat-strip { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 24px; }
        .sstat { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 14px 18px; display: flex; align-items: center; gap: 12px; }
        .sstat-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .si-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.2); }
        .si-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
        .si-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.2); }
        .si-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.2); }
        .si-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.2); }
        .sstat-num { font-family: var(--font-mono); font-size: 22px; font-weight: 500; color: var(--text-primary); line-height: 1; }
        .sstat-lbl { font-size: 11px; color: var(--text-secondary); margin-top: 3px; }

        /* FILTER */
        .filter-bar { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
        .search-input { width: 100%; background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; padding: 8px 12px 8px 34px; font-family: var(--font-main); outline: none; transition: border-color 0.2s; }
        .search-input:focus { border-color: var(--accent); }
        .filter-select { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; padding: 8px 12px; font-family: var(--font-main); outline: none; }
        .btn-filter { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 8px 16px; border-radius: var(--radius-sm); background: var(--accent); border: none; color: #fff; cursor: pointer; transition: all 0.2s; }
        .btn-filter:hover { background: #2280d4; }

        /* PANEL */
        .panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .panel-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }
        .panel-empty { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }

        /* TABLE */
        .tbl-wrap { overflow-x: auto; }
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 820px; }
        .tbl th { padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); }
        .tbl td { padding: 13px 16px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .name-cell { color: var(--text-primary) !important; font-weight: 600; }
        .mono { font-family: var(--font-mono); font-size: 12px; }
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--purple-dim); border: 1px solid rgba(167,139,250,0.3); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: var(--purple); flex-shrink: 0; }
        .user-avatar.inactive { background: rgba(77,98,120,0.2); border-color: rgba(77,98,120,0.3); color: var(--text-muted); }
        .user-name-wrap .uname { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .user-name-wrap .usub  { font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 2px; }

        /* BADGES */
        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; }
        .b-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        /* ACTION BTNS */
        .action-group { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-act { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 5px 12px; border-radius: var(--radius-sm); cursor: pointer; text-decoration: none; transition: all 0.18s; border: 1px solid transparent; }
        .btn-key   { background: var(--amber-dim); color: var(--amber); border-color: rgba(245,158,11,0.3); }
        .btn-key:hover { background: var(--amber); color: #0f1b2d; }
        .btn-tog-on  { background: var(--red-dim);   color: var(--red);   border-color: rgba(244,63,94,0.3); }
        .btn-tog-on:hover { background: var(--red); color: #fff; }
        .btn-tog-off { background: var(--green-dim); color: var(--green); border-color: rgba(34,197,94,0.3); }
        .btn-tog-off:hover { background: var(--green); color: #0f1b2d; }
        .btn-del   { background: rgba(77,98,120,0.15); color: var(--text-muted); border-color: rgba(77,98,120,0.25); }
        .btn-del:hover { background: var(--red-dim); color: var(--red); border-color: rgba(244,63,94,0.3); }
        .btn-report { background: var(--accent-dim); color: var(--accent); border-color: rgba(59,158,255,0.3); }
        .btn-report:hover { background: var(--accent); color: #fff; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 1000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
        .modal-overlay.open { display: flex; }
        .modal { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); width: 100%; max-width: 460px; overflow: hidden; animation: slideUp 0.25s ease; }
        @keyframes slideUp { from { opacity:0; transform: translateY(20px); } to { opacity:1; transform: translateY(0); } }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 15px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .modal-close { width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--navy-line); background: transparent; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.2s; }
        .modal-close:hover { background: var(--red-dim); color: var(--red); border-color: var(--red); }
        .modal-body { padding: 20px 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--navy-line); display: flex; gap: 10px; justify-content: flex-end; }

        /* FORM */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
        .form-input { width: 100%; background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; padding: 9px 13px; font-family: var(--font-main); outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: var(--accent); }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
        .pw-wrap { position: relative; }
        .pw-wrap .form-input { padding-right: 40px; }
        .pw-eye { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); cursor: pointer; font-size: 13px; background: none; border: none; }
        .pw-eye:hover { color: var(--text-primary); }

        .btn-submit { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; padding: 9px 22px; border-radius: var(--radius-sm); background: var(--accent); border: none; color: #fff; cursor: pointer; transition: all 0.2s; }
        .btn-submit:hover { background: #2280d4; }
        .btn-submit.danger { background: var(--red); }
        .btn-submit.danger:hover { background: #cc1a35; }
        .btn-cancel { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 9px 18px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; transition: all 0.2s; }
        .btn-cancel:hover { border-color: rgba(255,255,255,0.2); color: var(--text-primary); }

        /* DELETE CONFIRM */
        .del-info { background: var(--red-dim); border: 1px solid rgba(244,63,94,0.25); border-radius: var(--radius-lg); padding: 14px 16px; font-size: 13px; color: var(--red); line-height: 1.6; }
        .del-info strong { color: var(--text-primary); }

        /* LAST SEEN */
        .last-seen { font-size: 11px; color: var(--text-muted); }

        @media (max-width: 768px) {
            .stat-strip { grid-template-columns: repeat(2, 1fr); }
            .navbar { padding: 0 16px; }
            .main { padding: 20px 14px 50px; }
        }
        @media (max-width: 480px) {
            .stat-strip { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </a>
    <div class="navbar-right">
        <a href="index.php" class="btn-nav"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-user-shield"></i> Data Pamdal</div>
            <div class="page-subtitle">Kelola akun seluruh personil keamanan</div>
        </div>
        <button class="btn-primary" onclick="openModal('modal-buat')">
            <i class="fas fa-user-plus"></i> Buat Akun Baru
        </button>
    </div>

    <!-- ALERT -->
    <?php if ($pesan_sukses): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><div><?= $pesan_sukses ?></div></div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><div><?= $pesan_error ?></div></div>
    <?php endif; ?>

    <!-- STAT STRIP -->
    <div class="stat-strip">
        <div class="sstat">
            <div class="sstat-icon si-purple"><i class="fas fa-user-shield"></i></div>
            <div><div class="sstat-num"><?= count($pamdal_list) ?></div><div class="sstat-lbl">Total Pamdal</div></div>
        </div>
        <div class="sstat">
            <div class="sstat-icon si-green"><i class="fas fa-circle-check"></i></div>
            <div><div class="sstat-num"><?= $total_aktif ?></div><div class="sstat-lbl">Aktif</div></div>
        </div>
        <div class="sstat">
            <div class="sstat-icon si-red"><i class="fas fa-circle-xmark"></i></div>
            <div><div class="sstat-num"><?= $total_nonaktif ?></div><div class="sstat-lbl">Nonaktif</div></div>
        </div>
        <div class="sstat">
            <div class="sstat-icon si-blue"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="sstat-num"><?= array_sum(array_column($pamdal_list, 'total_absensi')) ?></div>
                <div class="sstat-lbl">Total Absensi</div>
            </div>
        </div>
    </div>

    <!-- FILTER -->
    <form method="GET" action="">
        <div class="filter-bar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" name="cari" placeholder="Cari nama atau username…" value="<?= htmlspecialchars($filter_cari) ?>">
            </div>
            <select name="status" class="filter-select">
                <option value="semua"    <?= $filter_status === 'semua'    ? 'selected':'' ?>>Semua Status</option>
                <option value="active"   <?= $filter_status === 'active'   ? 'selected':'' ?>>Aktif</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected':'' ?>>Nonaktif</option>
            </select>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Cari</button>
        </div>
    </form>

    <!-- TABEL PAMDAL -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><i class="fas fa-users" style="color:var(--purple);"></i> Daftar Personil</span>
            <span class="panel-badge"><?= count($pamdal_list) ?> pamdal</span>
        </div>
        <?php if (empty($pamdal_list)): ?>
            <div class="panel-empty">Tidak ada pamdal yang ditemukan.</div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Personil</th>
                        <th>Status</th>
                        <th>Total Absensi</th>
                        <th>Terlambat</th>
                        <th>Lap. ACC</th>
                        <th>Absensi Terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pamdal_list as $p): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar <?= $p['status'] !== 'active' ? 'inactive' : '' ?>">
                                <?= strtoupper(substr($p['name'],0,2)) ?>
                            </div>
                            <div class="user-name-wrap">
                                <div class="uname"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="usub">@<?= htmlspecialchars($p['username']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'active'): ?>
                            <span class="badge b-green"><i class="fas fa-circle" style="font-size:7px;"></i> Aktif</span>
                        <?php else: ?>
                            <span class="badge b-red"><i class="fas fa-circle" style="font-size:7px;"></i> Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="mono" style="color:var(--text-primary);font-weight:500;"><?= $p['total_absensi'] ?></td>
                    <td>
                        <?php if ($p['total_terlambat'] > 0): ?>
                            <span style="color:var(--amber);font-family:var(--font-mono);font-weight:500;"><?= $p['total_terlambat'] ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="mono" style="color:var(--green);"><?= $p['total_laporan_acc'] ?></td>
                    <td class="last-seen">
                        <?= $p['last_absensi'] ? date('d/m/Y', strtotime($p['last_absensi'])) : '<span style="color:var(--text-muted);">—</span>' ?>
                    </td>
                    <td>
                        <div class="action-group">
                            <!-- Edit Password -->
                            <button class="btn-act btn-key"
                                onclick="openEditPassword(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                                <i class="fas fa-key"></i> Password
                            </button>
                            <!-- Toggle Status -->
                            <?php if ($p['status'] === 'active'): ?>
                            <button class="btn-act btn-tog-on"
                                onclick="openToggle(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', 'nonaktifkan')">
                                <i class="fas fa-user-slash"></i> Nonaktif
                            </button>
                            <?php else: ?>
                            <button class="btn-act btn-tog-off"
                                onclick="openToggle(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', 'aktifkan')">
                                <i class="fas fa-user-check"></i> Aktifkan
                            </button>
                            <?php endif; ?>
                            <!-- Laporan -->
                            <a href="laporan_bulanan.php?user_id=<?= $p['id'] ?>" class="btn-act btn-report">
                                <i class="fas fa-chart-bar"></i> Laporan
                            </a>
                            <!-- Hapus -->
                            <button class="btn-act btn-del"
                                onclick="openHapus(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['total_absensi'] ?>)">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ══ MODAL BUAT AKUN ══ -->
<div class="modal-overlay" id="modal-buat">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-user-plus" style="color:var(--accent);"></i> Buat Akun Pamdal</span>
            <button class="modal-close" onclick="closeModal('modal-buat')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="aksi" value="buat_akun">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap <span style="color:var(--red);">*</span></label>
                    <input type="text" name="name" class="form-input" placeholder="Contoh: Budi Santoso" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username <span style="color:var(--red);">*</span></label>
                    <input type="text" name="username" class="form-input" placeholder="Contoh: budi.santoso" required>
                    <div class="form-hint">Gunakan huruf kecil, angka, atau titik. Tanpa spasi.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span style="color:var(--red);">*</span></label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="pw-baru" class="form-input" placeholder="Minimal 6 karakter" required>
                        <button type="button" class="pw-eye" onclick="togglePw('pw-baru', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Konfirmasi Password <span style="color:var(--red);">*</span></label>
                    <div class="pw-wrap">
                        <input type="password" name="confirm_password" id="pw-baru-c" class="form-input" placeholder="Ulangi password" required>
                        <button type="button" class="pw-eye" onclick="togglePw('pw-baru-c', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-buat')">Batal</button>
                <button type="submit" class="btn-submit"><i class="fas fa-plus"></i> Buat Akun</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL EDIT PASSWORD ══ -->
<div class="modal-overlay" id="modal-editpw">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-key" style="color:var(--amber);"></i> Reset Password</span>
            <button class="modal-close" onclick="closeModal('modal-editpw')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="aksi" value="edit_password">
            <input type="hidden" name="user_id" id="editpw-uid">
            <div class="modal-body">
                <div style="background:var(--amber-dim);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-lg);padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--amber);">
                    <i class="fas fa-user" style="margin-right:6px;"></i>
                    Reset password untuk: <strong id="editpw-nama" style="color:var(--text-primary);"></strong>
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru <span style="color:var(--red);">*</span></label>
                    <div class="pw-wrap">
                        <input type="password" name="password_baru" id="pw-edit" class="form-input" placeholder="Minimal 6 karakter" required>
                        <button type="button" class="pw-eye" onclick="togglePw('pw-edit', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Konfirmasi Password Baru <span style="color:var(--red);">*</span></label>
                    <div class="pw-wrap">
                        <input type="password" name="confirm_password_baru" id="pw-edit-c" class="form-input" placeholder="Ulangi password baru" required>
                        <button type="button" class="pw-eye" onclick="togglePw('pw-edit-c', this)"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-editpw')">Batal</button>
                <button type="submit" class="btn-submit"><i class="fas fa-key"></i> Simpan Password</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL TOGGLE STATUS ══ -->
<div class="modal-overlay" id="modal-toggle">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="toggle-title"><i class="fas fa-user-slash" style="color:var(--red);"></i> Ubah Status</span>
            <button class="modal-close" onclick="closeModal('modal-toggle')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="aksi" value="toggle_status">
            <input type="hidden" name="user_id" id="toggle-uid">
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;" id="toggle-desc"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-toggle')">Batal</button>
                <button type="submit" class="btn-submit" id="toggle-btn"><i class="fas fa-check"></i> Konfirmasi</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ MODAL HAPUS ══ -->
<div class="modal-overlay" id="modal-hapus">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-trash-alt" style="color:var(--red);"></i> Hapus Akun</span>
            <button class="modal-close" onclick="closeModal('modal-hapus')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="aksi" value="hapus_akun">
            <input type="hidden" name="user_id" id="hapus-uid">
            <div class="modal-body">
                <div class="del-info" id="hapus-info"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal('modal-hapus')">Batal</button>
                <button type="submit" class="btn-submit danger"><i class="fas fa-trash-alt"></i> Hapus Akun</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Tutup modal klik luar
document.querySelectorAll('.modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});

function openEditPassword(uid, nama) {
    document.getElementById('editpw-uid').value  = uid;
    document.getElementById('editpw-nama').textContent = nama;
    document.getElementById('pw-edit').value = '';
    document.getElementById('pw-edit-c').value = '';
    openModal('modal-editpw');
}

function openToggle(uid, nama, aksi) {
    document.getElementById('toggle-uid').value = uid;
    const icon  = aksi === 'nonaktifkan' ? 'fa-user-slash' : 'fa-user-check';
    const color = aksi === 'nonaktifkan' ? 'var(--red)' : 'var(--green)';
    document.getElementById('toggle-title').innerHTML = `<i class="fas ${icon}" style="color:${color};"></i> ${aksi.charAt(0).toUpperCase() + aksi.slice(1)} Akun`;
    document.getElementById('toggle-desc').innerHTML =
        aksi === 'nonaktifkan'
        ? `Anda akan <strong style="color:var(--red)">menonaktifkan</strong> akun <strong style="color:var(--text-primary)">${nama}</strong>. Pamdal ini tidak akan bisa login sampai diaktifkan kembali.`
        : `Anda akan <strong style="color:var(--green)">mengaktifkan kembali</strong> akun <strong style="color:var(--text-primary)">${nama}</strong>. Pamdal ini dapat login kembali ke sistem.`;
    const btn = document.getElementById('toggle-btn');
    btn.innerHTML = aksi === 'nonaktifkan' ? '<i class="fas fa-user-slash"></i> Nonaktifkan' : '<i class="fas fa-user-check"></i> Aktifkan';
    btn.style.background = aksi === 'nonaktifkan' ? 'var(--red)' : 'var(--green)';
    btn.style.color = aksi === 'nonaktifkan' ? '#fff' : '#0f1b2d';
    openModal('modal-toggle');
}

function openHapus(uid, nama, totalAbsensi) {
    document.getElementById('hapus-uid').value = uid;
    document.getElementById('hapus-info').innerHTML =
        totalAbsensi > 0
        ? `Akun <strong>${nama}</strong> memiliki <strong>${totalAbsensi} data absensi</strong> yang tersimpan. Penghapusan akun tidak dapat dilakukan — silakan <strong>nonaktifkan</strong> akun ini.`
        : `Anda akan menghapus akun <strong>${nama}</strong> secara permanen. Tindakan ini tidak dapat dibatalkan.`;
    openModal('modal-hapus');
}

function togglePw(id, btn) {
    const input = document.getElementById(id);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Auto dismiss alert
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    });
}, 4000);
</script>
</body>
</html>