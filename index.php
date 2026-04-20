<?php
// index.php – Dashboard Kepala Kantor
require_once 'config.php';
require_once 'functions.php';
requireLogin();

// Hanya kepala_kantor dan super_admin yang boleh akses
if (!hasAccess([ROLE_SUPER_ADMIN])) {
    header('Location: dashboard.php');
    exit;
}

$tanggal_hari_ini = date('Y-m-d');
$stats            = getStatistikHarian($tanggal_hari_ini);

// Laporan pending & revisi
$laporan_pending = getAllLaporan('pending');
$laporan_revisi  = getAllLaporan('revisi');

// Semua absensi hari ini
$absensi_hari_ini = getAllAbsensi($tanggal_hari_ini);

// Ringkasan per shift
$shifts    = getAllShifts();
$shift_map = [];
foreach ($shifts as $s) {
    $shift_map[$s['id']] = $s;
}

// Hitung total pamdal aktif
$semua_pamdal = getAllPamdal();
$total_pamdal = count($semua_pamdal);

// Shift aktif berdasarkan jam (untuk header badge saja)
$shift_aktif_jam = getActiveShift();

// ── Cek shift mana yang BENAR-BENAR ada pamdal berjaga (belum absen keluar) ──
$shift_sedang_dijaga = [];
$q_jaga = $conn->prepare(
    "SELECT DISTINCT a.shift_id
     FROM absensi a
     WHERE a.tanggal = ?
       AND a.jam_masuk IS NOT NULL
       AND a.jam_keluar IS NULL"
);
$q_jaga->bind_param('s', $tanggal_hari_ini);
$q_jaga->execute();
$res_jaga = $q_jaga->get_result();
while ($rw = $res_jaga->fetch_assoc()) {
    $shift_sedang_dijaga[] = (int)$rw['shift_id'];
}
$q_jaga->close();

