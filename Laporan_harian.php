<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

$user_id_sesi = (int)$_SESSION['user_id'];

// ── Filter bulan/tahun ──
$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$bulan = max(1, min(12, $bulan));
$tahun = max(2020, min(2099, $tahun));

$dari   = sprintf('%04d-%02d-01', $tahun, $bulan);
$sampai = date('Y-m-t', strtotime($dari));

// ── Ambil riwayat absensi ──
$riwayat = getRiwayatAbsensiUser($user_id_sesi, $dari, $sampai);

// ── Handle POST: update laporan (revisi) ──
$flash_msg  = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'update_laporan') {
        $laporan_id = (int)($_POST['laporan_id'] ?? 0);
        $isi_baru   = trim($_POST['isi_laporan'] ?? '');
        $result     = updateLaporan($laporan_id, $user_id_sesi, $isi_baru);
        $flash_msg  = $result['message'];
        $flash_type = $result['success'] ? 'success' : 'error';
        $riwayat    = getRiwayatAbsensiUser($user_id_sesi, $dari, $sampai);
    }
}

// ── Helper: label bulan ──
$nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
               'Juli','Agustus','September','Oktober','November','Desember'];

// ── Helper functions ──
function statusMasukBadge($keterangan_masuk, $status_masuk) {
    if ($status_masuk === 'tidak_sesuai')
        return ['label'=>'Tukar Shift','class'=>'badge-swap','icon'=>'fa-rotate'];
    if ($keterangan_masuk === 'terlambat')
        return ['label'=>'Terlambat',  'class'=>'badge-late','icon'=>'fa-clock'];
    return    ['label'=>'Normal',      'class'=>'badge-ok',  'icon'=>'fa-check-circle'];
}
function statusKeluarBadge($status_keluar) {
    switch ($status_keluar) {
        case 'tepat_waktu':  return ['label'=>'Tepat Waktu', 'class'=>'badge-ok',   'icon'=>'fa-check-circle'];
        case 'pulang_awal':  return ['label'=>'Pulang Awal', 'class'=>'badge-early','icon'=>'fa-arrow-left'];
        case 'lanjut_shift': return ['label'=>'Lanjut Shift','class'=>'badge-shift','icon'=>'fa-rotate'];
        default:             return ['label'=>'Belum Keluar','class'=>'badge-na',   'icon'=>'fa-minus'];
    }
}
function statusLaporanInfo($status_laporan) {
    switch ($status_laporan) {
        case 'acc':     return ['label'=>'Disetujui',   'class'=>'lstat-acc',    'icon'=>'fa-check-double'];
        case 'revisi':  return ['label'=>'Perlu Revisi','class'=>'lstat-revisi', 'icon'=>'fa-pen-to-square'];
        case 'pending': return ['label'=>'Menunggu',    'class'=>'lstat-pending','icon'=>'fa-hourglass-half'];
        default:        return ['label'=>'Belum Ada',   'class'=>'lstat-none',   'icon'=>'fa-file-circle-question'];
    }
}

// ── Ringkasan ──
$total_hadir  = count($riwayat);
$total_normal = $total_telat = $total_awal = $total_tukar = $total_revisi = 0;
foreach ($riwayat as $r) {
    if ($r['keterangan_masuk']==='normal' && $r['status_masuk']==='sesuai_jadwal') $total_normal++;
    if ($r['keterangan_masuk']==='terlambat')   $total_telat++;
    if ($r['status_keluar']==='pulang_awal')     $total_awal++;
    if ($r['status_masuk']==='tidak_sesuai')     $total_tukar++;
    if ($r['status_laporan']==='revisi')         $total_revisi++;
}

// ── Navigasi bulan ──
$prev_bulan=$bulan-1; $prev_tahun=$tahun;
if($prev_bulan<1){$prev_bulan=12;$prev_tahun--;}
$next_bulan=$bulan+1; $next_tahun=$tahun;
if($next_bulan>12){$next_bulan=1;$next_tahun++;}

