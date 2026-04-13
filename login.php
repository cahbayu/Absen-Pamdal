<?php
// login.php
require_once 'config.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if (isset($_POST['login'])) {
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        $stmt = $conn->prepare("SELECT id, nama, username, password, role, aktif FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (!$user['aktif']) {
                $error = "Akun Anda tidak aktif. Hubungi administrator.";
            } elseif (verifyPassword($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['login_time'] = time();

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Username atau password salah!";
            }
        } else {
            $error = "Username atau password salah!";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Login — Sistem Absensi Pamdal</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --brown-950: #1c0f05;
            --brown-900: #2d1a0a;
            --brown-800: #4a2c14;
            --brown-700: #6b3f1e;
            --brown-600: #8b5a2b;
            --brown-500: #a96f3a;
            --brown-400: #c8904f;
            --brown-300: #ddb07a;
            --brown-200: #edd4af;
            --brown-100: #f7ead8;
            --brown-50:  #fdf5ec;
            --cream:     #fef9f2;
            --gold:      #c9a84c;
            --gold-light:#e8cb7e;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--brown-950);
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            position: relative;
            overflow: hidden;
        }

        /* Noise texture overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.6;
        }

        /* Ambient glow blobs */
        .glow-1 {
            position: fixed;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(169,111,58,0.18) 0%, transparent 70%);
            top: -200px; left: -100px;
            border-radius: 50%;
            animation: drift1 12s ease-in-out infinite;
            pointer-events: none;
        }
        .glow-2 {
            position: fixed;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%);
            bottom: -150px; right: -100px;
            border-radius: 50%;
            animation: drift2 15s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes drift1 {
            0%,100% { transform: translate(0,0) scale(1); }
            50%      { transform: translate(30px,-40px) scale(1.1); }
        }
        @keyframes drift2 {
            0%,100% { transform: translate(0,0) scale(1); }
            50%      { transform: translate(-20px,30px) scale(1.08); }
        }

        .page-wrap {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            width: 42%;
            background: linear-gradient(160deg, var(--brown-800) 0%, var(--brown-900) 60%, var(--brown-950) 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 50px 44px;
            position: relative;
            overflow: hidden;
            border-right: 1px solid rgba(201,168,76,0.15);
        }

        .left-panel::after {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9a84c' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
            opacity: 0.8;
        }

        .brand {
            position: relative;
            z-index: 2;
            animation: fadeUp 0.8s ease both;
        }

        .logo-mark {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--gold) 0%, var(--brown-400) 100%);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--brown-950);
            font-weight: 700;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(201,168,76,0.3);
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: var(--cream);
            font-weight: 700;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .brand-sub {
            font-size: 13px;
            color: var(--brown-300);
            margin-top: 8px;
            line-height: 1.6;
            font-weight: 300;
            max-width: 280px;
        }

        .left-mid {
            position: relative;
            z-index: 2;
            animation: fadeUp 0.8s 0.15s ease both;
        }

        .quote-decor {
            font-family: 'Playfair Display', serif;
            font-size: 80px;
            color: var(--gold);
            opacity: 0.25;
            line-height: 0.7;
            margin-bottom: 12px;
        }
        .quote-text {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: var(--brown-100);
            line-height: 1.65;
            font-style: italic;
        }

        .shift-badges {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            animation: fadeUp 0.8s 0.3s ease both;
        }
        .shift-badge {
            padding: 7px 16px;
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 99px;
            font-size: 12px;
            font-weight: 500;
            color: var(--brown-200);
            background: rgba(201,168,76,0.07);
            letter-spacing: 0.5px;
        }
        .shift-badge.active {
            background: rgba(201,168,76,0.18);
            border-color: var(--gold);
            color: var(--gold-light);
        }

        /* ── RIGHT PANEL ── */
        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 32px;
            background: var(--cream);
            position: relative;
        }

        /* Warm corner accent */
        .right-panel::before {
            content: '';
            position: absolute;
            width: 320px; height: 320px;
            background: radial-gradient(circle, rgba(169,111,58,0.08) 0%, transparent 70%);
            top: -60px; right: -60px;
            border-radius: 50%;
            pointer-events: none;
        }

        .form-card {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
        }

        .form-header {
            margin-bottom: 36px;
            animation: fadeUp 0.7s 0.1s ease both;
        }
        .form-eyebrow {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--brown-500);
            margin-bottom: 10px;
        }
        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            color: var(--brown-900);
            font-weight: 700;
            line-height: 1.2;
        }
        .form-title span { color: var(--brown-500); }

        .form-desc {
            font-size: 14px;
            color: var(--brown-600);
            margin-top: 8px;
            font-weight: 300;
        }

        .alert {
            padding: 13px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 22px;
            display: flex; align-items: center; gap: 10px;
            animation: shakeIn 0.4s ease;
        }
        .alert-danger {
            background: rgba(155,40,40,0.07);
            border: 1px solid rgba(155,40,40,0.2);
            color: #7a1f1f;
        }
        .alert-success {
            background: rgba(74,108,60,0.07);
            border: 1px solid rgba(74,108,60,0.2);
            color: #2d4a22;
        }
        @keyframes shakeIn {
            0%   { transform: translateX(-6px); opacity: 0; }
            40%  { transform: translateX(4px); }
            70%  { transform: translateX(-2px); }
            100% { transform: translateX(0); opacity: 1; }
        }

        .field-group {
            margin-bottom: 22px;
            animation: fadeUp 0.6s ease both;
        }
        .field-group:nth-child(1) { animation-delay: 0.2s; }
        .field-group:nth-child(2) { animation-delay: 0.3s; }
        .field-group:nth-child(3) { animation-delay: 0.4s; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--brown-800);
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .input-wrap {
            position: relative;
        }
        .input-wrap svg {
            position: absolute;
            left: 15px; top: 50%;
            transform: translateY(-50%);
            width: 17px; height: 17px;
            color: var(--brown-400);
            pointer-events: none;
            transition: color 0.3s;
        }
        .field-input {
            width: 100%;
            height: 50px;
            padding: 0 44px 0 44px;
            border: 1.5px solid var(--brown-200);
            border-radius: 12px;
            font-size: 14.5px;
            font-family: 'DM Sans', sans-serif;
            color: var(--brown-900);
            background: #fff;
            transition: all 0.3s;
            outline: none;
        }
        .field-input::placeholder { color: var(--brown-300); }
        .field-input:focus {
            border-color: var(--brown-500);
            box-shadow: 0 0 0 4px rgba(169,111,58,0.1);
            background: #fffaf5;
        }
        .field-input:focus ~ svg,
        .input-wrap:focus-within svg { color: var(--brown-600); }

        .toggle-pw {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--brown-300);
            transition: color 0.3s;
            background: none; border: none;
            padding: 4px;
            display: flex; align-items: center;
        }
        .toggle-pw:hover { color: var(--brown-600); }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, var(--brown-600) 0%, var(--brown-800) 100%);
            color: var(--cream);
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            letter-spacing: 0.5px;
            box-shadow: 0 6px 20px rgba(74,44,20,0.35);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            margin-top: 6px;
            animation: fadeUp 0.6s 0.45s ease both;
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(74,44,20,0.45);
        }
        .btn-submit:active { transform: translateY(0); }

        .divider {
            display: flex; align-items: center; gap: 14px;
            margin: 24px 0;
            animation: fadeUp 0.6s 0.5s ease both;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px;
            background: var(--brown-200);
        }
        .divider span {
            font-size: 12px;
            color: var(--brown-300);
            white-space: nowrap;
        }

        .role-hint {
            animation: fadeUp 0.6s 0.55s ease both;
            background: rgba(169,111,58,0.06);
            border: 1px dashed var(--brown-200);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .role-hint p {
            font-size: 12.5px;
            color: var(--brown-600);
            margin: 0;
        }
        .role-hint strong { color: var(--brown-700); }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--brown-400);
            animation: fadeUp 0.6s 0.6s ease both;
        }

        /* Responsive */
        @media (max-width: 860px) {
            .left-panel { display: none; }
            .right-panel { background: var(--brown-950); }
            .form-card { max-width: 360px; }
            .form-title { color: var(--cream); }
            .form-eyebrow { color: var(--brown-300); }
            .form-desc { color: var(--brown-300); }
            .field-label { color: var(--brown-200); }
            .field-input {
                background: rgba(255,255,255,0.07);
                border-color: rgba(201,168,76,0.2);
                color: var(--cream);
            }
            .field-input::placeholder { color: var(--brown-500); }
            .field-input:focus { background: rgba(255,255,255,0.1); box-shadow: 0 0 0 4px rgba(201,168,76,0.12); }
            .input-wrap svg { color: var(--brown-500); }
            .role-hint { background: rgba(255,255,255,0.04); border-color: rgba(201,168,76,0.15); }
            .role-hint p, .role-hint strong { color: var(--brown-300); }
            .form-footer { color: var(--brown-500); }
        }
        @media (max-width: 480px) {
            .right-panel { padding: 30px 20px; }
        }
    </style>
