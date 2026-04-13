<?php
// login.php
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

// Proses login
if (isset($_POST['login'])) {
    // Ambil dan bersihkan input
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    
    // Validasi input
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        // Cari user berdasarkan email
        $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Cek status akun
            if ($user['status'] !== 'active') {
                $error = "Akun Anda tidak aktif. Hubungi administrator.";
            } 
            // Verifikasi password
            else if (verifyPassword($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Redirect ke dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Email atau password salah!";
            }
        } else {
            $error = "Email atau password salah!";
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Login - SELARAS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --accent-blue: #3b82f6;
            --accent-blue-dark: #2563eb;
            --accent-blue-light: #60a5fa;
            --text-primary: #2d3748;
            --text-secondary: #718096;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 50%, #e0e7ff 100%);
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Circles */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            top: -250px;
            left: -250px;
            animation: float 8s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            bottom: -200px;
            right: -200px;
            animation: float 10s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) translateX(0px);
            }
            25% {
                transform: translateY(-20px) translateX(10px);
            }
            50% {
                transform: translateY(-10px) translateX(-10px);
            }
            75% {
                transform: translateY(10px) translateX(5px);
            }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 950px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(59, 130, 246, 0.25);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
            display: flex;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100%;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -125px;
            right: -125px;
            animation: pulse 4s ease-in-out infinite;
        }

        .logo-section::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            bottom: -100px;
            left: -100px;
            animation: pulse 6s ease-in-out infinite reverse;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        .logo-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
            overflow: hidden;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% {
                transform: translateY(0) rotate(0deg);
            }
            50% {
                transform: translateY(-10px) rotate(5deg);
            }
        }

        .logo-wrapper img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            filter: drop-shadow(2px 2px 4px rgba(59, 130, 246, 0.2));
        }

        .institution-name {
            color: white;
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
            letter-spacing: 1px;
        }

        .institution-tagline {
            color: rgba(255, 255, 255, 0.95);
            font-size: 14px;
            margin-top: 8px;
            position: relative;
            z-index: 1;
            line-height: 1.5;
        }

        .form-section {
            padding: 40px 35px;
            flex: 1.5;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
        }

        .form-title {
            text-align: center;
            color: var(--text-primary);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
            animation: fadeIn 0.6s ease-out backwards;
        }

        .form-group:nth-child(1) {
            animation-delay: 0.1s;
        }

        .form-group:nth-child(2) {
            animation-delay: 0.2s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-group label {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-blue);
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .form-control {
            height: 50px;
            padding-left: 45px;
            border: 2px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            outline: none;
            background: white;
        }

        .form-control:focus + .input-icon {
            color: var(--accent-blue-dark);
            transform: translateY(-50%) scale(1.1);
        }

        .btn-login {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out 0.3s backwards;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
            animation: fadeIn 0.6s ease-out 0.4s backwards;
        }

        .forgot-password a {
            color: var(--accent-blue);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .forgot-password a:hover {
            color: var(--accent-blue-dark);
            text-decoration: underline;
        }

        .register-link {
            text-align: center;
            margin-top: 15px;
            color: var(--text-secondary);
            font-size: 14px;
            animation: fadeIn 0.6s ease-out 0.5s backwards;
        }

        .register-link a {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            color: var(--accent-blue-dark);
            text-decoration: underline;
        }

        .alert {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideDown 0.4s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--accent-blue);
            font-size: 18px;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--accent-blue-dark);
            transform: translateY(-50%) scale(1.2);
        }

        /* Decorative Elements */
        .decorative-circle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }

        .circle-1 {
            width: 60px;
            height: 60px;
            background: rgba(59, 130, 246, 0.1);
            top: 10%;
            right: 5%;
            animation: float 6s ease-in-out infinite;
        }

        .circle-2 {
            width: 40px;
            height: 40px;
            background: rgba(96, 165, 250, 0.1);
            bottom: 15%;
            right: 10%;
            animation: float 8s ease-in-out infinite reverse;
        }

        /* Loading Animation */
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
            }

            .logo-section {
                padding: 30px 20px;
            }

            .logo-wrapper {
                width: 80px;
                height: 80px;
            }

            .logo-wrapper img {
                width: 60px;
                height: 60px;
            }

            .form-section {
                padding: 30px 25px;
            }

            .institution-name {
                font-size: 20px;
            }

            .institution-tagline {
                font-size: 13px;
            }

            .form-title {
                font-size: 24px;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 15px;
            }

            .logo-wrapper {
                width: 70px;
                height: 70px;
            }

            .logo-wrapper img {
                width: 50px;
                height: 50px;
            }

            .institution-name {
                font-size: 18px;
            }

            .form-section {
                padding: 25px 20px;
            }

            .form-title {
                font-size: 22px;
            }

            .form-control {
                height: 45px;
            }

            .btn-login {
                height: 45px;
            }
        }
    </style>
</head>
<body>
    <div class="decorative-circle circle-1"></div>
    <div class="decorative-circle circle-2"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-wrapper">
                    <img src="assets/img/Setjen_DPDRI.png" alt="Logo SELARAS">
                </div>
                <h2 class="institution-name">SELARAS</h2>
                <p class="institution-tagline">Sistem Elektronik Pengelolaan Barang Pakai Habis Kantor DPD RI Prov Kalbar</p>
            </div>
            
            <div class="form-section">
                <h3 class="form-title">Selamat Datang</h3>
                <p class="form-subtitle">Silakan masuk untuk melanjutkan</p>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php 
                            echo $_SESSION['success_message']; 
                            unset($_SESSION['success_message']);
                        ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" id="loginForm">
                    <div class="form-group">
                        <label for="inputEmailAddress">Email</label>
                        <div class="input-wrapper">
                            <input 
                                class="form-control" 
                                name="email" 
                                id="inputEmailAddress" 
                                type="email" 
                                placeholder="Masukkan email Anda"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                required
                            />
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="inputPassword">Password</label>
                        <div class="input-wrapper">
                            <input 
                                class="form-control" 
                                name="password" 
                                id="inputPassword" 
                                type="password" 
                                placeholder="Masukkan password Anda"
                                required
                            />
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                        </div>
                    </div>
                    
                    <button class="btn-login" name="login" type="submit" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                    
                    <div class="forgot-password">
                        <a href="forgot_password.php">Lupa password?</a>
                    </div>
                    
                    <div class="register-link">
                        Belum punya akun? <a href="register.php">Daftar di sini</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('inputPassword');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Add loading animation on form submit
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        loginForm.addEventListener('submit', function() {
            loginBtn.classList.add('loading');
            loginBtn.innerHTML = '<span style="opacity: 0;">Loading...</span>';
        });

        // Add focus animation to input fields
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('.input-icon').style.color = '#2563eb';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.querySelector('.input-icon').style.color = '#3b82f6';
                }
            });
        });
    </script>
</body>
</html>