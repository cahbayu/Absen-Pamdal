<?php
// rekap_absensi.php – Rekap Absensi Kepala Kantor
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

// Filter parameter
$filter_tanggal_dari   = $_GET['dari']     ?? date('Y-m-01');
$filter_tanggal_sampai = $_GET['sampai']   ?? date('Y-m-d');
$filter_shift_id       = isset($_GET['shift_id']) ? (int)$_GET['shift_id'] : 0;
$filter_status         = $_GET['status']   ?? 'semua';
$filter_user_id        = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Ambil data
$shifts = getAllShifts();
$pamdal = getAllPamdal();

// Query absensi dengan filter
global $conn;
$where = "1=1";
if ($filter_tanggal_dari)   $where .= " AND a.tanggal >= '" . $conn->real_escape_string($filter_tanggal_dari) . "'";
if ($filter_tanggal_sampai) $where .= " AND a.tanggal <= '" . $conn->real_escape_string($filter_tanggal_sampai) . "'";
if ($filter_shift_id)       $where .= " AND a.shift_id = $filter_shift_id";
if ($filter_user_id)        $where .= " AND a.user_id = $filter_user_id";
if ($filter_status === 'terlambat')    $where .= " AND a.keterangan_masuk = 'terlambat'";
if ($filter_status === 'pulang_awal')  $where .= " AND a.status_keluar = 'pulang_awal'";
if ($filter_status === 'lanjut_shift') $where .= " AND a.status_keluar = 'lanjut_shift'";
if ($filter_status === 'tukar_shift')  $where .= " AND a.status_masuk = 'tidak_sesuai'";

$sql = "SELECT a.*, u.name AS nama_user, u.username,
               s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar,
               l.id AS laporan_id, l.status AS status_laporan,
               l.isi_laporan, l.catatan_revisi, l.created_at AS laporan_dibuat
        FROM absensi a
        JOIN users u ON a.user_id = u.id
        JOIN shift s ON a.shift_id = s.id
        LEFT JOIN laporan l ON l.absensi_id = a.id
        WHERE $where
        ORDER BY a.tanggal DESC, a.jam_masuk DESC";
$result = $conn->query($sql);
$absensi_list = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) $absensi_list[] = $row;
}

