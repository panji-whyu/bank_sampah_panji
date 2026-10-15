<?php
session_start();
require "koneksi.php";

if (isset($_SESSION["id_user"])) {
    header("Location: index.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = mysqli_real_escape_string($koneksi, $_POST["username"]);
    $password = $_POST["password"];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($koneksi, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if ($password === $user["password"] || password_verify($password, $user["password"])) {
            $_SESSION["id_user"]  = $user["id_user"];
            $_SESSION["nama"]     = $user["nama"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"]     = $user["role"];

            header("Location: index.php");
            exit();
        } else {
            $error = "Password yang Anda masukkan salah!";
        }
    } else {
        $error = "Username tidak ditemukan!";
    }
}

// Ambil daftar user untuk Quick Select
$users_list = [];
$qUsers = mysqli_query($koneksi, "SELECT id_user, nama, username, role, password FROM users ORDER BY id_user ASC LIMIT 6");
if ($qUsers) {
    while ($r = mysqli_fetch_assoc($qUsers)) {
        $users_list[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &mdash; Bank Sampah Panji</title>

    <!-- Font Modern & Premium: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Warna diselaraskan persis dengan Dashboard index.php */
            --bg-body: #0c322a;
            --card-bg: transparent;
            --hero-bg: rgba(255,255,255,.04);
            --input-bg: rgba(6,44,33,.5);
            --border-color: rgba(255,255,255,.13);
            --border-focus: #10b981;
            --primary: #10b981;
            --primary-hover: #34d399;
            --text-heading: #ffffff;
            --text-body: #94a3b8;
            --text-muted: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            /* Nuansa background charcoal green khas Dashboard */
            background: radial-gradient(1200px 700px at 12% -10%, #1b8a68 0%, transparent 60%), radial-gradient(1000px 700px at 100% 100%, #12766c 0%, transparent 60%), linear-gradient(160deg, #114536, #0c322a 55%, #0f423c);
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        /* Container Card Utama */
        .login-card-wrapper {
            width: 100%;
            max-width: 1000px;
            position: relative;
            z-index: 1;
            background: linear-gradient(145deg, rgba(255,255,255,.15), rgba(255,255,255,.05));
            backdrop-filter: blur(28px) saturate(180%);
            -webkit-backdrop-filter: blur(28px) saturate(180%);
            border: 1px solid var(--border-color);
            border-radius: 28px;
            display: flex;
            overflow: hidden;
            box-shadow: inset 0 1px 1px rgba(255,255,255,.3), 0 30px 70px -20px rgba(0, 0, 0, 0.6);
        }

        /* Cahaya hijau bergerak pelan di belakang kaca */
        body::before, body::after {
            content: "";
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: 520px; height: 520px; top: -160px; left: -120px;
            background: radial-gradient(circle, rgba(52,211,153,.7), transparent 70%);
            animation: drift 22s ease-in-out infinite alternate;
        }
        body::after {
            width: 460px; height: 460px; bottom: -140px; right: -100px;
            background: radial-gradient(circle, rgba(45,212,191,.55), transparent 70%);
            animation: drift 28s ease-in-out infinite alternate-reverse;
        }
        @keyframes drift { to { transform: translate(90px, 60px) scale(1.15); } }
        @media (prefers-reduced-motion: reduce) { body::before, body::after { animation: none; } }

        /* --- HERO PANEL (SISI KIRI) --- */
        .hero-panel {
            flex: 1;
            background: var(--hero-bg);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid var(--border-color);
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 18px;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid var(--border-color);
            border-radius: 100px;
            color: #34d399;
            font-size: 14px;
            font-weight: 700;
            width: fit-content;
        }

        .hero-body {
            margin: 40px 0;
        }

        .hero-title {
            font-size: 36px;
            font-weight: 800;
            line-height: 1.25;
            color: #ffffff;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .hero-title span {
            color: #34d399;
        }

        .hero-desc {
            font-size: 15px;
            color: var(--text-body);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .stat-box {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            padding: 18px 20px;
            border-radius: 16px;
        }

        .stat-val {
            font-size: 18px;
            font-weight: 800;
            color: #34d399;
            margin-bottom: 4px;
        }

        .stat-lbl {
            font-size: 13px;
            color: var(--text-body);
        }

        .hero-footer {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* --- FORM PANEL (SISI KANAN) --- */
        .form-panel {
            flex: 1.15;
            padding: 48px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--card-bg);
        }

        .form-header {
            margin-bottom: 28px;
        }

        .form-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .form-header p {
            font-size: 15px;
            color: var(--text-body);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        /* Quick Account Selector */
        .quick-section {
            margin-bottom: 26px;
        }

        .quick-label {
            font-size: 12px;
            font-weight: 800;
            color: #34d399;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .quick-pill {
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            padding: 12px 14px;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .quick-pill:hover {
            background: rgba(16, 185, 129, 0.15);
            border-color: #34d399;
            transform: translateY(-2px);
        }

        .pill-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: #10b981;
            color: #042f22;
            font-size: 15px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pill-info {
            overflow: hidden;
            line-height: 1.35;
        }

        .pill-name {
            font-size: 14px;
            font-weight: 700;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pill-sub {
            font-size: 12px;
            color: var(--text-body);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 24px 0;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.8px;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }

        .divider span {
            padding: 0 12px;
        }

        /* Form Input */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .custom-input {
            width: 100%;
            padding: 16px 18px;
            background: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            font-family: inherit;
            font-size: 15px;
            color: #ffffff;
            outline: none;
            transition: all 0.2s ease;
        }

        .custom-input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        }

        .custom-input::placeholder {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Button Submit */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #10b981;
            color: #042f22;
            border: none;
            border-radius: 14px;
            font-family: inherit;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: #34d399;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25);
        }

        @media (max-width: 850px) {
            .login-card-wrapper {
                flex-direction: column;
            }
            .hero-panel, .form-panel {
                padding: 32px 24px;
            }
            .quick-grid {
                grid-template-columns: 1fr;
            }
            .hero-title {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card-wrapper">
        
        <!-- SISI KIRI: HERO SECTION -->
        <div class="hero-panel">
            <div>
                <div class="brand-badge">
                    <span>♻️</span> Bank Sampah Panji
                </div>

                <div class="hero-body">
                    <h2 class="hero-title">Kelola Sampah,<br><span>Tingkatkan Tabungan.</span></h2>
                    <p class="hero-desc">
                        Ubah sampah menjadi nilai ekonomis. Sistem terintegrasi untuk pencatatan transaksi, penyetoran, dan penarikan saldo secara akurat.
                    </p>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-val">100%</div>
                            <div class="stat-lbl">Transparan & Realtime</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-val">#EcoSystem</div>
                            <div class="stat-lbl">Jaga Lingkungan Kita</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hero-footer">
                Panji Eco-System &bull; Bank Sampah Digital &copy; <?= date('Y') ?>
            </div>
        </div>

        <!-- SISI KANAN: FORM LOGIN -->
        <div class="form-panel">
            <div class="form-header">
                <h1>Selamat Datang! 👋</h1>
                <p>Silakan masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- PILIH AKUN CEPAT -->
            <?php if (!empty($users_list)): ?>
                <div class="quick-section">
                    <div class="quick-label">
                        <span>⚡</span> PILIH AKUN CEPAT
                    </div>
                    <div class="quick-grid">
                        <?php foreach ($users_list as $u): ?>
                            <div class="quick-pill" onclick="fillAccount('<?= htmlspecialchars($u['username']) ?>', '<?= htmlspecialchars($u['password']) ?>')">
                                <div class="pill-avatar"><?= strtoupper(substr($u['nama'], 0, 1)) ?></div>
                                <div class="pill-info">
                                    <div class="pill-name"><?= htmlspecialchars($u['nama']) ?></div>
                                    <div class="pill-sub">@<?= htmlspecialchars($u['username']) ?> &bull; <?= ucfirst($u['role']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="divider">
                    <span>ATAU MASUK MANUAL</span>
                </div>
            <?php endif; ?>

            <!-- FORM LOGIN MANUAL -->
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="custom-input" placeholder="Masukkan username Anda" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="custom-input" placeholder="Masukkan password Anda" required>
                </div>

                <button type="submit" class="btn-submit">
                    <span>Masuk ke Akun</span> &rarr;
                </button>
            </form>
        </div>

    </div>

    <script>
        function fillAccount(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('password').focus();
        }
    </script>

</body>
</html>