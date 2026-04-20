<?php
// laporan_masuk.php – Tinjauan Laporan (Kepala Kantor)
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

$pesan_sukses = '';
$pesan_error  = '';

// ── PROSES AKSI (ACC / REVISI) ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $laporan_id = (int)($_POST['laporan_id'] ?? 0);
    $aksi       = cleanInput($_POST['aksi']);
    $catatan    = cleanInput($_POST['catatan'] ?? '');

    if ($laporan_id <= 0) {
        $pesan_error = 'ID laporan tidak valid.';
    } elseif (!in_array($aksi, ['acc', 'revisi'])) {
        $pesan_error = 'Aksi tidak valid.';
    } elseif ($aksi === 'revisi' && empty($catatan)) {
        $pesan_error = 'Catatan revisi wajib diisi saat memberikan keputusan Revisi.';
    } else {
        $status_approval = ($aksi === 'acc') ? 'diterima' : 'ditolak';
        $result = buatApproval(
            approved_by: (int)$_SESSION['user_id'],
            status:      $status_approval,
            catatan:     $catatan,
            laporan_id:  $laporan_id
        );

        if ($result['success']) {
            $pesan_sukses = ($aksi === 'acc')
                ? 'Laporan berhasil di-ACC.'
                : 'Laporan dikembalikan untuk direvisi. Catatan telah dikirim ke pamdal.';
        } else {
            $pesan_error = $result['message'];
        }
    }
}

// ── FILTER ────────────────────────────────────────────────────────────────────
$filter_status = cleanInput($_GET['status'] ?? 'pending');
$filter_shift  = (int)($_GET['shift_id'] ?? 0);
$filter_dari   = cleanInput($_GET['dari']   ?? '');
$filter_sampai = cleanInput($_GET['sampai'] ?? '');
$cari          = cleanInput($_GET['cari']   ?? '');

// Validasi status
if (!in_array($filter_status, ['semua', 'pending', 'acc', 'revisi'])) {
    $filter_status = 'pending';
}

// ── AMBIL DATA LAPORAN ────────────────────────────────────────────────────────
$laporan_raw = getAllLaporan($filter_status);

// Filter tambahan (shift, tanggal, nama)
$laporan = array_filter($laporan_raw, function($l) use ($filter_shift, $filter_dari, $filter_sampai, $cari) {
    if ($filter_shift > 0) {
        // Ambil shift_id dari absensi — kita sudah JOIN di getAllLaporan (ada nama_shift)
        // Perlu absensi join, kita filter by nama_shift tidak cukup; pakai query sendiri
    }
    if ($filter_dari && $l['tanggal'] < $filter_dari) return false;
    if ($filter_sampai && $l['tanggal'] > $filter_sampai) return false;
    if ($cari && stripos($l['nama_user'], $cari) === false) return false;
    return true;
});

// Filter shift jika dipilih
if ($filter_shift > 0) {
    global $conn;
    $laporan = array_filter($laporan, function($l) use ($filter_shift, $conn) {
        $aid = (int)$l['absensi_id'];
        $r   = $conn->query("SELECT shift_id FROM absensi WHERE id = $aid LIMIT 1");
        if ($r && $r->num_rows > 0) {
            return (int)$r->fetch_assoc()['shift_id'] === $filter_shift;
        }
        return false;
    });
}

$laporan = array_values($laporan);

// Untuk tampilan detail modal — ambil per laporan_id jika ada
$detail_laporan = null;
if (isset($_GET['detail'])) {
    $did = (int)$_GET['detail'];
    $detail_laporan = getLaporanByAbsensi(
        // kita perlu cari absensi_id dari laporan id
        (function() use ($did, $conn) {
            $r = $conn->query("SELECT absensi_id FROM laporan WHERE id = $did LIMIT 1");
            return ($r && $r->num_rows > 0) ? (int)$r->fetch_assoc()['absensi_id'] : 0;
        })()
    );
    // Juga ambil langsung by laporan id
    if (!$detail_laporan) {
        $r = $conn->query(
            "SELECT l.*, a.tanggal, a.jam_masuk, a.jam_keluar, a.status_masuk, a.keterangan_masuk,
                    a.status_keluar, a.alasan_pulang_awal, a.is_double_shift,
                    u.name AS nama_user, u.username,
                    s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar
             FROM laporan l
             JOIN absensi a ON l.absensi_id = a.id
             JOIN users u   ON a.user_id = u.id
             JOIN shift s   ON a.shift_id = s.id
             WHERE l.id = $did LIMIT 1"
        );
        if ($r && $r->num_rows > 0) $detail_laporan = $r->fetch_assoc();
    }
}

