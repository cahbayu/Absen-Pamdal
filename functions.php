<?php
// functions.php - Fungsi-fungsi utama sistem absensi Pamdal
// Terhubung dengan config.php

require_once 'config.php';

// ============================================================
// BAGIAN 1: FUNGSI MANAJEMEN SHIFT
// ============================================================

function getAllShifts() {
    global $conn;
    $sql = "SELECT * FROM shift ORDER BY jam_masuk ASC";
    $result = $conn->query($sql);
    $shifts = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $shifts[] = $row;
        }
    }
    return $shifts;
}

function getShiftById($shift_id) {
    global $conn;
    $shift_id = (int)$shift_id;
    $sql = "SELECT * FROM shift WHERE id = $shift_id LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Cek shift yang sedang aktif berdasarkan waktu sekarang.
 * Mendukung shift yang melewati tengah malam (jam_masuk > jam_keluar),
 * seperti Shift Malam 20:00–07:30.
 */
function getActiveShift() {
    global $conn;
    $now = date('H:i:s');

    // Ambil semua shift lalu cek satu per satu di PHP
    // agar logika cross-midnight lebih akurat
    $sql    = "SELECT * FROM shift";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) return null;

    while ($shift = $result->fetch_assoc()) {
        $masuk  = $shift['jam_masuk'];
        $keluar = $shift['jam_keluar'];

        if ($masuk < $keluar) {
            // Shift normal (tidak melewati tengah malam)
            // Contoh: Pagi 07:30–11:00, Siang 11:00–20:00
            if ($now >= $masuk && $now < $keluar) {
                return $shift;
            }
        } else {
            // Shift melewati tengah malam
            // Contoh: Malam 20:00–07:30
            // Aktif jika sekarang >= 20:00 ATAU sekarang < 07:30
            if ($now >= $masuk || $now < $keluar) {
                return $shift;
            }
        }
    }

    return null;
}

/**
 * Cek keterlambatan.
 * Berapapun telatnya, Pamdal TETAP BISA absen masuk.
 * Keterangan akan otomatis menjadi 'terlambat'.
 *
 * @param  string $jam_masuk_shift  Format 'HH:MM:SS'
 * @return array  ['terlambat' => bool, 'menit' => int, 'jam' => int, 'sisa_menit' => int, 'bisa_absen' => bool, 'label' => string]
 */
function cekKeterlambatan($jam_masuk_shift) {
    $sekarang      = strtotime(date('H:i:s'));
    $jadwal_masuk  = strtotime($jam_masuk_shift);
    $selisih_menit = (int)(($sekarang - $jadwal_masuk) / 60);

    if ($selisih_menit <= 0) {
        return [
            'terlambat'   => false,
            'menit'       => 0,
            'jam'         => 0,
            'sisa_menit'  => 0,
            'bisa_absen'  => true,
            'label'       => '',
        ];
    }

    $jam        = (int)floor($selisih_menit / 60);
    $sisa_menit = $selisih_menit % 60;

    if ($jam > 0) {
        $label = $jam . ' jam ' . $sisa_menit . ' menit';
    } else {
        $label = $selisih_menit . ' menit';
    }

    return [
        'terlambat'  => true,
        'menit'      => $selisih_menit,
        'jam'        => $jam,
        'sisa_menit' => $sisa_menit,
        'bisa_absen' => true,
        'label'      => $label,
    ];
}

function isWaktuPulang($jam_keluar_shift) {
    $sekarang      = strtotime(date('H:i:s'));
    $jadwal_keluar = strtotime($jam_keluar_shift);
    $selisih_menit = abs($sekarang - $jadwal_keluar) / 60;
    return $selisih_menit <= 10;
}

function isPulangAwal($jam_keluar_shift) {
    $sekarang      = strtotime(date('H:i:s'));
    $jadwal_keluar = strtotime($jam_keluar_shift);
    return $sekarang < $jadwal_keluar;
}

