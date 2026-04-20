<?php
// monitoring_shift.php – Monitoring Shift Real-time
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

$tanggal_hari_ini = date('Y-m-d');
$jam_sekarang     = date('H:i:s');

// Data shift & occupancy
$shifts      = getAllShifts();
$shift_aktif = getActiveShift();
$occupancy   = getShiftOccupancy($tanggal_hari_ini, 0);

// Semua absensi aktif (jam_masuk ada, jam_keluar null)
global $conn;
$sql_aktif = "SELECT a.*, u.name AS nama_user, u.username,
                     s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar,
                     l.status AS status_laporan
              FROM absensi a
              JOIN users u ON a.user_id = u.id
              JOIN shift s ON a.shift_id = s.id
              LEFT JOIN laporan l ON l.absensi_id = a.id
              WHERE a.tanggal = '$tanggal_hari_ini'
                AND a.jam_masuk IS NOT NULL AND a.jam_keluar IS NULL
              ORDER BY a.jam_masuk ASC";
$r_aktif = $conn->query($sql_aktif);
$aktif_list = [];
while ($rw = $r_aktif->fetch_assoc()) $aktif_list[] = $rw;

// Semua absensi hari ini (sudah keluar juga)
$sql_semua = "SELECT a.*, u.name AS nama_user,
                     s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar,
                     l.status AS status_laporan
              FROM absensi a
              JOIN users u ON a.user_id = u.id
              JOIN shift s ON a.shift_id = s.id
              LEFT JOIN laporan l ON l.absensi_id = a.id
              WHERE a.tanggal = '$tanggal_hari_ini'
              ORDER BY a.shift_id ASC, a.jam_masuk ASC";
$r_semua = $conn->query($sql_semua);
$semua_list = [];
while ($rw = $r_semua->fetch_assoc()) $semua_list[] = $rw;

// Penukaran shift hari ini
$sql_tukar = "SELECT ps.*, a.tanggal, a.jam_masuk, u1.name AS nama_penukar, u2.name AS nama_pengganti, s.nama_shift
              FROM penukaran_shift ps
              JOIN absensi a   ON ps.absensi_id = a.id
              JOIN users u1    ON a.user_id = u1.id
              JOIN users u2    ON ps.user_pengganti_id = u2.id
              JOIN shift s     ON ps.shift_id = s.id
              WHERE a.tanggal = '$tanggal_hari_ini'
              ORDER BY ps.id DESC";
$r_tukar = $conn->query($sql_tukar);
$tukar_list = [];
while ($rw = $r_tukar->fetch_assoc()) $tukar_list[] = $rw;

// Hitung durasi berjaga (dalam menit)
function hitungDurasi($jam_masuk) {
    $masuk = strtotime($jam_masuk);
    $skrng = time();
    return (int)(($skrng - $masuk) / 60);
}

