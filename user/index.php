<?php
// index.php - halaman daftar + library bersama untuk semua halaman di folder ini
// Isi: session, koneksi, cek login, cek role, CSRF, flash message, helper query,
//       plus tampilan bersama: render_head() (head + CSS) dan render_navbar()
//
// ROLE:
//  - admin              : mengelola semua data
//  - nasabah/siswa/guru : PENJUAL -> menyetor sampah, punya saldo, bisa tarik saldo
//  - pengepul           : PEMBELI -> membeli sampah dari stok bank sampah

session_start();
require_once "koneksi.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["id_user"])) {
    header("Location: ../login.php");
    exit();
}

$ID_USER = (int) $_SESSION["id_user"];

// Sinkronkan role dari database di setiap request (perubahan role langsung berlaku)
$st = mysqli_prepare($koneksi, "SELECT role FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($st, "i", $ID_USER);
mysqli_stmt_execute($st);
$rowRole = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$rowRole) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$_SESSION["role"] = $rowRole["role"];

// ---------- Role ----------
function role()        { return $_SESSION["role"] ?? ""; }
function is_admin()    { return role() === "admin"; }
function is_penjual()  { return in_array(role(), ["nasabah", "siswa", "guru"], true); }
function is_pengepul() { return role() === "pengepul"; }

function require_role(array $allowed) {
    if (!in_array(role(), $allowed, true)) {
        http_response_code(403);
        die("Akses ditolak. Role Anda tidak diizinkan membuka halaman ini. <a href=\"../index.php\">Kembali ke dashboard</a>");
    }
}

// ---------- Umum ----------
function e($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8");
}

function rupiah($n) {
    return "Rp " . number_format((float) $n, 0, ",", ".");
}

// Di tabel transaksi: tipe "setor" = penjual menyetor, tipe "tarik" = pengepul membeli
function label_tipe($tipe) {
    return $tipe === "tarik" ? "Beli" : "Setor";
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

// ---------- CSRF ----------
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION["csrf"]) . '">';
}

function csrf_check() {
    $token = $_POST["csrf"] ?? "";
    if (!is_string($token) || !hash_equals($_SESSION["csrf"], $token)) {
        http_response_code(419);
        die("Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.");
    }
}

// ---------- Flash message ----------
function flash($pesan, $tipe = "sukses") {
    $_SESSION["flash"] = ["pesan" => $pesan, "tipe" => $tipe];
}

function take_flash() {
    $f = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $f;
}

function render_flash($f) {
    if ($f) {
        echo '<div class="alert alert-' . e($f["tipe"]) . '">' . e($f["pesan"]) . '</div>';
    }
}

function render_errors($errors) {
    if ($errors) {
        echo '<div class="alert alert-error"><ul>';
        foreach ($errors as $x) {
            echo '<li>' . e($x) . '</li>';
        }
        echo '</ul></div>';
    }
}

// ---------- Helper query (prepared statement) ----------
function q($koneksi, $sql, $types = "", ...$params) {
    $st = mysqli_prepare($koneksi, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($st, $types, ...$params);
    }
    mysqli_stmt_execute($st);
    return $st;
}

function q_all($koneksi, $sql, $types = "", ...$params) {
    $st  = q($koneksi, $sql, $types, ...$params);
    $res = mysqli_stmt_get_result($st);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($st);
    return $rows;
}

function q_one($koneksi, $sql, $types = "", ...$params) {
    $rows = q_all($koneksi, $sql, $types, ...$params);
    return $rows[0] ?? null;
}

function q_exec($koneksi, $sql, $types = "", ...$params) {
    $st = q($koneksi, $sql, $types, ...$params);
    $n  = mysqli_stmt_affected_rows($st);
    mysqli_stmt_close($st);
    return $n;
}

// ================= TAMPILAN BERSAMA =================
// Pakai di halaman: panggil render_head("Judul") di dalam head, dan render_navbar() di awal body.

