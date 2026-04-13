<?php
// export.php
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

// Gunakan fungsi filter yang sama dengan index.php
$ambilsemuadatastock = getFilteredStockData($conn);
$jumlah_data = mysqli_num_rows($ambilsemuadatastock);
$filter_desc = getFilterDescription();

// Convert logo to base64 untuk digunakan di semua export
$logo_path = 'assets/img/Setjen_DPDRI.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/png;base64,' . base64_encode($logo_data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Stock Barang - Selaras</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.6.5/css/buttons.dataTables.min.css">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Times New Roman', Times, serif;
            padding: 2rem;
        }
        
        .container {
            max-width: 21cm;
            min-height: 29.7cm;
            background: white;
            padding: 3cm 2.5cm 2cm 3cm;
            margin: 0 auto;
            border-radius: 0;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
        }
        
        .report-header h3 {
            font-size: 12pt;
            font-weight: bold;
            margin: 0;
            line-height: 1.3;
        }
        
        .report-title {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .report-title h3 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .opening-paragraph {
            text-align: justify;
            text-indent: 2cm;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            font-size: 11pt;
        }
        
        .opening-paragraph label {
            font-weight: normal;
            margin: 0;
            display: inline;
        }
        
        .opening-paragraph input.form-control {
            border: none;
            border-bottom: 1px dotted #333;
            padding: 0 0.25rem;
            margin: 0 0.15rem;
            font-size: 11pt;
            width: auto;
            display: inline-block;
            background: transparent;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
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
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 10pt;
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
            font-size: 10pt;
            margin-bottom: 2rem;
            border-collapse: collapse;
            width: 100%;
        }
        
        .table thead th {
            background: #f8f9fa;
            color: #1a1a1a;
            font-weight: bold;
            border: 1px solid #000;
            padding: 0.5rem 0.4rem;
            text-align: center;
            vertical-align: middle;
            font-size: 10pt;
        }
        
        .table tbody td {
            border: 1px solid #000;
            padding: 0.4rem 0.4rem;
            vertical-align: middle;
            font-size: 10pt;
        }
        
        .table tbody td:first-child {
            text-align: center;
        }
        
        .table tbody td:nth-child(3),
        .table tbody td:nth-child(4),
        .table tbody td:nth-child(5),
        .table tbody td:nth-child(6),
        .table tbody td:nth-child(7) {
            text-align: center;
        }
        
        .dt-buttons {
            margin-bottom: 1.5rem;
        }
        
        .dt-button {
            background: #4a90e2 !important;
            color: white !important;
            border: none !important;
            padding: 0.6rem 1.2rem !important;
            border-radius: 6px !important;
            margin-right: 0.5rem !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
            font-size: 13px !important;
        }
        
        .dt-button:hover {
            background: #3a7bc8 !important;
            transform: translateY(-1px);
        }
        
        .report-header table {
            border: 0 !important;
        }
        
        .report-header table td {
            border: 0 !important;
            padding: 0;
        }
        
        .closing-paragraph {
            text-align: justify;
            text-indent: 2cm;
            line-height: 1.8;
            margin-top: 2rem;
            font-size: 11pt;
            margin-bottom: 3rem;
        }
        
        .signature-section {
            margin-top: 3rem;
            display: flex;
            justify-content: flex-end;
            page-break-inside: avoid;
        }
        
        .signature-box {
            text-align: center;
            min-width: 250px;
        }
        
        .signature-title {
            margin-bottom: 0.5rem;
            font-weight: normal;
            font-size: 11pt;
        }
        
        .signature-space {
            height: 80px;
            margin: 0.5rem 0;
        }
        
        .signature-name {
            margin: 0.5rem 0;
            font-weight: bold;
            font-size: 11pt;
        }
        
        .signature-name u {
            text-decoration: underline;
        }
        
        .signature-nip {
            margin: 0.25rem 0 0 0;
            font-weight: normal;
            font-size: 11pt;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                max-width: 100%;
                padding: 3cm 2.5cm 2cm 3cm;
                page-break-after: always;
            }
            
            .btn-back, .dt-buttons, .filter-info, #export-buttons-container {
                display: none !important;
            }
            
            .opening-paragraph input.form-control {
                border: none;
                padding: 0;
                margin: 0 0.15rem;
                font-weight: bold;
            }
            
            .signature-name {
                font-weight: bold;
            }
            
            .table {
                page-break-inside: auto;
            }
            
            .table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            .table thead {
                display: table-header-group;
            }
            
            .closing-paragraph {
                page-break-inside: avoid;
            }
            
            .signature-section {
                page-break-inside: avoid;
            }
            
            @page {
                size: A4;
                margin: 0;
            }
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
    $back_link = 'index.php' . (count($back_params) > 0 ? '?' . implode('&', $back_params) : '');
    ?>
    
    <a href="<?=$back_link;?>" class="btn-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Stock Barang
    </a>
    
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
    
    <!-- Export Buttons Area -->
    <div id="export-buttons-container" style="margin-bottom: 1.5rem;"></div>
    
    <!-- Report Header (Kop Surat) -->
    <div class="report-header">
        <table style="width: 100%; border: 0;">
            <tr>
                <td style="width: 100px; vertical-align: middle; text-align: center;">
                    <img src="<?=$logo_path;?>" alt="Logo" id="logo-img" style="width: 80px; height: auto;">
                </td>
                <td style="vertical-align: middle; text-align: center;">
                    <h3><strong>KANTOR DEWAN PERWAKILAN DAERAH</strong></h3>
                    <h3><strong>REPUBLIK INDONESIA</strong></h3>
                    <h3><strong>PROVINSI KALIMANTAN BARAT</strong></h3>
                    <p style="font-size: 10pt; margin: 5px 0 0 0;">Jln. D.A Hadi Rq. Udi. A. Kota Pontianak</p>
                    <p style="font-size: 10pt; margin: 0;">Telp/Fax: (0561)739211 Email: kalbar@dpd.go.id</p>
                </td>
                <td style="width: 100px;"></td>
            </tr>
        </table>
        <div style="border-bottom: 3px solid #000; margin-top: 10px;"></div>
    </div>
    
    <!-- Report Title -->
    <div class="report-title">
        <h3>BERITA ACARA STOCK OPNAME</h3>
        <h3>BARANG PAKAI HABIS</h3>
    </div>
    
    <!-- Opening Paragraph -->
    <div class="opening-paragraph">
        <label>
            Pada hari ini 
            <input type="text" id="hari" class="form-control" style="width: 100px;" placeholder="Rabu" value="Rabu">
            tanggal 
            <input type="text" id="tanggal" class="form-control" style="width: 140px;" placeholder="Tiga Puluh Satu" value="Tiga Puluh Satu">
            bulan 
            <input type="text" id="bulan" class="form-control" style="width: 100px;" placeholder="Desember" value="Desember">
            tahun 
            <input type="text" id="tahun" class="form-control" style="width: 180px;" placeholder="Dua Ribu Dua Puluh Lima" value="Dua Ribu Dua Puluh Lima">, 
            telah dilakukan Stock Opname Barang Pakai Habis (ATK dan Cetakan) terhadap:
        </label>
    </div>
    
    <div class="data-tables datatable-dark">
        <table class="table table-bordered" id="mauexport" width="100%" cellspacing="0">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 35%;">Nama Barang</th>
                    <th style="width: 10%;">Satuan</th>
                    <th style="width: 12%;">Stock Awal</th>
                    <th style="width: 13%;">Barang Masuk</th>
                    <th style="width: 13%;">Barang Keluar</th>
                    <th style="width: 12%;">Stock Akhir</th>
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
                ?>
                <tr>
                    <td><?=$i++;?></td>
                    <td><?=$namabarang;?></td>
                    <td><?=$satuan;?></td>
                    <td><?=$stock_awal;?></td>
                    <td><?=$barang_masuk;?></td>
                    <td><?=$barang_keluar;?></td>
                    <td><?=$stock_akhir;?></td>
                </tr>
                <?php
                    };
                ?>
            </tbody>
        </table>
    </div>
    
    <!-- Closing Paragraph -->
    <div class="closing-paragraph">
        Demikian Berita Acara Stock Opname Barang Pakai Habis (ATK dan Cetakan) Satker Dewan dan Setjen ini dibuat untuk dipergunakan sebagaimana mestinya.
    </div>
    
    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <p class="signature-title">Kepala Kantor</p>
            <div class="signature-space"></div>
            <p class="signature-name"><u>Elis Nurdian, S.I.Kom.</u></p>
            <p class="signature-nip">NIP. 198203042009012002</p>
        </div>
    </div>
