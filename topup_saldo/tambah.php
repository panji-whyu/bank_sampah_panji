<?php
require "index.php";
require_role(["admin", "pengepul"]);

$users   = is_admin() ? q_all($koneksi, "SELECT id_user, nama, saldo FROM users WHERE role = 'pengepul' ORDER BY nama") : [];
$errors  = [];
$in_user = 0;
$nominal = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();

    $in_user = (int) ($_POST["id_user"] ?? 0);
    $nominal = $_POST["nominal"] ?? "";
    $target  = is_admin() ? $in_user : $ID_USER;

    $t = q_one($koneksi, "SELECT id_user, role FROM users WHERE id_user = ?", "i", $target);
    if (!$t || $t["role"] !== "pengepul") {
        $errors[] = "Pilih akun pengepul yang valid.";
    }

    $nom = is_numeric($nominal) ? round((float) $nominal, 2) : 0;
    if ($nom <= 0 || $nom > 1000000000) {
        $errors[] = "Nominal harus lebih dari 0.";
    }

    if (!$errors) {
        try {
            q_exec($koneksi, "INSERT INTO topup_saldo (id_user, nominal, status) VALUES (?, ?, 'pending')", "id", $target, $nom);
            flash("Pengajuan top up " . rupiah($nom) . " berhasil dikirim dan menunggu persetujuan admin.");
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
<?php render_head("Ajukan Top Up"); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container narrow">
        <div class="form-card">
            <h2>Ajukan Top Up Saldo</h2>

            <?php render_errors($errors); ?>

            <?php if (!is_admin()): ?>
                <div class="info-box">Top up dicatat atas nama <strong><?= e($_SESSION["nama"] ?? "") ?></strong>. Transfer dulu ke admin sesuai kesepakatan, lalu ajukan di sini.</div>
            <?php endif; ?>

            <form method="POST" action="tambah.php">
                <?= csrf_field() ?>

                <?php if (is_admin()): ?>
                <div class="form-group">
                    <label>Pengepul</label>
                    <select name="id_user" class="input-control" required>
                        <option value="">-- Pilih Pengepul --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u["id_user"] ?>" <?= $in_user === (int) $u["id_user"] ? "selected" : "" ?>>
                                <?= e($u["nama"]) ?> - saldo saat ini <?= rupiah($u["saldo"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Nominal Top Up (Rp)</label>
                    <input type="number" step="0.01" min="0.01" name="nominal" class="input-control" placeholder="Contoh: 500000" value="<?= e($nominal) ?>" required>
                </div>

                <button type="submit" class="btn-submit">Ajukan Top Up</button>
            </form>

            <a class="btn-kembali" href="index.php">&larr; Kembali ke daftar top up</a>
        </div>
    </div>
</body>
</html>
