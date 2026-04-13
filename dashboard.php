<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Pastikan hanya role pamdal/user yang bisa akses
if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

// Ambil data shift aktif hari ini (contoh query, sesuaikan dengan struktur DB Anda)
// $shift_aktif  = getShiftAktif($_SESSION['user_id']);
// $absen_masuk  = getAbsenMasuk($_SESSION['user_id'], date('Y-m-d'));
// $absen_keluar = getAbsenKeluar($_SESSION['user_id'], date('Y-m-d'));

// --- PLACEHOLDER (hapus jika sudah terhubung DB) ---
$shift_nama      = 'Shift Pagi';
$shift_mulai     = '07:00';
$shift_selesai   = '15.00';
$jam_sekarang    = date('H:i');
$sudah_masuk     = false;   // true jika sudah absen masuk
$sudah_keluar    = false;   // true jika sudah absen keluar
$terlambat       = false;   // true jika sudah melewati toleransi
$status_approval = '';      // '', 'pending', 'approved', 'rejected', 'revision'
// --------------------------------------------------
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
        /* ============================================================
           RESET & BASE
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:       #0f1b2d;
            --navy-mid:   #162236;
            --navy-card:  #1a2b42;
            --navy-hover: #1f3350;
            --navy-line:  rgba(255,255,255,0.07);

            --accent:     #3b9eff;
            --accent-dim: rgba(59,158,255,0.15);
            --accent-glow:rgba(59,158,255,0.25);

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
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 22px;
        }

        html { font-size: 16px; }

        body {
            font-family: var(--font-main);
            background-color: var(--navy);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================
           NAVBAR
        ============================================================ */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--navy-mid);
            border-bottom: 1px solid var(--navy-line);
            padding: 0 28px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: 0.5px;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            background: var(--accent-dim);
            border: 1px solid var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--accent);
            font-weight: 600;
        }

        .user-name {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .role-chip {
            font-size: 11px;
            font-weight: 500;
            padding: 2px 9px;
            border-radius: 20px;
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid rgba(59,158,255,0.3);
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            background: transparent;
            border: 1px solid var(--navy-line);
            color: var(--text-secondary);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: var(--red-dim);
            border-color: var(--red);
            color: var(--red);
            text-decoration: none;
        }

        /* ============================================================
           MAIN LAYOUT
        ============================================================ */
        .main {
            max-width: 940px;
            margin: 0 auto;
            padding: 32px 20px 60px;
        }

        /* ============================================================
           WELCOME STRIP
        ============================================================ */
        .welcome-strip {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-xl);
            padding: 24px 28px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .welcome-strip::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, var(--accent), var(--teal));
            border-radius: 4px 0 0 4px;
        }

        .welcome-avatar-lg {
            width: 52px;
            height: 52px;
            background: var(--accent-dim);
            border: 1.5px solid var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: var(--accent);
            flex-shrink: 0;
        }

        .welcome-text .name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .welcome-text .meta {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 5px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 500;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .chip-blue   { background: var(--accent-dim);  color: var(--accent);  border: 1px solid rgba(59,158,255,0.25); }
        .chip-green  { background: var(--green-dim);   color: var(--green);   border: 1px solid rgba(34,197,94,0.25); }
        .chip-amber  { background: var(--amber-dim);   color: var(--amber);   border: 1px solid rgba(245,158,11,0.25); }
        .chip-red    { background: var(--red-dim);     color: var(--red);     border: 1px solid rgba(244,63,94,0.25); }
        .chip-purple { background: var(--purple-dim);  color: var(--purple);  border: 1px solid rgba(167,139,250,0.25); }
        .chip-teal   { background: var(--teal-dim);    color: var(--teal);    border: 1px solid rgba(45,212,191,0.25); }

        .welcome-time {
            margin-left: auto;
            text-align: right;
            flex-shrink: 0;
        }

        .clock {
            font-family: var(--font-mono);
            font-size: 22px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .clock-date {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ============================================================
           STATUS BAR
        ============================================================ */
        .status-bar {
            display: grid;
            grid-template-columns: repeat(4, minmax(0,1fr));
            gap: 12px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 16px 18px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            font-family: var(--font-mono);
        }

        .stat-value.pending { color: var(--amber); }
        .stat-value.ok      { color: var(--green); }
        .stat-value.na      { color: var(--text-muted); }

        /* ============================================================
           SECTION TITLE
        ============================================================ */
        .section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }

        /* ============================================================
           MENU UTAMA — ABSENSI (2 kartu besar)
        ============================================================ */
        .absen-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .absen-card {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-xl);
            padding: 28px 24px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            display: block;
            transition: all 0.22s ease;
            position: relative;
            overflow: hidden;
        }

        .absen-card:hover {
            transform: translateY(-3px);
            text-decoration: none;
            color: var(--text-primary);
        }

        .absen-card.masuk { border-color: rgba(34,197,94,0.3); }
        .absen-card.masuk:hover { background: rgba(34,197,94,0.08); border-color: var(--green); box-shadow: 0 8px 30px rgba(34,197,94,0.12); }

        .absen-card.keluar { border-color: rgba(244,63,94,0.3); }
        .absen-card.keluar:hover { background: rgba(244,63,94,0.08); border-color: var(--red); box-shadow: 0 8px 30px rgba(244,63,94,0.12); }

        .absen-card.lanjut { border-color: rgba(167,139,250,0.3); }
        .absen-card.lanjut:hover { background: rgba(167,139,250,0.08); border-color: var(--purple); box-shadow: 0 8px 30px rgba(167,139,250,0.12); }

        .absen-card.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .absen-icon-wrap {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 26px;
        }

        .absen-card.masuk  .absen-icon-wrap { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.3); }
        .absen-card.keluar .absen-icon-wrap { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.3); }
        .absen-card.lanjut .absen-icon-wrap { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }

        .absen-label {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .absen-desc {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .absen-rule {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ============================================================
           MENU SEKUNDER
        ============================================================ */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 12px;
            margin-bottom: 28px;
        }

        .menu-card {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 18px 16px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            display: block;
            transition: all 0.2s;
        }

        .menu-card:hover {
            background: var(--navy-hover);
            border-color: rgba(255,255,255,0.15);
            transform: translateY(-2px);
            text-decoration: none;
            color: var(--text-primary);
        }

        .menu-icon-sm {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 18px;
        }

        .i-blue   { background: var(--accent-dim);  color: var(--accent);  border: 1px solid rgba(59,158,255,0.2); }
        .i-teal   { background: var(--teal-dim);    color: var(--teal);    border: 1px solid rgba(45,212,191,0.2); }
        .i-amber  { background: var(--amber-dim);   color: var(--amber);   border: 1px solid rgba(245,158,11,0.2); }

        .menu-lbl { font-size: 12px; font-weight: 600; }
        .menu-sub { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

        /* ============================================================
           RULES INFO BOX
        ============================================================ */
        .rules-box {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 28px;
        }

        .rules-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            cursor: pointer;
        }

        .rules-header-icon {
            width: 32px;
            height: 32px;
            background: var(--amber-dim);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            color: var(--amber);
        }

        .rules-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .rules-toggle {
            font-size: 12px;
            color: var(--text-muted);
        }

        .rules-list {
            display: none;
        }

        .rules-list.open {
            display: block;
        }

        .rule-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--navy-line);
        }

        .rule-item:last-child { border-bottom: none; padding-bottom: 0; }

        .rule-num {
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 500;
            color: var(--accent);
            width: 20px;
            flex-shrink: 0;
            padding-top: 1px;
        }

        .rule-text {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .rule-text strong {
            color: var(--text-primary);
            font-weight: 500;
        }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 600px) {
            .absen-grid   { grid-template-columns: 1fr; }
            .menu-grid    { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .status-bar   { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .welcome-time { display: none; }
            .navbar       { padding: 0 16px; }
            .main         { padding: 20px 14px 50px; }
            .user-name    { display: none; }
        }
    </style>
</head>
<body>

<!-- ============================================================
     NAVBAR
============================================================ -->
<nav class="navbar">
    <div class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        SELARAS
    </div>
    <div class="navbar-right">
        <div class="user-info">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['name'], 0, 2)) ?>
            </div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
            <span class="role-chip">Pamdal</span>
        </div>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>