</div>

<!-- Hidden logo base64 for export -->
<input type="hidden" id="logo-base64" value="<?=$logo_base64;?>">

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.print.min.js"></script>
<script src="https://unpkg.com/docx@7.1.0/build/index.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<script>
$(document).ready(function() {
    var exportTitle = 'Stock Opname Barang Pakai Habis';
    var logoBase64 = $('#logo-base64').val();
    
    <?php if($filter_desc != 'Semua Data'): ?>
    exportTitle += ' (<?=addslashes($filter_desc);?>)';
    <?php endif; ?>
    
    // Function to convert image to base64 (untuk logo)
    function getBase64Image(img) {
        var canvas = document.createElement("canvas");
        canvas.width = img.width;
        canvas.height = img.height;
        var ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0);
        return canvas.toDataURL("image/png");
    }
    
    // Export to Word function dengan logo
    function exportToWord() {
        var hari = $('#hari').val() || 'Rabu';
        var tanggal = $('#tanggal').val() || 'Tiga Puluh Satu';
        var bulan = $('#bulan').val() || 'Desember';
        var tahun = $('#tahun').val() || 'Dua Ribu Dua Puluh Lima';
        
        // Get table data
        var table = $('#mauexport').DataTable();
        var data = table.rows({search: 'applied'}).data();
        
        // Convert logo to base64 for Word
        var logoImg = document.getElementById('logo-img');
        var logoBase64Word = logoBase64;
        
        // Build table rows
        var tableRows = [];
        
        // Header row
        tableRows.push(
            new docx.TableRow({
                children: [
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "No", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    }),
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "Nama Barang", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    }),
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "Satuan", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    }),
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "Stock Awal", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    }),
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "Barang Masuk", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    }),
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "Barang Keluar", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    }),
                    new docx.TableCell({
                        children: [new docx.Paragraph({text: "Stock Akhir", alignment: docx.AlignmentType.CENTER, style: "tableHeader"})],
                        shading: {fill: "F8F9FA"},
                        verticalAlign: docx.VerticalAlign.CENTER
                    })
                ]
            })
        );
        
        // Data rows
        for (var i = 0; i < data.length; i++) {
            tableRows.push(
                new docx.TableRow({
                    children: [
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][0].toString(), alignment: docx.AlignmentType.CENTER})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        }),
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][1].toString()})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        }),
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][2].toString(), alignment: docx.AlignmentType.CENTER})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        }),
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][3].toString(), alignment: docx.AlignmentType.CENTER})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        }),
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][4].toString(), alignment: docx.AlignmentType.CENTER})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        }),
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][5].toString(), alignment: docx.AlignmentType.CENTER})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        }),
                        new docx.TableCell({
                            children: [new docx.Paragraph({text: data[i][6].toString(), alignment: docx.AlignmentType.CENTER})],
                            verticalAlign: docx.VerticalAlign.CENTER
                        })
                    ]
                })
            );
        }
        
        // Extract base64 data from data URL
        var base64Data = logoBase64Word.split(',')[1];
        
        // Create document
        const doc = new docx.Document({
            styles: {
                default: {
                    document: {
                        run: {
                            font: "Times New Roman",
                            size: 22
                        }
                    }
                },
                paragraphStyles: [
                    {
                        id: "header",
                        name: "Header",
                        basedOn: "Normal",
                        run: {
                            size: 24,
                            bold: true
                        },
                        paragraph: {
                            alignment: docx.AlignmentType.CENTER,
                            spacing: {after: 40}
                        }
                    },
                    {
                        id: "title",
                        name: "Title",
                        basedOn: "Normal",
                        run: {
                            size: 32,
                            bold: true
                        },
                        paragraph: {
                            alignment: docx.AlignmentType.CENTER,
                            spacing: {after: 200}
                        }
                    },
                    {
                        id: "tableHeader",
                        name: "Table Header",
                        basedOn: "Normal",
                        run: {
                            bold: true,
                            size: 20
                        }
                    }
                ]
            },
            sections: [{
                properties: {
                    page: {
                        margin: {
                            top: docx.convertInchesToTwip(1.18),
                            right: docx.convertInchesToTwip(0.98),
                            bottom: docx.convertInchesToTwip(0.79),
                            left: docx.convertInchesToTwip(1.18)
                        }
                    }
                },
                children: [
                    // Kop Surat dengan Logo
                    new docx.Table({
                        rows: [
                            new docx.TableRow({
                                children: [
                                    new docx.TableCell({
                                        children: [
                                            new docx.Paragraph({
                                                children: [
                                                    new docx.ImageRun({
                                                        data: Uint8Array.from(atob(base64Data), c => c.charCodeAt(0)),
                                                        transformation: {
                                                            width: 80,
                                                            height: 80
                                                        }
                                                    })
                                                ],
                                                alignment: docx.AlignmentType.CENTER
                                            })
                                        ],
                                        width: {
                                            size: 15,
                                            type: docx.WidthType.PERCENTAGE
                                        },
                                        borders: {
                                            top: { style: docx.BorderStyle.NONE },
                                            bottom: { style: docx.BorderStyle.NONE },
                                            left: { style: docx.BorderStyle.NONE },
                                            right: { style: docx.BorderStyle.NONE }
                                        },
                                        verticalAlign: docx.VerticalAlign.CENTER
                                    }),
                                    new docx.TableCell({
                                        children: [
                                            new docx.Paragraph({
                                                text: "KANTOR DEWAN PERWAKILAN DAERAH",
                                                alignment: docx.AlignmentType.CENTER,
                                                spacing: { after: 40 },
                                                style: "header"
                                            }),
                                            new docx.Paragraph({
                                                text: "REPUBLIK INDONESIA",
                                                alignment: docx.AlignmentType.CENTER,
                                                spacing: { after: 40 },
                                                style: "header"
                                            }),
                                            new docx.Paragraph({
                                                text: "PROVINSI KALIMANTAN BARAT",
                                                alignment: docx.AlignmentType.CENTER,
                                                spacing: { after: 40 },
                                                style: "header"
                                            }),
                                            new docx.Paragraph({
                                                text: "Jln. D.A Hadi Rq. Udi. A. Kota Pontianak",
                                                alignment: docx.AlignmentType.CENTER,
                                                spacing: { after: 20 }
                                            }),
                                            new docx.Paragraph({
                                                text: "Telp/Fax: (0561)739211 Email: kalbar@dpd.go.id",
                                                alignment: docx.AlignmentType.CENTER
                                            })
                                        ],
                                        width: {
                                            size: 70,
                                            type: docx.WidthType.PERCENTAGE
                                        },
                                        borders: {
                                            top: { style: docx.BorderStyle.NONE },
                                            bottom: { style: docx.BorderStyle.NONE },
                                            left: { style: docx.BorderStyle.NONE },
                                            right: { style: docx.BorderStyle.NONE }
                                        }
                                    }),
                                    new docx.TableCell({
                                        children: [new docx.Paragraph({ text: "" })],
                                        width: {
                                            size: 15,
                                            type: docx.WidthType.PERCENTAGE
                                        },
                                        borders: {
                                            top: { style: docx.BorderStyle.NONE },
                                            bottom: { style: docx.BorderStyle.NONE },
                                            left: { style: docx.BorderStyle.NONE },
                                            right: { style: docx.BorderStyle.NONE }
                                        }
                                    })
                                ]
                            })
                        ],
                        width: {
                            size: 100,
                            type: docx.WidthType.PERCENTAGE
                        },
                        borders: {
                            top: { style: docx.BorderStyle.NONE },
                            bottom: { style: docx.BorderStyle.NONE },
                            left: { style: docx.BorderStyle.NONE },
                            right: { style: docx.BorderStyle.NONE },
                            insideHorizontal: { style: docx.BorderStyle.NONE },
                            insideVertical: { style: docx.BorderStyle.NONE }
                        }
                    }),
                    
                    // Border line
                    new docx.Paragraph({
                        text: "",
                        spacing: { after: 100 },
                        border: {
                            bottom: {
                                color: "000000",
                                space: 1,
                                size: 24,
                                style: docx.BorderStyle.SINGLE
                            }
                        }
                    }),
                    
                    // Title
                    new docx.Paragraph({
                        text: "BERITA ACARA STOCK OPNAME",
                        style: "title",
                        alignment: docx.AlignmentType.CENTER,
                        spacing: {after: 100}
                    }),
                    new docx.Paragraph({
                        text: "BARANG PAKAI HABIS",
                        style: "title",
                        alignment: docx.AlignmentType.CENTER,
                        spacing: {after: 300}
                    }),
                    
                    // Opening paragraph
                    new docx.Paragraph({
                        children: [
                            new docx.TextRun({text: 'Pada hari ini '}),
                            new docx.TextRun({text: hari, bold: true}),
                            new docx.TextRun({text: ' tanggal '}),
                            new docx.TextRun({text: tanggal, bold: true}),
                            new docx.TextRun({text: ' bulan '}),
                            new docx.TextRun({text: bulan, bold: true}),
                            new docx.TextRun({text: ' tahun '}),
                            new docx.TextRun({text: tahun, bold: true}),
                            new docx.TextRun({text: ', telah dilakukan Stock Opname Barang Pakai Habis (ATK dan Cetakan) terhadap:'})
                        ],
                        alignment: docx.AlignmentType.JUSTIFIED,
                        spacing: {after: 300, line: 360},
                        indent: {firstLine: docx.convertInchesToTwip(0.79)}
                    }),
                    
                    // Table
                    new docx.Table({
                        rows: tableRows,
                        width: {
                            size: 100,
                            type: docx.WidthType.PERCENTAGE
                        }
                    }),
                    
                    // Closing paragraph
                    new docx.Paragraph({
                        text: 'Demikian Berita Acara Stock Opname Barang Pakai Habis (ATK dan Cetakan) Satker Dewan dan Setjen ini dibuat untuk dipergunakan sebagaimana mestinya.',
                        alignment: docx.AlignmentType.JUSTIFIED,
                        spacing: {before: 300, after: 600, line: 360},
                        indent: {firstLine: docx.convertInchesToTwip(0.79)}
                    }),
                    
                    // Signature
                    new docx.Paragraph({
                        text: 'Kepala Kantor',
                        alignment: docx.AlignmentType.LEFT,
                        spacing: {after: 800},
                        indent: {left: docx.convertInchesToTwip(3.94)}
                    }),
                    new docx.Paragraph({
                        children: [
                            new docx.TextRun({
                                text: 'Elis Nurdian, S.I.Kom.',
                                underline: {},
                                bold: true
                            })
                        ],
                        alignment: docx.AlignmentType.LEFT,
                        spacing: {after: 50},
                        indent: {left: docx.convertInchesToTwip(3.94)}
                    }),
                    new docx.Paragraph({
                        text: 'NIP. 198203042009012002',
                        alignment: docx.AlignmentType.LEFT,
                        indent: {left: docx.convertInchesToTwip(3.94)}
                    })
                ]
            }]
        });
        
        docx.Packer.toBlob(doc).then(blob => {
            saveAs(blob, "Berita_Acara_Stock_Opname.docx");
        });
    }
    
    var table = $('#mauexport').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                text: '<i class="bi bi-file-earmark-word"></i> Export Word',
                className: 'dt-button',
                action: function(e, dt, node, config) {
                    exportToWord();
                }
            },
            {
                extend: 'excelHtml5',
                title: '',
                text: '<i class="bi bi-file-earmark-excel"></i> Export Excel',
                messageTop: function() {
                    var hari = $('#hari').val() || 'Rabu';
                    var tanggal = $('#tanggal').val() || 'Tiga Puluh Satu';
                    var bulan = $('#bulan').val() || 'Desember';
                    var tahun = $('#tahun').val() || 'Dua Ribu Dua Puluh Lima';
                    
                    return 'KANTOR DEWAN PERWAKILAN DAERAH\n' +
                           'REPUBLIK INDONESIA\n' +
                           'PROVINSI KALIMANTAN BARAT\n' +
                           'Jln. D.A Hadi Rq. Udi. A. Kota Pontianak\n' +
                           'Telp/Fax: (0561)739211 Email: kalbar@dpd.go.id\n' +
                           '================================================================\n\n' +
                           'BERITA ACARA STOCK OPNAME\n' +
                           'BARANG PAKAI HABIS\n\n' +
                           'Pada hari ini ' + hari + ' tanggal ' + tanggal + ' bulan ' + bulan + ' tahun ' + tahun + ',\n' +
                           'telah dilakukan Stock Opname Barang Pakai Habis (ATK dan Cetakan) terhadap:\n';
                },
                messageBottom: function() {
                    return '\n\nDemikian Berita Acara Stock Opname Barang Pakai Habis (ATK dan Cetakan)\n' +
                           'Satker Dewan dan Setjen ini dibuat untuk dipergunakan sebagaimana mestinya.\n\n\n\n' +
                           '                                                    Kepala Kantor\n\n\n\n\n' +
                           '                                            Elis Nurdian, S.I.Kom.\n' +
                           '                                          NIP. 198203042009012002';
                },
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                title: '',
                text: '<i class="bi bi-file-earmark-pdf"></i> Export PDF',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(doc) {
                    try {
                        var hari = $('#hari').val() || 'Rabu';
                        var tanggal = $('#tanggal').val() || 'Tiga Puluh Satu';
                        var bulan = $('#bulan').val() || 'Desember';
                        var tahun = $('#tahun').val() || 'Dua Ribu Dua Puluh Lima';
                        
                        // Ambil logo dari hidden input
                        var logoBase64Pdf = $('#logo-base64').val();
                        
                        doc.pageMargins = [75, 85, 70, 55];
                        doc.defaultStyle.fontSize = 9;
                        
                        // Simpan table original
                        var tableContent = doc.content[0];
                        
                        // Reset content
                        doc.content = [];
                        
                        // Logo dan Kop Surat
                        doc.content.push({
                            columns: [
                                {
                                    width: '15%',
                                    image: logoBase64Pdf,
                                    width: 60,
                                    alignment: 'center'
                                },
                                {
                                    width: '70%',
                                    stack: [
                                        {
                                            text: 'KANTOR DEWAN PERWAKILAN DAERAH',
                                            alignment: 'center',
                                            fontSize: 12,
                                            bold: true,
                                            margin: [0, 5, 0, 3]
                                        },
                                        {
                                            text: 'REPUBLIK INDONESIA',
                                            alignment: 'center',
                                            fontSize: 12,
                                            bold: true,
                                            margin: [0, 0, 0, 3]
                                        },
                                        {
                                            text: 'PROVINSI KALIMANTAN BARAT',
                                            alignment: 'center',
                                            fontSize: 12,
                                            bold: true,
                                            margin: [0, 0, 0, 3]
                                        },
                                        {
                                            text: 'Jln. D.A Hadi Rq. Udi. A. Kota Pontianak',
                                            alignment: 'center',
                                            fontSize: 9,
                                            margin: [0, 3, 0, 2]
                                        },
                                        {
                                            text: 'Telp/Fax: (0561)739211 Email: kalbar@dpd.go.id',
                                            alignment: 'center',
                                            fontSize: 9
                                        }
                                    ]
                                },
                                {
                                    width: '15%',
                                    text: ''
                                }
                            ],
                            margin: [0, 0, 0, 5]
                        });
                        
                        // Border line
                        doc.content.push({
                            canvas: [{
                                type: 'line',
                                x1: 0, y1: 0,
                                x2: 515, y2: 0,
                                lineWidth: 2
                            }],
                            margin: [0, 0, 0, 15]
                        });
                        
                        // Title
                        doc.content.push({
                            text: 'BERITA ACARA STOCK OPNAME',
                            alignment: 'center',
                            fontSize: 14,
                            bold: true,
                            margin: [0, 0, 0, 5]
                        });
                        
                        doc.content.push({
                            text: 'BARANG PAKAI HABIS',
                            alignment: 'center',
                            fontSize: 14,
                            bold: true,
                            margin: [0, 0, 0, 15]
                        });
                        
                        // Opening paragraph
                        doc.content.push({
                            text: [
                                {text: 'Pada hari ini '},
                                {text: hari, bold: true},
                                {text: ' tanggal '},
                                {text: tanggal, bold: true},
                                {text: ' bulan '},
                                {text: bulan, bold: true},
                                {text: ' tahun '},
                                {text: tahun, bold: true},
                                {text: ', telah dilakukan Stock Opname Barang Pakai Habis (ATK dan Cetakan) terhadap:'}
                            ],
                            alignment: 'justify',
                            fontSize: 10,
                            margin: [55, 0, 0, 15]
                        });
                        
                        // Table - restore dengan styling
                        if (tableContent && tableContent.table) {
                            tableContent.table.widths = ['6%', '36%', '10%', '12%', '12%', '12%', '12%'];
                            tableContent.layout = {
                                hLineWidth: function(i, node) { return 1; },
                                vLineWidth: function(i, node) { return 1; },
                                hLineColor: function(i, node) { return 'black'; },
                                vLineColor: function(i, node) { return 'black'; }
                            };
                            doc.content.push(tableContent);
                        }
                        
                        // Closing paragraph
                        doc.content.push({
                            text: 'Demikian Berita Acara Stock Opname Barang Pakai Habis (ATK dan Cetakan) Satker Dewan dan Setjen ini dibuat untuk dipergunakan sebagaimana mestinya.',
                            margin: [55, 20, 0, 30],
                            alignment: 'justify',
                            fontSize: 10
                        });
                        
                        // Signature section
                        doc.content.push({
                            columns: [
                                { width: '60%', text: '' },
                                {
                                    width: '40%',
                                    stack: [
                                        {
                                            text: 'Kepala Kantor',
                                            alignment: 'center',
                                            fontSize: 10,
                                            margin: [0, 0, 0, 50]
                                        },
                                        {
                                            text: 'Elis Nurdian, S.I.Kom.',
                                            alignment: 'center',
                                            fontSize: 10,
                                            decoration: 'underline',
                                            bold: true,
                                            margin: [0, 0, 0, 2]
                                        },
                                        {
                                            text: 'NIP. 198203042009012002',
                                            alignment: 'center',
                                            fontSize: 10
                                        }
                                    ]
                                }
                            ]
                        });
                        
                    } catch(error) {
                        console.error('Error dalam PDF customize:', error);
                        alert('Terjadi error saat membuat PDF: ' + error.message);
                    }
                }
            },
            {
                extend: 'print',
                title: '',
                text: '<i class="bi bi-printer"></i> Print',
                exportOptions: {
                    columns: ':visible'
                },
                customize: function(win) {
                    var hari = $('#hari').val() || 'Rabu';
                    var tanggal = $('#tanggal').val() || 'Tiga Puluh Satu';
                    var bulan = $('#bulan').val() || 'Desember';
                    var tahun = $('#tahun').val() || 'Dua Ribu Dua Puluh Lima';
                    
                    $(win.document.body).css({
                        'font-family': 'Times New Roman, Times, serif',
                        'font-size': '11pt',
                        'padding': '3cm 2.5cm 2cm 3cm'
                    });
                    
                    $(win.document.body).find('table')
                        .addClass('compact')
                        .css({
                            'font-size': '10pt',
                            'border-collapse': 'collapse',
                            'width': '100%',
                            'margin': '20px 0'
                        });
                    
                    $(win.document.body).find('table thead th')
                        .css({
                            'border': '1px solid black',
                            'background': '#f8f9fa',
                            'font-weight': 'bold',
                            'text-align': 'center',
                            'padding': '8px',
                            'font-size': '10pt'
                        });
                    
                    $(win.document.body).find('table tbody td')
                        .css({
                            'border': '1px solid black',
                            'padding': '6px',
                            'font-size': '10pt'
                        });
                    
                    // Center align specific columns
                    $(win.document.body).find('table tbody tr').each(function() {
                        $(this).find('td:eq(0)').css('text-align', 'center'); // No
                        $(this).find('td:eq(2)').css('text-align', 'center'); // Satuan
                        $(this).find('td:eq(3)').css('text-align', 'center'); // Stock Awal
                        $(this).find('td:eq(4)').css('text-align', 'center'); // Barang Masuk
                        $(this).find('td:eq(5)').css('text-align', 'center'); // Barang Keluar
                        $(this).find('td:eq(6)').css('text-align', 'center'); // Stock Akhir
                    });
                    
                    // Clear existing content and add formatted header
                    $(win.document.body).html(
                        '<div style="margin-bottom: 15px; border-bottom: 3px solid black; padding-bottom: 10px;">' +
                        '<table style="width: 100%; border: 0; border-collapse: collapse;">' +
                        '<tr>' +
                        '<td style="width: 100px; vertical-align: middle; text-align: center; border: 0; padding: 0;">' +
                        '<img src="' + logoBase64 + '" alt="Logo" style="width: 80px; height: auto;">' +
                        '</td>' +
                        '<td style="vertical-align: middle; text-align: center; border: 0; padding: 0;">' +
                        '<p style="margin: 0; font-weight: bold; font-size: 12pt; line-height: 1.3;">KANTOR DEWAN PERWAKILAN DAERAH</p>' +
                        '<p style="margin: 0; font-weight: bold; font-size: 12pt; line-height: 1.3;">REPUBLIK INDONESIA</p>' +
                        '<p style="margin: 0; font-weight: bold; font-size: 12pt; line-height: 1.3;">PROVINSI KALIMANTAN BARAT</p>' +
                        '<p style="margin: 5px 0 0 0; font-size: 10pt;">Jln. D.A Hadi Rq. Udi. A. Kota Pontianak</p>' +
                        '<p style="margin: 0; font-size: 10pt;">Telp/Fax: (0561)739211 Email: kalbar@dpd.go.id</p>' +
                        '</td>' +
                        '<td style="width: 100px; border: 0; padding: 0;"></td>' +
                        '</tr>' +
                        '</table>' +
                        '</div>' +
                        '<div style="margin: 30px 0; text-align: center;">' +
                        '<h2 style="margin: 0 0 5px 0; font-weight: bold; font-size: 16pt;">BERITA ACARA STOCK OPNAME</h2>' +
                        '<h2 style="margin: 0 0 20px 0; font-weight: bold; font-size: 16pt;">BARANG PAKAI HABIS</h2>' +
                        '</div>' +
                        '<p style="text-align: justify; margin: 0 0 20px 0; line-height: 1.8; text-indent: 2cm; font-size: 11pt;">' +
                        'Pada hari ini <strong>' + hari + '</strong> tanggal <strong>' + tanggal + '</strong> bulan <strong>' + bulan + '</strong> tahun <strong>' + tahun + '</strong>, telah dilakukan Stock Opname Barang Pakai Habis (ATK dan Cetakan) terhadap:' +
                        '</p>' +
                        $(win.document.body).find('table')[0].outerHTML +
                        '<p style="margin: 30px 0 40px 0; text-align: justify; line-height: 1.8; text-indent: 2cm; font-size: 11pt;">' +
                        'Demikian Berita Acara Stock Opname Barang Pakai Habis (ATK dan Cetakan) Satker Dewan dan Setjen ini dibuat untuk dipergunakan sebagaimana mestinya.' +
                        '</p>' +
                        '<div style="margin-top: 50px; display: flex; justify-content: flex-end;">' +
                        '<div style="text-align: center; min-width: 250px;">' +
                        '<p style="margin-bottom: 5px; font-size: 11pt;">Kepala Kantor</p>' +
                        '<div style="height: 80px;"></div>' +
                        '<p style="margin: 5px 0; font-size: 11pt; text-decoration: underline; font-weight: bold;">Elis Nurdian, S.I.Kom.</p>' +
                        '<p style="margin: 2px 0 0 0; font-size: 11pt;">NIP. 198203042009012002</p>' +
                        '</div>' +
                        '</div>'
                    );
                    
                    // Add print styles
                    $(win.document.head).append(
                        '<style>' +
                        '@page { size: A4; margin: 0; }' +
                        'body { margin: 0; padding: 3cm 2.5cm 2cm 3cm; }' +
                        '@media print {' +
                        '  body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }' +
                        '}' +
                        '</style>'
                    );
                }
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.10.20/i18n/Indonesian.json'
        },
        pageLength: 25,
        paging: false,
        searching: false,
        info: false
    });
    
    // Move buttons to container
    setTimeout(function() {
        $('.dt-buttons').prependTo('#export-buttons-container');
    }, 100);
});
</script>

</body>
</html>