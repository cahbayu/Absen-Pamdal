<?php
require_once 'config.php';
requireLogin();

if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$tanggal    = date('Y-m-d');
$pesan      = '';
$tipe_pesan = '';

// ── Ambil semua shift ────────────────────────────────────────
$semua_shift = getAllShifts();

// ── Cari absensi aktif user hari ini (sudah masuk, belum keluar)
// Cari di semua shift yang sudah absen masuk hari ini
$absensi_aktif = null;
foreach ($semua_shift as $shift) {
    $cek = getAbsensiAktif($user_id, $shift['id'], $tanggal);
    if ($cek) {
        $absensi_aktif = $cek;
        break;
    }
}

// Jika shift malam yang carry-over dari kemarin
if (!$absensi_aktif) {
    $tanggal_kemarin = date('Y-m-d', strtotime('-1 day'));
    foreach ($semua_shift as $shift) {
        $nama_lower = strtolower($shift['nama_shift']);
        if (str_contains($nama_lower, 'malam')) {
            $cek = getAbsensiAktif($user_id, $shift['id'], $tanggal_kemarin);
            if ($cek) {
                $absensi_aktif = $cek;
                break;
            }
        }
    }
}

// ── Opsi status keluar berdasarkan waktu ────────────────────
$opsi_keluar = [];
if ($absensi_aktif) {
    $opsi_keluar = getOpsiStatusKeluar($absensi_aktif['shift_jam_keluar']);
}

