<?php
// index.php (root) - Dashboard Bank Sampah Panji
session_start();
require "koneksi.php";
date_default_timezone_set("Asia/Jakarta");

if (!isset($_SESSION["id_user"])) {
    header("Location: login.php");
    exit();
}
mysqli_set_charset($koneksi, "utf8mb4");
$id = (int) $_SESSION["id_user"];

function row($k, $sql)  { try { $r = mysqli_query($k, $sql); return $r ? (mysqli_fetch_assoc($r) ?: null) : null; } catch (Throwable $e) { return null; } }
function rows($k, $sql) { try { $r = mysqli_query($k, $sql); return $r ? mysqli_fetch_all($r, MYSQLI_ASSOC) : []; } catch (Throwable $e) { return []; } }
function num($k, $sql)  { $r = row($k, $sql); return $r ? (float) reset($r) : 0; }
function e($s)  { return htmlspecialchars((string) $s, ENT_QUOTES, "UTF-8"); }
function rp($n) { return "Rp " . number_format((float) $n, 0, ",", "."); }

$me = row($koneksi, "SELECT nama, username, role, saldo FROM users WHERE id_user = $id");
if (!$me) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$role = $me["role"];
$_SESSION["role"] = $role;
$_SESSION["nama"] = $me["nama"];
$admin    = $role === "admin";
$pengepul = $role === "pengepul";
$penjual  = !$admin && !$pengepul;

// ---------- Data ringkasan ----------
$own    = $admin ? "" : " WHERE id_user = $id";
$ownAnd = $admin ? "" : " AND id_user = $id";
$jenis  = num($koneksi, "SELECT COUNT(*) FROM sampah");
$stok   = num($koneksi, "SELECT COALESCE(SUM(stok_kg), 0) FROM sampah");
$trx    = num($koneksi, "SELECT COUNT(*) FROM transaksi" . $own);
$pTarik = num($koneksi, "SELECT COUNT(*) FROM tarik_saldo WHERE status = 'pending'" . $ownAnd);
$pTopup = num($koneksi, "SELECT COUNT(*) FROM topup_saldo WHERE status = 'pending'" . $ownAnd);

if ($admin)         { $pending = $pTarik + $pTopup; $pendingLabel = "Menunggu persetujuan"; }
elseif ($pengepul)  { $pending = $pTopup;           $pendingLabel = "Top up menunggu"; }
else                { $pending = $pTarik;           $pendingLabel = "Penarikan menunggu"; }

$stats = [
    ["trash",  $jenis,   0, "Jenis sampah"],
    ["scale",  $stok,    1, "Total stok (kg)"],
    ["repeat", $trx,     0, $admin ? "Total transaksi" : ($pengepul ? "Pembelian saya" : "Setoran saya")],
    ["clock",  $pending, 0, $pendingLabel],
];

$menu = [
    ["sampah/index.php",      "trash",  "Data Sampah", "Lihat jenis, harga, dan stok",                                  null],
    ["transaksi/index.php",   "repeat", "Transaksi",   $admin ? "Catat setoran dan pembelian" : ($pengepul ? "Beli sampah dan lihat riwayat" : "Setor sampah dan lihat riwayat"), null],
    ["tarik_saldo/index.php", "cash",   "Tarik Saldo", $admin ? "Setujui atau tolak penarikan" : "Ajukan penarikan saldo", ["admin", "nasabah", "siswa", "guru"]],
    ["topup_saldo/index.php", "card",   "Top Up Saldo", $admin ? "Setujui atau tolak top up" : "Ajukan top up saldo",     ["admin", "pengepul"]],
    ["user/index.php",        "users",  "Kelola User", "Tambah, ubah, dan hapus akun",                                   ["admin"]],
];

