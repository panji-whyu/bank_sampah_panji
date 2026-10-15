<?php
require "index.php";

$id = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);
$isSelf = ($id === (int) $_SESSION["id_user"]);

$stmt = mysqli_prepare($koneksi, "SELECT id_user, nama, username, saldo, role FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$user) {
    flash("User tidak ditemukan.", "error");
    header("Location: index.php");
    exit();
}

$errors   = [];
$nama     = $user["nama"];
$username = $user["username"];
$role     = $user["role"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_check();

    $nama     = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role     = $isSelf ? "admin" : ($_POST["role"] ?? ""); // admin tidak bisa menurunkan role sendiri

    if ($nama === "" || mb_strlen($nama) > 100) {
        $errors[] = "Nama wajib diisi (maksimal 100 karakter).";
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        $errors[] = "Username 3-50 karakter: huruf, angka, titik, atau underscore.";
    }
    if ($password !== "" && strlen($password) < 6) {
        $errors[] = "Password baru minimal 6 karakter (kosongkan jika tidak ingin mengubah).";
    }
    if (!in_array($role, $ROLES, true)) {
        $errors[] = "Role tidak valid.";
    }

    if (!$errors) {
        try {
            if ($password !== "") {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, username = ?, role = ?, password = ? WHERE id_user = ?");
                mysqli_stmt_bind_param($upd, "ssssi", $nama, $username, $role, $hash, $id);
            } else {
                $upd = mysqli_prepare($koneksi, "UPDATE users SET nama = ?, username = ?, role = ? WHERE id_user = ?");
                mysqli_stmt_bind_param($upd, "sssi", $nama, $username, $role, $id);
            }
            mysqli_stmt_execute($upd);

            if ($isSelf) {
                $_SESSION["nama"]     = $nama;
                $_SESSION["username"] = $username;
            }

            flash("Data user \"$nama\" berhasil diperbarui.");
            header("Location: index.php");
            exit();
        } catch (mysqli_sql_exception $ex) {
            $errors[] = ($ex->getCode() === 1062) ? "Username sudah dipakai, pilih yang lain." : "Gagal memperbarui data user.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php render_head("Edit User"); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container narrow">
        <div class="form-card">
            <h2>Edit User</h2>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="edit.php?id=<?= (int) $id ?>" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" class="input-control" value="<?= e($nama) ?>" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="input-control" value="<?= e($username) ?>" maxlength="50" required>
                </div>

                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="password" class="input-control" placeholder="Kosongkan jika tidak diubah" minlength="6">
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="input-control" <?= $isSelf ? "disabled" : "" ?> required>
                        <?php foreach ($ROLES as $r): ?>
                            <option value="<?= e($r) ?>" <?= $role === $r ? "selected" : "" ?>><?= e(ucfirst($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isSelf): ?><small>Role akun Anda sendiri tidak bisa diubah.</small><?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Saldo</label>
                    <input type="text" class="input-control" value="Rp <?= number_format($user["saldo"], 0, ",", ".") ?>" disabled>
                    <small>Saldo berubah otomatis lewat transaksi setor dan penarikan saldo.</small>
                </div>

                <button type="submit" class="btn-submit">Simpan Perubahan</button>
            </form>

            <a class="btn-kembali" href="index.php">&larr; Kembali ke daftar user</a>
        </div>
    </div>
</body>
</html>
