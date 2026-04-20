<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

$user_id      = $_SESSION['user_id'];
$tanggal      = date('Y-m-d');
$semua_shift  = getAllShifts();
$semua_pamdal = getAllUsersForDropdown($user_id);

// Ambil occupancy semua shift hari ini (siapa saja yang sudah masuk per shift)
$shift_occupancy = getShiftOccupancy($tanggal, $user_id);

// ----------------------------------------------------------------
// Cari absensi aktif user hari ini = sudah masuk, BELUM keluar
// (bisa lebih dari satu jika double shift, ambil yang terakhir aktif)
// ----------------------------------------------------------------
function getAbsensiAktifUser($user_id, $tanggal) {
    global $conn;
    $user_id = (int)$user_id;
    $tanggal = $conn->real_escape_string($tanggal);
    $sql = "SELECT a.id, a.shift_id, a.status_masuk,
                   TIME_FORMAT(a.jam_masuk, '%H:%i:%s') AS jam_masuk,
                   a.jam_keluar,
                   s.nama_shift
            FROM absensi a
            JOIN shift s ON a.shift_id = s.id
            WHERE a.user_id = $user_id
              AND a.tanggal = '$tanggal'
              AND a.jam_masuk IS NOT NULL
              AND a.jam_keluar IS NULL   -- belum absen keluar = sesi aktif
            ORDER BY a.id DESC
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Semua absensi hari ini (termasuk yang sudah keluar) — untuk keperluan tampilan riwayat
function getSemuaAbsensiHariIni($user_id, $tanggal) {
    global $conn;
    $user_id = (int)$user_id;
    $tanggal = $conn->real_escape_string($tanggal);
    $sql = "SELECT a.id, a.shift_id, a.status_masuk,
                   TIME_FORMAT(a.jam_masuk, '%H:%i:%s') AS jam_masuk,
                   a.jam_keluar,
                   s.nama_shift
            FROM absensi a
            JOIN shift s ON a.shift_id = s.id
            WHERE a.user_id = $user_id
              AND a.tanggal = '$tanggal'
              AND a.jam_masuk IS NOT NULL
            ORDER BY a.id ASC";
    $result = $conn->query($sql);
    $rows = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function getLaporanDetail($absensi_id) {
    global $conn;
    $absensi_id = (int)$absensi_id;
    $result = $conn->query("SELECT id, isi_laporan, status, catatan_revisi FROM laporan WHERE absensi_id = $absensi_id LIMIT 1");
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function sudahKirimLaporan($absensi_id) {
    global $conn;
    $absensi_id = (int)$absensi_id;
    $result = $conn->query("SELECT id FROM laporan WHERE absensi_id = $absensi_id LIMIT 1");
    return ($result && $result->num_rows > 0);
}

function sudahAbsenKeluar($absensi_id) {
    global $conn;
    $absensi_id = (int)$absensi_id;
    $result = $conn->query("SELECT id FROM absensi WHERE id = $absensi_id AND jam_keluar IS NOT NULL LIMIT 1");
    return ($result && $result->num_rows > 0);
}

$semua_absensi_hari_ini = getSemuaAbsensiHariIni($user_id, $tanggal);
$absensi_aktif          = getAbsensiAktifUser($user_id, $tanggal);

// Ada sesi aktif (sudah masuk, belum keluar)?
$ada_sesi_aktif      = ($absensi_aktif !== null);
$absensi_id_server   = $ada_sesi_aktif ? (int)$absensi_aktif['id']       : 0;
$shift_id_server     = $ada_sesi_aktif ? (int)$absensi_aktif['shift_id'] : 0;
$status_masuk_server = $ada_sesi_aktif ? $absensi_aktif['status_masuk']  : '';
$jam_masuk_server    = $ada_sesi_aktif ? $absensi_aktif['jam_masuk']     : '';

// Laporan hanya relevan untuk sesi aktif
$sudah_kirim_laporan = $ada_sesi_aktif && sudahKirimLaporan($absensi_id_server);
$laporan_detail      = $sudah_kirim_laporan ? getLaporanDetail($absensi_id_server) : null;
$isi_laporan_db      = $laporan_detail ? $laporan_detail['isi_laporan'] : '';
$status_laporan_db   = $laporan_detail ? $laporan_detail['status']      : '';

// Jumlah absensi hari ini (untuk banner double shift)
$jumlah_absensi = count($semua_absensi_hari_ini);
$is_double      = $jumlah_absensi > 1;

usort($semua_shift, function($a, $b) {
    return strcmp($a['jam_masuk'], $b['jam_masuk']);
});

/* ─── AJAX handler ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'absen_masuk') {
        $shift_id     = (int)($_POST['shift_id'] ?? 0);
        $status_masuk = cleanInput($_POST['status_masuk'] ?? '');

        if ($shift_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Harap pilih shift terlebih dahulu.']);
            exit;
        }
        if (sudahAbsenMasuk($user_id, $shift_id, $tanggal)) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah absen masuk pada shift ini hari ini.']);
            exit;
        }

        $data_penukaran = [];
        if ($status_masuk === 'tidak_sesuai') {
            $data_penukaran = [
                'tipe'              => cleanInput($_POST['tipe_penukaran'] ?? ''),
                'user_pengganti_id' => (int)($_POST['user_pengganti_id'] ?? 0),
                'tanggal'           => cleanInput($_POST['tanggal_penukaran'] ?? ''),
                'shift_id'          => (int)($_POST['shift_penukaran_id'] ?? 0),
            ];
        }

        $result = absenMasuk($user_id, $shift_id, $status_masuk, $data_penukaran);

        if ($result['success'] && $status_masuk === 'tidak_sesuai') {
            $isi = 'Absen masuk tidak sesuai jadwal. Tipe: ' . $data_penukaran['tipe']
                 . '. User pengganti ID: ' . $data_penukaran['user_pengganti_id'] . '.';
            buatLaporan($result['absensi_id'], $isi);
        }

        if ($result['success']) {
            $shift_info = getShiftById($shift_id);
            $result['shift_nama']   = $shift_info['nama_shift'] ?? '';
            $result['jam_sekarang'] = date('H:i:s');
        }

        echo json_encode($result);
        exit;
    }

    if ($aksi === 'kirim_laporan') {
        $absensi_id = (int)($_POST['absensi_id'] ?? 0);
        $isi        = cleanInput($_POST['isi_laporan'] ?? '');

        if (empty($isi)) {
            echo json_encode(['success' => false, 'message' => 'Isi laporan tidak boleh kosong.']);
            exit;
        }

        global $conn;
        $cek = $conn->query("SELECT id, jam_keluar FROM absensi
                             WHERE id = $absensi_id AND user_id = $user_id LIMIT 1");
        if (!$cek || $cek->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Data absensi tidak ditemukan.']);
            exit;
        }
        $cek_row = $cek->fetch_assoc();
        if (!empty($cek_row['jam_keluar'])) {
            echo json_encode(['success' => false, 'message' => 'Laporan tidak dapat dikirim setelah absen keluar.']);
            exit;
        }

        $res = buatLaporan($absensi_id, $isi);
        echo json_encode($res);
        exit;
    }

    if ($aksi === 'edit_laporan') {
        $absensi_id = (int)($_POST['absensi_id'] ?? 0);
        $isi        = cleanInput($_POST['isi_laporan'] ?? '');
        $laporan_id = (int)($_POST['laporan_id'] ?? 0);

        if (empty($isi)) {
            echo json_encode(['success' => false, 'message' => 'Isi laporan tidak boleh kosong.']);
            exit;
        }
        if (strlen($isi) < 5) {
            echo json_encode(['success' => false, 'message' => 'Isi laporan minimal 5 karakter.']);
            exit;
        }

        global $conn;

        $cek = $conn->query("SELECT id, jam_keluar FROM absensi
                             WHERE id = $absensi_id AND user_id = $user_id LIMIT 1");
        if (!$cek || $cek->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Data absensi tidak ditemukan.']);
            exit;
        }
        $cek_row = $cek->fetch_assoc();
        if (!empty($cek_row['jam_keluar'])) {
            echo json_encode(['success' => false, 'message' => 'Laporan tidak dapat diubah setelah absen keluar.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE laporan SET isi_laporan = ?, status = 'pending', catatan_revisi = NULL WHERE id = ? AND absensi_id = ?");
        $stmt->bind_param('sii', $isi, $laporan_id, $absensi_id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Laporan berhasil diperbarui.']);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui laporan: ' . $conn->error]);
        }
        exit;
    }

    // AJAX: refresh occupancy (dipanggil frontend setelah sukses absen)
    if ($aksi === 'get_occupancy') {
        $occ = getShiftOccupancy($tanggal, $user_id);
        echo json_encode(['success' => true, 'occupancy' => $occ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Absen Masuk — ANDALAN</title>
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
        body { font-family: var(--font-main); background-color: var(--navy); color: var(--text-primary); min-height: 100vh; -webkit-font-smoothing: antialiased; }

        .navbar { position: sticky; top: 0; z-index: 100; background: var(--navy-mid); border-bottom: 1px solid var(--navy-line); padding: 0 28px; height: 58px; display: flex; align-items: center; justify-content: space-between; backdrop-filter: blur(10px); }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 600; color: var(--text-primary); letter-spacing: 0.5px; text-decoration: none; }
        .brand-icon { width: 32px; height: 32px; background: var(--accent); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 14px; color: white; }
        .btn-back { display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; padding: 6px 14px; border-radius: var(--radius-sm); background: transparent; border: 1px solid var(--navy-line); color: var(--text-secondary); cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-back:hover { background: var(--navy-hover); border-color: rgba(255,255,255,.15); color: var(--text-primary); text-decoration: none; }

        .toast-wrap { position: fixed; top: 70px; left: 50%; transform: translateX(-50%); z-index: 9999; width: 100%; max-width: 500px; padding: 0 16px; pointer-events: none; }
        .toast { display: flex; align-items: flex-start; gap: 14px; padding: 16px 20px; border-radius: var(--radius-lg); font-size: 13px; line-height: 1.55; box-shadow: 0 8px 32px rgba(0,0,0,0.45); pointer-events: all; transform: translateY(-16px); opacity: 0; transition: all 0.35s cubic-bezier(.34,1.56,.64,1); }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.hide { transform: translateY(-16px); opacity: 0; transition: all .25s ease; }
        .toast-success { background: #0d2a1a; border: 1px solid rgba(34,197,94,0.5);  color: var(--green); }
        .toast-error   { background: #2a0d14; border: 1px solid rgba(244,63,94,0.5);  color: var(--red); }
        .toast-warning { background: #2a200d; border: 1px solid rgba(245,158,11,0.5); color: var(--amber); }
        .toast-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
        .toast-body strong { display: block; font-size: 14px; font-weight: 700; margin-bottom: 3px; }
        .toast-close { margin-left: auto; background: none; border: none; color: inherit; opacity: 0.6; cursor: pointer; font-size: 14px; padding: 0 0 0 8px; flex-shrink: 0; }
        .toast-close:hover { opacity: 1; }

        .main { max-width: 680px; margin: 0 auto; padding: 36px 20px 80px; }

        .page-header { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; }
        .page-icon { width: 52px; height: 52px; border-radius: 14px; background: var(--green-dim); border: 1px solid rgba(34,197,94,0.35); display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--green); flex-shrink: 0; }
        .page-title { font-size: 22px; font-weight: 700; }
        .page-sub   { font-size: 13px; color: var(--text-secondary); margin-top: 4px; }

        .info-box { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: var(--navy); border: 1px solid var(--navy-line); border-radius: var(--radius-md); margin-bottom: 20px; }
        .info-box-icon { width: 36px; height: 36px; border-radius: 8px; background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .info-box-label { font-size: 11px; color: var(--text-muted); }
        .info-box-val   { font-size: 15px; font-weight: 600; font-family: var(--font-mono); }

        /* Banner sesi aktif */
        .banner-status { display: flex; align-items: center; gap: 14px; padding: 14px 18px; border-radius: var(--radius-lg); margin-bottom: 20px; }
        .banner-status.sudah-absen  { background: var(--green-dim);  border: 1px solid rgba(34,197,94,0.35);  color: var(--green); }
        .banner-status.double-shift { background: var(--purple-dim); border: 1px solid rgba(167,139,250,0.35); color: var(--purple); }
        .banner-status-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(34,197,94,0.2); border: 1px solid rgba(34,197,94,0.3); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
        .banner-status.double-shift .banner-status-icon { background: rgba(167,139,250,0.2); border-color: rgba(167,139,250,0.3); }
        .banner-status-title  { font-size: 14px; font-weight: 700; }
        .banner-status-sub    { font-size: 12px; opacity: 0.8; margin-top: 2px; }

        .card { background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-xl); padding: 26px 28px; margin-bottom: 20px; }
        .card-title { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.9px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }

        /* ── Shift Grid ── */
        .shift-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .shift-option { display: none; }
        .shift-label { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; padding: 18px 12px; border-radius: var(--radius-lg); border: 1.5px solid var(--navy-line); background: var(--navy); cursor: pointer; transition: all 0.2s; text-align: center; position: relative; overflow: hidden; }
        .shift-label:hover:not(.locked) { border-color: rgba(59,158,255,0.4); background: var(--accent-dim); }
        .shift-option:checked + .shift-label { border-color: var(--accent); background: var(--accent-dim); box-shadow: 0 0 0 3px rgba(59,158,255,0.15); }
        /* Shift dikunci karena ada petugas lain */
        .shift-label.locked { opacity: 0.35; cursor: not-allowed; filter: grayscale(0.6); pointer-events: none; }
        /* Shift milik sesi aktif user sendiri */
        .shift-label.sesi-aktif { border-color: var(--green) !important; background: var(--green-dim) !important; box-shadow: 0 0 0 3px rgba(34,197,94,0.15) !important; }
        .shift-check { position: absolute; top: 8px; right: 8px; width: 18px; height: 18px; border-radius: 50%; background: var(--accent); color: white; display: none; align-items: center; justify-content: center; font-size: 9px; }
        .shift-option:checked + .shift-label .shift-check { display: flex; }
        .shift-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
        .shift-icon.pagi  { background: var(--amber-dim);  color: var(--amber);  border: 1px solid rgba(245,158,11,0.25); }
        .shift-icon.sore  { background: var(--teal-dim);   color: var(--teal);   border: 1px solid rgba(45,212,191,0.25); }
        .shift-icon.malam { background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(167,139,250,0.25); }
        .shift-icon.lain  { background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.25); }
        .shift-nama { font-size: 13px; font-weight: 600; }
        .shift-jam  { font-size: 11px; color: var(--text-secondary); font-family: var(--font-mono); }

        /* Badge: AKTIF = shift yg sudah ada petugasnya */
        .badge-aktif { font-size: 9px; font-weight: 600; padding: 2px 7px; border-radius: 20px; background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.3); }
        /* Badge: SAYA = sesi aktif milik sendiri */
        .badge-saya  { font-size: 9px; font-weight: 600; padding: 2px 7px; border-radius: 20px; background: var(--accent-dim); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); }
        /* Badge: nama petugas di shift */
        .badge-petugas { font-size: 9px; font-weight: 600; padding: 2px 7px; border-radius: 20px; background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.3); max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .info-telat { display: none; margin-top: 14px; padding: 12px 16px; border-radius: var(--radius-md); font-size: 12px; line-height: 1.5; }
        .info-telat.show { display: flex; gap: 10px; align-items: flex-start; }

        /* ── Status Grid ── */
        .status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .status-option { display: none; }
        .status-label { display: flex; align-items: center; gap: 14px; padding: 16px 18px; border-radius: var(--radius-lg); border: 1.5px solid var(--navy-line); background: var(--navy); cursor: pointer; transition: all 0.2s; }
        .status-label:hover:not(.locked) { border-color: rgba(255,255,255,0.15); background: var(--navy-hover); }
        .status-option:checked + .status-label { border-color: var(--accent); background: var(--accent-dim); box-shadow: 0 0 0 3px rgba(59,158,255,0.12); }
        .status-label.locked { opacity: 0.32; cursor: not-allowed; filter: grayscale(0.5); pointer-events: none; }
        .status-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .s-green { background: var(--green-dim); color: var(--green); border: 1px solid rgba(34,197,94,0.25); }
        .s-amber { background: var(--amber-dim); color: var(--amber); border: 1px solid rgba(245,158,11,0.25); }
        .status-text-lbl { font-size: 13px; font-weight: 600; }
        .status-text-sub { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

        .panel-tidak-sesuai { display: none; margin-top: 18px; padding: 20px; border-radius: var(--radius-lg); background: var(--navy); border: 1px solid rgba(245,158,11,0.25); }
        .panel-tidak-sesuai.show { display: block; animation: fadeIn .25s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .panel-title { font-size: 11px; font-weight: 600; color: var(--amber); text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 16px; display: flex; align-items: center; gap: 7px; }
        .tipe-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
        .tipe-option { display: none; }
        .tipe-label { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: var(--radius-md); border: 1.5px solid var(--navy-line); background: var(--navy-card); cursor: pointer; transition: all 0.2s; }
        .tipe-label:hover { border-color: rgba(255,255,255,0.15); }
        .tipe-option:checked + .tipe-label { border-color: var(--amber); background: var(--amber-dim); }
        .tipe-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--text-muted); flex-shrink: 0; transition: all 0.2s; }
        .tipe-option:checked + .tipe-label .tipe-dot { border-color: var(--amber); background: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,0.2); }
        .tipe-text { font-size: 12px; font-weight: 600; }
        .tipe-sub  { font-size: 11px; color: var(--text-muted); }

        .form-row { margin-bottom: 14px; }
        .form-label { display: block; font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 7px; }
        .form-control { width: 100%; padding: 10px 14px; background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-md); color: var(--text-primary); font-family: var(--font-main); font-size: 13px; transition: border-color 0.2s; appearance: none; }
        .form-control:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,158,255,0.12); }
        select.form-control { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237a90a8' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
        .form-hint { font-size: 11px; color: var(--text-muted); margin-top: 5px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .divider   { height: 1px; background: var(--navy-line); margin: 18px 0; }

        /* ── Panel Laporan ── */
        .panel-laporan { display: none; margin-top: 20px; border-radius: var(--radius-lg); overflow: hidden; }
        .panel-laporan.show { display: block; animation: fadeIn .3s ease; }
        .laporan-header { padding: 14px 18px; background: linear-gradient(135deg, rgba(59,158,255,0.12), rgba(59,158,255,0.06)); border: 1px solid rgba(59,158,255,0.25); border-bottom: none; border-radius: var(--radius-lg) var(--radius-lg) 0 0; display: flex; align-items: center; gap: 12px; }
        .laporan-header-icon { width: 36px; height: 36px; border-radius: 9px; background: rgba(59,158,255,0.15); color: var(--accent); border: 1px solid rgba(59,158,255,0.3); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .laporan-header-title { font-size: 13px; font-weight: 700; }
        .laporan-header-sub   { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
        .laporan-body { padding: 18px; background: var(--navy); border: 1px solid rgba(59,158,255,0.2); border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
        .laporan-textarea { width: 100%; min-height: 110px; padding: 12px 14px; background: var(--navy-card); border: 1px solid var(--navy-line); border-radius: var(--radius-md); color: var(--text-primary); font-family: var(--font-main); font-size: 13px; line-height: 1.6; resize: vertical; transition: border-color 0.2s; }
        .laporan-textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,158,255,0.12); }
        .laporan-textarea::placeholder { color: var(--text-muted); }
        .laporan-char { font-size: 11px; color: var(--text-muted); text-align: right; margin-top: 5px; }

        .laporan-terkirim-wrap { border-radius: var(--radius-md); overflow: hidden; }
        .laporan-terkirim-header { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: var(--green-dim); border: 1px solid rgba(34,197,94,0.3); color: var(--green); }
        .laporan-terkirim-header.updated { background: var(--accent-dim); border-color: rgba(59,158,255,0.3); color: var(--accent); }
        .laporan-terkirim-header i { font-size: 18px; flex-shrink: 0; }
        .laporan-terkirim-title { font-size: 13px; font-weight: 700; }
        .laporan-terkirim-sub   { font-size: 11px; opacity: 0.8; margin-top: 1px; }
        .laporan-terkirim-actions { display: flex; gap: 8px; margin-left: auto; flex-shrink: 0; }
        .laporan-isi-preview { padding: 12px 14px; margin: 0; background: var(--navy-card); border: 1px solid var(--navy-line); border-top: none; border-radius: 0 0 var(--radius-md) var(--radius-md); font-size: 13px; line-height: 1.6; color: var(--text-secondary); }

        .btn-edit-laporan { display: flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: var(--radius-sm); background: rgba(59,158,255,0.15); border: 1px solid rgba(59,158,255,0.3); color: var(--accent); font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .btn-edit-laporan:hover { background: rgba(59,158,255,0.25); }
        .laporan-edit-area { margin-top: 14px; animation: fadeIn .25s ease; }
        .laporan-edit-actions { display: flex; gap: 8px; margin-top: 10px; }

        .btn-laporan-kirim { flex: 1; padding: 10px; background: var(--accent); border: none; border-radius: var(--radius-md); color: white; font-family: var(--font-main); font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 7px; }
        .btn-laporan-kirim:hover:not(:disabled) { background: #2488e8; transform: translateY(-1px); }
        .btn-laporan-kirim:disabled { opacity: 0.45; cursor: not-allowed; transform: none !important; }
        .btn-laporan-kirim.full { width: 100%; margin-top: 12px; }
        .btn-laporan-batal { padding: 10px 18px; background: transparent; border: 1px solid var(--navy-line); border-radius: var(--radius-md); color: var(--text-secondary); font-family: var(--font-main); font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .btn-laporan-batal:hover { background: var(--navy-hover); border-color: rgba(255,255,255,0.15); color: var(--text-primary); }

        .laporan-ditutup { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: var(--radius-md); background: var(--navy-card); border: 1px solid var(--navy-line); color: var(--text-secondary); font-size: 13px; }

        .btn-submit { width: 100%; padding: 14px; background: var(--green); border: none; border-radius: var(--radius-lg); color: white; font-family: var(--font-main); font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-submit:hover:not(:disabled):not(.done) { background: #16a34a; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,197,94,0.3); }
        .btn-submit:disabled:not(.done) { opacity: 0.45; cursor: not-allowed; }
        .btn-submit.done { background: var(--navy); border: 1.5px solid var(--green); color: var(--green); cursor: default; }
        .btn-submit.done:hover { transform: none; box-shadow: none; }

        @media (max-width: 600px) {
            .shift-grid  { grid-template-columns: 1fr 1fr; }
            .status-grid { grid-template-columns: 1fr; }
            .tipe-grid   { grid-template-columns: 1fr; }
            .form-grid   { grid-template-columns: 1fr; }
            .navbar      { padding: 0 16px; }
            .main        { padding: 20px 14px 60px; }
            .laporan-edit-actions { flex-direction: column; }
            .laporan-terkirim-actions { flex-direction: column; gap: 4px; }
        }
        @media (max-width: 380px) { .shift-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="toast-wrap">
    <div class="toast" id="toast">
        <span class="toast-icon" id="toast-icon"></span>
        <div class="toast-body">
            <strong id="toast-title"></strong>
            <span id="toast-msg"></span>
        </div>
        <button class="toast-close" onclick="hideToast()"><i class="fas fa-times"></i></button>
    </div>
</div>

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
        <div class="page-icon"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <div class="page-title">Absen Masuk</div>
            <div class="page-sub">
                <?= date('l, d F Y') ?> &mdash;
                Halo, <strong><?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?></strong>
            </div>
        </div>
    </div>

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

    <?php if ($ada_sesi_aktif): ?>
    <div class="banner-status <?= $is_double ? 'double-shift' : 'sudah-absen' ?>" id="banner-sudah">
        <div class="banner-status-icon">
            <i class="fas <?= $is_double ? 'fa-rotate' : 'fa-check-circle' ?>"></i>
        </div>
        <div>
            <?php if ($is_double): ?>
                <div class="banner-status-title">Double Shift — Shift ke-<?= $jumlah_absensi ?> sedang berjalan</div>
                <div class="banner-status-sub">
                    Shift aktif: <?= htmlspecialchars($absensi_aktif['nama_shift']) ?>
                    &mdash; masuk pukul <?= htmlspecialchars($jam_masuk_server) ?>
                </div>
            <?php else: ?>
                <div class="banner-status-title">Anda sedang dalam sesi shift</div>
                <div class="banner-status-sub">
                    Masuk pukul <?= htmlspecialchars($jam_masuk_server) ?>
                    &mdash; shift <?= htmlspecialchars($absensi_aktif['nama_shift']) ?>
                    &mdash; lakukan absen keluar bila sudah selesai
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- LANGKAH 1 — PILIH SHIFT -->
    <div class="card">
        <div class="card-title"><i class="fas fa-layer-group"></i> Langkah 1 &mdash; Pilih Shift</div>

        <div class="shift-grid" id="shift-grid">
            <?php foreach ($semua_shift as $shift):
                $sid = (int)$shift['id'];
                $nl  = strtolower($shift['nama_shift']);
                if     (str_contains($nl,'pagi'))                               { $ico='fa-sun';          $kls='pagi'; }
                elseif (str_contains($nl,'siang') || str_contains($nl,'sore')) { $ico='fa-cloud-sun';    $kls='sore'; }
                elseif (str_contains($nl,'malam'))                              { $ico='fa-moon';         $kls='malam'; }
                else                                                             { $ico='fa-calendar-day'; $kls='lain'; }

                // Apakah shift ini adalah sesi aktif user sendiri?
                $is_sesi_sendiri = $ada_sesi_aktif && ($sid === $shift_id_server);

                // Apakah shift ini diisi oleh user LAIN?
                $occ          = $shift_occupancy[$sid] ?? null;
                $is_occupied  = $occ && !$occ['is_self'];   // ada user lain di sini
                $nama_petugas = $occ ? $occ['nama_user'] : '';

                // Locked jika sudah ada user lain, ATAU user sendiri sudah masuk di shift lain
                // (tapi jangan locked shift milik sendiri yang masih aktif)
                $is_locked = $is_occupied; // Hanya locked jika ada user LAIN

                $disabled = $is_locked ? 'disabled' : '';
                $checked  = $is_sesi_sendiri ? 'checked' : '';
            ?>
            <div>
                <input type="radio" name="shift_id" id="shift_<?= $sid ?>"
                       value="<?= $sid ?>"
                       class="shift-option"
                       data-jam-masuk="<?= htmlspecialchars($shift['jam_masuk']) ?>"
                       data-jam-keluar="<?= htmlspecialchars($shift['jam_keluar']) ?>"
                       data-nama="<?= htmlspecialchars($shift['nama_shift']) ?>"
                       data-occupied="<?= $is_occupied ? '1' : '0' ?>"
                       data-sesi-sendiri="<?= $is_sesi_sendiri ? '1' : '0' ?>"
                       <?= $checked ?> <?= $disabled ?>
                       onchange="onShiftChange(this)">
                <label for="shift_<?= $sid ?>"
                       class="shift-label <?= $is_locked ? 'locked' : '' ?> <?= $is_sesi_sendiri ? 'sesi-aktif' : '' ?>"
                       id="lbl-shift-<?= $sid ?>">
                    <span class="shift-check"><i class="fas fa-check"></i></span>
                    <div class="shift-icon <?= $kls ?>"><i class="fas <?= $ico ?>"></i></div>
                    <div class="shift-nama"><?= htmlspecialchars($shift['nama_shift']) ?></div>
                    <div class="shift-jam"><?= substr($shift['jam_masuk'],0,5) ?>&ndash;<?= substr($shift['jam_keluar'],0,5) ?></div>
                    <?php if ($is_sesi_sendiri): ?>
                        <!-- Shift sesi aktif sendiri -->
                        <span class="badge-saya">&#9679; SESI SAYA</span>
                    <?php elseif ($occ && !$occ['is_self']): ?>
                        <!-- Shift diisi user lain -->
                        <span class="badge-aktif">&#9679; ADA PETUGAS</span>
                        <span class="badge-petugas" title="<?= htmlspecialchars($nama_petugas) ?>"><?= htmlspecialchars($nama_petugas) ?></span>
                    <?php else: ?>
                        <!-- Shift kosong, bisa dipilih -->
                        <span class="badge-kosong" style="font-size:9px;font-weight:600;padding:2px 7px;border-radius:20px;background:rgba(74,222,128,0.08);color:var(--text-muted);border:1px solid rgba(255,255,255,0.07);">KOSONG</span>
                    <?php endif; ?>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="info-telat" id="info-telat">
            <i class="fas fa-exclamation-triangle"></i>
            <div id="info-telat-text"></div>
        </div>
    </div>

    <!-- LANGKAH 2 — STATUS MASUK -->
    <div class="card">
        <div class="card-title"><i class="fas fa-clipboard-check"></i> Langkah 2 &mdash; Status Masuk</div>

        <div class="status-grid">
            <div>
                <input type="radio" name="status_masuk" id="status_sesuai"
                       value="sesuai_jadwal" class="status-option"
                       <?= (!$ada_sesi_aktif || $status_masuk_server === 'sesuai_jadwal') ? 'checked' : '' ?>
                       <?= $ada_sesi_aktif ? 'disabled' : '' ?>
                       onchange="onStatusChange()">
                <label for="status_sesuai"
                       class="status-label <?= ($ada_sesi_aktif && $status_masuk_server !== 'sesuai_jadwal') ? 'locked' : '' ?>"
                       id="lbl-status-sesuai">
                    <div class="status-icon s-green"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="status-text-lbl">Sesuai Jadwal</div>
                        <div class="status-text-sub">Shift normal sesuai jadwal saya</div>
                    </div>
                </label>
            </div>
            <div>
                <input type="radio" name="status_masuk" id="status_tidak"
                       value="tidak_sesuai" class="status-option"
                       <?= ($ada_sesi_aktif && $status_masuk_server === 'tidak_sesuai') ? 'checked' : '' ?>
                       <?= $ada_sesi_aktif ? 'disabled' : '' ?>
                       onchange="onStatusChange()">
                <label for="status_tidak"
                       class="status-label <?= ($ada_sesi_aktif && $status_masuk_server !== 'tidak_sesuai') ? 'locked' : '' ?>"
                       id="lbl-status-tidak">
                    <div class="status-icon s-amber"><i class="fas fa-random"></i></div>
                    <div>
                        <div class="status-text-lbl">Tidak Sesuai</div>
                        <div class="status-text-sub">Penukaran / penggantian shift</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="panel-tidak-sesuai" id="panel-tidak-sesuai">
            <div class="panel-title"><i class="fas fa-exclamation-triangle"></i> Data Penukaran Shift &mdash; Wajib Diisi</div>
            <div class="form-row">
                <label class="form-label">Tipe Penukaran</label>
                <div class="tipe-grid">
                    <div>
                        <input type="radio" name="tipe_penukaran" id="tipe_penukar" value="penukar" class="tipe-option" <?= $ada_sesi_aktif ? 'disabled' : '' ?>>
                        <label for="tipe_penukar" class="tipe-label <?= $ada_sesi_aktif ? 'locked' : '' ?>">
                            <div class="tipe-dot"></div>
                            <div><div class="tipe-text">Penukar</div><div class="tipe-sub">Saya menggantikan orang lain</div></div>
                        </label>
                    </div>
                    <div>
                        <input type="radio" name="tipe_penukaran" id="tipe_pengganti" value="pengganti" class="tipe-option" <?= $ada_sesi_aktif ? 'disabled' : '' ?>>
                        <label for="tipe_pengganti" class="tipe-label <?= $ada_sesi_aktif ? 'locked' : '' ?>">
                            <div class="tipe-dot"></div>
                            <div><div class="tipe-text">Pengganti</div><div class="tipe-sub">Saya digantikan orang lain</div></div>
                        </label>
                    </div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="form-row">
                <label class="form-label" for="user_pengganti_id"><i class="fas fa-user" style="margin-right:4px;"></i> Nama Pamdal Pengganti / Penukar</label>
                <select name="user_pengganti_id" id="user_pengganti_id" class="form-control" <?= $ada_sesi_aktif ? 'disabled' : '' ?>>
                    <option value="">— Pilih nama pamdal —</option>
                    <?php foreach ($semua_pamdal as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label class="form-label" for="tanggal_penukaran"><i class="fas fa-calendar" style="margin-right:4px;"></i> Tanggal Penukaran</label>
                    <input type="date" name="tanggal_penukaran" id="tanggal_penukaran" class="form-control"
                           value="<?= date('Y-m-d') ?>"
                           min="<?= date('Y-m-d', strtotime('-30 days')) ?>"
                           max="<?= date('Y-m-d', strtotime('+30 days')) ?>"
                           <?= $ada_sesi_aktif ? 'disabled' : '' ?>>
                </div>
                <div class="form-row">
                    <label class="form-label" for="shift_penukaran_id"><i class="fas fa-layer-group" style="margin-right:4px;"></i> Shift Penukaran</label>
                    <select name="shift_penukaran_id" id="shift_penukaran_id" class="form-control" <?= $ada_sesi_aktif ? 'disabled' : '' ?>>
                        <option value="">— Pilih shift —</option>
                        <?php foreach ($semua_shift as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama_shift']) ?> (<?= substr($s['jam_masuk'],0,5) ?>–<?= substr($s['jam_keluar'],0,5) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- ─── PANEL LAPORAN ─── -->
        <div class="panel-laporan <?= $ada_sesi_aktif ? 'show' : '' ?>" id="panel-laporan">
            <div class="laporan-header">
                <div class="laporan-header-icon"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="laporan-header-title">
                        Langkah 3 &mdash; Laporan Shift
                        <span style="font-size:11px; font-weight:400; color:var(--text-muted); margin-left:6px;">(opsional)</span>
                    </div>
                    <div class="laporan-header-sub">
                        Catat kondisi shift, kejadian penting, atau hal yang perlu dilaporkan ke Kepala Kantor.
                        <?php if ($is_double && $ada_sesi_aktif): ?>
                        <span style="color:var(--purple); font-weight:600;">
                            &mdash; Laporan untuk shift <?= htmlspecialchars($absensi_aktif['nama_shift']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="laporan-body">
                <?php if ($sudah_kirim_laporan): ?>
                    <div id="laporan-terkirim-wrap" class="laporan-terkirim-wrap">
                        <div class="laporan-terkirim-header" id="laporan-terkirim-header">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <div class="laporan-terkirim-title">Laporan Berhasil Dikirim</div>
                                <div class="laporan-terkirim-sub">Status: <?= ucfirst($status_laporan_db) ?> &mdash; tap Edit untuk mengubah isi laporan</div>
                            </div>
                            <div class="laporan-terkirim-actions">
                                <button type="button" class="btn-edit-laporan" id="btn-mulai-edit" onclick="mulaiEditLaporan()">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                            </div>
                        </div>
                        <div class="laporan-isi-preview" id="laporan-preview-teks">
                            <?= nl2br(htmlspecialchars($isi_laporan_db)) ?>
                        </div>
                    </div>
                    <div class="laporan-edit-area" id="laporan-edit-area" style="display:none;">
                        <textarea id="laporan-isi-edit" class="laporan-textarea"
                            maxlength="1000"
                            oninput="document.getElementById('lap-char-edit').textContent = this.value.length"
                            placeholder="Tulis laporan shift Anda..."><?= htmlspecialchars($isi_laporan_db) ?></textarea>
                        <div class="laporan-char"><span id="lap-char-edit"><?= strlen($isi_laporan_db) ?></span> / 1000 karakter</div>
                        <div class="laporan-edit-actions">
                            <button type="button" class="btn-laporan-batal" onclick="batalEditLaporan()">
                                <i class="fas fa-times"></i> Batal
                            </button>
                            <button type="button" class="btn-laporan-kirim" id="btn-simpan-edit" onclick="simpanEditLaporan()">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div id="laporan-form-area">
                        <textarea id="laporan-isi" class="laporan-textarea"
                            placeholder="Contoh: Kondisi pos aman, tidak ada kejadian khusus. Koordinasi dengan satpam gedung berjalan lancar."
                            maxlength="1000"
                            oninput="document.getElementById('lap-char').textContent = this.value.length"
                        ></textarea>
                        <div class="laporan-char"><span id="lap-char">0</span> / 1000 karakter</div>
                        <button type="button" class="btn-laporan-kirim full" id="btn-kirim-laporan" onclick="kirimLaporan()">
                            <i class="fas fa-paper-plane"></i> Kirim Laporan
                        </button>
                    </div>
                    <div id="laporan-terkirim-ajax" style="display:none;">
                        <div class="laporan-terkirim-wrap">
                            <div class="laporan-terkirim-header">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <div class="laporan-terkirim-title">Laporan Berhasil Dikirim</div>
                                    <div class="laporan-terkirim-sub">Menunggu persetujuan Kepala Kantor</div>
                                </div>
                                <div class="laporan-terkirim-actions">
                                    <button type="button" class="btn-edit-laporan" onclick="mulaiEditLaporanBaru()">
                                        <i class="fas fa-pen"></i> Edit
                                    </button>
                                </div>
                            </div>
                            <div class="laporan-isi-preview" id="laporan-preview-baru"></div>
                        </div>
                        <div class="laporan-edit-area" id="laporan-edit-area-baru" style="display:none;">
                            <textarea id="laporan-isi-edit-baru" class="laporan-textarea"
                                maxlength="1000"
                                oninput="document.getElementById('lap-char-edit-baru').textContent = this.value.length"
                                placeholder="Tulis laporan shift Anda..."></textarea>
                            <div class="laporan-char"><span id="lap-char-edit-baru">0</span> / 1000 karakter</div>
                            <div class="laporan-edit-actions">
                                <button type="button" class="btn-laporan-batal" onclick="batalEditLaporanBaru()">
                                    <i class="fas fa-times"></i> Batal
                                </button>
                                <button type="button" class="btn-laporan-kirim" id="btn-simpan-edit-baru" onclick="simpanEditLaporanBaru()">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TOMBOL SUBMIT -->
        <div style="margin-top: 22px;">
            <?php if ($ada_sesi_aktif): ?>
            <button type="button" class="btn-submit done" id="btn-submit">
                <i class="fas fa-check" id="btn-icon"></i>
                <span id="btn-text">Sedang Dalam Sesi Shift</span>
            </button>
            <?php else: ?>
            <button type="button" class="btn-submit" id="btn-submit" onclick="submitAbsen()">
                <i class="fas fa-sign-in-alt" id="btn-icon"></i>
                <span id="btn-text">Konfirmasi Absen Masuk</span>
            </button>
            <?php endif; ?>

            <p style="text-align:center; font-size:12px; color:var(--text-muted); margin-top:12px;">
                <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                Absen masuk tetap bisa dilakukan meski terlambat, dengan keterangan
                <strong style="color:var(--amber);">terlambat</strong>.
            </p>
        </div>
    </div>

</div>

<script>
// ── State dari server ──
const SERVER_STATE = {
    adaSesiAktif      : <?= $ada_sesi_aktif       ? 'true' : 'false' ?>,
    absensiId         : <?= $absensi_id_server ?>,
    shiftId           : <?= $shift_id_server ?>,
    statusMasuk       : "<?= htmlspecialchars($status_masuk_server) ?>",
    jamMasuk          : "<?= htmlspecialchars($jam_masuk_server) ?>",
    sudahKirimLaporan : <?= $sudah_kirim_laporan   ? 'true' : 'false' ?>,
    tanggal           : "<?= $tanggal ?>",
    userId            : <?= (int)$user_id ?>,
    laporanId         : <?= $laporan_detail ? (int)$laporan_detail['id'] : 0 ?>
};

// Occupancy dari server (shift_id → {occupied, is_self, nama_user})
let shiftOccupancy = <?= json_encode($shift_occupancy) ?>;

let absensiIdBaru = SERVER_STATE.absensiId || null;
let laporanIdBaru = SERVER_STATE.laporanId || null;

/* ── Live Clock ── */
(function tick() {
    const el = document.getElementById('live-clock');
    if (el) {
        const n = new Date(), p = v => String(v).padStart(2,'0');
        el.textContent = p(n.getHours()) + ':' + p(n.getMinutes()) + ':' + p(n.getSeconds());
    }
    setTimeout(tick, 1000);
})();

/* ── Toast ── */
let _toastTimer = null;
function showToast(type, title, msg, ms = 6000) {
    const t   = document.getElementById('toast');
    const ico = { success:'fa-check-circle', error:'fa-times-circle', warning:'fa-exclamation-triangle' };
    t.className = 'toast toast-' + type;
    document.getElementById('toast-icon').innerHTML  = `<i class="fas ${ico[type]||'fa-info-circle'}"></i>`;
    document.getElementById('toast-title').textContent = title;
    document.getElementById('toast-msg').textContent   = msg;
    requestAnimationFrame(() => { t.classList.remove('hide'); t.classList.add('show'); });
    clearTimeout(_toastTimer);
    if (ms > 0) _toastTimer = setTimeout(hideToast, ms);
}
function hideToast() {
    const t = document.getElementById('toast');
    t.classList.remove('show');
    t.classList.add('hide');
}

function fmtMenit(m) {
    if (m <= 0) return '';
    const j = Math.floor(m / 60), s = m % 60;
    if (j > 0 && s > 0) return j + ' jam ' + s + ' menit';
    if (j > 0)           return j + ' jam';
    return s + ' menit';
}

/* ── Cek keterlambatan dari jam shift ── */
function cekTelat() {
    if (SERVER_STATE.adaSesiAktif) return;
    const opt = document.querySelector('.shift-option:checked');
    const box = document.getElementById('info-telat');
    const txt = document.getElementById('info-telat-text');
    if (!box) return;
    if (!opt) { box.classList.remove('show'); return; }

    // Jika shift ini occupied oleh user lain, jangan tampilkan info telat
    if (opt.dataset.occupied === '1') { box.classList.remove('show'); return; }

    const [jh, jm] = (opt.dataset.jamMasuk || '').split(':').map(Number);
    if (isNaN(jh))  { box.classList.remove('show'); return; }

    const now    = new Date();
    const jadwal = new Date(now.getFullYear(), now.getMonth(), now.getDate(), jh, jm, 0);
    const menit  = Math.floor((now - jadwal) / 60000);

    if (menit <= 0) {
        box.classList.remove('show');
    } else {
        box.classList.add('show');
        Object.assign(box.style, { background:'var(--amber-dim)', borderColor:'rgba(245,158,11,0.3)', color:'var(--amber)' });
        txt.innerHTML = `Anda <strong>terlambat ${fmtMenit(menit)}</strong>. Absen tetap bisa dilakukan dengan keterangan <strong>terlambat</strong>.`;
    }
}

function onShiftChange(radio) {
    cekTelat();
}

function onStatusChange() {
    if (SERVER_STATE.adaSesiAktif) return;
    togglePanelPenukaran();
}

function togglePanelPenukaran() {
    const val   = document.querySelector('input[name="status_masuk"]:checked')?.value;
    const panel = document.getElementById('panel-tidak-sesuai');
    if (!panel) return;
    const aktif = val === 'tidak_sesuai';
    panel.classList.toggle('show', aktif);
    ['user_pengganti_id','tanggal_penukaran','shift_penukaran_id'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.required = aktif && !SERVER_STATE.adaSesiAktif;
    });
}

/* ── Refresh occupancy dari server dan update UI shift ── */
async function refreshOccupancy() {
    try {
        const fd = new FormData();
        fd.append('aksi', 'get_occupancy');
        const res  = await fetch(location.href, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();
        if (data.success) {
            shiftOccupancy = data.occupancy;
            applyOccupancyToUI();
        }
    } catch(e) { /* silent */ }
}

function applyOccupancyToUI() {
    document.querySelectorAll('.shift-option').forEach(opt => {
        const sid  = opt.value;
        const lbl  = document.getElementById('lbl-shift-' + sid);
        const occ  = shiftOccupancy[sid];
        const isSelf = occ && occ.is_self;
        const isOther = occ && !occ.is_self;

        if (isOther) {
            // Shift diisi user lain — lock
            opt.disabled = true;
            if (lbl) {
                lbl.classList.add('locked');
                lbl.classList.remove('sesi-aktif');
            }
        } else if (isSelf) {
            // Shift sesi sendiri
            opt.disabled = false;
            if (lbl) {
                lbl.classList.remove('locked');
                lbl.classList.add('sesi-aktif');
            }
        } else {
            // Kosong — bisa dipilih
            if (!SERVER_STATE.adaSesiAktif) {
                opt.disabled = false;
            }
            if (lbl) {
                lbl.classList.remove('locked', 'sesi-aktif');
            }
        }
    });
}

window.addEventListener('load', () => {
    togglePanelPenukaran();
    if (!SERVER_STATE.adaSesiAktif) {
        cekTelat();
        setInterval(cekTelat, 30000);
    }
    if (SERVER_STATE.adaSesiAktif && SERVER_STATE.statusMasuk === 'tidak_sesuai') {
        const panel = document.getElementById('panel-tidak-sesuai');
        if (panel) panel.classList.add('show');
    }
});

/* ── Submit Absen (AJAX) ── */
async function submitAbsen() {
    if (document.getElementById('btn-submit').classList.contains('done')) return;

    const shiftOpt  = document.querySelector('.shift-option:checked');
    const statusOpt = document.querySelector('input[name="status_masuk"]:checked');

    if (!shiftOpt) {
        showToast('error', 'Pilih Shift Dulu', 'Harap pilih shift yang kosong sebelum konfirmasi.');
        return;
    }
    if (shiftOpt.dataset.occupied === '1') {
        showToast('error', 'Shift Sudah Ada Petugas', 'Shift ini sudah dijaga oleh petugas lain. Pilih shift yang kosong.');
        return;
    }

    const shiftNama  = shiftOpt.dataset.nama || 'shift ini';
    const statusTeks = statusOpt?.value === 'tidak_sesuai' ? 'Tidak Sesuai Jadwal' : 'Sesuai Jadwal';
    const clock      = document.getElementById('live-clock')?.textContent || '';
    const [jh, jm]  = (shiftOpt.dataset.jamMasuk || '').split(':').map(Number);
    const now        = new Date();
    const jadwal     = new Date(now.getFullYear(), now.getMonth(), now.getDate(), jh, jm, 0);
    const menit      = Math.floor((now - jadwal) / 60000);
    const telatInfo  = menit > 0 ? '\nKeterangan : Terlambat ' + fmtMenit(menit) : '';

    const ok = confirm(
        'Konfirmasi Absen Masuk\n\n' +
        'Shift      : ' + shiftNama + '\n' +
        'Status     : ' + statusTeks + '\n' +
        'Waktu      : ' + clock +
        telatInfo + '\n\nPastikan data sudah benar sebelum melanjutkan.'
    );
    if (!ok) return;

    if (statusOpt?.value === 'tidak_sesuai') {
        const tipe   = document.querySelector('input[name="tipe_penukaran"]:checked')?.value;
        const userId = document.getElementById('user_pengganti_id')?.value;
        const tgl    = document.getElementById('tanggal_penukaran')?.value;
        const shiftP = document.getElementById('shift_penukaran_id')?.value;
        if (!tipe || !userId || !tgl || !shiftP) {
            showToast('error', 'Data Tidak Lengkap', 'Lengkapi semua data penukaran shift terlebih dahulu.');
            return;
        }
    }

    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    document.getElementById('btn-icon').className = 'fas fa-spinner fa-spin';
    document.getElementById('btn-text').textContent = 'Memproses...';

    const fd = new FormData();
    fd.append('aksi',         'absen_masuk');
    fd.append('shift_id',     shiftOpt.value);
    fd.append('status_masuk', statusOpt?.value || 'sesuai_jadwal');

    if (statusOpt?.value === 'tidak_sesuai') {
        fd.append('tipe_penukaran',     document.querySelector('input[name="tipe_penukaran"]:checked').value);
        fd.append('user_pengganti_id',  document.getElementById('user_pengganti_id').value);
        fd.append('tanggal_penukaran',  document.getElementById('tanggal_penukaran').value);
        fd.append('shift_penukaran_id', document.getElementById('shift_penukaran_id').value);
    }

    try {
        const res  = await fetch(location.href, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();

        if (data.success) {
            absensiIdBaru = data.absensi_id;

            const isLate = data.message.toLowerCase().includes('terlambat');
            if (isLate) {
                showToast('warning', 'Absen Masuk — Terlambat', data.message, 0);
            } else {
                showToast('success', 'Absen Masuk Berhasil', 'Absen masuk tercatat pada ' + data.jam_sekarang + '.', 0);
            }

            // Tandai tombol done
            btn.classList.add('done');
            btn.disabled = false;
            document.getElementById('btn-icon').className = 'fas fa-check';
            document.getElementById('btn-text').textContent = 'Sedang Dalam Sesi Shift';

            // Lock status masuk
            document.querySelectorAll('input[name="status_masuk"]').forEach(r => r.disabled = true);
            const terpilih = statusOpt?.value || 'sesuai_jadwal';
            if (terpilih === 'sesuai_jadwal') {
                document.getElementById('lbl-status-tidak')?.classList.add('locked');
            } else {
                document.getElementById('lbl-status-sesuai')?.classList.add('locked');
            }

            // Tandai shift card sebagai sesi aktif milik sendiri
            const selectedLbl = document.getElementById('lbl-shift-' + shiftOpt.value);
            if (selectedLbl) {
                selectedLbl.classList.add('sesi-aktif');
                selectedLbl.classList.remove('locked');
            }

            // Tampilkan panel laporan
            const panelLap = document.getElementById('panel-laporan');
            panelLap.classList.add('show');
            setTimeout(() => panelLap.scrollIntoView({ behavior:'smooth', block:'nearest' }), 100);

            // Banner sudah absen
            if (!document.getElementById('banner-sudah')) {
                const banner = document.createElement('div');
                banner.id        = 'banner-sudah';
                banner.className = 'banner-status sudah-absen';
                banner.innerHTML = `
                    <div class="banner-status-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="banner-status-title">Anda sedang dalam sesi shift</div>
                        <div class="banner-status-sub">Masuk pukul ${data.jam_sekarang} &mdash; shift ${data.shift_nama} &mdash; lakukan absen keluar bila sudah selesai</div>
                    </div>`;
                const infoBox = document.querySelector('.info-box');
                if (infoBox) infoBox.insertAdjacentElement('afterend', banner);
            }

            // Update occupancy di UI (shift ini sekarang occupied oleh sendiri)
            shiftOccupancy[shiftOpt.value] = { occupied: true, is_self: true, nama_user: '' };

        } else {
            showToast('error', 'Absen Gagal', data.message, 7000);
            btn.disabled = false;
            document.getElementById('btn-icon').className = 'fas fa-sign-in-alt';
            document.getElementById('btn-text').textContent = 'Konfirmasi Absen Masuk';
            // Refresh occupancy untuk pastikan data shift terkini
            await refreshOccupancy();
        }
    } catch (err) {
        showToast('error', 'Error Jaringan', 'Terjadi kesalahan, silakan coba lagi.', 7000);
        btn.disabled = false;
        document.getElementById('btn-icon').className = 'fas fa-sign-in-alt';
        document.getElementById('btn-text').textContent = 'Konfirmasi Absen Masuk';
    }
}

/* ── Kirim Laporan Baru (AJAX) ── */
async function kirimLaporan() {
    const isi = document.getElementById('laporan-isi')?.value?.trim();
    if (!isi || isi.length < 5) {
        showToast('error', 'Laporan Terlalu Pendek', 'Isi laporan minimal 5 karakter.', 4000);
        return;
    }

    const idAbsensi = SERVER_STATE.absensiId || absensiIdBaru;
    if (!idAbsensi) {
        showToast('error', 'Error', 'ID absensi tidak ditemukan. Silakan refresh halaman.', 4000);
        return;
    }

    const btn = document.getElementById('btn-kirim-laporan');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';

    const fd = new FormData();
    fd.append('aksi',        'kirim_laporan');
    fd.append('absensi_id',  idAbsensi);
    fd.append('isi_laporan', isi);

    try {
        const res  = await fetch(location.href, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();

        if (data.success) {
            const isiTerkirim = isi;
            document.getElementById('laporan-form-area').style.display     = 'none';
            document.getElementById('laporan-terkirim-ajax').style.display = 'block';
            document.getElementById('laporan-preview-baru').textContent    = isiTerkirim;
            document.getElementById('laporan-isi-edit-baru').value         = isiTerkirim;
            document.getElementById('lap-char-edit-baru').textContent      = isiTerkirim.length;
            laporanIdBaru = data.laporan_id || null;
            showToast('success', 'Laporan Terkirim', 'Laporan shift berhasil dikirim ke Kepala Kantor.', 5000);
        } else {
            showToast('error', 'Gagal Kirim', data.message, 5000);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Laporan';
        }
    } catch (err) {
        showToast('error', 'Error Jaringan', 'Terjadi kesalahan, coba lagi.', 5000);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Kirim Laporan';
    }
}

/* ── Edit Laporan (laporan sudah ada di DB saat page load) ── */
function mulaiEditLaporan() {
    document.getElementById('laporan-terkirim-wrap').style.display = 'none';
    document.getElementById('laporan-edit-area').style.display     = 'block';
    const ta = document.getElementById('laporan-isi-edit');
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);
}
function batalEditLaporan() {
    document.getElementById('laporan-edit-area').style.display     = 'none';
    document.getElementById('laporan-terkirim-wrap').style.display = 'block';
}
async function simpanEditLaporan() {
    const isi = document.getElementById('laporan-isi-edit')?.value?.trim();
    if (!isi || isi.length < 5) { showToast('error', 'Terlalu Pendek', 'Isi laporan minimal 5 karakter.', 4000); return; }
    const laporanId = SERVER_STATE.laporanId;
    const absensiId = SERVER_STATE.absensiId;
    if (!laporanId || !absensiId) { showToast('error', 'Error', 'Data laporan tidak ditemukan.', 4000); return; }
    const btn = document.getElementById('btn-simpan-edit');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    const fd = new FormData();
    fd.append('aksi', 'edit_laporan'); fd.append('absensi_id', absensiId);
    fd.append('laporan_id', laporanId); fd.append('isi_laporan', isi);
    try {
        const res  = await fetch(location.href, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('laporan-preview-teks').innerHTML = isi.replace(/\n/g, '<br>');
            const header = document.getElementById('laporan-terkirim-header');
            header.classList.add('updated');
            header.querySelector('.laporan-terkirim-title').textContent = 'Laporan Diperbarui';
            header.querySelector('.laporan-terkirim-sub').textContent   = 'Laporan berhasil diubah — menunggu persetujuan ulang';
            batalEditLaporan();
            showToast('success', 'Laporan Diperbarui', 'Isi laporan berhasil diubah dan dikirim ulang.', 5000);
        } else {
            showToast('error', 'Gagal Simpan', data.message, 5000);
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
        }
    } catch (err) {
        showToast('error', 'Error Jaringan', 'Terjadi kesalahan, coba lagi.', 5000);
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
    }
}

/* ── Edit Laporan Baru (baru dikirim via AJAX) ── */
function mulaiEditLaporanBaru() {
    document.getElementById('laporan-terkirim-ajax').querySelector('.laporan-terkirim-wrap').style.display = 'none';
    document.getElementById('laporan-edit-area-baru').style.display = 'block';
    const ta = document.getElementById('laporan-isi-edit-baru');
    ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length);
}
function batalEditLaporanBaru() {
    document.getElementById('laporan-edit-area-baru').style.display = 'none';
    document.getElementById('laporan-terkirim-ajax').querySelector('.laporan-terkirim-wrap').style.display = 'block';
}
async function simpanEditLaporanBaru() {
    const isi = document.getElementById('laporan-isi-edit-baru')?.value?.trim();
    if (!isi || isi.length < 5) { showToast('error', 'Terlalu Pendek', 'Isi laporan minimal 5 karakter.', 4000); return; }
    const absensiId = SERVER_STATE.absensiId || absensiIdBaru;
    if (!absensiId) { showToast('error', 'Error', 'ID absensi tidak ditemukan.', 4000); return; }
    const btn = document.getElementById('btn-simpan-edit-baru');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
    const fd = new FormData();
    fd.append('aksi', 'edit_laporan'); fd.append('absensi_id', absensiId);
    fd.append('laporan_id', laporanIdBaru || 0); fd.append('isi_laporan', isi);
    try {
        const res  = await fetch(location.href, { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd });
        const data = await res.json();
        if (data.success) {
            document.getElementById('laporan-preview-baru').textContent = isi;
            batalEditLaporanBaru();
            showToast('success', 'Laporan Diperbarui', 'Isi laporan berhasil diubah.', 5000);
        } else {
            if (!laporanIdBaru) {
                showToast('warning', 'Perlu Refresh', 'Refresh halaman untuk mengedit laporan.', 0);
            } else {
                showToast('error', 'Gagal Simpan', data.message, 5000);
            }
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
        }
    } catch (err) {
        showToast('error', 'Error Jaringan', 'Terjadi kesalahan, coba lagi.', 5000);
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Simpan Perubahan';
    }
}
</script>
</body>
</html>