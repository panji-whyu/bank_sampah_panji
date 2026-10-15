<?php
// koneksi.php (folder modul)
// Konfigurasi koneksi ke database bank_sampah_panji

$host     = "localhost";
$username = "root";
$password = "";
$database = "bank_sampah_panji";

$koneksi = mysqli_connect($host, $username, $password, $database);

if (!$koneksi) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($koneksi, "utf8mb4");
?>
