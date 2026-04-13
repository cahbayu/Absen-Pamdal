<?php
require_once 'config.php';

// ========== HELPER FUNCTIONS ==========

// Fungsi untuk mendapatkan stok akhir real-time
function getStokAkhir($conn, $idbarang, $jenis = 'atk') {
    $tabel_stok   = ($jenis == 'non_atk') ? 'stok_non_atk' : 'stok';
    $tabel_masuk  = ($jenis == 'non_atk') ? 'masuk_non_atk' : 'masuk';
    $tabel_keluar = ($jenis == 'non_atk') ? 'keluar_non_atk' : 'keluar';

    $query = mysqli_query($conn, "
        SELECT
            stock,
            (SELECT COALESCE(SUM(qty), 0) FROM $tabel_masuk  WHERE idbarang = $tabel_stok.idbarang) as total_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM $tabel_keluar WHERE idbarang = $tabel_stok.idbarang) as total_keluar
        FROM $tabel_stok WHERE idbarang = '$idbarang'
    ");

    if($data = mysqli_fetch_array($query)) {
        return ($data['stock'] + $data['total_masuk']) - $data['total_keluar'];
    }
    return 0;
}

// Fungsi untuk mendapatkan detail barang
function getBarangDetail($conn, $idbarang, $jenis = 'atk') {
    $tabel_stok = ($jenis == 'non_atk') ? 'stok_non_atk' : 'stok';
    $query = mysqli_query($conn, "SELECT namabarang, satuan FROM $tabel_stok WHERE idbarang='$idbarang'");
    return mysqli_fetch_array($query);
}

// Fungsi untuk set notifikasi ke session
function setNotification($message, $type) {
    $_SESSION['notification']      = $message;
    $_SESSION['notification_type'] = $type;
}

// Fungsi untuk mendapatkan dan clear notifikasi
function getNotification() {
    if(isset($_SESSION['notification'])) {
        $notification = array(
            'message' => $_SESSION['notification'],
            'type'    => $_SESSION['notification_type']
        );
        unset($_SESSION['notification']);
        unset($_SESSION['notification_type']);
        return $notification;
    }
    return null;
}

// ========== DASHBOARD FUNCTIONS ==========

function getTotalStokAkhir($conn, $jenis = 'atk') {
    $tabel_stok   = ($jenis == 'non_atk') ? 'stok_non_atk' : 'stok';
    $tabel_masuk  = ($jenis == 'non_atk') ? 'masuk_non_atk' : 'masuk';
    $tabel_keluar = ($jenis == 'non_atk') ? 'keluar_non_atk' : 'keluar';

    $query = mysqli_query($conn, "
        SELECT
            s.idbarang,
            s.stock as stock_awal,
            (SELECT COALESCE(SUM(qty), 0) FROM $tabel_masuk  WHERE idbarang = s.idbarang) as total_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM $tabel_keluar WHERE idbarang = s.idbarang) as total_keluar
        FROM $tabel_stok s
    ");

    $totalstok = 0;
    while($data = mysqli_fetch_array($query)){
        $totalstok += ($data['stock_awal'] + $data['total_masuk']) - $data['total_keluar'];
    }
    return $totalstok;
}

