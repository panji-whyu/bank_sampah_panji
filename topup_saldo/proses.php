<?php
require "index.php";
require_role(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("index.php");
}
csrf_check();

$id   = (int) ($_POST["id"] ?? 0);
$aksi = $_POST["aksi"] ?? "";

if (!in_array($aksi, ["setuju", "tolak"], true)) {
    flash("Aksi tidak valid.", "error");
    redirect("index.php");
}

try {
    mysqli_begin_transaction($koneksi);

    $t = q_one($koneksi, "SELECT * FROM topup_saldo WHERE id_topup = ? FOR UPDATE", "i", $id);
    if (!$t) {
        throw new RuntimeException("Data top up tidak ditemukan.");
    }
    if ($t["status"] !== "pending") {
        throw new RuntimeException("Top up ini sudah diproses sebelumnya.");
    }

    if ($aksi === "setuju") {
        q_exec($koneksi, "UPDATE users SET saldo = saldo + ? WHERE id_user = ?", "di", (float) $t["nominal"], (int) $t["id_user"]);
        q_exec($koneksi, "UPDATE topup_saldo SET status = 'disetujui' WHERE id_topup = ?", "i", $id);
        $pesan = "Top up disetujui dan saldo pengepul sudah ditambah.";
    } else {
        q_exec($koneksi, "UPDATE topup_saldo SET status = 'ditolak' WHERE id_topup = ?", "i", $id);
        $pesan = "Top up ditolak.";
    }

    mysqli_commit($koneksi);
    flash($pesan);
} catch (RuntimeException $ex) {
    mysqli_rollback($koneksi);
    flash($ex->getMessage(), "error");
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($koneksi);
    flash("Terjadi kesalahan database.", "error");
}

redirect("index.php");
