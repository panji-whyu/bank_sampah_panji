<?php
require "index.php";
require_role(["admin", "nasabah", "siswa", "guru"]);

$users   = is_admin() ? q_all($koneksi, "SELECT id_user, nama, role, saldo FROM users WHERE role <> 'pengepul' ORDER BY nama") : [];
$me      = q_one($koneksi, "SELECT saldo FROM users WHERE id_user = ?", "i", $ID_USER);
$errors  = [];
$in_user = 0;
$nominal = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();

    $in_user = (int) ($_POST["id_user"] ?? 0);
    $nominal = $_POST["nominal"] ?? "";
    $target  = is_admin() ? $in_user : $ID_USER;

    $t = q_one($koneksi, "SELECT id_user, role, saldo FROM users WHERE id_user = ?", "i", $target);
    if (!$t || $t["role"] === "pengepul") {
        $errors[] = "Pilih nasabah yang valid.";
    }

    $nom = is_numeric($nominal) ? round((float) $nominal, 2) : 0;
    if ($nom <= 0) {
        $errors[] = "Nominal harus lebih dari 0.";
    }

    if (!$errors) {
        // Saldo yang bisa diajukan = saldo dikurangi pengajuan yang masih pending
        $p = q_one($koneksi, "SELECT COALESCE(SUM(nominal), 0) AS total FROM tarik_saldo WHERE id_user = ? AND status = 'pending'", "i", $target);
        $tersedia = (float) $t["saldo"] - (float) $p["total"];

        if ($nom > $tersedia) {
            $errors[] = "Saldo tidak cukup. Saldo tersedia (di luar pengajuan pending): " . rupiah(max($tersedia, 0)) . ".";
        }
    }

    if (!$errors) {
        try {
            q_exec($koneksi, "INSERT INTO tarik_saldo (id_user, nominal, status) VALUES (?, ?, 'pending')", "id", $target, $nom);
            flash("Pengajuan penarikan " . rupiah($nom) . " berhasil dikirim dan menunggu persetujuan admin.");
            redirect("index.php");
        } catch (mysqli_sql_exception $ex) {
            $errors[] = "Gagal menyimpan pengajuan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php render_head("Ajukan Penarikan"); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container narrow">
        <div class="form-card">
            <h2>Ajukan Penarikan Saldo</h2>

            <?php render_errors($errors); ?>

            <?php if (!is_admin()): ?>
                <div class="info-box">Saldo Anda saat ini: <strong><?= rupiah($me["saldo"] ?? 0) ?></strong></div>
            <?php endif; ?>

            <form method="POST" action="tambah.php">
                <?= csrf_field() ?>

                <?php if (is_admin()): ?>
                <div class="form-group">
                    <label>Nasabah</label>
                    <select name="id_user" class="input-control" required>
                        <option value="">-- Pilih Nasabah --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u["id_user"] ?>" <?= $in_user === (int) $u["id_user"] ? "selected" : "" ?>>
                                <?= e($u["nama"]) ?> (<?= e(ucfirst($u["role"])) ?>) - saldo <?= rupiah($u["saldo"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Nominal Penarikan (Rp)</label>
                    <input type="number" step="0.01" min="0.01" name="nominal" class="input-control" placeholder="Contoh: 50000" value="<?= e($nominal) ?>" required>
                </div>

                <button type="submit" class="btn-submit">Ajukan Penarikan</button>
            </form>

            <a class="btn-kembali" href="index.php">&larr; Kembali ke daftar penarikan</a>
        </div>
    </div>
</body>
</html>