function getOpsiStatusKeluar($shift_jam_keluar) {
    $opsi = [];
    if (isPulangAwal($shift_jam_keluar)) {
        $opsi[] = ['value' => 'pulang_awal',  'label' => 'Pulang Lebih Awal',        'alasan_required' => true];
    }
    if (isWaktuPulang($shift_jam_keluar)) {
        $opsi[] = ['value' => 'tepat_waktu',  'label' => 'Tepat Waktu',              'alasan_required' => false];
    }
    $opsi[]     = ['value' => 'lanjut_shift', 'label' => 'Lanjut Shift Berikutnya', 'alasan_required' => false];
    return $opsi;
}


// ============================================================
// BAGIAN 2: FUNGSI ABSEN MASUK
// ============================================================

/**
 * Proses absen masuk untuk Pamdal.
 * Berapapun telatnya, absen TETAP BISA dilakukan.
 * Jika terlambat, keterangan_masuk otomatis = 'terlambat'.
 */
function absenMasuk($user_id, $shift_id, $status_masuk, $data_penukaran = []) {
    global $conn;

    $user_id      = (int)$user_id;
    $shift_id     = (int)$shift_id;
    $status_masuk = cleanInput($status_masuk);

    if (!in_array($status_masuk, ['sesuai_jadwal', 'tidak_sesuai'])) {
        return ['success' => false, 'message' => 'Status masuk tidak valid.', 'absensi_id' => null];
    }

    if ($status_masuk === 'tidak_sesuai') {
        if (
            empty($data_penukaran['user_pengganti_id']) ||
            empty($data_penukaran['tanggal'])           ||
            empty($data_penukaran['shift_id'])          ||
            empty($data_penukaran['tipe'])
        ) {
            return [
                'success'    => false,
                'message'    => 'Data penukar/pengganti (nama, tanggal, shift) wajib diisi jika tidak sesuai jadwal.',
                'absensi_id' => null,
            ];
        }
    }

    $tanggal = date('Y-m-d');
    if (sudahAbsenMasuk($user_id, $shift_id, $tanggal)) {
        return ['success' => false, 'message' => 'Anda sudah absen masuk pada shift ini hari ini.', 'absensi_id' => null];
    }

    $shift = getShiftById($shift_id);
    if (!$shift) {
        return ['success' => false, 'message' => 'Data shift tidak ditemukan.', 'absensi_id' => null];
    }

    $cek_telat        = cekKeterlambatan($shift['jam_masuk']);
    $keterangan_masuk = $cek_telat['terlambat'] ? 'terlambat' : 'normal';

    $jam_masuk_now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "INSERT INTO absensi (user_id, shift_id, tanggal, jam_masuk, status_masuk, keterangan_masuk)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iissss', $user_id, $shift_id, $tanggal, $jam_masuk_now, $status_masuk, $keterangan_masuk);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal menyimpan absen masuk: ' . $stmt->error, 'absensi_id' => null];
    }

    $absensi_id = $conn->insert_id;
    $stmt->close();

    if ($status_masuk === 'tidak_sesuai') {
        $result_penukaran = simpanPenukaranShift($absensi_id, $data_penukaran);
        if (!$result_penukaran['success']) {
            $conn->query("DELETE FROM absensi WHERE id = $absensi_id");
            return ['success' => false, 'message' => $result_penukaran['message'], 'absensi_id' => null];
        }
    }

    if ($cek_telat['terlambat']) {
        $pesan = 'Absen masuk berhasil. Catatan: Anda terlambat ' . $cek_telat['label'] . '.';
    } else {
        $pesan = 'Absen masuk berhasil.';
    }

    return ['success' => true, 'message' => $pesan, 'absensi_id' => $absensi_id];
}

function sudahAbsenMasuk($user_id, $shift_id, $tanggal) {
    global $conn;
    $user_id  = (int)$user_id;
    $shift_id = (int)$shift_id;
    $tanggal  = cleanInput($tanggal);
    $sql = "SELECT id FROM absensi
            WHERE user_id = $user_id AND shift_id = $shift_id AND tanggal = '$tanggal'
              AND jam_masuk IS NOT NULL
            LIMIT 1";
    $result = $conn->query($sql);
    return ($result && $result->num_rows > 0);
}

