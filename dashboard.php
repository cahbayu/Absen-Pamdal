<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

if (!function_exists('getActiveShift')) {
    function getActiveShift() {
        global $conn;
        $now    = date('H:i:s');
        $sql    = "SELECT * FROM shift";
        $result = $conn->query($sql);
        if (!$result || $result->num_rows === 0) return null;
        while ($shift = $result->fetch_assoc()) {
            $masuk  = $shift['jam_masuk'];
            $keluar = $shift['jam_keluar'];
            if ($masuk < $keluar) {
                if ($now >= $masuk && $now < $keluar) return $shift;
            } else {
                if ($now >= $masuk || $now < $keluar) return $shift;
            }
        }
        return null;
    }
}

$user_id_sesi     = (int)$_SESSION['user_id'];
$tanggal_hari_ini = date('Y-m-d');

$sudah_masuk      = false;
$sudah_keluar     = false;
$jam_masuk_db     = '—';
$jam_keluar_db    = '—';
$absensi_id_aktif = 0;

$shift_nama_user    = '—';
$shift_mulai_user   = '—';
$shift_selesai_user = '—';
$shift_id_user      = 0;
$shift_jam_keluar_raw = null;

// Cek sudah ada laporan atau belum untuk absensi aktif
$sudah_ada_laporan = false;

$q = $conn->prepare(
    "SELECT a.id, a.jam_masuk, a.jam_keluar, a.shift_id,
            s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar
     FROM absensi a
     JOIN shift s ON a.shift_id = s.id
     WHERE a.user_id = ? AND a.tanggal = ?
       AND a.jam_masuk IS NOT NULL
     ORDER BY a.jam_masuk DESC
     LIMIT 1"
);
$q->bind_param('is', $user_id_sesi, $tanggal_hari_ini);
$q->execute();
$res = $q->get_result();
$q->close();

if ($res && $res->num_rows > 0) {
    $row_absen = $res->fetch_assoc();

    $sudah_masuk          = true;
    $absensi_id_aktif     = (int)$row_absen['id'];
    $jam_masuk_db         = date('H:i', strtotime($row_absen['jam_masuk']));
    $shift_id_user        = (int)$row_absen['shift_id'];
    $shift_nama_user      = $row_absen['nama_shift'];
    $shift_mulai_user     = substr($row_absen['shift_jam_masuk'],  0, 5);
    $shift_selesai_user   = substr($row_absen['shift_jam_keluar'], 0, 5);
    $shift_jam_keluar_raw = $row_absen['shift_jam_keluar'];

    if (!empty($row_absen['jam_keluar'])) {
        $sudah_keluar  = true;
        $jam_keluar_db = date('H:i', strtotime($row_absen['jam_keluar']));
    }

    // Cek apakah sudah ada laporan untuk absensi aktif ini
    $cek_lap = $conn->prepare("SELECT id FROM laporan WHERE absensi_id = ? LIMIT 1");
    $cek_lap->bind_param('i', $absensi_id_aktif);
    $cek_lap->execute();
    $res_lap = $cek_lap->get_result();
    $sudah_ada_laporan = ($res_lap && $res_lap->num_rows > 0);
    $cek_lap->close();
}

$dalam_shift_user = false;
if ($sudah_masuk && $shift_jam_keluar_raw) {
    $now          = date('H:i:s');
    $shift_masuk  = substr($row_absen['shift_jam_masuk'],  0, 8);
    $shift_keluar = substr($row_absen['shift_jam_keluar'], 0, 8);

    if ($shift_masuk < $shift_keluar) {
        $dalam_shift_user = ($now >= $shift_masuk && $now < $shift_keluar);
    } else {
        $dalam_shift_user = ($now >= $shift_masuk || $now < $shift_keluar);
    }
}

