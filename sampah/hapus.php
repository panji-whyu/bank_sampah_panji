<?php
require "index.php";
require_role(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("index.php");
}
csrf_check();

$id = (int) ($_POST["id"] ?? 0);

// Jenis sampah yang sudah punya riwayat transaksi tidak boleh dihapus (riwayat ikut hilang)
$pakai = q_one($koneksi, "SELECT COUNT(*) AS n FROM transaksi WHERE id_sampah = ?", "i", $id);

if ((int) $pakai["n"] > 0) {
    flash("Tidak bisa dihapus: jenis sampah ini sudah dipakai di " . (int) $pakai["n"] . " transaksi.", "error");
} else {
    $n = q_exec($koneksi, "DELETE FROM sampah WHERE Id_sampah = ?", "i", $id);
    if ($n > 0) {
        flash("Data sampah berhasil dihapus.");
    } else {
        flash("Data sampah tidak ditemukan.", "error");
    }
}

redirect("index.php");