function render_head($judul = "Halaman") { ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($judul) ?> - Bank Sampah Panji</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0c322a; --ink:#f1f5f9; --soft:#a8b5b0; --mute:#6b7c76; --mint:#34d399; --amber:#fbbf24; --red:#f87171; --line:rgba(255,255,255,.11); }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; min-height: 100vh; background: radial-gradient(1200px 700px at 12% -10%, #1b8a68 0%, transparent 60%), radial-gradient(1000px 700px at 100% 100%, #12766c 0%, transparent 60%), linear-gradient(160deg, #114536, #0c322a 55%, #0f423c); background-attachment: fixed; color: var(--ink); overflow-x: hidden; padding-bottom: 56px; position: relative; }
        a { color: inherit; }
        svg { width: 1em; height: 1em; display: block; }

        /* Latar: cahaya hijau bergerak pelan di belakang kaca */
        .bg-glow-1, .bg-glow-2, body::before { position: fixed; border-radius: 50%; filter: blur(90px); pointer-events: none; z-index: 0; content: ""; }
        .bg-glow-1 { width: 520px; height: 520px; top: -160px; left: -120px; background: radial-gradient(circle, rgba(52,211,153,.7), transparent 70%); animation: drift 22s ease-in-out infinite alternate; }
        .bg-glow-2 { width: 460px; height: 460px; bottom: -140px; right: -100px; background: radial-gradient(circle, rgba(45,212,191,.55), transparent 70%); animation: drift 28s ease-in-out infinite alternate-reverse; }
        body::before { width: 340px; height: 340px; top: 38%; left: 52%; background: radial-gradient(circle, rgba(163,230,53,.3), transparent 70%); animation: drift 34s ease-in-out infinite alternate; }
        @keyframes drift { to { transform: translate(90px, 60px) scale(1.15); } }

        /* Kaca cair (dipakai kartu tabel, form, info) */
        .table-card, .form-card {
            position: relative; overflow: hidden; border-radius: 26px;
            background: linear-gradient(145deg, rgba(255,255,255,.15), rgba(255,255,255,.05));
            backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 1px rgba(255,255,255,.3), inset 0 -1px 1px rgba(255,255,255,.05), 0 24px 60px -24px rgba(0,0,0,.7);
        }
        .table-card::before, .form-card::before { content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
            background: radial-gradient(360px circle at var(--mx, 15%) var(--my, 0%), rgba(110,255,200,.15), transparent 60%); }
        .form-card { padding: 36px; }

        /* Navbar */
        .navbar { position: sticky; top: 0; z-index: 100; display: flex; align-items: center; gap: 20px; padding: 12px 28px;
            background: rgba(15,58,45,.6); backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-bottom: 1px solid rgba(52,211,153,.16); box-shadow: 0 8px 24px rgba(0,0,0,.3); }
        .navbar .brand { font-size: 18px; font-weight: 800; letter-spacing: -.3px; text-decoration: none; color: #fff; display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .navbar .menu { flex: 1; display: flex; justify-content: center; flex-wrap: wrap; gap: 2px; padding: 4px; border-radius: 14px; background: rgba(255,255,255,.04); border: 1px solid rgba(52,211,153,.1); }
        .navbar .nav-link { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 10px; font-size: 13.5px; font-weight: 600; color: #94a3b8; text-decoration: none; white-space: nowrap; transition: .2s; }
        .nav-icon { font-size: 14px; line-height: 1; }
        .navbar .nav-link:hover { color: #e2e8f0; background: rgba(255,255,255,.07); }
        .navbar .nav-link.active { color: var(--mint); background: rgba(16,185,129,.16); box-shadow: inset 0 0 0 1px rgba(52,211,153,.35); }
        .navbar-user { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .user-chip { display: flex; align-items: center; gap: 8px; padding: 5px 12px 5px 5px; border-radius: 999px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
        .user-avatar { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: linear-gradient(135deg, #10b981, #059669); color: #04120c; font-size: 12px; font-weight: 800; }
        .user-meta { display: flex; flex-direction: column; line-height: 1.2; }
        .user-meta strong { font-size: 12.5px; color: #e2e8f0; }
        .user-meta small { font-size: 10.5px; color: var(--mute); text-transform: capitalize; }
        .navbar .logout-btn { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fca5a5; text-decoration: none; font-size: 15px; border: 1px solid rgba(248,113,113,.2); background: rgba(239,68,68,.08); transition: .2s; }
        .navbar .logout-btn:hover { background: rgba(239,68,68,.22); color: #fff; }

        /* Halaman */
        .container { position: relative; z-index: 10; max-width: 1100px; margin: 36px auto 0; padding: 0 25px; animation: rise .7s cubic-bezier(.2,.8,.2,1) both; }
        .container.narrow { max-width: 580px; }
        @keyframes rise { from { opacity: 0; transform: translateY(16px); } }
        .header-action { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 22px; }
        h2 { font-size: 26px; font-weight: 700; letter-spacing: -.6px; }
        .form-card h2 { margin-bottom: 24px; }

        .btn-tambah, .btn-submit { position: relative; border: 0; cursor: pointer; font-family: inherit; font-weight: 600; color: #04120c; text-decoration: none;
            background: linear-gradient(180deg, #4ade9d, #10b981); box-shadow: inset 0 1px 1px rgba(255,255,255,.55), 0 8px 24px rgba(16,185,129,.3); transition: transform .2s, box-shadow .2s; }
        .btn-tambah { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 999px; font-size: 14px; }
        .btn-submit { width: 100%; padding: 14px; border-radius: 16px; font-size: 15px; margin-top: 8px; }
        .btn-tambah:hover, .btn-submit:hover { transform: translateY(-2px); box-shadow: inset 0 1px 1px rgba(255,255,255,.55), 0 12px 30px rgba(16,185,129,.45); }
        .btn-kembali { display: inline-block; margin-top: 20px; color: var(--soft); text-decoration: none; font-size: 14px; transition: color .2s; }
        .btn-kembali:hover { color: var(--mint); }
        a:focus-visible, button:focus-visible, .input-control:focus-visible { outline: 2px solid var(--mint); outline-offset: 3px; }

        /* Notifikasi */
        .alert, .info-box { padding: 14px 18px; border-radius: 18px; font-size: 14px; line-height: 1.5; margin-bottom: 20px; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); }
        .alert { font-weight: 600; }
        .alert-sukses { background: rgba(16,185,129,.16); border: 1px solid rgba(52,211,153,.38); color: var(--mint); }
        .alert-error  { background: rgba(239,68,68,.15); border: 1px solid rgba(248,113,113,.38); color: #fca5a5; }
        .alert-error ul { margin-left: 18px; }
        .info-box { background: rgba(255,255,255,.06); border: 1px solid var(--line); color: var(--soft); box-shadow: inset 0 1px 1px rgba(255,255,255,.14); }
        .info-box strong { color: var(--mint); }

        /* Tabel */
        .table-card { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 16px 22px; }
        th { color: var(--mute); font-size: 13px; font-weight: 500; white-space: nowrap; border-bottom: 1px solid var(--line); }
        td { font-size: 14px; color: #e2e8f0; border-bottom: 1px solid rgba(255,255,255,.05); }
        tr:last-child td { border-bottom: 0; }
        tbody tr:hover td { background: rgba(255,255,255,.035); }
        .money { color: var(--mint); font-weight: 600; }
        .empty { text-align: center; color: var(--mute); padding: 38px; }
        .you, .sub { color: var(--mute); font-size: 12px; margin-left: 6px; }

        /* Lencana */
        .badge-role, .badge-tipe, .badge-status { display: inline-block; padding: 3px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .role-admin { background: rgba(239,68,68,.15); border: 1px solid rgba(248,113,113,.35); color: var(--red); }
        .role-nasabah, .tipe-setor, .status-disetujui { background: rgba(16,185,129,.15); border: 1px solid rgba(52,211,153,.35); color: var(--mint); }
        .role-siswa { background: rgba(59,130,246,.15); border: 1px solid rgba(96,165,250,.35); color: #60a5fa; }
        .role-guru { background: rgba(168,85,247,.15); border: 1px solid rgba(192,132,252,.35); color: #c084fc; }
        .role-pengepul, .tipe-tarik, .status-pending { background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.35); color: var(--amber); }
        .status-ditolak { background: rgba(239,68,68,.15); border: 1px solid rgba(248,113,113,.35); color: var(--red); }

        /* Tombol aksi */
        .action-btns { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .action-btns form { display: inline; }
        .btn-edit, .btn-hapus, .btn-setuju, .btn-tolak { display: inline-block; padding: 6px 13px; border-radius: 999px; font-family: inherit; font-size: 12px; font-weight: 600; text-decoration: none; cursor: pointer; transition: .2s; }
        .btn-edit, .btn-tolak { background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.35); color: var(--amber); }
        .btn-edit:hover, .btn-tolak:hover { background: rgba(245,158,11,.3); color: #fff; }
        .btn-hapus { background: rgba(239,68,68,.15); border: 1px solid rgba(248,113,113,.32); color: var(--red); }
        .btn-hapus:hover { background: rgba(239,68,68,.3); color: #fff; }
        .btn-setuju { background: rgba(16,185,129,.15); border: 1px solid rgba(52,211,153,.35); color: var(--mint); }
        .btn-setuju:hover { background: rgba(16,185,129,.3); color: #fff; }

        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 500; color: #cbd5e1; margin-bottom: 8px; }
        .form-group small { display: block; margin-top: 6px; font-size: 12px; color: var(--mute); }
        .input-control { width: 100%; padding: 13px 16px; border-radius: 14px; font-family: inherit; font-size: 14px; color: #fff; outline: none; transition: .25s;
            background: rgba(6,44,33,.5); border: 1px solid rgba(255,255,255,.13); box-shadow: inset 0 1px 2px rgba(0,0,0,.3); }
        .input-control option { background: #0f3a2d; color: #fff; }
        .input-control:focus { border-color: var(--mint); box-shadow: 0 0 0 4px rgba(52,211,153,.16); }
        .input-control:disabled { opacity: .55; cursor: not-allowed; }
        .input-control::placeholder { color: var(--mute); }

        @media (max-width: 900px) {
            .navbar { flex-wrap: wrap; padding: 12px 16px; row-gap: 10px; }
            .navbar .menu { order: 3; width: 100%; justify-content: flex-start; flex-wrap: nowrap; overflow-x: auto; }
            .navbar-user { margin-left: auto; } .user-meta { display: none; }
            .container { padding: 0 16px; margin-top: 24px; }
            .form-card { padding: 24px; }
        }
        @media (prefers-reduced-motion: reduce) { .bg-glow-1, .bg-glow-2, body::before, .container { animation: none; } }
    </style>
    <script>
        // Kilau kaca mengikuti kursor
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.table-card, .form-card').forEach(function (el) {
                el.addEventListener('pointermove', function (ev) {
                    var r = el.getBoundingClientRect();
                    el.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
                    el.style.setProperty('--my', (ev.clientY - r.top) + 'px');
                });
            });
        });
    </script>
<?php }

// Menu mengikuti role. Menu aktif ditentukan dari nama folder halaman.
function render_navbar() {
    $active = basename(dirname($_SERVER["SCRIPT_NAME"]));
    $_role  = $_SESSION["role"] ?? "";
    $_nama  = $_SESSION["nama"] ?? "";
    $_items = [
        ["dashboard",   "../index.php",             "🏠", "Dashboard",   null],
        ["sampah",      "../sampah/index.php",      "🗑️", "Sampah",      null],
        ["transaksi",   "../transaksi/index.php",   "🔄", "Transaksi",   null],
        ["tarik_saldo", "../tarik_saldo/index.php", "🏧", "Tarik Saldo", ["admin", "nasabah", "siswa", "guru"]],
        ["topup_saldo", "../topup_saldo/index.php", "💳", "Top Up",      ["admin", "pengepul"]],
        ["user",        "../user/index.php",        "👥", "User",        ["admin"]],
    ];
?>
<div class="navbar">
    <a href="../index.php" class="brand">
        <span>♻️</span> Bank Sampah Panji
    </a>

    <nav class="menu">
        <?php foreach ($_items as [$key, $href, $icon, $label, $roles]): ?>
            <?php if ($roles === null || in_array($_role, $roles, true)): ?>
                <a href="<?= $href ?>" class="nav-link <?= $active === $key ? "active" : "" ?>">
                    <span class="nav-icon"><?= $icon ?></span><?= $label ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="navbar-user">
        <div class="user-chip">
            <span class="user-avatar"><?= e(mb_strtoupper(mb_substr($_nama ?: "?", 0, 1))) ?></span>
            <span class="user-meta">
                <strong><?= e($_nama ?: "Pengguna") ?></strong>
                <small><?= e(ucfirst($_role)) ?></small>
            </span>
        </div>
        <a href="../logout.php" class="logout-btn" title="Logout">⏻</a>
    </div>
</div>
<?php
}

// Kelola user hanya untuk admin
require_role(["admin"]);

$ROLES = ["admin", "nasabah", "siswa", "guru", "pengepul"];

// ===== Batas: kode di atas adalah "library" (auth, head, navbar, CSS) yang juga dipakai
// tambah.php / hapus.php / proses.php / edit.php lewat require "index.php".
// Kalau file ini di-require dari halaman lain, berhenti di sini (daftar data tidak ditampilkan).
if (realpath(__FILE__) !== realpath($_SERVER["SCRIPT_FILENAME"])) {
    return;
}

$data = mysqli_query($koneksi, "SELECT id_user, nama, username, saldo, role FROM users ORDER BY id_user ASC");

$flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php render_head("Data User"); ?>
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <?php render_navbar(); ?>

    <div class="container">
        <div class="header-action">
            <h2>Data User</h2>
            <a class="btn-tambah" href="tambah.php">+ Tambah User</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash["tipe"]) ?>"><?= e($flash["pesan"]) ?></div>
        <?php endif; ?>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Saldo</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($data)): ?>
                    <?php $isSelf = ((int) $row["id_user"] === (int) $_SESSION["id_user"]); ?>
                    <tr>
                        <td>#<?= (int) $row["id_user"] ?></td>
                        <td>
                            <strong><?= e($row["nama"]) ?></strong>
                            <?php if ($isSelf): ?><span class="you">(Anda)</span><?php endif; ?>
                        </td>
                        <td>@<?= e($row["username"]) ?></td>
                        <td><span class="badge-role role-<?= e($row["role"]) ?>"><?= e(ucfirst($row["role"])) ?></span></td>
                        <td style="color: #34d399; font-weight: 700;">Rp <?= number_format($row["saldo"], 0, ",", ".") ?></td>
                        <td>
                            <div class="action-btns">
                                <a class="btn-edit" href="edit.php?id=<?= (int) $row["id_user"] ?>">Edit</a>
                                <?php if (!$isSelf): ?>
                                <form method="POST" action="hapus.php"
                                      onsubmit="return confirm('Hapus user ini? Semua transaksi dan penarikan saldo milik user ini ikut terhapus permanen.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row["id_user"] ?>">
                                    <button type="submit" class="btn-hapus">Hapus</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