</head>
<body>
<div class="glow-1"></div>
<div class="glow-2"></div>

<div class="page-wrap">

    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div class="brand">
            <div class="logo-mark">A</div>
            <div class="brand-name">Sistem Absensi<br>Pamdal</div>
            <p class="brand-sub">Pengelolaan kehadiran & laporan harian petugas keamanan secara digital.</p>
        </div>

        <div class="left-mid">
            <div class="quote-decor">"</div>
            <p class="quote-text">Disiplin adalah jembatan antara tujuan dan pencapaian. Hadir tepat waktu adalah bentuk tanggung jawab tertinggi.</p>
        </div>

        <div class="shift-badges">
            <span class="shift-badge active">Shift Pagi 07:30</span>
            <span class="shift-badge">Shift Siang 11:00</span>
            <span class="shift-badge">Shift Malam 20:00</span>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div class="form-card">

            <div class="form-header">
                <p class="form-eyebrow">Portal Masuk</p>
                <h1 class="form-title">Selamat <span>Datang</span></h1>
                <p class="form-desc">Masuk dengan akun pamdal atau kepala Anda.</p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="flex-shrink:0"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18" style="flex-shrink:0"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>

            <form method="post" action="" id="loginForm">
                <div class="field-group">
                    <label class="field-label" for="inputUsername">Username</label>
                    <div class="input-wrap">
                        <input
                            class="field-input"
                            type="text"
                            id="inputUsername"
                            name="username"
                            placeholder="Masukkan username Anda"
                            value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                            autocomplete="username"
                            required
                        >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="inputPassword">Password</label>
                    <div class="input-wrap">
                        <input
                            class="field-input"
                            type="password"
                            id="inputPassword"
                            name="password"
                            placeholder="Masukkan password Anda"
                            autocomplete="current-password"
                            required
                        >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Tampilkan password">
                            <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>
                </div>

                <div class="field-group">
                    <button class="btn-submit" type="submit" name="login" id="loginBtn">
                        Masuk ke Sistem
                    </button>
                </div>
            </form>

            <div class="divider"><span>Info Akun</span></div>

            <div class="role-hint">
                <p><strong>Kepala:</strong> Memiliki akses review laporan & manajemen shift.</p>
                <p style="margin-top:6px"><strong>Pamdal:</strong> Absen masuk/keluar & submit laporan harian.</p>
            </div>

            <p class="form-footer">Sistem Absensi Pamdal &copy; <?= date('Y') ?></p>
        </div>
    </div>

</div>

<script>
    // Toggle password
    const togglePw = document.getElementById('togglePw');
    const pwInput  = document.getElementById('inputPassword');
    const eyeOpen  = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');
    togglePw.addEventListener('click', () => {
        const show = pwInput.type === 'password';
        pwInput.type = show ? 'text' : 'password';
        eyeOpen.style.display  = show ? 'none' : 'block';
        eyeClosed.style.display = show ? 'block' : 'none';
    });

    // Loading state
    document.getElementById('loginForm').addEventListener('submit', () => {
        const btn = document.getElementById('loginBtn');
        btn.textContent = 'Memverifikasi...';
        btn.style.opacity = '0.8';
        btn.disabled = true;
    });
</script>
</body>
</html>