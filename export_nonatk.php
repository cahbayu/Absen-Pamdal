<?php
// export_nonatk.php
require_once 'config.php';
require_once 'function.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user dari session
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];

// Gunakan fungsi filter yang sama dengan index_nonatk.php
$ambilsemuadatastock = getFilteredStockData($conn, 'non_atk');
$jumlah_data = mysqli_num_rows($ambilsemuadatastock);
$filter_desc = getFilterDescription();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Stock Barang Non-ATK - Selaras</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.6.5/css/buttons.dataTables.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            padding: 2rem;
        }
        
        .container {
            max-width: 1400px;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        
        h2 {
            color: #1a1a1a;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        h4 {
            color: #6c757d;
            font-weight: 400;
            margin-bottom: 1rem;
        }
        
        .filter-info {
            background: #e6f7ff;
            border-left: 4px solid #1890ff;
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .filter-info strong {
            color: #0050b3;
        }
        
        .btn-back {
            background: #4a90e2;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 1.5rem;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            background: #3a7bc8;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .table {
            font-size: 0.875rem;
        }
        
        .table thead th {
            background: #f8f9fa;
            color: #1a1a1a;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
        }
        
        .badge {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #4a90e2;
        }
        
        .badge-success {
            background: #f0f9ff;
            color: #52c41a;
        }
        
        .badge-warning {
            background: #fff7e6;
            color: #fa8c16;
        }
        
        .badge-danger {
            background: #fff1f0;
            color: #f5222d;
        }
        
        .dt-buttons {
            margin-bottom: 1rem;
        }
        
        .dt-button {
            background: #4a90e2 !important;
            color: white !important;
            border: none !important;
            padding: 0.5rem 1rem !important;
            border-radius: 6px !important;
            margin-right: 0.5rem !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
        }
        
        .dt-button:hover {
            background: #3a7bc8 !important;
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
<div class="container">
    <?php 
    // Build back link dengan parameter filter
    $back_params = array();
    if(isset($_GET['filter_periode']) && $_GET['filter_periode'] != ''){
        $back_params[] = 'filter_periode=' . urlencode($_GET['filter_periode']);
    }
    if(isset($_GET['tanggal_dari']) && $_GET['tanggal_dari'] != ''){
        $back_params[] = 'tanggal_dari=' . urlencode($_GET['tanggal_dari']);
    }
    if(isset($_GET['tanggal_sampai']) && $_GET['tanggal_sampai'] != ''){
        $back_params[] = 'tanggal_sampai=' . urlencode($_GET['tanggal_sampai']);
    }
    if(isset($_GET['search']) && $_GET['search'] != ''){
        $back_params[] = 'search=' . urlencode($_GET['search']);
    }
    $back_link = 'index_nonatk.php' . (count($back_params) > 0 ? '?' . implode('&', $back_params) : '');
    ?>
    
    <a href="<?=$back_link;?>" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Stock Barang Non-ATK
    </a>
    
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> Export Stock Barang Non-ATK</h2>
    <h4>SELARAS - Inventory Management System</h4>
    
    <?php if($filter_desc != 'Semua Data'): ?>
    <div class="filter-info">
        <i class="bi bi-funnel-fill"></i> <strong>Filter Aktif:</strong> <?=$filter_desc;?> 
        <span class="badge badge-primary"><?=$jumlah_data;?> data</span>
    </div>
    <?php else: ?>
    <div class="filter-info">
        <i class="bi bi-info-circle"></i> Menampilkan <strong>semua data</strong> 
        <span class="badge badge-primary"><?=$jumlah_data;?> data</span>
    </div>
    <?php endif; ?>
    
    <div class="data-tables datatable-dark">
        <table class="table table-bordered table-hover" id="mauexport" width="100%" cellspacing="0">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Barang</th>
                    <th>Deskripsi</th>
                    <th>Penyimpanan</th>
                    <th>Satuan</th>
                    <th>Stock Awal</th>
                    <th>Barang Masuk</th>
                    <th>Barang Keluar</th>
                    <th>Stock Akhir</th>
                </tr>
            </thead>
            
            <tbody>
                <?php
                    $i = 1;
                    while($data=mysqli_fetch_array($ambilsemuadatastock)){
                        $idbarang = $data['idbarang'];
                        $namabarang = $data['namabarang'];
                        $deskripsi = $data['deskripsi'];
                        $penyimpanan = $data['penyimpanan'] ? $data['penyimpanan'] : '-';
                        $satuan = $data['satuan'];
                        $stock_awal = $data['stock_awal'];
                        $barang_masuk = $data['total_masuk'];
                        $barang_keluar = $data['total_keluar'];
                        $stock_akhir = $stock_awal + $barang_masuk - $barang_keluar;
                        $tanggal_input = $data['tanggal_input'];
                        $tanggal_formatted = date('d/m/Y', strtotime($tanggal_input));
                ?>
                <tr>
                    <td><?=$i++;?></td>
                    <td><strong><?=$namabarang;?></strong></td>
                    <td><?=$deskripsi;?></td>
                    <td><?=$penyimpanan;?></td>
                    <td><?=$satuan;?></td>
                    <td><?=$stock_awal;?></td>
                    <td><?=$barang_masuk;?></td>
                    <td><?=$barang_keluar;?></td>
                    <td><strong><?=$stock_akhir;?></strong></td>
                </tr>
                <?php
                    };
                ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    var exportTitle = 'Stock Opname Barang Non-ATK Kantor DPD RI Provinsi Kalimantan Barat';
    <?php if($filter_desc != 'Semua Data'): ?>
    exportTitle += ' (<?=addslashes($filter_desc);?>)';
    <?php endif; ?>
    
    $('#mauexport').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excel',
                title: exportTitle,
                text: '<i class="bi bi-file-earmark-excel"></i> Export Excel',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdf',
                title: exportTitle,
                text: '<i class="bi bi-file-earmark-pdf"></i> Export PDF',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(doc) {
                    doc.content[1].table.widths = ['5%', '18%', '18%', '13%', '8%', '9%', '9%', '9%', '11%'];
                    doc.styles.tableHeader.fillColor = '#4a90e2';
                    doc.styles.tableHeader.color = 'white';
                    doc.defaultStyle.fontSize = 7;
                    
                    // Add filter info to PDF
                    <?php if($filter_desc != 'Semua Data'): ?>
                    doc.content.splice(1, 0, {
                        text: 'Filter: <?=addslashes($filter_desc);?>',
                        style: 'subheader',
                        margin: [0, 0, 0, 10]
                    });
                    <?php endif; ?>
                }
            },
            {
                extend: 'print',
                title: exportTitle,
                text: '<i class="bi bi-printer"></i> Print',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(win) {
                    $(win.document.body).css('font-size', '10pt');
                    $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                    
                    // Add filter info to print
                    <?php if($filter_desc != 'Semua Data'): ?>
                    $(win.document.body).find('h1').after('<p style="font-size: 12pt; margin: 10px 0;">Filter: <?=addslashes($filter_desc);?></p>');
                    <?php endif; ?>
                }
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.20/i18n/Indonesian.json'
        },
        pageLength: 25
    });
});
</script>

</body>
</html>