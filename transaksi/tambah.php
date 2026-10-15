<?php
require "index.php";

$sampahs = q_all($koneksi, "SELECT Id_sampah, Jenis_sampah, Harga_per_kg, stok_kg FROM sampah ORDER BY Jenis_sampah, Id_sampah");
$users   = is_admin() ? q_all($koneksi, "SELECT id_user, nama, role FROM users ORDER BY nama") : [];

$errors    = [];
$in_user   = 0;
$in_sampah = 0;
$in_berat  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();

    $in_user   = (int) ($_POST["id_user"] ?? 0);
    $in_sampah = (int) ($_POST["id_sampah"] ?? 0);
    $in_berat  = $_POST["berat_kg"] ?? "";

    // Tentukan siapa pelakunya dan tipe transaksi berdasarkan ROLE
    //   admin/nasabah/siswa/guru -> setor (saldo bertambah), pengepul -> beli (tipe "tarik")
    $target = null;
    $tipe   = null;

    if (is_admin()) {
        $t = q_one($koneksi, "SELECT id_user, role FROM users WHERE id_user = ?", "i", $in_user);
        if (!$t) {
            $errors[] = "Pilih pengguna yang valid.";
        } else {
            $target = (int) $t["id_user"];
            $tipe   = ($t["role"] === "pengepul") ? "tarik" : "setor";
        }
    } else {
        $target = $ID_USER;
        $tipe   = is_pengepul() ? "tarik" : "setor";
    }

    if ($tipe === "tarik") {
        $__u = q_one($koneksi, "SELECT saldo FROM users WHERE id_user = ?", "i", $target);
        $saldoPengepul = (float) ($__u["saldo"] ?? 0);
    }

    $berat = is_numeric($in_berat) ? round((float) $in_berat, 2) : 0;
    if ($berat <= 0 || $berat > 100000) {
        $errors[] = "Berat harus lebih dari 0 dan maksimal 100.000 kg.";
    }

    if (!$errors) {
        try {
            mysqli_begin_transaction($koneksi);

            $s = q_one($koneksi, "SELECT Harga_per_kg, stok_kg FROM sampah WHERE Id_sampah = ? FOR UPDATE", "i", $in_sampah);
            if (!$s) {
                throw new RuntimeException("Jenis sampah tidak ditemukan.");
            }

            $total = round($berat * (float) $s["Harga_per_kg"], 2);

            if ($tipe === "tarik") {
                if ((float) $s["stok_kg"] < $berat) {
                    throw new RuntimeException("Stok tidak cukup. Stok tersedia: " . number_format((float) $s["stok_kg"], 2, ",", ".") . " kg.");
                }
                $up = q_one($koneksi, "SELECT saldo FROM users WHERE id_user = ? FOR UPDATE", "i", $target);
                if (!$up || (float) $up["saldo"] < $total) {
                    throw new RuntimeException("Saldo tidak cukup. Saldo tersedia: " . rupiah($up["saldo"] ?? 0) . ", dibutuhkan " . rupiah($total) . ". Silakan top up saldo terlebih dahulu.");
                }
            }

            q_exec($koneksi,
                "INSERT INTO transaksi (id_user, id_sampah, tipe, berat_kg, total_rp) VALUES (?, ?, ?, ?, ?)",
                "iisdd", $target, $in_sampah, $tipe, $berat, $total);

            if ($tipe === "setor") {
                q_exec($koneksi, "UPDATE sampah SET stok_kg = stok_kg + ? WHERE Id_sampah = ?", "di", $berat, $in_sampah);
                q_exec($koneksi, "UPDATE users SET saldo = saldo + ? WHERE id_user = ?", "di", $total, $target);
            } else {
                q_exec($koneksi, "UPDATE sampah SET stok_kg = stok_kg - ? WHERE Id_sampah = ?", "di", $berat, $in_sampah);
                q_exec($koneksi, "UPDATE users SET saldo = saldo - ? WHERE id_user = ?", "di", $total, $target);
            }

            mysqli_commit($koneksi);

            if ($tipe === "setor") {
                flash("Setoran berhasil dicatat. Saldo bertambah " . rupiah($total) . ".");
            } else {
                flash("Pembelian berhasil dicatat. Total " . rupiah($total) . " dipotong dari saldo, stok sampah berkurang.");
            }
            redirect("index.php");
        } catch (RuntimeException $ex) {
            mysqli_rollback($koneksi);
            $errors[] = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            mysqli_rollback($koneksi);
            $errors[] = "Gagal menyimpan transaksi. Coba lagi.";
        }
    }
}

