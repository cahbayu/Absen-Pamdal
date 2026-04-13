-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 23, 2026 at 04:27 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stokbarang2`
--

-- --------------------------------------------------------

--
-- Table structure for table `keluar`
--

CREATE TABLE `keluar` (
  `idkeluar` int NOT NULL,
  `idbarang` int NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `penerima` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `totalharga` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `keluar`
--

INSERT INTO `keluar` (`idkeluar`, `idbarang`, `tanggal`, `penerima`, `qty`, `totalharga`) VALUES

-- --------------------------------------------------------

--
-- Table structure for table `keluar_non_atk`
--

CREATE TABLE `keluar_non_atk` (
  `idkeluar` int NOT NULL,
  `idbarang` int NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `penerima` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `totalharga` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `keluar_non_atk`
--

INSERT INTO `keluar_non_atk` (`idkeluar`, `idbarang`, `tanggal`, `penerima`, `qty`, `totalharga`) VALUES

-- --------------------------------------------------------

--
-- Table structure for table `masuk`
--

CREATE TABLE `masuk` (
  `idmasuk` int NOT NULL,
  `idbarang` int NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `keterangan` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `masuk`
--

INSERT INTO `masuk` (`idmasuk`, `idbarang`, `tanggal`, `keterangan`, `qty`) VALUES


-- --------------------------------------------------------

--
-- Table structure for table `masuk_non_atk`
--

CREATE TABLE `masuk_non_atk` (
  `idmasuk` int NOT NULL,
  `idbarang` int NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `keterangan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `masuk_non_atk`
--

INSERT INTO `masuk_non_atk` (`idmasuk`, `idbarang`, `tanggal`, `keterangan`, `qty`) VALUES

-- --------------------------------------------------------

--
-- Table structure for table `stok`
--

CREATE TABLE `stok` (
  `idbarang` int NOT NULL,
  `namabarang` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `stock` int NOT NULL,
  `satuan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `penyimpanan` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_input` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stok`
--

INSERT INTO `stok` (`idbarang`, `namabarang`, `deskripsi`, `stock`, `satuan`, `penyimpanan`, `tanggal_input`) VALUES

-- --------------------------------------------------------

--
-- Table structure for table `stok_non_atk`
--

CREATE TABLE `stok_non_atk` (
  `idbarang` int NOT NULL,
  `namabarang` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stock` int NOT NULL,
  `satuan` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `penyimpanan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_input` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stok_non_atk`
--

INSERT INTO `stok_non_atk` (`idbarang`, `namabarang`, `deskripsi`, `stock`, `satuan`, `penyimpanan`, `tanggal_input`) VALUES


-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`, `last_login`, `status`) VALUES
(3, 'cahyo', 'cahyoawal43@gmail.com', '$2y$10$w0DxJfcg5/lWBrdV4EJ6YuAs9Ym3hsdCzbYoJOTq40yVStxxtCxLu', 'super_admin', '2026-01-13 04:41:06', '2026-02-23 04:25:59', '2026-02-23 04:25:59', 'active'),
(4, 'admin', 'admin@gmail.com', '$2y$10$doLdG6SBMtcbiYdkjmseX.nz4uoi2mMvHbi9bVG.cKmWTeyGOsTZK', 'admin', '2026-01-15 06:20:45', '2026-02-23 03:45:02', '2026-02-23 03:45:02', 'active'),
(8, 'user', 'user@gmail.com', '$2y$10$Gv.9oTHLifb2RISa4ZG.kOYRVRNuq6zngVAuO.rNfpLj.Xbj1esT2', 'user', '2026-02-03 03:01:30', '2026-02-23 03:50:29', '2026-02-23 03:50:29', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `keluar`
--
ALTER TABLE `keluar`
  ADD PRIMARY KEY (`idkeluar`);

--
-- Indexes for table `keluar_non_atk`
--
ALTER TABLE `keluar_non_atk`
  ADD PRIMARY KEY (`idkeluar`),
  ADD KEY `idbarang` (`idbarang`);

--
-- Indexes for table `masuk`
--
ALTER TABLE `masuk`
  ADD PRIMARY KEY (`idmasuk`);

--
-- Indexes for table `masuk_non_atk`
--
ALTER TABLE `masuk_non_atk`
  ADD PRIMARY KEY (`idmasuk`),
  ADD KEY `idbarang` (`idbarang`);

--
-- Indexes for table `stok`
--
ALTER TABLE `stok`
  ADD PRIMARY KEY (`idbarang`);

--
-- Indexes for table `stok_non_atk`
--
ALTER TABLE `stok_non_atk`
  ADD PRIMARY KEY (`idbarang`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `keluar`
--
ALTER TABLE `keluar`
  MODIFY `idkeluar` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `keluar_non_atk`
--
ALTER TABLE `keluar_non_atk`
  MODIFY `idkeluar` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `masuk`
--
ALTER TABLE `masuk`
  MODIFY `idmasuk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `masuk_non_atk`
--
ALTER TABLE `masuk_non_atk`
  MODIFY `idmasuk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stok`
--
ALTER TABLE `stok`
  MODIFY `idbarang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stok_non_atk`
--
ALTER TABLE `stok_non_atk`
  MODIFY `idbarang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `keluar_non_atk`
--
ALTER TABLE `keluar_non_atk`
  ADD CONSTRAINT `keluar_non_atk_ibfk_1` FOREIGN KEY (`idbarang`) REFERENCES `stok_non_atk` (`idbarang`) ON DELETE CASCADE;

--
-- Constraints for table `masuk_non_atk`
--
ALTER TABLE `masuk_non_atk`
  ADD CONSTRAINT `masuk_non_atk_ibfk_1` FOREIGN KEY (`idbarang`) REFERENCES `stok_non_atk` (`idbarang`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