// ── Siapkan data lengkap semua laporan pending untuk modal (embed langsung) ──
$laporan_detail_map = [];
foreach ($laporan_pending as $lap) {
    $lid        = (int)$lap['id'];
    $absensi_id = (int)$lap['absensi_id'];

    // Ambil data absensi lengkap
    $abs_data = getAbsensiById($absensi_id);

    // Cek foto laporan (kolom foto di tabel laporan, bisa null/JSON array)
    $foto_list = [];
    if (!empty($lap['foto'])) {
        $decoded = json_decode($lap['foto'], true);
        if (is_array($decoded)) {
            $foto_list = $decoded;
        } elseif (is_string($lap['foto'])) {
            $foto_list = [$lap['foto']];
        }
    }

    $laporan_detail_map[$lid] = [
        'id'          => $lid,
        'nama_user'   => $lap['nama_user']  ?? '',
        'nama_shift'  => $lap['nama_shift'] ?? '',
        'tanggal_fmt' => date('d/m/Y', strtotime($lap['tanggal'])),
        'tanggal_raw' => $lap['tanggal'],
        'status'      => $lap['status']     ?? 'pending',
        'isi_laporan' => $lap['isi_laporan'] ?? '',
        'kejadian'    => $lap['kejadian']   ?? '',       // opsional, kolom tambahan
        'catatan_kk'  => $lap['catatan_revisi'] ?? '',  // catatan kk sebelumnya
        'foto'        => $foto_list,
        'jam_masuk'   => ($abs_data && $abs_data['jam_masuk'])  ? date('H:i', strtotime($abs_data['jam_masuk']))  : '',
        'jam_keluar'  => ($abs_data && $abs_data['jam_keluar']) ? date('H:i', strtotime($abs_data['jam_keluar'])) : '',
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Kepala Kantor — ANDALAN</title>
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
        .btn-logout { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-logout:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); text-decoration: none; }

        /* ── MAIN ── */
        .main { max-width: 1060px; margin: 0 auto; padding: 32px 20px 60px; }

        /* ── WELCOME STRIP ── */
        .welcome-strip { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 24px 28px; margin-bottom: 28px; display: flex; align-items: center; gap: 20px; position: relative; overflow: hidden; }
        .welcome-strip::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: linear-gradient(180deg, var(--gold), var(--amber)); border-radius: 4px 0 0 4px; }
        .welcome-avatar-lg { width: 52px; height: 52px; background: var(--gold-dim); border: 1.5px solid var(--gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 600; color: var(--gold); flex-shrink: 0; }
        .welcome-text .name { font-size: 18px; font-weight: 600; color: var(--text-primary); }
        .welcome-text .meta { font-size: 13px; color: var(--text-secondary); margin-top: 5px; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 3px 10px; border-radius: 20px; }
        .chip-blue   { background: var(--accent-dim); color: var(--accent);  border: 1px solid rgba(59,158,255,0.25); }
        .chip-green  { background: var(--green-dim);  color: var(--green);   border: 1px solid rgba(34,197,94,0.25); }
        .chip-amber  { background: var(--amber-dim);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.25); }
        .chip-teal   { background: var(--teal-dim);   color: var(--teal);    border: 1px solid rgba(45,212,191,0.25); }
        .chip-red    { background: var(--red-dim);    color: var(--red);     border: 1px solid rgba(244,63,94,0.25); }
        .chip-gold   { background: var(--gold-dim);   color: var(--gold);    border: 1px solid rgba(251,191,36,0.25); }
        .chip-purple { background: var(--purple-dim); color: var(--purple);  border: 1px solid rgba(167,139,250,0.25); }
        .chip-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }
        .welcome-time { margin-left: auto; text-align: right; flex-shrink: 0; }
        .clock { font-family: var(--font-mono); font-size: 22px; font-weight: 500; color: var(--text-primary); }
        .clock-date { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ── SECTION TITLE ── */
        .section-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }

        /* ── STAT GRID ── */
        .stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 18px 20px; position: relative; overflow: hidden; }
        .stat-card::after { content: ''; position: absolute; right: -16px; bottom: -16px; width: 60px; height: 60px; border-radius: 50%; opacity: 0.07; }
        .stat-card.c-blue::after  { background: var(--accent);  }
        .stat-card.c-green::after { background: var(--green);   }
        .stat-card.c-amber::after { background: var(--amber);   }
        .stat-card.c-red::after   { background: var(--red);     }
        .stat-card.c-teal::after  { background: var(--teal);    }
        .stat-card.c-gold::after  { background: var(--gold);    }
        .stat-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; margin-bottom: 14px; }
        .si-blue   { background: var(--accent-dim); color: var(--accent);  border: 1px solid rgba(59,158,255,0.2); }
        .si-green  { background: var(--green-dim);  color: var(--green);   border: 1px solid rgba(34,197,94,0.2); }
        .si-amber  { background: var(--amber-dim);  color: var(--amber);   border: 1px solid rgba(245,158,11,0.2); }
        .si-red    { background: var(--red-dim);    color: var(--red);     border: 1px solid rgba(244,63,94,0.2); }
        .si-teal   { background: var(--teal-dim);   color: var(--teal);    border: 1px solid rgba(45,212,191,0.2); }
        .si-gold   { background: var(--gold-dim);   color: var(--gold);    border: 1px solid rgba(251,191,36,0.2); }
        .stat-number { font-family: var(--font-mono); font-size: 28px; font-weight: 500; color: var(--text-primary); line-height: 1; }
        .stat-label  { font-size: 12px; color: var(--text-secondary); margin-top: 6px; }

        /* ── 2-COL LAYOUT ── */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .full-col { margin-bottom: 28px; }

        /* ── CARD BASE ── */
        .panel { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); overflow: hidden; }
        .panel-header { padding: 16px 20px; border-bottom: 1px solid var(--navy-line); display: flex; align-items: center; justify-content: space-between; }
        .panel-title { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
        .panel-badge { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 20px; }
        .pb-amber { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); }
        .pb-red   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(244,63,94,0.3); }
        .pb-green { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        .pb-blue  { background: var(--accent-dim);color: var(--accent);border: 1px solid rgba(59,158,255,0.3); }
        .panel-body { padding: 0; }
        .panel-empty { padding: 28px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }

        /* ── TABLE ── */
        .tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
        .tbl th { padding: 10px 16px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--navy-line); }
        .tbl td { padding: 11px 16px; border-bottom: 1px solid var(--navy-line); color: var(--text-secondary); vertical-align: middle; }
        .tbl tr:last-child td { border-bottom: none; }
        .tbl tr:hover td { background: var(--navy-hover); }
        .tbl .name-cell { color: var(--text-primary); font-weight: 500; }
        .tbl .mono { font-family: var(--font-mono); font-size: 12px; }

        /* ── BADGE ── */
        .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 20px; }
        .b-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .b-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .b-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.25); }
        .b-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .b-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .b-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.3); }

        /* ── SHIFT STATUS CARDS ── */
        .shift-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; padding: 16px; }
        .shift-card { background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 16px; transition: border-color 0.2s; }
        .shift-card.dijaga  { border-color: rgba(34,197,94,0.45);  background: rgba(34,197,94,0.04); }
        .shift-card.selesai { border-color: rgba(59,158,255,0.25); background: rgba(59,158,255,0.02); }
        .shift-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .shift-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .shift-time { font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 3px; }
        .shift-occupant { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--navy-line); }
        .occ-avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--accent-dim); border: 1px solid rgba(59,158,255,0.3); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: var(--accent); flex-shrink: 0; }
        .occ-avatar.berjaga { background: var(--green-dim); border-color: rgba(34,197,94,0.4); color: var(--green); }
        .occ-name { font-size: 12px; color: var(--text-primary); font-weight: 500; }
        .occ-time { font-size: 11px; color: var(--text-muted); }
        .shift-empty { display: flex; align-items: center; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--navy-line); color: var(--text-muted); font-size: 12px; }

        /* ── QUICK ACTIONS ── */
        .action-grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; margin-bottom: 28px; }
        .action-card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-lg); padding: 20px 16px; text-align: center; text-decoration: none; color: var(--text-primary); display: block; transition: all 0.2s; }
        .action-card:hover { background: var(--navy-hover); border-color: rgba(255,255,255,0.15); transform: translateY(-2px); text-decoration: none; color: var(--text-primary); }
        .action-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; font-size: 18px; }
        .ai-blue   { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.2); }
        .ai-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.2); }
        .ai-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
        .ai-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.2); }
        .ai-teal   { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.2); }
        .ai-red    { background: var(--red-dim);    color: var(--red);    border: 1px solid rgba(244,63,94,0.2); }
        .action-lbl { font-size: 12px; font-weight: 600; }
        .action-sub { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

        /* ── APPROVAL BTN ── */
        .btn-approve { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 500; padding: 4px 12px; border-radius: var(--radius-sm); border: 1px solid rgba(34,197,94,0.35); background: var(--green-dim); color: var(--green); text-decoration: none; transition: all 0.18s; cursor: pointer; }
        .btn-approve:hover { background: var(--green); color: #0f1b2d; border-color: var(--green); }
        .btn-group { display: flex; gap: 6px; }

        /* ── ALERT STRIP ── */
        .alert-strip { background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.3); border-radius: var(--radius-lg); padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 12px; font-size: 13px; color: var(--amber); line-height: 1.5; }
        .alert-strip i { font-size: 16px; margin-top: 1px; flex-shrink: 0; }

        /* ── PULSE ANIMATION ── */
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .pulse { animation: pulse-dot 1.8s ease-in-out infinite; }

        /* ════════════════════════════════════════════
           MODAL TINJAU LAPORAN
        ════════════════════════════════════════════ */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 500;
            background: rgba(8, 14, 24, 0.88);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }

        .modal-box {
            background: var(--navy-card);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            animation: modal-slide-in 0.25s cubic-bezier(0.34, 1.3, 0.64, 1);
            box-shadow: 0 32px 80px rgba(0,0,0,0.5);
        }
        @keyframes modal-slide-in {
            from { opacity: 0; transform: translateY(24px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-head {
            position: sticky;
            top: 0;
            background: var(--navy-card);
            border-bottom: 1px solid var(--navy-line);
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        }
        .modal-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .modal-title i { color: var(--amber); }
        .modal-title .modal-subtitle {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-muted);
            margin-left: 4px;
        }
        .modal-close {
            width: 30px; height: 30px;
            border-radius: 8px;
            background: transparent;
            border: 1px solid var(--navy-line);
            color: var(--text-muted);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            transition: all 0.18s;
        }
        .modal-close:hover { background: var(--red-dim); border-color: var(--red); color: var(--red); }

        .modal-body { padding: 22px; flex: 1; }

        /* Info grid di dalam modal */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
        }
        .info-item {
            background: var(--navy);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 12px 14px;
        }
        .info-item .lbl {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        .info-item .val {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
        }
        .info-item .val.mono { font-family: var(--font-mono); font-size: 12px; }

        /* Isi laporan */
        .laporan-section {
            background: var(--navy);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-bottom: 14px;
        }
        .laporan-section-title {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .laporan-content {
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
            white-space: pre-wrap;
        }
        .laporan-content.empty { color: var(--text-muted); font-style: italic; }

        /* Foto laporan */
        .foto-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 8px;
            margin-top: 6px;
        }
        .foto-item {
            aspect-ratio: 1;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid var(--navy-line);
            cursor: pointer;
            transition: border-color 0.18s, transform 0.18s;
        }
        .foto-item:hover { border-color: var(--accent); transform: scale(1.03); }
        .foto-item img { width: 100%; height: 100%; object-fit: cover; }

        /* Catatan revisi sebelumnya */
        .catatan-revisi-box {
            background: var(--red-dim);
            border: 1px solid rgba(244,63,94,0.3);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            margin-bottom: 14px;
            font-size: 13px;
            color: var(--red);
            line-height: 1.6;
        }
        .catatan-revisi-box strong { display: block; margin-bottom: 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }

        /* Form catatan + aksi */
        .modal-action-area {
            background: var(--navy);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-lg);
            padding: 16px;
            margin-top: 6px;
        }
        .action-area-title {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .textarea-catatan {
            width: 100%;
            background: var(--navy-card);
            border: 1px solid var(--navy-line);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: var(--font-main);
            font-size: 13px;
            padding: 10px 12px;
            resize: vertical;
            min-height: 80px;
            outline: none;
            transition: border-color 0.18s;
            margin-bottom: 12px;
        }
        .textarea-catatan:focus { border-color: var(--accent); }
        .textarea-catatan::placeholder { color: var(--text-muted); }

        .modal-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .btn-modal {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            transition: all 0.18s;
            text-decoration: none;
        }
        .btn-modal:disabled { opacity: 0.45; cursor: not-allowed; pointer-events: none; }

        .btn-acc    { background: var(--green); color: #0a1a0f; }
        .btn-acc:hover:not(:disabled) { background: #16a34a; }

        .btn-revisi { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.4); }
        .btn-revisi:hover:not(:disabled) { background: var(--amber); color: #1a0e00; border-color: var(--amber); }

        .btn-cancel { background: transparent; color: var(--text-muted); border: 1px solid var(--navy-line); }
        .btn-cancel:hover { background: var(--navy-hover); color: var(--text-primary); }

        /* Notif di dalam modal */
        .modal-notif {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 14px;
        }
        .modal-notif.show   { display: flex; }
        .modal-notif.success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        .modal-notif.error   { background: var(--red-dim);   color: var(--red);   border: 1px solid rgba(244,63,94,0.3); }

        /* Divider */
        .m-divider { height: 1px; background: var(--navy-line); margin: 16px 0; }

        /* Sending spinner overlay dalam tombol */
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn-spinner { width: 13px; height: 13px; border: 2px solid rgba(0,0,0,0.2); border-top-color: currentColor; border-radius: 50%; animation: spin 0.6s linear infinite; display: none; }
        .sending .btn-spinner { display: inline-block; }
        .sending .btn-txt { opacity: 0.6; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .stat-grid    { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .two-col      { grid-template-columns: 1fr; }
            .action-grid  { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .shift-grid   { grid-template-columns: 1fr; }
            .welcome-time { display: none; }
            .navbar       { padding: 0 16px; }
            .main         { padding: 20px 14px 50px; }
            .user-name    { display: none; }
            .info-grid    { grid-template-columns: 1fr; }
            .modal-box    { max-height: 95vh; }
        }
        @media (max-width: 480px) {
            .stat-grid    { grid-template-columns: 1fr; }
            .action-grid  { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════════════════════
     MODAL TINJAU LAPORAN
     Data diisi lewat JS dari PHP embed — tanpa AJAX
═══════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalTinjau" role="dialog" aria-modal="true" aria-label="Detail Laporan">
    <div class="modal-box" id="modalBox">

        <div class="modal-head">
            <div class="modal-title">
                <i class="fas fa-file-alt"></i>
                <span id="modalHeadTitle">Detail Laporan</span>
                <span class="modal-subtitle" id="modalHeadSub"></span>
            </div>
            <button class="modal-close" onclick="tutupModal()" title="Tutup">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body" id="modalBody">
            <!-- diisi oleh JS -->
        </div>

    </div>
</div>


<!-- ── NAVBAR ── -->
<nav class="navbar">
    <div class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </div>
    <div class="navbar-right">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'KK', 0, 2)) ?></div>
            <span class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['name'] ?? '') ?></span>
            <span class="role-chip">Kepala Kantor</span>
        </div>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="main">

    <!-- ── WELCOME STRIP ── -->
    <div class="welcome-strip">
        <div class="welcome-avatar-lg"><?= strtoupper(substr($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'KK', 0, 2)) ?></div>
        <div class="welcome-text">
            <div class="name">Selamat datang, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'Kepala Kantor')[0]) ?>!</div>
            <div class="meta">
                <span class="chip chip-gold"><i class="fas fa-crown" style="font-size:10px;"></i> Kepala Kantor</span>
                <?php if ($stats['total_laporan_pending'] > 0): ?>
                    <span class="chip chip-amber">
                        <i class="fas fa-file-alt" style="font-size:10px;"></i>
                        <?= $stats['total_laporan_pending'] ?> laporan menunggu ACC
                    </span>
                <?php endif; ?>
                <?php if ($stats['total_laporan_revisi'] > 0): ?>
                    <span class="chip chip-red">
                        <i class="fas fa-redo" style="font-size:10px;"></i>
                        <?= $stats['total_laporan_revisi'] ?> laporan revisi
                    </span>
                <?php endif; ?>
                <?php if ($stats['total_laporan_pending'] === 0 && $stats['total_laporan_revisi'] === 0): ?>
                    <span class="chip chip-teal">
                        <i class="fas fa-check-double" style="font-size:10px;"></i>
                        Semua laporan bersih
                    </span>
                <?php endif; ?>
                <?php if (!empty($shift_sedang_dijaga)): ?>
                    <span class="chip chip-green">
                        <i class="fas fa-circle pulse" style="font-size:8px;"></i>
                        <?= count($shift_sedang_dijaga) ?> shift sedang dijaga
                    </span>
                <?php else: ?>
                    <span class="chip chip-muted">
                        <i class="fas fa-circle" style="font-size:8px;"></i>
                        Tidak ada shift aktif
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="welcome-time">
            <div class="clock" id="clock"><?= date('H:i:s') ?></div>
            <div class="clock-date"><?= date('l, d F Y') ?></div>
        </div>
    </div>

    <!-- ── ALERT jika ada laporan pending ── -->
    <?php if ($stats['total_laporan_pending'] > 0): ?>
    <div class="alert-strip">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            Ada <strong><?= $stats['total_laporan_pending'] ?> laporan</strong> yang menunggu tinjauan Anda hari ini.
            Silakan tinjau langsung dari tabel di bawah.
        </div>
    </div>
    <?php endif; ?>

    <!-- ── STATISTIK HARIAN ── -->
    <div class="section-title">Statistik Hari Ini</div>
    <div class="stat-grid" style="margin-bottom:28px;">
        <div class="stat-card c-blue">
            <div class="stat-icon si-blue"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?= $stats['total_hadir'] ?></div>
            <div class="stat-label">Hadir Hari Ini</div>
        </div>
        <div class="stat-card c-amber">
            <div class="stat-icon si-amber"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?= $stats['total_terlambat'] ?></div>
            <div class="stat-label">Terlambat</div>
        </div>
        <div class="stat-card c-red">
            <div class="stat-icon si-red"><i class="fas fa-person-walking-arrow-right"></i></div>
            <div class="stat-number"><?= $stats['total_pulang_awal'] ?></div>
            <div class="stat-label">Pulang Lebih Awal</div>
        </div>
        <div class="stat-card c-teal">
            <div class="stat-icon si-teal"><i class="fas fa-rotate"></i></div>
            <div class="stat-number"><?= $stats['total_lanjut_shift'] ?></div>
            <div class="stat-label">Lanjut Shift</div>
        </div>
        <div class="stat-card c-amber">
            <div class="stat-icon si-amber"><i class="fas fa-file-circle-xmark"></i></div>
            <div class="stat-number"><?= $stats['total_laporan_pending'] ?></div>
            <div class="stat-label">Laporan Pending</div>
        </div>
        <div class="stat-card c-red">
            <div class="stat-icon si-red"><i class="fas fa-file-circle-exclamation"></i></div>
            <div class="stat-number"><?= $stats['total_laporan_revisi'] ?></div>
            <div class="stat-label">Laporan Revisi</div>
        </div>
        <div class="stat-card c-blue">
            <div class="stat-icon si-blue"><i class="fas fa-user-shield"></i></div>
            <div class="stat-number"><?= $total_pamdal ?></div>
            <div class="stat-label">Total Pamdal Aktif</div>
        </div>
        <div class="stat-card c-green">
            <div class="stat-icon si-green"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-number"><?= $stats['total_tidak_sesuai'] ?></div>
            <div class="stat-label">Tukar Shift</div>
        </div>
    </div>

    <!-- ── STATUS SHIFT ── -->
    <div class="section-title">Status Shift Hari Ini</div>
    <div class="panel full-col">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fas fa-calendar-day" style="color:var(--accent);"></i>
                Kondisi Shift — <?= date('d F Y') ?>
            </span>
            <?php if (!empty($shift_sedang_dijaga)): ?>
                <span class="panel-badge pb-green">
                    <i class="fas fa-circle pulse" style="font-size:8px;"></i>
                    <?= count($shift_sedang_dijaga) ?> shift sedang dijaga
                </span>
            <?php else: ?>
                <span class="panel-badge pb-blue">Tidak ada penjagaan aktif</span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <div class="shift-grid">
                <?php foreach ($shifts as $shift):
                    $sid = (int)$shift['id'];

                    $q2 = $conn->prepare(
                        "SELECT a.jam_masuk, a.jam_keluar, u.name
                         FROM absensi a
                         JOIN users u ON a.user_id = u.id
                         WHERE a.shift_id = ? AND a.tanggal = ? AND a.jam_masuk IS NOT NULL
                         ORDER BY a.jam_masuk ASC"
                    );
                    $q2->bind_param('is', $sid, $tanggal_hari_ini);
                    $q2->execute();
                    $res2 = $q2->get_result();
                    $pamdal_shift = [];
                    while ($rw = $res2->fetch_assoc()) $pamdal_shift[] = $rw;
                    $q2->close();

                    $sedang_dijaga = in_array($sid, $shift_sedang_dijaga);
                    $pernah_ada    = count($pamdal_shift) > 0;
                    $semua_keluar  = $pernah_ada && !$sedang_dijaga;

                    $card_class = '';
                    if ($sedang_dijaga) $card_class = 'dijaga';
                    elseif ($semua_keluar) $card_class = 'selesai';
                ?>
                <div class="shift-card <?= $card_class ?>">
                    <div class="shift-head">
                        <div>
                            <div class="shift-name"><?= htmlspecialchars($shift['nama_shift']) ?></div>
                            <div class="shift-time"><?= substr($shift['jam_masuk'],0,5) ?> – <?= substr($shift['jam_keluar'],0,5) ?></div>
                        </div>
                        <?php if ($sedang_dijaga): ?>
                            <span class="badge b-green"><i class="fas fa-circle pulse" style="font-size:8px;"></i> Aktif</span>
                        <?php elseif ($semua_keluar): ?>
                            <span class="badge b-blue"><i class="fas fa-check" style="font-size:8px;"></i> Selesai</span>
                        <?php else: ?>
                            <span class="badge b-muted">Belum ada</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($pernah_ada): ?>
                        <?php foreach ($pamdal_shift as $ps):
                            $masih_berjaga = empty($ps['jam_keluar']);
                        ?>
                        <div class="shift-occupant">
                            <div class="occ-avatar <?= $masih_berjaga ? 'berjaga' : '' ?>">
                                <?= strtoupper(substr($ps['name'],0,2)) ?>
                            </div>
                            <div>
                                <div class="occ-name"><?= htmlspecialchars($ps['name']) ?></div>
                                <div class="occ-time">
                                    Masuk <?= date('H:i', strtotime($ps['jam_masuk'])) ?>
                                    <?php if ($masih_berjaga): ?>
                                        · <span style="color:var(--green);font-size:10px;font-weight:600;">
                                            <i class="fas fa-circle pulse" style="font-size:7px;"></i> Masih berjaga
                                          </span>
                                    <?php else: ?>
                                        · Keluar <?= date('H:i', strtotime($ps['jam_keluar'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="shift-empty">
                            <i class="fas fa-user-slash" style="color:var(--red);"></i>
                            <span>Belum ada pamdal yang masuk</span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── LAPORAN PENDING & LAPORAN REVISI ── -->
    <div class="two-col">

        <!-- LAPORAN PENDING -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fas fa-file-alt" style="color:var(--amber);"></i>
                    Laporan Menunggu ACC
                </span>
                <span class="panel-badge pb-amber" id="badgePending"><?= count($laporan_pending) ?></span>
            </div>
            <div class="panel-body" id="tabelPendingWrapper">
                <?php if (empty($laporan_pending)): ?>
                    <div class="panel-empty" id="emptyPending">
                        <i class="fas fa-check-circle" style="color:var(--green); font-size:22px; display:block; margin-bottom:8px;"></i>
                        Tidak ada laporan yang menunggu tinjauan.
                    </div>
                <?php else: ?>
                    <table class="tbl" id="tabelPending">
                        <thead>
                            <tr>
                                <th>Pamdal</th>
                                <th>Shift</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tabelPendingBody">
                        <?php foreach (array_slice($laporan_pending, 0, 8) as $lap): ?>
                            <tr id="row-laporan-<?= (int)$lap['id'] ?>">
                                <td class="name-cell"><?= htmlspecialchars($lap['nama_user']) ?></td>
                                <td><span class="badge b-blue"><?= htmlspecialchars($lap['nama_shift']) ?></span></td>
                                <td class="mono"><?= date('d/m/Y', strtotime($lap['tanggal'])) ?></td>
                                <td>
                                    <button
                                        class="btn-approve"
                                        onclick="bukaModal(<?= (int)$lap['id'] ?>)"
                                        title="Tinjau laporan">
                                        <i class="fas fa-eye"></i> Tinjau
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($laporan_pending) > 8): ?>
                        <div style="padding:10px 16px; border-top:1px solid var(--navy-line); text-align:center;">
                            <a href="laporan_masuk.php" style="font-size:12px; color:var(--accent); text-decoration:none;">
                                Lihat <?= count($laporan_pending) - 8 ?> laporan lainnya →
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- LAPORAN PERLU REVISI -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">
                    <i class="fas fa-file-circle-exclamation" style="color:var(--red);"></i>
                    Laporan Perlu Revisi
                </span>
                <span class="panel-badge pb-red"><?= count($laporan_revisi) ?></span>
            </div>
            <div class="panel-body">
                <?php if (empty($laporan_revisi)): ?>
                    <div class="panel-empty">
                        <i class="fas fa-check-circle" style="color:var(--green); font-size:22px; display:block; margin-bottom:8px;"></i>
                        Tidak ada laporan yang perlu direvisi.
                    </div>
                <?php else: ?>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Pamdal</th>
                                <th>Shift</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($laporan_revisi, 0, 8) as $lap): ?>
                            <tr>
                                <td class="name-cell"><?= htmlspecialchars($lap['nama_user']) ?></td>
                                <td><span class="badge b-blue"><?= htmlspecialchars($lap['nama_shift']) ?></span></td>
                                <td class="mono"><?= date('d/m/Y', strtotime($lap['tanggal'])) ?></td>
                                <td><span class="badge b-amber">Revisi</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ── ABSENSI HARI INI ── -->
    <div class="section-title">Rekap Absensi Hari Ini</div>
    <div class="panel full-col">
        <div class="panel-header">
            <span class="panel-title">
                <i class="fas fa-clipboard-list" style="color:var(--accent);"></i>
                Daftar Absensi — <?= date('d F Y') ?>
            </span>
            <span class="panel-badge pb-blue"><?= count($absensi_hari_ini) ?> catatan</span>
        </div>
        <div class="panel-body">
            <?php if (empty($absensi_hari_ini)): ?>
                <div class="panel-empty">
                    <i class="fas fa-calendar-times" style="color:var(--text-muted); font-size:22px; display:block; margin-bottom:8px;"></i>
                    Belum ada data absensi hari ini.
                </div>
            <?php else: ?>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Pamdal</th>
                            <th>Shift</th>
                            <th>Absen Masuk</th>
                            <th>Status Masuk</th>
                            <th>Absen Keluar</th>
                            <th>Status Keluar</th>
                            <th>Laporan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($absensi_hari_ini as $abs): ?>
                        <tr>
                            <td class="name-cell"><?= htmlspecialchars($abs['nama_user']) ?></td>
                            <td><span class="badge b-blue"><?= htmlspecialchars($abs['nama_shift']) ?></span></td>
                            <td class="mono"><?= $abs['jam_masuk'] ? date('H:i', strtotime($abs['jam_masuk'])) : '—' ?></td>
                            <td>
                                <?php
                                    $km = $abs['keterangan_masuk'] ?? 'normal';
                                    $sm = $abs['status_masuk'] ?? '';
                                    if ($sm === 'tidak_sesuai'):
                                ?>
                                    <span class="badge b-amber">Tukar Shift</span>
                                <?php elseif ($km === 'terlambat'): ?>
                                    <span class="badge b-amber">Terlambat</span>
                                <?php else: ?>
                                    <span class="badge b-green">Tepat Waktu</span>
                                <?php endif; ?>
                            </td>
                            <td class="mono"><?= $abs['jam_keluar'] ? date('H:i', strtotime($abs['jam_keluar'])) : '—' ?></td>
                            <td>
                                <?php $sk = $abs['status_keluar'] ?? ''; ?>
                                <?php if ($sk === 'tepat_waktu'): ?>
                                    <span class="badge b-green">Tepat Waktu</span>
                                <?php elseif ($sk === 'pulang_awal'): ?>
                                    <span class="badge b-red">Pulang Awal</span>
                                <?php elseif ($sk === 'lanjut_shift'): ?>
                                    <span class="badge" style="background:var(--purple-dim);color:var(--purple);border:1px solid rgba(167,139,250,0.25);">Lanjut Shift</span>
                                <?php else: ?>
                                    <span class="badge b-muted">Belum Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $sl = $abs['status_laporan'] ?? ''; ?>
                                <?php if ($sl === 'acc'): ?>
                                    <span class="badge b-green">ACC</span>
                                <?php elseif ($sl === 'pending'): ?>
                                    <span class="badge b-amber">Pending</span>
                                <?php elseif ($sl === 'revisi'): ?>
                                    <span class="badge b-red">Revisi</span>
                                <?php else: ?>
                                    <span class="badge b-muted">Belum Ada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── QUICK ACTIONS ── -->
    <div class="section-title">Menu Utama</div>
    <div class="action-grid">
        <a href="laporan_masuk.php" class="action-card">
            <div class="action-icon ai-amber"><i class="fas fa-inbox"></i></div>
            <div class="action-lbl">Laporan Masuk</div>
            <div class="action-sub">Tinjau &amp; ACC laporan</div>
        </a>
        <a href="rekap_absensi.php" class="action-card">
            <div class="action-icon ai-blue"><i class="fas fa-clipboard-list"></i></div>
            <div class="action-lbl">Rekap Absensi</div>
            <div class="action-sub">Lihat semua data absensi</div>
        </a>
        <a href="monitoring_shift.php" class="action-card">
            <div class="action-icon ai-green"><i class="fas fa-eye"></i></div>
            <div class="action-lbl">Monitoring Shift</div>
            <div class="action-sub">Pantau kondisi shift aktif</div>
        </a>
        <a href="data_pamdal.php" class="action-card">
            <div class="action-icon ai-purple"><i class="fas fa-user-shield"></i></div>
            <div class="action-lbl">Data Pamdal</div>
            <div class="action-sub">Daftar seluruh personil</div>
        </a>
        <a href="laporan_bulanan.php" class="action-card">
            <div class="action-icon ai-teal"><i class="fas fa-chart-bar"></i></div>
            <div class="action-lbl">Laporan Bulanan</div>
            <div class="action-sub">Statistik &amp; rekapitulasi</div>
        </a>
        <a href="penukaran_shift.php" class="action-card">
            <div class="action-icon ai-red"><i class="fas fa-exchange-alt"></i></div>
            <div class="action-lbl">Penukaran Shift</div>
            <div class="action-sub">Riwayat tukar jadwal</div>
        </a>
    </div>

</div><!-- /.main -->


<!-- ══════════════════════════════════════════════
     DATA LAPORAN EMBED DARI PHP (tanpa AJAX)
═══════════════════════════════════════════════ -->
<script>
/* Seluruh data laporan pending sudah disiapkan di PHP dan ditulis langsung ke sini */
const LAPORAN_DATA = <?= json_encode($laporan_detail_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>


<!-- ══════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════ -->
<script>
/* ── Clock ── */
(function tickClock() {
    const el = document.getElementById('clock');
    if (el) {
        const now = new Date();
        const p = n => String(n).padStart(2, '0');
        el.textContent = p(now.getHours()) + ':' + p(now.getMinutes()) + ':' + p(now.getSeconds());
    }
    setTimeout(tickClock, 1000);
})();

/* ══════════════════════════════════════════════
   MODAL
═══════════════════════════════════════════════ */
const overlay   = document.getElementById('modalTinjau');
const modalBody = document.getElementById('modalBody');
const modalHeadTitle = document.getElementById('modalHeadTitle');
const modalHeadSub   = document.getElementById('modalHeadSub');

let currentLaporanId = null;

/* Buka modal – data sudah ada di LAPORAN_DATA, tidak perlu fetch */
function bukaModal(laporanId) {
    const d = LAPORAN_DATA[laporanId];
    if (!d) {
        alert('Data laporan tidak ditemukan.');
        return;
    }
    currentLaporanId = laporanId;

    /* Update header */
    modalHeadTitle.textContent = 'Detail Laporan';
    modalHeadSub.textContent   = '— ' + d.nama_user + ' · ' + d.nama_shift;

    /* Render isi */
    renderModal(d);

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

/* Tutup modal */
function tutupModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    currentLaporanId = null;
}

/* Klik di luar box → tutup */
overlay.addEventListener('click', e => { if (e.target === overlay) tutupModal(); });

/* ESC → tutup */
document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupModal(); });

/* ── Render isi modal dari object data ── */
function renderModal(d) {

    /* Badge status */
    function statusBadge(s) {
        const map = {
            acc:     ['b-green', 'ACC'],
            pending: ['b-amber', 'Pending'],
            revisi:  ['b-red',   'Revisi'],
        };
        const [cls, lbl] = map[s] || ['b-muted', s || '—'];
        return `<span class="badge ${cls}">${lbl}</span>`;
    }

    /* Foto (jika ada) */
    let fotoHtml = '';
    if (d.foto && d.foto.length > 0) {
        const items = d.foto.map(f => `
            <div class="foto-item" onclick="window.open('${esc(f)}','_blank')" title="Buka foto">
                <img src="${esc(f)}" alt="Foto laporan" loading="lazy">
            </div>`).join('');
        fotoHtml = `
            <div class="laporan-section">
                <div class="laporan-section-title">
                    <i class="fas fa-images" style="color:var(--accent);"></i> Dokumentasi Foto
                </div>
                <div class="foto-grid">${items}</div>
            </div>`;
    }

    /* Catatan KK sebelumnya (jika status revisi dan ada catatan) */
    let catatanRevisiHtml = '';
    if (d.catatan_kk) {
        catatanRevisiHtml = `
            <div class="catatan-revisi-box">
                <strong><i class="fas fa-comment-dots"></i> Catatan Kepala Kantor Sebelumnya</strong>
                ${esc(d.catatan_kk)}
            </div>`;
    }

    /* Kejadian/temuan opsional */
    let kejadianHtml = '';
    if (d.kejadian) {
        kejadianHtml = `
            <div class="laporan-section">
                <div class="laporan-section-title">
                    <i class="fas fa-triangle-exclamation" style="color:var(--red);"></i> Kejadian / Temuan
                </div>
                <div class="laporan-content">${esc(d.kejadian)}</div>
            </div>`;
    }

    modalBody.innerHTML = `
        <!-- Notifikasi hasil aksi -->
        <div class="modal-notif" id="modalNotif"></div>

        <!-- Info ringkas -->
        <div class="info-grid">
            <div class="info-item">
                <div class="lbl">Pelapor</div>
                <div class="val">${esc(d.nama_user)}</div>
            </div>
            <div class="info-item">
                <div class="lbl">Shift</div>
                <div class="val">${esc(d.nama_shift)}</div>
            </div>
            <div class="info-item">
                <div class="lbl">Tanggal</div>
                <div class="val mono">${esc(d.tanggal_fmt)}</div>
            </div>
            <div class="info-item">
                <div class="lbl">Status</div>
                <div class="val">${statusBadge(d.status)}</div>
            </div>
            <div class="info-item">
                <div class="lbl">Absen Masuk</div>
                <div class="val mono">${esc(d.jam_masuk) || '—'}</div>
            </div>
            <div class="info-item">
                <div class="lbl">Absen Keluar</div>
                <div class="val mono">${esc(d.jam_keluar) || '—'}</div>
            </div>
        </div>

        <!-- Isi laporan -->
        <div class="laporan-section">
            <div class="laporan-section-title">
                <i class="fas fa-file-lines" style="color:var(--amber);"></i> Isi Laporan
            </div>
            <div class="laporan-content ${d.isi_laporan ? '' : 'empty'}">
                ${d.isi_laporan ? esc(d.isi_laporan) : 'Tidak ada isi laporan.'}
            </div>
        </div>

        ${kejadianHtml}
        ${fotoHtml}
        ${catatanRevisiHtml}

        <div class="m-divider"></div>

        <!-- Area keputusan -->
        <div class="modal-action-area">
            <div class="action-area-title">Keputusan Anda</div>
            <textarea
                class="textarea-catatan"
                id="catatanKK"
                placeholder="Tulis catatan atau alasan revisi (wajib jika memilih Revisi, opsional untuk ACC)…"
                rows="3"></textarea>
            <div class="modal-actions">
                <button class="btn-modal btn-acc" id="btnAcc" onclick="kirimKeputusan('acc')">
                    <span class="btn-spinner"></span>
                    <span class="btn-txt"><i class="fas fa-check-double"></i> ACC Laporan</span>
                </button>
                <button class="btn-modal btn-revisi" id="btnRevisi" onclick="kirimKeputusan('revisi')">
                    <span class="btn-spinner"></span>
                    <span class="btn-txt"><i class="fas fa-redo"></i> Minta Revisi</span>
                </button>
                <button class="btn-modal btn-cancel" onclick="tutupModal()">
                    Batal
                </button>
            </div>
        </div>
    `;
}

/* ── Kirim keputusan ke server via fetch POST ── */
async function kirimKeputusan(aksi) {
    if (!currentLaporanId) return;

    const catatan   = document.getElementById('catatanKK')?.value.trim() ?? '';
    const btnAcc    = document.getElementById('btnAcc');
    const btnRevisi = document.getElementById('btnRevisi');

    if (aksi === 'revisi' && catatan === '') {
        tampilNotif('error', '<i class="fas fa-exclamation-circle"></i> Catatan wajib diisi jika memilih Revisi.');
        return;
    }

    /* Set loading state */
    const activeBtn = aksi === 'acc' ? btnAcc : btnRevisi;
    if (activeBtn) activeBtn.classList.add('sending');
    if (btnAcc)    btnAcc.disabled    = true;
    if (btnRevisi) btnRevisi.disabled = true;

    try {
        const body = new FormData();
        body.append('id',      currentLaporanId);
        body.append('aksi',    aksi);
        body.append('catatan', catatan);

        const resp = await fetch('aksi_laporan.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });

        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const data = await resp.json();

        if (data.success) {
            tampilNotif('success',
                aksi === 'acc'
                    ? '<i class="fas fa-check-circle"></i> Laporan berhasil di-ACC.'
                    : '<i class="fas fa-redo"></i> Laporan dikembalikan untuk revisi.');

            /* Hapus baris dari tabel tanpa reload */
            const tr = document.getElementById(`row-laporan-${currentLaporanId}`);
            if (tr) tr.remove();

            /* Update badge jumlah pending */
            const badge = document.getElementById('badgePending');
            if (badge) {
                const n = parseInt(badge.textContent, 10);
                if (!isNaN(n) && n > 0) badge.textContent = n - 1;
                if (n - 1 <= 0) {
                    /* Tidak ada lagi laporan → tampilkan empty state */
                    const tabel = document.getElementById('tabelPending');
                    if (tabel) {
                        tabel.outerHTML = `
                            <div class="panel-empty" id="emptyPending">
                                <i class="fas fa-check-circle" style="color:var(--green); font-size:22px; display:block; margin-bottom:8px;"></i>
                                Tidak ada laporan yang menunggu tinjauan.
                            </div>`;
                    }
                }
            }

            /* Tutup modal otomatis setelah 1.4 detik */
            setTimeout(tutupModal, 1400);

        } else {
            tampilNotif('error', '<i class="fas fa-exclamation-circle"></i> ' + (data.message || 'Terjadi kesalahan.'));
            if (activeBtn) activeBtn.classList.remove('sending');
            if (btnAcc)    btnAcc.disabled    = false;
            if (btnRevisi) btnRevisi.disabled = false;
        }

    } catch (err) {
        tampilNotif('error', '<i class="fas fa-exclamation-circle"></i> Gagal terhubung ke server: ' + err.message);
        if (activeBtn) activeBtn.classList.remove('sending');
        if (btnAcc)    btnAcc.disabled    = false;
        if (btnRevisi) btnRevisi.disabled = false;
    }
}

/* ── Notifikasi di dalam modal ── */
function tampilNotif(type, html) {
    const el = document.getElementById('modalNotif');
    if (!el) return;
    el.className = `modal-notif show ${type}`;
    el.innerHTML = html;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── Escape HTML (XSS prevention) ── */
function esc(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>
</body>
</html>