<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

if (!hasRole(ROLE_USER)) {
    header('Location: dashboard.php');
    exit;
}

$user_id_sesi = (int)$_SESSION['user_id'];
$nama_user    = $_SESSION['name'] ?? 'Pengguna';

// ── Validasi & sanitasi tanggal ──
$dari_raw   = $_GET['dari']   ?? date('Y-m-01');
$sampai_raw = $_GET['sampai'] ?? date('Y-m-t');
$dari   = date('Y-m-d', strtotime($dari_raw));
$sampai = date('Y-m-d', strtotime($sampai_raw));
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

// ── Ambil data ──
$riwayat     = getRiwayatAbsensiUser($user_id_sesi, $dari, $sampai);
$jumlah_data = count($riwayat);

// ── Hitung ringkasan ──
$tot_hadir = $jumlah_data;
$tot_normal = $tot_telat = $tot_awal = $tot_revisi = 0;
foreach ($riwayat as $r) {
    if ($r['keterangan_masuk'] === 'normal' && $r['status_masuk'] === 'sesuai_jadwal') $tot_normal++;
    if ($r['keterangan_masuk'] === 'terlambat')   $tot_telat++;
    if ($r['status_keluar']   === 'pulang_awal')  $tot_awal++;
    if ($r['status_laporan']  === 'revisi')       $tot_revisi++;
}

// ── Helper functions ──
$nama_bulan_arr = ['','Januari','Februari','Maret','April','Mei','Juni',
                   'Juli','Agustus','September','Oktober','November','Desember'];

function fmtTgl($tgl) {
    global $nama_bulan_arr;
    if (!$tgl) return '-';
    $ts = strtotime($tgl);
    $hr = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $hr[date('w',$ts)].', '.date('d',$ts).' '.$nama_bulan_arr[(int)date('m',$ts)].' '.date('Y',$ts);
}
function labelMasuk($ket, $stat) {
    if ($stat === 'tidak_sesuai') return 'Tukar Shift';
    if ($ket  === 'terlambat')    return 'Terlambat';
    return 'Normal';
}
function labelKeluar($s) {
    return match($s) {
        'tepat_waktu'  => 'Tepat Waktu',
        'pulang_awal'  => 'Pulang Awal',
        'lanjut_shift' => 'Lanjut Shift',
        default        => 'Belum Keluar',
    };
}
function labelLaporan($s) {
    return match($s) {
        'acc'     => 'Disetujui',
        'revisi'  => 'Perlu Revisi',
        'pending' => 'Menunggu',
        default   => 'Belum Ada',
    };
}
function periodeLabel($dari, $sampai) {
    global $nama_bulan_arr;
    $t1=strtotime($dari); $t2=strtotime($sampai);
    $m1=(int)date('m',$t1); $y1=(int)date('Y',$t1);
    $m2=(int)date('m',$t2); $y2=(int)date('Y',$t2);
    if ($dari===$sampai) return date('d',$t1).' '.$nama_bulan_arr[$m1].' '.$y1;
    if ($m1===$m2 && $y1===$y2)
        return date('d',$t1).' – '.date('d',$t2).' '.$nama_bulan_arr[$m1].' '.$y1;
    return date('d',$t1).' '.$nama_bulan_arr[$m1].' '.$y1.' – '.date('d',$t2).' '.$nama_bulan_arr[$m2].' '.$y2;
}

$periode      = periodeLabel($dari, $sampai);
$tgl_cetak    = date('d').' '.$nama_bulan_arr[(int)date('m')].' '.date('Y');

$hari_pembuka_map = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa',
                     'Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$hari_pembuka = $hari_pembuka_map[date('l', strtotime($dari))] ?? 'Senin';
$tgl_pembuka  = date('d', strtotime($dari));
$bln_pembuka  = $nama_bulan_arr[(int)date('m', strtotime($dari))];
$thn_pembuka  = date('Y', strtotime($dari));

// ── Logo (base64 untuk embed di HTML & Word) ──
$logo_path   = 'assets/img/Setjen_DPDRI.png';
$logo_base64 = '';
$logo_mime   = 'image/png';
if (file_exists($logo_path)) {
    $logo_base64 = 'data:'.$logo_mime.';base64,'.base64_encode(file_get_contents($logo_path));
}

