<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

$user_id    = $_SESSION['user_id'];
$tanggal    = date('Y-m-d');
$pesan      = '';
$tipe_pesan = '';

// ── Ambil semua shift ─────────────────────────────────────────
$semua_shift = getAllShifts();

// ── Cari absensi aktif (sudah masuk, belum keluar) ───────────
$absensi_aktif = null;
foreach ($semua_shift as $shift) {
    $cek = getAbsensiAktif($user_id, $shift['id'], $tanggal);
    if ($cek) { $absensi_aktif = $cek; break; }
}

// Carry-over shift malam dari kemarin
if (!$absensi_aktif) {
    $kemarin = date('Y-m-d', strtotime('-1 day'));
    foreach ($semua_shift as $shift) {
        if (str_contains(strtolower($shift['nama_shift']), 'malam')) {
            $cek = getAbsensiAktif($user_id, $shift['id'], $kemarin);
            if ($cek) { $absensi_aktif = $cek; break; }
        }
    }
}

// ── Cek status "tepat waktu" berdasarkan aturan baru ─────────
// Absen keluar normal HANYA tersedia jika sudah ada pamdal baru
// yang absen masuk di shift berikutnya (serah terima terjadi).
$tepat_waktu_tersedia = false;
if ($absensi_aktif) {
    $tepat_waktu_tersedia = isWaktuPulang(
        (int)$absensi_aktif['shift_id'],
        $absensi_aktif['tanggal']
    );
}
$tepat_waktu_terkunci = $absensi_aktif && !$tepat_waktu_tersedia;

// ── Bangun opsi keluar ─────────────────────────────────────────
$opsi_keluar = [];
if ($absensi_aktif) {
    // Tepat waktu selalu tampil (tapi mungkin terkunci)
    $opsi_keluar[] = ['value' => 'tepat_waktu',  'label' => 'Absen Keluar Normal'];
    // Pulang awal: hanya saat belum ada pengganti
    if ($tepat_waktu_terkunci) {
        $opsi_keluar[] = ['value' => 'pulang_awal', 'label' => 'Pulang Lebih Awal'];
    }
    // Lanjut shift selalu ada
    $opsi_keluar[] = ['value' => 'lanjut_shift', 'label' => 'Lanjut Shift Berikutnya'];
}

$sudah_keluar = $absensi_aktif && !empty($absensi_aktif['jam_keluar']);

// ── Proses POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $absensi_id    = (int)($_POST['absensi_id'] ?? 0);
    $status_keluar = cleanInput($_POST['status_keluar'] ?? '');
    $alasan        = cleanInput($_POST['alasan'] ?? '');

    if (!$absensi_aktif || (int)$absensi_aktif['id'] !== $absensi_id) {
        $pesan      = 'Data absensi masuk tidak valid. Pastikan Anda sudah absen masuk terlebih dahulu.';
        $tipe_pesan = 'error';
    } elseif ($sudah_keluar) {
        $pesan      = 'Anda sudah melakukan absen keluar pada shift ini.';
        $tipe_pesan = 'warning';
    } elseif ($status_keluar === 'tepat_waktu' && $tepat_waktu_terkunci) {
        $pesan      = 'Absen keluar normal belum bisa dilakukan. Tunggu hingga pamdal shift berikutnya sudah absen masuk sebagai tanda serah terima.';
        $tipe_pesan = 'error';
    } else {
        $result     = absenKeluar($user_id, $absensi_id, $status_keluar, $alasan);
        $pesan      = $result['message'];
        $tipe_pesan = $result['success'] ? 'success' : 'error';

        if ($result['success'] && $status_keluar === 'lanjut_shift') {
            header('Location: absen_keluar.php?lanjut=1');
            exit;
        }
    }
}

// ── Tangkap parameter lanjut shift ────────────────────────────
$dari_lanjut_shift = isset($_GET['lanjut']) && $_GET['lanjut'] == '1';