function getAbsensiAktif($user_id, $shift_id, $tanggal) {
    global $conn;
    $user_id  = (int)$user_id;
    $shift_id = (int)$shift_id;
    $tanggal  = cleanInput($tanggal);
    $sql = "SELECT a.*, s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar
            FROM absensi a
            JOIN shift s ON a.shift_id = s.id
            WHERE a.user_id = $user_id AND a.shift_id = $shift_id AND a.tanggal = '$tanggal'
              AND a.jam_masuk IS NOT NULL AND a.jam_keluar IS NULL
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}


// ============================================================
// BAGIAN 3: FUNGSI ABSEN KELUAR
// ============================================================

function absenKeluar($user_id, $absensi_id, $status_keluar, $alasan = '') {
    global $conn;

    $user_id       = (int)$user_id;
    $absensi_id    = (int)$absensi_id;
    $status_keluar = cleanInput($status_keluar);
    $alasan        = cleanInput($alasan);

    if (!in_array($status_keluar, ['tepat_waktu', 'pulang_awal', 'lanjut_shift'])) {
        return ['success' => false, 'message' => 'Status keluar tidak valid.'];
    }

    $sql = "SELECT a.*, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar
            FROM absensi a
            JOIN shift s ON a.shift_id = s.id
            WHERE a.id = $absensi_id AND a.user_id = $user_id AND a.jam_masuk IS NOT NULL
            LIMIT 1";
    $result = $conn->query($sql);

    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Data absen masuk tidak ditemukan. Anda harus absen masuk terlebih dahulu.'];
    }

    $absensi = $result->fetch_assoc();

    if (!empty($absensi['jam_keluar'])) {
        return ['success' => false, 'message' => 'Anda sudah melakukan absen keluar pada shift ini.'];
    }

    if ($status_keluar === 'tepat_waktu' && !isWaktuPulang($absensi['shift_jam_keluar'])) {
        return ['success' => false, 'message' => 'Opsi tepat waktu hanya tersedia saat waktu pulang shift.'];
    }

    if ($status_keluar === 'pulang_awal' && empty($alasan)) {
        return ['success' => false, 'message' => 'Alasan wajib diisi jika pulang lebih awal.'];
    }

    $jam_keluar_now  = date('Y-m-d H:i:s');
    $is_double_shift = ($status_keluar === 'lanjut_shift') ? 1 : 0;
    $alasan_db       = ($status_keluar === 'pulang_awal') ? $alasan : null;

    $stmt = $conn->prepare(
        "UPDATE absensi
         SET jam_keluar = ?, status_keluar = ?, alasan_pulang_awal = ?, is_double_shift = ?
         WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param('sssiis', $jam_keluar_now, $status_keluar, $alasan_db, $is_double_shift, $absensi_id, $user_id);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal menyimpan absen keluar: ' . $stmt->error];
    }
    $stmt->close();

    if ($status_keluar === 'lanjut_shift') {
        $result_lanjut = buatAbsensiLanjutShift($user_id, $absensi['shift_id']);
        if (!$result_lanjut['success']) {
            return [
                'success' => true,
                'message' => 'Absen keluar berhasil, namun gagal menyiapkan absensi shift berikutnya: ' . $result_lanjut['message'],
            ];
        }
        return ['success' => true, 'message' => 'Absen keluar berhasil. Absensi shift berikutnya sudah disiapkan otomatis.'];
    }

    return ['success' => true, 'message' => 'Absen keluar berhasil.'];
}

function buatAbsensiLanjutShift($user_id, $shift_id_sekarang) {
    global $conn;

    $user_id           = (int)$user_id;
    $shift_id_sekarang = (int)$shift_id_sekarang;

    $shift_berikutnya_id = ($shift_id_sekarang % 3) + 1;

    $tanggal = ($shift_id_sekarang == 3)
        ? date('Y-m-d', strtotime('+1 day'))
        : date('Y-m-d');

    if (sudahAbsenMasuk($user_id, $shift_berikutnya_id, $tanggal)) {
        return ['success' => false, 'message' => 'Absensi shift berikutnya sudah ada.'];
    }

    $jam_masuk_now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "INSERT INTO absensi (user_id, shift_id, tanggal, jam_masuk, status_masuk, keterangan_masuk, is_double_shift)
         VALUES (?, ?, ?, ?, 'sesuai_jadwal', 'normal', 1)"
    );
    $stmt->bind_param('iiss', $user_id, $shift_berikutnya_id, $tanggal, $jam_masuk_now);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => $stmt->error];
    }

    $stmt->close();
    return ['success' => true, 'message' => 'Absensi lanjut shift berhasil dibuat.'];
}


