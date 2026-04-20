<?php
// penukaran_shift.php — Riwayat Penukaran Shift
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Hanya super_admin yang boleh akses halaman ini
if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

// ── FILTER ──────────────────────────────────────────────────
$filter_tipe   = cleanInput($_GET['tipe']   ?? 'semua');
$filter_dari   = cleanInput($_GET['dari']   ?? date('Y-m-01'));
$filter_sampai = cleanInput($_GET['sampai'] ?? date('Y-m-d'));

// Validasi tipe filter
$tipe_valid = ['semua', 'penukar', 'pengganti'];
if (!in_array($filter_tipe, $tipe_valid)) $filter_tipe = 'semua';

// ── QUERY DATA PENUKARAN ─────────────────────────────────────
$where_parts = ["a.tanggal >= '$filter_dari'", "a.tanggal <= '$filter_sampai'"];
if ($filter_tipe !== 'semua') {
    $where_parts[] = "ps.tipe = '$filter_tipe'";
}
$where_sql = implode(' AND ', $where_parts);

$sql_penukaran = "
    SELECT
        ps.id,
        ps.tipe,
        ps.tanggal        AS tanggal_penukaran,
        a.tanggal         AS tanggal_absensi,
        a.jam_masuk,
        a.id              AS absensi_id,
        u_pemilik.id      AS user_pemilik_id,
        u_pemilik.name    AS nama_pemilik,
        u_pengganti.name  AS nama_pengganti,
        s_asli.nama_shift AS shift_asli,
        s_tukar.nama_shift AS shift_tukar,
        s_asli.jam_masuk  AS jam_masuk_shift_asli,
        s_asli.jam_keluar AS jam_keluar_shift_asli,
        s_tukar.jam_masuk AS jam_masuk_shift_tukar,
        s_tukar.jam_keluar AS jam_keluar_shift_tukar,
        a.keterangan_masuk,
        l.status          AS status_laporan
    FROM penukaran_shift ps
    JOIN absensi a           ON ps.absensi_id     = a.id
    JOIN users u_pemilik     ON a.user_id          = u_pemilik.id
    JOIN users u_pengganti   ON ps.user_pengganti_id = u_pengganti.id
    JOIN shift s_asli        ON a.shift_id         = s_asli.id
    JOIN shift s_tukar       ON ps.shift_id        = s_tukar.id
    LEFT JOIN laporan l      ON l.absensi_id       = a.id
    WHERE $where_sql
    ORDER BY a.tanggal DESC, ps.id DESC
";

$result_penukaran = $conn->query($sql_penukaran);
$data_penukaran   = [];
if ($result_penukaran && $result_penukaran->num_rows > 0) {
    while ($row = $result_penukaran->fetch_assoc()) {
        $data_penukaran[] = $row;
    }
}

// ── STATISTIK ────────────────────────────────────────────────
$total_semua    = count($data_penukaran);
$total_penukar  = count(array_filter($data_penukaran, fn($r) => $r['tipe'] === 'penukar'));
$total_pengganti= count(array_filter($data_penukaran, fn($r) => $r['tipe'] === 'pengganti'));

// Penukaran per shift (shift yang paling sering ditukar)
$shift_count = [];
foreach ($data_penukaran as $row) {
    $nm = $row['shift_asli'];
    $shift_count[$nm] = ($shift_count[$nm] ?? 0) + 1;
}
arsort($shift_count);

