<?php
 require 'function.php';
 require 'cek.php';

 //dapetin ID barang yang dipassing dihalaman sebelumnya
 $idbarang = $_GET['id'];
 //Get informasi barang berdasarkan database
 $get = mysqli_query($conn, "select * from stok where idbarang='$idbarang'");
 $fetch = mysqli_fetch_assoc($get);
 //set variable
 $namabarang = $fetch['namabarang'];
 $deskripsi = $fetch['deskripsi'];
 $stock = $fetch['stock'];
 $hargasatuan = $fetch['hargasatuan'];

 //cek ada gambar atau tidak
 $gambar = $fetch['image'];
 if($gambar==null){
    $img = 'No Photo';
 }else{
     $img = '<img src="images/'.$gambar.'" class="zoomable">';
 }

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Stok - Detail Barang</title>
        <link href="css/styles.css" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css" rel="stylesheet" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js" crossorigin="anonymous"></script>
        <style>
            .zoomable{
                width: 200px;
                height: 200px;
            }
            .zoomable:hover{
                transform: scale(1.5);
                transition: 0.3s ease;
            }
        </style>

    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <a class="navbar-brand" href="index.php">TOKO DIAMOND STORE</a>
            <button class="btn btn-link btn-sm order-1 order-lg-0" id="sidebarToggle" href="#"><i class="fas fa-bars"></i></button>
            <!-- Navbar Search-->
            <form class="d-none d-md-inline-block form-inline ml-auto mr-0 mr-md-3 my-2 my-md-0">
                
            </form>
            <!-- Navbar-->
            <ul class="navbar-nav ml-auto ml-md-0">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                        
                        <a class="dropdown-item" href="logout.php">Logout</a>
                    </div>
                </li>
            </ul>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Diamond Store</div>
                            <a class="nav-link" href="dashboard.php">
                                <div class="sb-nav-link-icon"><i class="bi bi-grid-1x2"></i></div>
                                Dashboard
                            </a>
                            <a class="nav-link" href="index.php">
                                <div class="sb-nav-link-icon"><i class="bi bi-inboxes-fill"></i></div>
                                Stock Barang
                            </a>
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseLayouts" aria-expanded="false" aria-controls="collapseLayouts">
                                <div class="sb-nav-link-icon"><i class="fas fa-columns"></i></div>
                                Pemrosesan
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-angle-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseLayouts" aria-labelledby="headingOne" data-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="masuk.php">
                                    <div class="sb-nav-link-icon"><i class="bi bi-cloud-arrow-down-fill"></i></div>
                                    Barang Masuk
                                    </a>
                                    <a class="nav-link" href="keluar.php">
                                    <div class="sb-nav-link-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                                    Barang Keluar
                                    </a>
                                </nav>
                            </div>
                            <a class="nav-link" href="logout.php">
                                Logout
                            </a>
                            
                        </div>

                    </div>
                </nav>
            </div>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid">
                        <h1 class="mt-4">Detail Barang Toko Diamond Store</h1>


                        <div class="card mb-4 mt-4">
                            <div class="card-header">
                                <h2><?=$namabarang;?></h2>
                                <?=$img;?>
                            </div>
                            <div class="card-body">

                            <div class="row">
                                <div class="col-md-3">Deskripsi</div>
                                <div class="col-md-9">: <?=$deskripsi;?></div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">Stock</div>
                                <div class="col-md-9">: <?=$stock;?></div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">Harga Satuan</div>
                                <div class="col-md-9">: Rp.<?=number_format($hargasatuan, 2);?></div>
                            </div>
                            <br><br><hr>

                                <h3>Barang Masuk</h3>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="barangmasuk" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal</th>
                                                <th>Keterangan</th>
                                                <th>Jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <?php
                                               $ambildatamasuk = mysqli_query($conn,"select * from masuk where idbarang='$idbarang'");
                                               $i = 1;
                                               while($fetch=mysqli_fetch_array($ambildatamasuk)){
                                                   $tanggal = $fetch['tanggal'];
                                                   $keterangan = $fetch['keterangan'];
                                                   $quantity = $fetch['qty'];

                                            ?>
                                            <tr>
                                                <td><?=$i++;?></td>
                                                <td><?=$tanggal;?></td>
                                                <td><?=$keterangan;?></td>
                                                <td><?=$quantity;?></td>
                                            </tr>

                                            <?php
                                                };
                                            ?>

                                        </tbody>
                                    </table>
                                </div>

                                <br><br>
                                
                                <h3>Barang Keluar</h3>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="barangkeluar" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal</th>
                                                <th>Penerima</th>
                                                <th>Jumlah</th>
                                                <th>Total Harga (Rp.)</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <?php
                                               $ambildatakeluar = mysqli_query($conn,"select * from keluar where idbarang='$idbarang'");
                                               $i = 1;
                                               while($fetch=mysqli_fetch_array($ambildatakeluar)){
                                                   $tanggal = $fetch['tanggal'];
                                                   $penerima = $fetch['penerima'];
                                                   $quantity = $fetch['qty'];
                                                   $totalharga = $fetch['totalharga'];

                                            ?>
                                            <tr>
                                                <td><?=$i++;?></td>
                                                <td><?=$tanggal;?></td>
                                                <td><?=$penerima;?></td>
                                                <td><?=$quantity;?></td>
                                                <td><?=number_format($totalharga, 2);?></td>
                                            </tr>

                                            <?php
                                                };
                                            ?>

                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; KP SISKOM 2023</div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
        <script src="assets/demo/chart-area-demo.js"></script>
        <script src="assets/demo/chart-bar-demo.js"></script>
        <script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>
        <script src="assets/demo/datatables-demo.js"></script>
    </body>
      <!-- The Modal -->
    <div class="modal fade" id="myModal">
        <div class="modal-dialog">
            <div class="modal-content">
      
            <!-- Modal Header -->
            <div class="modal-header">
            <h4 class="modal-title">Tambah Barang</h4>
            <button type="button" class="clo2se" data-dismiss="modal">&times;</button>
            </div>
        
            <!-- Modal body -->
            <form method="post" enctype="multipart/form-data"> 
            <div class="modal-body">
              <input type="text" name="namabarang" placeholder="Nama Barang" class="form-control" required>
              <br>
              <input type="text" name="deskripsi" placeholder="Deskripsi Barang" class="form-control" required>
              <br>
              <input type="number" name="stock" placeholder="Stock" class="form-control" required>
              <br>
              <input type="file" name="file" class="form-control">
              <br>
              <button type="submit" class="btn btn-primary" name="addnewbarang">Submit</button>
            </div>
            </form>
        
        </div>
        </div>
    </div>
</html>
