<?php
// dashboard.php — Dashboard Pamdal
require_once 'config.php';
requireLogin();
requireRole(ROLE_PAMDAL);

$userId   = $_SESSION['user_id'];
$nama     = $_SESSION['nama'];
$today    = date('Y-m-d');
$dayName  = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa',
             'Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$hariIni  = $dayName[date('l')] . ', ' . formatTanggal($today);

// ── Ambil absensi hari ini ───────────────────────────────────────────────────
$absenHariIni = null;
$stmt = $conn->prepare(
    "SELECT a.*, s.nama_shift, s.jam_masuk, s.jam_keluar
     FROM absensi a
     JOIN shift s ON s.id = a.shift_id
     WHERE a.user_id = ? AND a.tanggal = ?
     ORDER BY a.id DESC LIMIT 1"
);
$stmt->bind_param("is", $userId, $today);
$stmt->execute();
$absenHariIni = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Absensi bulan ini ────────────────────────────────────────────────────────
$bulanIni = date('Y-m');
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status_masuk='hadir') AS hadir,
            SUM(status_masuk='terlambat') AS terlambat,
            SUM(status_masuk='sangat_terlambat') AS sangat_terlambat,
            SUM(status_masuk='tidak_hadir') AS tidak_hadir
     FROM absensi
     WHERE user_id = ? AND DATE_FORMAT(tanggal,'%Y-%m') = ?"
);
$stmt->bind_param("is", $userId, $bulanIni);
$stmt->execute();
$statBulan = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── 5 riwayat terakhir ───────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT a.tanggal, a.waktu_masuk, a.waktu_keluar,
            a.status_masuk, a.status_keluar, s.nama_shift
     FROM absensi a
     JOIN shift s ON s.id = a.shift_id
     WHERE a.user_id = ?
     ORDER BY a.tanggal DESC, a.id DESC LIMIT 5"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$riwayat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Laporan hari ini ─────────────────────────────────────────────────────────
