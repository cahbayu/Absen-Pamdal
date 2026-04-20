<?php
// laporan_bulanan.php – Laporan Bulanan Per Pamdal
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

global $conn;

// Parameter
$bulan_param  = isset($_GET['bulan'])   ? (int)$_GET['bulan']   : (int)date('m');
$tahun_param  = isset($_GET['tahun'])   ? (int)$_GET['tahun']   : (int)date('Y');
$user_id_param = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($bulan_param < 1 || $bulan_param > 12) $bulan_param = (int)date('m');
if ($tahun_param < 2020 || $tahun_param > 2099) $tahun_param = (int)date('Y');

$tgl_dari   = sprintf('%04d-%02d-01', $tahun_param, $bulan_param);
$tgl_sampai = date('Y-m-t', strtotime($tgl_dari));

$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];
$label_periode = $nama_bulan[$bulan_param] . ' ' . $tahun_param;

// Daftar semua pamdal
$pamdal_list = getAllPamdal();

// Jika ada user_id, filter ke user itu saja
$pamdal_tampil = $user_id_param
    ? array_filter($pamdal_list, fn($p) => (int)$p['id'] === $user_id_param)
    : $pamdal_list;
$pamdal_tampil = array_values($pamdal_tampil);

// ── Fungsi ambil data laporan bulanan per user ─────────────
function getLaporanBulananUser($user_id, $dari, $sampai) {
    global $conn;
    $user_id = (int)$user_id;
    $dari    = $conn->real_escape_string($dari);
    $sampai  = $conn->real_escape_string($sampai);

    $sql = "SELECT a.*, s.nama_shift, s.jam_masuk AS s_masuk, s.jam_keluar AS s_keluar,
                   l.id AS laporan_id, l.isi_laporan, l.status AS status_laporan,
                   l.catatan_revisi, l.created_at AS laporan_tgl
            FROM absensi a
            JOIN shift s ON a.shift_id = s.id
            LEFT JOIN laporan l ON l.absensi_id = a.id
            WHERE a.user_id = $user_id AND a.tanggal BETWEEN '$dari' AND '$sampai'
            ORDER BY a.tanggal ASC, a.jam_masuk ASC";
    $result = $conn->query($sql);
    $rows = [];
    while ($r = $result->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function getRingkasanUser($rows) {
    $r = [
        'hadir' => 0, 'terlambat' => 0, 'pulang_awal' => 0,
        'lanjut_shift' => 0, 'tukar_shift' => 0,
        'lap_acc' => 0, 'lap_pending' => 0, 'lap_revisi' => 0, 'lap_kosong' => 0,
        'total_menit' => 0,
    ];
    foreach ($rows as $row) {
        $r['hadir']++;
        if ($row['keterangan_masuk'] === 'terlambat') $r['terlambat']++;
        if ($row['status_masuk'] === 'tidak_sesuai')  $r['tukar_shift']++;
        if ($row['status_keluar'] === 'pulang_awal')  $r['pulang_awal']++;
        if ($row['status_keluar'] === 'lanjut_shift') $r['lanjut_shift']++;
        if (!empty($row['jam_masuk']) && !empty($row['jam_keluar'])) {
            $r['total_menit'] += (int)((strtotime($row['jam_keluar']) - strtotime($row['jam_masuk'])) / 60);
        }
        $sl = $row['status_laporan'] ?? '';
        if ($sl === 'acc')     $r['lap_acc']++;
        elseif ($sl === 'pending') $r['lap_pending']++;
        elseif ($sl === 'revisi')  $r['lap_revisi']++;
        else                       $r['lap_kosong']++;
    }
    return $r;
}

// Hitung ringkasan global
$global = ['hadir'=>0,'terlambat'=>0,'pulang_awal'=>0,'lap_acc'=>0,'lap_pending'=>0,'lap_revisi'=>0];
$data_per_user = [];
foreach ($pamdal_tampil as $p) {
    $rows = getLaporanBulananUser($p['id'], $tgl_dari, $tgl_sampai);
    $ring = getRingkasanUser($rows);
    $data_per_user[$p['id']] = ['pamdal' => $p, 'rows' => $rows, 'ring' => $ring];
    $global['hadir']       += $ring['hadir'];
    $global['terlambat']   += $ring['terlambat'];
    $global['pulang_awal'] += $ring['pulang_awal'];
    $global['lap_acc']     += $ring['lap_acc'];
    $global['lap_pending'] += $ring['lap_pending'];
    $global['lap_revisi']  += $ring['lap_revisi'];
}

// Tahun pilihan (5 tahun ke belakang)
$tahun_options = range(date('Y'), date('Y') - 5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Laporan Bulanan — ANDALAN</title>
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
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); text-decoration: none; }
        .brand-icon { width: 32px; height: 32px; background: var(--gold); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0f1b2d; }
        .navbar-right { display: flex; gap: 10px; align-items: center; }
        .btn-nav { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all 0.2s; }
        .btn-nav:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }

        .main { max-width: 1160px; margin: 0 auto; padding: 32px 20px 60px; }

        /* PAGE HEADER */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 22px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--teal); }
        .page-subtitle { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }
        .periode-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; padding: 6px 16px; border-radius: 20px; background: var(--teal-dim); color: var(--teal); border: 1px solid rgba(45,212,191,0.3); }

        /* FILTER */
        .filter-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 18px 22px; margin-bottom: 24px; }
        .filter-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 11px; font-weight: 500; color: var(--text-secondary); }
        .fsel { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; padding: 7px 12px; font-family: var(--font-main); outline: none; min-width: 140px; transition: border-color 0.2s; }
        .fsel:focus { border-color: var(--accent); }
        .fsel option { background: var(--navy-card); }
        .btn-filter { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 7px 20px; border-radius: var(--radius-sm); background: var(--accent); border: none; color: #fff; cursor: pointer; transition: all 0.2s; }
        .btn-filter:hover { background: #2280d4; }

        /* GLOBAL STATS */
        .global-grid { display: grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: 10px; margin-bottom: 28px; }
        .gstat { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 14px 16px; text-align: center; }
        .gstat-num { font-family: var(--font-mono); font-size: 22px; font-weight: 500; color: var(--text-primary); }
        .gstat-lbl { font-size: 10px; color: var(--text-secondary); margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .gstat.c-blue  .gstat-num { color: var(--accent); }
        .gstat.c-green .gstat-num { color: var(--green); }
        .gstat.c-amber .gstat-num { color: var(--amber); }
        .gstat.c-red   .gstat-num { color: var(--red); }
        .gstat.c-teal  .gstat-num { color: var(--teal); }
        .gstat.c-purple .gstat-num { color: var(--purple); }

        /* SECTION */
        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; margin-top: 28px; }

        /* USER REPORT CARD */
        .user-report { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 20px; }
        .ur-header { padding: 18px 22px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; gap: 16px; cursor: pointer; user-select: none; transition: background 0.15s; }
        .ur-header:hover { background: var(--navy-hover); }
        .ur-avatar { width: 42px; height: 42px; border-radius: 50%; background: var(--teal-dim); border: 1.5px solid rgba(45,212,191,0.35); display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 600; color: var(--teal); flex-shrink: 0; }
        .ur-info { flex: 1; min-width: 0; }
        .ur-name { font-size: 15px; font-weight: 600; color: var(--text-primary); }
        .ur-sub  { font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 3px; }
        .ur-stats { display: flex; gap: 16px; flex-wrap: wrap; }
        .ur-stat { text-align: center; }
        .ur-stat-num { font-family: var(--font-mono); font-size: 16px; font-weight: 500; }
        .ur-stat-lbl { font-size: 10px; color: var(--text-muted); margin-top: 1px; }
        .c-blue   { color: var(--accent); }
        .c-amber  { color: var(--amber); }
        .c-red    { color: var(--red); }
        .c-green  { color: var(--green); }
        .c-teal   { color: var(--teal); }
        .c-purple { color: var(--purple); }
        .c-muted  { color: var(--text-muted); }
        .ur-toggle { color: var(--text-muted); font-size: 14px; flex-shrink: 0; transition: transform 0.25s; }
        .ur-toggle.open { transform: rotate(180deg); }

        /* DETAIL TABLE */
        .ur-body { display: none; }
        .ur-body.open { display: block; }
        .summary-pills { display: flex; flex-wrap: wrap; gap: 8px; padding: 14px 22px; border-bottom: 1px solid var(--navy-line); background: rgba(255,255,255,0.01); }
        .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 4px 12px; border-radius: 20px; }
        .pill-blue   { background: var(--accent-dim); color: var(--accent);  border: 1px solid rgba(59,158,255,0.2); }
        .pill-amber  { background: var(--amber-dim);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.2); }
        .pill-red    { background: var(--red-dim);    color: var(--red);     border: 1px solid rgba(244,63,94,0.2); }
        .pill-green  { background: var(--green-dim);  color: var(--green);   border: 1px solid rgba(34,197,94,0.2); }
        .pill-purple { background: var(--purple-dim); color: var(--purple);  border: 1px solid rgba(167,139,250,0.2); }
        .pill-teal   { background: var(--teal-dim);   color: var(--teal);    border: 1px solid rgba(45,212,191,0.2); }
        .pill-muted  { background: rgba(77,98,120,0.15); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.25); }

        .tbl-wrap { overflow-x: auto; }
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 820px; }
        .tbl th { padding: 9px 16px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); white-space: nowrap; }
        .tbl td { padding: 11px 16px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .mono { font-family: var(--font-mono); font-size: 12px; }

        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; }
        .b-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .b-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .b-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .b-teal   { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .b-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        .btn-detail { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid rgba(59,158,255,0.3); background: var(--accent-dim); color: var(--accent); text-decoration: none; transition: all 0.18s; }
        .btn-detail:hover { background: var(--accent); color: #fff; }

        /* LAPORAN EXCERPT */
        .laporan-text { font-size: 11px; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .catatan-revisi { font-size: 11px; color: var(--red); font-style: italic; margin-top: 3px; }

        /* KOSONG */
        .no-data { padding: 30px 20px; text-align: center; color: var(--text-muted); font-size: 13px; border-top: 1px solid var(--navy-line); }

        /* PRINT */
        .btn-print { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 7px 16px; border-radius: var(--radius-sm); background: var(--teal-dim); border: 1px solid rgba(45,212,191,0.3); color: var(--teal); cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .btn-print:hover { background: var(--teal); color: #0f1b2d; }

        @media print {
            .navbar, .filter-card, .btn-print, .ur-toggle, .ur-header { display: block !important; cursor: default; }
            .ur-body { display: block !important; }
            .btn-detail { display: none; }
            body { background: #fff; color: #000; }
        }

        @media (max-width: 900px) {
            .global-grid { grid-template-columns: repeat(3, 1fr); }
            .navbar { padding: 0 16px; }
            .main { padding: 20px 14px 50px; }
            .ur-stats { display: none; }
        }
        @media (max-width: 480px) {
            .global-grid { grid-template-columns: repeat(2, 1fr); }
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
        <a href="data_pamdal.php" class="btn-nav"><i class="fas fa-user-shield"></i> Data Pamdal</a>
        <a href="index.php" class="btn-nav"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-chart-bar"></i> Laporan Bulanan</div>
            <div class="page-subtitle">
                Rekap kinerja personil keamanan
                <?php if ($user_id_param && count($pamdal_tampil) === 1): ?>
                    · <strong style="color:var(--text-primary);"><?= htmlspecialchars($pamdal_tampil[0]['name']) ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="periode-chip"><i class="fas fa-calendar-alt"></i> <?= $label_periode ?></span>
            <a href="javascript:window.print()" class="btn-print"><i class="fas fa-print"></i> Cetak</a>
        </div>
    </div>

    <!-- FILTER -->
    <div class="filter-card">
        <div class="filter-title"><i class="fas fa-sliders-h" style="margin-right:5px;"></i> Filter Laporan</div>
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Bulan</label>
                    <select name="bulan" class="fsel">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $bulan_param ? 'selected' : '' ?>>
                            <?= $nama_bulan[$m] ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tahun</label>
                    <select name="tahun" class="fsel">
                        <?php foreach ($tahun_options as $y): ?>
                        <option value="<?= $y ?>" <?= $y === $tahun_param ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Pamdal</label>
                    <select name="user_id" class="fsel">
                        <option value="0" <?= !$user_id_param ? 'selected' : '' ?>>Semua Pamdal</option>
                        <?php foreach ($pamdal_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $user_id_param === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Tampilkan</button>
            </div>
        </form>
    </div>

    <!-- GLOBAL STATS -->
    <div class="global-grid">
        <div class="gstat c-blue">
            <div class="gstat-num"><?= $global['hadir'] ?></div>
            <div class="gstat-lbl">Total Hadir</div>
        </div>
        <div class="gstat c-amber">
            <div class="gstat-num"><?= $global['terlambat'] ?></div>
            <div class="gstat-lbl">Terlambat</div>
        </div>
        <div class="gstat c-red">
            <div class="gstat-num"><?= $global['pulang_awal'] ?></div>
            <div class="gstat-lbl">Pulang Awal</div>
        </div>
        <div class="gstat c-green">
            <div class="gstat-num"><?= $global['lap_acc'] ?></div>
            <div class="gstat-lbl">Laporan ACC</div>
        </div>
        <div class="gstat c-amber">
            <div class="gstat-num"><?= $global['lap_pending'] ?></div>
            <div class="gstat-lbl">Pending</div>
        </div>
        <div class="gstat c-red">
            <div class="gstat-num"><?= $global['lap_revisi'] ?></div>
            <div class="gstat-lbl">Revisi</div>
        </div>
    </div>

    <!-- LAPORAN PER USER -->
    <div class="section-title">Laporan Per Personil — <?= $label_periode ?></div>

    <?php if (empty($data_per_user)): ?>
        <div style="background:var(--navy-card);border:1px solid var(--navy-line);border-radius:var(--radius-xl);padding:40px 20px;text-align:center;color:var(--text-muted);">
            <i class="fas fa-calendar-times" style="font-size:28px;display:block;margin-bottom:10px;opacity:0.4;"></i>
            Tidak ada data untuk periode ini.
        </div>
    <?php endif; ?>

    <?php foreach ($data_per_user as $uid => $du):
        $p    = $du['pamdal'];
        $rows = $du['rows'];
        $ring = $du['ring'];
        $jam_total = $ring['total_menit'] > 0
            ? floor($ring['total_menit']/60) . 'j ' . ($ring['total_menit']%60) . 'm'
            : '—';
        $is_clean = $ring['terlambat'] === 0 && $ring['pulang_awal'] === 0 && $ring['lap_revisi'] === 0;
    ?>
    <div class="user-report">
        <div class="ur-header" onclick="toggleAccordion(<?= $uid ?>)">
            <div class="ur-avatar"><?= strtoupper(substr($p['name'],0,2)) ?></div>
            <div class="ur-info">
                <div class="ur-name">
                    <?= htmlspecialchars($p['name']) ?>
                    <?php if ($is_clean && $ring['hadir'] > 0): ?>
                        <span class="badge b-green" style="margin-left:6px;font-size:10px;">
                            <i class="fas fa-star" style="font-size:9px;"></i> Kinerja Baik
                        </span>
                    <?php endif; ?>
                </div>
                <div class="ur-sub">@<?= htmlspecialchars($p['username']) ?> · <?= $ring['hadir'] ?> hari hadir</div>
            </div>
            <div class="ur-stats">
                <div class="ur-stat">
                    <div class="ur-stat-num c-blue"><?= $ring['hadir'] ?></div>
                    <div class="ur-stat-lbl">Hadir</div>
                </div>
                <div class="ur-stat">
                    <div class="ur-stat-num <?= $ring['terlambat'] > 0 ? 'c-amber' : 'c-muted' ?>"><?= $ring['terlambat'] ?></div>
                    <div class="ur-stat-lbl">Terlambat</div>
                </div>
                <div class="ur-stat">
                    <div class="ur-stat-num <?= $ring['pulang_awal'] > 0 ? 'c-red' : 'c-muted' ?>"><?= $ring['pulang_awal'] ?></div>
                    <div class="ur-stat-lbl">Pulang Awal</div>
                </div>
                <div class="ur-stat">
                    <div class="ur-stat-num <?= $ring['lap_acc'] > 0 ? 'c-green' : 'c-muted' ?>"><?= $ring['lap_acc'] ?></div>
                    <div class="ur-stat-lbl">Lap. ACC</div>
                </div>
                <div class="ur-stat">
                    <div class="ur-stat-num <?= $ring['lap_pending'] > 0 ? 'c-amber' : 'c-muted' ?>"><?= $ring['lap_pending'] ?></div>
                    <div class="ur-stat-lbl">Pending</div>
                </div>
                <div class="ur-stat">
                    <div class="ur-stat-num <?= $ring['lap_revisi'] > 0 ? 'c-red' : 'c-muted' ?>"><?= $ring['lap_revisi'] ?></div>
                    <div class="ur-stat-lbl">Revisi</div>
                </div>
                <div class="ur-stat">
                    <div class="ur-stat-num c-teal" style="font-size:13px;"><?= $jam_total ?></div>
                    <div class="ur-stat-lbl">Total Jam</div>
                </div>
            </div>
            <i class="fas fa-chevron-down ur-toggle" id="toggle-icon-<?= $uid ?>"></i>
        </div>

        <div class="ur-body" id="ur-body-<?= $uid ?>">
            <!-- SUMMARY PILLS -->
            <div class="summary-pills">
                <span class="pill pill-blue"><i class="fas fa-calendar-check"></i> <?= $ring['hadir'] ?> Hadir</span>
                <?php if ($ring['terlambat'] > 0): ?>
                <span class="pill pill-amber"><i class="fas fa-clock"></i> <?= $ring['terlambat'] ?> Terlambat</span>
                <?php endif; ?>
                <?php if ($ring['pulang_awal'] > 0): ?>
                <span class="pill pill-red"><i class="fas fa-person-walking-arrow-right"></i> <?= $ring['pulang_awal'] ?> Pulang Awal</span>
                <?php endif; ?>
                <?php if ($ring['lanjut_shift'] > 0): ?>
                <span class="pill pill-purple"><i class="fas fa-rotate"></i> <?= $ring['lanjut_shift'] ?> Lanjut Shift</span>
                <?php endif; ?>
                <?php if ($ring['tukar_shift'] > 0): ?>
                <span class="pill pill-teal"><i class="fas fa-exchange-alt"></i> <?= $ring['tukar_shift'] ?> Tukar Shift</span>
                <?php endif; ?>
                <?php if ($ring['lap_acc'] > 0): ?>
                <span class="pill pill-green"><i class="fas fa-check-double"></i> <?= $ring['lap_acc'] ?> Lap. ACC</span>
                <?php endif; ?>
                <?php if ($ring['lap_pending'] > 0): ?>
                <span class="pill pill-amber"><i class="fas fa-file-circle-xmark"></i> <?= $ring['lap_pending'] ?> Pending</span>
                <?php endif; ?>
                <?php if ($ring['lap_revisi'] > 0): ?>
                <span class="pill pill-red"><i class="fas fa-file-circle-exclamation"></i> <?= $ring['lap_revisi'] ?> Revisi</span>
                <?php endif; ?>
                <?php if ($ring['lap_kosong'] > 0): ?>
                <span class="pill pill-muted"><i class="fas fa-file-slash"></i> <?= $ring['lap_kosong'] ?> Tanpa Laporan</span>
                <?php endif; ?>
                <?php if ($ring['total_menit'] > 0): ?>
                <span class="pill pill-teal"><i class="fas fa-stopwatch"></i> Total <?= $jam_total ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($rows)): ?>
                <div class="no-data">
                    <i class="fas fa-calendar-xmark" style="font-size:20px;display:block;margin-bottom:6px;opacity:0.4;"></i>
                    Tidak ada data absensi pada periode ini.
                </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Shift</th>
                            <th>Masuk</th>
                            <th>Status Masuk</th>
                            <th>Keluar</th>
                            <th>Status Keluar</th>
                            <th>Durasi</th>
                            <th>Laporan</th>
                            <th>Isi Laporan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $i => $row):
                        $dur = '';
                        if (!empty($row['jam_masuk']) && !empty($row['jam_keluar'])) {
                            $m = (int)((strtotime($row['jam_keluar']) - strtotime($row['jam_masuk'])) / 60);
                            $dur = floor($m/60).'j '.($m%60).'m';
                        }
                    ?>
                        <tr>
                            <td class="mono" style="color:var(--text-muted);"><?= $i+1 ?></td>
                            <td class="mono"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td><span class="badge b-blue"><?= htmlspecialchars($row['nama_shift']) ?></span></td>
                            <td class="mono"><?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '—' ?></td>
                            <td>
                                <?php $km = $row['keterangan_masuk'] ?? 'normal'; $sm = $row['status_masuk'] ?? ''; ?>
                                <?php if ($sm === 'tidak_sesuai'): ?><span class="badge b-teal">Tukar</span>
                                <?php elseif ($km === 'terlambat'): ?><span class="badge b-amber">Terlambat</span>
                                <?php else: ?><span class="badge b-green">Tepat</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : '—' ?></td>
                            <td>
                                <?php $sk = $row['status_keluar'] ?? ''; ?>
                                <?php if ($sk === 'tepat_waktu'):  ?><span class="badge b-green">Tepat</span>
                                <?php elseif ($sk === 'pulang_awal'):  ?><span class="badge b-red">Pulang Awal</span>
                                <?php elseif ($sk === 'lanjut_shift'): ?><span class="badge b-purple">Lanjut</span>
                                <?php else: ?><span class="badge b-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono" style="color:var(--teal);"><?= $dur ?: '—' ?></td>
                            <td>
                                <?php $sl = $row['status_laporan'] ?? ''; ?>
                                <?php if ($sl === 'acc'):     ?><span class="badge b-green"><i class="fas fa-check-double" style="font-size:9px;"></i> ACC</span>
                                <?php elseif ($sl === 'pending'): ?><span class="badge b-amber">Pending</span>
                                <?php elseif ($sl === 'revisi'):  ?><span class="badge b-red">Revisi</span>
                                <?php else: ?><span class="badge b-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['isi_laporan'])): ?>
                                    <div class="laporan-text" title="<?= htmlspecialchars($row['isi_laporan']) ?>">
                                        <?= htmlspecialchars(mb_substr($row['isi_laporan'], 0, 60)) . (mb_strlen($row['isi_laporan']) > 60 ? '…' : '') ?>
                                    </div>
                                    <?php if (!empty($row['catatan_revisi'])): ?>
                                    <div class="catatan-revisi"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(mb_substr($row['catatan_revisi'],0,50)) ?>…</div>
                                    <?php endif; ?>
                                    <?php if ($row['laporan_id']): ?>
                                    <a href="detail_laporan.php?id=<?= $row['laporan_id'] ?>" class="btn-detail" style="margin-top:4px;">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:11px;">Belum ada laporan</span>
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
    <?php endforeach; ?>

</div>

<script>
function toggleAccordion(uid) {
    const body = document.getElementById('ur-body-' + uid);
    const icon = document.getElementById('toggle-icon-' + uid);
    const isOpen = body.classList.contains('open');
    body.classList.toggle('open', !isOpen);
    icon.classList.toggle('open', !isOpen);
}

// Jika hanya 1 user, buka otomatis
<?php if (count($data_per_user) === 1): ?>
const uids = [<?= implode(',', array_keys($data_per_user)) ?>];
uids.forEach(uid => toggleAccordion(uid));
<?php endif; ?>
</script>
</body>
</html>
