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

$semua_shift = getAllShifts();

$shift_aktif = getActiveShift();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shift_id     = (int)($_POST['shift_id'] ?? 0);
    $status_masuk = cleanInput($_POST['status_masuk'] ?? '');

    $data_penukaran = [];
    if ($status_masuk === 'tidak_sesuai') {
        $data_penukaran = [
            'tipe'              => cleanInput($_POST['tipe_penukaran'] ?? ''),
            'user_pengganti_id' => (int)($_POST['user_pengganti_id'] ?? 0),
            'tanggal'           => cleanInput($_POST['tanggal_penukaran'] ?? ''),
            'shift_id'          => (int)($_POST['shift_penukaran_id'] ?? 0),
        ];
    }

    if ($shift_id <= 0) {
        $pesan      = 'Harap pilih shift terlebih dahulu.';
        $tipe_pesan = 'error';
    } elseif (sudahAbsenMasuk($user_id, $shift_id, $tanggal)) {
        $pesan      = 'Anda sudah absen masuk pada shift ini hari ini.';
        $tipe_pesan = 'warning';
    } else {
        $result     = absenMasuk($user_id, $shift_id, $status_masuk, $data_penukaran);
        $pesan      = $result['message'];
        $tipe_pesan = $result['success'] ? 'success' : 'error';

        if ($result['success'] && $status_masuk === 'tidak_sesuai') {
            $isi = 'Absen masuk tidak sesuai jadwal. Tipe: ' . $data_penukaran['tipe'] . '. User pengganti ID: ' . $data_penukaran['user_pengganti_id'] . '.';
            buatLaporan($result['absensi_id'], $isi);
        }
    }
}