$url_keluar = 'absen_keluar.php?absensi_id=' . $absensi_id_aktif;
$url_lanjut = 'absen_keluar.php?absensi_id=' . $absensi_id_aktif . '&mode=lanjut';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Pamdal — SELARAS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:       #0f1b2d;
            --navy-mid:   #162236;
            --navy-card:  #1a2b42;
            --navy-hover: #1f3350;
            --navy-line:  rgba(255,255,255,0.07);
            --accent:     #3b9eff;
            --accent-dim: rgba(59,158,255,0.15);
            --green:      #22c55e;
            --green-dim:  rgba(34,197,94,0.15);
            --red:        #f43f5e;
            --red-dim:    rgba(244,63,94,0.15);
            --amber:      #f59e0b;
            --amber-dim:  rgba(245,158,11,0.15);
            --purple:     #a78bfa;
            --purple-dim: rgba(167,139,250,0.15);
            --teal:       #2dd4bf;
            --teal-dim:   rgba(45,212,191,0.15);
            --text-primary:   #e8edf4;
            --text-secondary: #7a90a8;
            --text-muted:     #4d6278;
            --font-main: 'DM Sans', sans-serif;
            --font-mono: 'DM Mono', monospace;
            --radius-sm: 6px;
            --radius-lg: 16px;
            --radius-xl: 22px;
        }

        html { font-size: 16px; }
        body { font-family: var(--font-main); background: var(--navy); color: var(--text-primary); min-height: 100vh; -webkit-font-smoothing: antialiased; }

        /* ── NAVBAR ── */
        .navbar { position: sticky; top: 0; z-index: 100; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; }
        .brand-icon { width: 32px; height: 32px; background: var(--accent); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: white; }
        .navbar-right { display: flex; align-items: center; gap: 16px; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 34px; height: 34px; background: var(--accent-dim); border: 1px solid var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--accent); font-weight: 600; }
        .user-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
        .role-chip { font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); text-decoration: none; }

        /* ── MAIN ── */
        .main { max-width: 940px; margin: 0 auto; padding: 32px 20px 60px; }

        /* ── WELCOME STRIP ── */
        .welcome-strip { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 24px 28px; margin-bottom: 28px; display: flex; align-items: center; gap: 20px; position: relative; overflow: hidden; }
        .welcome-strip::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, var(--accent), var(--teal)); border-radius: 4px 0 0 4px; }
        .welcome-avatar-lg { width: 52px; height: 52px; background: var(--accent-dim); border: 1.5px solid var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: var(--accent); flex-shrink: 0; }
        .welcome-text .name { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .welcome-text .meta { font-size: 13px; color: var(--text-secondary); margin-top: 5px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 10px; border-radius: 20px; }
        .chip-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .chip-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .chip-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .chip-teal   { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .chip-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .chip-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }
        .welcome-time { margin-left: auto; text-align: right; flex-shrink: 0; }
        .clock { font-family: var(--font-mono); font-size: 22px; font-weight: 500; color: var(--text-primary); }
        .clock-date { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ── STATUS BAR ── */
        .status-bar { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 16px 18px; }
        .stat-label { font-size: 11px; font-weight: 500; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px; }
        .stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); font-family: var(--font-mono); }
        .stat-value.ok      { color: var(--green); }
        .stat-value.pending { color: var(--amber); }
        .stat-value.na      { color: var(--text-muted); }

        /* ── SECTION TITLE ── */
        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }

        /* ── ABSENSI GRID ── */
        .absen-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 16px; margin-bottom: 16px; }
        .absen-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 28px 24px; text-align: center; text-decoration: none; color: var(--text-primary); display: block; transition: all 0.22s ease; position: relative; overflow: hidden; }
        .absen-card:hover { transform: translateY(-3px); text-decoration: none; color: var(--text-primary); }

        .absen-card.masuk  { border-color: rgba(34,197,94,0.3); }
        .absen-card.masuk:not(.disabled):hover  { background: rgba(34,197,94,0.08); border-color: var(--green); box-shadow: 0 8px 30px rgba(34,197,94,0.12); }
        .absen-card.masuk.sudah-masuk { border-color: rgba(59,158,255,0.3); }
        .absen-card.masuk.sudah-masuk:hover { background: rgba(59,158,255,0.08); border-color: var(--accent); box-shadow: 0 8px 30px rgba(59,158,255,0.12); }

        .absen-card.keluar { border-color: rgba(244,63,94,0.3); }
        .absen-card.keluar:not(.disabled):hover { background: rgba(244,63,94,0.08); border-color: var(--red); box-shadow: 0 8px 30px rgba(244,63,94,0.12); }
        .absen-card.keluar.warn-laporan { border-color: rgba(245,158,11,0.5); }
        .absen-card.keluar.warn-laporan:not(.disabled):hover { background: rgba(245,158,11,0.08); border-color: var(--amber); box-shadow: 0 8px 30px rgba(245,158,11,0.12); }

        .absen-card.lanjut { border-color: rgba(167,139,250,0.3); }
        .absen-card.lanjut:not(.disabled):hover { background: rgba(167,139,250,0.08); border-color: var(--purple); box-shadow: 0 8px 30px rgba(167,139,250,0.12); }

        .absen-card.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

        .absen-icon-wrap { width: 64px; height: 64px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 26px; }
        .absen-card.masuk  .absen-icon-wrap { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.3); }
        .absen-card.masuk.sudah-masuk .absen-icon-wrap { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); }
        .absen-card.keluar .absen-icon-wrap { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.3); }
        .absen-card.keluar.warn-laporan .absen-icon-wrap { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .absen-card.lanjut .absen-icon-wrap { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }

        .absen-label { font-size: 15px; font-weight: 600; margin-bottom: 6px; }
        .absen-desc  { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
        .absen-rule  { margin-top: 12px; display: inline-flex; align-items: center; gap: 5px; font-size: 11px; padding: 3px 10px; border-radius: 20px; }

        /* Warn badge untuk belum laporan */
        .warn-badge {
            display: flex; align-items: center; gap: 6px;
            margin-top: 10px; padding: 7px 12px;
            border-radius: 10px;
            background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.35);
            color: var(--amber); font-size: 11px; font-weight: 500;
            line-height: 1.4;
        }
        .warn-badge i { flex-shrink: 0; font-size: 13px; }

        /* ── INFO BOX ── */
        .info-box { background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.3); border-radius: var(--radius-lg); padding: 14px 18px; margin-bottom: 16px; display: flex; align-items: flex-start; gap: 12px; font-size: 13px; color: var(--amber); line-height: 1.5; }
        .info-box i { font-size: 16px; margin-top: 1px; flex-shrink: 0; }

        /* ── MENU ── */
        .menu-grid { display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 28px; }
        .menu-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 18px 16px; text-align: center; text-decoration: none; color: var(--text-primary); display: block; transition: all 0.2s; }
        .menu-card:hover { background: var(--navy-hover); border-color: rgba(255,255,255,0.15); transform: translateY(-2px); text-decoration: none; color: var(--text-primary); }
        .menu-icon-sm { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 18px; }
        .i-blue { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.2); }
        .menu-lbl { font-size: 12px; font-weight: 600; }
        .menu-sub { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

        /* ── LANJUT SHIFT ── */
        .lanjut-wrap { margin-bottom: 28px; }

        @media (max-width: 600px) {
            .absen-grid   { grid-template-columns: 1fr; }
            .status-bar   { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .welcome-time { display: none; }
            .navbar       { padding: 0 16px; }
            .main         { padding: 20px 14px 50px; }
            .user-name    { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        SELARAS
    </div>
    <div class="navbar-right">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 2)) ?></div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
            <span class="role-chip">Pamdal</span>
        </div>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- ── WELCOME STRIP ── -->
    <div class="welcome-strip">
        <div class="welcome-avatar-lg"><?= strtoupper(substr($_SESSION['name'], 0, 2)) ?></div>
        <div class="welcome-text">
            <div class="name">Selamat datang, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>!</div>
            <div class="meta">
                <?php if ($sudah_masuk): ?>
                    <span class="chip chip-blue">
                        <i class="fas fa-clock" style="font-size:10px;"></i>
                        <?= htmlspecialchars($shift_nama_user) ?>
                        &middot; <?= $shift_mulai_user ?>–<?= $shift_selesai_user ?>
                    </span>
                    <?php if ($sudah_keluar): ?>
                        <span class="chip chip-teal"><i class="fas fa-check-double" style="font-size:10px;"></i> Absensi selesai</span>
                    <?php else: ?>
                        <span class="chip chip-green"><i class="fas fa-check-circle" style="font-size:10px;"></i> Sudah absen masuk</span>
                        <?php if (!$sudah_ada_laporan): ?>
                            <span class="chip chip-amber"><i class="fas fa-file-alt" style="font-size:10px;"></i> Belum ada laporan</span>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="chip chip-muted">
                        <i class="fas fa-moon" style="font-size:10px;"></i>
                        Shift belum dipilih
                    </span>
                    <span class="chip chip-amber"><i class="fas fa-exclamation-circle" style="font-size:10px;"></i> Belum absen masuk</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="welcome-time">
            <div class="clock" id="clock"><?= date('H:i:s') ?></div>
            <div class="clock-date"><?= date('l, d F Y') ?></div>
        </div>
    </div>

    <!-- ── STATUS BAR ── -->
    <div class="status-bar">
        <div class="stat-card">
            <div class="stat-label">Shift Aktif</div>
            <div class="stat-value <?= $sudah_masuk ? '' : 'na' ?>">
                <?= $sudah_masuk ? htmlspecialchars($shift_nama_user) : '—' ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Absen Masuk</div>
            <div class="stat-value <?= $sudah_masuk ? 'ok' : 'na' ?>">
                <?= $jam_masuk_db ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Absen Keluar</div>
            <div class="stat-value <?= $sudah_keluar ? 'ok' : ($sudah_masuk ? 'pending' : 'na') ?>">
                <?= $jam_keluar_db ?>
            </div>
        </div>
    </div>

    <!-- ── INFO: belum absen masuk ── -->
    <?php if (!$sudah_masuk): ?>
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div>
            Anda belum melakukan absen masuk hari ini. Pilih <strong>Absen Masuk</strong> di bawah dan
            pilih shift Anda (Pagi / Siang / Malam) untuk memulai.
        </div>
    </div>
    <?php endif; ?>

    <!-- ── ABSENSI ── -->
    <div class="section-title">Absensi</div>
    <div class="absen-grid">

        <!-- ABSEN MASUK — SELALU BISA DIKLIK -->
        <a href="absen_masuk.php" class="absen-card masuk <?= $sudah_masuk ? 'sudah-masuk' : '' ?>">
            <div class="absen-icon-wrap">
                <i class="fas <?= $sudah_masuk ? 'fa-pen-to-square' : 'fa-sign-in-alt' ?>"></i>
            </div>
            <div class="absen-label">Absen Masuk</div>
            <div class="absen-desc">
                <?php if ($sudah_masuk): ?>
                    <?php if ($sudah_ada_laporan): ?>
                        Anda sudah absen masuk. Tap untuk <strong>mengubah laporan</strong> shift ini.
                    <?php else: ?>
                        Anda sudah absen masuk. Tap untuk <strong>mengisi laporan</strong> shift ini.
                    <?php endif; ?>
                <?php else: ?>
                    Pilih shift (Pagi / Siang / Malam) dan status sesuai atau tidak sesuai jadwal.
                <?php endif; ?>
            </div>
            <?php if ($sudah_masuk): ?>
                <?php if ($sudah_ada_laporan): ?>
                    <span class="absen-rule chip chip-blue">
                        <i class="fas fa-pen"></i> Ubah laporan shift ini
                    </span>
                <?php else: ?>
                    <span class="absen-rule chip chip-amber">
                        <i class="fas fa-file-alt"></i> Isi laporan shift ini
                    </span>
                <?php endif; ?>
            <?php else: ?>
                <span class="absen-rule chip chip-amber">
                    <i class="fas fa-info-circle"></i> Tap untuk pilih shift &amp; absen masuk
                </span>
            <?php endif; ?>
        </a>

        <!-- ABSEN KELUAR -->
        <?php if ($sudah_masuk && !$sudah_keluar): ?>
            <?php if ($dalam_shift_user): ?>
                <a href="<?= $url_keluar ?>" class="absen-card keluar <?= (!$sudah_ada_laporan) ? 'warn-laporan' : '' ?>">
                    <div class="absen-icon-wrap">
                        <i class="fas <?= (!$sudah_ada_laporan) ? 'fa-exclamation-triangle' : 'fa-sign-out-alt' ?>"></i>
                    </div>
                    <div class="absen-label">Absen Keluar</div>
                    <div class="absen-desc">
                        Pilih tepat waktu, pulang lebih awal (wajib isi alasan),
                        atau lanjut shift berikutnya.
                    </div>
                    <?php if (!$sudah_ada_laporan): ?>
                        <div class="warn-badge">
                            <i class="fas fa-file-alt"></i>
                            <span>Anda belum mengisi laporan shift ini. Laporan bisa diisi di halaman Absen Masuk sebelum keluar.</span>
                        </div>
                    <?php endif; ?>
                    <span class="absen-rule <?= (!$sudah_ada_laporan) ? 'chip chip-amber' : 'chip chip-red' ?>">
                        <i class="fas fa-door-open"></i> Tap untuk absen keluar
                    </span>
                </a>
            <?php else: ?>
                <div class="absen-card keluar disabled" style="opacity:.45; cursor:not-allowed; pointer-events:none;">
                    <div class="absen-icon-wrap"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="absen-label">Absen Keluar</div>
                    <div class="absen-desc">
                        Hanya tersedia pada saat shift
                        <strong><?= htmlspecialchars($shift_nama_user) ?></strong>
                        (<?= $shift_mulai_user ?>–<?= $shift_selesai_user ?>) sedang berjalan.
                    </div>
                    <span class="absen-rule chip chip-amber">
                        <i class="fas fa-clock"></i> Belum waktunya keluar
                    </span>
                </div>
            <?php endif; ?>

        <?php elseif ($sudah_masuk && $sudah_keluar): ?>
            <div class="absen-card keluar disabled">
                <div class="absen-icon-wrap"><i class="fas fa-sign-out-alt"></i></div>
                <div class="absen-label">Absen Keluar</div>
                <div class="absen-desc">Absensi hari ini telah selesai.</div>
                <span class="absen-rule chip chip-teal">
                    <i class="fas fa-check"></i> Sudah diabsen pukul <?= $jam_keluar_db ?>
                </span>
            </div>

        <?php else: ?>
            <div class="absen-card keluar disabled">
                <div class="absen-icon-wrap"><i class="fas fa-sign-out-alt"></i></div>
                <div class="absen-label">Absen Keluar</div>
                <div class="absen-desc">Hanya tersedia setelah absen masuk pada shift ini.</div>
                <span class="absen-rule chip chip-amber">
                    <i class="fas fa-lock"></i> Absen masuk dulu
                </span>
            </div>
        <?php endif; ?>

    </div>

    <!-- ── LANJUT SHIFT ── -->
    <?php if ($sudah_masuk && !$sudah_keluar && $dalam_shift_user): ?>
    <div class="lanjut-wrap">
        <a href="<?= $url_lanjut ?>" class="absen-card lanjut"
           style="display:flex; align-items:center; gap:20px; text-align:left; padding:20px 28px;">
            <div class="absen-icon-wrap" style="margin:0; flex-shrink:0;">
                <i class="fas fa-rotate"></i>
            </div>
            <div>
                <div class="absen-label">Lanjut Shift Berikutnya</div>
                <div class="absen-desc">
                    Tidak perlu absen masuk ulang. Gunakan opsi ini jika Anda
                    menjalani dua shift berturut-turut.
                </div>
            </div>
            <i class="fas fa-arrow-right" style="margin-left:auto; color:var(--purple); font-size:18px;"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- ── LAPORAN ── -->
    <div class="section-title">Informasi &amp; Laporan</div>
    <div class="menu-grid">
        <a href="laporan_harian.php" class="menu-card">
            <div class="menu-icon-sm i-blue"><i class="fas fa-file-alt"></i></div>
            <div class="menu-lbl">Laporan Saya</div>
            <div class="menu-sub">Riwayat absensi &amp; laporan</div>
        </a>
    </div>

</div>

<script>
    function tickClock() {
        const el = document.getElementById('clock');
        if (!el) return;
        const now = new Date();
        const p = n => String(n).padStart(2, '0');
        el.textContent = p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
    }
    setInterval(tickClock, 1000);
</script>
</body>
</html>