function formatDurasi($menit) {
    $jam  = (int)floor($menit / 60);
    $sisa = $menit % 60;
    if ($jam > 0) return $jam . 'j ' . $sisa . 'm';
    return $sisa . ' mnt';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Monitoring Shift — ANDALAN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy:         #0f1b2d;
            --navy-mid:     #162236;
            --navy-card:    #1a2b42;
            --navy-hover:   #1f3350;
            --navy-line:    rgba(255,255,255,0.07);
            --accent:       #3b9eff;
            --accent-dim:   rgba(59,158,255,0.15);
            --green:        #22c55e;
            --green-dim:    rgba(34,197,94,0.15);
            --red:          #f43f5e;
            --red-dim:      rgba(244,63,94,0.15);
            --amber:        #f59e0b;
            --amber-dim:    rgba(245,158,11,0.15);
            --purple:       #a78bfa;
            --purple-dim:   rgba(167,139,250,0.15);
            --teal:         #2dd4bf;
            --teal-dim:     rgba(45,212,191,0.15);
            --gold:         #fbbf24;
            --gold-dim:     rgba(251,191,36,0.15);
            --text-primary: #e8edf4;
            --text-secondary:#7a90a8;
            --text-muted:   #4d6278;
            --font-main:    'DM Sans', sans-serif;
            --font-mono:    'DM Mono', monospace;
            --radius-sm:    6px;
            --radius-lg:    16px;
            --radius-xl:    22px;
        }
        html { font-size: 16px; }
        body { font-family: var(--font-main); background: var(--navy); color: var(--text-primary); min-height: 100vh; -webkit-font-smoothing: antialiased; }

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 100; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; text-decoration: none; }
        .brand-icon { width: 32px; height: 32px; background: var(--gold); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0f1b2d; }
        .navbar-right { display: flex; align-items: center; gap: 12px; }
        .btn-back { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-back:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }
        .live-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 20px; background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        .pulse { width: 7px; height: 7px; border-radius: 50%; background: var(--green); animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.3)} }

        /* MAIN */
        .main { max-width: 1200px; margin: 0 auto; padding: 32px 20px 60px; }

        /* PAGE HEADER */
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
        .page-title { font-size: 22px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--green); }
        .page-subtitle { font-size: 13px; color: var(--text-secondary); margin-top: 5px; }
        .header-right { display: flex; align-items: center; gap: 12px; }
        .clock-box { text-align: right; }
        .clock-big { font-family: var(--font-mono); font-size: 24px; font-weight: 500; color: var(--text-primary); }
        .clock-date-sm { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .btn-refresh { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 7px 16px; border-radius: var(--radius-sm); background: var(--accent-dim); border: 1px solid rgba(59,158,255,0.3); color: var(--accent); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-refresh:hover { background: var(--accent); color: #fff; }

        /* SECTION TITLE */
        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; margin-top: 28px; }

        /* SHIFT STATUS OVERVIEW */
        .shift-overview { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; margin-bottom: 28px; }
        .shift-ov-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 20px; position: relative; overflow: hidden; }
        .shift-ov-card.is-aktif { border-color: rgba(34,197,94,0.5); box-shadow: 0 0 0 1px rgba(34,197,94,0.1), 0 4px 24px rgba(34,197,94,0.07); }
        .shift-ov-card.is-kosong { border-color: rgba(244,63,94,0.35); }
        .sov-glow { position: absolute; top: -30px; right: -30px; width: 80px; height: 80px; border-radius: 50%; opacity: 0.06; }
        .glow-green  { background: var(--green); }
        .glow-red    { background: var(--red); }
        .glow-muted  { background: var(--text-muted); }
        .sov-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
        .sov-name { font-size: 15px; font-weight: 600; color: var(--text-primary); }
        .sov-time { font-family: var(--font-mono); font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .sov-body { }
        .sov-officer { display: flex; align-items: center; gap: 12px; padding: 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); margin-bottom: 10px; }
        .sov-officer:last-child { margin-bottom: 0; }
        .officer-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-dim); border: 1px solid rgba(59,158,255,0.3); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; color: var(--accent); flex-shrink: 0; }
        .officer-info { flex: 1; min-width: 0; }
        .officer-name { font-size: 13px; font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .officer-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; font-family: var(--font-mono); }
        .officer-dur { font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 12px; background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.2); flex-shrink: 0; }
        .sov-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 20px; text-align: center; }
        .sov-empty i { font-size: 24px; color: var(--red); opacity: 0.6; }
        .sov-empty span { font-size: 12px; color: var(--text-muted); }
        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; }
        .b-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .b-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .b-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .b-teal   { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .b-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        /* SUMMARY ROW */
        .summary-row { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 28px; }
        .sum-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
        .sum-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .si-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
        .si-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.2); }
        .si-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.2); }
        .si-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.2); }
        .si-teal   { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.2); }
        .sum-num { font-family: var(--font-mono); font-size: 22px; font-weight: 500; color: var(--text-primary); line-height: 1; }
        .sum-lbl { font-size: 11px; color: var(--text-secondary); margin-top: 3px; }

        /* TWO COL */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* PANEL */
        .panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .panel-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; }
        .pb-green { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        .pb-amber { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .pb-blue  { background: var(--accent-dim);color: var(--accent);border: 1px solid rgba(59,158,255,0.3); }
        .pb-red   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(244,63,94,0.3); }
        .panel-empty { padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }
        .panel-empty i { font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.5; }

        /* TABLE */
        .tbl-wrap { overflow-x: auto; }
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tbl th { padding: 9px 14px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); white-space: nowrap; }
        .tbl td { padding: 10px 14px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .name-cell { color: var(--text-primary) !important; font-weight: 500; }
        .mono { font-family: var(--font-mono); font-size: 12px; }

        /* TIMELINE / AKTIF OFFICER LIST */
        .aktif-list { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
        .aktif-item { display: flex; align-items: center; gap: 14px; background: rgba(34,197,94,0.04); border: 1px solid rgba(34,197,94,0.15); border-radius: var(--radius-lg); padding: 12px 16px; }
        .aktif-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--green-dim); border: 1.5px solid rgba(34,197,94,0.4); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; color: var(--green); flex-shrink: 0; }
        .aktif-info { flex: 1; min-width: 0; }
        .aktif-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .aktif-meta { font-size: 11px; color: var(--text-muted); margin-top: 3px; font-family: var(--font-mono); }
        .aktif-right { text-align: right; flex-shrink: 0; }
        .dur-badge { font-family: var(--font-mono); font-size: 13px; font-weight: 500; color: var(--green); }
        .dur-lbl { font-size: 10px; color: var(--text-muted); margin-top: 2px; }
        .dot-aktif { width: 8px; height: 8px; border-radius: 50%; background: var(--green); flex-shrink: 0; animation: pulse 1.5s infinite; }

        /* TUKAR SHIFT */
        .tukar-item { padding: 12px 16px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; gap: 12px; }
        .tukar-item:last-child { border-bottom: none; }
        .tukar-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--teal-dim); border: 1px solid rgba(45,212,191,0.3); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: var(--teal); flex-shrink: 0; }
        .tukar-info { flex: 1; min-width: 0; }
        .tukar-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
        .tukar-detail { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        /* PROGRESS BAR */
        .progress-wrap { margin-top: 14px; }
        .progress-label { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); margin-bottom: 6px; }
        .progress-bar { height: 6px; border-radius: 6px; background: rgba(255,255,255,0.06); overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 6px; background: linear-gradient(90deg, var(--green), var(--teal)); transition: width 0.5s ease; }
        .progress-fill.warn { background: linear-gradient(90deg, var(--amber), var(--gold)); }
        .progress-fill.over { background: linear-gradient(90deg, var(--red), var(--amber)); }

        @media (max-width: 900px) {
            .shift-overview { grid-template-columns: 1fr; }
            .summary-row    { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .two-col        { grid-template-columns: 1fr; }
            .navbar         { padding: 0 16px; }
            .main           { padding: 20px 14px 50px; }
        }
        @media (max-width: 480px) {
            .summary-row { grid-template-columns: 1fr; }
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
        <div class="live-chip">
            <div class="pulse"></div>
            LIVE
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-eye"></i>
                Monitoring Shift
            </div>
            <div class="page-subtitle">
                Pantau kondisi shift real-time · <?= date('d F Y') ?>
            </div>
        </div>
        <div class="header-right">
            <div class="clock-box">
                <div class="clock-big" id="clock"><?= date('H:i:s') ?></div>
                <div class="clock-date-sm"><?= date('l, d F Y') ?></div>
            </div>
            <a href="" class="btn-refresh" onclick="location.reload();return false;">
                <i class="fas fa-rotate-right"></i> Refresh
            </a>
        </div>
    </div>

    <!-- SUMMARY ROW -->
    <div class="summary-row">
        <?php
        $total_berjaga = count($aktif_list);
        $total_selesai = count(array_filter($semua_list, fn($x) => !empty($x['jam_keluar'])));
        $total_hadir   = count($semua_list);
        $total_shift   = count($shifts);
        $shift_terisi  = count(array_filter($shifts, fn($s) => isset($occupancy[$s['id']])));
        ?>
        <div class="sum-card">
            <div class="sum-icon si-green"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="sum-num"><?= $total_berjaga ?></div>
                <div class="sum-lbl">Sedang Berjaga</div>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon si-blue"><i class="fas fa-users"></i></div>
            <div>
                <div class="sum-num"><?= $total_hadir ?></div>
                <div class="sum-lbl">Hadir Hari Ini</div>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon si-teal"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="sum-num"><?= $shift_terisi ?> / <?= $total_shift ?></div>
                <div class="sum-lbl">Shift Terisi</div>
            </div>
        </div>
        <div class="sum-card">
            <div class="sum-icon si-amber"><i class="fas fa-exchange-alt"></i></div>
            <div>
                <div class="sum-num"><?= count($tukar_list) ?></div>
                <div class="sum-lbl">Tukar Shift</div>
            </div>
        </div>
    </div>

    <!-- KONDISI PER SHIFT -->
    <div class="section-title" style="margin-top:0;">Status Per Shift</div>
    <div class="shift-overview">
        <?php foreach ($shifts as $shift):
            $sid     = (int)$shift['id'];
            $is_aktif_now = $shift_aktif && ((int)$shift_aktif['id'] === $sid);

            // Cari semua officer yang sedang di shift ini (aktif/belum keluar)
            $officers_aktif = [];
            foreach ($aktif_list as $a) {
                if ((int)$a['shift_id'] === $sid) $officers_aktif[] = $a;
            }
            // Cari officer yang sudah keluar dari shift ini
            $officers_selesai = [];
            foreach ($semua_list as $a) {
                if ((int)$a['shift_id'] === $sid && !empty($a['jam_keluar'])) $officers_selesai[] = $a;
            }

            $is_kosong = count($officers_aktif) === 0 && count($officers_selesai) === 0;
            $card_class = $is_aktif_now ? 'is-aktif' : ($is_kosong ? 'is-kosong' : '');
            $glow_class = $is_aktif_now ? 'glow-green' : ($is_kosong ? 'glow-red' : 'glow-muted');

            // Progress durasi shift
            $jam_masuk_ts  = strtotime($shift['jam_masuk']);
            $jam_keluar_ts = strtotime($shift['jam_keluar']);
            if ($jam_keluar_ts <= $jam_masuk_ts) $jam_keluar_ts += 86400;
            $durasi_total  = $jam_keluar_ts - $jam_masuk_ts;
            $sekarang_ts   = time();
            $elapsed       = max(0, min($sekarang_ts - strtotime(date('Y-m-d') . ' ' . $shift['jam_masuk']), $durasi_total));
            $pct           = $durasi_total > 0 ? min(100, round(($elapsed / $durasi_total) * 100)) : 0;
            $fill_class    = $pct >= 100 ? 'over' : ($pct >= 75 ? 'warn' : '');
        ?>
        <div class="shift-ov-card <?= $card_class ?>">
            <div class="sov-glow <?= $glow_class ?>"></div>
            <div class="sov-head">
                <div>
                    <div class="sov-name"><?= htmlspecialchars($shift['nama_shift']) ?></div>
                    <div class="sov-time"><?= substr($shift['jam_masuk'],0,5) ?> – <?= substr($shift['jam_keluar'],0,5) ?></div>
                </div>
                <?php if ($is_aktif_now): ?>
                    <span class="badge b-green"><div class="pulse" style="width:6px;height:6px;"></div> Aktif</span>
                <?php elseif ($is_kosong): ?>
                    <span class="badge b-red">Kosong</span>
                <?php else: ?>
                    <span class="badge b-muted">Selesai</span>
                <?php endif; ?>
            </div>

            <!-- Progress bar durasi shift -->
            <?php if ($is_aktif_now): ?>
            <div class="progress-wrap">
                <div class="progress-label">
                    <span>Durasi shift</span>
                    <span><?= $pct ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?= $fill_class ?>" style="width:<?= $pct ?>%;"></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="sov-body" style="margin-top:14px;">
                <?php if (count($officers_aktif) > 0): ?>
                    <?php foreach ($officers_aktif as $off):
                        $dur = hitungDurasi($off['jam_masuk']);
                    ?>
                    <div class="sov-officer">
                        <div class="officer-avatar"><?= strtoupper(substr($off['nama_user'],0,2)) ?></div>
                        <div class="officer-info">
                            <div class="officer-name"><?= htmlspecialchars($off['nama_user']) ?></div>
                            <div class="officer-meta">Masuk <?= date('H:i', strtotime($off['jam_masuk'])) ?> · <span style="color:var(--green);">Berjaga</span></div>
                        </div>
                        <div class="officer-dur"><?= formatDurasi($dur) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php elseif (count($officers_selesai) > 0): ?>
                    <?php foreach ($officers_selesai as $off): ?>
                    <div class="sov-officer" style="opacity:0.65;">
                        <div class="officer-avatar" style="background:rgba(77,98,120,0.2);border-color:rgba(77,98,120,0.3);color:var(--text-muted);"><?= strtoupper(substr($off['nama_user'],0,2)) ?></div>
                        <div class="officer-info">
                            <div class="officer-name"><?= htmlspecialchars($off['nama_user']) ?></div>
                            <div class="officer-meta">
                                Selesai · Keluar <?= date('H:i', strtotime($off['jam_keluar'])) ?>
                                <?php
                                    $sk = $off['status_keluar'] ?? '';
                                    if ($sk === 'pulang_awal') echo ' · <span style="color:var(--red);">Pulang awal</span>';
                                    if ($sk === 'lanjut_shift') echo ' · <span style="color:var(--purple);">Lanjut shift</span>';
                                ?>
                            </div>
                        </div>
                        <span class="badge b-muted">Selesai</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sov-empty">
                        <i class="fas fa-user-slash"></i>
                        <span>Belum ada pamdal</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- BAWAH: 2 KOLOM -->
    <div class="two-col">

        <!-- PAMDAL SEDANG AKTIF -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fas fa-user-check" style="color:var(--green);"></i>
                    Pamdal Sedang Berjaga
                </span>
                <span class="panel-badge pb-green"><?= count($aktif_list) ?></span>
            </div>
            <?php if (empty($aktif_list)): ?>
                <div class="panel-empty">
                    <i class="fas fa-moon"></i>
                    Tidak ada pamdal yang sedang berjaga saat ini.
                </div>
            <?php else: ?>
                <div class="aktif-list">
                    <?php foreach ($aktif_list as $a):
                        $dur = hitungDurasi($a['jam_masuk']);
                    ?>
                    <div class="aktif-item">
                        <div class="dot-aktif"></div>
                        <div class="aktif-avatar"><?= strtoupper(substr($a['nama_user'],0,2)) ?></div>
                        <div class="aktif-info">
                            <div class="aktif-name"><?= htmlspecialchars($a['nama_user']) ?></div>
                            <div class="aktif-meta">
                                <?= htmlspecialchars($a['nama_shift']) ?> · Masuk <?= date('H:i', strtotime($a['jam_masuk'])) ?>
                                <?php if ($a['keterangan_masuk'] === 'terlambat'): ?>
                                    · <span style="color:var(--amber);">Terlambat</span>
                                <?php endif; ?>
                                <?php if ($a['status_masuk'] === 'tidak_sesuai'): ?>
                                    · <span style="color:var(--teal);">Tukar shift</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="aktif-right">
                            <div class="dur-badge"><?= formatDurasi($dur) ?></div>
                            <div class="dur-lbl">berjaga</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TUKAR SHIFT HARI INI -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fas fa-exchange-alt" style="color:var(--teal);"></i>
                    Penukaran Shift Hari Ini
                </span>
                <span class="panel-badge pb-blue"><?= count($tukar_list) ?></span>
            </div>
            <?php if (empty($tukar_list)): ?>
                <div class="panel-empty">
                    <i class="fas fa-check"></i>
                    Tidak ada penukaran shift hari ini.
                </div>
            <?php else: ?>
                <?php foreach ($tukar_list as $t): ?>
                <div class="tukar-item">
                    <div class="tukar-avatar"><?= strtoupper(substr($t['nama_penukar'],0,2)) ?></div>
                    <div class="tukar-info">
                        <div class="tukar-name">
                            <?= htmlspecialchars($t['nama_penukar']) ?>
                            <span style="color:var(--teal);margin:0 4px;font-size:12px;">⇄</span>
                            <?= htmlspecialchars($t['nama_pengganti']) ?>
                        </div>
                        <div class="tukar-detail">
                            Shift <?= htmlspecialchars($t['nama_shift']) ?>
                            · Masuk <?= $t['jam_masuk'] ? date('H:i', strtotime($t['jam_masuk'])) : '—' ?>
                            · <span class="badge b-teal" style="font-size:10px;"><?= ucfirst($t['tipe']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- TABEL LENGKAP HARI INI -->
    <div class="section-title">Semua Absensi Hari Ini</div>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fas fa-table" style="color:var(--accent);"></i>
                Rekap Lengkap — <?= date('d F Y') ?>
            </span>
            <span class="panel-badge pb-blue"><?= count($semua_list) ?> catatan</span>
        </div>
        <?php if (empty($semua_list)): ?>
            <div class="panel-empty">
                <i class="fas fa-calendar-times"></i>
                Belum ada data absensi hari ini.
            </div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Pamdal</th>
                        <th>Shift</th>
                        <th>Jam Masuk</th>
                        <th>Status Masuk</th>
                        <th>Jam Keluar</th>
                        <th>Status Keluar</th>
                        <th>Durasi</th>
                        <th>Laporan</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($semua_list as $a):
                    $dur_menit = 0;
                    if (!empty($a['jam_masuk']) && !empty($a['jam_keluar'])) {
                        $dur_menit = (int)((strtotime($a['jam_keluar']) - strtotime($a['jam_masuk'])) / 60);
                    } elseif (!empty($a['jam_masuk'])) {
                        $dur_menit = hitungDurasi($a['jam_masuk']);
                    }
                ?>
                    <tr>
                        <td class="name-cell"><?= htmlspecialchars($a['nama_user']) ?></td>
                        <td><span class="badge b-blue"><?= htmlspecialchars($a['nama_shift']) ?></span></td>
                        <td class="mono"><?= $a['jam_masuk'] ? date('H:i', strtotime($a['jam_masuk'])) : '—' ?></td>
                        <td>
                            <?php $km = $a['keterangan_masuk'] ?? 'normal'; $sm = $a['status_masuk'] ?? ''; ?>
                            <?php if ($sm === 'tidak_sesuai'): ?>
                                <span class="badge b-teal"><i class="fas fa-exchange-alt" style="font-size:9px;"></i> Tukar</span>
                            <?php elseif ($km === 'terlambat'): ?>
                                <span class="badge b-amber"><i class="fas fa-clock" style="font-size:9px;"></i> Terlambat</span>
                            <?php else: ?>
                                <span class="badge b-green"><i class="fas fa-check" style="font-size:9px;"></i> Tepat</span>
                            <?php endif; ?>
                        </td>
                        <td class="mono"><?= $a['jam_keluar'] ? date('H:i', strtotime($a['jam_keluar'])) : '<span style="color:var(--green);font-size:11px;">Berjaga</span>' ?></td>
                        <td>
                            <?php $sk = $a['status_keluar'] ?? ''; ?>
                            <?php if ($sk === 'tepat_waktu'): ?><span class="badge b-green">Tepat</span>
                            <?php elseif ($sk === 'pulang_awal'): ?><span class="badge b-red">Awal</span>
                            <?php elseif ($sk === 'lanjut_shift'): ?><span class="badge b-purple">Lanjut</span>
                            <?php else: ?><span class="badge b-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="mono">
                            <?php if ($dur_menit > 0): ?>
                                <span style="color:<?= empty($a['jam_keluar']) ? 'var(--green)' : 'var(--text-secondary)' ?>;"><?= formatDurasi($dur_menit) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php $sl = $a['status_laporan'] ?? ''; ?>
                            <?php if ($sl === 'acc'): ?><span class="badge b-green">ACC</span>
                            <?php elseif ($sl === 'pending'): ?><span class="badge b-amber">Pending</span>
                            <?php elseif ($sl === 'revisi'): ?><span class="badge b-red">Revisi</span>
                            <?php else: ?><span class="badge b-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
    // Live clock
    function tickClock() {
        const el = document.getElementById('clock');
        if (!el) return;
        const now = new Date();
        const p = n => String(n).padStart(2, '0');
        el.textContent = p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
    }
    setInterval(tickClock, 1000);

    // Auto-refresh setiap 2 menit
    setTimeout(() => location.reload(), 120000);
</script>
</body>
</html>