// ── Default export tanggal (seluruh bulan aktif) ──
$export_dari_default   = $dari;
$export_sampai_default = $sampai;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Laporan Harian — ANDALAN</title>
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
            --cyan:       #22d3ee;
            --cyan-dim:   rgba(34,211,238,0.15);
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

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 100; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; text-decoration: none; }
        .brand-icon { width: 32px; height: 32px; background: var(--accent); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: white; }
        .navbar-right { display: flex; align-items: center; gap: 12px; }
        .btn-back { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-back:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .user-avatar { width: 34px; height: 34px; background: var(--accent-dim); border: 1px solid var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--accent); font-weight: 600; }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }

        /* MAIN */
        .main { max-width: 1060px; margin: 0 auto; padding: 32px 20px 60px; }

        /* PAGE HEADER */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
        .page-title { font-size: 22px; font-weight: 700; color: var(--text-primary); }
        .page-sub   { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

        /* TOMBOL EXPORT */
        .btn-export {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600;
            padding: 10px 22px; border-radius: var(--radius-sm);
            background: linear-gradient(135deg, rgba(34,197,94,.18) 0%, rgba(34,197,94,.08) 100%);
            border: 1px solid rgba(34,197,94,.45);
            color: var(--green); cursor: pointer;
            transition: all .22s; white-space: nowrap; flex-shrink: 0;
        }
        .btn-export:hover {
            background: rgba(34,197,94,.26); border-color: var(--green);
            box-shadow: 0 0 18px rgba(34,197,94,.18);
            transform: translateY(-1px);
        }
        .btn-export i { font-size: 15px; }

        /* FLASH */
        .flash { padding: 12px 18px; border-radius: var(--radius-lg); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 13px; }
        .flash.success { background: var(--green-dim); border: 1px solid rgba(34,197,94,.3); color: var(--green); }
        .flash.error   { background: var(--red-dim);   border: 1px solid rgba(244,63,94,.3); color: var(--red); }

        /* STATS */
        .stats-row { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-pill { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 14px 16px; text-align: center; }
        .stat-pill .sp-val { font-size: 22px; font-weight: 700; font-family: var(--font-mono); }
        .stat-pill .sp-lbl { font-size: 11px; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .5px; }
        .sp-hadir  .sp-val { color: var(--accent); }
        .sp-normal .sp-val { color: var(--green);  }
        .sp-telat  .sp-val { color: var(--red);    }
        .sp-awal   .sp-val { color: var(--amber);  }
        .sp-revisi .sp-val { color: var(--purple); }

        /* NAVIGASI BULAN */
        .month-nav { display: flex; align-items: center; gap: 10px; background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 10px 16px; margin-bottom: 22px; width: fit-content; }
        .month-nav a { display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 8px; background: var(--navy-hover); border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all .2s; font-size: 13px; }
        .month-nav a:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .month-nav .month-label { font-size: 14px; font-weight: 600; color: var(--text-primary); min-width: 160px; text-align: center; }

        /* SECTION TITLE */
        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }

        /* EMPTY STATE */
        .empty-state { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 48px 24px; text-align: center; }
        .empty-state i { font-size: 36px; color: var(--text-muted); margin-bottom: 14px; display: block; }
        .empty-state p { font-size: 14px; color: var(--text-secondary); }

        /* LAPORAN CARDS */
        .laporan-list { display: flex; flex-direction: column; gap: 14px; }
        .laporan-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; transition: border-color .2s; }
        .laporan-card:hover { border-color: rgba(59,158,255,.2); }
        .laporan-card.status-ok    { border-left: 4px solid var(--green); }
        .laporan-card.status-late  { border-left: 4px solid var(--red);   }
        .laporan-card.status-early { border-left: 4px solid var(--amber); }
        .laporan-card.status-swap  { border-left: 4px solid var(--cyan);  }

        .card-main { padding: 18px 20px; display: grid; grid-template-columns: 1fr auto; gap: 16px; align-items: start; }
        .card-date-row { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
        .card-date  { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .card-shift { font-size: 11px; font-weight: 500; padding: 2px 10px; border-radius: 20px; background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,.2); }
        .card-times { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; margin-bottom: 12px; }
        .card-time-item { display: flex; align-items: center; gap: 7px; }
        .time-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
        .time-icon.in  { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,.2); }
        .time-icon.out { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(244,63,94,.2); }
        .time-val { font-family: var(--font-mono); font-size: 15px; font-weight: 500; color: var(--text-primary); }
        .time-lbl { font-size: 11px; color: var(--text-muted); }
        .card-badges { display: flex; flex-wrap: wrap; gap: 6px; }

        .badge { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 10px; border-radius: 20px; }
        .badge-ok    { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,.25); }
        .badge-late  { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,.25); }
        .badge-early { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,.25); }
        .badge-swap  { background: var(--cyan-dim);   color: var(--cyan);   border: 1px solid rgba(34,211,238,.25); }
        .badge-shift { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,.25); }
        .badge-na    { background: rgba(77,98,120,.15); color: var(--text-muted); border: 1px solid rgba(77,98,120,.2); }

        .card-right { text-align: right; flex-shrink: 0; }
        .lstat-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 5px 13px; border-radius: 20px; }
        .lstat-acc     { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,.3); }
        .lstat-revisi  { background: rgba(167,139,250,.15); color: var(--purple); border: 1px solid rgba(167,139,250,.35); }
        .lstat-pending { background: rgba(77,98,120,.15); color: var(--text-secondary); border: 1px solid rgba(77,98,120,.2); }
        .lstat-none    { background: rgba(77,98,120,.1);  color: var(--text-muted); border: 1px solid rgba(77,98,120,.15); }

        .card-laporan { padding: 0 20px 16px; }
        .laporan-box { background: rgba(255,255,255,.03); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 14px 16px; }
        .laporan-box-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .laporan-isi { font-size: 13px; color: var(--text-secondary); line-height: 1.6; white-space: pre-line; }
        .catatan-revisi-box { margin-top: 10px; background: rgba(167,139,250,.07); border: 1px solid rgba(167,139,250,.2); border-radius: var(--radius-sm); padding: 10px 14px; }
        .catatan-revisi-label { font-size: 11px; font-weight: 600; color: var(--purple); margin-bottom: 4px; display: flex; align-items: center; gap: 5px; }
        .catatan-revisi-isi { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }

        .card-actions { padding: 0 20px 18px; }
        .btn-edit-laporan { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 600; padding: 8px 18px; border-radius: var(--radius-sm); background: rgba(167,139,250,.12); border: 1px solid rgba(167,139,250,.35); color: var(--purple); cursor: pointer; transition: all .2s; text-decoration: none; }
        .btn-edit-laporan:hover { background: rgba(167,139,250,.22); border-color: var(--purple); }

        .card-divider { height: 1px; background: var(--navy-line); margin: 0 20px; }

        /* MODAL EDIT LAPORAN */
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(5,12,22,.82); z-index: 500; align-items: center; justify-content: center; padding: 16px; backdrop-filter: blur(4px); }
        .modal-backdrop.open { display: flex; }
        .modal { background: var(--navy-card); border: 1px solid rgba(167,139,250,.25); border-radius: var(--radius-xl); width: 100%; max-width: 520px; overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,.6); animation: modal-in .22s ease; }
        @keyframes modal-in { from { opacity:0; transform:translateY(16px) scale(.97); } to { opacity:1; transform:none; } }
        .modal-header { padding: 20px 24px 16px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .modal-title  { font-size: 15px; font-weight: 700; color: var(--text-primary); }
        .modal-close  { width: 30px; height: 30px; border-radius: 8px; background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all .2s; }
        .modal-close:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }
        .modal-body   { padding: 20px 24px; }
        .modal-info   { background: rgba(167,139,250,.07); border: 1px solid rgba(167,139,250,.2); border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 16px; font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
        .modal-info strong { color: var(--purple); }
        .form-label   { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: .5px; }
        .form-textarea { width: 100%; background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-family: var(--font-main); font-size: 13px; line-height: 1.6; padding: 12px 14px; resize: vertical; min-height: 120px; outline: none; transition: border-color .2s; }
        .form-textarea:focus { border-color: var(--purple); }
        .modal-footer { padding: 16px 24px 20px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel   { padding: 8px 18px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); font-size: 13px; font-weight: 500; cursor: pointer; transition: all .2s; }
        .btn-cancel:hover { border-color: rgba(255,255,255,.15); color: var(--text-primary); }
        .btn-submit   { padding: 8px 22px; border-radius: var(--radius-sm); background: var(--purple); border: none; color: white; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; display: flex; align-items: center; gap: 7px; }
        .btn-submit:hover { background: #9166f0; }

        /* ══ MODAL EXPORT TANGGAL ══ */
        .modal-export { border-color: rgba(34,197,94,.3); max-width: 480px; }
        .modal-export .modal-header { border-bottom-color: rgba(34,197,94,.15); }
        .modal-export .modal-title  { color: var(--green); }

        .export-date-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .export-date-group label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
        .export-date-input {
            width: 100%; background: var(--navy); border: 1px solid var(--navy-line);
            border-radius: var(--radius-sm); color: var(--text-primary);
            font-family: var(--font-main); font-size: 13px; padding: 10px 12px;
            outline: none; transition: border-color .2s;
            color-scheme: dark;
        }
        .export-date-input:focus { border-color: var(--green); }

        .export-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px; }
        .preset-btn {
            font-size: 11px; font-weight: 500; padding: 5px 12px; border-radius: 20px;
            background: var(--navy-hover); border: 1px solid var(--navy-line);
            color: var(--text-secondary); cursor: pointer; transition: all .18s;
        }
        .preset-btn:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .preset-btn.active { background: var(--green-dim); border-color: rgba(34,197,94,.4); color: var(--green); }

        .export-preview {
            background: rgba(34,197,94,.06); border: 1px solid rgba(34,197,94,.18);
            border-radius: var(--radius-sm); padding: 10px 14px;
            font-size: 12px; color: var(--text-secondary); line-height: 1.6;
        }
        .export-preview strong { color: var(--green); }

        .btn-submit-green { background: var(--green); }
        .btn-submit-green:hover { background: #16a34a; }

        @media (max-width: 700px) {
            .stats-row { grid-template-columns: repeat(3, minmax(0,1fr)); }
            .card-main { grid-template-columns: 1fr; }
            .card-right { text-align: left; }
            .navbar { padding: 0 16px; }
            .main { padding: 20px 12px 50px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .export-date-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stats-row { grid-template-columns: repeat(2, minmax(0,1fr)); }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </a>
    <div class="navbar-right">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'], 0, 2)) ?></div>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-calendar-days" style="color:var(--accent);margin-right:10px;"></i>Laporan Harian
            </div>
            <div class="page-sub">Riwayat absensi & status laporan Anda — <?= htmlspecialchars($_SESSION['name']) ?></div>
        </div>
        <!-- TOMBOL EXPORT — buka popup pilih tanggal -->
        <button class="btn-export" onclick="bukaModalExport()">
            <i class="fas fa-file-word"></i>
            Export Word
        </button>
    </div>

    <!-- FLASH -->
    <?php if ($flash_msg): ?>
    <div class="flash <?= $flash_type ?>">
        <i class="fas <?= $flash_type==='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($flash_msg) ?>
    </div>
    <?php endif; ?>

    <!-- NAVIGASI BULAN -->
    <div class="month-nav">
        <a href="?bulan=<?= $prev_bulan ?>&tahun=<?= $prev_tahun ?>"><i class="fas fa-chevron-left"></i></a>
        <span class="month-label"><?= $nama_bulan[$bulan] ?> <?= $tahun ?></span>
        <a href="?bulan=<?= $next_bulan ?>&tahun=<?= $next_tahun ?>"><i class="fas fa-chevron-right"></i></a>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-pill sp-hadir">
            <div class="sp-val"><?= $total_hadir ?></div>
            <div class="sp-lbl">Total Hadir</div>
        </div>
        <div class="stat-pill sp-normal">
            <div class="sp-val"><?= $total_normal ?></div>
            <div class="sp-lbl">Tepat Waktu</div>
        </div>
        <div class="stat-pill sp-telat">
            <div class="sp-val"><?= $total_telat ?></div>
            <div class="sp-lbl">Terlambat</div>
        </div>
        <div class="stat-pill sp-awal">
            <div class="sp-val"><?= $total_awal ?></div>
            <div class="sp-lbl">Pulang Awal</div>
        </div>
        <div class="stat-pill sp-revisi">
            <div class="sp-val"><?= $total_revisi ?></div>
            <div class="sp-lbl">Perlu Revisi</div>
        </div>
    </div>

    <!-- DAFTAR LAPORAN -->
    <div class="section-title">Rincian Per Hari</div>

    <?php if (empty($riwayat)): ?>
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <p>Tidak ada data absensi untuk <strong><?= $nama_bulan[$bulan] ?> <?= $tahun ?></strong>.</p>
    </div>
    <?php else: ?>

    <div class="laporan-list">
    <?php foreach ($riwayat as $row):
        $bMasuk  = statusMasukBadge($row['keterangan_masuk'], $row['status_masuk']);
        $bKeluar = statusKeluarBadge($row['status_keluar']);
        $bLap    = statusLaporanInfo($row['status_laporan']);

        if ($row['keterangan_masuk']==='terlambat')    $cardClass='status-late';
        elseif ($row['status_keluar']==='pulang_awal') $cardClass='status-early';
        elseif ($row['status_masuk']==='tidak_sesuai') $cardClass='status-swap';
        else                                            $cardClass='status-ok';

        $jam_masuk_fmt  = $row['jam_masuk']  ? date('H:i', strtotime($row['jam_masuk']))  : '—';
        $jam_keluar_fmt = $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : '—';
        $tgl_fmt        = formatTanggalID($row['tanggal']);
        $ada_laporan    = !empty($row['status_laporan']);
        $boleh_edit     = ($row['status_laporan']==='revisi');
        $absensi_id_row = (int)$row['id'];
        $laporan_id_row = 0;
        $catatan_revisi = $row['catatan_revisi'] ?? '';
        if ($ada_laporan && $boleh_edit) {
            $lap_row = getLaporanByAbsensi($absensi_id_row);
            if ($lap_row) $laporan_id_row = (int)$lap_row['id'];
        }
    ?>
    <div class="laporan-card <?= $cardClass ?>">
        <div class="card-main">
            <div class="card-left">
                <div class="card-date-row">
                    <span class="card-date"><?= $tgl_fmt ?></span>
                    <span class="card-shift"><?= htmlspecialchars($row['nama_shift']) ?></span>
                    <?php if ($row['is_double_shift']): ?>
                    <span class="badge badge-shift"><i class="fas fa-rotate"></i> Double Shift</span>
                    <?php endif; ?>
                </div>
                <div class="card-times">
                    <div class="card-time-item">
                        <div class="time-icon in"><i class="fas fa-sign-in-alt"></i></div>
                        <div>
                            <div class="time-val"><?= $jam_masuk_fmt ?></div>
                            <div class="time-lbl">Masuk</div>
                        </div>
                    </div>
                    <div style="color:var(--text-muted);font-size:18px;">→</div>
                    <div class="card-time-item">
                        <div class="time-icon out"><i class="fas fa-sign-out-alt"></i></div>
                        <div>
                            <div class="time-val"><?= $jam_keluar_fmt ?></div>
                            <div class="time-lbl">Keluar</div>
                        </div>
                    </div>
                </div>
                <div class="card-badges">
                    <span class="badge <?= $bMasuk['class'] ?>">
                        <i class="fas <?= $bMasuk['icon'] ?>"></i> <?= $bMasuk['label'] ?>
                    </span>
                    <span class="badge <?= $bKeluar['class'] ?>">
                        <i class="fas <?= $bKeluar['icon'] ?>"></i> <?= $bKeluar['label'] ?>
                    </span>
                </div>
            </div>
            <div class="card-right">
                <div class="lstat-badge <?= $bLap['class'] ?>">
                    <i class="fas <?= $bLap['icon'] ?>"></i> <?= $bLap['label'] ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">Status Laporan</div>
            </div>
        </div>

        <?php if ($ada_laporan): ?>
        <div class="card-divider"></div>
        <div class="card-laporan">
            <div class="laporan-box">
                <div class="laporan-box-title"><i class="fas fa-file-lines"></i> Isi Laporan</div>
                <div class="laporan-isi"><?= nl2br(htmlspecialchars($row['isi_laporan'] ?? '—')) ?></div>
                <?php if ($boleh_edit && !empty($catatan_revisi)): ?>
                <div class="catatan-revisi-box">
                    <div class="catatan-revisi-label"><i class="fas fa-comment-dots"></i> Catatan Revisi dari Kepala Kantor</div>
                    <div class="catatan-revisi-isi"><?= nl2br(htmlspecialchars($catatan_revisi)) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($boleh_edit): ?>
        <div class="card-actions">
            <button class="btn-edit-laporan" onclick="bukaModal(<?= $laporan_id_row ?>, <?= $absensi_id_row ?>, <?= htmlspecialchars(json_encode($row['isi_laporan'] ?? ''), ENT_QUOTES) ?>)">
                <i class="fas fa-pen-to-square"></i> Edit & Kirim Ulang Laporan
            </button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div><!-- /main -->

<!-- ══════════════════════════════════════════
     MODAL EDIT LAPORAN
══════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalEdit">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-pen-to-square" style="color:var(--purple);margin-right:8px;"></i>Edit Laporan</div>
            <button class="modal-close" onclick="tutupModal()"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="aksi"       value="update_laporan">
            <input type="hidden" name="laporan_id" id="modal_laporan_id">
            <div class="modal-body">
                <div class="modal-info">
                    Laporan Anda memerlukan <strong>revisi</strong> dari Kepala Kantor.
                    Perbaiki isi laporan di bawah, lalu kirim ulang untuk ditinjau kembali.
                </div>
                <label class="form-label" for="modal_isi">Isi Laporan</label>
                <textarea class="form-textarea" name="isi_laporan" id="modal_isi" placeholder="Tulis isi laporan yang sudah diperbaiki..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Kirim Ulang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL EXPORT — PILIH RENTANG TANGGAL
══════════════════════════════════════════ -->
<div class="modal-backdrop" id="modalExport">
    <div class="modal modal-export">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-file-word" style="margin-right:8px;"></i>Export Laporan Word
            </div>
            <button class="modal-close" onclick="tutupModalExport()"><i class="fas fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;">
                Pilih rentang tanggal yang ingin diekspor ke file Word (.docx).
            </p>

            <!-- Preset cepat -->
            <div style="margin-bottom:10px;">
                <div class="form-label">Pilihan Cepat</div>
                <div class="export-presets">
                    <button class="preset-btn" onclick="setPreset('bulan_ini')">Bulan Ini</button>
                    <button class="preset-btn" onclick="setPreset('bulan_lalu')">Bulan Lalu</button>
                    <button class="preset-btn" onclick="setPreset('minggu_ini')">Minggu Ini</button>
                    <button class="preset-btn" onclick="setPreset('7hari')">7 Hari Terakhir</button>
                    <button class="preset-btn" onclick="setPreset('30hari')">30 Hari Terakhir</button>
                </div>
            </div>

            <!-- Input tanggal manual -->
            <div class="export-date-grid">
                <div class="export-date-group">
                    <label for="exp_dari">Dari Tanggal</label>
                    <input type="date" id="exp_dari" class="export-date-input"
                           value="<?= htmlspecialchars($export_dari_default) ?>"
                           onchange="updatePreview()">
                </div>
                <div class="export-date-group">
                    <label for="exp_sampai">Sampai Tanggal</label>
                    <input type="date" id="exp_sampai" class="export-date-input"
                           value="<?= htmlspecialchars($export_sampai_default) ?>"
                           onchange="updatePreview()">
                </div>
            </div>

            <!-- Preview -->
            <div class="export-preview" id="exportPreview">
                Memuat pratinjau…
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="tutupModalExport()">Batal</button>
            <button type="button" class="btn-submit btn-submit-green" onclick="jalankanExport()">
                <i class="fas fa-file-word"></i> Buka Halaman Export
            </button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════
   MODAL EDIT LAPORAN
══════════════════════════════════════ */
function bukaModal(laporanId, absensiId, isiLaporan) {
    document.getElementById('modal_laporan_id').value = laporanId;
    document.getElementById('modal_isi').value        = isiLaporan || '';
    document.getElementById('modalEdit').classList.add('open');
    setTimeout(() => document.getElementById('modal_isi').focus(), 100);
}
function tutupModal() {
    document.getElementById('modalEdit').classList.remove('open');
}
document.getElementById('modalEdit').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
});

/* ══════════════════════════════════════
   MODAL EXPORT TANGGAL
══════════════════════════════════════ */
var BULAN_AKTIF_DARI   = <?= json_encode($dari) ?>;
var BULAN_AKTIF_SAMPAI = <?= json_encode($sampai) ?>;

function pad2(n) { return String(n).padStart(2, '0'); }
function formatYMD(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth()+1) + '-' + pad2(d.getDate());
}
function formatTglID(str) {
    if (!str) return '-';
    var d = new Date(str + 'T00:00:00');
    var hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][d.getDay()];
    var bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli',
                 'Agustus','September','Oktober','November','Desember'][d.getMonth()];
    return hari + ', ' + pad2(d.getDate()) + ' ' + bulan + ' ' + d.getFullYear();
}

