<?php
require "index.php";
require_role(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("index.php");
}
csrf_check();

$id = (int) ($_POST["id"] ?? 0);

// Menghapus transaksi = membatalkan efeknya pada stok dan saldo (dalam satu transaksi database)
try {
    mysqli_begin_transaction($koneksi);

    $t = q_one($koneksi, "SELECT * FROM transaksi WHERE id_transaksi = ? FOR UPDATE", "i", $id);
    if (!$t) {
        throw new RuntimeException("Transaksi tidak ditemukan.");
    }

    $s = q_one($koneksi, "SELECT stok_kg FROM sampah WHERE Id_sampah = ? FOR UPDATE", "i", (int) $t["id_sampah"]);
    $berat = (float) $t["berat_kg"];
    $total = (float) $t["total_rp"];

    if ($t["tipe"] === "setor") {
        if ((float) $s["stok_kg"] < $berat) {
            throw new RuntimeException("Tidak bisa dihapus: stok sampah saat ini lebih kecil dari berat setoran ini (sebagian sudah dibeli).");
        }
        $u = q_one($koneksi, "SELECT saldo FROM users WHERE id_user = ? FOR UPDATE", "i", (int) $t["id_user"]);
        if (!$u || (float) $u["saldo"] < $total) {
            throw new RuntimeException("Tidak bisa dihapus: saldo nasabah sudah lebih kecil dari nilai setoran ini (kemungkinan sudah ditarik).");
        }
        q_exec($koneksi, "UPDATE sampah SET stok_kg = stok_kg - ? WHERE Id_sampah = ?", "di", $berat, (int) $t["id_sampah"]);
        q_exec($koneksi, "UPDATE users SET saldo = saldo - ? WHERE id_user = ?", "di", $total, (int) $t["id_user"]);
    } else {
        // tipe "tarik" = pembelian pengepul: kembalikan stok dan kembalikan saldo yang terpotong
        q_exec($koneksi, "UPDATE sampah SET stok_kg = stok_kg + ? WHERE Id_sampah = ?", "di", $berat, (int) $t["id_sampah"]);
        q_exec($koneksi, "UPDATE users SET saldo = saldo + ? WHERE id_user = ?", "di", $total, (int) $t["id_user"]);
    }

    q_exec($koneksi, "DELETE FROM transaksi WHERE id_transaksi = ?", "i", $id);

    mysqli_commit($koneksi);
    flash("Transaksi dihapus dan stok/saldo sudah dikembalikan.");
} catch (RuntimeException $ex) {
    mysqli_rollback($koneksi);
    flash($ex->getMessage(), "error");
} catch (mysqli_sql_exception $ex) {
    mysqli_rollback($koneksi);
    flash("Terjadi kesalahan database saat menghapus transaksi.", "error");
}

redirect("index.php");