$where  = $admin ? "" : " WHERE t.id_user = $id";
$recent = rows($koneksi, "SELECT t.*, u.nama, s.Jenis_sampah FROM transaksi t
    JOIN users u ON t.id_user = u.id_user
    JOIN sampah s ON t.id_sampah = s.Id_sampah" . $where . " ORDER BY t.id_transaksi DESC LIMIT 5");

// ---------- Tampilan ----------
$hari  = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
$bulan = ["", "Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
$bulanP = ["", "Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
$jam   = (int) date("G");
$salam = $jam < 11 ? "Selamat pagi" : ($jam < 15 ? "Selamat siang" : ($jam < 18 ? "Selamat sore" : "Selamat malam"));
$tanggal = $hari[(int) date("w")] . ", " . date("j") . " " . $bulanP[(int) date("n")] . " " . date("Y");
$deskripsi = $admin ? "Mengelola seluruh data user, sampah, transaksi, dan saldo."
          : ($pengepul ? "Beli sampah dari stok bank sampah dengan saldo Anda."
          : "Setor sampah, kumpulkan saldo, lalu tarik kapan perlu.");

function ico($n) {
    $p = [
        "trash"  => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/>',
        "scale"  => '<path d="m16 16 3-8 3 8c-.9.7-1.9 1-3 1s-2.1-.3-3-1Z"/><path d="m2 16 3-8 3 8c-.9.7-1.9 1-3 1s-2.100-.3-3-1Z"/><path d="M7 21h10M12 3v18M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/>',
        "repeat" => '<path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
        "clock"  => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        "cash"   => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        "card"   => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
        "users"  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        "wallet" => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
        "arrow"  => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
}

$_items = [
    ["dashboard",   "index.php",             "🏠", "Dashboard",   null],
    ["sampah",      "sampah/index.php",      "🗑️", "Sampah",      null],
    ["transaksi",   "transaksi/index.php",   "🔄", "Transaksi",   null],
    ["tarik_saldo", "tarik_saldo/index.php", "🏧", "Tarik Saldo", ["admin", "nasabah", "siswa", "guru"]],
    ["topup_saldo", "topup_saldo/index.php", "💳", "Top Up",      ["admin", "pengepul"]],
    ["user",        "user/index.php",        "👥", "User",        ["admin"]],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bank Sampah Panji</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0c322a; --ink: #f1f5f9; --soft: #a8b5b0; --mute: #6b7c76;
            --mint: #34d399; --mint-2: #10b981; --amber: #fbbf24;
            --line: rgba(255,255,255,.11);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Outfit', sans-serif; min-height: 100vh; background: radial-gradient(1200px 700px at 12% -10%, #1b8a68 0%, transparent 60%), radial-gradient(1000px 700px at 100% 100%, #12766c 0%, transparent 60%), linear-gradient(160deg, #114536, #0c322a 55%, #0f423c); background-attachment: fixed; color: var(--ink); overflow-x: hidden; padding-bottom: 56px; }
        a { color: inherit; text-decoration: none; }
        svg { width: 1em; height: 1em; display: block; }

        /* Latar cair: tiga cahaya bergerak pelan di belakang kaca */
        .orb { position: fixed; border-radius: 50%; filter: blur(90px); pointer-events: none; z-index: 0; }
        .orb.a { width: 520px; height: 520px; top: -160px; left: -120px; background: radial-gradient(circle, rgba(52,211,153,.7), transparent 70%); animation: drift 22s ease-in-out infinite alternate; }
        .orb.b { width: 460px; height: 460px; bottom: -140px; right: -100px; background: radial-gradient(circle, rgba(45,212,191,.55), transparent 70%); animation: drift 28s ease-in-out infinite alternate-reverse; }
        .orb.c { width: 340px; height: 340px; top: 38%; left: 52%; background: radial-gradient(circle, rgba(163,230,53,.3), transparent 70%); animation: drift 34s ease-in-out infinite alternate; }
        @keyframes drift { to { transform: translate(90px, 60px) scale(1.15); } }

        /* Kaca cair */
        .glass {
            position: relative; overflow: hidden; border-radius: 26px;
            background: linear-gradient(145deg, rgba(255,255,255,.15), rgba(255,255,255,.05));
            backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 1px rgba(255,255,255,.3), inset 0 -1px 1px rgba(255,255,255,.05), 0 24px 60px -24px rgba(0,0,0,.7);
            transition: transform .3s ease, border-color .3s ease;
        }
        .glass::before {
            content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
            background: radial-gradient(360px circle at var(--mx, 15%) var(--my, 0%), rgba(110,255,200,.17), transparent 60%);
        }
        a.glass:hover { transform: translateY(-4px); border-color: rgba(110,255,200,.4); }
        a.glass:focus-visible, .logout:focus-visible, .nav-link:focus-visible, .more:focus-visible { outline: 2px solid var(--mint); outline-offset: 3px; }

        /* Navbar */
        .navbar { position: sticky; top: 0; z-index: 100; display: flex; align-items: center; gap: 20px; padding: 12px 28px;
            background: rgba(15,58,45,.6); backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-bottom: 1px solid rgba(52,211,153,.16); box-shadow: 0 8px 24px rgba(0,0,0,.3); }
        .brand { font-size: 18px; font-weight: 800; letter-spacing: -.3px; display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .menu { flex: 1; display: flex; justify-content: center; flex-wrap: wrap; gap: 2px; padding: 4px; border-radius: 14px; background: rgba(255,255,255,.04); border: 1px solid rgba(52,211,153,.1); }
        .nav-link { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 10px; font-size: 13.5px; font-weight: 600; color: #94a3b8; white-space: nowrap; transition: .2s; }
        .nav-link:hover { color: #e2e8f0; background: rgba(255,255,255,.07); }
        .nav-link.active { color: var(--mint); background: rgba(16,185,129,.16); box-shadow: inset 0 0 0 1px rgba(52,211,153,.35); }
        .navbar-user { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .user-chip { display: flex; align-items: center; gap: 8px; padding: 5px 12px 5px 5px; border-radius: 999px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
        .avatar { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: linear-gradient(135deg, #10b981, #059669); color: #04120c; font-size: 12px; font-weight: 800; }
        .user-meta { display: flex; flex-direction: column; line-height: 1.2; }
        .user-meta strong { font-size: 12.5px; }
        .user-meta small { font-size: 10.5px; color: var(--mute); text-transform: capitalize; }
        .logout { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; color: #fca5a5; font-size: 15px; border: 1px solid rgba(248,113,113,.2); background: rgba(239,68,68,.08); transition: .2s; }
        .logout:hover { background: rgba(239,68,68,.22); color: #fff; }

        /* Halaman */
        .wrap { position: relative; z-index: 10; max-width: 1100px; margin: 36px auto 0; padding: 0 25px; display: grid; gap: 22px; }
        .rise { animation: rise .8s cubic-bezier(.2,.8,.2,1) both; animation-delay: calc(var(--i, 0) * 90ms); }
        @keyframes rise { from { opacity: 0; transform: translateY(18px); } }

        .hero { padding: 38px 40px; display: flex; justify-content: space-between; align-items: center; gap: 30px; flex-wrap: wrap; }
        .hero h1 { font-size: clamp(28px, 4vw, 40px); font-weight: 700; letter-spacing: -1px; line-height: 1.1; }
        .hero .date { color: var(--soft); font-size: 14px; margin-bottom: 10px; }
        .hero .desc { color: var(--soft); margin-top: 12px; max-width: 46ch; line-height: 1.6; font-size: 15px; }
        .role { display: inline-block; margin-top: 18px; padding: 5px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 600; text-transform: capitalize; color: var(--mint); background: rgba(16,185,129,.14); border: 1px solid rgba(52,211,153,.35); }
        .balance { min-width: 270px; padding: 24px 26px; border-radius: 22px; background: rgba(6,44,33,.4); border: 1px solid var(--line); box-shadow: inset 0 1px 1px rgba(255,255,255,.2); }
        .balance .lbl { display: flex; align-items: center; gap: 8px; color: var(--soft); font-size: 14px; }
        .balance .lbl svg { color: var(--mint); font-size: 18px; }
        .balance .val { margin-top: 10px; font-size: clamp(30px, 4.4vw, 42px); font-weight: 700; letter-spacing: -1.2px; color: var(--mint); text-shadow: 0 0 30px rgba(52,211,153,.35); }

        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .stat { padding: 22px; }
        .stat .ic { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; font-size: 20px; color: var(--mint); background: rgba(16,185,129,.14); border: 1px solid rgba(52,211,153,.25); margin-bottom: 18px; }
        .stat .n { font-size: 28px; font-weight: 700; letter-spacing: -.8px; }
        .stat .t { color: var(--soft); font-size: 13.5px; margin-top: 2px; }

        h2 { font-size: 19px; font-weight: 700; letter-spacing: -.3px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .more { font-size: 13.5px; font-weight: 500; color: var(--mint); display: inline-flex; align-items: center; gap: 6px; }
        .more svg { font-size: 15px; }

        .quick { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; }
        .q { padding: 20px; display: flex; gap: 14px; align-items: center; }
        .q .ic { flex-shrink: 0; width: 46px; height: 46px; border-radius: 15px; display: grid; place-items: center; font-size: 22px; color: var(--mint); background: rgba(16,185,129,.14); border: 1px solid rgba(52,211,153,.25); }
        .q b { display: block; font-size: 15.5px; font-weight: 600; }
        .q span { display: block; margin-top: 3px; color: var(--soft); font-size: 13px; line-height: 1.4; }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 15px 22px; white-space: nowrap; }
        th { color: var(--mute); font-size: 13px; font-weight: 500; border-bottom: 1px solid var(--line); }
        td { font-size: 14px; border-bottom: 1px solid rgba(255,255,255,.05); }
        tr:last-child td { border-bottom: 0; }
        tbody tr:hover td { background: rgba(255,255,255,.035); }
        .money { color: var(--mint); font-weight: 600; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .b-setor { color: var(--mint); background: rgba(16,185,129,.15); border: 1px solid rgba(52,211,153,.35); }
        .b-tarik { color: var(--amber); background: rgba(245,158,11,.15); border: 1px solid rgba(245,158,11,.35); }
        .empty { text-align: center; color: var(--mute); padding: 38px; }
        .muted { color: var(--soft); }

        @media (max-width: 900px) {
            .navbar { flex-wrap: wrap; padding: 12px 16px; row-gap: 10px; }
            .menu { order: 3; width: 100%; justify-content: flex-start; overflow-x: auto; flex-wrap: nowrap; }
            .navbar-user { margin-left: auto; } .user-meta { display: none; }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .wrap { padding: 0 16px; margin-top: 24px; }
            .hero { padding: 28px 24px; } .balance { width: 100%; }
        }
        @media (max-width: 480px) { .stats { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { .orb, .rise { animation: none; } .glass { transition: none; } }
    </style>
</head>
<body>
    <div class="orb a"></div><div class="orb b"></div><div class="orb c"></div>

    <div class="navbar">
        <a href="index.php" class="brand"><span>♻️</span> Bank Sampah Panji</a>
        <nav class="menu">
            <?php foreach ($_items as [$key, $href, $icon, $label, $roles]): ?>
                <?php if ($roles === null || in_array($role, $roles, true)): ?>
                    <a href="<?= $href ?>" class="nav-link <?= $key === "dashboard" ? "active" : "" ?>"><span><?= $icon ?></span><?= $label ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="navbar-user">
            <div class="user-chip">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($me["nama"] ?: "?", 0, 1))) ?></span>
                <span class="user-meta"><strong><?= e($me["nama"]) ?></strong><small><?= e($role) ?></small></span>
            </div>
            <a href="logout.php" class="logout" title="Keluar">⏻</a>
        </div>
    </div>

    <main class="wrap">
        <section class="glass hero rise">
            <div>
                <div class="date"><?= e($tanggal) ?></div>
                <h1><?= e($salam) ?>, <?= e($me["nama"]) ?></h1>
                <p class="desc"><?= e($deskripsi) ?></p>
                <span class="role"><?= e($role) ?></span>
            </div>
            <div class="balance">
                <div class="lbl"><?= ico("wallet") ?> Saldo Anda</div>
                <div class="val" data-rp="<?= (float) $me["saldo"] ?>"><?= rp($me["saldo"]) ?></div>
            </div>
        </section>

        <section class="stats">
            <?php foreach ($stats as $i => [$ic, $val, $dec, $lbl]): ?>
                <div class="glass stat rise" style="--i:<?= $i + 1 ?>">
                    <div class="ic"><?= ico($ic) ?></div>
                    <div class="n"><?= number_format($val, $dec, ",", ".") ?></div>
                    <div class="t"><?= e($lbl) ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="rise" style="--i:5">
            <h2>Menu cepat</h2>
            <div class="quick">
                <?php foreach ($menu as [$href, $ic, $judul, $ket, $roles]): ?>
                    <?php if ($roles === null || in_array($role, $roles, true)): ?>
                        <a class="glass q" href="<?= $href ?>">
                            <div class="ic"><?= ico($ic) ?></div>
                            <div><b><?= e($judul) ?></b><span><?= e($ket) ?></span></div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="rise" style="--i:6">
            <h2>Transaksi terbaru <a class="more" href="transaksi/index.php">Lihat semua <?= ico("arrow") ?></a></h2>
            <div class="glass table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php if ($admin): ?><th>Pengguna</th><?php endif; ?>
                            <th>Jenis sampah</th><th>Tipe</th><th>Berat</th><th>Total</th><th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recent): ?>
                            <tr><td colspan="6" class="empty">Belum ada transaksi. Mulai dari menu Transaksi.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recent as $r): $ts = strtotime($r["tanggal"]); ?>
                            <tr>
                                <?php if ($admin): ?><td><strong><?= e($r["nama"]) ?></strong></td><?php endif; ?>
                                <td><?= e($r["Jenis_sampah"]) ?></td>
                                <td><span class="badge b-<?= e($r["tipe"]) ?>"><?= $r["tipe"] === "tarik" ? "Beli" : "Setor" ?></span></td>
                                <td><?= number_format((float) $r["berat_kg"], 2, ",", ".") ?> kg</td>
                                <td class="money"><?= rp($r["total_rp"]) ?></td>
                                <td class="muted"><?= date("j", $ts) . " " . $bulan[(int) date("n", $ts)] . " " . date("Y, H:i", $ts) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // Kilau kaca mengikuti kursor
        document.querySelectorAll('.glass').forEach(function (el) {
            el.addEventListener('pointermove', function (ev) {
                var r = el.getBoundingClientRect();
                el.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
                el.style.setProperty('--my', (ev.clientY - r.top) + 'px');
            });
        });
        // Saldo naik pelan saat halaman dibuka
        (function () {
            var el = document.querySelector('[data-rp]');
            if (!el || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var to = parseFloat(el.dataset.rp), t0 = null;
            function step(t) {
                t0 = t0 || t;
                var p = Math.min((t - t0) / 900, 1), v = to * (1 - Math.pow(1 - p, 3));
                el.textContent = 'Rp ' + Math.round(v).toLocaleString('id-ID');
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        })();
    </script>
</body>
</html>