// ============================================================
// BAGIAN 4: FUNGSI PENUKARAN SHIFT
// ============================================================

function simpanPenukaranShift($absensi_id, $data) {
    global $conn;

    $absensi_id        = (int)$absensi_id;
    $tipe              = cleanInput($data['tipe']);
    $user_pengganti_id = (int)$data['user_pengganti_id'];
    $tanggal           = cleanInput($data['tanggal']);
    $shift_id          = (int)$data['shift_id'];

    if (!in_array($tipe, ['penukar', 'pengganti'])) {
        return ['success' => false, 'message' => 'Tipe penukaran tidak valid.'];
    }

    if (empty($tanggal) || !strtotime($tanggal)) {
        return ['success' => false, 'message' => 'Tanggal penukaran tidak valid.'];
    }

    if (!getUserById($user_pengganti_id)) {
        return ['success' => false, 'message' => 'User pengganti tidak ditemukan.'];
    }

    if (!getShiftById($shift_id)) {
        return ['success' => false, 'message' => 'Data shift penukaran tidak ditemukan.'];
    }

    $stmt = $conn->prepare(
        "INSERT INTO penukaran_shift (absensi_id, tipe, user_pengganti_id, tanggal, shift_id)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isisi', $absensi_id, $tipe, $user_pengganti_id, $tanggal, $shift_id);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal menyimpan data penukaran shift: ' . $stmt->error];
    }

    $stmt->close();
    return ['success' => true, 'message' => 'Data penukaran shift berhasil disimpan.'];
}

function getPenukaranShiftByAbsensi($absensi_id) {
    global $conn;
    $absensi_id = (int)$absensi_id;
    $sql = "SELECT ps.*, u.name AS nama_pengganti, s.nama_shift
            FROM penukaran_shift ps
            JOIN users u  ON ps.user_pengganti_id = u.id
            JOIN shift s  ON ps.shift_id = s.id
            WHERE ps.absensi_id = $absensi_id";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}


// ============================================================
// BAGIAN 5: FUNGSI LAPORAN
// ============================================================

function buatLaporan($absensi_id, $isi_laporan) {
    global $conn;

    $absensi_id  = (int)$absensi_id;
    $isi_laporan = cleanInput($isi_laporan);

    if (empty($isi_laporan)) {
        return ['success' => false, 'message' => 'Isi laporan tidak boleh kosong.', 'laporan_id' => null];
    }

    $cek_absensi = $conn->query("SELECT id FROM absensi WHERE id = $absensi_id LIMIT 1");
    if (!$cek_absensi || $cek_absensi->num_rows === 0) {
        return ['success' => false, 'message' => 'Data absensi tidak ditemukan.', 'laporan_id' => null];
    }

    $cek_laporan = $conn->query("SELECT id FROM laporan WHERE absensi_id = $absensi_id LIMIT 1");
    if ($cek_laporan && $cek_laporan->num_rows > 0) {
        return ['success' => false, 'message' => 'Laporan untuk absensi ini sudah dibuat.', 'laporan_id' => null];
    }

    $stmt = $conn->prepare(
        "INSERT INTO laporan (absensi_id, isi_laporan, status) VALUES (?, ?, 'pending')"
    );
    $stmt->bind_param('is', $absensi_id, $isi_laporan);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal membuat laporan: ' . $stmt->error, 'laporan_id' => null];
    }

    $laporan_id = $conn->insert_id;
    $stmt->close();
    return ['success' => true, 'message' => 'Laporan berhasil dibuat.', 'laporan_id' => $laporan_id];
}