function getTotalBarangMasuk($conn, $bulan = 6, $jenis = 'atk') {
    $tabel_masuk    = ($jenis == 'non_atk') ? 'masuk_non_atk' : 'masuk';
    $tanggalBatasAwal = date("Y-m-d", strtotime("-{$bulan} months"));

    $query = mysqli_query($conn, "
        SELECT COALESCE(SUM(qty), 0) as total_masuk
        FROM $tabel_masuk
        WHERE tanggal >= '$tanggalBatasAwal'
    ");

    $data = mysqli_fetch_array($query);
    return $data['total_masuk'] ? $data['total_masuk'] : 0;
}

function getTotalBarangKeluar($conn, $bulan = 6, $jenis = 'atk') {
    $tabel_keluar   = ($jenis == 'non_atk') ? 'keluar_non_atk' : 'keluar';
    $tanggalBatasAwal = date("Y-m-d", strtotime("-{$bulan} months"));

    $query = mysqli_query($conn, "
        SELECT COALESCE(SUM(qty), 0) as total_keluar
        FROM $tabel_keluar
        WHERE tanggal >= '$tanggalBatasAwal'
    ");

    $data = mysqli_fetch_array($query);
    return $data['total_keluar'] ? $data['total_keluar'] : 0;
}

// ========== FILTER FUNCTION ==========

function getFilteredStockData($conn, $jenis = 'atk') {
    $tabel_stok   = ($jenis == 'non_atk') ? 'stok_non_atk' : 'stok';
    $tabel_masuk  = ($jenis == 'non_atk') ? 'masuk_non_atk' : 'masuk';
    $tabel_keluar = ($jenis == 'non_atk') ? 'keluar_non_atk' : 'keluar';

    $where_clauses = array();

    if(isset($_GET['filter_periode']) && $_GET['filter_periode'] != ''){
        $filter = $_GET['filter_periode'];

        if($filter == '1bulan'){
            $where_clauses[] = "DATE(s.tanggal_input) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        } elseif($filter == '6bulan'){
            $where_clauses[] = "DATE(s.tanggal_input) >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
        } elseif($filter == '1tahun'){
            $where_clauses[] = "DATE(s.tanggal_input) >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        } elseif($filter == 'custom'){
            if(isset($_GET['tanggal_dari'])   && $_GET['tanggal_dari']   != '' &&
               isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] != ''){
                $tanggal_dari    = mysqli_real_escape_string($conn, $_GET['tanggal_dari']);
                $tanggal_sampai  = mysqli_real_escape_string($conn, $_GET['tanggal_sampai']);
                $where_clauses[] = "DATE(s.tanggal_input) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
            }
        }
    }

    if(isset($_GET['search']) && $_GET['search'] != ''){
        $search          = mysqli_real_escape_string($conn, $_GET['search']);
        $where_clauses[] = "(s.namabarang LIKE '%$search%' OR s.deskripsi LIKE '%$search%' OR s.penyimpanan LIKE '%$search%')";
    }

    $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $query = "
        SELECT
            s.idbarang,
            s.namabarang,
            s.deskripsi,
            s.stock as stock_awal,
            s.satuan,
            s.penyimpanan,
            s.tanggal_input,
            (SELECT COALESCE(SUM(qty), 0) FROM $tabel_masuk  WHERE idbarang = s.idbarang) as total_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM $tabel_keluar WHERE idbarang = s.idbarang) as total_keluar
        FROM $tabel_stok s
        $where_sql
        ORDER BY s.tanggal_input DESC
    ";

    return mysqli_query($conn, $query);
}

function getFilterDescription() {
    $desc = "Semua Data";
    if(isset($_GET['filter_periode']) && $_GET['filter_periode'] != ''){
        if($_GET['filter_periode'] == '1bulan')      $desc = "1 Bulan Terakhir";
        elseif($_GET['filter_periode'] == '6bulan')  $desc = "6 Bulan Terakhir";
        elseif($_GET['filter_periode'] == '1tahun')  $desc = "1 Tahun Terakhir";
        elseif($_GET['filter_periode'] == 'custom') {
            if(isset($_GET['tanggal_dari']) && isset($_GET['tanggal_sampai'])) {
                $desc = "Custom: " . date('d/m/Y', strtotime($_GET['tanggal_dari'])) . " - " . date('d/m/Y', strtotime($_GET['tanggal_sampai']));
            }
        }
    }
    if(isset($_GET['search']) && $_GET['search'] != '') $desc .= " | Pencarian: " . htmlspecialchars($_GET['search']);
    return $desc;
}

// ========== CRUD FUNCTIONS - ATK ==========

