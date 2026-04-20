<?php
// login.php
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header("Location: " . getDashboardUrl());
    exit();
}

$error = '';

// Proses login
if (isset($_POST['login'])) {
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username dan password harus diisi!";
    } else {
        $stmt = $conn->prepare("SELECT id, name, username, password, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if ($user['status'] !== 'active') {
                $error = "Akun Anda tidak aktif. Hubungi administrator.";
            } else if (verifyPassword($password, $user['password'])) {
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['name']       = $user['name'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['login_time'] = time();

                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                header("Location: " . getDashboardUrl());
                exit();;
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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Login — Absensi Pamdal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --brown:          #5C3317;
            --brown-deep:     #3B1F0A;
            --brown-mid:      #7A4520;
            --brown-light:    #A0622E;
            --brown-pale:     #F5EDE3;
            --gold:           #C9954A;
            --gold-light:     #E8B87A;
            --cream:          #FDF8F1;
            --text-dark:      #2C1A08;
            --text-mid:       #6B4020;
            --text-light:     #9E7050;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--brown-deep);
            background-image:
                radial-gradient(ellipse at 15% 20%, rgba(122,69,32,0.45) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 80%, rgba(59,31,10,0.7)  0%, transparent 55%),
                radial-gradient(ellipse at 50% 50%, rgba(40,20,5,0.4)   0%, transparent 70%);
            overflow: hidden;
            position: relative;
        }

        /* Geometric pattern background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                repeating-linear-gradient(
                    45deg,
                    transparent,
                    transparent 40px,
                    rgba(201,149,74,0.05) 40px,
                    rgba(201,149,74,0.05) 41px
                ),
                repeating-linear-gradient(
                    -45deg,
                    transparent,
                    transparent 40px,
                    rgba(201,149,74,0.05) 40px,
                    rgba(201,149,74,0.05) 41px
                );
            pointer-events: none;
        }

        /* Floating orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(60px);
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: rgba(122,69,32,0.3);
            top: -120px; left: -100px;
            animation: drift 12s ease-in-out infinite;
        }
        .orb-2 {
            width: 300px; height: 300px;
            background: rgba(201,149,74,0.15);
            bottom: -80px; right: -80px;
            animation: drift 15s ease-in-out infinite reverse;
        }
        .orb-3 {
            width: 200px; height: 200px;
            background: rgba(92,51,23,0.35);
            top: 50%; left: 60%;
            animation: drift 10s ease-in-out infinite 3s;
        }

        @keyframes drift {
            0%,100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(30px,-20px) scale(1.05); }
            66%      { transform: translate(-20px,15px) scale(0.95); }
        }

        /* Card wrapper */
        .login-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 980px;
            padding: 24px;
        }

        .login-card {
            display: flex;
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 40px 80px rgba(0,0,0,0.55),
                0 0 0 1px rgba(201,149,74,0.2),
                inset 0 1px 0 rgba(255,255,255,0.05);
            animation: riseUp 0.7s cubic-bezier(0.16,1,0.3,1) both;
        }

        @keyframes riseUp {
            from { opacity: 0; transform: translateY(40px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── Left Panel ── */
        .panel-left {
            flex: 0 0 380px;
            background: linear-gradient(160deg, var(--brown-mid) 0%, var(--brown-deep) 60%, #1A0A02 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            width: 280px; height: 280px;
            border-radius: 50%;
            border: 1px solid rgba(201,149,74,0.18);
            top: -80px; right: -80px;
        }
        .panel-left::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            border: 1px solid rgba(201,149,74,0.12);
            bottom: -60px; left: -60px;
        }

        .brand-area {
            position: relative;
            z-index: 2;
        }

        .logo-ring {
            width: 88px; height: 88px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            border: 2px solid rgba(201,149,74,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            animation: pulse-ring 3s ease-in-out infinite;
        }

        @keyframes pulse-ring {
            0%,100% { box-shadow: 0 0 0 0 rgba(201,149,74,0.35); }
            50%      { box-shadow: 0 0 0 12px rgba(201,149,74,0); }
        }

        .logo-ring img {
            width: 56px; height: 56px;
            object-fit: contain;
            filter: brightness(1.1);
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 800;
            color: #fff;
            letter-spacing: 2px;
            line-height: 1;
            margin-bottom: 6px;
        }

        .brand-sub {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 3px;
            color: var(--gold);
            text-transform: uppercase;
            margin-bottom: 20px;
        }

        .brand-desc {
            font-size: 13px;
            color: rgba(255,255,255,0.55);
            line-height: 1.7;
            max-width: 260px;
        }

        .divider-gold {
            width: 40px;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), transparent);
            margin: 18px 0;
        }

        /* Shift info */
        .shift-list {
            position: relative;
            z-index: 2;
        }

        .shift-label {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 12px;
            font-weight: 600;
        }

        .shift-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .shift-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--gold-light);
            flex-shrink: 0;
        }

        .shift-text {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            font-weight: 400;
        }

        .shift-name {
            color: rgba(255,255,255,0.85);
            font-weight: 500;
        }

        /* ── Right Panel ── */
        .panel-right {
            flex: 1;
            background: var(--cream);
            padding: 52px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-head {
            margin-bottom: 36px;
        }

        .form-eyebrow {
            font-size: 11px;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--brown-light);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--brown-deep);
            line-height: 1.1;
        }

        .form-title span {
            color: var(--brown-mid);
        }

        /* Alert */
        .alert-custom {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 24px;
            animation: slideDown 0.35s ease;
            font-weight: 500;
        }
        .alert-custom.error {
            background: rgba(92,51,23,0.09);
            color: var(--brown);
            border: 1px solid rgba(92,51,23,0.22);
        }
        .alert-custom.success {
            background: rgba(30,120,60,0.08);
            color: #1a6035;
            border: 1px solid rgba(30,120,60,0.2);
        }
        @keyframes slideDown {
            from { opacity:0; transform: translateY(-8px); }
            to   { opacity:1; transform: translateY(0); }
        }

        /* Fields */
        .field-group { margin-bottom: 20px; }

        .field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-mid);
            margin-bottom: 8px;
        }

        .field-wrap { position: relative; }

        .field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--brown-light);
            font-size: 15px;
            transition: color 0.25s;
            pointer-events: none;
        }

        .field-input {
            width: 100%;
            height: 50px;
            padding: 0 44px 0 44px;
            border: 1.5px solid rgba(92,51,23,0.2);
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14.5px;
            color: var(--text-dark);
            background: #fff;
            transition: all 0.25s ease;
            outline: none;
        }

        .field-input::placeholder { color: rgba(92,51,23,0.32); }

        .field-input:focus {
            border-color: var(--brown-mid);
            box-shadow: 0 0 0 4px rgba(92,51,23,0.09);
        }

        .field-wrap:focus-within .field-icon { color: var(--brown); }

        .eye-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--brown-light);
            font-size: 15px;
            transition: color 0.25s;
        }
        .eye-toggle:hover { color: var(--brown); }

        /* Submit button */
        .btn-masuk {
            width: 100%;
            height: 52px;
            background: linear-gradient(135deg, var(--brown-mid) 0%, var(--brown-deep) 100%);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 6px 20px rgba(59,31,10,0.4);
            margin-top: 8px;
        }

        .btn-masuk::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, transparent 60%);
        }

        .btn-masuk::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.5s ease;
        }

        .btn-masuk:hover::after { left: 100%; }
        .btn-masuk:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(59,31,10,0.5);
        }
        .btn-masuk:active { transform: translateY(0); }
        .btn-masuk i { margin-right: 8px; }

        .accent-line {
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(201,149,74,0.4), transparent);
            margin: 28px 0;
        }

        .info-text {
            text-align: center;
            font-size: 12.5px;
            color: var(--text-light);
        }

        .info-text strong {
            color: var(--brown-mid);
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-card { flex-direction: column; }
            .panel-left { flex: none; padding: 36px 32px; }
            .shift-list { display: none; }
            .panel-right { padding: 36px 28px; }
        }

        @media (max-width: 480px) {
            .login-wrap { padding: 16px; }
            .panel-right { padding: 28px 20px; }
            .form-title { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="login-wrap">
        <div class="login-card">

            <!-- Panel Kiri -->
            <div class="panel-left">
                <div class="brand-area">
                    <div class="logo-ring">
                        <img src="assets/img/Setjen_DPDRI.png" alt="Logo DPD RI">
                    </div>
                    <div class="brand-sub">Sistem Absensi</div>
                    <div class="brand-name">ANDALAN</div>
                    <div class="divider-gold"></div>
                    <p class="brand-desc">
                        Sistem pencatatan kehadiran dan pelaporan harian petugas pengamanan dalam.
                    </p>
                </div>

                <div class="shift-list">
                    <div class="shift-label">Jadwal Shift</div>
                    <div class="shift-item">
                        <div class="shift-dot"></div>
                        <div class="shift-text"><span class="shift-name">Pagi &nbsp;&nbsp;</span> 07.30 – 16.30</div>
                    </div>
                    <div class="shift-item">
                        <div class="shift-dot"></div>
                        <div class="shift-text"><span class="shift-name">Siang &nbsp;</span> 11.00 – 20.00</div>
                    </div>
                    <div class="shift-item">
                        <div class="shift-dot"></div>
                        <div class="shift-text"><span class="shift-name">Malam</span> 20.00 – 07.30</div>
                    </div>
                </div>
            </div>

            <!-- Panel Kanan -->
            <div class="panel-right">
                <div class="form-head">
                    <div class="form-eyebrow">Portal Masuk</div>
                    <div class="form-title">Selamat <span>Datang</span></div>
                </div>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert-custom success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert-custom error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" id="loginForm" autocomplete="off">

                    <div class="field-group">
                        <label class="field-label" for="inputUsername">Username</label>
                        <div class="field-wrap">
                            <input
                                class="field-input"
                                type="text"
                                name="username"
                                id="inputUsername"
                                placeholder="Masukkan username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                required
                                autocomplete="username"
                            />
                            <i class="fas fa-user field-icon"></i>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="inputPassword">Password</label>
                        <div class="field-wrap">
                            <input
                                class="field-input"
                                type="password"
                                name="password"
                                id="inputPassword"
                                placeholder="Masukkan password"
                                required
                                autocomplete="current-password"
                            />
                            <i class="fas fa-lock field-icon"></i>
                            <i class="fas fa-eye eye-toggle" id="eyeToggle"></i>
                        </div>
                    </div>

                    <button class="btn-masuk" type="submit" name="login" id="btnMasuk">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>

                </form>

                <div class="accent-line"></div>

                <div class="info-text">
                    Akun dibuat oleh <strong>Kepala Kantor</strong>.<br>
                    Jika Lupa Password Hubungi Kepala Kantor.
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const eyeToggle = document.getElementById('eyeToggle');
        const passwordInput = document.getElementById('inputPassword');

        eyeToggle.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Add loading animation on form submit
        const loginForm = document.getElementById('loginForm');
        const btnMasuk  = document.getElementById('btnMasuk');

        loginForm.addEventListener('submit', function() {
            btnMasuk.classList.add('loading');
            btnMasuk.innerHTML = '<span style="opacity: 0;">Loading...</span>';
        });
    </script>
</body>
</html>