$laporanHariIni = null;
if ($absenHariIni) {
    $stmt = $conn->prepare(
        "SELECT status, waktu_submit FROM laporan_harian
         WHERE absensi_id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $absenHariIni['id']);
    $stmt->execute();
    $laporanHariIni = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── Ambil shift yang tersedia hari ini ───────────────────────────────────────
$shifts = $conn->query("SELECT * FROM shift ORDER BY jam_masuk")->fetch_all(MYSQLI_ASSOC);

// ── Toleransi dari pengaturan ────────────────────────────────────────────────
$toleransi = getToleransiMasuk($conn);

// ── Helpers ──────────────────────────────────────────────────────────────────
function badgeStatus(string $status): string {
    return match($status) {
        'hadir'           => '<span class="pill pill-ok">Hadir</span>',
        'terlambat'       => '<span class="pill pill-warn">Terlambat</span>',
        'sangat_terlambat'=> '<span class="pill pill-danger">Sangat Terlambat</span>',
        'tidak_hadir'     => '<span class="pill pill-danger">Tidak Hadir</span>',
        'tepat_waktu'     => '<span class="pill pill-ok">Tepat Waktu</span>',
        'pulang_awal'     => '<span class="pill pill-warn">Pulang Awal</span>',
        'lanjut_shift'    => '<span class="pill pill-info">Lanjut Shift</span>',
        default           => '<span class="pill pill-muted">–</span>',
    };
}

function laporanBadge(?array $lap): string {
    if (!$lap) return '<span class="pill pill-muted">Belum dibuat</span>';
    return match($lap['status']) {
        'draft'   => '<span class="pill pill-muted">Draft</span>',
        'pending' => '<span class="pill pill-warn">Menunggu Review</span>',
        'acc'     => '<span class="pill pill-ok">Disetujui</span>',
        'revisi'  => '<span class="pill pill-danger">Perlu Revisi</span>',
        default   => '<span class="pill pill-muted">–</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — <?= htmlspecialchars($nama) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --br-950: #1c0f05;
            --br-900: #2d1a0a;
            --br-800: #4a2c14;
            --br-700: #6b3f1e;
            --br-600: #8b5a2b;
            --br-500: #a96f3a;
            --br-400: #c8904f;
            --br-300: #ddb07a;
            --br-200: #edd4af;
            --br-100: #f7ead8;
            --br-50:  #fdf5ec;
            --cream:  #fef9f2;
            --gold:   #c9a84c;
            --gold-l: #e8cb7e;
            --ok:     #3d6b3a;
            --ok-t:   rgba(61,107,58,0.12);
            --ok-c:   #6bcf6b;
            --warn-t: rgba(180,120,20,0.12);
            --warn-c: #e8c060;
            --danger-t: rgba(155,40,40,0.1);
            --danger-c: #e88080;
            --info-t: rgba(50,90,160,0.1);
            --info-c: #7ab4f0;
            --muted-t: rgba(200,160,80,0.08);
            --muted-c: var(--br-400);
            --sidebar-w: 240px;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--br-50);
            color: var(--br-900);
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ───────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--br-900) 0%, var(--br-950) 100%);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: transform 0.3s ease;
            border-right: 1px solid rgba(201,168,76,0.12);
        }
        .sidebar::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c9a84c' fill-opacity='0.03'%3E%3Cpath d='M20 20.5V18H0v5h5v5H0v5h5v4h5v-4h5v4h5v-4h5v4h5v-4h4v-5h-4v-5h4v-5h-4v-5H20v2.5zm-15 4v-4h5v4h-5zm0 5v-4h5v4h-5zm5-5v-4h5v4h-5zm5 5v-4h5v4h-5zm-5 0v-4h5v4h-5zm5-5v-4h5v4h-5zm5 5v-4h5v4h-5zm0-5v-4h5v4h-5z'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }

        .sb-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(201,168,76,0.1);
            position: relative; z-index: 1;
        }
        .sb-logo-row {
            display: flex; align-items: center; gap: 12px;
        }
        .sb-logo-mark {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--gold), var(--br-500));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 700;
            color: var(--br-950);
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
        }
        .sb-logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 16px; font-weight: 700;
            color: var(--cream);
            line-height: 1.2;
        }
        .sb-logo-sub {
            font-size: 10px; font-weight: 400;
            color: var(--br-400);
            letter-spacing: 0.5px;
        }

        .sb-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(201,168,76,0.08);
            position: relative; z-index: 1;
        }
        .sb-avatar {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--br-600), var(--br-800));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 18px; font-weight: 700;
            color: var(--gold-l);
            border: 2px solid rgba(201,168,76,0.25);
            margin-bottom: 10px;
        }
        .sb-user-name {
            font-size: 13.5px; font-weight: 600;
            color: var(--br-100);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sb-user-role {
            font-size: 11px; font-weight: 500;
            color: var(--gold);
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .sb-nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
            position: relative; z-index: 1;
        }
        .sb-section-label {
            font-size: 10px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1.5px;
            color: var(--br-600);
            padding: 0 8px;
            margin: 14px 0 6px;
        }
        .sb-section-label:first-child { margin-top: 0; }

        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13.5px; font-weight: 500;
            color: var(--br-300);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            margin-bottom: 2px;
            position: relative;
        }
        .nav-item svg { flex-shrink: 0; opacity: 0.7; transition: opacity 0.2s; }
        .nav-item:hover {
            background: rgba(201,168,76,0.08);
            color: var(--br-100);
        }
        .nav-item:hover svg { opacity: 1; }
        .nav-item.active {
            background: rgba(201,168,76,0.14);
            color: var(--gold-l);
        }
        .nav-item.active svg { opacity: 1; color: var(--gold); }
        .nav-item.active::before {
            content: '';
            position: absolute; left: 0; top: 8px; bottom: 8px;
            width: 3px; border-radius: 0 3px 3px 0;
            background: var(--gold);
        }
        .nav-badge {
            margin-left: auto;
            background: rgba(201,168,76,0.15);
            border: 1px solid rgba(201,168,76,0.25);
            color: var(--gold-l);
            font-size: 10px; font-weight: 700;
            padding: 2px 7px; border-radius: 99px;
        }

        .sb-footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(201,168,76,0.08);
            position: relative; z-index: 1;
        }
        .btn-logout {
            display: flex; align-items: center; gap: 10px;
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13.5px; font-weight: 500;
            color: var(--br-400);
            background: none; border: none; cursor: pointer;
            transition: all 0.2s;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
        }
        .btn-logout:hover {
            background: rgba(200,60,60,0.1);
            color: #e88;
        }

        /* ── MAIN ──────────────────────────────────────── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            background: var(--cream);
            border-bottom: 1px solid var(--br-200);
            padding: 0 32px;
            height: 64px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
            box-shadow: 0 1px 8px rgba(74,44,20,0.06);
        }
        .topbar-left {
            display: flex; align-items: center; gap: 14px;
        }
        .hamburger {
            display: none;
            background: none; border: none; cursor: pointer;
            padding: 6px; color: var(--br-600);
        }
        .topbar-date {
            font-size: 13.5px;
            color: var(--br-500);
            font-weight: 400;
        }
        .topbar-date strong {
            color: var(--br-800);
            font-weight: 600;
        }
        .topbar-right {
            display: flex; align-items: center; gap: 10px;
        }
        .topbar-time {
            font-family: 'DM Mono', monospace;
            font-size: 15px; font-weight: 500;
            color: var(--br-700);
            background: var(--br-100);
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid var(--br-200);
        }

        /* Content area */
        .content {
            padding: 28px 32px 48px;
            flex: 1;
        }

        /* Page title */
        .page-head {
            margin-bottom: 28px;
            animation: fadeUp 0.5s ease both;
        }
        .page-eyebrow {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 2px;
            color: var(--br-400);
            margin-bottom: 6px;
        }
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 26px; font-weight: 700;
            color: var(--br-900);
            line-height: 1.2;
        }
        .page-title span { color: var(--br-500); }

        @keyframes fadeUp {
            from { opacity:0; transform:translateY(14px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* ── ABSEN TODAY CARD ──────────────────────────── */
        .absen-hero {
            background: linear-gradient(135deg, var(--br-800) 0%, var(--br-900) 100%);
            border-radius: 18px;
            padding: 28px 28px 24px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s 0.05s ease both;
        }
        .absen-hero::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%);
            top: -100px; right: -80px;
            border-radius: 50%;
            pointer-events: none;
        }
        .absen-hero::after {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c9a84c' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .absen-hero-inner {
            position: relative; z-index: 1;
            display: flex; align-items: flex-start;
            justify-content: space-between; gap: 20px;
            flex-wrap: wrap;
        }
        .absen-status-col {}
        .absen-label {
            font-size: 11px; font-weight: 600; letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 8px;
        }
        .absen-shift-name {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 700;
            color: var(--cream);
            margin-bottom: 6px;
        }
        .absen-time-row {
            display: flex; align-items: center; gap: 16px;
            flex-wrap: wrap;
        }
        .absen-time-item {
            font-size: 13px;
            color: var(--br-300);
        }
        .absen-time-item strong {
            font-family: 'DM Mono', monospace;
            font-size: 15px; font-weight: 500;
            color: var(--br-100);
        }
        .absen-divider {
            color: var(--br-600);
            font-size: 18px;
        }
        .absen-badges-row {
            display: flex; gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .absen-actions {
            display: flex; flex-direction: column; gap: 10px;
            flex-shrink: 0;
        }
        .btn-absen {
            padding: 11px 22px;
            border-radius: 11px;
            font-size: 13.5px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer; border: none;
            transition: all 0.25s;
            display: flex; align-items: center; gap: 8px;
            white-space: nowrap;
            text-decoration: none;
        }
        .btn-masuk {
            background: linear-gradient(135deg, var(--gold), var(--br-400));
            color: var(--br-950);
            box-shadow: 0 4px 14px rgba(201,168,76,0.35);
        }
        .btn-masuk:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(201,168,76,0.45); }
        .btn-keluar {
            background: rgba(255,255,255,0.08);
            color: var(--br-200);
            border: 1px solid rgba(255,255,255,0.12);
        }
        .btn-keluar:hover { background: rgba(255,255,255,0.14); }
        .btn-disabled {
            background: rgba(255,255,255,0.04);
            color: var(--br-600);
            cursor: not-allowed;
            border: 1px dashed rgba(201,168,76,0.15);
        }

        /* No absen state */
        .no-absen {
            color: var(--br-300);
            font-size: 14px;
            font-style: italic;
        }

        /* ── STAT CARDS ────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--br-200);
            border-radius: 14px;
            padding: 18px 20px;
            position: relative;
            overflow: hidden;
            animation: fadeUp 0.5s ease both;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(74,44,20,0.1); }
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.15s; }
        .stat-card:nth-child(3) { animation-delay: 0.2s; }
        .stat-card:nth-child(4) { animation-delay: 0.25s; }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 0 0 14px 14px;
        }
        .stat-hadir::after     { background: linear-gradient(90deg, #4a7c47, #6bcf6b); }
        .stat-terlambat::after { background: linear-gradient(90deg, #8b6020, var(--gold)); }
        .stat-absen::after     { background: linear-gradient(90deg, #7a1f1f, #e88); }
        .stat-laporan::after   { background: linear-gradient(90deg, var(--br-600), var(--br-400)); }

        .stat-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 12px;
        }
        .stat-icon-ok      { background: var(--ok-t); color: var(--ok-c); }
        .stat-icon-warn    { background: var(--warn-t); color: var(--warn-c); }
        .stat-icon-danger  { background: var(--danger-t); color: var(--danger-c); }
        .stat-icon-brown   { background: rgba(169,111,58,0.1); color: var(--br-500); }

        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 32px; font-weight: 700;
            color: var(--br-900);
            line-height: 1;
            margin-bottom: 4px;
        }
        .stat-label {
            font-size: 12.5px; font-weight: 500;
            color: var(--br-500);
        }
        .stat-sub {
            font-size: 11.5px; color: var(--br-400);
            margin-top: 2px;
        }

        /* ── BOTTOM GRID ───────────────────────────────── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 20px;
        }

        /* Panel */
        .panel {
            background: #fff;
            border: 1px solid var(--br-200);
            border-radius: 16px;
            overflow: hidden;
            animation: fadeUp 0.5s 0.3s ease both;
        }
        .panel-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--br-100);
            display: flex; align-items: center; justify-content: space-between;
        }
        .panel-title {
            font-family: 'Playfair Display', serif;
            font-size: 16px; font-weight: 600;
            color: var(--br-900);
        }
        .panel-link {
            font-size: 12.5px; font-weight: 500;
            color: var(--br-500);
            text-decoration: none;
            transition: color 0.2s;
        }
        .panel-link:hover { color: var(--br-700); }
        .panel-body { padding: 8px 0; }

        /* Riwayat rows */
        .riwayat-row {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 22px;
            border-bottom: 1px solid var(--br-50);
            transition: background 0.15s;
        }
        .riwayat-row:last-child { border-bottom: none; }
        .riwayat-row:hover { background: var(--br-50); }
        .rw-date-col {
            flex-shrink: 0;
            text-align: center;
            width: 46px;
        }
        .rw-day {
            font-family: 'Playfair Display', serif;
            font-size: 20px; font-weight: 700;
            color: var(--br-800);
            line-height: 1;
        }
        .rw-month {
            font-size: 10px; font-weight: 600;
            color: var(--br-400);
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .rw-divider {
            width: 1px; height: 36px;
            background: var(--br-200);
            flex-shrink: 0;
        }
        .rw-info { flex: 1; }
        .rw-shift {
            font-size: 13.5px; font-weight: 600;
            color: var(--br-800);
            margin-bottom: 4px;
        }
        .rw-times {
            font-size: 12px; color: var(--br-400);
            font-family: 'DM Mono', monospace;
        }
        .rw-status { flex-shrink: 0; }

        /* Empty state */
        .empty-state {
            padding: 36px 22px;
            text-align: center;
        }
        .empty-icon {
            font-size: 40px; margin-bottom: 10px; opacity: 0.3;
        }
        .empty-text {
            font-size: 13.5px; color: var(--br-400);
        }

        /* Quick actions panel */
        .quick-panel {
            animation-delay: 0.35s;
        }
        .quick-item {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 22px;
            border-bottom: 1px solid var(--br-50);
            text-decoration: none;
            transition: background 0.15s;
            cursor: pointer;
        }
        .quick-item:last-child { border-bottom: none; }
        .quick-item:hover { background: var(--br-50); }
        .quick-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .qi-gold   { background: rgba(201,168,76,0.1); color: var(--gold); }
        .qi-ok     { background: var(--ok-t); color: var(--ok-c); }
        .qi-brown  { background: rgba(169,111,58,0.1); color: var(--br-500); }
        .qi-info   { background: var(--info-t); color: var(--info-c); }

        .quick-text h4 {
            font-size: 13.5px; font-weight: 600;
            color: var(--br-800);
            margin-bottom: 2px;
        }
        .quick-text p {
            font-size: 12px; color: var(--br-400);
        }
        .quick-arrow {
            margin-left: auto;
            color: var(--br-300);
        }

        /* Shift info card */
        .shift-info-card {
            background: linear-gradient(135deg, var(--br-100), var(--br-50));
            border: 1px solid var(--br-200);
            border-radius: 12px;
            padding: 16px 20px;
            margin: 10px 22px 14px;
        }
        .shift-info-title {
            font-size: 11px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px;
            color: var(--br-500);
            margin-bottom: 10px;
        }
        .shift-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 7px 0;
            border-bottom: 1px solid var(--br-200);
            font-size: 13px;
        }
        .shift-row:last-child { border-bottom: none; }
        .shift-name-label { font-weight: 500; color: var(--br-700); }
        .shift-time-label {
            font-family: 'DM Mono', monospace;
            font-size: 12px; color: var(--br-500);
        }

        /* Pills */
        .pill {
            display: inline-flex; align-items: center;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 11.5px; font-weight: 600;
            line-height: 1.4;
        }
        .pill-ok     { background: var(--ok-t); color: var(--ok-c); }
        .pill-warn   { background: var(--warn-t); color: var(--warn-c); }
        .pill-danger { background: var(--danger-t); color: var(--danger-c); }
        .pill-info   { background: var(--info-t); color: var(--info-c); }
        .pill-muted  { background: var(--muted-t); color: var(--muted-c); }

        /* Modal overlay */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(28,15,5,0.6);
            backdrop-filter: blur(4px);
            z-index: 200;
            align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: var(--cream);
            border-radius: 18px;
            padding: 30px;
            width: 100%; max-width: 440px;
            margin: 20px;
            animation: scaleIn 0.25s ease;
            box-shadow: 0 20px 60px rgba(28,15,5,0.4);
        }
        @keyframes scaleIn {
            from { opacity:0; transform:scale(0.94); }
            to   { opacity:1; transform:scale(1); }
        }
        .modal-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px; font-weight: 700;
            color: var(--br-900); margin-bottom: 6px;
        }
        .modal-sub { font-size: 13.5px; color: var(--br-500); margin-bottom: 22px; }
        .modal-field { margin-bottom: 18px; }
        .modal-label {
            display: block; font-size: 13px; font-weight: 600;
            color: var(--br-700); margin-bottom: 7px;
        }
        .modal-select, .modal-textarea {
            width: 100%;
            border: 1.5px solid var(--br-200);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            color: var(--br-900);
            background: #fff;
            outline: none;
            transition: border-color 0.25s, box-shadow 0.25s;
        }
        .modal-select:focus, .modal-textarea:focus {
            border-color: var(--br-500);
            box-shadow: 0 0 0 3px rgba(169,111,58,0.1);
        }
        .modal-textarea { resize: vertical; min-height: 90px; }
        .modal-actions {
            display: flex; gap: 10px; justify-content: flex-end; margin-top: 22px;
        }
        .btn-cancel {
            padding: 10px 20px; border-radius: 9px;
            font-size: 13.5px; font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            background: var(--br-100); border: 1px solid var(--br-200);
            color: var(--br-600); cursor: pointer; transition: all 0.2s;
        }
        .btn-cancel:hover { background: var(--br-200); }
        .btn-confirm {
            padding: 10px 22px; border-radius: 9px;
            font-size: 13.5px; font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            background: linear-gradient(135deg, var(--br-600), var(--br-800));
            color: var(--cream); border: none; cursor: pointer;
            box-shadow: 0 4px 14px rgba(74,44,20,0.3);
            transition: all 0.2s;
        }
        .btn-confirm:hover { transform: translateY(-1px); }

        /* Responsive */
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .bottom-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .hamburger { display: block; }
            .content { padding: 20px 16px 40px; }
            .topbar { padding: 0 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .absen-hero-inner { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        /* Overlay for mobile sidebar */
        .sb-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
        }
        .sb-overlay.open { display: block; }
    </style>
</head>
<body>

<!-- Sidebar overlay (mobile) -->
<div class="sb-overlay" id="sbOverlay" onclick="toggleSidebar()"></div>

<!-- ── SIDEBAR ─────────────────────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo-row">
            <div class="sb-logo-mark">A</div>
            <div>
                <div class="sb-logo-text">Absensi<br>Pamdal</div>
            </div>
        </div>
    </div>

    <div class="sb-user">
        <div class="sb-avatar"><?= mb_strtoupper(mb_substr($nama, 0, 1)) ?></div>
        <div class="sb-user-name"><?= htmlspecialchars($nama) ?></div>
        <div class="sb-user-role">Pamdal</div>
    </div>

    <nav class="sb-nav">
        <div class="sb-section-label">Utama</div>
        <a href="dashboard.php" class="nav-item active">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="absensi.php" class="nav-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
            Absensi
        </a>

        <div class="sb-section-label">Laporan</div>
        <a href="laporan.php" class="nav-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
            Laporan Harian
            <?php if ($laporanHariIni && $laporanHariIni['status'] === 'revisi'): ?>
            <span class="nav-badge">!</span>
            <?php endif; ?>
        </a>
        <a href="riwayat.php" class="nav-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            Riwayat
        </a>

        <div class="sb-section-label">Lainnya</div>
        <a href="profil.php" class="nav-item">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profil Saya
        </a>
    </nav>

    <div class="sb-footer">
        <a href="logout.php" class="btn-logout">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </div>
</aside>

<!-- ── MAIN ────────────────────────────────────────────────────────────────── -->
<main class="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <span class="topbar-date"><strong><?= $hariIni ?></strong></span>
        </div>
        <div class="topbar-right">
            <span class="topbar-time" id="liveClock">--:--:--</span>
        </div>
    </div>

    <!-- Content -->
    <div class="content">

        <div class="page-head">
            <p class="page-eyebrow">Selamat datang kembali</p>
            <h1 class="page-title"><?= htmlspecialchars(explode(' ', $nama)[0]) ?>, <span>siap bertugas?</span></h1>
        </div>

        <!-- Absen Hero Card -->
        <div class="absen-hero">
            <div class="absen-hero-inner">
                <div class="absen-status-col">
                    <p class="absen-label">Status Absen Hari Ini</p>

                    <?php if ($absenHariIni): ?>
                        <div class="absen-shift-name"><?= htmlspecialchars($absenHariIni['nama_shift']) ?></div>
                        <div class="absen-time-row">
                            <span class="absen-time-item">
                                Masuk &nbsp;<strong><?= $absenHariIni['waktu_masuk'] ? date('H:i', strtotime($absenHariIni['waktu_masuk'])) : '–' ?></strong>
                            </span>
                            <span class="absen-divider">·</span>
                            <span class="absen-time-item">
                                Keluar &nbsp;<strong><?= $absenHariIni['waktu_keluar'] ? date('H:i', strtotime($absenHariIni['waktu_keluar'])) : '–' ?></strong>
                            </span>
                        </div>
                        <div class="absen-badges-row">
                            <?= badgeStatus($absenHariIni['status_masuk']) ?>
                            <?php if ($absenHariIni['status_keluar']): ?>
                                <?= badgeStatus($absenHariIni['status_keluar']) ?>
                            <?php endif; ?>
                            <?= laporanBadge($laporanHariIni) ?>
                        </div>
                    <?php else: ?>
                        <p class="no-absen">Anda belum melakukan absen hari ini.</p>
                        <div class="absen-badges-row">
                            <span class="pill pill-muted">Belum Absen</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="absen-actions">
                    <?php if (!$absenHariIni): ?>
                        <button class="btn-absen btn-masuk" onclick="openModalMasuk()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10,17 15,12 10,7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            Absen Masuk
                        </button>
                    <?php elseif (!$absenHariIni['waktu_keluar']): ?>
                        <button class="btn-absen btn-masuk" disabled style="cursor:default;opacity:0.5">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 11l3 3L22 4"/></svg>
                            Sudah Masuk
                        </button>
                        <button class="btn-absen btn-keluar" onclick="openModalKeluar()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Absen Keluar
                        </button>
                    <?php else: ?>
                        <span class="btn-absen btn-disabled">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg>
                            Absen Selesai
                        </span>
                        <?php if (!$laporanHariIni || $laporanHariIni['status'] === 'draft'): ?>
                        <a href="laporan.php?aksi=buat" class="btn-absen btn-keluar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            Buat Laporan
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-hadir">
                <div class="stat-icon stat-icon-ok">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                </div>
                <div class="stat-num"><?= $statBulan['hadir'] ?? 0 ?></div>
                <div class="stat-label">Hadir Tepat Waktu</div>
                <div class="stat-sub">Bulan <?= date('F Y') ?></div>
            </div>
            <div class="stat-card stat-terlambat">
                <div class="stat-icon stat-icon-warn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                </div>
                <div class="stat-num"><?= ($statBulan['terlambat'] ?? 0) + ($statBulan['sangat_terlambat'] ?? 0) ?></div>
                <div class="stat-label">Terlambat</div>
                <div class="stat-sub">Toleransi <?= $toleransi ?> menit</div>
            </div>
            <div class="stat-card stat-absen">
                <div class="stat-icon stat-icon-danger">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                </div>
                <div class="stat-num"><?= $statBulan['tidak_hadir'] ?? 0 ?></div>
                <div class="stat-label">Tidak Hadir</div>
                <div class="stat-sub">Total absen tidak masuk</div>
            </div>
            <div class="stat-card stat-laporan">
                <div class="stat-icon stat-icon-brown">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
                </div>
                <div class="stat-num"><?= $statBulan['total'] ?? 0 ?></div>
                <div class="stat-label">Total Shift</div>
                <div class="stat-sub">Dijalani bulan ini</div>
            </div>
        </div>

        <!-- Bottom Grid -->
        <div class="bottom-grid">

            <!-- Riwayat Absensi -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Riwayat Absensi Terakhir</h2>
                    <a href="riwayat.php" class="panel-link">Lihat semua →</a>
                </div>
                <div class="panel-body">
                    <?php if (empty($riwayat)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <p class="empty-text">Belum ada riwayat absensi.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($riwayat as $r): ?>
                    <div class="riwayat-row">
                        <div class="rw-date-col">
                            <div class="rw-day"><?= date('d', strtotime($r['tanggal'])) ?></div>
                            <div class="rw-month"><?= date('M', strtotime($r['tanggal'])) ?></div>
                        </div>
                        <div class="rw-divider"></div>
                        <div class="rw-info">
                            <div class="rw-shift"><?= htmlspecialchars($r['nama_shift']) ?></div>
                            <div class="rw-times">
                                <?= $r['waktu_masuk'] ? date('H:i', strtotime($r['waktu_masuk'])) : '--:--' ?>
                                &nbsp;→&nbsp;
                                <?= $r['waktu_keluar'] ? date('H:i', strtotime($r['waktu_keluar'])) : '--:--' ?>
                            </div>
                        </div>
                        <div class="rw-status">
                            <?= badgeStatus($r['status_masuk']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right col -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Quick Actions -->
                <div class="panel quick-panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Aksi Cepat</h2>
                    </div>
                    <div class="panel-body">
                        <a href="laporan.php?aksi=buat" class="quick-item">
                            <div class="quick-icon qi-gold">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </div>
                            <div class="quick-text">
                                <h4>Buat Laporan Harian</h4>
                                <p>Submit laporan shift hari ini</p>
                            </div>
                            <span class="quick-arrow">→</span>
                        </a>
                        <a href="absensi.php?aksi=tukar" class="quick-item">
                            <div class="quick-icon qi-ok">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17,1 21,5 17,9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7,23 3,19 7,15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg>
                            </div>
                            <div class="quick-text">
                                <h4>Tukar Shift</h4>
                                <p>Ajukan penukaran jadwal shift</p>
                            </div>
                            <span class="quick-arrow">→</span>
                        </a>
                        <a href="riwayat.php" class="quick-item">
                            <div class="quick-icon qi-brown">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="quick-text">
                                <h4>Riwayat Lengkap</h4>
                                <p>Lihat semua catatan absensi</p>
                            </div>
                            <span class="quick-arrow">→</span>
                        </a>
                        <a href="profil.php" class="quick-item">
                            <div class="quick-icon qi-info">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="quick-text">
                                <h4>Profil Saya</h4>
                                <p>Edit data & ganti password</p>
                            </div>
                            <span class="quick-arrow">→</span>
                        </a>
                    </div>
                </div>

                <!-- Info Shift -->
                <div class="panel" style="animation-delay:0.4s">
                    <div class="panel-header">
                        <h2 class="panel-title">Jadwal Shift</h2>
                    </div>
                    <div class="shift-info-card">
                        <div class="shift-info-title">Waktu operasional</div>
                        <?php foreach ($shifts as $s): ?>
                        <div class="shift-row">
                            <span class="shift-name-label"><?= htmlspecialchars($s['nama_shift']) ?></span>
                            <span class="shift-time-label">
                                <?= substr($s['jam_masuk'],0,5) ?> – <?= substr($s['jam_keluar'],0,5) ?>
                                <?= $s['lintas_hari'] ? '<span style="font-size:10px;color:var(--br-400)">&nbsp;(+1 hari)</span>' : '' ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

    </div><!-- /content -->
</main>

<!-- ── MODAL ABSEN MASUK ──────────────────────────────────────────────────── -->
<div class="modal-overlay" id="modalMasuk">
    <div class="modal-box">
        <h3 class="modal-title">Absen Masuk</h3>
        <p class="modal-sub">Pilih shift dan konfirmasi kehadiran Anda hari ini.</p>
        <form method="post" action="proses_absen.php">
            <input type="hidden" name="aksi" value="masuk">
            <div class="modal-field">
                <label class="modal-label" for="pilihShift">Shift Hari Ini</label>
                <select class="modal-select" id="pilihShift" name="shift_id" required>
                    <option value="">— Pilih shift —</option>
                    <?php foreach ($shifts as $s): ?>
                    <option value="<?= $s['id'] ?>">
                        <?= htmlspecialchars($s['nama_shift']) ?> (<?= substr($s['jam_masuk'],0,5) ?> – <?= substr($s['jam_keluar'],0,5) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-field">
                <label class="modal-label" for="ketMasuk">Keterangan (opsional)</label>
                <textarea class="modal-textarea" id="ketMasuk" name="keterangan" placeholder="Tambahkan catatan jika perlu..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalMasuk')">Batal</button>
                <button type="submit" class="btn-confirm">Konfirmasi Masuk</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL ABSEN KELUAR ─────────────────────────────────────────────────── -->
<div class="modal-overlay" id="modalKeluar">
    <div class="modal-box">
        <h3 class="modal-title">Absen Keluar</h3>
        <p class="modal-sub">Konfirmasi bahwa Anda telah selesai bertugas.</p>
        <form method="post" action="proses_absen.php">
            <input type="hidden" name="aksi" value="keluar">
            <?php if ($absenHariIni): ?>
            <input type="hidden" name="absensi_id" value="<?= $absenHariIni['id'] ?>">
            <?php endif; ?>
            <div class="modal-field">
                <label class="modal-label" for="statusKeluar">Status Keluar</label>
                <select class="modal-select" id="statusKeluar" name="status_keluar" required onchange="toggleAlasan(this)">
                    <option value="tepat_waktu">Tepat Waktu</option>
                    <option value="pulang_awal">Pulang Awal</option>
                    <option value="lanjut_shift">Lanjut ke Shift Berikutnya</option>
                </select>
            </div>
            <div class="modal-field" id="fieldAlasan" style="display:none">
                <label class="modal-label" for="alasanKeluar">Alasan Pulang Awal</label>
                <textarea class="modal-textarea" id="alasanKeluar" name="alasan_pulang_awal" placeholder="Tuliskan alasan pulang lebih awal..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('modalKeluar')">Batal</button>
                <button type="submit" class="btn-confirm">Konfirmasi Keluar</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Live clock
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        const s = String(now.getSeconds()).padStart(2,'0');
        document.getElementById('liveClock').textContent = `${h}:${m}:${s}`;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Sidebar toggle
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sbOverlay').classList.toggle('open');
    }

    // Modal helpers
    function openModalMasuk() {
        document.getElementById('modalMasuk').classList.add('open');
    }
    function openModalKeluar() {
        document.getElementById('modalKeluar').classList.add('open');
    }
    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });
    });

    // Toggle alasan field
    function toggleAlasan(sel) {
        document.getElementById('fieldAlasan').style.display =
            sel.value === 'pulang_awal' ? 'block' : 'none';
    }
</script>
</body>
</html>