// ── Helper ikon shift ─────────────────────────────────────────
function shiftIcon(string $nama): array {
    $n = strtolower($nama);
    if (str_contains($n, 'pagi'))  return ['fa-sun',         'pagi'];
    if (str_contains($n, 'sore'))  return ['fa-cloud-sun',   'sore'];
    if (str_contains($n, 'malam')) return ['fa-moon',        'malam'];
    return ['fa-calendar-day', 'lain'];
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
            background: var(--navy);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

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
            color: var(--text-primary); letter-spacing: 0.5px; text-decoration: none;
        }
        .brand-icon {
            width: 32px; height: 32px; background: var(--accent);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: white;
        }
        .btn-back {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 500; padding: 6px 14px;
            border-radius: var(--radius-sm);
            background: transparent; border: 1px solid var(--navy-line);
            color: var(--text-secondary); text-decoration: none; transition: all 0.2s;
        }
        .btn-back:hover { background: var(--navy-hover); border-color: rgba(255,255,255,.15); color: var(--text-primary); text-decoration: none; }

        .main { max-width: 680px; margin: 0 auto; padding: 36px 20px 80px; }

        .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
        .page-icon {
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--red-dim); border: 1px solid rgba(244,63,94,0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: var(--red); flex-shrink: 0;
        }
        .page-title { font-size: 22px; font-weight: 700; }
        .page-sub   { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

        .alert {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 14px 18px; border-radius: var(--radius-lg);
            margin-bottom: 24px; font-size: 13px; line-height: 1.55;
            animation: slideDown .3s ease;
        }
        @keyframes slideDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        .alert-success { background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3);  color: var(--green); }
        .alert-error   { background: var(--red-dim);   border: 1px solid rgba(244,63,94,0.3);  color: var(--red); }
        .alert-warning { background: var(--amber-dim); border: 1px solid rgba(245,158,11,0.3); color: var(--amber); }
        .alert i { margin-top: 1px; flex-shrink: 0; }
        .alert-text strong { display: block; margin-bottom: 2px; }

        .banner-lanjut {
            display: flex; align-items: center; gap: 14px;
            padding: 16px 20px; margin-bottom: 24px;
            background: var(--purple-dim);
            border: 1px solid rgba(167,139,250,0.4);
            border-radius: var(--radius-lg);
            animation: slideDown .3s ease;
        }
        .banner-lanjut-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(167,139,250,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: var(--purple); flex-shrink: 0;
        }
        .banner-lanjut-text { font-size: 13px; color: var(--purple); line-height: 1.5; }
        .banner-lanjut-text strong { display: block; font-size: 14px; margin-bottom: 2px; }

        /* Banner: menunggu pamdal pengganti */
        .banner-tunggu {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px 20px; margin-bottom: 24px;
            background: var(--amber-dim);
            border: 1px solid rgba(245,158,11,0.4);
            border-radius: var(--radius-lg);
        }
        .banner-tunggu-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(245,158,11,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; color: var(--amber); flex-shrink: 0; margin-top: 2px;
        }
        .banner-tunggu-title { font-size: 14px; font-weight: 700; color: var(--amber); }
        .banner-tunggu-sub   { font-size: 12px; color: rgba(245,158,11,0.8); margin-top: 4px; line-height: 1.6; }

        .card {
            background: var(--navy-card); border: 1px solid var(--navy-line);
            border-radius: var(--radius-xl); padding: 26px 28px; margin-bottom: 20px;
        }
        .card-title {
            font-size: 11px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.9px;
            margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
        }

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
        .info-box-val   { font-size: 15px; font-weight: 600; font-family: var(--font-mono); }

        .shift-summary {
            display: flex; align-items: center; gap: 16px;
            padding: 18px 20px; background: var(--navy);
            border: 1px solid var(--navy-line); border-radius: var(--radius-lg);
            margin-bottom: 20px; position: relative; overflow: hidden;
        }
        .shift-summary::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
            background: linear-gradient(180deg, var(--green), var(--teal));
            border-radius: 3px 0 0 3px;
        }
        .ss-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .ss-icon.pagi  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .ss-icon.sore  { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .ss-icon.malam { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .ss-icon.lain  { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .ss-nama { font-size: 15px; font-weight: 600; }
        .ss-meta { font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: flex; flex-wrap: wrap; gap: 12px; }
        .ss-meta span { display: flex; align-items: center; gap: 5px; }
        .dot-green { width: 7px; height: 7px; border-radius: 50%; background: var(--green); }

        .opsi-grid { display: flex; flex-direction: column; gap: 12px; }
        .opsi-radio { display: none; }
        .opsi-label {
            display: flex; align-items: center; gap: 16px;
            padding: 18px 20px; border-radius: var(--radius-lg);
            border: 1.5px solid var(--navy-line); background: var(--navy);
            cursor: pointer; transition: all 0.22s;
        }
        .opsi-label:hover { border-color: rgba(255,255,255,0.18); background: var(--navy-hover); }
        .opsi-label.opsi-locked {
            cursor: not-allowed; opacity: 0.55; border-style: dashed;
            border-color: var(--navy-line) !important; background: var(--navy) !important; box-shadow: none !important;
        }
        .opsi-label.opsi-locked:hover { border-color: var(--navy-line) !important; background: var(--navy) !important; }
        .opsi-radio.ok:checked     + .opsi-label { border-color: var(--green);  background: var(--green-dim);  box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }
        .opsi-radio.early:checked  + .opsi-label { border-color: var(--amber);  background: var(--amber-dim);  box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
        .opsi-radio.double:checked + .opsi-label { border-color: var(--purple); background: var(--purple-dim); box-shadow: 0 0 0 3px rgba(167,139,250,0.12); }
        .radio-dot {
            width: 20px; height: 20px; border-radius: 50%;
            border: 2px solid var(--text-muted); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .opsi-radio.ok:checked     + .opsi-label .radio-dot { border-color: var(--green);  background: var(--green); }
        .opsi-radio.early:checked  + .opsi-label .radio-dot { border-color: var(--amber);  background: var(--amber); }
        .opsi-radio.double:checked + .opsi-label .radio-dot { border-color: var(--purple); background: var(--purple); }
        .radio-dot::after { content: ''; width: 7px; height: 7px; border-radius: 50%; background: white; display: none; }
        .opsi-radio:checked + .opsi-label .radio-dot::after { display: block; }
        .opsi-label.opsi-locked .radio-dot { border-color: var(--text-muted); background: transparent; }
        .opsi-label.opsi-locked .radio-dot::after { display: none !important; }
        .opsi-icon {
            width: 44px; height: 44px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .i-green  { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(34,197,94,0.25); }
        .i-amber  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .i-purple { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .i-muted  { background: rgba(77,98,120,0.2); color: var(--text-muted); border: 1px solid rgba(77,98,120,0.25); }
        .opsi-lbl { font-size: 14px; font-weight: 600; }
        .opsi-sub { font-size: 12px; color: var(--text-secondary); margin-top: 3px; line-height: 1.4; }
        .opsi-badge {
            margin-left: auto; flex-shrink: 0; font-size: 10px; font-weight: 600;
            padding: 3px 9px; border-radius: 20px; border: 1px solid transparent;
        }
        .badge-ok     { background: var(--green-dim);  color: var(--green);  border-color: rgba(34,197,94,0.3); }
        .badge-awal   { background: var(--amber-dim);  color: var(--amber);  border-color: rgba(245,158,11,0.3); }
        .badge-double { background: var(--purple-dim); color: var(--purple); border-color: rgba(167,139,250,0.3); }
        .badge-locked { background: rgba(77,98,120,0.2); color: var(--text-muted); border-color: rgba(77,98,120,0.3); display: flex; align-items: center; gap: 4px; }

        .panel-alasan, .panel-lanjut {
            display: none; margin-top: 18px; padding: 18px 20px;
            border-radius: var(--radius-lg); animation: fadeIn .25s ease;
        }
        .panel-alasan.show, .panel-lanjut.show { display: block; }
        .panel-alasan { background: var(--navy); border: 1px solid rgba(245,158,11,0.3); }
        .panel-lanjut { background: var(--navy); border: 1px solid rgba(167,139,250,0.3); }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .panel-ttl {
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.8px; margin-bottom: 12px; display: flex; align-items: center; gap: 7px;
        }
        .panel-alasan .panel-ttl { color: var(--amber); }
        .panel-lanjut .panel-ttl { color: var(--purple); }
        .form-label {
            display: block; font-size: 11px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 8px;
        }
        textarea.form-control {
            width: 100%; padding: 12px 14px;
            background: var(--navy-card); border: 1px solid var(--navy-line);
            border-radius: var(--radius-md); color: var(--text-primary);
            font-family: var(--font-main); font-size: 13px; line-height: 1.6;
            resize: vertical; min-height: 100px; transition: border-color 0.2s;
        }
        textarea.form-control:focus { outline: none; border-color: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
        .char-counter { font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 4px; }
        .lanjut-info { display: flex; gap: 12px; align-items: flex-start; }
        .lanjut-icon {
            width: 36px; height: 36px; border-radius: 8px;
            background: var(--purple-dim); color: var(--purple);
            border: 1px solid rgba(167,139,250,0.3);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        .lanjut-text { font-size: 12px; color: var(--text-secondary); line-height: 1.6; }
        .lanjut-text strong { color: var(--purple); }

        .empty-state { text-align: center; padding: 52px 24px; }
        .empty-icon {
            width: 72px; height: 72px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin: 0 auto 20px;
        }
        .empty-icon.lock { background: var(--amber-dim); border: 2px solid rgba(245,158,11,0.3); color: var(--amber); }
        .empty-icon.done { background: var(--teal-dim);  border: 2px solid rgba(45,212,191,0.3); color: var(--teal); }
        .empty-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .empty-sub   { font-size: 13px; color: var(--text-secondary); line-height: 1.6; }
        .btn-goto {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 22px; padding: 11px 22px;
            background: var(--green); border: none; border-radius: var(--radius-lg);
            color: white; font-family: var(--font-main); font-size: 13px; font-weight: 600;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-goto:hover { background: #16a34a; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(34,197,94,0.3); text-decoration: none; color: white; }
        .btn-goto.secondary { background: var(--navy-hover); border: 1px solid var(--navy-line); }
        .btn-goto.secondary:hover { background: var(--navy-card); box-shadow: none; }

        .btn-submit {
            width: 100%; padding: 14px; border: none; border-radius: var(--radius-lg);
            color: white; font-family: var(--font-main); font-size: 15px; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-submit.red    { background: var(--red); }
        .btn-submit.red:hover    { background: #e11d48; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(244,63,94,0.35); }
        .btn-submit.purple { background: var(--purple); }
        .btn-submit.purple:hover { background: #9061f9; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(167,139,250,0.35); }
        .btn-submit.amber  { background: var(--amber); }
        .btn-submit.amber:hover  { background: #d97706; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,0.35); }
        .btn-submit.green  { background: var(--green); }
        .btn-submit.green:hover  { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,197,94,0.35); }
        .btn-submit:active { transform: translateY(0) !important; }
        .btn-submit:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

        .success-state { display: none; text-align: center; padding: 48px 24px; }
        .success-state.show { display: block; }
        .success-circle {
            width: 80px; height: 80px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 32px; margin: 0 auto 20px;
            animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
        }
        .sc-red    { background: var(--red-dim);    border: 2px solid var(--red);    color: var(--red); }
        .sc-purple { background: var(--purple-dim); border: 2px solid var(--purple); color: var(--purple); }
        .sc-green  { background: var(--green-dim);  border: 2px solid var(--green);  color: var(--green); }
        @keyframes popIn { from { transform:scale(0.5); opacity:0; } to { transform:scale(1); opacity:1; } }
        .success-title { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .success-sub   { font-size: 13px; color: var(--text-secondary); }
        .success-redir { font-size: 12px; color: var(--text-muted); margin-top: 20px; }

        /* Auto-refresh indicator */
        .refresh-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 600; padding: 4px 10px;
            border-radius: 20px; background: rgba(59,158,255,0.12);
            color: var(--accent); border: 1px solid rgba(59,158,255,0.25);
            margin-top: 10px;
        }
        .refresh-badge .dot {
            width: 6px; height: 6px; border-radius: 50%; background: var(--accent);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.3;} }

        @media (max-width: 600px) {
            .navbar { padding: 0 16px; }
            .main   { padding: 20px 14px 60px; }
            .shift-summary { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        ANDALAN
    </a>
    <a href="dashboard.php" class="btn-back">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</nav>

<div class="main">

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

    <?php if ($dari_lanjut_shift): ?>
    <div class="banner-lanjut">
        <div class="banner-lanjut-icon"><i class="fas fa-rotate"></i></div>
        <div class="banner-lanjut-text">
            <strong>Lanjut Shift Dicatat!</strong>
            Absensi masuk untuk shift berikutnya sudah otomatis tercatat. Silakan pilih status keluar untuk shift ini.
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($pesan) && $tipe_pesan !== 'success'): ?>
    <div class="alert alert-<?= $tipe_pesan ?>">
        <i class="fas fa-<?= $tipe_pesan === 'error' ? 'times-circle' : 'exclamation-triangle' ?>"></i>
        <div class="alert-text">
            <strong><?= $tipe_pesan === 'error' ? 'Gagal' : 'Perhatian' ?></strong>
            <?= htmlspecialchars($pesan) ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tipe_pesan === 'success'): ?>
    <?php
        $is_lanjut = isset($_POST['status_keluar']) && $_POST['status_keluar'] === 'lanjut_shift';
        $is_tepat  = isset($_POST['status_keluar']) && $_POST['status_keluar'] === 'tepat_waktu';
        if ($is_lanjut) {
            $sc_cls = 'sc-purple'; $sc_icon = 'fa-rotate';       $sc_title = 'Lanjut Shift Dicatat!';
        } elseif ($is_tepat) {
            $sc_cls = 'sc-green';  $sc_icon = 'fa-check-circle'; $sc_title = 'Absen Keluar Berhasil!';
        } else {
            $sc_cls = 'sc-red';    $sc_icon = 'fa-sign-out-alt'; $sc_title = 'Absen Keluar Berhasil!';
        }
    ?>
    <div class="card">
        <div class="success-state show">
            <div class="success-circle <?= $sc_cls ?>"><i class="fas <?= $sc_icon ?>"></i></div>
            <div class="success-title"><?= $sc_title ?></div>
            <div class="success-sub"><?= htmlspecialchars($pesan) ?></div>
            <div style="font-family:var(--font-mono); font-size:22px; color:var(--<?= $is_lanjut ? 'purple' : ($is_tepat ? 'green' : 'red') ?>); margin-top:10px;"><?= date('H:i:s') ?></div>
            <div class="success-redir">
                <i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>
                Kembali ke dashboard dalam <span id="countdown">3</span> detik&hellip;
            </div>
        </div>
    </div>

    <?php elseif (!$absensi_aktif): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon lock"><i class="fas fa-lock"></i></div>
            <div class="empty-title">Belum Ada Absensi Masuk</div>
            <div class="empty-sub">
                Anda belum melakukan absen masuk pada shift hari ini.<br>
                Selesaikan absen masuk terlebih dahulu sebelum bisa absen keluar.
            </div>
            <a href="absen_masuk.php" class="btn-goto">
                <i class="fas fa-sign-in-alt"></i> Absen Masuk Sekarang
            </a>
        </div>
    </div>

    <?php elseif ($sudah_keluar): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon done"><i class="fas fa-check-double"></i></div>
            <div class="empty-title">Absensi Hari Ini Selesai</div>
            <div class="empty-sub">
                Anda sudah melakukan absen keluar pada shift ini.<br>
                Jam keluar tercatat: <strong style="color:var(--teal);"><?= date('H:i', strtotime($absensi_aktif['jam_keluar'])) ?></strong>
            </div>
            <a href="dashboard.php" class="btn-goto secondary" style="margin-top:22px;">
                <i class="fas fa-home"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <?php else: ?>
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

    <?php if ($tepat_waktu_terkunci): ?>
    <div class="banner-tunggu">
        <div class="banner-tunggu-icon"><i class="fas fa-user-clock"></i></div>
        <div>
            <div class="banner-tunggu-title">Menunggu Pamdal Pengganti</div>
            <div class="banner-tunggu-sub">
                Absen keluar normal baru bisa dilakukan setelah pamdal shift berikutnya
                sudah melakukan absen masuk sebagai tanda <strong>serah terima pos</strong>.
                Halaman ini akan otomatis refresh setiap 30 detik.
                <div class="refresh-badge">
                    <span class="dot"></span>
                    Auto-refresh aktif &mdash; <span id="refresh-cd">30</span>s lagi
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
        [$sum_icon, $sum_kls] = shiftIcon($absensi_aktif['nama_shift']);
        $jam_masuk_fmt  = date('H:i', strtotime($absensi_aktif['jam_masuk']));
        $jam_keluar_sch = substr($absensi_aktif['shift_jam_keluar'], 0, 5);
        $dur_menit      = (int)((time() - strtotime($absensi_aktif['jam_masuk'])) / 60);
        $durasi_str     = floor($dur_menit / 60) . 'j ' . ($dur_menit % 60) . 'm';
    ?>
    <div class="shift-summary">
        <div class="ss-icon <?= $sum_kls ?>"><i class="fas <?= $sum_icon ?>"></i></div>
        <div style="flex:1;">
            <div class="ss-nama"><?= htmlspecialchars($absensi_aktif['nama_shift']) ?></div>
            <div class="ss-meta">
                <span>
                    <i class="fas fa-sign-in-alt" style="font-size:10px; color:var(--green);"></i>
                    Masuk: <strong style="color:var(--text-primary);"><?= $jam_masuk_fmt ?></strong>
                </span>
                <span>
                    <i class="fas fa-sign-out-alt" style="font-size:10px; color:var(--red);"></i>
                    Jadwal pulang: <strong style="color:var(--text-primary);"><?= $jam_keluar_sch ?></strong>
                </span>
                <span>
                    <i class="fas fa-stopwatch" style="font-size:10px; color:var(--accent);"></i>
                    Durasi: <strong style="color:var(--text-primary);" id="durasi-kerja"><?= $durasi_str ?></strong>
                </span>
            </div>
        </div>
        <span style="font-size:10px; font-weight:600; padding:3px 9px; border-radius:20px; background:var(--green-dim); color:var(--green); border:1px solid rgba(34,197,94,0.3); flex-shrink:0;">
            <span class="dot-green"></span> AKTIF
        </span>
    </div>

    <form method="POST" id="form-keluar">
        <input type="hidden" name="absensi_id" value="<?= $absensi_aktif['id'] ?>">

        <div class="card">
            <div class="card-title">
                <i class="fas fa-clipboard-list"></i>
                Pilih Status Keluar
            </div>

            <div class="opsi-grid">
                <?php
                $first_selectable_idx = -1;
                foreach ($opsi_keluar as $idx => $o) {
                    $terkunci_ini = ($o['value'] === 'tepat_waktu' && $tepat_waktu_terkunci);
                    if (!$terkunci_ini && $first_selectable_idx === -1) {
                        $first_selectable_idx = $idx;
                    }
                }

                foreach ($opsi_keluar as $i => $opsi):
                    $is_locked = ($opsi['value'] === 'tepat_waktu' && $tepat_waktu_terkunci);

                    if ($opsi['value'] === 'tepat_waktu') {
                        $cls   = 'ok';
                        $icon  = $is_locked ? 'fa-lock' : 'fa-check-circle';
                        $icls  = $is_locked ? 'i-muted' : 'i-green';
                        $badge = $is_locked
                            ? '<span class="opsi-badge badge-locked"><i class="fas fa-lock"></i> MENUNGGU PENGGANTI</span>'
                            : '<span class="opsi-badge badge-ok">TERSEDIA</span>';
                        $sub = $is_locked
                            ? 'Tersedia setelah pamdal shift berikutnya absen masuk (serah terima).'
                            : 'Pamdal pengganti sudah absen masuk. Serah terima dapat dilakukan.';
                    } elseif ($opsi['value'] === 'pulang_awal') {
                        $cls   = 'early';
                        $icon  = 'fa-door-open';
                        $icls  = 'i-amber';
                        $badge = '<span class="opsi-badge badge-awal">WAJIB ALASAN</span>';
                        $sub   = 'Pulang sebelum ada pengganti — wajib isi alasan.';
                    } else {
                        $cls   = 'double';
                        $icon  = 'fa-rotate';
                        $icls  = 'i-purple';
                        $badge = '<span class="opsi-badge badge-double">DOUBLE SHIFT</span>';
                        $sub   = 'Lanjut ke shift berikutnya tanpa absen masuk ulang.';
                    }

                    $is_default = ($i === $first_selectable_idx);
                ?>
                <div>
                    <input type="radio"
                           name="status_keluar"
                           id="opsi_<?= $opsi['value'] ?>"
                           value="<?= $opsi['value'] ?>"
                           class="opsi-radio <?= $cls ?>"
                           <?= $is_default ? 'checked' : '' ?>
                           <?= $is_locked  ? 'disabled' : '' ?>>
                    <label for="opsi_<?= $opsi['value'] ?>"
                           class="opsi-label<?= $is_locked ? ' opsi-locked' : '' ?>">
                        <div class="radio-dot"></div>
                        <div class="opsi-icon <?= $icls ?>"><i class="fas <?= $icon ?>"></i></div>
                        <div style="flex:1;">
                            <div class="opsi-lbl"><?= $opsi['label'] ?></div>
                            <div class="opsi-sub"><?= $sub ?></div>
                        </div>
                        <?= $badge ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="panel-alasan" id="panel-alasan">
                <div class="panel-ttl">
                    <i class="fas fa-exclamation-triangle"></i>
                    Alasan Pulang Lebih Awal — Wajib Diisi
                </div>
                <label class="form-label" for="alasan">Tuliskan alasan Anda</label>
                <textarea name="alasan" id="alasan" class="form-control"
                    placeholder="Contoh: Ada keperluan keluarga mendesak / kondisi kesehatan menurun..."
                    maxlength="500" rows="4"></textarea>
                <div style="display:flex; justify-content:space-between; margin-top:4px;">
                    <div class="form-hint">Minimal 10 karakter. Alasan direkam untuk laporan.</div>
                    <div class="char-counter"><span id="char-count">0</span>/500</div>
                </div>
            </div>

            <div class="panel-lanjut" id="panel-lanjut">
                <div class="panel-ttl"><i class="fas fa-info-circle"></i> Informasi Double Shift</div>
                <div class="lanjut-info">
                    <div class="lanjut-icon"><i class="fas fa-rotate"></i></div>
                    <div class="lanjut-text">
                        <strong>Sistem otomatis</strong> akan langsung mencatat absensi masuk untuk shift
                        berikutnya dan halaman ini akan <strong>terbuka kembali</strong> dengan
                        ketiga pilihan keluar aktif. Anda <strong>tidak perlu absen masuk ulang</strong>.
                        Pastikan kondisi fisik Anda memadai sebelum melanjutkan shift.
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-submit red" id="btn-submit">
            <i class="fas fa-sign-out-alt" id="btn-icon"></i>
            <span id="btn-text">Konfirmasi Absen Keluar</span>
        </button>

        <p style="text-align:center; font-size:12px; color:var(--text-muted); margin-top:14px;">
            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
            Opsi <strong style="color:var(--green);">Absen Keluar Normal</strong> tersedia setelah
            pamdal shift berikutnya sudah melakukan absen masuk (serah terima pos).
        </p>
    </form>
    <?php endif; ?>

</div>

<script>
(function tick() {
    const el = document.getElementById('live-clock');
    if (el) {
        const n = new Date(), p = v => String(v).padStart(2,'0');
        el.textContent = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
    }
    setTimeout(tick, 1000);
})();

<?php if (!$sudah_keluar && $absensi_aktif && $tipe_pesan !== 'success'): ?>
(function() {
    const el    = document.getElementById('durasi-kerja');
    const masuk = new Date('<?= str_replace(' ', 'T', $absensi_aktif['jam_masuk']) ?>');
    function tick() {
        if (!el) return;
        const d = Math.floor((Date.now() - masuk) / 60000);
        el.textContent = Math.floor(d/60) + 'j ' + (d%60) + 'm';
    }
    tick(); setInterval(tick, 30000);
})();
<?php endif; ?>

<?php if ($tipe_pesan === 'success'): ?>
let cd = 3;
const cdEl = document.getElementById('countdown');
const t = setInterval(() => {
    cd--; if (cdEl) cdEl.textContent = cd;
    if (cd <= 0) { clearInterval(t); location.href = 'dashboard.php'; }
}, 1000);
<?php endif; ?>

<?php if ($tepat_waktu_terkunci && $absensi_aktif && $tipe_pesan !== 'success'): ?>
// Auto-refresh setiap 30 detik untuk cek apakah pamdal pengganti sudah masuk
let refreshCount = 30;
const refreshEl = document.getElementById('refresh-cd');
const refreshTimer = setInterval(() => {
    refreshCount--;
    if (refreshEl) refreshEl.textContent = refreshCount;
    if (refreshCount <= 0) {
        clearInterval(refreshTimer);
        location.reload();
    }
}, 1000);
<?php endif; ?>

const panelAlasan = document.getElementById('panel-alasan');
const panelLanjut = document.getElementById('panel-lanjut');
const btnSubmit   = document.getElementById('btn-submit');
const btnIcon     = document.getElementById('btn-icon');
const btnText     = document.getElementById('btn-text');
const tepat_waktu_terkunci = <?= $tepat_waktu_terkunci ? 'true' : 'false' ?>;

function updatePanel() {
    const checked = document.querySelector('input[name="status_keluar"]:checked');
    const val = checked ? checked.value : null;

    panelAlasan?.classList.remove('show');
    panelLanjut?.classList.remove('show');
    const alasanEl = document.getElementById('alasan');

    if (val === 'pulang_awal') {
        panelAlasan?.classList.add('show');
        if (alasanEl) alasanEl.required = true;
        if (btnSubmit) { btnSubmit.className = 'btn-submit amber'; btnSubmit.disabled = false; }
        if (btnIcon)   btnIcon.className   = 'fas fa-door-open';
        if (btnText)   btnText.textContent = 'Konfirmasi Pulang Lebih Awal';
    } else if (val === 'lanjut_shift') {
        panelLanjut?.classList.add('show');
        if (alasanEl) alasanEl.required = false;
        if (btnSubmit) { btnSubmit.className = 'btn-submit purple'; btnSubmit.disabled = false; }
        if (btnIcon)   btnIcon.className   = 'fas fa-rotate';
        if (btnText)   btnText.textContent = 'Konfirmasi Lanjut Shift';
    } else if (val === 'tepat_waktu') {
        if (alasanEl) alasanEl.required = false;
        if (tepat_waktu_terkunci) {
            if (btnSubmit) { btnSubmit.className = 'btn-submit red'; btnSubmit.disabled = true; }
            if (btnIcon)   btnIcon.className   = 'fas fa-lock';
            if (btnText)   btnText.textContent = 'Menunggu Pamdal Pengganti';
        } else {
            if (btnSubmit) { btnSubmit.className = 'btn-submit green'; btnSubmit.disabled = false; }
            if (btnIcon)   btnIcon.className   = 'fas fa-check-circle';
            if (btnText)   btnText.textContent = 'Konfirmasi Absen Keluar Normal';
        }
    } else {
        if (alasanEl) alasanEl.required = false;
        if (btnSubmit) { btnSubmit.className = 'btn-submit red'; btnSubmit.disabled = true; }
        if (btnIcon)   btnIcon.className   = 'fas fa-sign-out-alt';
        if (btnText)   btnText.textContent = 'Pilih Status Keluar';
    }
}

document.querySelectorAll('input[name="status_keluar"]').forEach(r =>
    r.addEventListener('change', updatePanel)
);
window.addEventListener('load', updatePanel);

const alasanEl  = document.getElementById('alasan');
const charCount = document.getElementById('char-count');
if (alasanEl && charCount) {
    alasanEl.addEventListener('input', () => {
        charCount.textContent = alasanEl.value.length;
        charCount.style.color = alasanEl.value.length < 10 ? 'var(--amber)' : 'var(--text-muted)';
    });
}

document.getElementById('form-keluar')?.addEventListener('submit', function(e) {
    const val = document.querySelector('input[name="status_keluar"]:checked')?.value;

    if (val === 'tepat_waktu' && tepat_waktu_terkunci) {
        e.preventDefault();
        alert('Absen keluar normal belum tersedia.\nTunggu hingga pamdal shift berikutnya sudah absen masuk.');
        return;
    }

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
        tepat_waktu:  'Absen Keluar Normal',
        pulang_awal:  'Pulang Lebih Awal',
        lanjut_shift: 'Lanjut Shift Berikutnya'
    };

    const extraNote = val === 'lanjut_shift'
        ? '\n\nAbsen masuk shift berikutnya akan otomatis tercatat\ndan halaman ini akan terbuka kembali.'
        : '';

    const ok = confirm(
        'Konfirmasi Absen Keluar\n\n' +
        'Status : ' + (labelMap[val] || val) + '\n' +
        'Waktu  : ' + (document.getElementById('live-clock')?.textContent || '') +
        extraNote + '\n\nPastikan data sudah benar sebelum melanjutkan.'
    );
    if (!ok) e.preventDefault();
});
</script>
</body>
</html>