function bukaModalExport() {
    updatePreview();
    document.getElementById('modalExport').classList.add('open');
}
function tutupModalExport() {
    document.getElementById('modalExport').classList.remove('open');
}
document.getElementById('modalExport').addEventListener('click', function(e) {
    if (e.target === this) tutupModalExport();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { tutupModal(); tutupModalExport(); }
});

function setPreset(p) {
    var now   = new Date();
    var dari, sampai;

    if (p === 'bulan_ini') {
        dari   = new Date(now.getFullYear(), now.getMonth(), 1);
        sampai = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    } else if (p === 'bulan_lalu') {
        dari   = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        sampai = new Date(now.getFullYear(), now.getMonth(), 0);
    } else if (p === 'minggu_ini') {
        var day = now.getDay();
        var mon = new Date(now); mon.setDate(now.getDate() - (day === 0 ? 6 : day - 1));
        var sun = new Date(mon); sun.setDate(mon.getDate() + 6);
        dari = mon; sampai = sun;
    } else if (p === '7hari') {
        sampai = new Date(now);
        dari   = new Date(now); dari.setDate(now.getDate() - 6);
    } else if (p === '30hari') {
        sampai = new Date(now);
        dari   = new Date(now); dari.setDate(now.getDate() - 29);
    }

    document.getElementById('exp_dari').value   = formatYMD(dari);
    document.getElementById('exp_sampai').value = formatYMD(sampai);

    // Highlight tombol aktif
    document.querySelectorAll('.preset-btn').forEach(function(b) { b.classList.remove('active'); });
    event.target.classList.add('active');

    updatePreview();
}