$shifts       = getAllShifts();
$total_semua  = count(getAllLaporan('semua'));
$total_pending= count(getAllLaporan('pending'));
$total_acc    = count(getAllLaporan('acc'));
$total_revisi = count(getAllLaporan('revisi'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Laporan Masuk — ANDALAN</title>
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
        .navbar { position: sticky; top: 0; z-index: 200; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; text-decoration: none; }
        .brand-icon { width: 32px; height: 32px; background: var(--gold); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #0f1b2d; }
        .navbar-right { display: flex; align-items: center; gap: 16px; }
        .user-avatar { width: 34px; height: 34px; background: var(--gold-dim); border: 1px solid var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; color: var(--gold); font-weight: 600; }
        .user-name { font-size: 13px; font-weight: 500; color: var(--text-primary); }
        .role-chip { font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; background: var(--gold-dim); color: var(--gold); border: 1px solid rgba(251,191,36,0.35); }
        .btn-back { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-back:hover { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); text-decoration: none; }
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); text-decoration: none; }

        /* ── MAIN ── */
        .main { max-width: 1120px; margin: 0 auto; padding: 32px 20px 60px; }

        /* ── PAGE HEADER ── */
        .page-header { margin-bottom: 28px; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .page-title { font-size: 22px; font-weight: 600; color: var(--text-primary); }
        .page-sub   { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }
        .breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); margin-bottom: 6px; }
        .breadcrumb a { color: var(--accent); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── ALERT ── */
        .alert { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-radius: var(--radius-lg); font-size: 13px; line-height: 1.5; margin-bottom: 20px; }
        .alert-success { background: var(--green-dim);  color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        .alert-error   { background: var(--red-dim);    color: var(--red);   border: 1px solid rgba(244,63,94,0.3); }
        .alert i { font-size: 16px; margin-top: 1px; flex-shrink: 0; }

        /* ── STAT TAB BAR ── */
        .stat-tabs { display: flex; gap: 10px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-tab { display: flex; align-items: center; gap: 10px; padding: 12px 18px; background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); text-decoration: none; color: var(--text-secondary); font-size: 13px; font-weight: 500; transition: all 0.2s; cursor: pointer; }
        .stat-tab:hover { background: var(--navy-hover); color: var(--text-primary); text-decoration: none; }
        .stat-tab.active { color: var(--text-primary); }
        .stat-tab.active.t-semua  { border-color: rgba(59,158,255,0.5);  background: var(--accent-dim); color: var(--accent); }
        .stat-tab.active.t-pending{ border-color: rgba(245,158,11,0.5);  background: var(--amber-dim);  color: var(--amber); }
        .stat-tab.active.t-acc    { border-color: rgba(34,197,94,0.5);   background: var(--green-dim);  color: var(--green); }
        .stat-tab.active.t-revisi { border-color: rgba(244,63,94,0.5);   background: var(--red-dim);    color: var(--red); }
        .tab-count { font-family: var(--font-mono); font-size: 15px; font-weight: 600; }

        /* ── FILTER BAR ── */
        .filter-bar { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 16px 18px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); }
        .filter-input, .filter-select {
            height: 36px; padding: 0 12px; border-radius: var(--radius-sm);
            background: var(--navy); border: 1px solid var(--navy-line);
            color: var(--text-primary); font-family: var(--font-main); font-size: 13px;
            outline: none; transition: border-color 0.2s;
        }
        .filter-input:focus, .filter-select:focus { border-color: var(--accent); }
        .filter-select option { background: var(--navy-mid); }
        .filter-input { width: 180px; }
        .btn-filter { height: 36px; padding: 0 18px; border-radius: var(--radius-sm); background: var(--accent); border: none; color: white; font-family: var(--font-main); font-size: 13px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: opacity 0.2s; }
        .btn-filter:hover { opacity: 0.88; }
        .btn-reset { height: 36px; padding: 0 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-muted); font-family: var(--font-main); font-size: 13px; cursor: pointer; transition: all 0.2s; text-decoration: none; display: flex; align-items: center; gap: 6px; }
        .btn-reset:hover { border-color: var(--red); color: var(--red); text-decoration: none; }

        /* ── PANEL ── */
        .panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .panel-badge { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 20px; }
        .pb-amber { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .pb-blue  { background: var(--accent-dim);color: var(--accent);border: 1px solid rgba(59,158,255,0.3); }
        .panel-empty { padding: 48px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }

        /* ── TABLE ── */
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tbl th { padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); white-space: nowrap; }
        .tbl td { padding: 13px 16px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .tbl .name-cell { color: var(--text-primary); font-weight: 500; }
        .tbl .mono { font-family: var(--font-mono); font-size: 12px; }
        .preview-text { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; color: var(--text-muted); }

        /* ── BADGE ── */
        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; white-space: nowrap; }
        .b-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .b-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .b-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        /* ── ACTION BUTTONS ── */
        .btn-tinjau { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 5px 13px; border-radius: var(--radius-sm); border: 1px solid rgba(59,158,255,0.35); background: var(--accent-dim); color: var(--accent); text-decoration: none; transition: all 0.18s; cursor: pointer; }
        .btn-tinjau:hover { background: var(--accent); color: white; border-color: var(--accent); text-decoration: none; }
        .btn-acc    { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 5px 13px; border-radius: var(--radius-sm); border: 1px solid rgba(34,197,94,0.35); background: var(--green-dim); color: var(--green); cursor: pointer; transition: all 0.18s; }
        .btn-acc:hover { background: var(--green); color: #0f1b2d; border-color: var(--green); }
        .btn-rev    { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 5px 13px; border-radius: var(--radius-sm); border: 1px solid rgba(244,63,94,0.35); background: var(--red-dim); color: var(--red); cursor: pointer; transition: all 0.18s; }
        .btn-rev:hover { background: var(--red); color: white; border-color: var(--red); }
        .btn-group { display: flex; gap: 6px; flex-wrap: wrap; }

        /* ── MODAL OVERLAY ── */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 500; display: flex; align-items: center; justify-content: center; padding: 20px; opacity: 0; pointer-events: none; transition: opacity 0.25s; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); width: 100%; max-width: 620px; max-height: 90vh; overflow-y: auto; box-shadow: 0 40px 80px rgba(0,0,0,0.5); transform: translateY(20px); transition: transform 0.25s; }
        .modal-overlay.open .modal { transform: translateY(0); }
        .modal-head { padding: 20px 24px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: var(--navy-card); z-index: 2; }
        .modal-title { font-size: 15px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
        .modal-close { width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--navy-line); background: transparent; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.2s; }
        .modal-close:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }
        .modal-body { padding: 24px; }
        .modal-section { margin-bottom: 20px; }
        .modal-section-title { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--navy-line); }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .info-item label { font-size: 11px; color: var(--text-muted); display: block; margin-bottom: 3px; }
        .info-item span  { font-size: 13px; color: var(--text-primary); font-weight: 500; }
        .laporan-box { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 16px; font-size: 13px; color: var(--text-secondary); line-height: 1.7; white-space: pre-wrap; word-break: break-word; max-height: 200px; overflow-y: auto; }
        .catatan-revisi-box { background: var(--red-dim); border: 1px solid rgba(244,63,94,0.25); border-radius: var(--radius-lg); padding: 14px 16px; font-size: 13px; color: var(--red); line-height: 1.6; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--navy-line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }

        /* ── FORM AKSI ── */
        .form-aksi { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 16px; margin-top: 16px; }
        .form-aksi-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; }
        .form-textarea { width: 100%; min-height: 80px; padding: 10px 12px; background: var(--navy-mid); border: 1px solid var(--navy-line); border-radius: var(--radius-sm); color: var(--text-primary); font-family: var(--font-main); font-size: 13px; resize: vertical; outline: none; transition: border-color 0.2s; }
        .form-textarea:focus { border-color: var(--accent); }
        .form-textarea::placeholder { color: var(--text-muted); }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 6px; }
        .form-row { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
        .btn-submit-acc { display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border-radius: var(--radius-sm); background: var(--green); border: none; color: #0f1b2d; font-family: var(--font-main); font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .btn-submit-acc:hover { opacity: 0.88; }
        .btn-submit-rev { display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border-radius: var(--radius-sm); background: var(--red); border: none; color: white; font-family: var(--font-main); font-size: 13px; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .btn-submit-rev:hover { opacity: 0.88; }

        /* ── PAGINATION ── */
        .paging { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-top: 1px solid var(--navy-line); font-size: 12px; color: var(--text-muted); }
        .paging-info { }
        .paging-nav { display: flex; gap: 6px; }
        .paging-btn { padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid var(--navy-line); background: transparent; color: var(--text-secondary); font-size: 12px; cursor: pointer; transition: all 0.18s; text-decoration: none; }
        .paging-btn:hover { border-color: var(--accent); color: var(--accent); text-decoration: none; }
        .paging-btn.active { background: var(--accent); border-color: var(--accent); color: white; }

        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-input { width: 100%; }
            .stat-tabs { gap: 8px; }
            .stat-tab { padding: 10px 14px; font-size: 12px; }
            .tbl th:nth-child(5), .tbl td:nth-child(5),
            .tbl th:nth-child(4), .tbl td:nth-child(4) { display: none; }
            .navbar { padding: 0 16px; }
            .main  { padding: 20px 14px 50px; }
            .user-name { display: none; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </a>
    <div class="navbar-right">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <div style="display:flex;align-items:center;gap:10px;">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'KK', 0, 2)) ?></div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></span>
            <span class="role-chip">Kepala Kantor</span>
        </div>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- ── PAGE HEADER ── -->
    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:9px;"></i>
                <span>Laporan Masuk</span>
            </div>
            <div class="page-title"><i class="fas fa-inbox" style="color:var(--amber);margin-right:8px;"></i>Laporan Masuk</div>
            <div class="page-sub">Tinjau, ACC, atau kembalikan laporan harian pamdal</div>
        </div>
    </div>

    <!-- ── ALERT ── -->
    <?php if ($pesan_sukses): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div><?= htmlspecialchars($pesan_sukses) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($pesan_error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <div><?= htmlspecialchars($pesan_error) ?></div>
    </div>
    <?php endif; ?>

    <!-- ── STAT TABS ── -->
    <div class="stat-tabs">
        <?php
        $tab_defs = [
            ['key'=>'semua',   'label'=>'Semua',   'count'=>$total_semua,   'cls'=>'t-semua',   'icon'=>'fa-list'],
            ['key'=>'pending', 'label'=>'Pending',  'count'=>$total_pending, 'cls'=>'t-pending', 'icon'=>'fa-clock'],
            ['key'=>'acc',     'label'=>'Sudah ACC','count'=>$total_acc,     'cls'=>'t-acc',     'icon'=>'fa-check-circle'],
            ['key'=>'revisi',  'label'=>'Revisi',   'count'=>$total_revisi,  'cls'=>'t-revisi',  'icon'=>'fa-redo'],
        ];
        $params = $_GET;
        foreach ($tab_defs as $t):
            $params['status'] = $t['key'];
            unset($params['detail']);
            $active = ($filter_status === $t['key']);
        ?>
        <a href="?<?= http_build_query($params) ?>" class="stat-tab <?= $t['cls'] ?> <?= $active ? 'active' : '' ?>">
            <i class="fas <?= $t['icon'] ?>"></i>
            <?= $t['label'] ?>
            <span class="tab-count"><?= $t['count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── FILTER BAR ── -->
    <form method="GET" action="">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Cari Pamdal</span>
                <input type="text" name="cari" class="filter-input" placeholder="Nama pamdal..." value="<?= htmlspecialchars($cari) ?>">
            </div>
            <div class="filter-group">
                <span class="filter-label">Shift</span>
                <select name="shift_id" class="filter-select">
                    <option value="0">Semua Shift</option>
                    <?php foreach ($shifts as $sh): ?>
                        <option value="<?= $sh['id'] ?>" <?= $filter_shift === (int)$sh['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sh['nama_shift']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Dari Tanggal</span>
                <input type="date" name="dari" class="filter-input" style="width:150px;" value="<?= htmlspecialchars($filter_dari) ?>">
            </div>
            <div class="filter-group">
                <span class="filter-label">Sampai Tanggal</span>
                <input type="date" name="sampai" class="filter-input" style="width:150px;" value="<?= htmlspecialchars($filter_sampai) ?>">
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            <a href="laporan_masuk.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </div>
    </form>

    <!-- ── TABEL LAPORAN ── -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fas fa-file-alt" style="color:var(--amber);"></i>
                Daftar Laporan
                <?php if ($filter_status !== 'semua'): ?>
                    — <span style="font-weight:400;color:var(--text-secondary);"><?= ucfirst($filter_status) ?></span>
                <?php endif; ?>
            </span>
            <span class="panel-badge pb-blue"><?= count($laporan) ?> laporan</span>
        </div>

        <?php if (empty($laporan)): ?>
            <div class="panel-empty">
                <i class="fas fa-file-circle-check" style="font-size:32px;color:var(--text-muted);display:block;margin-bottom:12px;"></i>
                <?php if ($filter_status === 'pending'): ?>
                    Tidak ada laporan yang menunggu tinjauan. Semua sudah diproses!
                <?php else: ?>
                    Tidak ada laporan ditemukan.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Pamdal</th>
                        <th>Shift</th>
                        <th>Tanggal</th>
                        <th>Pratinjau Laporan</th>
                        <th>Status</th>
                        <th>Dikirim</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($laporan as $i => $lap): ?>
                    <tr>
                        <td class="mono" style="color:var(--text-muted);"><?= $i + 1 ?></td>
                        <td>
                            <div class="name-cell"><?= htmlspecialchars($lap['nama_user']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">@<?= htmlspecialchars($lap['username'] ?? '') ?></div>
                        </td>
                        <td><span class="badge b-blue"><?= htmlspecialchars($lap['nama_shift']) ?></span></td>
                        <td class="mono"><?= date('d/m/Y', strtotime($lap['tanggal'])) ?></td>
                        <td>
                            <div class="preview-text" title="<?= htmlspecialchars($lap['isi_laporan']) ?>">
                                <?= htmlspecialchars($lap['isi_laporan']) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($lap['status'] === 'acc'): ?>
                                <span class="badge b-green"><i class="fas fa-check"></i> ACC</span>
                            <?php elseif ($lap['status'] === 'revisi'): ?>
                                <span class="badge b-red"><i class="fas fa-redo"></i> Revisi</span>
                            <?php else: ?>
                                <span class="badge b-amber"><i class="fas fa-clock"></i> Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="mono" style="font-size:11px;">
                            <?= $lap['created_at'] ? date('d/m H:i', strtotime($lap['created_at'])) : '—' ?>
                        </td>
                        <td>
                            <div class="btn-group">
                                <!-- Tombol Tinjau → buka modal -->
                                <button class="btn-tinjau" onclick="bukaModal(<?= $lap['id'] ?>)">
                                    <i class="fas fa-eye"></i> Tinjau
                                </button>
                                <?php if ($lap['status'] === 'pending'): ?>
                                    <!-- Shortcut langsung ACC tanpa modal -->
                                    <button class="btn-acc" onclick="konfirmasiAcc(<?= $lap['id'] ?>, '<?= htmlspecialchars(addslashes($lap['nama_user'])) ?>')">
                                        <i class="fas fa-check"></i> ACC
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     MODAL DETAIL + AKSI
═══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalOverlay" onclick="tutupModalLuar(event)">
    <div class="modal" id="modalBox">
        <div class="modal-head">
            <div class="modal-title">
                <i class="fas fa-file-alt" style="color:var(--amber);"></i>
                <span id="modalTitleText">Detail Laporan</span>
            </div>
            <button class="modal-close" onclick="tutupModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="font-size:24px;margin-bottom:12px;display:block;"></i>
                Memuat detail laporan...
            </div>
        </div>
    </div>
</div>

<!-- Form tersembunyi untuk ACC/Revisi -->
<form method="POST" action="" id="formAksi" style="display:none;">
    <input type="hidden" name="laporan_id" id="f_laporan_id">
    <input type="hidden" name="aksi"       id="f_aksi">
    <input type="hidden" name="catatan"    id="f_catatan">
</form>

<script>
// ── DATA LAPORAN (PHP → JS) ────────────────────────────────────────────────
const LAPORAN_DATA = <?= json_encode(
    array_map(function($l) use ($conn) {
        // Ambil history approval
        $approvals = getApprovalHistory(null, $l['id']);
        // Ambil data absensi lengkap
        $abs = getAbsensiById((int)$l['absensi_id']);
        return [
            'id'             => $l['id'],
            'absensi_id'     => $l['absensi_id'],
            'nama_user'      => $l['nama_user'],
            'username'       => $l['username'] ?? '',
            'nama_shift'     => $l['nama_shift'],
            'tanggal'        => $l['tanggal'],
            'jam_masuk'      => $l['jam_masuk'] ?? '',
            'jam_keluar'     => $l['jam_keluar'] ?? '',
            'status'         => $l['status'],
            'isi_laporan'    => $l['isi_laporan'],
            'catatan_revisi' => $l['catatan_revisi'] ?? '',
            'created_at'     => $l['created_at'] ?? '',
            'status_masuk'   => $abs['status_masuk'] ?? '',
            'keterangan_masuk'=> $abs['keterangan_masuk'] ?? '',
            'status_keluar'  => $abs['status_keluar'] ?? '',
            'alasan_pulang_awal'=> $abs['alasan_pulang_awal'] ?? '',
            'approvals'      => $approvals,
        ];
    }, $laporan)
) ?>;

const lapMap = {};
LAPORAN_DATA.forEach(l => lapMap[l.id] = l);

// ── BUKA MODAL ────────────────────────────────────────────────────────────
function bukaModal(id) {
    const l = lapMap[id];
    if (!l) return;

    document.getElementById('modalTitleText').textContent = 'Laporan — ' + l.nama_user;
    document.getElementById('modalOverlay').classList.add('open');

    const statusBadge = {
        'acc':     '<span class="badge b-green"><i class="fas fa-check"></i> ACC</span>',
        'revisi':  '<span class="badge b-red"><i class="fas fa-redo"></i> Revisi</span>',
        'pending': '<span class="badge b-amber"><i class="fas fa-clock"></i> Pending</span>',
    }[l.status] || '';

    const statusMasukBadge = l.status_masuk === 'tidak_sesuai'
        ? '<span class="badge b-amber">Tukar Shift</span>'
        : (l.keterangan_masuk === 'terlambat'
            ? '<span class="badge b-amber">Terlambat</span>'
            : '<span class="badge b-green">Tepat Waktu</span>');

    const statusKeluarBadge = {
        'tepat_waktu':  '<span class="badge b-green">Tepat Waktu</span>',
        'pulang_awal':  '<span class="badge b-red">Pulang Awal</span>',
        'lanjut_shift': '<span class="badge" style="background:var(--purple-dim);color:var(--purple);border:1px solid rgba(167,139,250,0.25);">Lanjut Shift</span>',
    }[l.status_keluar] || '<span class="badge b-muted">Belum Keluar</span>';

    const jamMasuk  = l.jam_masuk  ? l.jam_masuk.substr(11,5)  : '—';
    const jamKeluar = l.jam_keluar ? l.jam_keluar.substr(11,5) : '—';
    const tgl = l.tanggal ? new Date(l.tanggal).toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'}) : '—';

    // History approval
    let historyHtml = '';
    if (l.approvals && l.approvals.length > 0) {
        historyHtml = `
        <div class="modal-section">
            <div class="modal-section-title">Riwayat Keputusan</div>
            ${l.approvals.map(a => `
                <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:10px;padding:10px 12px;background:var(--navy);border-radius:var(--radius-sm);border:1px solid var(--navy-line);">
                    <div style="flex:1;">
                        <div style="font-size:12px;font-weight:600;color:var(--text-primary);">${escHtml(a.nama_approver)}</div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${a.created_at ? a.created_at.substr(0,16) : ''}</div>
                        ${a.catatan ? `<div style="font-size:12px;color:var(--text-secondary);margin-top:6px;">${escHtml(a.catatan)}</div>` : ''}
                    </div>
                    <span class="badge ${a.status === 'diterima' ? 'b-green' : 'b-red'}">${a.status === 'diterima' ? 'ACC' : 'Revisi'}</span>
                </div>
            `).join('')}
        </div>`;
    }

    // Catatan revisi jika ada
    let catatanHtml = '';
    if (l.status === 'revisi' && l.catatan_revisi) {
        catatanHtml = `
        <div class="modal-section">
            <div class="modal-section-title" style="color:var(--red);">Catatan Revisi</div>
            <div class="catatan-revisi-box"><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>${escHtml(l.catatan_revisi)}</div>
        </div>`;
    }

    // Form aksi (hanya tampil jika pending)
    let formAksiHtml = '';
    if (l.status === 'pending') {
        formAksiHtml = `
        <div class="form-aksi">
            <div class="form-aksi-title"><i class="fas fa-gavel" style="margin-right:6px;color:var(--amber);"></i>Berikan Keputusan</div>
            <textarea class="form-textarea" id="catatanInput_${l.id}" placeholder="Catatan (wajib diisi jika Revisi, opsional jika ACC)..."></textarea>
            <div class="form-hint">Catatan akan dikirim ke pamdal jika memilih Revisi.</div>
            <div class="form-row">
                <button class="btn-submit-acc" onclick="submitAksi(${l.id}, 'acc')">
                    <i class="fas fa-check-double"></i> ACC Laporan
                </button>
                <button class="btn-submit-rev" onclick="submitAksi(${l.id}, 'revisi')">
                    <i class="fas fa-redo"></i> Kembalikan untuk Revisi
                </button>
            </div>
        </div>`;
    }

    // Alasan pulang awal
    let alasanHtml = '';
    if (l.alasan_pulang_awal) {
        alasanHtml = `<div class="info-item" style="grid-column:span 2;"><label>Alasan Pulang Awal</label><span>${escHtml(l.alasan_pulang_awal)}</span></div>`;
    }

    document.getElementById('modalBody').innerHTML = `
        <div class="modal-section">
            <div class="modal-section-title">Informasi Pamdal & Absensi</div>
            <div class="info-grid">
                <div class="info-item"><label>Nama</label><span>${escHtml(l.nama_user)}</span></div>
                <div class="info-item"><label>Username</label><span>@${escHtml(l.username)}</span></div>
                <div class="info-item"><label>Shift</label><span class="badge b-blue">${escHtml(l.nama_shift)}</span></div>
                <div class="info-item"><label>Tanggal</label><span>${tgl}</span></div>
                <div class="info-item"><label>Absen Masuk</label><span>${jamMasuk}</span></div>
                <div class="info-item"><label>Absen Keluar</label><span>${jamKeluar}</span></div>
                <div class="info-item"><label>Status Masuk</label>${statusMasukBadge}</div>
                <div class="info-item"><label>Status Keluar</label>${statusKeluarBadge}</div>
                ${alasanHtml}
            </div>
        </div>

        <div class="modal-section">
            <div class="modal-section-title">Isi Laporan</div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <span style="font-size:11px;color:var(--text-muted);">Dikirim: ${l.created_at ? l.created_at.substr(0,16) : '—'}</span>
                ${statusBadge}
            </div>
            <div class="laporan-box">${escHtml(l.isi_laporan)}</div>
        </div>

        ${catatanHtml}
        ${historyHtml}
        ${formAksiHtml}
    `;
}

// ── TUTUP MODAL ───────────────────────────────────────────────────────────
function tutupModal() {
    document.getElementById('modalOverlay').classList.remove('open');
}
function tutupModalLuar(e) {
    if (e.target === document.getElementById('modalOverlay')) tutupModal();
}

// ── SUBMIT ACC / REVISI ───────────────────────────────────────────────────
function submitAksi(id, aksi) {
    const catatanEl = document.getElementById('catatanInput_' + id);
    const catatan   = catatanEl ? catatanEl.value.trim() : '';

    if (aksi === 'revisi' && !catatan) {
        catatanEl.style.borderColor = 'var(--red)';
        catatanEl.focus();
        catatanEl.placeholder = '⚠ Catatan wajib diisi untuk Revisi!';
        return;
    }

    const konfirm = aksi === 'acc'
        ? 'ACC laporan ini? Pamdal akan mendapat konfirmasi laporan diterima.'
        : 'Kembalikan laporan ini untuk direvisi? Catatan akan dikirim ke pamdal.';

    if (!confirm(konfirm)) return;

    document.getElementById('f_laporan_id').value = id;
    document.getElementById('f_aksi').value       = aksi;
    document.getElementById('f_catatan').value    = catatan;
    document.getElementById('formAksi').submit();
}

// ── KONFIRMASI ACC CEPAT (dari tabel) ────────────────────────────────────
function konfirmasiAcc(id, nama) {
    if (!confirm('ACC laporan dari ' + nama + '? Laporan akan langsung diterima tanpa catatan.')) return;
    document.getElementById('f_laporan_id').value = id;
    document.getElementById('f_aksi').value       = 'acc';
    document.getElementById('f_catatan').value    = '';
    document.getElementById('formAksi').submit();
}

// ── HELPER ───────────────────────────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// Tutup modal dengan ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupModal(); });
</script>
</body>
</html>