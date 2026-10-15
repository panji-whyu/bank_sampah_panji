<?php
require "index.php";
require_role(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("index.php");
}
csrf_check();

$id = (int) ($_POST["id"] ?? 0);
$t  = q_one($koneksi, "SELECT status FROM topup_saldo WHERE id_topup = ?", "i", $id);

if (!$t) {
    flash("Data top up tidak ditemukan.", "error");
} elseif ($t["status"] === "disetujui") {
    flash("Top up yang sudah disetujui tidak boleh dihapus.", "error");
} else {
    q_exec($koneksi, "DELETE FROM topup_saldo WHERE id_topup = ?", "i", $id);
    flash("Data top up dihapus.");
}

redirect("index.php");
