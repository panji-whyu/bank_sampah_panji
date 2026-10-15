<?php
require "index.php";

$errors   = [];
$nama     = "";
$username = "";
$role     = "siswa";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();

    $nama     = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role     = $_POST["role"] ?? "";

    if ($nama === "" || mb_strlen($nama) > 100) {
        $errors[] = "Nama wajib diisi (maksimal 100 karakter).";
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        $errors[] = "Username 3-50 karakter: huruf, angka, titik, atau underscore.";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    }
    if (!in_array($role, $ROLES, true)) {
        $errors[] = "Role tidak valid.";
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($koneksi, "INSERT INTO users (nama, username, password, saldo, role) VALUES (?, ?, ?, 0, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $nama, $username, $hash, $role);

        try {
            mysqli_stmt_execute($stmt);
            flash("User \"$nama\" berhasil ditambahkan.");
            header("Location: index.php");
            exit();
        } catch (mysqli_sql_exception $ex) {
            $errors[] = ($ex->getCode() === 1062) ? "Username sudah dipakai, pilih yang lain." : "Gagal menyimpan data user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php render_head("Tambah User"); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container narrow">
        <div class="form-card">
            <h2>Tambah User</h2>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="tambah.php" autocomplete="off">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" class="input-control" placeholder="Contoh: Budi Santoso" value="<?= e($nama) ?>" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="input-control" placeholder="Contoh: budi" value="<?= e($username) ?>" maxlength="50" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="input-control" placeholder="Minimal 6 karakter" minlength="6" required>
                    <small>Password disimpan dalam bentuk hash, bukan teks biasa.</small>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="input-control" required>
                        <?php foreach ($ROLES as $r): ?>
                            <option value="<?= e($r) ?>" <?= $role === $r ? "selected" : "" ?>><?= e(ucfirst($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Simpan User</button>
            </form>

            <a class="btn-kembali" href="index.php">&larr; Kembali ke daftar user</a>
        </div>
    </div>
</body>
</html>