// ── Dropdown pamdal lain ─────────────────────────────────────
$semua_pamdal = getAllUsersForDropdown($user_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Absen Masuk — SELARAS</title>
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

        /* NAVBAR */
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
        .navbar-right { display: flex; align-items: center; gap: 12px; }
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

        /* MAIN */
        .main { max-width: 680px; margin: 0 auto; padding: 36px 20px 80px; }

        /* PAGE HEADER */
        .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
        .page-icon {
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--green-dim); border: 1px solid rgba(34,197,94,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: var(--green); flex-shrink: 0;
        }
        .page-title { font-size: 22px; font-weight: 700; color: var(--text-primary); }
        .page-sub   { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

        /* ALERT */
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

        /* CARD */
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

        /* SHIFT CARDS */
        .shift-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .shift-option { display: none; }
        .shift-label {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 8px; padding: 18px 12px;
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--navy-line);
            background: var(--navy);
            cursor: pointer; transition: all 0.2s;
            text-align: center; position: relative; overflow: hidden;
        }
        .shift-label:hover { border-color: rgba(59,158,255,0.4); background: var(--accent-dim); }
        .shift-option:checked + .shift-label {
            border-color: var(--accent);
            background: var(--accent-dim);
            box-shadow: 0 0 0 3px rgba(59,158,255,0.15);
        }
        .shift-check {
            position: absolute; top: 8px; right: 8px;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--accent); color: white;
            display: none; align-items: center; justify-content: center;
            font-size: 9px;
        }
        .shift-option:checked + .shift-label .shift-check { display: flex; }
        .shift-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
        }
        .shift-icon.pagi  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .shift-icon.sore  { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .shift-icon.malam { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .shift-icon.lain  { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .shift-nama { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .shift-jam  { font-size: 11px; color: var(--text-secondary); font-family: var(--font-mono); }
        .badge-aktif {
            font-size: 9px; font-weight: 600;
            padding: 2px 7px; border-radius: 20px;
            background: var(--green-dim); color: var(--green);
            border: 1px solid rgba(34,197,94,0.3); letter-spacing: 0.4px;
        }
        .badge-sudah {
            font-size: 9px; font-weight: 600;
            padding: 2px 7px; border-radius: 20px;
            background: var(--teal-dim); color: var(--teal);
            border: 1px solid rgba(45,212,191,0.3); letter-spacing: 0.4px;
        }

        /* INFO TELAT */
        .info-telat {
            display: none; margin-top: 14px;
            padding: 12px 16px; border-radius: var(--radius-md);
            font-size: 12px; line-height: 1.5;
        }
        .info-telat.show { display: flex; gap: 10px; align-items: flex-start; }
        .info-telat i { margin-top: 1px; flex-shrink: 0; }

        /* STATUS MASUK */
        .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .status-option { display: none; }
        .status-label {
            display: flex; align-items: center; gap: 14px;
            padding: 16px 18px; border-radius: var(--radius-lg);
            border: 1.5px solid var(--navy-line);
            background: var(--navy); cursor: pointer; transition: all 0.2s;
        }
        .status-label:hover { border-color: rgba(255,255,255,0.15); background: var(--navy-hover); }
        .status-option:checked + .status-label {
            border-color: var(--accent);
            background: var(--accent-dim);
            box-shadow: 0 0 0 3px rgba(59,158,255,0.12);
        }
        .status-icon {
            width: 38px; height: 38px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }
        .s-green { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.25); }
        .s-amber { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.25); }
        .status-text-lbl { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .status-text-sub { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

        /* PANEL TIDAK SESUAI */
        .panel-tidak-sesuai {
            display: none; margin-top: 18px;
            padding: 20px; border-radius: var(--radius-lg);
            background: var(--navy); border: 1px solid rgba(245,158,11,0.25);
            animation: fadeIn .25s ease;
        }
        .panel-tidak-sesuai.show { display: block; }
        @keyframes fadeIn {
            from { opacity:0; transform:translateY(6px); }
            to   { opacity:1; transform:translateY(0); }
        }
        .panel-title {
            font-size: 11px; font-weight: 600; color: var(--amber);
            text-transform: uppercase; letter-spacing: 0.8px;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 7px;
        }

        /* TIPE PENUKARAN */
        .tipe-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
        .tipe-option { display: none; }
        .tipe-label {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px; border-radius: var(--radius-md);
            border: 1.5px solid var(--navy-line);
            background: var(--navy-card); cursor: pointer; transition: all 0.2s;
        }
        .tipe-label:hover { border-color: rgba(255,255,255,0.15); }
        .tipe-option:checked + .tipe-label { border-color: var(--amber); background: var(--amber-dim); }
        .tipe-dot {
            width: 14px; height: 14px; border-radius: 50%;
            border: 2px solid var(--text-muted); flex-shrink: 0; transition: all 0.2s;
        }
        .tipe-option:checked + .tipe-label .tipe-dot {
            border-color: var(--amber); background: var(--amber);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.2);
        }
        .tipe-text { font-size: 12px; font-weight: 600; color: var(--text-primary); }
        .tipe-sub  { font-size: 11px; color: var(--text-muted); }

        /* FORM FIELDS */
        .form-row { margin-bottom: 14px; }
        .form-label {
            display: block; font-size: 11px; font-weight: 600;
            color: var(--text-muted); text-transform: uppercase;
            letter-spacing: 0.6px; margin-bottom: 7px;
        }
        .form-control {
            width: 100%; padding: 10px 14px;
            background: var(--navy-card); border: 1px solid var(--navy-line);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-family: var(--font-main); font-size: 13px;
            transition: border-color 0.2s; appearance: none;
        }
        .form-control:focus {
            outline: none; border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,158,255,0.12);
        }
        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a90a8' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center;
            padding-right: 36px;
        }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* INFO BOX WAKTU */
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

        /* SUBMIT */
        .btn-submit {
            width: 100%; padding: 14px;
            background: var(--green); border: none; border-radius: var(--radius-lg);
            color: white; font-family: var(--font-main); font-size: 15px; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            letter-spacing: 0.3px;
        }
        .btn-submit:hover   { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,197,94,0.3); }
        .btn-submit:active  { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

        /* SUCCESS STATE */
        .success-state { display: none; text-align: center; padding: 48px 24px; }
        .success-state.show { display: block; }
        .success-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: var(--green-dim); border: 2px solid var(--green);
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; color: var(--green);
            margin: 0 auto 20px;
            animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes popIn {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        .success-title { font-size: 20px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
        .success-sub   { font-size: 13px; color: var(--text-secondary); }
        .success-redirect { font-size: 12px; color: var(--text-muted); margin-top: 20px; }

        /* DIVIDER */
        .divider { height: 1px; background: var(--navy-line); margin: 18px 0; }

        /* RESPONSIVE */
        @media (max-width: 600px) {
            .shift-grid  { grid-template-columns: 1fr 1fr; }
            .status-grid { grid-template-columns: 1fr; }
            .tipe-grid   { grid-template-columns: 1fr; }
            .form-grid   { grid-template-columns: 1fr; }
            .navbar      { padding: 0 16px; }
            .main        { padding: 20px 14px 60px; }
        }
        @media (max-width: 380px) {
            .shift-grid  { grid-template-columns: 1fr; }
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
    <div class="navbar-right">
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</nav>

<!-- MAIN -->
<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-icon"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <div class="page-title">Absen Masuk</div>
            <div class="page-sub">
                <?= date('l, d F Y') ?> &mdash;
                Halo, <strong><?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?></strong>
            </div>
        </div>
    </div>

    <!-- ALERT PESAN (error / warning) -->
    <?php if (!empty($pesan) && $tipe_pesan !== 'success'): ?>
    <div class="alert alert-<?= $tipe_pesan ?>">
        <i class="fas fa-<?= $tipe_pesan === 'error' ? 'times-circle' : 'exclamation-triangle' ?>"></i>
        <div class="alert-text">
            <strong><?= $tipe_pesan === 'error' ? 'Gagal' : 'Perhatian' ?></strong>
            <?= htmlspecialchars($pesan) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════
         STATE: SUKSES
    ═════════════════════════════════════════════ -->
    <?php if ($tipe_pesan === 'success'): ?>
    <div class="card">
        <div class="success-state show">
            <div class="success-circle"><i class="fas fa-check"></i></div>
            <div class="success-title">Absen Masuk Berhasil!</div>
            <div class="success-sub"><?= htmlspecialchars($pesan) ?></div>
            <div style="font-family:var(--font-mono); font-size:22px; color:var(--green); margin-top:10px;"><?= date('H:i:s') ?></div>
            <div class="success-redirect">
                <i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>
                Kembali ke dashboard dalam <span id="countdown">3</span> detik&hellip;
            </div>
        </div>
    </div>

    <?php else: ?>

    <!-- ════════════════════════════════════════════
         STATE: FORM
    ═════════════════════════════════════════════ -->

    <!-- INFO WAKTU -->
    <div class="info-box">
        <div class="info-box-icon"><i class="fas fa-clock"></i></div>
        <div>
            <div class="info-box-label">Waktu Sekarang</div>
            <div class="info-box-val" id="live-clock"><?= date('H:i:s') ?></div>
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div class="info-box-label">Tanggal</div>
            <div style="font-size:13px; font-weight:600; color:var(--text-secondary);">
                <?= date('d/m/Y') ?>
            </div>
        </div>
    </div>

    <form method="POST" id="form-absen">

        <!-- ──────────────────────────────────────
             STEP 1: PILIH SHIFT
        ─────────────────────────────────────── -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-layer-group"></i>
                Langkah 1 &mdash; Pilih Shift
            </div>

            <div class="shift-grid">
                <?php foreach ($semua_shift as $shift):
                    $nama_lower = strtolower($shift['nama_shift']);
                    if (str_contains($nama_lower, 'pagi'))      { $icon = 'fa-sun';        $kls = 'pagi'; }
                    elseif (str_contains($nama_lower, 'sore'))  { $icon = 'fa-cloud-sun';  $kls = 'sore'; }
                    elseif (str_contains($nama_lower, 'malam')) { $icon = 'fa-moon';       $kls = 'malam'; }
                    else                                        { $icon = 'fa-calendar-day'; $kls = 'lain'; }

                    $is_aktif = $shift_aktif && ($shift_aktif['id'] == $shift['id']);
                    $sudah    = sudahAbsenMasuk($user_id, $shift['id'], $tanggal);
                    $checked  = ($is_aktif && !$sudah) ? 'checked' : '';
                ?>
                <div>
                    <input
                        type="radio"
                        name="shift_id"
                        id="shift_<?= $shift['id'] ?>"
                        value="<?= $shift['id'] ?>"
                        class="shift-option"
                        data-jam-masuk="<?= htmlspecialchars($shift['jam_masuk']) ?>"
                        data-jam-keluar="<?= htmlspecialchars($shift['jam_keluar']) ?>"
                        data-nama="<?= htmlspecialchars($shift['nama_shift']) ?>"
                        <?= $checked ?>
                        <?= $sudah ? 'disabled' : '' ?>
                    >
                    <label
                        for="shift_<?= $shift['id'] ?>"
                        class="shift-label"
                        style="<?= $sudah ? 'opacity:.4;cursor:not-allowed;' : '' ?>"
                    >
                        <span class="shift-check"><i class="fas fa-check"></i></span>
                        <div class="shift-icon <?= $kls ?>">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div class="shift-nama"><?= htmlspecialchars($shift['nama_shift']) ?></div>
                        <div class="shift-jam">
                            <?= substr($shift['jam_masuk'],0,5) ?>&ndash;<?= substr($shift['jam_keluar'],0,5) ?>
                        </div>
                        <?php if ($is_aktif && !$sudah): ?>
                            <span class="badge-aktif">&#9679; AKTIF</span>
                        <?php endif; ?>
                        <?php if ($sudah): ?>
                            <span class="badge-sudah">&#10003; SUDAH</span>
                        <?php endif; ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Info keterlambatan -->
            <div class="info-telat" id="info-telat">
                <i class="fas fa-exclamation-triangle"></i>
                <div id="info-telat-text"></div>
            </div>
        </div>

        <!-- ──────────────────────────────────────
             STEP 2: STATUS MASUK
        ─────────────────────────────────────── -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-clipboard-check"></i>
                Langkah 2 &mdash; Status Masuk
            </div>

            <div class="status-grid">
                <!-- Sesuai Jadwal -->
                <div>
                    <input type="radio" name="status_masuk" id="status_sesuai"
                           value="sesuai_jadwal" class="status-option" checked>
                    <label for="status_sesuai" class="status-label">
                        <div class="status-icon s-green"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div class="status-text-lbl">Sesuai Jadwal</div>
                            <div class="status-text-sub">Shift normal sesuai jadwal saya</div>
                        </div>
                    </label>
                </div>
                <!-- Tidak Sesuai -->
                <div>
                    <input type="radio" name="status_masuk" id="status_tidak"
                           value="tidak_sesuai" class="status-option">
                    <label for="status_tidak" class="status-label">
                        <div class="status-icon s-amber"><i class="fas fa-random"></i></div>
                        <div>
                            <div class="status-text-lbl">Tidak Sesuai</div>
                            <div class="status-text-sub">Penukaran / penggantian shift</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Panel data penukaran -->
            <div class="panel-tidak-sesuai" id="panel-tidak-sesuai">
                <div class="panel-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Data Penukaran Shift &mdash; Wajib Diisi
                </div>

                <!-- Tipe -->
                <div class="form-row">
                    <label class="form-label">Tipe Penukaran</label>
                    <div class="tipe-grid">
                        <div>
                            <input type="radio" name="tipe_penukaran" id="tipe_penukar"
                                   value="penukar" class="tipe-option">
                            <label for="tipe_penukar" class="tipe-label">
                                <div class="tipe-dot"></div>
                                <div>
                                    <div class="tipe-text">Penukar</div>
                                    <div class="tipe-sub">Saya menggantikan orang lain</div>
                                </div>
                            </label>
                        </div>
                        <div>
                            <input type="radio" name="tipe_penukaran" id="tipe_pengganti"
                                   value="pengganti" class="tipe-option">
                            <label for="tipe_pengganti" class="tipe-label">
                                <div class="tipe-dot"></div>
                                <div>
                                    <div class="tipe-text">Pengganti</div>
                                    <div class="tipe-sub">Saya digantikan orang lain</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Nama pamdal pengganti -->
                <div class="form-row">
                    <label class="form-label" for="user_pengganti_id">
                        <i class="fas fa-user" style="margin-right:4px;"></i>
                        Nama Pamdal Pengganti / Penukar
                    </label>
                    <select name="user_pengganti_id" id="user_pengganti_id" class="form-control">
                        <option value="">— Pilih nama pamdal —</option>
                        <?php foreach ($semua_pamdal as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Pamdal yang menjadi penukar/pengganti shift ini</div>
                </div>

                <div class="form-grid">
                    <!-- Tanggal Penukaran -->
                    <div class="form-row">
                        <label class="form-label" for="tanggal_penukaran">
                            <i class="fas fa-calendar" style="margin-right:4px;"></i>
                            Tanggal Penukaran
                        </label>
                        <input type="date" name="tanggal_penukaran" id="tanggal_penukaran"
                               class="form-control"
                               value="<?= date('Y-m-d') ?>"
                               min="<?= date('Y-m-d', strtotime('-30 days')) ?>"
                               max="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                        <div class="form-hint">Tanggal kesepakatan penukaran</div>
                    </div>

                    <!-- Shift Penukaran -->
                    <div class="form-row">
                        <label class="form-label" for="shift_penukaran_id">
                            <i class="fas fa-layer-group" style="margin-right:4px;"></i>
                            Shift Penukaran
                        </label>
                        <select name="shift_penukaran_id" id="shift_penukaran_id" class="form-control">
                            <option value="">— Pilih shift —</option>
                            <?php foreach ($semua_shift as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= htmlspecialchars($s['nama_shift']) ?>
                                (<?= substr($s['jam_masuk'],0,5) ?>–<?= substr($s['jam_keluar'],0,5) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Shift yang ditukarkan</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUBMIT -->
        <button type="submit" class="btn-submit" id="btn-submit">
            <i class="fas fa-sign-in-alt"></i>
            <span>Konfirmasi Absen Masuk</span>
        </button>

        <p style="text-align:center; font-size:12px; color:var(--text-muted); margin-top:14px;">
            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
            Toleransi keterlambatan maksimal
            <strong style="color:var(--amber);">15 menit</strong> dari jam masuk shift.
        </p>

    </form>
    <?php endif; ?>

</div><!-- /.main -->

<script>
/* ── Live Clock ──────────────────────────────────────────── */
(function tickClock() {
    const el = document.getElementById('live-clock');
    if (el) {
        const n = new Date(), p = n => String(n).padStart(2,'0');
        el.textContent = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
    }
    setTimeout(tickClock, 1000);
})();

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

/* ── Cek Keterlambatan ───────────────────────────────────── */
function cekTelat() {
    const checked = document.querySelector('.shift-option:checked');
    const box     = document.getElementById('info-telat');
    const txt     = document.getElementById('info-telat-text');
    const btn     = document.getElementById('btn-submit');
    if (!box) return;

    if (!checked) { box.classList.remove('show'); return; }

    const [jh, jm] = (checked.dataset.jamMasuk || '').split(':').map(Number);
    if (isNaN(jh)) { box.classList.remove('show'); return; }

    const now    = new Date();
    const jadwal = new Date(now.getFullYear(), now.getMonth(), now.getDate(), jh, jm, 0);
    const menit  = Math.floor((now - jadwal) / 60000);

    if (menit <= 0) {
        // Belum waktunya atau tepat waktu
        box.classList.remove('show');
        if (btn) btn.disabled = false;
    } else if (menit <= 15) {
        box.classList.add('show');
        Object.assign(box.style, { background:'var(--amber-dim)', borderColor:'rgba(245,158,11,0.3)', color:'var(--amber)' });
        txt.innerHTML = `Anda <strong>terlambat ${menit} menit</strong>. Masih dalam toleransi 15 menit — absen tetap bisa dilakukan.`;
        if (btn) btn.disabled = false;
    } else {
        box.classList.add('show');
        Object.assign(box.style, { background:'var(--red-dim)', borderColor:'rgba(244,63,94,0.3)', color:'var(--red)' });
        txt.innerHTML = `Anda <strong>terlambat ${menit} menit</strong>. Melebihi toleransi 15 menit — absen masuk <strong>tidak dapat dilakukan</strong>.`;
        if (btn) btn.disabled = true;
    }
}

document.querySelectorAll('.shift-option').forEach(el => el.addEventListener('change', cekTelat));
window.addEventListener('load', cekTelat);
setInterval(cekTelat, 30000); // cek ulang tiap 30 detik

/* ── Toggle Panel Tidak Sesuai ──────────────────────────── */
function togglePanel() {
    const val   = document.querySelector('input[name="status_masuk"]:checked')?.value;
    const panel = document.getElementById('panel-tidak-sesuai');
    if (!panel) return;
    const aktif = val === 'tidak_sesuai';
    panel.classList.toggle('show', aktif);

    // Set required secara dinamis
    const ids = ['user_pengganti_id','tanggal_penukaran','shift_penukaran_id'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = aktif;
    });
    const tipeRadio = document.querySelector('input[name="tipe_penukaran"]');
    if (tipeRadio) tipeRadio.required = aktif;
}
document.querySelectorAll('input[name="status_masuk"]').forEach(r => r.addEventListener('change', togglePanel));
window.addEventListener('load', togglePanel);

/* ── Konfirmasi Submit ───────────────────────────────────── */
const formAbsen = document.getElementById('form-absen');
if (formAbsen) {
    formAbsen.addEventListener('submit', function(e) {
        const shiftEl = document.querySelector('.shift-option:checked');
        if (!shiftEl) {
            e.preventDefault();
            alert('Harap pilih shift terlebih dahulu!');
            return;
        }
        const shiftNama  = shiftEl.dataset.nama || 'shift ini';
        const statusVal  = document.querySelector('input[name="status_masuk"]:checked')?.value;
        const statusTeks = statusVal === 'tidak_sesuai' ? 'Tidak Sesuai Jadwal (Penukaran)' : 'Sesuai Jadwal';
        const clock      = document.getElementById('live-clock')?.textContent || '';

        const ok = confirm(
            'Konfirmasi Absen Masuk\n\n' +
            'Shift   : ' + shiftNama + '\n' +
            'Status  : ' + statusTeks + '\n' +
            'Waktu   : ' + clock + '\n\n' +
            'Pastikan data sudah benar sebelum melanjutkan.'
        );
        if (!ok) e.preventDefault();
    });
}
</script>
</body>
</html>