// ── Back link ──
$back_link = 'laporan_harian.php?bulan='.date('m',strtotime($dari)).'&tahun='.date('Y',strtotime($dari));

// ══════════════════════════════════════════════════════════════════
// HANDLE DOWNLOAD — jika ada ?download=1 maka generate & kirim file
// ══════════════════════════════════════════════════════════════════
if (isset($_GET['download']) && $_GET['download'] === '1') {

    // ── Buat konten HTML yang kompatibel dengan Word (MIME HTML) ──
    $safe_name  = preg_replace('/[^A-Za-z0-9 _\-]/', '_', $nama_user);
    $safe_name  = trim(str_replace(' ', '_', $safe_name));
    $fname      = 'Laporan_Harian_'.$safe_name.'_'.$dari.'_'.$sampai.'.doc';

    // Bangun tabel rincian
    $rows_html = '';
    foreach ($riwayat as $idx => $r) {
        $jam_masuk  = $r['jam_masuk']  ? date('H:i', strtotime($r['jam_masuk']))  : '-';
        $jam_keluar = $r['jam_keluar'] ? date('H:i', strtotime($r['jam_keluar'])) : '-';
        $ket_masuk  = labelMasuk($r['keterangan_masuk'], $r['status_masuk']);
        $ket_keluar = labelKeluar($r['status_keluar']);
        $stat_lap   = labelLaporan($r['status_laporan']);
        $isi_lap    = nl2br(htmlspecialchars($r['isi_laporan'] ?? '-'));

        // Warna status
        $cm_color = match(true) {
            $r['status_masuk']      === 'tidak_sesuai' => '#0891b2',
            $r['keterangan_masuk']  === 'terlambat'    => '#c0392b',
            default                                     => '#1a7f3c',
        };
        $ck_color = match($r['status_keluar']) {
            'pulang_awal'  => '#b7770d',
            'lanjut_shift' => '#6f42c1',
            'tepat_waktu'  => '#1a7f3c',
            default        => '#6c757d',
        };
        $cl_color = match($r['status_laporan']) {
            'acc'     => '#1a7f3c',
            'revisi'  => '#6f42c1',
            default   => '#6c757d',
        };

        $bg = ($idx % 2 === 1) ? 'background:#f5f8ff;' : '';
        $rows_html .= '<tr style="'.$bg.'">
            <td style="border:1px solid #000;padding:4px;text-align:center;font-size:9pt;">'.($idx+1).'</td>
            <td style="border:1px solid #000;padding:4px;font-size:8.5pt;">'.fmtTgl($r['tanggal']).'</td>
            <td style="border:1px solid #000;padding:4px;text-align:center;font-size:9pt;">'.htmlspecialchars($r['nama_shift'] ?? '-').'</td>
            <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;font-size:10pt;color:'.$cm_color.';">'.$jam_masuk.'</td>
            <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;font-size:10pt;color:'.$ck_color.';">'.$jam_keluar.'</td>
            <td style="border:1px solid #000;padding:4px;font-size:9pt;color:'.$cm_color.';font-weight:bold;">'.htmlspecialchars($ket_masuk).'</td>
            <td style="border:1px solid #000;padding:4px;font-size:9pt;color:'.$ck_color.';font-weight:bold;">'.htmlspecialchars($ket_keluar).'</td>
            <td style="border:1px solid #000;padding:4px;text-align:center;font-size:9pt;color:'.$cl_color.';font-weight:bold;">'.htmlspecialchars($stat_lap).'</td>
            <td style="border:1px solid #000;padding:4px;font-size:8pt;">'.$isi_lap.'</td>
        </tr>';
    }

    $rincian_html = $jumlah_data > 0
        ? '<table style="width:100%;border-collapse:collapse;font-family:\'Times New Roman\',serif;">
            <thead><tr style="background:#f8f9fa;">
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:4%;">No</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:16%;">Tanggal</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:10%;">Shift</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:7%;">Masuk</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:7%;">Keluar</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:11%;">Ket. Masuk</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:11%;">Ket. Keluar</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;width:10%;">Lap.</th>
                <th style="border:1px solid #000;padding:5px 4px;text-align:center;font-size:9pt;">Isi Laporan</th>
            </tr></thead>
            <tbody>'.$rows_html.'</tbody>
           </table>'
        : '<p style="text-align:center;font-style:italic;color:#888;">Tidak ada data absensi pada periode ini.</p>';

    $logo_img_tag = $logo_base64
        ? '<img src="'.$logo_base64.'" width="75" height="75" alt="Logo">'
        : '<div style="width:75px;height:75px;background:#ddd;"></div>';

    $word_html = '
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:w="urn:schemas-microsoft-com:office:word"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml>
 <w:WordDocument>
  <w:View>Print</w:View>
  <w:Zoom>100</w:Zoom>
  <w:DoNotOptimizeForBrowser/>
 </w:WordDocument>
</xml>
<![endif]-->
<style>
 @page {
   size: 21cm 29.7cm;
   margin: 3cm 2.5cm 2cm 3cm;
   mso-page-orientation: portrait;
 }
 body {
   font-family: "Times New Roman", serif;
   font-size: 11pt;
   line-height: 1.6;
 }
 table { border-collapse: collapse; }
 p { margin: 0; padding: 0; }
</style>
</head>
<body>

<!-- KOP SURAT -->
<table width="100%" style="border:none;margin-bottom:6pt;">
  <tr>
    <td width="90" style="border:none;text-align:center;vertical-align:middle;">
      '.$logo_img_tag.'
    </td>
    <td style="border:none;text-align:center;vertical-align:middle;">
      <p style="font-size:13pt;font-weight:bold;margin:2pt 0;">KANTOR DEWAN PERWAKILAN DAERAH</p>
      <p style="font-size:13pt;font-weight:bold;margin:2pt 0;">REPUBLIK INDONESIA</p>
      <p style="font-size:13pt;font-weight:bold;margin:2pt 0;">PROVINSI KALIMANTAN BARAT</p>
      <p style="font-size:10pt;margin:3pt 0;">Jln. D.A Hadi Rq. Udi. A. Kota Pontianak</p>
      <p style="font-size:10pt;margin:3pt 0;">Telp/Fax: (0561)739211 &nbsp; Email: kalbar@dpd.go.id</p>
    </td>
    <td width="90" style="border:none;"></td>
  </tr>
</table>
<hr style="border:2px solid #000;margin:6pt 0 14pt;">

<!-- JUDUL -->
<p style="text-align:center;font-size:14pt;font-weight:bold;margin:4pt 0;">LAPORAN HARIAN KARYAWAN</p>
<p style="text-align:center;font-size:14pt;font-weight:bold;margin:4pt 0 14pt;">DEWAN PERWAKILAN DAERAH RI</p>

<!-- META -->
<table style="border:none;margin-bottom:12pt;border-left:4px solid #1a6bb5;padding-left:8pt;background:#f0f6ff;width:100%;">
  <tr><td style="border:none;padding:2pt 4pt;color:#555;font-size:10pt;white-space:nowrap;">Nama Karyawan</td><td style="border:none;font-size:10pt;padding:2pt 2pt;">:</td><td style="border:none;font-weight:bold;font-size:10pt;padding:2pt 4pt;">'.htmlspecialchars($nama_user).'</td></tr>
  <tr><td style="border:none;padding:2pt 4pt;color:#555;font-size:10pt;">Periode</td><td style="border:none;font-size:10pt;padding:2pt 2pt;">:</td><td style="border:none;font-weight:bold;font-size:10pt;padding:2pt 4pt;">'.htmlspecialchars($periode).'</td></tr>
  <tr><td style="border:none;padding:2pt 4pt;color:#555;font-size:10pt;">Tanggal Cetak</td><td style="border:none;font-size:10pt;padding:2pt 2pt;">:</td><td style="border:none;font-weight:bold;font-size:10pt;padding:2pt 4pt;">'.$tgl_cetak.'</td></tr>
  <tr><td style="border:none;padding:2pt 4pt;color:#555;font-size:10pt;">Jumlah Data</td><td style="border:none;font-size:10pt;padding:2pt 2pt;">:</td><td style="border:none;font-weight:bold;font-size:10pt;padding:2pt 4pt;">'.$jumlah_data.' hari hadir</td></tr>
</table>

<!-- PARAGRAF PEMBUKA -->
<p style="text-align:justify;text-indent:2cm;line-height:1.8;margin-bottom:12pt;font-size:11pt;">
  Pada hari ini <strong>'.htmlspecialchars($hari_pembuka).'</strong> tanggal
  <strong>'.htmlspecialchars($tgl_pembuka).'</strong> bulan
  <strong>'.htmlspecialchars($bln_pembuka).'</strong> tahun
  <strong>'.htmlspecialchars($thn_pembuka).'</strong>,
  telah dilakukan pencatatan Laporan Harian Karyawan atas nama
  <strong>'.htmlspecialchars($nama_user).'</strong>
  untuk periode '.htmlspecialchars($periode).' dengan rincian sebagai berikut:
</p>

<!-- RINGKASAN -->
<p style="font-size:10pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.3px;margin:14pt 0 6pt;">A. Ringkasan Kehadiran</p>
<table width="100%" style="border-collapse:collapse;margin-bottom:14pt;font-family:\'Times New Roman\',serif;">
  <tr>
    <th style="border:1px solid #000;background:#e8f0f8;padding:6px 8px;text-align:center;font-size:10pt;">Total Hadir</th>
    <th style="border:1px solid #000;background:#e8f0f8;padding:6px 8px;text-align:center;font-size:10pt;">Tepat Waktu</th>
    <th style="border:1px solid #000;background:#e8f0f8;padding:6px 8px;text-align:center;font-size:10pt;">Terlambat</th>
    <th style="border:1px solid #000;background:#e8f0f8;padding:6px 8px;text-align:center;font-size:10pt;">Pulang Awal</th>
    <th style="border:1px solid #000;background:#e8f0f8;padding:6px 8px;text-align:center;font-size:10pt;">Perlu Revisi</th>
  </tr>
  <tr>
    <td style="border:1px solid #000;padding:7px 8px;text-align:center;font-weight:bold;font-size:14pt;color:#1A6BB5;">'.$tot_hadir.'</td>
    <td style="border:1px solid #000;padding:7px 8px;text-align:center;font-weight:bold;font-size:14pt;color:#1A7F3C;">'.$tot_normal.'</td>
    <td style="border:1px solid #000;padding:7px 8px;text-align:center;font-weight:bold;font-size:14pt;color:#C0392B;">'.$tot_telat.'</td>
    <td style="border:1px solid #000;padding:7px 8px;text-align:center;font-weight:bold;font-size:14pt;color:#B7770D;">'.$tot_awal.'</td>
    <td style="border:1px solid #000;padding:7px 8px;text-align:center;font-weight:bold;font-size:14pt;color:#6F42C1;">'.$tot_revisi.'</td>
  </tr>
</table>

<!-- RINCIAN -->
<p style="font-size:10pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.3px;margin:14pt 0 6pt;">B. Rincian Per Hari</p>
'.$rincian_html.'

<!-- PENUTUP -->
<p style="text-align:justify;text-indent:2cm;line-height:1.8;margin:16pt 0 28pt;font-size:11pt;">
  Demikian Laporan Harian Karyawan atas nama <strong>'.htmlspecialchars($nama_user).'</strong>
  untuk periode '.htmlspecialchars($periode).' ini dibuat dengan sebenar-benarnya
  dan untuk dipergunakan sebagaimana mestinya.
</p>

<!-- TANDA TANGAN -->
<table width="100%" style="border:none;">
  <tr>
    <td width="60%" style="border:none;"></td>
    <td style="border:none;text-align:center;">
      <p style="font-size:11pt;margin:0 0 4pt;">Kepala Kantor,</p>
      <p style="margin:0;line-height:1;">&nbsp;</p>
      <p style="margin:0;line-height:1;">&nbsp;</p>
      <p style="margin:0;line-height:1;">&nbsp;</p>
      <p style="margin:0;line-height:1;">&nbsp;</p>
      <p style="font-size:11pt;font-weight:bold;text-decoration:underline;margin:0 0 2pt;">Elis Nurdian, S.I.Kom.</p>
      <p style="font-size:11pt;margin:0;">NIP. 198203042009012002</p>
    </td>
  </tr>
</table>

</body>
</html>';

    // Kirim sebagai file download
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.rawurlencode($fname).'"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $word_html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Export Laporan Harian — ANDALAN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: #eef2f7;
    font-family: 'Times New Roman', Times, serif;
    padding: 2rem;
}

