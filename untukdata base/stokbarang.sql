-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 15 Agu 2023 pada 17.05
-- Versi server: 10.4.28-MariaDB
-- Versi PHP: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `stokbarang`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `keluar`
--

CREATE TABLE `keluar` (
  `idkeluar` int(11) NOT NULL,
  `idbarang` int(11) NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `penerima` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL,
  `totalharga` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `keluar`
--

INSERT INTO `keluar` (`idkeluar`, `idbarang`, `tanggal`, `penerima`, `qty`, `totalharga`) VALUES
(1, 9, '2023-08-08 13:56:06', 'pembeli', 100, NULL),
(2, 9, '2023-08-09 06:36:58', 'pembeli', 100, NULL),
(3, 14, '2023-08-09 10:39:32', 'pembeli', 100, NULL),
(4, 16, '2023-08-09 10:41:53', 'pembeli', 100, NULL),
(5, 17, '2023-08-09 10:49:38', 'pembeli', 100, NULL),
(6, 18, '2023-08-10 00:18:44', '', 100, NULL),
(7, 19, '2023-08-10 00:20:23', '', 100, NULL),
(8, 25, '2023-08-10 01:12:27', 'awal', 50, NULL),
(13, 32, '2023-08-12 13:39:07', 'iwan', 100, NULL),
(14, 34, '2023-08-12 13:45:03', 'bapak kosasi', 69, NULL),
(15, 33, '2023-08-15 13:12:02', 'Pak Senin', 25, NULL),
(16, 33, '2023-08-15 13:13:59', 'Pak Selasa', 25, NULL),
(17, 32, '2023-08-15 13:14:17', 'Pak Rabu', 25, NULL),
(26, 39, '2023-08-15 14:55:24', 'iwan', 2, 3000000),
(27, 39, '2023-08-15 14:56:31', 'Pak Minggu', 4, 6000000),
(28, 45, '2023-08-15 15:01:19', 'iwan', 2, 3000000);

-- --------------------------------------------------------

--
-- Struktur dari tabel `login`
--

CREATE TABLE `login` (
  `iduser` int(11) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `login`
--

INSERT INTO `login` (`iduser`, `email`, `password`) VALUES
(1, 'toko@gmail.com', '12345');

-- --------------------------------------------------------

--
-- Struktur dari tabel `masuk`
--

CREATE TABLE `masuk` (
  `idmasuk` int(11) NOT NULL,
  `idbarang` int(11) NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `keterangan` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `masuk`
--

INSERT INTO `masuk` (`idmasuk`, `idbarang`, `tanggal`, `keterangan`, `qty`) VALUES
(10, 9, '2023-08-09 06:36:45', 'awal', 15),
(11, 12, '2023-08-09 10:35:30', 'awal', 100),
(12, 13, '2023-08-09 10:37:07', 'awal', 15),
(13, 15, '2023-08-09 10:40:29', '', 500),
(14, 16, '2023-08-09 10:41:38', 'awal', 500),
(15, 17, '2023-08-09 10:49:25', 'awal', 300),
(16, 18, '2023-08-10 00:18:34', 'awal', 300),
(19, 20, '2023-08-10 00:40:54', 'awal', 200),
(25, 25, '2023-08-11 04:01:10', 'pembeli', 30),
(26, 26, '2023-08-11 04:02:29', 'awal', 200),
(27, 27, '2023-08-11 04:03:51', 'awal', 100),
(30, 30, '2023-08-11 04:06:50', 'awal', 50),
(31, 31, '2023-08-11 04:07:48', 'awal', 100),
(32, 32, '2023-08-11 04:09:06', 'awal', 100),
(33, 34, '2023-08-12 13:44:46', 'iwan', 25),
(34, 33, '2023-08-15 13:11:47', 'Pak Minggu', 25),
(35, 38, '2023-08-15 13:17:41', 'Diamond Store', 100),
(36, 39, '2023-08-15 13:17:50', 'Diamond Store', 100),
(37, 45, '2023-08-15 15:01:03', 'Diamond Store', 25);

-- --------------------------------------------------------

--
-- Struktur dari tabel `stok`
--

CREATE TABLE `stok` (
  `idbarang` int(11) NOT NULL,
  `namabarang` varchar(50) NOT NULL,
  `deskripsi` varchar(50) NOT NULL,
  `stock` int(11) NOT NULL,
  `image` varchar(99) DEFAULT NULL,
  `hargasatuan` int(99) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `stok`
--

INSERT INTO `stok` (`idbarang`, `namabarang`, `deskripsi`, `stock`, `image`, `hargasatuan`) VALUES
(45, 'Smartphone', 'Hp Pintar', 123, '98e88f0f9ab7e85b0eb193f12ca1636f.jpg', 1500000);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `keluar`
--
ALTER TABLE `keluar`
  ADD PRIMARY KEY (`idkeluar`);

--
-- Indeks untuk tabel `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`iduser`);

--
-- Indeks untuk tabel `masuk`
--
ALTER TABLE `masuk`
  ADD PRIMARY KEY (`idmasuk`);

--
-- Indeks untuk tabel `stok`
--
ALTER TABLE `stok`
  ADD PRIMARY KEY (`idbarang`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `keluar`
--
ALTER TABLE `keluar`
  MODIFY `idkeluar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `login`
--
ALTER TABLE `login`
  MODIFY `iduser` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `masuk`
--
ALTER TABLE `masuk`
  MODIFY `idmasuk` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT untuk tabel `stok`
--
ALTER TABLE `stok`
  MODIFY `idbarang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
