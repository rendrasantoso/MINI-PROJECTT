-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 03 Des 2025 pada 06.39
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smekda_jersey`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT 'admin@smekda.com',
  `nama_lengkap` varchar(100) DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kartu_pelajar` varchar(255) DEFAULT NULL,
  `status_verifikasi` enum('pending','verified','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `email`, `nama_lengkap`, `created_at`, `kartu_pelajar`, `status_verifikasi`) VALUES
(1, 'admin', 'admin123', 'admin@smekda.com', 'Administrator', '2025-10-07 06:39:42', NULL, 'pending');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_match`
--

CREATE TABLE `jadwal_match` (
  `id` int(11) NOT NULL,
  `pertandingan` varchar(200) NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `lokasi` varchar(200) NOT NULL,
  `status` enum('upcoming','ongoing','finished','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_match`
--

INSERT INTO `jadwal_match` (`id`, `pertandingan`, `tanggal`, `waktu`, `lokasi`, `status`, `created_at`) VALUES
(10, 'smekda vs stemba', '2025-12-22', '19:12:00', 'GOR UNESA', 'upcoming', '2025-12-01 09:01:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `merchandise`
--

CREATE TABLE `merchandise` (
  `id` int(11) NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `terjual` int(11) DEFAULT 0,
  `deskripsi` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `status` enum('tersedia','habis','pre-order') DEFAULT 'tersedia',
  `gambar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `merchandise`
--

INSERT INTO `merchandise` (`id`, `nama_produk`, `kategori`, `harga`, `stok`, `terjual`, `deskripsi`, `icon`, `status`, `gambar`, `created_at`) VALUES
(1, 'Dresscode Smekda', 'Jersey', 90000.00, 128, 45, 'Desain autentik dengan kain yang dapat bernapas dan logo bordir, dijamin josjiss.', 'fa-tshirt', 'tersedia', NULL, '2025-10-07 06:39:43'),
(2, 'Ganci Smekda', 'Aksesoris', 10000.00, 295, 120, 'Bahan anti hujan dan anti badai. Aksesoris wajib untuk fans sejati.', 'fa-key', 'tersedia', NULL, '2025-10-07 06:39:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemesanan`
--

CREATE TABLE `pemesanan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `alamat` text NOT NULL,
  `produk` varchar(255) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `tanggal_pesan` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pemesanan_tiket`
--

CREATE TABLE `pemesanan_tiket` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tiket_id` int(11) NOT NULL,
  `jumlah_tiket` int(11) NOT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','paid','cancelled') DEFAULT 'pending',
  `tanggal_pesan` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pemesanan_tiket`
--

INSERT INTO `pemesanan_tiket` (`id`, `nama`, `no_hp`, `email`, `tiket_id`, `jumlah_tiket`, `total_harga`, `status`, `tanggal_pesan`) VALUES
(3, 'rendra', '0832724973', 'rendradwisantoso03@gmail.com', 8, 1, -10.00, 'pending', '2025-12-01 09:31:28'),
(4, 'raffah', '02933j', 'rendradwisantoso03@gmail.com', 8, 1, -10.00, 'pending', '2025-12-01 09:40:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan`
--

CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL,
  `order_id_midtrans` varchar(100) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `no_hp` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text NOT NULL,
  `produk` varchar(100) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `foto_kartu_pelajar` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `bukti_transfer` varchar(255) DEFAULT NULL,
  `status_verifikasi` enum('pending','approved','rejected') DEFAULT 'pending',
  `status_pembayaran` enum('pending','lunas','expired') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `payment_verified_at` datetime DEFAULT NULL,
  `payment_verified_by` int(11) DEFAULT NULL,
  `bank_tujuan` varchar(50) DEFAULT NULL,
  `batas_pembayaran` datetime DEFAULT NULL,
  `keterangan_admin` text DEFAULT NULL,
  `status` enum('pending','paid','failed','expired','proses','selesai','dibatalkan') DEFAULT 'pending',
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `snap_token` text DEFAULT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `transaction_status` varchar(50) DEFAULT NULL,
  `transaction_time` datetime DEFAULT NULL,
  `settlement_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pesanan`
--

INSERT INTO `pesanan` (`id`, `order_id_midtrans`, `nama`, `no_hp`, `email`, `alamat`, `produk`, `jumlah`, `total`, `foto_kartu_pelajar`, `catatan`, `bukti_pembayaran`, `bukti_transfer`, `status_verifikasi`, `status_pembayaran`, `verified_by`, `verified_at`, `payment_verified_at`, `payment_verified_by`, `bank_tujuan`, `batas_pembayaran`, `keterangan_admin`, `status`, `tanggal`, `snap_token`, `payment_type`, `transaction_status`, `transaction_time`, `settlement_time`) VALUES
(33, 'SMEKDA-1764740044-6427', 'rendra', '08327249730', NULL, '', 'Dresscode Smekda', 1, 90000.00, 'kartu_1764740044_692fcbcc44791.jpg', 'yyyyyyyyyyyy', NULL, NULL, 'pending', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', '2025-12-03 05:34:04', 'ab5079fc-3c30-4fbd-842b-5351e0806a22', NULL, 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tiket`
--

CREATE TABLE `tiket` (
  `id` int(11) NOT NULL,
  `jenis_tiket` varchar(50) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `harga` decimal(10,2) NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `terjual` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tiket`
--

INSERT INTO `tiket` (`id`, `jenis_tiket`, `match_id`, `harga`, `stok`, `terjual`, `created_at`) VALUES
(8, 'smekda vs stemba', 10, -10.00, 12, 2, '2025-12-01 09:01:21');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `jadwal_match`
--
ALTER TABLE `jadwal_match`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `merchandise`
--
ALTER TABLE `merchandise`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pemesanan`
--
ALTER TABLE `pemesanan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pemesanan_tiket`
--
ALTER TABLE `pemesanan_tiket`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`);

--
-- Indeks untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indeks untuk tabel `tiket`
--
ALTER TABLE `tiket`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `jadwal_match`
--
ALTER TABLE `jadwal_match`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `merchandise`
--
ALTER TABLE `merchandise`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pemesanan`
--
ALTER TABLE `pemesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pemesanan_tiket`
--
ALTER TABLE `pemesanan_tiket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT untuk tabel `tiket`
--
ALTER TABLE `tiket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `pemesanan_tiket`
--
ALTER TABLE `pemesanan_tiket`
  ADD CONSTRAINT `pemesanan_tiket_ibfk_1` FOREIGN KEY (`tiket_id`) REFERENCES `tiket` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `pesanan_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tiket`
--
ALTER TABLE `tiket`
  ADD CONSTRAINT `tiket_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `jadwal_match` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