function updatePreview() {
    var dari   = document.getElementById('exp_dari').value;
    var sampai = document.getElementById('exp_sampai').value;
    var box    = document.getElementById('exportPreview');

    if (!dari || !sampai) {
        box.innerHTML = '<i class="fas fa-exclamation-circle" style="color:var(--amber);"></i> Pilih kedua tanggal terlebih dahulu.';
        return;
    }
    if (dari > sampai) {
        box.innerHTML = '<i class="fas fa-exclamation-circle" style="color:var(--red);"></i> Tanggal mulai tidak boleh lebih besar dari tanggal selesai.';
        return;
    }

    var d1 = new Date(dari   + 'T00:00:00');
    var d2 = new Date(sampai + 'T00:00:00');
    var selisih = Math.round((d2 - d1) / 86400000) + 1;

    box.innerHTML =
        '<i class="fas fa-calendar-check" style="color:var(--green);margin-right:6px;"></i>' +
        'Akan mengekspor data periode <strong>' +
        formatTglID(dari) + '</strong> s.d. <strong>' + formatTglID(sampai) +
        '</strong> <span style="color:var(--text-muted);">(' + selisih + ' hari)</span>';
}

function jalankanExport() {
    var dari   = document.getElementById('exp_dari').value;
    var sampai = document.getElementById('exp_sampai').value;

    if (!dari || !sampai) {
        alert('Pilih kedua tanggal terlebih dahulu!');
        return;
    }
    if (dari > sampai) {
        alert('Tanggal mulai tidak boleh lebih besar dari tanggal selesai!');
        return;
    }

    var url = 'export_harian.php?dari=' + encodeURIComponent(dari) + '&sampai=' + encodeURIComponent(sampai);
    window.open(url, '_blank');
    tutupModalExport();
}

// Init preview saat halaman dimuat
updatePreview();
</script>

</body>
</html>