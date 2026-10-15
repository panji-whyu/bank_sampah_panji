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

    $t = q_one($koneksi, "SELECT * FROM tarik_saldo WHERE id_tarik = ? FOR UPDATE", "i", $id);
    if (!$t) {
        throw new RuntimeException("Data penarikan tidak ditemukan.");
    }
    if ($t["status"] !== "pending") {
        throw new RuntimeException("Penarikan ini sudah diproses sebelumnya.");
    }

    if ($aksi === "setuju") {
        $u = q_one($koneksi, "SELECT saldo FROM users WHERE id_user = ? FOR UPDATE", "i", (int) $t["id_user"]);
        if (!$u || (float) $u["saldo"] < (float) $t["nominal"]) {
            throw new RuntimeException("Tidak bisa disetujui: saldo nasabah tidak cukup.");
        }
        q_exec($koneksi, "UPDATE users SET saldo = saldo - ? WHERE id_user = ?", "di", (float) $t["nominal"], (int) $t["id_user"]);
        q_exec($koneksi, "UPDATE tarik_saldo SET status = 'disetujui' WHERE id_tarik = ?", "i", $id);
        $pesan = "Penarikan disetujui dan saldo nasabah sudah dikurangi.";
    } else {
        q_exec($koneksi, "UPDATE tarik_saldo SET status = 'ditolak' WHERE id_tarik = ?", "i", $id);
        $pesan = "Penarikan ditolak.";
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
