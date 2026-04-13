<?php
// functions.php - Fungsi-fungsi utama sistem absensi Pamdal
// Terhubung dengan config.php

require_once 'config.php';

// ============================================================
// BAGIAN 1: FUNGSI MANAJEMEN SHIFT
// ============================================================

/**
 * Mendapatkan semua data shift yang tersedia
 */
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

/**
 * Mendapatkan data shift berdasarkan ID
 */
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
 * Mendapatkan shift yang sedang aktif berdasarkan waktu sekarang.
 * Berguna untuk menentukan shift user saat ini.
 * Penanganan khusus untuk Shift Malam (00:00 - 08:00) yang melewati tengah malam.
 */
function getActiveShift() {
    global $conn;
    $now = date('H:i:s');
    $sql = "SELECT * FROM shift WHERE
                (jam_masuk < jam_keluar AND '$now' BETWEEN jam_masuk AND jam_keluar)
                OR
                (jam_masuk > jam_keluar AND ('$now' >= jam_masuk OR '$now' <= jam_keluar))
            LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Cek keterlambatan berdasarkan jam masuk shift vs waktu sekarang.
 * Rule #4: Pamdal terlambat tetap bisa absen masuk, maks toleransi 15 menit.
 *
 * @param  string $jam_masuk_shift  Format 'HH:MM:SS'
 * @return array  ['terlambat' => bool, 'menit' => int, 'bisa_absen' => bool]
 */
function cekKeterlambatan($jam_masuk_shift) {
    $sekarang      = strtotime(date('H:i:s'));
    $jadwal_masuk  = strtotime($jam_masuk_shift);
    $selisih_menit = (int)(($sekarang - $jadwal_masuk) / 60);

    if ($selisih_menit <= 0) {
        return ['terlambat' => false, 'menit' => 0, 'bisa_absen' => true];
    } elseif ($selisih_menit <= 15) {
        return ['terlambat' => true, 'menit' => $selisih_menit, 'bisa_absen' => true];
    } else {
        return ['terlambat' => true, 'menit' => $selisih_menit, 'bisa_absen' => false];
    }
}

/**
 * Cek apakah waktu sekarang sudah mendekati jam pulang shift (toleransi ±10 menit).
 * Rule #5: opsi "tepat waktu" hanya muncul saat waktu pulang.
 *
 * @param  string $jam_keluar_shift  Format 'HH:MM:SS'
 * @return bool
 */
function isWaktuPulang($jam_keluar_shift) {
    $sekarang      = strtotime(date('H:i:s'));
    $jadwal_keluar = strtotime($jam_keluar_shift);
    $selisih_menit = abs($sekarang - $jadwal_keluar) / 60;
    return $selisih_menit <= 10;
}

/**
 * Cek apakah user pulang lebih awal dari jadwal shift.
 *
 * @param  string $jam_keluar_shift  Format 'HH:MM:SS'
 * @return bool
 */
function isPulangAwal($jam_keluar_shift) {
    $sekarang      = strtotime(date('H:i:s'));
    $jadwal_keluar = strtotime($jam_keluar_shift);
    return $sekarang < $jadwal_keluar;
}

/**
 * Mendapatkan opsi status keluar yang tersedia berdasarkan waktu & kondisi shift.
 * Rule #5: tepat_waktu hanya saat waktu pulang; pulang_awal butuh alasan; lanjut_shift selalu ada.
 *
 * @param  string $shift_jam_keluar  Format 'HH:MM:SS'
 * @return array  Daftar opsi [['value', 'label', 'alasan_required'], ...]
 */
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
 *
 * Rule #1 : Pamdal wajib login (dicek via isLoggedIn() di halaman pemanggil).
 * Rule #2 : Jika status 'tidak_sesuai', data penukaran wajib diisi.
 * Rule #4 : Terlambat maks 15 menit masih bisa absen dengan keterangan 'terlambat'.
 *
 * @param  int    $user_id
 * @param  int    $shift_id
 * @param  string $status_masuk   'sesuai_jadwal' | 'tidak_sesuai'
 * @param  array  $data_penukaran ['tipe', 'user_pengganti_id', 'tanggal', 'shift_id'] — wajib jika tidak_sesuai
 * @return array  ['success' => bool, 'message' => string, 'absensi_id' => int|null]
 */
function absenMasuk($user_id, $shift_id, $status_masuk, $data_penukaran = []) {
    global $conn;

    $user_id      = (int)$user_id;
    $shift_id     = (int)$shift_id;
    $status_masuk = cleanInput($status_masuk);

    // Validasi nilai status_masuk
    if (!in_array($status_masuk, ['sesuai_jadwal', 'tidak_sesuai'])) {
        return ['success' => false, 'message' => 'Status masuk tidak valid.', 'absensi_id' => null];
    }

    // Rule #2: data penukaran wajib jika tidak sesuai jadwal
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

    // Cek apakah sudah absen masuk pada shift & tanggal yang sama
    $tanggal = date('Y-m-d');
    if (sudahAbsenMasuk($user_id, $shift_id, $tanggal)) {
        return ['success' => false, 'message' => 'Anda sudah absen masuk pada shift ini hari ini.', 'absensi_id' => null];
    }

    // Ambil data shift untuk cek keterlambatan
    $shift = getShiftById($shift_id);
    if (!$shift) {
        return ['success' => false, 'message' => 'Data shift tidak ditemukan.', 'absensi_id' => null];
    }

    // Rule #4: cek keterlambatan
    $cek_telat        = cekKeterlambatan($shift['jam_masuk']);
    $keterangan_masuk = 'normal';

    if ($cek_telat['terlambat']) {
        if (!$cek_telat['bisa_absen']) {
            return [
                'success'    => false,
                'message'    => 'Anda terlambat ' . $cek_telat['menit'] . ' menit. Melebihi toleransi 15 menit, tidak dapat absen masuk.',
                'absensi_id' => null,
            ];
        }
        $keterangan_masuk = 'terlambat';
    }

    $jam_masuk_now = date('Y-m-d H:i:s');

    // Insert record absensi
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

    // Rule #2: simpan data penukaran shift jika tidak sesuai jadwal
    if ($status_masuk === 'tidak_sesuai') {
        $result_penukaran = simpanPenukaranShift($absensi_id, $data_penukaran);
        if (!$result_penukaran['success']) {
            // Rollback insert absensi jika penukaran gagal
            $conn->query("DELETE FROM absensi WHERE id = $absensi_id");
            return ['success' => false, 'message' => $result_penukaran['message'], 'absensi_id' => null];
        }
    }

    $pesan = ($keterangan_masuk === 'terlambat')
        ? 'Absen masuk berhasil. Catatan: Anda terlambat ' . $cek_telat['menit'] . ' menit.'
        : 'Absen masuk berhasil.';

    return ['success' => true, 'message' => $pesan, 'absensi_id' => $absensi_id];
}

/**
 * Cek apakah user sudah absen masuk pada shift & tanggal tertentu.
 *
 * @param  int    $user_id
 * @param  int    $shift_id
 * @param  string $tanggal  Format 'YYYY-MM-DD'
 * @return bool
 */
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

/**
 * Mendapatkan data absensi aktif (sudah masuk, belum keluar) milik user pada shift & tanggal tertentu.
 *
 * @param  int    $user_id
 * @param  int    $shift_id
 * @param  string $tanggal  Format 'YYYY-MM-DD'
 * @return array|null
 */
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

/**
 * Proses absen keluar untuk Pamdal.
 *
 * Rule #3 : Hanya bisa absen keluar jika sudah absen masuk.
 * Rule #5 : Status keluar: 'tepat_waktu' (hanya saat waktunya), 'pulang_awal' (wajib alasan), 'lanjut_shift'.
 * Rule #6 : 'lanjut_shift' = sistem otomatis siapkan absensi masuk shift berikutnya.
 *
 * @param  int    $user_id
 * @param  int    $absensi_id   ID record absensi masuk yang dikaitkan
 * @param  string $status_keluar 'tepat_waktu' | 'pulang_awal' | 'lanjut_shift'
 * @param  string $alasan        Wajib diisi jika status_keluar = 'pulang_awal'
 * @return array  ['success' => bool, 'message' => string]
 */
function absenKeluar($user_id, $absensi_id, $status_keluar, $alasan = '') {
    global $conn;

    $user_id       = (int)$user_id;
    $absensi_id    = (int)$absensi_id;
    $status_keluar = cleanInput($status_keluar);
    $alasan        = cleanInput($alasan);

    // Validasi nilai status_keluar
    if (!in_array($status_keluar, ['tepat_waktu', 'pulang_awal', 'lanjut_shift'])) {
        return ['success' => false, 'message' => 'Status keluar tidak valid.'];
    }

    // Rule #3: Validasi absensi masuk ada, milik user ini, dan belum keluar
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

    // Rule #5: tepat_waktu hanya saat waktu pulang
    if ($status_keluar === 'tepat_waktu' && !isWaktuPulang($absensi['shift_jam_keluar'])) {
        return ['success' => false, 'message' => 'Opsi tepat waktu hanya tersedia saat waktu pulang shift.'];
    }

    // Rule #5: pulang_awal wajib disertai alasan
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

    // Rule #6: Jika lanjut_shift, buat otomatis absensi masuk untuk shift berikutnya
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

/**
 * Buat record absensi masuk otomatis untuk shift berikutnya (lanjut shift).
 * Rule #6: Pamdal double-shift tidak perlu absen masuk kembali.
 *
 * @param  int $user_id
 * @param  int $shift_id_sekarang
 * @return array ['success' => bool, 'message' => string]
 */
function buatAbsensiLanjutShift($user_id, $shift_id_sekarang) {
    global $conn;

    $user_id           = (int)$user_id;
    $shift_id_sekarang = (int)$shift_id_sekarang;

    // Rotasi shift: 1 (Pagi) -> 2 (Sore) -> 3 (Malam) -> 1
    $shift_berikutnya_id = ($shift_id_sekarang % 3) + 1;

    // Shift malam (3) lanjut ke shift pagi (1) berarti hari berikutnya
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

/**
 * Simpan data penukaran / pengganti shift.
 * Rule #2: Jika tidak sesuai jadwal, wajib isi nama pengganti, tanggal, dan shift.
 *
 * @param  int   $absensi_id
 * @param  array $data  ['tipe', 'user_pengganti_id', 'tanggal', 'shift_id']
 * @return array ['success' => bool, 'message' => string]
 */
function simpanPenukaranShift($absensi_id, $data) {
    global $conn;

    $absensi_id        = (int)$absensi_id;
    $tipe              = cleanInput($data['tipe']);
    $user_pengganti_id = (int)$data['user_pengganti_id'];
    $tanggal           = cleanInput($data['tanggal']);
    $shift_id          = (int)$data['shift_id'];

    if (!in_array($tipe, ['penukar', 'pengganti'])) {
        return ['success' => false, 'message' => 'Tipe penukaran tidak valid. Gunakan "penukar" atau "pengganti".'];
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

/**
 * Mendapatkan data penukaran shift berdasarkan absensi_id.
 *
 * @param  int   $absensi_id
 * @return array
 */
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

/**
 * Membuat laporan baru.
 * Rule #2: Laporan wajib dibuat ketika absen tidak sesuai jadwal.
 *
 * @param  int    $absensi_id
 * @param  string $isi_laporan
 * @return array  ['success' => bool, 'message' => string, 'laporan_id' => int|null]
 */
function buatLaporan($absensi_id, $isi_laporan) {
    global $conn;

    $absensi_id  = (int)$absensi_id;
    $isi_laporan = cleanInput($isi_laporan);

    if (empty($isi_laporan)) {
        return ['success' => false, 'message' => 'Isi laporan tidak boleh kosong.', 'laporan_id' => null];
    }

    // Verifikasi absensi ada
    $cek_absensi = $conn->query("SELECT id FROM absensi WHERE id = $absensi_id LIMIT 1");
    if (!$cek_absensi || $cek_absensi->num_rows === 0) {
        return ['success' => false, 'message' => 'Data absensi tidak ditemukan.', 'laporan_id' => null];
    }

    // Laporan hanya boleh satu per absensi
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

/**
 * Mendapatkan laporan berdasarkan absensi_id.
 *
 * @param  int        $absensi_id
 * @return array|null
 */
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

/**
 * Mendapatkan semua laporan (untuk Kepala Kantor).
 * Rule #7: Kepala Kantor dapat memantau seluruh laporan.
 *
 * @param  string $status  'pending' | 'acc' | 'revisi' | 'semua'
 * @return array
 */
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

/**
 * Perbarui isi laporan setelah mendapat catatan revisi (oleh Pamdal).
 *
 * @param  int    $laporan_id
 * @param  int    $user_id           Untuk verifikasi kepemilikan laporan
 * @param  string $isi_laporan_baru
 * @return array  ['success' => bool, 'message' => string]
 */
function updateLaporan($laporan_id, $user_id, $isi_laporan_baru) {
    global $conn;

    $laporan_id       = (int)$laporan_id;
    $user_id          = (int)$user_id;
    $isi_laporan_baru = cleanInput($isi_laporan_baru);

    if (empty($isi_laporan_baru)) {
        return ['success' => false, 'message' => 'Isi laporan tidak boleh kosong.'];
    }

    // Verifikasi laporan milik user dan statusnya 'revisi'
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

/**
 * Memberikan approval (ACC / Tolak) terhadap absensi atau laporan.
 * Rule #7: Kepala Kantor memberikan persetujuan, penolakan, atau revisi.
 *
 * @param  int    $approved_by   user_id Kepala Kantor (super_admin)
 * @param  string $status        'diterima' | 'ditolak'
 * @param  string $catatan       Catatan opsional / alasan penolakan
 * @param  int    $absensi_id    Opsional — isi salah satu saja
 * @param  int    $laporan_id    Opsional — isi salah satu saja
 * @return array  ['success' => bool, 'message' => string]
 */
function buatApproval($approved_by, $status, $catatan = '', $absensi_id = null, $laporan_id = null) {
    global $conn;

    $approved_by = (int)$approved_by;
    $status      = cleanInput($status);
    $catatan     = cleanInput($catatan);

    if (!in_array($status, ['diterima', 'ditolak'])) {
        return ['success' => false, 'message' => 'Status approval tidak valid. Gunakan "diterima" atau "ditolak".'];
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

    // Sinkronisasi status laporan berdasarkan hasil approval
    if ($laporan_id) {
        if ($status === 'diterima') {
            $conn->query("UPDATE laporan SET status = 'acc'    WHERE id = $laporan_id");
        } elseif ($status === 'ditolak') {
            // Ditolak berarti perlu revisi dari Pamdal
            $conn->query("UPDATE laporan SET status = 'revisi', catatan_revisi = '$catatan' WHERE id = $laporan_id");
        }
    }

    return ['success' => true, 'message' => 'Approval berhasil disimpan.'];
}

/**
 * Mendapatkan riwayat approval berdasarkan absensi_id atau laporan_id.
 *
 * @param  int|null $absensi_id
 * @param  int|null $laporan_id
 * @return array
 */
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

/**
 * Mendapatkan rekap absensi seorang user dalam rentang tanggal tertentu.
 *
 * @param  int         $user_id
 * @param  string|null $dari    Format 'YYYY-MM-DD'
 * @param  string|null $sampai  Format 'YYYY-MM-DD'
 * @return array
 */
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

/**
 * Mendapatkan seluruh rekap absensi semua Pamdal (untuk Kepala Kantor).
 * Rule #7: Kepala Kantor dapat memantau seluruh absensi.
 *
 * @param  string|null $tanggal   Filter tanggal tertentu
 * @param  int|null    $shift_id  Filter shift tertentu
 * @return array
 */
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

/**
 * Mendapatkan detail satu record absensi beserta informasi user & shift.
 *
 * @param  int        $absensi_id
 * @return array|null
 */
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

/**
 * Statistik ringkasan absensi harian untuk dashboard Kepala Kantor.
 *
 * @param  string|null $tanggal  Format 'YYYY-MM-DD' (default: hari ini)
 * @return array
 */
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

/**
 * Mendapatkan data user berdasarkan ID (tanpa password).
 *
 * @param  int        $user_id
 * @return array|null
 */
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

/**
 * Mendapatkan semua Pamdal aktif (role = user) untuk monitoring.
 *
 * @return array
 */
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

/**
 * Mendapatkan semua user aktif untuk keperluan dropdown pengganti shift.
 *
 * @param  int   $kecuali_user_id  Hilangkan user ini dari daftar (diri sendiri)
 * @return array
 */
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

/**
 * Update kolom last_login setelah berhasil login.
 *
 * @param int $user_id
 */
function updateLastLogin($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $now     = date('Y-m-d H:i:s');
    $conn->query("UPDATE users SET last_login = '$now' WHERE id = $user_id");
}

/**
 * Login user: validasi kredensial, buat session.
 * Rule #1: Pamdal wajib login sebelum melakukan absensi.
 *
 * @param  string $username
 * @param  string $password
 * @return array  ['success' => bool, 'message' => string]
 */
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

    // Buat session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];

    updateLastLogin($user['id']);

    return ['success' => true, 'message' => 'Login berhasil. Selamat datang, ' . $user['name'] . '!'];
}

/**
 * Logout user: hancurkan session dan cookie.
 */
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

/**
 * Format datetime ke format user-friendly Bahasa Indonesia.
 * Contoh: '2026-04-13 08:05:00' => 'Senin, 13 April 2026 08:05'
 *
 * @param  string $datetime
 * @return string
 */
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

/**
 * Format tanggal ke format Bahasa Indonesia.
 * Contoh: '2026-04-13' => '13 April 2026'
 *
 * @param  string $tanggal
 * @return string
 */
function formatTanggalID($tanggal) {
    if (empty($tanggal)) return '-';
    $bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    $ts  = strtotime($tanggal);
    return date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Menghasilkan badge HTML Bootstrap untuk status masuk.
 *
 * @param  string $status      'sesuai_jadwal' | 'tidak_sesuai'
 * @param  string $keterangan  'normal' | 'terlambat'
 * @return string
 */
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

/**
 * Menghasilkan badge HTML Bootstrap untuk status keluar.
 *
 * @param  string|null $status
 * @return string
 */
function badgeStatusKeluar($status) {
    switch ($status) {
        case 'tepat_waktu':  return '<span class="badge bg-success">Tepat Waktu</span>';
        case 'pulang_awal':  return '<span class="badge bg-danger">Pulang Awal</span>';
        case 'lanjut_shift': return '<span class="badge bg-primary">Lanjut Shift</span>';
        default:             return '<span class="badge bg-secondary">Belum Keluar</span>';
    }
}

/**
 * Menghasilkan badge HTML Bootstrap untuk status laporan.
 *
 * @param  string|null $status
 * @return string
 */
function badgeStatusLaporan($status) {
    switch ($status) {
        case 'acc':     return '<span class="badge bg-success">ACC</span>';
        case 'revisi':  return '<span class="badge bg-warning text-dark">Perlu Revisi</span>';
        case 'pending': return '<span class="badge bg-secondary">Menunggu</span>';
        default:        return '<span class="badge bg-light text-dark">-</span>';
    }
}