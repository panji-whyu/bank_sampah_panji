-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Sep 2026 pada 04.31
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bank_sampah_panji`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `sampah`
--

CREATE TABLE `sampah` (
  `Id_sampah` int(11) NOT NULL,
  `Jenis_sampah` varchar(100) NOT NULL,
  `Harga_per_kg` decimal(15,2) NOT NULL,
  `stok_kg` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sampah`
--

INSERT INTO `sampah` (`Id_sampah`, `Jenis_sampah`, `Harga_per_kg`, `stok_kg`) VALUES
(2, 'Kertas', 1500.00, 5002.00),
(8, 'botol kaca', 500.00, 28.00),
(9, 'plastik', 80000.00, 9.00),
(10, 'kertas lipat', 45000.00, 5.00),
(11, 'air', 10000000.00, 50.00),
(12, 'plastik', 4000.00, 10.00);

-- --------------------------------------------------------

--
-- Struktur dari tabel `tarik_saldo`
--

CREATE TABLE `tarik_saldo` (
  `id_tarik` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tarik_saldo`
--

INSERT INTO `tarik_saldo` (`id_tarik`, `id_user`, `nominal`, `tanggal`, `status`) VALUES
(4, 1, 4600.00, '2026-09-10 09:37:55', 'disetujui'),
(5, 1, 100000.00, '2026-09-10 09:38:24', 'disetujui'),
(6, 1, 100.00, '2026-09-17 09:14:39', 'disetujui');

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_sampah` int(11) NOT NULL,
  `tipe` enum('setor','tarik') NOT NULL DEFAULT 'setor',
  `berat_kg` decimal(10,2) NOT NULL,
  `total_rp` decimal(15,2) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id_transaksi`, `id_user`, `id_sampah`, `tipe`, `berat_kg`, `total_rp`, `tanggal`) VALUES
(3, 1, 9, 'setor', 4.00, 320000.00, '2026-09-10 09:32:17'),
(5, 1, 10, 'setor', 4.00, 180000.00, '2026-09-10 09:35:46'),
(6, 1, 10, 'setor', 1.00, 45000.00, '2026-09-10 09:36:04'),
(7, 1, 10, 'tarik', 4.00, 180000.00, '2026-09-10 09:36:33'),
(8, 1, 8, 'tarik', 5.00, 2500.00, '2026-09-10 09:36:54'),
(9, 2, 8, 'setor', 30.00, 15000.00, '2026-09-10 11:20:45'),
(10, 3, 2, 'setor', 5000.00, 7500000.00, '2026-09-17 09:29:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `saldo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `role` enum('admin','nasabah','siswa','guru','pengepul') NOT NULL DEFAULT 'siswa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `saldo`, `role`) VALUES
(1, 'Panji', 'panji', 'panji', 440300.00, 'admin'),
(2, 'Budi Santoso', 'budi', 'budi123', 18000.00, 'nasabah'),
(3, 'Siswa', 'siswa', 'siswa123', 7500000.00, 'siswa'),
(4, 'Guru', 'guru', 'guru123', 0.00, 'guru'),
(5, 'Pengepul', 'pengepul', 'pengepul123', 0.00, 'pengepul');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `sampah`
--
ALTER TABLE `sampah`
  ADD PRIMARY KEY (`Id_sampah`);

--
-- Indeks untuk tabel `tarik_saldo`
--
ALTER TABLE `tarik_saldo`
  ADD PRIMARY KEY (`id_tarik`),
  ADD KEY `fk_tarik_user` (`id_user`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `fk_transaksi_user` (`id_user`),
  ADD KEY `fk_transaksi_sampah` (`id_sampah`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `sampah`
--
ALTER TABLE `sampah`
  MODIFY `Id_sampah` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `tarik_saldo`
--
ALTER TABLE `tarik_saldo`
  MODIFY `id_tarik` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id_transaksi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `tarik_saldo`
--
ALTER TABLE `tarik_saldo`
  ADD CONSTRAINT `fk_tarik_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_sampah` FOREIGN KEY (`id_sampah`) REFERENCES `sampah` (`Id_sampah`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