/* ══ TOOLBAR ══ */
.toolbar {
    max-width: 21cm;
    margin: 0 auto 1.2rem;
    display: flex; align-items: center;
    justify-content: space-between; flex-wrap: wrap; gap: 10px;
    font-family: 'DM Sans', sans-serif;
}
.toolbar-left { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.btn-nav {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px; border-radius: 6px; border: none;
    font-size: 13px; font-weight: 600; cursor: pointer;
    text-decoration: none; transition: all .2s; white-space: nowrap;
}
.btn-back  { background:#4a90e2; color:#fff; }
.btn-back:hover  { background:#3a7bc8; color:#fff; text-decoration:none; }
.btn-word  { background:#2b7a3b; color:#fff; }
.btn-word:hover  { background:#1e5e2c; }
.btn-print { background:#6c757d; color:#fff; }
.btn-print:hover { background:#545b62; }

.filter-info {
    background:#d1ecf1; border:1px solid #bee5eb; color:#0c5460;
    padding:7px 14px; border-radius:6px; font-size:12px;
    font-family:'DM Sans',sans-serif;
    display:flex; align-items:center; gap:6px;
}

/* ══ DOKUMEN A4 ══ */
.doc-wrap {
    max-width: 21cm;
    min-height: 29.7cm;
    background: #fff;
    padding: 3cm 2.5cm 2cm 3cm;
    margin: 0 auto;
    box-shadow: 0 4px 20px rgba(0,0,0,.13);
}

/* Kop surat */
.kop-table { width:100%; border-collapse:collapse; }
.kop-table td { border:0; padding:0; vertical-align:middle; }
.kop-logo { width:90px; text-align:center; }
.kop-logo img { width:80px; height:auto; }
.kop-text { text-align:center; }
.kop-text h3 { font-size:12pt; font-weight:bold; margin:0; line-height:1.4; }
.kop-text p  { font-size:10pt; margin:3px 0 0; }
.kop-spacer  { width:90px; }
.kop-line { border-bottom:3px solid #000; margin-top:10px; }

/* Judul */
.doc-title { text-align:center; margin:18px 0 10px; }
.doc-title h2 { font-size:14pt; font-weight:bold; text-transform:uppercase; margin:0; line-height:1.5; }

/* Meta info */
.doc-meta {
    margin:12px 0 18px;
    border-left:4px solid #1a6bb5;
    padding:8px 14px;
    background:#f0f6ff;
    font-size:10pt;
}
.doc-meta table { border:0; width:auto; }
.doc-meta td { border:0; padding:2px 6px 2px 0; font-size:10pt; vertical-align:top; }
.doc-meta td:first-child { color:#555; white-space:nowrap; }
.doc-meta td:nth-child(2){ color:#555; }
.doc-meta td:last-child  { font-weight:bold; color:#1a1a1a; }

/* Paragraf pembuka */
.opening {
    text-align:justify; text-indent:2cm;
    line-height:1.8; margin-bottom:16px; font-size:11pt;
}
.opening input {
    border:none; border-bottom:1px dotted #444;
    padding:0 0.2rem; margin:0 0.1rem;
    font-family:'Times New Roman',Times,serif;
    font-size:11pt; font-weight:bold;
    background:transparent; display:inline-block; outline:none;
}
.opening input:focus { border-bottom-color:#1a6bb5; }

/* Sub-judul seksi */
.sec-label { font-size:10pt; font-weight:bold; margin:16px 0 6px; text-transform:uppercase; letter-spacing:.3px; }

/* Tabel ringkasan */
.tbl-ringkasan {
    width:100%; border-collapse:collapse;
    font-size:10pt; margin-bottom:16px;
}
.tbl-ringkasan th {
    border:1px solid #000; background:#e8f0f8;
    padding:6px 8px; text-align:center; font-weight:bold;
}
.tbl-ringkasan td {
    border:1px solid #000; padding:7px 8px;
    text-align:center; font-weight:bold; font-size:14pt;
}
.c-hadir  { color:#1a6bb5; }
.c-normal { color:#1a7f3c; }
.c-telat  { color:#c0392b; }
.c-awal   { color:#b7770d; }
.c-revisi { color:#6f42c1; }

/* Tabel rincian */
.tbl-main {
    width:100%; border-collapse:collapse;
    font-size:9pt; margin-bottom:18px;
}
.tbl-main thead th {
    border:1px solid #000; background:#f8f9fa;
    padding:5px 4px; text-align:center;
    font-weight:bold; vertical-align:middle;
}
.tbl-main tbody td { border:1px solid #000; padding:4px 4px; vertical-align:middle; }
.tbl-main tbody tr:nth-child(even) td { background:#f5f8ff; }
.tc { text-align:center; }
.fw { font-weight:bold; }
.sm { font-size:8pt; }

.s-ok     { color:#1a7f3c; font-weight:bold; }
.s-telat  { color:#c0392b; font-weight:bold; }
.s-awal   { color:#b7770d; font-weight:bold; }
.s-swap   { color:#0891b2; font-weight:bold; }
.s-revisi { color:#6f42c1; font-weight:bold; }
.s-gray   { color:#6c757d; }

/* Penutup */
.closing {
    text-align:justify; text-indent:2cm;
    line-height:1.8; font-size:11pt; margin:14px 0 28px;
}

/* TTD */
.ttd { display:flex; justify-content:flex-end; margin-top:16px; page-break-inside:avoid; }
.ttd-box { text-align:center; min-width:240px; }
.ttd-box p { font-size:11pt; margin:0; line-height:1.7; }
.ttd-space { height:75px; }
.ttd-name { font-weight:bold; text-decoration:underline; }

/* Spinner overlay */
.download-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); z-index:9999;
    align-items:center; justify-content:center;
    flex-direction:column; gap:14px;
    font-family:'DM Sans',sans-serif;
}
.download-overlay.show { display:flex; }
.spinner {
    width:48px; height:48px; border:5px solid rgba(255,255,255,.2);
    border-top-color:#2b7a3b; border-radius:50%;
    animation:spin .8s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }
.download-text { color:#fff; font-size:15px; font-weight:600; }

/* ── PRINT ── */
@media print {
    body { background:#fff; padding:0; }
    .toolbar { display:none !important; }
    .download-overlay { display:none !important; }
    .doc-wrap {
        box-shadow:none; max-width:100%;
        padding:3cm 2.5cm 2cm 3cm; min-height:unset;
    }
    .opening input { border:none; padding:0 .1rem; }
    .doc-meta { background:none; }
    @page { size:A4; margin:0; }
}
</style>
</head>
<body>

<!-- SPINNER OVERLAY -->
<div class="download-overlay" id="dlOverlay">
    <div class="spinner"></div>
    <div class="download-text"><i class="fas fa-file-word"></i>&nbsp; Sedang menyiapkan file Word…</div>
</div>

<!-- ══ TOOLBAR ══ -->
<div class="toolbar">
    <div class="toolbar-left">
        <a href="<?= htmlspecialchars($back_link) ?>" class="btn-nav btn-back">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <button onclick="downloadWord()" class="btn-nav btn-word" id="btnWord">
            <i class="fas fa-file-word"></i> Download Word (.doc)
        </button>
        <button onclick="window.print()" class="btn-nav btn-print">
            <i class="fas fa-print"></i> Print / PDF
        </button>
    </div>
    <div class="filter-info">
        <i class="fas fa-calendar-alt"></i>
        Periode: <strong><?= htmlspecialchars($periode) ?></strong>
        &nbsp;&bull;&nbsp; <?= $jumlah_data ?> hari hadir
    </div>
</div>

<!-- ══ DOKUMEN PREVIEW ══ -->
<div class="doc-wrap">

    <!-- KOP SURAT -->
    <table class="kop-table">
        <tr>
            <td class="kop-logo">
                <?php if ($logo_base64): ?>
                <img src="<?= $logo_base64 ?>" alt="Logo">
                <?php else: ?>
                <div style="width:80px;height:80px;background:#ddd;display:flex;align-items:center;justify-content:center;font-size:9px;color:#888;">LOGO</div>
                <?php endif; ?>
            </td>
            <td class="kop-text">
                <h3>KANTOR DEWAN PERWAKILAN DAERAH</h3>
                <h3>REPUBLIK INDONESIA</h3>
                <h3>PROVINSI KALIMANTAN BARAT</h3>
                <p>Jln. D.A Hadi Rq. Udi. A. Kota Pontianak</p>
                <p>Telp/Fax: (0561)739211 &nbsp; Email: kalbar@dpd.go.id</p>
            </td>
            <td class="kop-spacer"></td>
        </tr>
    </table>
    <div class="kop-line"></div>

    <!-- JUDUL -->
    <div class="doc-title">
        <h2>Laporan Harian Karyawan</h2>
        <h2>Dewan Perwakilan Daerah RI</h2>
    </div>

    <!-- META -->
    <div class="doc-meta">
        <table>
            <tr><td>Nama Karyawan</td><td>:</td><td><?= htmlspecialchars($nama_user) ?></td></tr>
            <tr><td>Periode</td><td>:</td><td><?= htmlspecialchars($periode) ?></td></tr>
            <tr><td>Tanggal Cetak</td><td>:</td><td><?= $tgl_cetak ?></td></tr>
            <tr><td>Jumlah Data</td><td>:</td><td><?= $jumlah_data ?> hari hadir</td></tr>
        </table>
    </div>

    <!-- PARAGRAF PEMBUKA -->
    <div class="opening">
        Pada hari ini
        <input type="text" id="inp_hari"    value="<?= htmlspecialchars($hari_pembuka) ?>" style="width:80px;"  placeholder="Hari">
        tanggal
        <input type="text" id="inp_tanggal" value="<?= htmlspecialchars($tgl_pembuka)  ?>" style="width:40px;"  placeholder="Tgl">
        bulan
        <input type="text" id="inp_bulan"   value="<?= htmlspecialchars($bln_pembuka)  ?>" style="width:95px;"  placeholder="Bulan">
        tahun
        <input type="text" id="inp_tahun"   value="<?= htmlspecialchars($thn_pembuka)  ?>" style="width:52px;"  placeholder="Tahun">,
        telah dilakukan pencatatan Laporan Harian Karyawan atas nama
        <strong><?= htmlspecialchars($nama_user) ?></strong>
        untuk periode <?= htmlspecialchars($periode) ?> dengan rincian sebagai berikut:
    </div>

    <!-- RINGKASAN -->
    <p class="sec-label">A. Ringkasan Kehadiran</p>
    <table class="tbl-ringkasan">
        <thead>
            <tr>
                <th>Total Hadir</th>
                <th>Tepat Waktu</th>
                <th>Terlambat</th>
                <th>Pulang Awal</th>
                <th>Perlu Revisi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="c-hadir"><?=  $tot_hadir  ?></td>
                <td class="c-normal"><?= $tot_normal ?></td>
                <td class="c-telat"><?=  $tot_telat  ?></td>
                <td class="c-awal"><?=   $tot_awal   ?></td>
                <td class="c-revisi"><?= $tot_revisi ?></td>
            </tr>
        </tbody>
    </table>

    <!-- RINCIAN -->
    <p class="sec-label">B. Rincian Per Hari</p>

    <?php if (empty($riwayat)): ?>
    <p style="text-align:center;font-style:italic;color:#888;padding:20px 0;">Tidak ada data absensi pada periode ini.</p>
    <?php else: ?>
    <table class="tbl-main">
        <thead>
            <tr>
                <th style="width:4%;">No</th>
                <th style="width:16%;">Tanggal</th>
                <th style="width:11%;">Shift</th>
                <th style="width:7%;">Masuk</th>
                <th style="width:7%;">Keluar</th>
                <th style="width:12%;">Ket. Masuk</th>
                <th style="width:12%;">Ket. Keluar</th>
                <th style="width:10%;">Lap.</th>
                <th>Isi Laporan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($riwayat as $idx => $r):
            $jam_masuk  = $r['jam_masuk']  ? date('H:i', strtotime($r['jam_masuk']))  : '-';
            $jam_keluar = $r['jam_keluar'] ? date('H:i', strtotime($r['jam_keluar'])) : '-';
            $ket_masuk  = labelMasuk($r['keterangan_masuk'], $r['status_masuk']);
            $ket_keluar = labelKeluar($r['status_keluar']);
            $stat_lap   = labelLaporan($r['status_laporan']);

            $cm = match(true) {
                $r['status_masuk'] === 'tidak_sesuai'  => 's-swap',
                $r['keterangan_masuk'] === 'terlambat' => 's-telat',
                default                                => 's-ok',
            };
            $ck = match($r['status_keluar']) {
                'pulang_awal'  => 's-awal',
                'lanjut_shift' => 's-revisi',
                'tepat_waktu'  => 's-ok',
                default        => 's-gray',
            };
            $cl = match($r['status_laporan']) {
                'acc'     => 's-ok',
                'revisi'  => 's-revisi',
                'pending' => 's-gray',
                default   => 's-gray',
            };
        ?>
        <tr>
            <td class="tc"><?= $idx+1 ?></td>
            <td><?= fmtTgl($r['tanggal']) ?></td>
            <td class="tc"><?= htmlspecialchars($r['nama_shift'] ?? '-') ?></td>
            <td class="tc fw <?= $cm ?>"><?= $jam_masuk ?></td>
            <td class="tc fw <?= $ck ?>"><?= $jam_keluar ?></td>
            <td class="<?= $cm ?>"><?= htmlspecialchars($ket_masuk) ?></td>
            <td class="<?= $ck ?>"><?= htmlspecialchars($ket_keluar) ?></td>
            <td class="tc <?= $cl ?>"><?= htmlspecialchars($stat_lap) ?></td>
            <td class="sm"><?= nl2br(htmlspecialchars($r['isi_laporan'] ?? '-')) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- PENUTUP -->
    <div class="closing">
        Demikian Laporan Harian Karyawan atas nama <strong><?= htmlspecialchars($nama_user) ?></strong>
        untuk periode <?= htmlspecialchars($periode) ?> ini dibuat dengan sebenar-benarnya
        dan untuk dipergunakan sebagaimana mestinya.
    </div>

    <!-- TANDA TANGAN -->
    <div class="ttd">
        <div class="ttd-box">
            <p>Kepala Kantor,</p>
            <div class="ttd-space"></div>
            <p class="ttd-name">Elis Nurdian, S.I.Kom.</p>
            <p>NIP. 198203042009012002</p>
        </div>
    </div>

</div><!-- /doc-wrap -->

<script>
/* ══ Download Word — arahkan ke PHP dengan ?download=1 ══ */
function downloadWord() {
    var btn = document.getElementById('btnWord');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses…';

    // Tampilkan overlay spinner
    document.getElementById('dlOverlay').classList.add('show');

    // Bangun URL download dengan parameter yang sama + download=1
    var params = new URLSearchParams(window.location.search);
    params.set('download', '1');
    var url = window.location.pathname + '?' + params.toString();

    // Buat iframe tersembunyi untuk trigger download tanpa pindah halaman
    var iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = url;
    document.body.appendChild(iframe);

    // Sembunyikan overlay dan reset tombol setelah jeda
    setTimeout(function() {
        document.getElementById('dlOverlay').classList.remove('show');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-file-word"></i> Download Word (.doc)';
        document.body.removeChild(iframe);
    }, 2500);
}
</script>
</body>
</html>