function getLaporanByAbsensi($absensi_id) {
    global $conn;
    $absensi_id = (int)$absensi_id;
    $sql = "SELECT l.*, a.tanggal, u.name AS nama_user, s.nama_shift
            FROM laporan l
            JOIN absensi a ON l.absensi_id = a.id
            JOIN users u   ON a.user_id = u.id
            JOIN shift s   ON a.shift_id = s.id
            WHERE l.absensi_id = $absensi_id
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function getAllLaporan($status = 'semua') {
    global $conn;
    $where = '';
    if ($status !== 'semua') {
        $status = cleanInput($status);
        $where  = "WHERE l.status = '$status'";
    }
    $sql = "SELECT l.*, a.tanggal, a.jam_masuk, a.jam_keluar,
                   u.name AS nama_user, s.nama_shift
            FROM laporan l
            JOIN absensi a ON l.absensi_id = a.id
            JOIN users u   ON a.user_id = u.id
            JOIN shift s   ON a.shift_id = s.id
            $where
            ORDER BY l.created_at DESC";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function updateLaporan($laporan_id, $user_id, $isi_laporan_baru) {
    global $conn;

    $laporan_id       = (int)$laporan_id;
    $user_id          = (int)$user_id;
    $isi_laporan_baru = cleanInput($isi_laporan_baru);

    if (empty($isi_laporan_baru)) {
        return ['success' => false, 'message' => 'Isi laporan tidak boleh kosong.'];
    }

    $sql = "SELECT l.id FROM laporan l
            JOIN absensi a ON l.absensi_id = a.id
            WHERE l.id = $laporan_id AND a.user_id = $user_id AND l.status = 'revisi'
            LIMIT 1";
    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return ['success' => false, 'message' => 'Laporan tidak ditemukan atau tidak dapat diperbarui.'];
    }

    $stmt = $conn->prepare(
        "UPDATE laporan SET isi_laporan = ?, status = 'pending', catatan_revisi = NULL WHERE id = ?"
    );
    $stmt->bind_param('si', $isi_laporan_baru, $laporan_id);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal memperbarui laporan: ' . $stmt->error];
    }

    $stmt->close();
    return ['success' => true, 'message' => 'Laporan berhasil diperbarui dan dikirim ulang untuk ditinjau.'];
}


// ============================================================
// BAGIAN 6: FUNGSI APPROVAL (KEPALA KANTOR)
// ============================================================

function buatApproval($approved_by, $status, $catatan = '', $absensi_id = null, $laporan_id = null) {
    global $conn;

    $approved_by = (int)$approved_by;
    $status      = cleanInput($status);
    $catatan     = cleanInput($catatan);

    if (!in_array($status, ['diterima', 'ditolak'])) {
        return ['success' => false, 'message' => 'Status approval tidak valid.'];
    }

    if (empty($absensi_id) && empty($laporan_id)) {
        return ['success' => false, 'message' => 'Harus mengisi absensi_id atau laporan_id.'];
    }

    $absensi_id = $absensi_id ? (int)$absensi_id : null;
    $laporan_id = $laporan_id ? (int)$laporan_id : null;

    $stmt = $conn->prepare(
        "INSERT INTO approval (absensi_id, laporan_id, approved_by, status, catatan)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iiiss', $absensi_id, $laporan_id, $approved_by, $status, $catatan);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Gagal menyimpan approval: ' . $stmt->error];
    }

    $stmt->close();

    if ($laporan_id) {
        if ($status === 'diterima') {
            $conn->query("UPDATE laporan SET status = 'acc' WHERE id = $laporan_id");
        } elseif ($status === 'ditolak') {
            $conn->query("UPDATE laporan SET status = 'revisi', catatan_revisi = '$catatan' WHERE id = $laporan_id");
        }
    }

    return ['success' => true, 'message' => 'Approval berhasil disimpan.'];
}