if (is_admin())        { $judul = "Tambah Transaksi"; }
elseif (is_pengepul()) { $judul = "Beli Sampah"; }
else                   { $judul = "Setor Sampah"; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php render_head($judul); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container narrow">
        <div class="form-card">
            <h2><?= e($judul) ?></h2>

            <?php render_errors($errors); ?>

            <?php if (is_penjual()): ?>
                <div class="info-box">Setoran dicatat atas nama <strong><?= e($_SESSION["nama"] ?? "") ?></strong>. Nilainya otomatis masuk ke <strong>saldo</strong> Anda.</div>
            <?php elseif (is_pengepul()): ?>
                <?php $__me = q_one($koneksi, "SELECT saldo FROM users WHERE id_user = ?", "i", $ID_USER); ?>
                <div class="info-box">
                    Saldo Anda: <strong><?= rupiah($__me["saldo"] ?? 0) ?></strong>. Pembelian akan <strong>memotong saldo</strong> dan <strong>mengurangi stok</strong> sampah.
                    Saldo kurang? <a href="../topup_saldo/tambah.php" style="color:#34d399;">Ajukan top up di sini</a>.
                </div>
            <?php endif; ?>

            <form method="POST" action="tambah.php">
                <?= csrf_field() ?>

                <?php if (is_admin()): ?>
                <div class="form-group">
                    <label>Pengguna</label>
                    <select name="id_user" class="input-control" required>
                        <option value="">-- Pilih Pengguna --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int) $u["id_user"] ?>" <?= $in_user === (int) $u["id_user"] ? "selected" : "" ?>>
                                <?= e($u["nama"]) ?> (<?= e(ucfirst($u["role"])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Admin, nasabah, siswa, dan guru = <strong>setor</strong> (saldo bertambah). Pengepul = <strong>beli</strong> (stok berkurang).</small>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Jenis Sampah</label>
                    <select name="id_sampah" id="id_sampah" class="input-control" required>
                        <option value="">-- Pilih Sampah --</option>
                        <?php foreach ($sampahs as $s): ?>
                            <option value="<?= (int) $s["Id_sampah"] ?>"
                                    data-harga="<?= e($s["Harga_per_kg"]) ?>"
                                    <?= $in_sampah === (int) $s["Id_sampah"] ? "selected" : "" ?>>
                                <?= e($s["Jenis_sampah"]) ?> - <?= rupiah($s["Harga_per_kg"]) ?>/kg (stok <?= number_format((float) $s["stok_kg"], 1, ",", ".") ?> kg)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Berat (Kg)</label>
                    <input type="number" step="0.01" min="0.01" name="berat_kg" id="berat_kg" class="input-control" placeholder="Contoh: 2.5" value="<?= e($in_berat) ?>" required>
                </div>

                <div class="info-box" id="estimasi">Estimasi total: -</div>

                <button type="submit" class="btn-submit"><?= is_pengepul() ? "Simpan Pembelian" : "Simpan Setoran" ?></button>
            </form>

            <a class="btn-kembali" href="index.php">&larr; Kembali ke daftar transaksi</a>
        </div>
    </div>

    <script>
        var sel = document.getElementById('id_sampah');
        var berat = document.getElementById('berat_kg');
        var out = document.getElementById('estimasi');

        function hitung() {
            var opt = sel.options[sel.selectedIndex];
            var harga = parseFloat(opt && opt.dataset.harga ? opt.dataset.harga : 0);
            var b = parseFloat(berat.value || 0);
            out.textContent = (harga > 0 && b > 0)
                ? 'Estimasi total: Rp ' + Math.round(harga * b).toLocaleString('id-ID')
                : 'Estimasi total: -';
        }
        sel.addEventListener('change', hitung);
        berat.addEventListener('input', hitung);
        hitung();
    </script>
</body>
</html>