<!-- ============================================================
     MAIN CONTENT
============================================================ -->
<div class="main">

    <!-- WELCOME STRIP -->
    <div class="welcome-strip">
        <div class="welcome-avatar-lg">
            <?= strtoupper(substr($_SESSION['name'], 0, 2)) ?>
        </div>
        <div class="welcome-text">
            <div class="name">Selamat datang, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>!</div>
            <div class="meta">
                <span class="chip chip-blue">
                    <i class="fas fa-clock" style="font-size:10px;"></i>
                    <?= $shift_nama ?> · <?= $shift_mulai ?>–<?= $shift_selesai ?>
                </span>
                <?php if (!$sudah_masuk): ?>
                    <span class="chip chip-amber">
                        <i class="fas fa-exclamation-circle" style="font-size:10px;"></i>
                        Belum absen masuk
                    </span>
                <?php elseif (!$sudah_keluar): ?>
                    <span class="chip chip-green">
                        <i class="fas fa-check-circle" style="font-size:10px;"></i>
                        Sudah absen masuk
                    </span>
                <?php else: ?>
                    <span class="chip chip-teal">
                        <i class="fas fa-check-double" style="font-size:10px;"></i>
                        Absensi selesai
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="welcome-time">
            <div class="clock" id="clock"><?= date('H:i:s') ?></div>
            <div class="clock-date"><?= date('l, d F Y') ?></div>
        </div>
    </div>

    <!-- STATUS BAR -->
    <div class="status-bar">
        <div class="stat-card">
            <div class="stat-label">Shift Aktif</div>
            <div class="stat-value"><?= $shift_nama ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Absen Masuk</div>
            <div class="stat-value <?= $sudah_masuk ? 'ok' : 'pending' ?>">
                <?= $sudah_masuk ? date('H:i') : '—' ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Absen Keluar</div>
            <div class="stat-value <?= $sudah_keluar ? 'ok' : 'na' ?>">
                <?= $sudah_keluar ? date('H:i') : '—' ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Status Approval</div>
            <div class="stat-value <?= $status_approval === 'approved' ? 'ok' : ($status_approval === 'pending' ? 'pending' : 'na') ?>">
                <?php
                    $label_approval = [
                        ''         => '—',
                        'pending'  => 'Menunggu',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        'revision' => 'Revisi',
                    ];
                    echo $label_approval[$status_approval] ?? '—';
                ?>
            </div>
        </div>
    </div>

    <!-- SECTION: ABSENSI UTAMA -->
    <div class="section-title">Absensi</div>
    <div class="absen-grid">

        <!-- ABSEN MASUK -->
        <a href="absen_masuk.php" class="absen-card masuk <?= $sudah_masuk ? 'disabled' : '' ?>">
            <div class="absen-icon-wrap">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <div class="absen-label">Absen Masuk</div>
            <div class="absen-desc">
                Pilih status sesuai jadwal atau tidak sesuai jadwal.
                Jika tidak sesuai, wajib isi data penukar/pengganti.
            </div>
            <?php if ($sudah_masuk): ?>
                <span class="absen-rule chip chip-teal" style="margin-top:12px;">
                    <i class="fas fa-check"></i> Sudah diabsen
                </span>
            <?php else: ?>
                <span class="absen-rule chip chip-amber" style="margin-top:12px;">
                    <i class="fas fa-info-circle"></i> Toleransi terlambat 15 menit
                </span>
            <?php endif; ?>
        </a>

        <!-- ABSEN KELUAR / LANJUT SHIFT -->
        <?php if ($sudah_masuk): ?>
        <a href="absen_keluar.php" class="absen-card keluar <?= $sudah_keluar ? 'disabled' : '' ?>">
            <div class="absen-icon-wrap">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div class="absen-label">Absen Keluar</div>
            <div class="absen-desc">
                Pilih tepat waktu atau pulang lebih awal.
                Pulang lebih awal wajib disertai alasan.
            </div>
            <?php if ($sudah_keluar): ?>
                <span class="absen-rule chip chip-teal" style="margin-top:12px;">
                    <i class="fas fa-check"></i> Sudah diabsen
                </span>
            <?php else: ?>
                <span class="absen-rule chip chip-red" style="margin-top:12px;">
                    <i class="fas fa-door-open"></i> Atau lanjut shift berikutnya
                </span>
            <?php endif; ?>
        </a>
        <?php else: ?>
        <div class="absen-card keluar disabled">
            <div class="absen-icon-wrap">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div class="absen-label">Absen Keluar</div>
            <div class="absen-desc">
                Hanya tersedia setelah absen masuk pada shift ini.
            </div>
            <span class="absen-rule chip chip-amber" style="margin-top:12px;">
                <i class="fas fa-lock"></i> Absen masuk dulu
            </span>
        </div>
        <?php endif; ?>

    </div>

    <!-- LANJUT SHIFT (ditampilkan jika sudah masuk dan belum keluar) -->
    <?php if ($sudah_masuk && !$sudah_keluar): ?>
    <div class="absen-grid" style="margin-top:-12px;">
        <a href="lanjut_shift.php" class="absen-card lanjut" style="grid-column: 1 / -1; display:flex; align-items:center; gap:20px; text-align:left; padding:20px 28px;">
            <div class="absen-icon-wrap" style="margin:0; flex-shrink:0;">
                <i class="fas fa-rotate"></i>
            </div>
            <div>
                <div class="absen-label">Lanjut Shift Berikutnya</div>
                <div class="absen-desc">
                    Tidak perlu absen masuk ulang. Gunakan opsi ini sebagai pengganti absen keluar jika Anda menjalani dua shift berturut-turut.
                </div>
            </div>
            <i class="fas fa-arrow-right" style="margin-left:auto; color:var(--purple); font-size:18px;"></i>
        </a>
    </div>
    <?php endif; ?>

    <!-- SECTION: MENU LAIN -->
    <div class="section-title">Informasi & Laporan</div>
    <div class="menu-grid">
        <a href="laporan_saya.php" class="menu-card">
            <div class="menu-icon-sm i-blue"><i class="fas fa-file-alt"></i></div>
            <div class="menu-lbl">Laporan Saya</div>
            <div class="menu-sub">Riwayat absensi</div>
        </a>
        <a href="jadwal_shift.php" class="menu-card">
            <div class="menu-icon-sm i-teal"><i class="fas fa-calendar-alt"></i></div>
            <div class="menu-lbl">Jadwal Shift</div>
            <div class="menu-sub">Lihat jadwal bulan ini</div>
        </a>
        <a href="status_approval.php" class="menu-card">
            <div class="menu-icon-sm i-amber"><i class="fas fa-clipboard-check"></i></div>
            <div class="menu-lbl">Status Persetujuan</div>
            <div class="menu-sub">ACC / revisi dari kepala</div>
        </a>
    </div>
</div><!-- /.main -->

<script>
    /* Live clock */
    function tickClock() {
        const el = document.getElementById('clock');
        if (!el) return;
        const now = new Date();
        const h = String(now.getHours()).padStart(2,'0');
        const m = String(now.getMinutes()).padStart(2,'0');
        const s = String(now.getSeconds()).padStart(2,'0');
        el.textContent = h + ':' + m + ':' + s;
    }
    setInterval(tickClock, 1000);

    /* Toggle rules */
    function toggleRules() {
        const list = document.getElementById('rules-list');
        const lbl  = document.getElementById('rules-toggle-lbl');
        const open = list.classList.toggle('open');
        lbl.innerHTML = open
            ? '<i class="fas fa-chevron-up"></i> Tutup'
            : '<i class="fas fa-chevron-down"></i> Lihat';
    }
</script>
</body>
</html>