// Hitung ringkasan
$total_hadir        = count($absensi_list);
$total_terlambat    = 0;
$total_pulang_awal  = 0;
$total_lanjut_shift = 0;
$total_tukar_shift  = 0;
$total_lap_acc      = 0;
$total_lap_pending  = 0;
$total_lap_revisi   = 0;
foreach ($absensi_list as $a) {
    if ($a['keterangan_masuk'] === 'terlambat')   $total_terlambat++;
    if ($a['status_keluar']    === 'pulang_awal')  $total_pulang_awal++;
    if ($a['status_keluar']    === 'lanjut_shift') $total_lanjut_shift++;
    if ($a['status_masuk']     === 'tidak_sesuai') $total_tukar_shift++;
    if ($a['status_laporan']   === 'acc')           $total_lap_acc++;
    if ($a['status_laporan']   === 'pending')       $total_lap_pending++;
    if ($a['status_laporan']   === 'revisi')        $total_lap_revisi++;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($absensi_list)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rekap_absensi_' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['No','Tanggal','Pamdal','Shift','Jam Masuk','Status Masuk','Jam Keluar','Status Keluar','Status Laporan']);
    foreach ($absensi_list as $i => $a) {
        fputcsv($out, [
            $i + 1,
            date('d/m/Y', strtotime($a['tanggal'])),
            $a['nama_user'],
            $a['nama_shift'],
            $a['jam_masuk']  ? date('H:i', strtotime($a['jam_masuk']))  : '-',
            $a['keterangan_masuk'] === 'terlambat' ? 'Terlambat' : ($a['status_masuk'] === 'tidak_sesuai' ? 'Tukar Shift' : 'Tepat Waktu'),
            $a['jam_keluar'] ? date('H:i', strtotime($a['jam_keluar'])) : '-',
            ucfirst(str_replace('_', ' ', $a['status_keluar'] ?? '-')),
            $a['status_laporan'] ?? '-',
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Rekap Absensi — ANDALAN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --navy:          #0f1b2d;
            --navy-mid:      #162236;
            --navy-card:     #1a2b42;
            --navy-hover:    #1f3350;
            --navy-line:     rgba(255,255,255,0.07);
            --accent:        #3b9eff;
            --accent-dim:    rgba(59,158,255,0.15);
            --green:         #22c55e;
            --green-dim:     rgba(34,197,94,0.15);
            --red:           #f43f5e;
            --red-dim:       rgba(244,63,94,0.15);
            --amber:         #f59e0b;
            --amber-dim:     rgba(245,158,11,0.15);
            --purple:        #a78bfa;
            --purple-dim:    rgba(167,139,250,0.15);
            --teal:          #2dd4bf;
            --teal-dim:      rgba(45,212,191,0.15);
            --gold:          #fbbf24;
            --gold-dim:      rgba(251,191,36,0.15);
            --text-primary:  #e8edf4;
            --text-secondary:#7a90a8;
            --text-muted:    #4d6278;
            --font-main:     'DM Sans', sans-serif;
            --font-mono:     'DM Mono', monospace;
            --radius-sm:     6px;
            --radius-lg:     16px;
            --radius-xl:     22px;
        }
        html { font-size: 16px; }
        body { font-family: var(--font-main); background: var(--navy); color: var(--text-primary); min-height: 100vh; -webkit-font-smoothing: antialiased; }

        /* NAVBAR */
        .navbar { position: sticky; top: 0; z-index: 200; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; text-decoration: none; }
        .brand-icon { width: 32px; height: 32px; background: var(--gold); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0f1b2d; }
        .navbar-right { display: flex; align-items: center; gap: 12px; }
        .btn-back { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-back:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); text-decoration: none; }

        /* MAIN */
        .main { max-width: 1200px; margin: 0 auto; padding: 32px 20px 60px; }

        /* PAGE HEADER */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; gap: 16px; }
        .page-title { font-size: 22px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .page-title i { color: var(--accent); }
        .page-subtitle { font-size: 13px; color: var(--text-secondary); margin-top: 5px; }
        .btn-export { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 8px 18px; border-radius: var(--radius-sm); background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3); color: var(--green); text-decoration: none; transition: all 0.2s; cursor: pointer; }
        .btn-export:hover { background: var(--green); color: #0f1b2d; border-color: var(--green); }

        /* STAT GRID */
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 24px; }
        .stat-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 16px 18px; display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .si-blue   { background: var(--accent-dim); color: var(--accent);  border: 1px solid rgba(59,158,255,0.2); }
        .si-amber  { background: var(--amber-dim);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.2); }
        .si-red    { background: var(--red-dim);    color: var(--red);     border: 1px solid rgba(244,63,94,0.2); }
        .si-green  { background: var(--green-dim);  color: var(--green);   border: 1px solid rgba(34,197,94,0.2); }
        .si-teal   { background: var(--teal-dim);   color: var(--teal);    border: 1px solid rgba(45,212,191,0.2); }
        .si-purple { background: var(--purple-dim); color: var(--purple);  border: 1px solid rgba(167,139,250,0.2); }
        .stat-num  { font-family: var(--font-mono); font-size: 24px; font-weight: 500; color: var(--text-primary); line-height: 1; }
        .stat-lbl  { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

        /* FILTER CARD */
        .filter-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 20px 24px; margin-bottom: 24px; }
        .filter-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; display: flex; align-items: center; gap: 6px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-size: 11px; font-weight: 500; color: var(--text-secondary); }
        .filter-group select,
        .filter-group input[type="date"] { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 13px; padding: 7px 12px; font-family: var(--font-main); min-width: 150px; outline: none; transition: border-color 0.2s; }
        .filter-group select:focus,
        .filter-group input[type="date"]:focus { border-color: var(--accent); }
        .filter-group select option { background: var(--navy-card); }
        .btn-filter { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 7px 20px; border-radius: var(--radius-sm); background: var(--accent); border: none; color: #fff; cursor: pointer; transition: all 0.2s; }
        .btn-filter:hover { background: #2280d4; }
        .btn-reset { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 7px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-reset:hover { border-color: rgba(255,255,255,0.2); color: var(--text-primary); }

        /* PANEL */
        .panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .panel-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); }
        .panel-empty { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }
        .panel-empty i { font-size: 28px; display: block; margin-bottom: 10px; }

        /* TABLE */
        .tbl-wrap { overflow-x: auto; }
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
        .tbl th { padding: 10px 14px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); white-space: nowrap; }
        .tbl td { padding: 11px 14px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .name-cell { color: var(--text-primary) !important; font-weight: 500; }
        .mono { font-family: var(--font-mono); font-size: 12px; }
        .row-pending td { background: rgba(245,158,11,0.03); }
        .row-revisi  td { background: rgba(244,63,94,0.03); }

        /* BADGES */
        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; white-space: nowrap; }
        .b-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .b-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .b-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .b-teal   { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .b-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        /* TOMBOL LAPORAN */
        .btn-laporan { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 4px 11px; border-radius: var(--radius-sm); border: none; cursor: pointer; transition: all 0.18s; font-family: var(--font-main); }
        .btn-laporan-ada      { background: var(--accent-dim); border: 1px solid rgba(59,158,255,0.3);  color: var(--accent); }
        .btn-laporan-ada:hover { background: var(--accent); color: #fff; }
        .btn-laporan-pending  { background: var(--amber-dim);  border: 1px solid rgba(245,158,11,0.3);  color: var(--amber); }
        .btn-laporan-pending:hover { background: var(--amber); color: #0f1b2d; }
        .btn-laporan-revisi   { background: var(--red-dim);    border: 1px solid rgba(244,63,94,0.3);   color: var(--red); }
        .btn-laporan-revisi:hover  { background: var(--red);   color: #fff; }

        /* PAGINATION BAR */
        .bottom-bar { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-top: 1px solid var(--navy-line); font-size: 12px; color: var(--text-secondary); flex-wrap: wrap; gap: 10px; }

        /* ═══════════════════════════════════════════
           MODAL POPUP
        ═══════════════════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999;
            background: rgba(9, 18, 32, 0.82);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }

        .modal {
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: modalIn 0.22s ease;
            overflow: hidden;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(16px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--navy-line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        .modal-header-left { display: flex; align-items: center; gap: 10px; }
        .modal-header-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .mhi-blue   { background: var(--accent-dim); color: var(--accent);  border: 1px solid rgba(59,158,255,0.2); }
        .mhi-amber  { background: var(--amber-dim);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.2); }
        .mhi-red    { background: var(--red-dim);    color: var(--red);     border: 1px solid rgba(244,63,94,0.2); }
        .mhi-green  { background: var(--green-dim);  color: var(--green);   border: 1px solid rgba(34,197,94,0.2); }
        .modal-header-title { font-size: 14px; font-weight: 600; color: var(--text-primary); }
        .modal-header-sub   { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
        .btn-modal-close {
            width: 32px; height: 32px; border-radius: 8px;
            background: transparent; border: 1px solid var(--navy-line);
            color: var(--text-muted); font-size: 14px;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.18s; flex-shrink: 0;
        }
        .btn-modal-close:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }

        .modal-body { padding: 22px; overflow-y: auto; flex: 1; }

        /* Info grid dalam modal */
        .modal-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .modal-info-item { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 12px 14px; }
        .modal-info-label { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 5px; }
        .modal-info-value { font-size: 13px; color: var(--text-primary); font-weight: 500; }
        .modal-info-value.mono { font-family: var(--font-mono); font-size: 12px; }

        /* Isi laporan */
        .modal-section-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .laporan-isi-box {
            background: var(--navy);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 16px;
            font-size: 13px;
            color: var(--text-primary);
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Catatan revisi */
        .revisi-box {
            background: var(--red-dim);
            border: 1px solid rgba(244,63,94,0.25);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-top: 16px;
            font-size: 13px;
            color: var(--red);
            line-height: 1.6;
        }
        .revisi-box .revisi-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 6px; opacity: 0.75; }

        /* Belum ada laporan */
        .no-laporan-box {
            text-align: center;
            padding: 32px 20px;
            color: var(--text-muted);
            font-size: 13px;
        }
        .no-laporan-box i { font-size: 30px; display: block; margin-bottom: 10px; }

        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--navy-line);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-modal-close-footer {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 18px; border-radius: var(--radius-sm);
            background: transparent; border: 1px solid var(--navy-line);
            color: var(--text-secondary); font-size: 13px; font-weight: 500;
            cursor: pointer; font-family: var(--font-main); transition: all 0.2s;
        }
        .btn-modal-close-footer:hover { background: var(--navy-hover); color: var(--text-primary); }

        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }

        @media (max-width: 900px) {
            .stat-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .page-header { flex-direction: column; }
            .filter-row { flex-direction: column; align-items: flex-start; }
            .navbar { padding: 0 16px; }
            .main  { padding: 20px 14px 50px; }
            .modal-info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </a>
    <div class="navbar-right">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-clipboard-list"></i>
                Rekap Absensi
            </div>
            <div class="page-subtitle">
                Menampilkan <?= number_format($total_hadir) ?> catatan absensi
                &middot; <?= date('d M Y', strtotime($filter_tanggal_dari)) ?> – <?= date('d M Y', strtotime($filter_tanggal_sampai)) ?>
            </div>
        </div>
        <a href="?dari=<?= $filter_tanggal_dari ?>&sampai=<?= $filter_tanggal_sampai ?>&shift_id=<?= $filter_shift_id ?>&status=<?= $filter_status ?>&user_id=<?= $filter_user_id ?>&export=csv"
           class="btn-export">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <!-- STATISTIK -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="fas fa-users"></i></div>
            <div><div class="stat-num"><?= $total_hadir ?></div><div class="stat-lbl">Total Hadir</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-amber"><i class="fas fa-clock"></i></div>
            <div><div class="stat-num"><?= $total_terlambat ?></div><div class="stat-lbl">Terlambat</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-red"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div><div class="stat-num"><?= $total_pulang_awal ?></div><div class="stat-lbl">Pulang Awal</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-purple"><i class="fas fa-rotate"></i></div>
            <div><div class="stat-num"><?= $total_lanjut_shift ?></div><div class="stat-lbl">Lanjut Shift</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-teal"><i class="fas fa-exchange-alt"></i></div>
            <div><div class="stat-num"><?= $total_tukar_shift ?></div><div class="stat-lbl">Tukar Shift</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="fas fa-check-double"></i></div>
            <div><div class="stat-num"><?= $total_lap_acc ?></div><div class="stat-lbl">Laporan ACC</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-amber"><i class="fas fa-file-circle-xmark"></i></div>
            <div><div class="stat-num"><?= $total_lap_pending ?></div><div class="stat-lbl">Laporan Pending</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-red"><i class="fas fa-file-circle-exclamation"></i></div>
            <div><div class="stat-num"><?= $total_lap_revisi ?></div><div class="stat-lbl">Laporan Revisi</div></div>
        </div>
    </div>

    <!-- FILTER -->
    <div class="filter-card">
        <div class="filter-title"><i class="fas fa-filter"></i> Filter Data</div>
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Tanggal Dari</label>
                    <input type="date" name="dari" value="<?= $filter_tanggal_dari ?>">
                </div>
                <div class="filter-group">
                    <label>Tanggal Sampai</label>
                    <input type="date" name="sampai" value="<?= $filter_tanggal_sampai ?>">
                </div>
                <div class="filter-group">
                    <label>Shift</label>
                    <select name="shift_id">
                        <option value="0">Semua Shift</option>
                        <?php foreach ($shifts as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $filter_shift_id == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nama_shift']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Pamdal</label>
                    <select name="user_id">
                        <option value="0">Semua Pamdal</option>
                        <?php foreach ($pamdal as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_user_id == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status Kehadiran</label>
                    <select name="status">
                        <option value="semua"        <?= $filter_status == 'semua'        ? 'selected' : '' ?>>Semua Status</option>
                        <option value="terlambat"    <?= $filter_status == 'terlambat'    ? 'selected' : '' ?>>Terlambat</option>
                        <option value="pulang_awal"  <?= $filter_status == 'pulang_awal'  ? 'selected' : '' ?>>Pulang Awal</option>
                        <option value="lanjut_shift" <?= $filter_status == 'lanjut_shift' ? 'selected' : '' ?>>Lanjut Shift</option>
                        <option value="tukar_shift"  <?= $filter_status == 'tukar_shift'  ? 'selected' : '' ?>>Tukar Shift</option>
                    </select>
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Tampilkan</button>
                <a href="rekap_absensi.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- TABEL -->
    <div class="section-title">Data Absensi</div>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fas fa-table" style="color:var(--accent);"></i>
                Daftar Absensi
            </span>
            <span class="panel-badge"><?= count($absensi_list) ?> data</span>
        </div>
        <div class="panel-body">
            <?php if (empty($absensi_list)): ?>
                <div class="panel-empty">
                    <i class="fas fa-calendar-times" style="color:var(--text-muted);"></i>
                    Tidak ada data absensi sesuai filter yang dipilih.
                </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Pamdal</th>
                            <th>Shift</th>
                            <th>Jam Masuk</th>
                            <th>Status Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Status Keluar</th>
                            <th>Laporan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($absensi_list as $i => $a):
                        $row_class = '';
                        if ($a['status_laporan'] === 'pending') $row_class = 'row-pending';
                        if ($a['status_laporan'] === 'revisi')  $row_class = 'row-revisi';

                        // Siapkan data untuk modal (di-encode ke JSON)
                        $modal_data = json_encode([
                            'nama_user'      => $a['nama_user'],
                            'tanggal'        => date('d F Y', strtotime($a['tanggal'])),
                            'nama_shift'     => $a['nama_shift'],
                            'shift_jam'      => substr($a['shift_jam_masuk'],0,5) . ' – ' . substr($a['shift_jam_keluar'],0,5),
                            'jam_masuk'      => $a['jam_masuk']  ? date('H:i', strtotime($a['jam_masuk']))  : '—',
                            'jam_keluar'     => $a['jam_keluar'] ? date('H:i', strtotime($a['jam_keluar'])) : '—',
                            'status_masuk'   => $a['keterangan_masuk'] === 'terlambat' ? 'Terlambat' : ($a['status_masuk'] === 'tidak_sesuai' ? 'Tukar Shift' : 'Tepat Waktu'),
                            'status_keluar'  => $a['status_keluar'] ?? '',
                            'ada_laporan'    => !empty($a['laporan_id']),
                            'status_laporan' => $a['status_laporan'] ?? '',
                            'isi_laporan'    => $a['isi_laporan'] ?? '',
                            'catatan_revisi' => $a['catatan_revisi'] ?? '',
                            'laporan_dibuat' => $a['laporan_dibuat'] ? date('d/m/Y H:i', strtotime($a['laporan_dibuat'])) : '',
                        ], JSON_HEX_QUOT | JSON_HEX_APOS);
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td class="mono" style="color:var(--text-muted);"><?= $i + 1 ?></td>
                            <td class="mono"><?= date('d/m/Y', strtotime($a['tanggal'])) ?></td>
                            <td class="name-cell"><?= htmlspecialchars($a['nama_user']) ?></td>
                            <td><span class="badge b-blue"><?= htmlspecialchars($a['nama_shift']) ?></span></td>
                            <td class="mono"><?= $a['jam_masuk']  ? date('H:i', strtotime($a['jam_masuk']))  : '—' ?></td>
                            <td>
                                <?php
                                $km = $a['keterangan_masuk'] ?? 'normal';
                                $sm = $a['status_masuk'] ?? '';
                                if ($sm === 'tidak_sesuai'): ?>
                                    <span class="badge b-teal"><i class="fas fa-exchange-alt" style="font-size:9px;"></i> Tukar Shift</span>
                                <?php elseif ($km === 'terlambat'): ?>
                                    <span class="badge b-amber"><i class="fas fa-clock" style="font-size:9px;"></i> Terlambat</span>
                                <?php else: ?>
                                    <span class="badge b-green"><i class="fas fa-check" style="font-size:9px;"></i> Tepat Waktu</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= $a['jam_keluar'] ? date('H:i', strtotime($a['jam_keluar'])) : '—' ?></td>
                            <td>
                                <?php $sk = $a['status_keluar'] ?? ''; ?>
                                <?php if ($sk === 'tepat_waktu'): ?>
                                    <span class="badge b-green">Tepat Waktu</span>
                                <?php elseif ($sk === 'pulang_awal'): ?>
                                    <span class="badge b-red">Pulang Awal</span>
                                <?php elseif ($sk === 'lanjut_shift'): ?>
                                    <span class="badge b-purple">Lanjut Shift</span>
                                <?php else: ?>
                                    <span class="badge b-muted">Belum Keluar</span>
                                <?php endif; ?>
                            </td>

                            <!-- KOLOM LAPORAN: tombol atau tanda -->
                            <td>
                                <?php if (empty($a['laporan_id'])): ?>
                                    <span style="font-size:11px; color:var(--text-muted);">Belum ada</span>
                                <?php else:
                                    $sl = $a['status_laporan'] ?? '';
                                    $btn_cls = match($sl) {
                                        'pending' => 'btn-laporan-pending',
                                        'revisi'  => 'btn-laporan-revisi',
                                        default   => 'btn-laporan-ada',
                                    };
                                    $btn_icon = match($sl) {
                                        'pending' => 'fa-clock',
                                        'revisi'  => 'fa-triangle-exclamation',
                                        'acc'     => 'fa-check-double',
                                        default   => 'fa-file-lines',
                                    };
                                    $btn_label = match($sl) {
                                        'pending' => 'Pending',
                                        'revisi'  => 'Revisi',
                                        'acc'     => 'ACC',
                                        default   => 'Lihat',
                                    };
                                ?>
                                <button
                                    class="btn-laporan <?= $btn_cls ?>"
                                    onclick='openModal(<?= $modal_data ?>)'
                                    title="Lihat isi laporan">
                                    <i class="fas <?= $btn_icon ?>" style="font-size:10px;"></i>
                                    <?= $btn_label ?>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="bottom-bar">
                <span>
                    Menampilkan <strong style="color:var(--text-primary);"><?= count($absensi_list) ?></strong> catatan
                </span>
                <div style="display:flex;gap:20px;align-items:center;">
                    <?php
                    $terlambat_pct = $total_hadir > 0 ? round(($total_terlambat / $total_hadir) * 100) : 0;
                    $acc_pct       = $total_hadir > 0 ? round(($total_lap_acc    / $total_hadir) * 100) : 0;
                    ?>
                    <span style="font-size:11px;color:var(--text-muted);">
                        Keterlambatan: <span style="color:var(--amber);"><?= $terlambat_pct ?>%</span>
                    </span>
                    <span style="font-size:11px;color:var(--text-muted);">
                        Laporan ACC: <span style="color:var(--green);"><?= $acc_pct ?>%</span>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════
     MODAL POPUP LAPORAN
═══════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay" onclick="handleOverlayClick(event)">
    <div class="modal" id="modalBox">

        <!-- HEADER -->
        <div class="modal-header">
            <div class="modal-header-left">
                <div class="modal-header-icon" id="modalIcon"><i class="fas fa-file-lines"></i></div>
                <div>
                    <div class="modal-header-title" id="modalTitle">Laporan Shift</div>
                    <div class="modal-header-sub"   id="modalSub">—</div>
                </div>
            </div>
            <button class="btn-modal-close" onclick="closeModal()" title="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- BODY -->
        <div class="modal-body">

            <!-- INFO GRID -->
            <div class="modal-info-grid">
                <div class="modal-info-item">
                    <div class="modal-info-label"><i class="fas fa-user" style="font-size:9px;"></i> Pamdal</div>
                    <div class="modal-info-value" id="mNamaUser">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label"><i class="fas fa-calendar" style="font-size:9px;"></i> Tanggal</div>
                    <div class="modal-info-value" id="mTanggal">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label"><i class="fas fa-moon" style="font-size:9px;"></i> Shift</div>
                    <div class="modal-info-value" id="mShift">—</div>
                </div>
                <div class="modal-info-item">
                    <div class="modal-info-label"><i class="fas fa-clock" style="font-size:9px;"></i> Waktu Bertugas</div>
                    <div class="modal-info-value mono" id="mWaktu">—</div>
                </div>
            </div>

            <!-- ISI LAPORAN -->
            <div id="sectionLaporan">
                <div class="modal-section-label">
                    <i class="fas fa-file-alt" style="font-size:11px;"></i>
                    Isi Laporan
                    <span id="mLaporanDibuat" style="font-family:var(--font-mono);font-size:10px;color:var(--text-muted);margin-left:auto;font-weight:400;"></span>
                </div>
                <div class="laporan-isi-box" id="mIsiLaporan">—</div>

                <!-- Catatan revisi (hanya muncul jika status revisi) -->
                <div class="revisi-box" id="mRevisiBox" style="display:none;">
                    <div class="revisi-label"><i class="fas fa-triangle-exclamation"></i> Catatan Revisi dari Kepala Kantor</div>
                    <div id="mCatatanRevisi">—</div>
                </div>
            </div>

            <!-- Belum ada laporan -->
            <div class="no-laporan-box" id="sectionNoLaporan" style="display:none;">
                <i class="fas fa-file-circle-xmark" style="color:var(--text-muted);"></i>
                Pamdal ini belum membuat laporan untuk shift tersebut.
            </div>

        </div>

        <!-- FOOTER -->
        <div class="modal-footer">
            <div id="mStatusBadge" style="margin-right:auto;"></div>
            <button class="btn-modal-close-footer" onclick="closeModal()">
                <i class="fas fa-times"></i> Tutup
            </button>
        </div>

    </div>
</div>

<script>
function openModal(data) {
    // Isi info grid
    document.getElementById('mNamaUser').textContent = data.nama_user  || '—';
    document.getElementById('mTanggal').textContent  = data.tanggal    || '—';
    document.getElementById('mShift').textContent    = data.nama_shift + ' (' + data.shift_jam + ')';
    document.getElementById('mWaktu').innerHTML      = (data.jam_masuk || '—') + ' <span style="color:var(--text-muted);">→</span> ' + (data.jam_keluar || '—');

    // Header modal
    document.getElementById('modalSub').textContent   = data.nama_user + ' · ' + data.tanggal;
    document.getElementById('modalTitle').textContent = data.ada_laporan ? 'Laporan Shift' : 'Belum Ada Laporan';

    // Ikon & warna header sesuai status laporan
    const icon = document.getElementById('modalIcon');
    icon.className = 'modal-header-icon';
    let statusBadgeHtml = '';

    if (!data.ada_laporan) {
        icon.classList.add('mhi-blue');
        icon.innerHTML = '<i class="fas fa-file-lines"></i>';
        statusBadgeHtml = '<span style="font-size:11px;color:var(--text-muted);">Belum ada laporan</span>';
    } else {
        const sl = data.status_laporan;
        if (sl === 'acc') {
            icon.classList.add('mhi-green');
            icon.innerHTML = '<i class="fas fa-check-double"></i>';
            statusBadgeHtml = '<span class="badge b-green"><i class="fas fa-check-double" style="font-size:9px;"></i> Laporan ACC</span>';
        } else if (sl === 'pending') {
            icon.classList.add('mhi-amber');
            icon.innerHTML = '<i class="fas fa-clock"></i>';
            statusBadgeHtml = '<span class="badge b-amber"><i class="fas fa-clock" style="font-size:9px;"></i> Menunggu Tinjauan</span>';
        } else if (sl === 'revisi') {
            icon.classList.add('mhi-red');
            icon.innerHTML = '<i class="fas fa-triangle-exclamation"></i>';
            statusBadgeHtml = '<span class="badge b-red"><i class="fas fa-triangle-exclamation" style="font-size:9px;"></i> Perlu Revisi</span>';
        } else {
            icon.classList.add('mhi-blue');
            icon.innerHTML = '<i class="fas fa-file-lines"></i>';
        }
    }
    document.getElementById('mStatusBadge').innerHTML = statusBadgeHtml;

    // Tampilkan isi atau pesan belum ada
    const secLaporan   = document.getElementById('sectionLaporan');
    const secNoLaporan = document.getElementById('sectionNoLaporan');
    if (data.ada_laporan && data.isi_laporan) {
        secLaporan.style.display   = '';
        secNoLaporan.style.display = 'none';
        document.getElementById('mIsiLaporan').textContent    = data.isi_laporan;
        document.getElementById('mLaporanDibuat').textContent = data.laporan_dibuat ? ('Dibuat: ' + data.laporan_dibuat) : '';

        // Catatan revisi
        const revisiBox = document.getElementById('mRevisiBox');
        if (data.status_laporan === 'revisi' && data.catatan_revisi) {
            document.getElementById('mCatatanRevisi').textContent = data.catatan_revisi;
            revisiBox.style.display = '';
        } else {
            revisiBox.style.display = 'none';
        }
    } else {
        secLaporan.style.display   = 'none';
        secNoLaporan.style.display = '';
    }

    document.getElementById('modalOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function handleOverlayClick(e) {
    if (e.target === document.getElementById('modalOverlay')) closeModal();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>

</body>
</html>