// Tambah barang baru ATK
if(isset($_POST['addnewbarang'])){
    if(!canCreate('atk')){
        setNotification("Anda tidak memiliki akses untuk menambah barang ATK!", "danger");
        header('location:index.php');
        exit();
    }

    $namabarang    = mysqli_real_escape_string($conn, $_POST['namabarang']);
    $deskripsi     = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $stock         = (int)$_POST['stock'];
    $satuan        = mysqli_real_escape_string($conn, $_POST['satuan']);
    $penyimpanan   = mysqli_real_escape_string($conn, $_POST['penyimpanan']);
    $tanggal_input = isset($_POST['tanggal_input']) ? $_POST['tanggal_input'] : date('Y-m-d');

    $addtotable = mysqli_query($conn, "INSERT INTO stok (namabarang, deskripsi, stock, satuan, penyimpanan, tanggal_input)
                                       VALUES('$namabarang','$deskripsi','$stock','$satuan','$penyimpanan','$tanggal_input')");

    if($addtotable){
        $tanggal_formatted = date('d/m/Y', strtotime($tanggal_input));
        setNotification("Barang <strong>{$namabarang}</strong> berhasil ditambahkan dengan stok awal <strong>{$stock} {$satuan}</strong> pada tanggal <strong>{$tanggal_formatted}</strong>", "success");
    } else {
        setNotification("Gagal menambahkan barang. Silakan coba lagi.", "danger");
    }
    header('location:index.php');
    exit();
}

// Tambah barang masuk ATK
if(isset($_POST['barangmasuk'])){
    if(!canCreate('atk')){
        setNotification("Anda tidak memiliki akses untuk menambah barang masuk ATK!", "danger");
        header('location:masuk_atk.php');
        exit();
    }

    $barangnya = $_POST['barangnya'];
    $penerima  = $_POST['penerima'];
    $tanggal   = $_POST['tanggal'];
    $qty       = $_POST['qty'];

    $addtomasuk = mysqli_query($conn, "INSERT INTO masuk (idbarang, keterangan, qty, tanggal)
                                       VALUES('$barangnya','$penerima','$qty','$tanggal')");

    if($addtomasuk){
        $barang     = getBarangDetail($conn, $barangnya, 'atk');
        $stok_akhir = getStokAkhir($conn, $barangnya, 'atk');
        setNotification("Barang masuk berhasil ditambahkan! <strong>{$barang['namabarang']}</strong> bertambah <strong>{$qty} {$barang['satuan']}</strong>. Stok akhir: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal menambahkan barang masuk. Silakan coba lagi.", "danger");
    }
    header('location:masuk_atk.php');
    exit();
}

// Tambah barang keluar ATK
if(isset($_POST['barangkeluar'])){
    if(!canCreate('atk')){
        setNotification("Anda tidak memiliki akses untuk menambah barang keluar ATK!", "danger");
        header('location:keluar_atk.php');
        exit();
    }

    $barangnya = $_POST['barangnya'];
    $penerima  = $_POST['penerima'];
    $tanggal   = $_POST['tanggal'];
    $qty       = $_POST['qty'];

    $cekstock = mysqli_query($conn, "
        SELECT
            stock, satuan, namabarang,
            (SELECT COALESCE(SUM(qty), 0) FROM masuk  WHERE idbarang = stok.idbarang) as total_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM keluar WHERE idbarang = stok.idbarang) as total_keluar
        FROM stok WHERE idbarang = '$barangnya'
    ");
    $ds             = mysqli_fetch_array($cekstock);
    $stock_sekarang = ($ds['stock'] + $ds['total_masuk']) - $ds['total_keluar'];
    $nama_barang    = $ds['namabarang'];
    $satuan         = $ds['satuan'];

    if($qty <= $stock_sekarang){
        $addtokeluar = mysqli_query($conn, "INSERT INTO keluar (idbarang, penerima, qty, tanggal)
                                            VALUES('$barangnya','$penerima','$qty','$tanggal')");
        if($addtokeluar){
            $sisa_stok = $stock_sekarang - $qty;
            setNotification("Barang keluar berhasil ditambahkan! <strong>{$nama_barang}</strong> berkurang <strong>{$qty} {$satuan}</strong>. Sisa stok: <strong>{$sisa_stok} {$satuan}</strong>", "success");
        } else {
            setNotification("Gagal menambahkan barang keluar. Silakan coba lagi.", "danger");
        }
        header('location:keluar_atk.php');
    } else {
        $kekurangan = $qty - $stock_sekarang;
        setNotification("Stok tidak mencukupi! Stok tersedia untuk <strong>{$nama_barang}</strong> hanya <strong>{$stock_sekarang} {$satuan}</strong>, Anda ingin mengeluarkan <strong>{$qty} {$satuan}</strong>. Kekurangan: <strong>{$kekurangan} {$satuan}</strong>", "danger");
        header('location:keluar_atk.php');
    }
    exit();
}

// ===== UPDATE BARANG ATK (tanggal_input sekarang bisa diedit) =====
if(isset($_POST['update'])){
    if(!canUpdate('atk')){
        setNotification("Anda tidak memiliki akses untuk mengupdate barang ATK!", "danger");
        header('location:index.php');
        exit();
    }

    $idb           = $_POST['idb'];
    $namabarang    = mysqli_real_escape_string($conn, $_POST['namabarang']);
    $deskripsi     = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $stock         = (int)$_POST['stock'];
    $satuan        = mysqli_real_escape_string($conn, $_POST['satuan']);
    $penyimpanan   = mysqli_real_escape_string($conn, $_POST['penyimpanan']);
    $tanggal_input = mysqli_real_escape_string($conn, $_POST['tanggal_input']); // <-- BARU: ambil tanggal dari form

    $update = mysqli_query($conn, "
        UPDATE stok
        SET namabarang   = '$namabarang',
            deskripsi    = '$deskripsi',
            stock        = '$stock',
            satuan       = '$satuan',
            penyimpanan  = '$penyimpanan',
            tanggal_input = '$tanggal_input'          -- <-- BARU: simpan perubahan tanggal
        WHERE idbarang = '$idb'
    ");

    if($update){
        $tanggal_formatted = date('d/m/Y', strtotime($tanggal_input));
        setNotification("Data barang <strong>{$namabarang}</strong> berhasil diupdate! Tanggal input: <strong>{$tanggal_formatted}</strong>", "success");
    } else {
        setNotification("Gagal mengupdate data barang. Silakan coba lagi.", "danger");
    }
    header('location:index.php');
    exit();
}

// Hapus barang ATK
if(isset($_POST['hapus'])){
    if(!canDelete('atk')){
        setNotification("Anda tidak memiliki akses untuk menghapus barang ATK!", "danger");
        header('location:index.php');
        exit();
    }

    $idb    = $_POST['idb'];
    $barang = getBarangDetail($conn, $idb, 'atk');
    $nama   = $barang['namabarang'];

    mysqli_begin_transaction($conn);

    try {
        $hapus_masuk  = mysqli_query($conn, "DELETE FROM masuk  WHERE idbarang='$idb'");
        $hapus_keluar = mysqli_query($conn, "DELETE FROM keluar WHERE idbarang='$idb'");
        $hapus_stok   = mysqli_query($conn, "DELETE FROM stok   WHERE idbarang='$idb'");

        if($hapus_masuk && $hapus_keluar && $hapus_stok){
            mysqli_commit($conn);
            setNotification("Barang <strong>{$nama}</strong> dan semua data transaksi terkait berhasil dihapus dari database!", "success");
        } else {
            mysqli_rollback($conn);
            setNotification("Gagal menghapus barang. Silakan coba lagi.", "danger");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        setNotification("Terjadi kesalahan: " . $e->getMessage(), "danger");
    }

    header('location:index.php');
    exit();
}

// Update barang masuk ATK
if(isset($_POST['updatemasuk'])){
    if(!canUpdate('atk')){
        setNotification("Anda tidak memiliki akses untuk mengupdate barang masuk ATK!", "danger");
        header('location:masuk_atk.php');
        exit();
    }

    $idb       = $_POST['idb'];
    $idm       = $_POST['idm'];
    $deskripsi = $_POST['keterangan'];
    $qty       = $_POST['qty'];
    $tanggal   = $_POST['tanggal'];

    $update = mysqli_query($conn, "UPDATE masuk SET qty='$qty', keterangan='$deskripsi', tanggal='$tanggal' WHERE idmasuk='$idm'");

    if($update){
        $barang     = getBarangDetail($conn, $idb, 'atk');
        $stok_akhir = getStokAkhir($conn, $idb, 'atk');
        setNotification("Data barang masuk berhasil diupdate! Stok akhir <strong>{$barang['namabarang']}</strong>: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal mengupdate data barang masuk. Silakan coba lagi.", "danger");
    }
    header('location:masuk_atk.php');
    exit();
}

// Hapus barang masuk ATK
if(isset($_POST['hapusmasuk'])){
    if(!canDelete('atk')){
        setNotification("Anda tidak memiliki akses untuk menghapus barang masuk ATK!", "danger");
        header('location:masuk_atk.php');
        exit();
    }

    $idb    = $_POST['idb'];
    $idm    = $_POST['idm'];
    $barang = getBarangDetail($conn, $idb, 'atk');

    $hapus = mysqli_query($conn, "DELETE FROM masuk WHERE idmasuk='$idm'");

    if($hapus){
        $stok_akhir = getStokAkhir($conn, $idb, 'atk');
        setNotification("Data barang masuk berhasil dihapus! Stok akhir <strong>{$barang['namabarang']}</strong>: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal menghapus data barang masuk. Silakan coba lagi.", "danger");
    }
    header('location:masuk_atk.php');
    exit();
}

// Update barang keluar ATK
if(isset($_POST['updatekeluar'])){
    if(!canUpdate('atk')){
        setNotification("Anda tidak memiliki akses untuk mengupdate barang keluar ATK!", "danger");
        header('location:keluar_atk.php');
        exit();
    }

    $idb      = $_POST['idb'];
    $idk      = $_POST['idk'];
    $penerima = $_POST['penerima'];
    $tanggal  = $_POST['tanggal'];
    $qty_baru = $_POST['qty'];

    $lama  = mysqli_query($conn, "SELECT qty FROM keluar WHERE idkeluar='$idk'");
    $qlama = mysqli_fetch_array($lama)['qty'];

    $cek = mysqli_query($conn, "
        SELECT
            stock, namabarang, satuan,
            (SELECT COALESCE(SUM(qty), 0) FROM masuk  WHERE idbarang = s.idbarang) as t_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM keluar WHERE idbarang = s.idbarang) as t_keluar
        FROM stok s WHERE idbarang = '$idb'
    ");
    $d              = mysqli_fetch_array($cek);
    $stok_tersedia  = ($d['stock'] + $d['t_masuk'] - $d['t_keluar']) + $qlama;
    $nama_barang    = $d['namabarang'];
    $satuan         = $d['satuan'];

    if($qty_baru <= $stok_tersedia){
        $update = mysqli_query($conn, "UPDATE keluar SET qty='$qty_baru', penerima='$penerima', tanggal='$tanggal' WHERE idkeluar='$idk'");
        if($update){
            $sisa_stok = $stok_tersedia - $qty_baru;
            setNotification("Data barang keluar berhasil diupdate! Sisa stok <strong>{$nama_barang}</strong>: <strong>{$sisa_stok} {$satuan}</strong>", "success");
        } else {
            setNotification("Gagal mengupdate data barang keluar. Silakan coba lagi.", "danger");
        }
        header('location:keluar_atk.php');
    } else {
        $kekurangan = $qty_baru - $stok_tersedia;
        setNotification("Stok tidak mencukupi! Stok tersedia untuk <strong>{$nama_barang}</strong> hanya <strong>{$stok_tersedia} {$satuan}</strong>, Anda ingin mengeluarkan <strong>{$qty_baru} {$satuan}</strong>. Kekurangan: <strong>{$kekurangan} {$satuan}</strong>", "danger");
        header('location:keluar_atk.php');
    }
    exit();
}

// Hapus barang keluar ATK
if(isset($_POST['hapuskeluar'])){
    if(!canDelete('atk')){
        setNotification("Anda tidak memiliki akses untuk menghapus barang keluar ATK!", "danger");
        header('location:keluar_atk.php');
        exit();
    }

    $idb    = $_POST['idb'];
    $idk    = $_POST['idk'];
    $barang = getBarangDetail($conn, $idb, 'atk');

    $hapus = mysqli_query($conn, "DELETE FROM keluar WHERE idkeluar='$idk'");

    if($hapus){
        $stok_akhir = getStokAkhir($conn, $idb, 'atk');
        setNotification("Data barang keluar berhasil dihapus! Stok akhir <strong>{$barang['namabarang']}</strong>: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal menghapus data barang keluar. Silakan coba lagi.", "danger");
    }
    header('location:keluar_atk.php');
    exit();
}


// ========== CRUD FUNCTIONS - NON ATK ==========

// Tambah barang baru NON ATK
if(isset($_POST['addnewbarang_nonatk'])){
    if(!canCreate('non_atk')){
        setNotification("Anda tidak memiliki akses untuk menambah barang Non-ATK!", "danger");
        header('location:index_nonatk.php');
        exit();
    }

    $namabarang    = mysqli_real_escape_string($conn, $_POST['namabarang']);
    $deskripsi     = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $stock         = (int)$_POST['stock'];
    $satuan        = mysqli_real_escape_string($conn, $_POST['satuan']);
    $penyimpanan   = mysqli_real_escape_string($conn, $_POST['penyimpanan']);
    $tanggal_input = isset($_POST['tanggal_input']) ? $_POST['tanggal_input'] : date('Y-m-d');

    $addtotable = mysqli_query($conn, "INSERT INTO stok_non_atk (namabarang, deskripsi, stock, satuan, penyimpanan, tanggal_input)
                                       VALUES('$namabarang','$deskripsi','$stock','$satuan','$penyimpanan','$tanggal_input')");

    if($addtotable){
        $tanggal_formatted = date('d/m/Y', strtotime($tanggal_input));
        setNotification("Barang Non-ATK <strong>{$namabarang}</strong> berhasil ditambahkan dengan stok awal <strong>{$stock} {$satuan}</strong> pada tanggal <strong>{$tanggal_formatted}</strong>", "success");
    } else {
        setNotification("Gagal menambahkan barang. Silakan coba lagi.", "danger");
    }
    header('location:index_nonatk.php');
    exit();
}

// Tambah barang masuk NON ATK
if(isset($_POST['barangmasuk_nonatk'])){
    if(!canCreate('non_atk')){
        setNotification("Anda tidak memiliki akses untuk menambah barang masuk Non-ATK!", "danger");
        header('location:masuk_nonatk.php');
        exit();
    }

    $barangnya = $_POST['barangnya'];
    $penerima  = $_POST['penerima'];
    $tanggal   = $_POST['tanggal'];
    $qty       = $_POST['qty'];

    $addtomasuk = mysqli_query($conn, "INSERT INTO masuk_non_atk (idbarang, keterangan, qty, tanggal)
                                       VALUES('$barangnya','$penerima','$qty','$tanggal')");

    if($addtomasuk){
        $barang     = getBarangDetail($conn, $barangnya, 'non_atk');
        $stok_akhir = getStokAkhir($conn, $barangnya, 'non_atk');
        setNotification("Barang masuk berhasil ditambahkan! <strong>{$barang['namabarang']}</strong> bertambah <strong>{$qty} {$barang['satuan']}</strong>. Stok akhir: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal menambahkan barang masuk. Silakan coba lagi.", "danger");
    }
    header('location:masuk_nonatk.php');
    exit();
}

// Tambah barang keluar NON ATK
if(isset($_POST['barangkeluar_nonatk'])){
    if(!canCreate('non_atk')){
        setNotification("Anda tidak memiliki akses untuk menambah barang keluar Non-ATK!", "danger");
        header('location:keluar_nonatk.php');
        exit();
    }

    $barangnya = $_POST['barangnya'];
    $penerima  = $_POST['penerima'];
    $tanggal   = $_POST['tanggal'];
    $qty       = $_POST['qty'];

    $cekstock = mysqli_query($conn, "
        SELECT
            stock, satuan, namabarang,
            (SELECT COALESCE(SUM(qty), 0) FROM masuk_non_atk  WHERE idbarang = stok_non_atk.idbarang) as total_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM keluar_non_atk WHERE idbarang = stok_non_atk.idbarang) as total_keluar
        FROM stok_non_atk WHERE idbarang = '$barangnya'
    ");
    $ds             = mysqli_fetch_array($cekstock);
    $stock_sekarang = ($ds['stock'] + $ds['total_masuk']) - $ds['total_keluar'];
    $nama_barang    = $ds['namabarang'];
    $satuan         = $ds['satuan'];

    if($qty <= $stock_sekarang){
        $addtokeluar = mysqli_query($conn, "INSERT INTO keluar_non_atk (idbarang, penerima, qty, tanggal)
                                            VALUES('$barangnya','$penerima','$qty','$tanggal')");
        if($addtokeluar){
            $sisa_stok = $stock_sekarang - $qty;
            setNotification("Barang keluar berhasil ditambahkan! <strong>{$nama_barang}</strong> berkurang <strong>{$qty} {$satuan}</strong>. Sisa stok: <strong>{$sisa_stok} {$satuan}</strong>", "success");
        } else {
            setNotification("Gagal menambahkan barang keluar. Silakan coba lagi.", "danger");
        }
        header('location:keluar_nonatk.php');
    } else {
        $kekurangan = $qty - $stock_sekarang;
        setNotification("Stok tidak mencukupi! Stok tersedia untuk <strong>{$nama_barang}</strong> hanya <strong>{$stock_sekarang} {$satuan}</strong>, Anda ingin mengeluarkan <strong>{$qty} {$satuan}</strong>. Kekurangan: <strong>{$kekurangan} {$satuan}</strong>", "danger");
        header('location:keluar_nonatk.php');
    }
    exit();
}

// Update barang NON ATK (tanggal_input sekarang bisa diedit)
if(isset($_POST['update_nonatk'])){
    if(!canUpdate('non_atk')){
        setNotification("Anda tidak memiliki akses untuk mengupdate barang Non-ATK!", "danger");
        header('location:index_nonatk.php');
        exit();
    }

    $idb           = $_POST['idb'];
    $namabarang    = mysqli_real_escape_string($conn, $_POST['namabarang']);
    $deskripsi     = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $stock         = (int)$_POST['stock'];
    $satuan        = mysqli_real_escape_string($conn, $_POST['satuan']);
    $penyimpanan   = mysqli_real_escape_string($conn, $_POST['penyimpanan']);
    $tanggal_input = mysqli_real_escape_string($conn, $_POST['tanggal_input']); // <-- BARU: ambil tanggal dari form

    $update = mysqli_query($conn, "
        UPDATE stok_non_atk
        SET namabarang    = '$namabarang',
            deskripsi     = '$deskripsi',
            stock         = '$stock',
            satuan        = '$satuan',
            penyimpanan   = '$penyimpanan',
            tanggal_input = '$tanggal_input'          -- <-- BARU: simpan perubahan tanggal
        WHERE idbarang = '$idb'
    ");

    if($update){
        $tanggal_formatted = date('d/m/Y', strtotime($tanggal_input));
        setNotification("Data barang <strong>{$namabarang}</strong> berhasil diupdate! Tanggal input: <strong>{$tanggal_formatted}</strong>", "success");
    } else {
        setNotification("Gagal mengupdate data barang. Silakan coba lagi.", "danger");
    }
    header('location:index_nonatk.php');
    exit();
}

// Hapus barang NON ATK
if(isset($_POST['hapus_nonatk'])){
    if(!canDelete('non_atk')){
        setNotification("Anda tidak memiliki akses untuk menghapus barang Non-ATK!", "danger");
        header('location:index_nonatk.php');
        exit();
    }

    $idb    = $_POST['idb'];
    $barang = getBarangDetail($conn, $idb, 'non_atk');
    $nama   = $barang['namabarang'];

    mysqli_begin_transaction($conn);

    try {
        $hapus_masuk  = mysqli_query($conn, "DELETE FROM masuk_non_atk  WHERE idbarang='$idb'");
        $hapus_keluar = mysqli_query($conn, "DELETE FROM keluar_non_atk WHERE idbarang='$idb'");
        $hapus_stok   = mysqli_query($conn, "DELETE FROM stok_non_atk   WHERE idbarang='$idb'");

        if($hapus_masuk && $hapus_keluar && $hapus_stok){
            mysqli_commit($conn);
            setNotification("Barang <strong>{$nama}</strong> dan semua data transaksi terkait berhasil dihapus dari database!", "success");
        } else {
            mysqli_rollback($conn);
            setNotification("Gagal menghapus barang. Silakan coba lagi.", "danger");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        setNotification("Terjadi kesalahan: " . $e->getMessage(), "danger");
    }

    header('location:index_nonatk.php');
    exit();
}

// Update barang masuk NON ATK
if(isset($_POST['updatemasuk_nonatk'])){
    if(!canUpdate('non_atk')){
        setNotification("Anda tidak memiliki akses untuk mengupdate barang masuk Non-ATK!", "danger");
        header('location:masuk_nonatk.php');
        exit();
    }

    $idb       = $_POST['idb'];
    $idm       = $_POST['idm'];
    $deskripsi = $_POST['keterangan'];
    $qty       = $_POST['qty'];
    $tanggal   = $_POST['tanggal'];

    $update = mysqli_query($conn, "UPDATE masuk_non_atk SET qty='$qty', keterangan='$deskripsi', tanggal='$tanggal' WHERE idmasuk='$idm'");

    if($update){
        $barang     = getBarangDetail($conn, $idb, 'non_atk');
        $stok_akhir = getStokAkhir($conn, $idb, 'non_atk');
        setNotification("Data barang masuk berhasil diupdate! Stok akhir <strong>{$barang['namabarang']}</strong>: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal mengupdate data barang masuk. Silakan coba lagi.", "danger");
    }
    header('location:masuk_nonatk.php');
    exit();
}

// Hapus barang masuk NON ATK
if(isset($_POST['hapusmasuk_nonatk'])){
    if(!canDelete('non_atk')){
        setNotification("Anda tidak memiliki akses untuk menghapus barang masuk Non-ATK!", "danger");
        header('location:masuk_nonatk.php');
        exit();
    }

    $idb    = $_POST['idb'];
    $idm    = $_POST['idm'];
    $barang = getBarangDetail($conn, $idb, 'non_atk');

    $hapus = mysqli_query($conn, "DELETE FROM masuk_non_atk WHERE idmasuk='$idm'");

    if($hapus){
        $stok_akhir = getStokAkhir($conn, $idb, 'non_atk');
        setNotification("Data barang masuk berhasil dihapus! Stok akhir <strong>{$barang['namabarang']}</strong>: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal menghapus data barang masuk. Silakan coba lagi.", "danger");
    }
    header('location:masuk_nonatk.php');
    exit();
}

// Update barang keluar NON ATK
if(isset($_POST['updatekeluar_nonatk'])){
    if(!canUpdate('non_atk')){
        setNotification("Anda tidak memiliki akses untuk mengupdate barang keluar Non-ATK!", "danger");
        header('location:keluar_nonatk.php');
        exit();
    }

    $idb      = $_POST['idb'];
    $idk      = $_POST['idk'];
    $penerima = $_POST['penerima'];
    $tanggal  = $_POST['tanggal'];
    $qty_baru = $_POST['qty'];

    $lama  = mysqli_query($conn, "SELECT qty FROM keluar_non_atk WHERE idkeluar='$idk'");
    $qlama = mysqli_fetch_array($lama)['qty'];

    $cek = mysqli_query($conn, "
        SELECT
            stock, namabarang, satuan,
            (SELECT COALESCE(SUM(qty), 0) FROM masuk_non_atk  WHERE idbarang = s.idbarang) as t_masuk,
            (SELECT COALESCE(SUM(qty), 0) FROM keluar_non_atk WHERE idbarang = s.idbarang) as t_keluar
        FROM stok_non_atk s WHERE idbarang = '$idb'
    ");
    $d             = mysqli_fetch_array($cek);
    $stok_tersedia = ($d['stock'] + $d['t_masuk'] - $d['t_keluar']) + $qlama;
    $nama_barang   = $d['namabarang'];
    $satuan        = $d['satuan'];

    if($qty_baru <= $stok_tersedia){
        $update = mysqli_query($conn, "UPDATE keluar_non_atk SET qty='$qty_baru', penerima='$penerima', tanggal='$tanggal' WHERE idkeluar='$idk'");
        if($update){
            $sisa_stok = $stok_tersedia - $qty_baru;
            setNotification("Data barang keluar berhasil diupdate! Sisa stok <strong>{$nama_barang}</strong>: <strong>{$sisa_stok} {$satuan}</strong>", "success");
        } else {
            setNotification("Gagal mengupdate data barang keluar. Silakan coba lagi.", "danger");
        }
        header('location:keluar_nonatk.php');
    } else {
        $kekurangan = $qty_baru - $stok_tersedia;
        setNotification("Stok tidak mencukupi! Stok tersedia untuk <strong>{$nama_barang}</strong> hanya <strong>{$stok_tersedia} {$satuan}</strong>, Anda ingin mengeluarkan <strong>{$qty_baru} {$satuan}</strong>. Kekurangan: <strong>{$kekurangan} {$satuan}</strong>", "danger");
        header('location:keluar_nonatk.php');
    }
    exit();
}

// Hapus barang keluar NON ATK
if(isset($_POST['hapuskeluar_nonatk'])){
    if(!canDelete('non_atk')){
        setNotification("Anda tidak memiliki akses untuk menghapus barang keluar Non-ATK!", "danger");
        header('location:keluar_nonatk.php');
        exit();
    }

    $idb    = $_POST['idb'];
    $idk    = $_POST['idk'];
    $barang = getBarangDetail($conn, $idb, 'non_atk');

    $hapus = mysqli_query($conn, "DELETE FROM keluar_non_atk WHERE idkeluar='$idk'");

    if($hapus){
        $stok_akhir = getStokAkhir($conn, $idb, 'non_atk');
        setNotification("Data barang keluar berhasil dihapus! Stok akhir <strong>{$barang['namabarang']}</strong>: <strong>{$stok_akhir} {$barang['satuan']}</strong>", "success");
    } else {
        setNotification("Gagal menghapus data barang keluar. Silakan coba lagi.", "danger");
    }
    header('location:keluar_nonatk.php');
    exit();
}
?>