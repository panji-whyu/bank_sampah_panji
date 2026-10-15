<?php
require "index.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

csrf_check();

$id = (int) ($_POST["id"] ?? 0);

if ($id === (int) $_SESSION["id_user"]) {
    flash("Anda tidak bisa menghapus akun Anda sendiri.", "error");
    header("Location: index.php");
    exit();
}

$stmt = mysqli_prepare($koneksi, "DELETE FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    flash("User berhasil dihapus.");
} else {
    flash("User tidak ditemukan.", "error");
}

header("Location: index.php");
exit();