// ── Proses POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $absensi_id    = (int)($_POST['absensi_id'] ?? 0);
    $status_keluar = cleanInput($_POST['status_keluar'] ?? '');
    $alasan        = cleanInput($_POST['alasan'] ?? '');

    // Rule #3: wajib sudah absen masuk
    if (!$absensi_aktif || $absensi_aktif['id'] !== $absensi_id) {
        $pesan      = 'Data absensi masuk tidak valid. Pastikan Anda sudah absen masuk terlebih dahulu.';
        $tipe_pesan = 'error';
    } else {
        $result     = absenKeluar($user_id, $absensi_id, $status_keluar, $alasan);
        $pesan      = $result['message'];
        $tipe_pesan = $result['success'] ? 'success' : 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Absen Keluar — SELARAS</title>
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

        /* ── NAVBAR ─────────────────────────────────── */
        .navbar {
            position: sticky; top: 0; z-index: 100;
            background: var(--navy-mid);
            border-bottom: 1px solid var(--navy-line);
            padding: 0 28px; height: 58px;
            display: flex; align-items: center; justify-content: space-between;
            backdrop-filter: blur(10px);
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 17px; font-weight: 600;
            color: var(--text-primary); letter-spacing: 0.5px;
            text-decoration: none;
        }
        .brand-icon {
            width: 32px; height: 32px;
            background: var(--accent); border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: white;
        }
        .btn-back {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 500; padding: 6px 14px;
            border-radius: var(--radius-sm);
            background: transparent; border: 1px solid var(--navy-line);
            color: var(--text-secondary); cursor: pointer;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-back:hover {
            background: var(--navy-hover); border-color: rgba(255,255,255,.15);
            color: var(--text-primary); text-decoration: none;
        }

        /* ── MAIN ───────────────────────────────────── */
        .main { max-width: 680px; margin: 0 auto; padding: 36px 20px 80px; }

        /* ── PAGE HEADER ────────────────────────────── */
        .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
        .page-icon {
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--red-dim); border: 1px solid rgba(244,63,94,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: var(--red); flex-shrink: 0;
        }
        .page-title { font-size: 22px; font-weight: 700; color: var(--text-primary); }
        .page-sub   { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

        /* ── ALERT ──────────────────────────────────── */
        .alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 18px; border-radius: var(--radius-lg);
            margin-bottom: 24px; font-size: 13px; line-height: 1.55;
            animation: slideDown .3s ease;
        }
        @keyframes slideDown {
            from { opacity:0; transform:translateY(-8px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .alert-success { background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3);  color: var(--green); }
        .alert-error   { background: var(--red-dim);   border: 1px solid rgba(244,63,94,0.3);  color: var(--red); }
        .alert-warning { background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.3); color: var(--amber); }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-text strong { display: block; margin-bottom: 2px; }

        /* ── CARD ───────────────────────────────────── */
        .card {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-xl);
            padding: 26px 28px;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 11px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.9px;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }

        /* ── INFO BOX WAKTU ─────────────────────────── */
        .info-box {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; background: var(--navy);
            border: 1px solid var(--navy-line); border-radius: var(--radius-md);
            margin-bottom: 20px;
        }
        .info-box-icon {
            width: 36px; height: 36px; border-radius: 8px;
            background: var(--accent-dim); color: var(--accent);
            border: 1px solid rgba(59,158,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .info-box-label { font-size: 11px; color: var(--text-muted); }
        .info-box-val   { font-size: 15px; font-weight: 600; font-family: var(--font-mono); color: var(--text-primary); }

        /* ── SHIFT AKTIF SUMMARY ────────────────────── */
        .shift-summary {
            display: flex; align-items: center; gap: 16px;
            padding: 18px 20px;
            background: var(--navy);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            position: relative; overflow: hidden;
        }
        .shift-summary::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, var(--green), var(--teal));
            border-radius: 3px 0 0 3px;
        }
        .shift-sum-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .shift-sum-icon.pagi  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .shift-sum-icon.sore  { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .shift-sum-icon.malam { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .shift-sum-icon.lain  { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .shift-sum-info { flex: 1; }
        .shift-sum-nama { font-size: 15px; font-weight: 600; color: var(--text-primary); }
        .shift-sum-meta {
            font-size: 12px; color: var(--text-secondary);
            margin-top: 4px; display: flex; flex-wrap: wrap; gap: 10px;
        }
        .shift-sum-meta span { display: flex; align-items: center; gap: 5px; }
        .dot-green { width: 7px; height: 7px; border-radius: 50%; background: var(--green); display: inline-block; }

        /* ── STATUS KELUAR OPTIONS ──────────────────── */
        .opsi-grid { display: flex; flex-direction: column; gap: 12px; }
        .opsi-option { display: none; }
        .opsi-label {
            display: flex; align-items: center; gap: 16px;
            padding: 18px 20px;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--navy-line);
            background: var(--navy);
            cursor: pointer; transition: all 0.22s;
            position: relative; overflow: hidden;
        }
        .opsi-label:hover { border-color: rgba(255,255,255,0.18); background: var(--navy-hover); }
        .opsi-option:checked + .opsi-label {
            border-color: var(--accent);
            background: var(--accent-dim);
            box-shadow: 0 0 0 3px rgba(59,158,255,0.12);
        }

        /* Warna aksen per opsi */
        .opsi-option.ok:checked     + .opsi-label { border-color: var(--green);  background: var(--green-dim);  box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }
        .opsi-option.early:checked  + .opsi-label { border-color: var(--amber);  background: var(--amber-dim);  box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
        .opsi-option.double:checked + .opsi-label { border-color: var(--purple); background: var(--purple-dim); box-shadow: 0 0 0 3px rgba(167,139,250,0.12); }

        .opsi-radio-dot {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid var(--text-muted); flex-shrink: 0;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .opsi-option:checked + .opsi-label .opsi-radio-dot {
            border-color: var(--accent); background: var(--accent);
        }
        .opsi-option.ok:checked     + .opsi-label .opsi-radio-dot { border-color: var(--green);  background: var(--green); }
        .opsi-option.early:checked  + .opsi-label .opsi-radio-dot { border-color: var(--amber);  background: var(--amber); }
        .opsi-option.double:checked + .opsi-label .opsi-radio-dot { border-color: var(--purple); background: var(--purple); }
        .opsi-radio-dot::after {
            content: ''; width: 7px; height: 7px; border-radius: 50%;
            background: white; display: none;
        }
        .opsi-option:checked + .opsi-label .opsi-radio-dot::after { display: block; }

        .opsi-icon {
            width: 44px; height: 44px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .i-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .i-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .i-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }

        .opsi-text-lbl { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .opsi-text-sub { font-size: 12px; color: var(--text-secondary); margin-top: 3px; line-height: 1.4; }

        .opsi-badge {
            margin-left: auto; flex-shrink: 0;
            font-size: 10px; font-weight: 600;
            padding: 3px 9px; border-radius: 20px;
            border: 1px solid transparent;
        }
        .badge-now    { background: var(--green-dim);  color: var(--green);  border-color: rgba(34,197,94,0.3); }
        .badge-wajib  { background: var(--amber-dim);  color: var(--amber);  border-color: rgba(245,158,11,0.3); }
        .badge-double { background: var(--purple-dim); color: var(--purple); border-color: rgba(167,139,250,0.3); }

        /* ── PANEL ALASAN ───────────────────────────── */
        .panel-alasan {
            display: none; margin-top: 18px;
            padding: 20px; border-radius: var(--radius-lg);
            background: var(--navy); border: 1px solid rgba(245,158,11,0.3);
            animation: fadeIn .25s ease;
        }
        .panel-alasan.show { display: block; }
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(6px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .panel-title {
            font-size: 11px; font-weight: 600; color: var(--amber);
            text-transform: uppercase; letter-spacing: 0.8px;
            margin-bottom: 12px;
            display: flex; align-items: center; gap: 7px;
        }
        .form-label {
            display: block; font-size: 11px; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.6px; margin-bottom: 8px;
        }
        textarea.form-control {
            width: 100%; padding: 12px 14px;
            background: var(--navy-card); border: 1px solid var(--navy-line);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-family: var(--font-main); font-size: 13px;
            line-height: 1.6; resize: vertical; min-height: 100px;
            transition: border-color 0.2s;
        }
        textarea.form-control:focus {
            outline: none; border-color: var(--amber);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.12);
        }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
        .char-counter { font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 4px; }

        /* ── PANEL LANJUT SHIFT ─────────────────────── */
        .panel-lanjut {
            display: none; margin-top: 18px;
            padding: 18px 20px; border-radius: var(--radius-lg);
            background: var(--navy); border: 1px solid rgba(167,139,250,0.3);
            animation: fadeIn .25s ease;
        }
        .panel-lanjut.show { display: block; }
        .panel-lanjut-info {
            display: flex; gap: 12px; align-items: flex-start;
        }
        .panel-lanjut-icon {
            width: 36px; height: 36px; border-radius: 8px;
            background: var(--purple-dim); color: var(--purple);
            border: 1px solid rgba(167,139,250,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        .panel-lanjut-text { font-size: 12px; color: var(--text-secondary); line-height: 1.6; }
        .panel-lanjut-text strong { color: var(--purple); }

        /* ── TIDAK ADA ABSENSI AKTIF ────────────────── */
        .empty-state {
            text-align: center; padding: 52px 24px;
        }
        .empty-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: var(--amber-dim); border: 2px solid rgba(245,158,11,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: var(--amber);
            margin: 0 auto 20px;
        }
        .empty-title { font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
        .empty-sub   { font-size: 13px; color: var(--text-secondary); line-height: 1.6; }
        .btn-goto {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 22px; padding: 11px 22px;
            background: var(--green); border: none; border-radius: var(--radius-lg);
            color: white; font-family: var(--font-main); font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-goto:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(34,197,94,0.3); text-decoration: none; color: white; }

        /* ── SUBMIT ─────────────────────────────────── */
        .btn-submit {
            width: 100%; padding: 14px;
            background: var(--red); border: none; border-radius: var(--radius-lg);
            color: white; font-family: var(--font-main); font-size: 15px; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            letter-spacing: 0.3px;
        }
        .btn-submit:hover   { background: #e11d48; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(244,63,94,0.35); }
        .btn-submit:active  { transform: translateY(0); }
        .btn-submit.lanjut  { background: var(--purple); }
        .btn-submit.lanjut:hover { background: #9061f9; box-shadow: 0 6px 20px rgba(167,139,250,0.35); }

        /* ── SUCCESS STATE ──────────────────────────── */
        .success-state { display: none; text-align: center; padding: 48px 24px; }
        .success-state.show { display: block; }
        .success-circle {
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; margin: 0 auto 20px;
            animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
        }
        .success-circle.red    { background: var(--red-dim);    border: 2px solid var(--red);    color: var(--red); }
        .success-circle.purple { background: var(--purple-dim); border: 2px solid var(--purple); color: var(--purple); }
        @keyframes popIn {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        .success-title  { font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
        .success-sub    { font-size: 13px; color: var(--text-secondary); }
        .success-redir  { font-size: 12px; color: var(--text-muted); margin-top: 20px; }

        /* ── DIVIDER ────────────────────────────────── */
        .divider { height: 1px; background: var(--navy-line); margin: 18px 0; }

        /* ── RESPONSIVE ─────────────────────────────── */
        @media (max-width: 600px) {
            .navbar { padding: 0 16px; }
            .main   { padding: 20px 14px 60px; }
            .shift-summary { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        SELARAS
    </a>
    <a href="dashboard.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</nav>

<!-- MAIN -->
<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-icon"><i class="fas fa-sign-out-alt"></i></div>
        <div>
            <div class="page-title">Absen Keluar</div>
            <div class="page-sub">
                <?= date('l, d F Y') ?> &mdash;
                Halo, <strong><?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?></strong>
            </div>
        </div>
    </div>

    <!-- ALERT (error / warning) -->
    <?php if (!empty($pesan) && $tipe_pesan !== 'success'): ?>
    <div class="alert alert-<?= $tipe_pesan ?>">
        <i class="fas fa-<?= $tipe_pesan === 'error' ? 'times-circle' : 'exclamation-triangle' ?>"></i>
        <div class="alert-text">
            <strong><?= $tipe_pesan === 'error' ? 'Gagal' : 'Perhatian' ?></strong>
            <?= htmlspecialchars($pesan) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════
         STATE: SUKSES
    ═════════════════════════════════════ -->
    <?php if ($tipe_pesan === 'success'): ?>
    <?php
        $is_lanjut  = (isset($_POST['status_keluar']) && $_POST['status_keluar'] === 'lanjut_shift');
        $circle_cls = $is_lanjut ? 'purple' : 'red';
        $icon_fa    = $is_lanjut ? 'fa-rotate' : 'fa-sign-out-alt';
        $title      = $is_lanjut ? 'Lanjut Shift Dicatat!' : 'Absen Keluar Berhasil!';
    ?>
    <div class="card">
        <div class="success-state show">
            <div class="success-circle <?= $circle_cls ?>">
                <i class="fas <?= $icon_fa ?>"></i>
            </div>
            <div class="success-title"><?= $title ?></div>
            <div class="success-sub"><?= htmlspecialchars($pesan) ?></div>
            <div style="font-family:var(--font-mono); font-size:22px; color:var(--<?= $circle_cls === 'purple' ? 'purple' : 'red' ?>); margin-top:10px;">
                <?= date('H:i:s') ?>
            </div>
            <div class="success-redir">
                <i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>
                Kembali ke dashboard dalam <span id="countdown">3</span> detik&hellip;
            </div>
        </div>
    </div>

    <?php elseif (!$absensi_aktif): ?>

    <!-- ════════════════════════════════════
         STATE: BELUM ABSEN MASUK (Rule #3)
    ═════════════════════════════════════ -->
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-lock"></i></div>
            <div class="empty-title">Belum Ada Absensi Masuk</div>
            <div class="empty-sub">
                Anda belum melakukan absen masuk pada shift hari ini.<br>
                Selesaikan absen masuk terlebih dahulu sebelum absen keluar.
            </div>
            <a href="absen_masuk.php" class="btn-goto">
                <i class="fas fa-sign-in-alt"></i>
                Absen Masuk Sekarang
            </a>
        </div>
    </div>

    <?php else: ?>

    <!-- ════════════════════════════════════
         STATE: FORM ABSEN KELUAR
    ═════════════════════════════════════ -->

    <!-- INFO WAKTU -->
    <div class="info-box">
        <div class="info-box-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="info-box-label">Waktu Sekarang</div>
            <div class="info-box-val" id="live-clock"><?= date('H:i:s') ?></div>
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div class="info-box-label">Tanggal</div>
            <div style="font-size:13px; font-weight:600; color:var(--text-secondary);"><?= date('d/m/Y') ?></div>
        </div>
    </div>

    <!-- SHIFT AKTIF SUMMARY -->
    <?php
        $nama_lower = strtolower($absensi_aktif['nama_shift']);
        if (str_contains($nama_lower, 'pagi'))      { $sum_icon = 'fa-sun';          $sum_kls = 'pagi'; }
        elseif (str_contains($nama_lower, 'sore'))  { $sum_icon = 'fa-cloud-sun';    $sum_kls = 'sore'; }
        elseif (str_contains($nama_lower, 'malam')) { $sum_icon = 'fa-moon';         $sum_kls = 'malam'; }
        else                                        { $sum_icon = 'fa-calendar-day'; $sum_kls = 'lain'; }

        $jam_masuk_fmt  = date('H:i', strtotime($absensi_aktif['jam_masuk']));
        $jam_keluar_sch = substr($absensi_aktif['shift_jam_keluar'], 0, 5);

        // Hitung durasi kerja
        $dur_menit  = (int)((time() - strtotime($absensi_aktif['jam_masuk'])) / 60);
        $dur_jam    = floor($dur_menit / 60);
        $dur_sisa   = $dur_menit % 60;
        $durasi_str = $dur_jam . 'j ' . $dur_sisa . 'm';
    ?>
    <div class="shift-summary">
        <div class="shift-sum-icon <?= $sum_kls ?>">
            <i class="fas <?= $sum_icon ?>"></i>
        </div>
        <div class="shift-sum-info">
            <div class="shift-sum-nama"><?= htmlspecialchars($absensi_aktif['nama_shift']) ?></div>
            <div class="shift-sum-meta">
                <span>
                    <i class="fas fa-sign-in-alt" style="font-size:10px; color:var(--green);"></i>
                    Masuk: <strong style="color:var(--text-primary);"><?= $jam_masuk_fmt ?></strong>
                </span>
                <span>
                    <i class="fas fa-sign-out-alt" style="font-size:10px; color:var(--red);"></i>
                    Jadwal keluar: <strong style="color:var(--text-primary);"><?= $jam_keluar_sch ?></strong>
                </span>
                <span>
                    <i class="fas fa-stopwatch" style="font-size:10px; color:var(--accent);"></i>
                    Durasi: <strong style="color:var(--text-primary);" id="durasi-kerja"><?= $durasi_str ?></strong>
                </span>
            </div>
        </div>
        <div>
            <span style="font-size:10px; font-weight:600; padding:3px 9px; border-radius:20px; background:var(--green-dim); color:var(--green); border:1px solid rgba(34,197,94,0.3);">
                <span class="dot-green"></span> AKTIF
            </span>
        </div>
    </div>

    <!-- FORM -->
    <form method="POST" id="form-keluar">
        <input type="hidden" name="absensi_id" value="<?= $absensi_aktif['id'] ?>">

        <!-- ──────────────────────────────────────
             PILIH STATUS KELUAR
        ─────────────────────────────────────── -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-clipboard-list"></i>
                Pilih Status Keluar
            </div>

            <?php if (empty($opsi_keluar)): ?>
            <div style="text-align:center; padding:24px 0; color:var(--text-muted); font-size:13px;">
                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                Tidak ada opsi keluar yang tersedia saat ini. Silakan coba kembali nanti.
            </div>
            <?php else: ?>

            <div class="opsi-grid">
                <?php foreach ($opsi_keluar as $i => $opsi):
                    // Tentukan kelas & tampilan per opsi
                    if ($opsi['value'] === 'tepat_waktu') {
                        $o_cls   = 'ok';
                        $o_icon  = 'fa-check-circle';
                        $o_icls  = 'i-green';
                        $o_badge = '<span class="opsi-badge badge-now">TEPAT WAKTU</span>';
                        $o_sub   = 'Sesuai jadwal pulang shift Anda.';
                    } elseif ($opsi['value'] === 'pulang_awal') {
                        $o_cls   = 'early';
                        $o_icon  = 'fa-door-open';
                        $o_icls  = 'i-amber';
                        $o_badge = '<span class="opsi-badge badge-wajib">WAJIB ALASAN</span>';
                        $o_sub   = 'Pulang sebelum jadwal — wajib isi alasan.';
                    } else { // lanjut_shift
                        $o_cls   = 'double';
                        $o_icon  = 'fa-rotate';
                        $o_icls  = 'i-purple';
                        $o_badge = '<span class="opsi-badge badge-double">DOUBLE SHIFT</span>';
                        $o_sub   = 'Tidak perlu absen masuk ulang di shift berikutnya.';
                    }
                    $checked = ($i === 0) ? 'checked' : '';
                ?>
                <div>
                    <input
                        type="radio"
                        name="status_keluar"
                        id="opsi_<?= $opsi['value'] ?>"
                        value="<?= $opsi['value'] ?>"
                        class="opsi-option <?= $o_cls ?>"
                        <?= $checked ?>
                    >
                    <label for="opsi_<?= $opsi['value'] ?>" class="opsi-label">
                        <div class="opsi-radio-dot"></div>
                        <div class="opsi-icon <?= $o_icls ?>">
                            <i class="fas <?= $o_icon ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div class="opsi-text-lbl"><?= $opsi['label'] ?></div>
                            <div class="opsi-text-sub"><?= $o_sub ?></div>
                        </div>
                        <?= $o_badge ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Panel Alasan (untuk pulang_awal) -->
            <div class="panel-alasan" id="panel-alasan">
                <div class="panel-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Alasan Pulang Lebih Awal &mdash; Wajib Diisi
                </div>
                <label class="form-label" for="alasan">
                    <i class="fas fa-comment-alt" style="margin-right:4px;"></i>
                    Tuliskan alasan Anda
                </label>
                <textarea
                    name="alasan"
                    id="alasan"
                    class="form-control"
                    placeholder="Contoh: Ada keperluan keluarga mendesak / kondisi kesehatan menurun..."
                    maxlength="500"
                    rows="4"
                ></textarea>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                    <div class="form-hint">Minimal 10 karakter. Alasan akan direkam untuk keperluan laporan.</div>
                    <div class="char-counter"><span id="char-count">0</span>/500</div>
                </div>
            </div>

            <!-- Panel Lanjut Shift (untuk lanjut_shift) -->
            <div class="panel-lanjut" id="panel-lanjut">
                <div class="panel-lanjut-info">
                    <div class="panel-lanjut-icon"><i class="fas fa-info-circle"></i></div>
                    <div class="panel-lanjut-text">
                        <strong>Double Shift:</strong> Sistem akan otomatis mencatat absensi masuk untuk shift berikutnya.
                        Anda <strong>tidak perlu absen masuk ulang</strong> pada shift berikutnya.
                        Pastikan kondisi fisik Anda memadai sebelum melanjutkan.
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <?php if (!empty($opsi_keluar)): ?>
        <!-- SUBMIT -->
        <button type="submit" class="btn-submit" id="btn-submit">
            <i class="fas fa-sign-out-alt" id="btn-icon"></i>
            <span id="btn-text">Konfirmasi Absen Keluar</span>
        </button>

        <p style="text-align:center; font-size:12px; color:var(--text-muted); margin-top:14px;">
            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
            Opsi <strong style="color:var(--green);">Tepat Waktu</strong> hanya tersedia dalam rentang ±10 menit dari jam pulang shift.
        </p>
        <?php endif; ?>

    </form>
    <?php endif; ?>

</div><!-- /.main -->

<script>
/* ── Live Clock ──────────────────────────────────────────── */
(function tickClock() {
    const el = document.getElementById('live-clock');
    if (el) {
        const n = new Date(), p = n => String(n).padStart(2, '0');
        el.textContent = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
    }
    setTimeout(tickClock, 1000);
})();

/* ── Durasi kerja live update ────────────────────────────── */
<?php if ($absensi_aktif && $tipe_pesan !== 'success'): ?>
(function updateDurasi() {
    const el = document.getElementById('durasi-kerja');
    if (!el) return;
    const masuk = new Date('<?= $absensi_aktif['jam_masuk'] ?>');
    function tick() {
        const diff = Math.floor((Date.now() - masuk) / 60000);
        const j = Math.floor(diff / 60), m = diff % 60;
        el.textContent = j + 'j ' + m + 'm';
    }
    tick();
    setInterval(tick, 30000);
})();
<?php endif; ?>

/* ── Countdown redirect setelah sukses ──────────────────── */
<?php if ($tipe_pesan === 'success'): ?>
let cd = 3;
const cdEl = document.getElementById('countdown');
const t = setInterval(() => {
    cd--;
    if (cdEl) cdEl.textContent = cd;
    if (cd <= 0) { clearInterval(t); location.href = 'dashboard.php'; }
}, 1000);
<?php endif; ?>

/* ── Toggle panel berdasarkan opsi dipilih ──────────────── */
const panelAlasan = document.getElementById('panel-alasan');
const panelLanjut = document.getElementById('panel-lanjut');
const btnSubmit   = document.getElementById('btn-submit');
const btnIcon     = document.getElementById('btn-icon');
const btnText     = document.getElementById('btn-text');

function updatePanel() {
    const val = document.querySelector('input[name="status_keluar"]:checked')?.value;

    // Reset panels
    if (panelAlasan) panelAlasan.classList.remove('show');
    if (panelLanjut) panelLanjut.classList.remove('show');

    if (!btnSubmit) return;

    if (val === 'pulang_awal') {
        if (panelAlasan) panelAlasan.classList.add('show');
        const alasanEl = document.getElementById('alasan');
        if (alasanEl) alasanEl.required = true;
        btnSubmit.className  = 'btn-submit';
        btnIcon.className    = 'fas fa-door-open';
        btnText.textContent  = 'Konfirmasi Pulang Lebih Awal';
    } else if (val === 'lanjut_shift') {
        if (panelLanjut) panelLanjut.classList.add('show');
        const alasanEl = document.getElementById('alasan');
        if (alasanEl) alasanEl.required = false;
        btnSubmit.className  = 'btn-submit lanjut';
        btnIcon.className    = 'fas fa-rotate';
        btnText.textContent  = 'Konfirmasi Lanjut Shift';
    } else {
        // tepat_waktu
        const alasanEl = document.getElementById('alasan');
        if (alasanEl) alasanEl.required = false;
        btnSubmit.className  = 'btn-submit';
        btnIcon.className    = 'fas fa-sign-out-alt';
        btnText.textContent  = 'Konfirmasi Absen Keluar';
    }
}

document.querySelectorAll('input[name="status_keluar"]').forEach(r => r.addEventListener('change', updatePanel));
window.addEventListener('load', updatePanel);

/* ── Char counter untuk textarea alasan ─────────────────── */
const alasanEl   = document.getElementById('alasan');
const charCount  = document.getElementById('char-count');
if (alasanEl && charCount) {
    alasanEl.addEventListener('input', () => {
        charCount.textContent = alasanEl.value.length;
        charCount.style.color = alasanEl.value.length < 10 ? 'var(--amber)' : 'var(--text-muted)';
    });
}

/* ── Konfirmasi submit ───────────────────────────────────── */
const formKeluar = document.getElementById('form-keluar');
if (formKeluar) {
    formKeluar.addEventListener('submit', function(e) {
        const val    = document.querySelector('input[name="status_keluar"]:checked')?.value;
        const clock  = document.getElementById('live-clock')?.textContent || '';

        // Validasi alasan jika pulang_awal
        if (val === 'pulang_awal') {
            const alasan = (document.getElementById('alasan')?.value || '').trim();
            if (alasan.length < 10) {
                e.preventDefault();
                alert('Alasan pulang awal wajib diisi minimal 10 karakter.');
                document.getElementById('alasan')?.focus();
                return;
            }
        }

        const labelMap = {
            'tepat_waktu':  'Tepat Waktu',
            'pulang_awal':  'Pulang Lebih Awal',
            'lanjut_shift': 'Lanjut Shift Berikutnya',
        };

        const ok = confirm(
            'Konfirmasi Absen Keluar\n\n' +
            'Status : ' + (labelMap[val] || val) + '\n' +
            'Waktu  : ' + clock + '\n\n' +
            'Pastikan data sudah benar sebelum melanjutkan.'
        );
        if (!ok) e.preventDefault();
    });
}
</script>
</body>
</html>