function getApprovalHistory($absensi_id = null, $laporan_id = null) {
    global $conn;
    $where = '1=1';
    if ($absensi_id) {
        $absensi_id = (int)$absensi_id;
        $where      = "ap.absensi_id = $absensi_id";
    } elseif ($laporan_id) {
        $laporan_id = (int)$laporan_id;
        $where      = "ap.laporan_id = $laporan_id";
    }

    $sql = "SELECT ap.*, u.name AS nama_approver
            FROM approval ap
            JOIN users u ON ap.approved_by = u.id
            WHERE $where
            ORDER BY ap.created_at DESC";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}


// ============================================================
// BAGIAN 7: FUNGSI DATA ABSENSI & MONITORING
// ============================================================

function getRiwayatAbsensiUser($user_id, $dari = null, $sampai = null) {
    global $conn;
    $user_id = (int)$user_id;
    $where   = "a.user_id = $user_id";

    if ($dari) {
        $dari  = cleanInput($dari);
        $where .= " AND a.tanggal >= '$dari'";
    }
    if ($sampai) {
        $sampai = cleanInput($sampai);
        $where .= " AND a.tanggal <= '$sampai'";
    }

    $sql = "SELECT a.*, s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar,
                   l.status AS status_laporan, l.isi_laporan, l.catatan_revisi
            FROM absensi a
            JOIN shift s      ON a.shift_id = s.id
            LEFT JOIN laporan l ON l.absensi_id = a.id
            WHERE $where
            ORDER BY a.tanggal DESC, a.jam_masuk DESC";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getAllAbsensi($tanggal = null, $shift_id = null) {
    global $conn;
    $where = '1=1';

    if ($tanggal) {
        $tanggal = cleanInput($tanggal);
        $where  .= " AND a.tanggal = '$tanggal'";
    }
    if ($shift_id) {
        $shift_id = (int)$shift_id;
        $where   .= " AND a.shift_id = $shift_id";
    }

    $sql = "SELECT a.*, u.name AS nama_user, u.username,
                   s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar,
                   l.status AS status_laporan
            FROM absensi a
            JOIN users u       ON a.user_id = u.id
            JOIN shift s       ON a.shift_id = s.id
            LEFT JOIN laporan l ON l.absensi_id = a.id
            WHERE $where
            ORDER BY a.tanggal DESC, a.jam_masuk DESC";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getAbsensiById($absensi_id) {
    global $conn;
    $absensi_id = (int)$absensi_id;
    $sql = "SELECT a.*, u.name AS nama_user, u.username,
                   s.nama_shift, s.jam_masuk AS shift_jam_masuk, s.jam_keluar AS shift_jam_keluar
            FROM absensi a
            JOIN users u ON a.user_id = u.id
            JOIN shift s ON a.shift_id = s.id
            WHERE a.id = $absensi_id
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function getStatistikHarian($tanggal = null) {
    global $conn;
    if (!$tanggal) $tanggal = date('Y-m-d');
    $tanggal = cleanInput($tanggal);

    $queries = [
        'total_hadir'           => "SELECT COUNT(*) AS n FROM absensi WHERE tanggal = '$tanggal' AND jam_masuk IS NOT NULL",
        'total_terlambat'       => "SELECT COUNT(*) AS n FROM absensi WHERE tanggal = '$tanggal' AND keterangan_masuk = 'terlambat'",
        'total_pulang_awal'     => "SELECT COUNT(*) AS n FROM absensi WHERE tanggal = '$tanggal' AND status_keluar = 'pulang_awal'",
        'total_lanjut_shift'    => "SELECT COUNT(*) AS n FROM absensi WHERE tanggal = '$tanggal' AND status_keluar = 'lanjut_shift'",
        'total_tidak_sesuai'    => "SELECT COUNT(*) AS n FROM absensi WHERE tanggal = '$tanggal' AND status_masuk = 'tidak_sesuai'",
        'total_laporan_pending' => "SELECT COUNT(*) AS n FROM laporan WHERE status = 'pending'",
        'total_laporan_revisi'  => "SELECT COUNT(*) AS n FROM laporan WHERE status = 'revisi'",
    ];

    $stats = [];
    foreach ($queries as $key => $sql) {
        $result     = $conn->query($sql);
        $stats[$key] = ($result) ? (int)$result->fetch_assoc()['n'] : 0;
    }

    return $stats;
}


// ============================================================
// BAGIAN 8: FUNGSI MANAJEMEN USER
// ============================================================

function getUserById($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $sql = "SELECT id, name, username, role, status, created_at, last_login
            FROM users WHERE id = $user_id LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

function getAllPamdal() {
    global $conn;
    $sql = "SELECT id, name, username, status, created_at, last_login
            FROM users WHERE role = 'user' AND status = 'active'
            ORDER BY name ASC";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function getAllUsersForDropdown($kecuali_user_id = 0) {
    global $conn;
    $kecuali_user_id = (int)$kecuali_user_id;
    $sql = "SELECT id, name FROM users
            WHERE role = 'user' AND status = 'active' AND id != $kecuali_user_id
            ORDER BY name ASC";
    $result = $conn->query($sql);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

function updateLastLogin($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $now     = date('Y-m-d H:i:s');
    $conn->query("UPDATE users SET last_login = '$now' WHERE id = $user_id");
}

function loginUser($username, $password) {
    global $conn;

    $username = cleanInput($username);

    if (!isValidUsername($username)) {
        return ['success' => false, 'message' => 'Format username tidak valid.'];
    }

    $stmt = $conn->prepare(
        "SELECT id, name, username, password, role, status FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Akun Anda tidak aktif. Hubungi administrator.'];
    }

    if (!verifyPassword($password, $user['password'])) {
        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];

    updateLastLogin($user['id']);

    return ['success' => true, 'message' => 'Login berhasil. Selamat datang, ' . $user['name'] . '!'];
}

function logoutUser() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}


// ============================================================
// BAGIAN 9: FUNGSI HELPER & FORMAT TAMPILAN
// ============================================================

function formatDatetimeID($datetime) {
    if (empty($datetime)) return '-';
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts        = strtotime($datetime);
    $nama_hari = $hari[date('w', $ts)];
    $tgl       = date('j', $ts);
    $bln       = $bulan[(int)date('n', $ts)];
    $tahun     = date('Y', $ts);
    $waktu     = date('H:i', $ts);
    return "$nama_hari, $tgl $bln $tahun $waktu";
}

function formatTanggalID($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts  = strtotime($tanggal);
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function formatDurasiMenit($menit) {
    $menit = (int)$menit;
    if ($menit <= 0) return '0 menit';
    $jam        = (int)floor($menit / 60);
    $sisa_menit = $menit % 60;
    if ($jam > 0 && $sisa_menit > 0) return $jam . ' jam ' . $sisa_menit . ' menit';
    if ($jam > 0)                     return $jam . ' jam';
    return $sisa_menit . ' menit';
}

function badgeStatusMasuk($status, $keterangan = 'normal') {
    if ($status === 'sesuai_jadwal' && $keterangan === 'normal') {
        return '<span class="badge bg-success">Sesuai Jadwal</span>';
    } elseif ($status === 'sesuai_jadwal' && $keterangan === 'terlambat') {
        return '<span class="badge bg-warning text-dark">Terlambat</span>';
    } elseif ($status === 'tidak_sesuai') {
        return '<span class="badge bg-info text-dark">Tidak Sesuai Jadwal</span>';
    }
    return '<span class="badge bg-secondary">-</span>';
}

function badgeStatusKeluar($status) {
    switch ($status) {
        case 'tepat_waktu':  return '<span class="badge bg-success">Tepat Waktu</span>';
        case 'pulang_awal':  return '<span class="badge bg-danger">Pulang Awal</span>';
        case 'lanjut_shift': return '<span class="badge bg-primary">Lanjut Shift</span>';
        default:             return '<span class="badge bg-secondary">Belum Keluar</span>';
    }
}

function badgeStatusLaporan($status) {
    switch ($status) {
        case 'acc':     return '<span class="badge bg-success">ACC</span>';
        case 'revisi':  return '<span class="badge bg-warning text-dark">Perlu Revisi</span>';
        case 'pending': return '<span class="badge bg-secondary">Menunggu</span>';
        default:        return '<span class="badge bg-light text-dark">-</span>';
    }
}