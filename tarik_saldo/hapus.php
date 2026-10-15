<?php
require "index.php";
require_role(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("index.php");
}
csrf_check();

$id = (int) ($_POST["id"] ?? 0);
$t  = q_one($koneksi, "SELECT status FROM tarik_saldo WHERE id_tarik = ?", "i", $id);

if (!$t) {
    flash("Data penarikan tidak ditemukan.", "error");
} elseif ($t["status"] === "disetujui") {
    // Riwayat uang keluar dipertahankan sebagai catatan
    flash("Penarikan yang sudah disetujui tidak boleh dihapus.", "error");
} else {
    q_exec($koneksi, "DELETE FROM tarik_saldo WHERE id_tarik = ?", "i", $id);
    flash("Data penarikan dihapus.");
}

redirect("index.php");