// Pamdal yang paling sering tukar
$pamdal_count = [];
foreach ($data_penukaran as $row) {
    $nm = $row['nama_pemilik'];
    $pamdal_count[$nm] = ($pamdal_count[$nm] ?? 0) + 1;
}
arsort($pamdal_count);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Penukaran Shift — ANDALAN</title>
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

        /* ── NAVBAR ── */
        .navbar { position: sticky; top: 0; z-index: 100; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; }
        .brand-icon { width: 32px; height: 32px; background: var(--gold); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0f1b2d; }
        .navbar-right { display: flex; align-items: center; gap: 16px; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar { width: 34px; height: 34px; background: var(--gold-dim); border: 1px solid var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--gold); font-weight: 600; }
        .user-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
        .role-chip { font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; background: var(--gold-dim); color: var(--gold); border: 1px solid rgba(251,191,36,0.35); }
        .btn-nav { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-nav:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); text-decoration: none; }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); text-decoration: none; }

        /* ── MAIN ── */
        .main { max-width: 1100px; margin: 0 auto; padding: 32px 20px 60px; }

        /* ── PAGE HEADER ── */
        .page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
        .page-header-left .page-title { font-size: 22px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .page-title-icon { width: 40px; height: 40px; background: var(--red-dim); border: 1px solid rgba(244,63,94,0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; color: var(--red); }
        .page-header-left .page-sub { font-size: 13px; color: var(--text-muted); margin-top: 6px; }
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; }
        .breadcrumb a:hover { color: var(--accent); }
        .breadcrumb span { color: var(--text-primary); }

        /* ── SECTION TITLE ── */
        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }

        /* ── STAT GRID ── */
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 18px 20px; position: relative; overflow: hidden; }
        .stat-card::after { content: ''; position: absolute; right: -16px; bottom: -16px; width: 60px; height: 60px; border-radius: 50%; opacity: 0.07; }
        .stat-card.c-red::after    { background: var(--red);    }
        .stat-card.c-amber::after  { background: var(--amber);  }
        .stat-card.c-purple::after { background: var(--purple); }
        .stat-card.c-blue::after   { background: var(--accent); }
        .stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 14px; }
        .si-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.2); }
        .si-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.2); }
        .si-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.2); }
        .si-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.2); }
        .si-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
        .stat-number { font-family: var(--font-mono); font-size: 28px; font-weight: 500; color: var(--text-primary); line-height: 1; }
        .stat-label  { font-size: 12px; color: var(--text-secondary); margin-top: 6px; }

        /* ── FILTER PANEL ── */
        .filter-panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 18px 22px; margin-bottom: 24px; }
        .filter-row { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.7px; }
        .filter-group select,
        .filter-group input[type="date"] { background: var(--navy); border: 1px solid var(--navy-line); color: var(--text-primary); border-radius: var(--radius-sm); padding: 7px 12px; font-size: 13px; font-family: var(--font-main); outline: none; cursor: pointer; transition: border-color 0.2s; min-width: 140px; }
        .filter-group select:focus,
        .filter-group input[type="date"]:focus { border-color: var(--accent); }
        .filter-group select option { background: var(--navy-card); }
        .btn-filter { display: inline-flex; align-items: center; gap: 7px; padding: 8px 18px; border-radius: var(--radius-sm); border: none; font-size: 13px; font-weight: 600; font-family: var(--font-main); cursor: pointer; transition: all 0.2s; }
        .btn-primary-filter { background: var(--accent); color: white; }
        .btn-primary-filter:hover { background: #2388e8; }
        .btn-reset-filter { background: transparent; color: var(--text-secondary); border: 1px solid var(--navy-line) !important; border: none; }
        .btn-reset-filter:hover { background: var(--navy-hover); color: var(--text-primary); }

        /* ── TABS (TIPE) ── */
        .tabs { display: flex; gap: 6px; margin-bottom: 18px; }
        .tab { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; padding: 6px 16px; border-radius: 20px; text-decoration: none; transition: all 0.2s; border: 1px solid transparent; cursor: pointer; }
        .tab-active-semua    { background: var(--accent-dim);  color: var(--accent);  border-color: rgba(59,158,255,0.3); }
        .tab-active-penukar  { background: var(--amber-dim);   color: var(--amber);   border-color: rgba(245,158,11,0.3); }
        .tab-active-pengganti{ background: var(--purple-dim);  color: var(--purple);  border-color: rgba(167,139,250,0.3); }
        .tab-default { color: var(--text-muted); }
        .tab-default:hover { background: var(--navy-card); color: var(--text-secondary); border-color: var(--navy-line); }
        .tab-count { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 20px; background: rgba(255,255,255,0.1); }

        /* ── PANEL ── */
        .panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; margin-bottom: 24px; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .panel-badge { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 20px; }
        .pb-blue  { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); }
        .pb-amber { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.3); }
        .pb-red   { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.3); }
        .panel-body { padding: 0; }
        .panel-empty { padding: 48px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }
        .panel-empty i { font-size: 28px; display: block; margin-bottom: 10px; color: var(--text-muted); }

        /* ── TABLE ── */
        .tbl-wrap { overflow-x: auto; }
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 860px; }
        .tbl th { padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); white-space: nowrap; }
        .tbl td { padding: 12px 16px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .tbl .name-cell { color: var(--text-primary); font-weight: 500; }
        .tbl .mono { font-family: var(--font-mono); font-size: 12px; }

        /* ── BADGE ── */
        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; white-space: nowrap; }
        .b-green   { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-amber   { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .b-red     { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-blue    { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .b-purple  { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .b-teal    { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .b-muted   { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        /* ── ARROW BETWEEN SHIFTS ── */
        .shift-swap { display: flex; align-items: center; gap: 8px; }
        .shift-swap .arrow { color: var(--text-muted); font-size: 11px; }

        /* ── AVATAR PAIR ── */
        .user-pair { display: flex; flex-direction: column; gap: 4px; }
        .user-line { display: flex; align-items: center; gap: 7px; }
        .mini-avatar { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; flex-shrink: 0; }
        .ma-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); }
        .ma-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }
        .ma-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.3); }
        .user-line-name  { font-size: 12px; color: var(--text-primary); font-weight: 500; }
        .user-line-role  { font-size: 10px; color: var(--text-muted); }

        /* ── 2-COL INSIGHT ── */
        .insight-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .insight-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--navy-line); }
        .insight-item:last-child { border-bottom: none; }
        .insight-name { font-size: 13px; color: var(--text-primary); }
        .insight-bar-wrap { display: flex; align-items: center; gap: 10px; }
        .insight-bar { height: 6px; border-radius: 3px; background: var(--accent); min-width: 4px; }
        .insight-val { font-family: var(--font-mono); font-size: 12px; color: var(--text-secondary); min-width: 24px; text-align: right; }

        /* ── EXPORT BTN ── */
        .btn-export { display: inline-flex; align-items: center; gap: 7px; padding: 7px 16px; border-radius: var(--radius-sm); border: 1px solid rgba(34,197,94,0.35); background: var(--green-dim); color: var(--green); font-size: 12px; font-weight: 600; font-family: var(--font-main); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-export:hover { background: var(--green); color: #0f1b2d; border-color: var(--green); text-decoration: none; }

        /* ── DETAIL LINK ── */
        .btn-detail { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid rgba(59,158,255,0.3); background: var(--accent-dim); color: var(--accent); text-decoration: none; transition: all 0.18s; }
        .btn-detail:hover { background: var(--accent); color: #0f1b2d; }

        /* ── NO DATA ── */
        .empty-state { padding: 60px 20px; text-align: center; }
        .empty-icon { font-size: 36px; margin-bottom: 14px; color: var(--text-muted); }
        .empty-title { font-size: 15px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .empty-sub { font-size: 13px; color: var(--text-muted); }

        @media (max-width: 900px) {
            .stat-grid    { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .insight-grid { grid-template-columns: 1fr; }
            .filter-row   { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 768px) {
            .navbar { padding: 0 16px; }
            .main   { padding: 20px 14px 50px; }
            .user-name { display: none; }
            .page-header { flex-direction: column; }
            .tabs { flex-wrap: wrap; }
        }
        @media (max-width: 480px) {
            .stat-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
    <div class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </div>
    <div class="navbar-right">
        <a href="index.php" class="btn-nav"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'KK', 0, 2)) ?></div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['name'] ?? '') ?></span>
            <span class="role-chip">Kepala Kantor</span>
        </div>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:9px;"></i>
                <span>Penukaran Shift</span>
            </div>
            <div class="page-title">
                <div class="page-title-icon"><i class="fas fa-exchange-alt"></i></div>
                Riwayat Penukaran Shift
            </div>
            <div class="page-sub">
                Pantau dan lacak seluruh aktivitas tukar jadwal antar Pamdal
            </div>
        </div>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-export">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>

    <!-- ── STATISTIK ── -->
    <div class="section-title">Ringkasan Periode</div>
    <div class="stat-grid" style="margin-bottom:28px;">
        <div class="stat-card c-red">
            <div class="stat-icon si-red"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-number"><?= $total_semua ?></div>
            <div class="stat-label">Total Penukaran</div>
        </div>
        <div class="stat-card c-amber">
            <div class="stat-icon si-amber"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div class="stat-number"><?= $total_penukar ?></div>
            <div class="stat-label">Sebagai Penukar</div>
        </div>
        <div class="stat-card c-purple">
            <div class="stat-icon si-purple"><i class="fas fa-person-walking-arrow-loop-left"></i></div>
            <div class="stat-number"><?= $total_pengganti ?></div>
            <div class="stat-label">Sebagai Pengganti</div>
        </div>
        <div class="stat-card c-blue">
            <div class="stat-icon si-blue"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= count(array_unique(array_column($data_penukaran, 'nama_pemilik'))) ?></div>
            <div class="stat-label">Pamdal Terlibat</div>
        </div>
    </div>

    <!-- ── INSIGHT: TOP SHIFT & TOP PAMDAL ── -->
    <?php if (!empty($shift_count) || !empty($pamdal_count)): ?>
    <div class="section-title">Analisis Penukaran</div>
    <div class="insight-grid" style="margin-bottom:28px;">

        <!-- Top shift -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fas fa-chart-bar" style="color:var(--amber);"></i>
                    Shift Paling Sering Ditukar
                </span>
            </div>
            <div class="panel-body" style="padding:10px 20px;">
                <?php
                $max_sc = max(array_values($shift_count) ?: [1]);
                foreach ($shift_count as $nm => $cnt): ?>
                <div class="insight-item">
                    <span class="insight-name"><?= htmlspecialchars($nm) ?></span>
                    <div class="insight-bar-wrap">
                        <div class="insight-bar" style="width:<?= max(8, round(($cnt/$max_sc)*100)) ?>px; background:var(--amber);"></div>
                        <span class="insight-val"><?= $cnt ?>×</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($shift_count)): ?>
                    <p style="padding:16px 0; font-size:13px; color:var(--text-muted); text-align:center;">Tidak ada data</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top pamdal -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fas fa-user-clock" style="color:var(--purple);"></i>
                    Pamdal Paling Sering Tukar
                </span>
            </div>
            <div class="panel-body" style="padding:10px 20px;">
                <?php
                $max_pc = max(array_values($pamdal_count) ?: [1]);
                $top5   = array_slice($pamdal_count, 0, 5, true);
                foreach ($top5 as $nm => $cnt): ?>
                <div class="insight-item">
                    <span class="insight-name"><?= htmlspecialchars($nm) ?></span>
                    <div class="insight-bar-wrap">
                        <div class="insight-bar" style="width:<?= max(8, round(($cnt/$max_pc)*100)) ?>px; background:var(--purple);"></div>
                        <span class="insight-val"><?= $cnt ?>×</span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($pamdal_count)): ?>
                    <p style="padding:16px 0; font-size:13px; color:var(--text-muted); text-align:center;">Tidak ada data</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <!-- ── FILTER ── -->
    <div class="filter-panel">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="dari" value="<?= htmlspecialchars($filter_dari) ?>">
                </div>
                <div class="filter-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="sampai" value="<?= htmlspecialchars($filter_sampai) ?>">
                </div>
                <div class="filter-group">
                    <label>Tipe Penukaran</label>
                    <select name="tipe">
                        <option value="semua"     <?= $filter_tipe === 'semua'     ? 'selected' : '' ?>>Semua Tipe</option>
                        <option value="penukar"   <?= $filter_tipe === 'penukar'   ? 'selected' : '' ?>>Penukar</option>
                        <option value="pengganti" <?= $filter_tipe === 'pengganti' ? 'selected' : '' ?>>Pengganti</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <button type="submit" class="btn-filter btn-primary-filter">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="penukaran_shift.php" class="btn-filter btn-reset-filter" style="border:1px solid var(--navy-line);">
                        <i class="fas fa-rotate-left"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- ── TABS ── -->
    <div class="tabs">
        <?php
        $tab_configs = [
            ['val' => 'semua',     'label' => 'Semua',      'active_cls' => 'tab-active-semua',     'cnt' => $total_semua],
            ['val' => 'penukar',   'label' => 'Penukar',    'active_cls' => 'tab-active-penukar',   'cnt' => $total_penukar],
            ['val' => 'pengganti', 'label' => 'Pengganti',  'active_cls' => 'tab-active-pengganti', 'cnt' => $total_pengganti],
        ];
        foreach ($tab_configs as $tc):
            $q     = array_merge($_GET, ['tipe' => $tc['val']]);
            $href  = '?' . http_build_query($q);
            $aktif = ($filter_tipe === $tc['val']);
        ?>
        <a href="<?= $href ?>" class="tab <?= $aktif ? $tc['active_cls'] : 'tab-default' ?>">
            <?= $tc['label'] ?>
            <span class="tab-count"><?= $tc['cnt'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── TABEL PENUKARAN ── -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fas fa-list" style="color:var(--accent);"></i>
                Daftar Penukaran Shift
                <span style="font-size:11px; color:var(--text-muted); font-weight:400;">
                    (<?= date('d/m/Y', strtotime($filter_dari)) ?> – <?= date('d/m/Y', strtotime($filter_sampai)) ?>)
                </span>
            </span>
            <span class="panel-badge pb-blue"><?= count($data_penukaran) ?> data</span>
        </div>
        <div class="panel-body">
            <?php if (empty($data_penukaran)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-calendar-xmark"></i></div>
                <div class="empty-title">Tidak ada data penukaran shift</div>
                <div class="empty-sub">Belum ada aktivitas penukaran shift pada periode yang dipilih.</div>
            </div>
            <?php else: ?>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tgl Absensi</th>
                            <th>Pamdal Penukar</th>
                            <th>Pamdal Pengganti</th>
                            <th>Shift Asli</th>
                            <th>Shift Pengganti</th>
                            <th>Tipe</th>
                            <th>Status Masuk</th>
                            <th>Laporan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($data_penukaran as $i => $row): ?>
                        <tr>
                            <!-- No -->
                            <td class="mono" style="color:var(--text-muted);"><?= $i + 1 ?></td>

                            <!-- Tanggal Absensi -->
                            <td>
                                <span class="mono"><?= date('d/m/Y', strtotime($row['tanggal_absensi'])) ?></span>
                                <?php if ($row['jam_masuk']): ?>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                        <?= date('H:i', strtotime($row['jam_masuk'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Pamdal Penukar -->
                            <td>
                                <div class="user-line">
                                    <div class="mini-avatar ma-blue"><?= strtoupper(substr($row['nama_pemilik'], 0, 2)) ?></div>
                                    <div>
                                        <div class="user-line-name"><?= htmlspecialchars($row['nama_pemilik']) ?></div>
                                        <div class="user-line-role">Penukar</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Pamdal Pengganti -->
                            <td>
                                <div class="user-line">
                                    <div class="mini-avatar ma-purple"><?= strtoupper(substr($row['nama_pengganti'], 0, 2)) ?></div>
                                    <div>
                                        <div class="user-line-name"><?= htmlspecialchars($row['nama_pengganti']) ?></div>
                                        <div class="user-line-role">Pengganti</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Shift Asli -->
                            <td>
                                <span class="badge b-blue"><?= htmlspecialchars($row['shift_asli']) ?></span>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:3px; font-family:var(--font-mono);">
                                    <?= substr($row['jam_masuk_shift_asli'],0,5) ?> – <?= substr($row['jam_keluar_shift_asli'],0,5) ?>
                                </div>
                            </td>

                            <!-- Shift Pengganti -->
                            <td>
                                <span class="badge b-purple"><?= htmlspecialchars($row['shift_tukar']) ?></span>
                                <div style="font-size:11px; color:var(--text-muted); margin-top:3px; font-family:var(--font-mono);">
                                    <?= substr($row['jam_masuk_shift_tukar'],0,5) ?> – <?= substr($row['jam_keluar_shift_tukar'],0,5) ?>
                                </div>
                            </td>

                            <!-- Tipe -->
                            <td>
                                <?php if ($row['tipe'] === 'penukar'): ?>
                                    <span class="badge b-amber"><i class="fas fa-arrow-right-from-bracket" style="font-size:9px;"></i> Penukar</span>
                                <?php else: ?>
                                    <span class="badge b-purple"><i class="fas fa-arrow-right-to-bracket" style="font-size:9px;"></i> Pengganti</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status Masuk -->
                            <td>
                                <?php if ($row['keterangan_masuk'] === 'terlambat'): ?>
                                    <span class="badge b-amber"><i class="fas fa-clock" style="font-size:9px;"></i> Terlambat</span>
                                <?php else: ?>
                                    <span class="badge b-green"><i class="fas fa-check" style="font-size:9px;"></i> Tepat Waktu</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status Laporan -->
                            <td>
                                <?php
                                    $sl = $row['status_laporan'] ?? '';
                                    if ($sl === 'acc'):
                                ?>
                                    <span class="badge b-green">ACC</span>
                                <?php elseif ($sl === 'pending'): ?>
                                    <span class="badge b-amber">Pending</span>
                                <?php elseif ($sl === 'revisi'): ?>
                                    <span class="badge b-red">Revisi</span>
                                <?php else: ?>
                                    <span class="badge b-muted">Belum Ada</span>
                                <?php endif; ?>
                            </td>

                            <!-- Aksi -->
                            <td>
                                <a href="detail_laporan.php?absensi_id=<?= $row['absensi_id'] ?>" class="btn-detail">
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
// ── EXPORT CSV ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($data_penukaran)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=penukaran_shift_' . date('Ymd') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['No', 'Tgl Absensi', 'Pamdal Penukar', 'Pamdal Pengganti', 'Shift Asli', 'Shift Pengganti', 'Tipe', 'Status Masuk', 'Status Laporan']);
    foreach ($data_penukaran as $i => $row) {
        fputcsv($out, [
            $i + 1,
            date('d/m/Y', strtotime($row['tanggal_absensi'])),
            $row['nama_pemilik'],
            $row['nama_pengganti'],
            $row['shift_asli'],
            $row['shift_tukar'],
            ucfirst($row['tipe']),
            ucfirst($row['keterangan_masuk']),
            $row['status_laporan'] ?? '-',
        ]);
    }
    fclose($out);
    exit;
}
?>
</body>
</html>
