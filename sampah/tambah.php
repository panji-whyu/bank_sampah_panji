<?php
require "index.php";
require_role(["admin"]);

$errors = [];
$jenis  = "";
$harga  = "";
$stok   = "0";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();

    $jenis = trim($_POST["jenis_sampah"] ?? "");
    $harga = $_POST["harga_per_kg"] ?? "";
    $stok  = $_POST["stok_kg"] ?? "";

    if ($jenis === "" || mb_strlen($jenis) > 100) {
        $errors[] = "Jenis sampah wajib diisi (maksimal 100 karakter).";
    }
    if (!is_numeric($harga) || (float) $harga <= 0 || (float) $harga > 1000000000) {
        $errors[] = "Harga per kg harus lebih dari 0.";
    }
    if (!is_numeric($stok) || (float) $stok < 0 || (float) $stok > 9999999) {
        $errors[] = "Stok awal tidak boleh negatif.";
    }

    if (!$errors) {
        try {
            q_exec($koneksi,
                "INSERT INTO sampah (Jenis_sampah, Harga_per_kg, stok_kg) VALUES (?, ?, ?)",
                "sdd", $jenis, round((float) $harga, 2), round((float) $stok, 2));
            flash("Jenis sampah \"$jenis\" berhasil ditambahkan.");
            redirect("index.php");
        } catch (mysqli_sql_exception $ex) {
            $errors[] = "Gagal menyimpan data sampah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php render_head("Tambah Sampah"); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container narrow">
        <div class="form-card">
            <h2>Tambah Data Sampah</h2>

            <?php render_errors($errors); ?>

            <form method="POST" action="tambah.php">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Jenis Sampah</label>
                    <input type="text" name="jenis_sampah" class="input-control" placeholder="Contoh: Plastik Pet, Kardus, Botol Kaca" value="<?= e($jenis) ?>" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label>Harga per Kg (Rp)</label>
                    <input type="number" step="0.01" min="0.01" name="harga_per_kg" class="input-control" placeholder="Contoh: 3000" value="<?= e($harga) ?>" required>
                </div>

                <div class="form-group">
                    <label>Stok Awal (Kg)</label>
                    <input type="number" step="0.01" min="0" name="stok_kg" class="input-control" value="<?= e($stok) ?>" required>
                </div>

                <button type="submit" class="btn-submit">Simpan Sampah</button>
            </form>

            <a class="btn-kembali" href="index.php">&larr; Kembali ke daftar sampah</a>
        </div>
    </div>
</body>
</html>
