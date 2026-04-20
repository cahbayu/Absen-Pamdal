<?php
// profil.php
require_once 'config.php';
require_once 'function.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ambil data user dari database
$user_id = $_SESSION['user_id'];
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_array($query);

// Update Profil
if(isset($_POST['updateprofil'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    
    // Cek apakah email sudah digunakan user lain
    $cek_email = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND id != '$user_id'");
    if(mysqli_num_rows($cek_email) > 0){
        echo '<script>alert("Email sudah digunakan oleh user lain!"); window.location.href="profil.php";</script>';
    } else {
        $update = mysqli_query($conn, "UPDATE users SET name='$name', email='$email' WHERE id='$user_id'");
        
        if($update){
            // Update session
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            echo '<script>alert("Profil berhasil diperbarui!"); window.location.href="profil.php";</script>';
        } else {
            echo '<script>alert("Gagal memperbarui profil!"); window.location.href="profil.php";</script>';
        }
    }
}

// Ganti Password
if(isset($_POST['gantipassword'])){
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    // Verifikasi password lama
    if(password_verify($password_lama, $user_data['password'])){
        // Cek apakah password baru sama dengan konfirmasi
        if($password_baru == $konfirmasi_password){
            // Hash password baru
            $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
            
            $update = mysqli_query($conn, "UPDATE users SET password='$hashed_password' WHERE id='$user_id'");
            
            if($update){
                echo '<script>alert("Password berhasil diubah!"); window.location.href="profil.php";</script>';
            } else {
                echo '<script>alert("Gagal mengubah password!"); window.location.href="profil.php";</script>';
            }
        } else {
            echo '<script>alert("Konfirmasi password tidak cocok!"); window.location.href="profil.php";</script>';
        }
    } else {
        echo '<script>alert("Password lama salah!"); window.location.href="profil.php";</script>';
    }
}

// FUNGSI SUPER ADMIN - Ubah Role User
if(isset($_POST['ubah_role']) && $user_data['role'] == 'super_admin'){
    $target_user_id = $_POST['target_user_id'];
    $new_role = $_POST['new_role'];
    
    // Pastikan tidak mengubah role diri sendiri
    if($target_user_id == $user_id){
        echo '<script>alert("Tidak dapat mengubah role akun sendiri!"); window.location.href="profil.php";</script>';
    } else {
        $update_role = mysqli_query($conn, "UPDATE users SET role='$new_role' WHERE id='$target_user_id'");
        
        if($update_role){
            echo '<script>alert("Role user berhasil diubah!"); window.location.href="profil.php";</script>';
        } else {
            echo '<script>alert("Gagal mengubah role user!"); window.location.href="profil.php";</script>';
        }
    }
}

// FUNGSI SUPER ADMIN - Ubah Status User (Aktif/Tidak Aktif)
if(isset($_POST['ubah_status']) && $user_data['role'] == 'super_admin'){
    $target_user_id = $_POST['target_user_id'];
    $new_status = $_POST['new_status'];
    
    // Pastikan tidak mengubah status diri sendiri
    if($target_user_id == $user_id){
        echo '<script>alert("Tidak dapat mengubah status akun sendiri!"); window.location.href="profil.php";</script>';
    } else {
        $update_status = mysqli_query($conn, "UPDATE users SET status='$new_status' WHERE id='$target_user_id'");
        
        if($update_status){
            echo '<script>alert("Status user berhasil diubah!"); window.location.href="profil.php";</script>';
        } else {
            echo '<script>alert("Gagal mengubah status user!"); window.location.href="profil.php";</script>';
        }
    }
}

// FUNGSI SUPER ADMIN - Hapus User
if(isset($_POST['hapus_user']) && $user_data['role'] == 'super_admin'){
    $target_user_id = $_POST['target_user_id'];
    
    // Pastikan tidak menghapus diri sendiri
    if($target_user_id == $user_id){
        echo '<script>alert("Tidak dapat menghapus akun sendiri!"); window.location.href="profil.php";</script>';
    } else {
        $delete_user = mysqli_query($conn, "DELETE FROM users WHERE id='$target_user_id'");
        
        if($delete_user){
            echo '<script>alert("User berhasil dihapus!"); window.location.href="profil.php";</script>';
        } else {
            echo '<script>alert("Gagal menghapus user!"); window.location.href="profil.php";</script>';
        }
    }
}

// Ambil semua user untuk Super Admin
if($user_data['role'] == 'super_admin'){
    $all_users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>Profil Pengguna - ANDALAN</title>
        <link href="css/styles.css" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css" rel="stylesheet" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js" crossorigin="anonymous"></script>
        <style>
/* profil.php - Modern Glass Morphism Style */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --bg-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --bg-secondary: #f0f7ff;
    --bg-white: #ffffff;
    --text-primary: #2d3748;
    --text-secondary: #718096;
    --border-color: #e2e8f0;
    --accent-blue: #3b82f6;
    --accent-green: #10b981;
    --accent-orange: #f59e0b;
    --accent-red: #ef4444;
    --accent-cyan: #06b6d4;
    --accent-purple: #8b5cf6;
    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.06);
    --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.15);
}

body {
    background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 50%, #e0e7ff 100%);
    background-attachment: fixed;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    color: var(--text-primary);
    line-height: 1.6;
    padding-top: 56px;
}

/* Navbar dengan Glass Effect */
.sb-topnav {
    background: rgba(255, 255, 255, 0.25) !important;
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-top: none;
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.15);
    padding: 0.75rem 1.5rem;
    z-index: 1040;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    display: flex !important;
    flex-wrap: nowrap;
    align-items: center;
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.35rem;
    color: #1e40af !important;
    display: flex !important;
    align-items: center;
    gap: 0.75rem;
    margin: 0 !important;
    padding: 0.5rem 0 !important;
    order: 1;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}

.navbar-brand:hover {
    transform: scale(1.05);
}

.brand-logo {
    height: 40px;
    width: auto;
    object-fit: contain;
    display: block;
    transition: transform 0.3s ease;
    filter: drop-shadow(2px 2px 4px rgba(59, 130, 246, 0.2));
}

.navbar-brand:hover .brand-logo {
    transform: rotate(5deg) scale(1.1);
}

.brand-text {
    font-weight: 700;
    font-size: 1.35rem;
    color: #1e40af;
    letter-spacing: 1px;
    text-shadow: 2px 2px 4px rgba(59, 130, 246, 0.1);
}

#sidebarToggle {
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 8px;
    padding: 8px 12px;
    transition: all 0.3s ease;
    color: #1e40af !important;
    order: 3;
    margin-left: auto !important;
    margin-right: 1rem !important;
    backdrop-filter: blur(10px);
}

#sidebarToggle:hover {
    background: rgba(59, 130, 246, 0.25);
    border-color: rgba(59, 130, 246, 0.5);
    transform: rotate(90deg);
}

#sidebarToggle:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

#sidebarToggle i {
    color: #1e40af;
    font-size: 1rem;
}

.navbar-nav {
    order: 4;
    margin: 0 !important;
}

.navbar-nav .nav-link {
    color: #1e40af !important;
    background: rgba(59, 130, 246, 0.15);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.navbar-nav .nav-link:hover {
    background: rgba(59, 130, 246, 0.25);
    transform: scale(1.1);
}

.dropdown-menu {
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.15);
    border-radius: 12px;
    padding: 0.5rem;
    animation: fadeInDown 0.3s ease;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dropdown-item {
    border-radius: 8px;
    padding: 0.75rem 1rem;
    transition: all 0.2s;
    color: var(--text-primary);
}

/* Role Badge Styles - Untuk Dropdown Menu */
.dropdown-item .role-badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-left: 0.5rem;
    vertical-align: middle;
}

.dropdown-item .role-super-admin {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.dropdown-item .role-admin {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.dropdown-item .role-user {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.dropdown-item:hover {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    transform: translateX(5px);
}

/* Sidebar dengan Glass Effect */
.sb-sidenav-dark {
    background: rgba(255, 255, 255, 0.25) !important;
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-right: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 4px 0 32px rgba(59, 130, 246, 0.1);
}

.sb-sidenav-menu-heading {
    color: #0369a1 !important;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 1.5rem 1rem 0.5rem;
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link {
    color: var(--text-primary);
    padding: 0.85rem 1rem;
    margin: 0.35rem 0.75rem;
    border-radius: 10px;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 0.95rem;
    position: relative;
    overflow: hidden;
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.15), transparent);
    transition: left 0.5s ease;
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link:hover::before {
    left: 100%;
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link:hover {
    background: rgba(59, 130, 246, 0.15);
    color: #1e40af;
    transform: translateX(5px);
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
    transform: scale(1.02);
}

.sb-nav-link-icon {
    margin-right: 0.75rem;
    width: 20px;
    color: var(--text-secondary) !important;
    transition: all 0.3s ease;
}

.sb-nav-link-icon i {
    color: inherit !important;
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link:hover .sb-nav-link-icon {
    color: #1e40af !important;
    transform: scale(1.2);
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link.active .sb-nav-link-icon {
    color: white !important;
}

.sb-sidenav-menu-nested .nav-link {
    padding-left: 3rem !important;
    font-size: 0.9rem;
}

/* Main Content */
#layoutSidenav_content {
    background: transparent;
}

.container-fluid {
    padding: 2rem;
    max-width: 1400px;
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

h1 {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

h1 i {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Card dengan Glass Effect */
.card {
    background: rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
    overflow: hidden;
    animation: slideUp 0.6s ease-out;
    transition: all 0.4s ease;
    margin-bottom: 1.5rem;
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

.card:hover {
    box-shadow: 0 12px 40px rgba(59, 130, 246, 0.2);
    transform: translateY(-5px);
}

.card-header {
    background: rgba(139, 92, 246, 0.08);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    padding: 1.5rem;
}

.card-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.25rem;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-header h5 i {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.card-body {
    padding: 1.75rem;
    background: rgba(255, 255, 255, 0.05);
}

/* Profile Avatar */
.profile-avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    box-shadow: 0 8px 32px rgba(139, 92, 246, 0.3);
    animation: float 3s ease-in-out infinite;
}

.profile-avatar i {
    font-size: 5rem;
    color: white;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

/* Info Cards */
.info-card {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.info-card:hover {
    background: rgba(255, 255, 255, 0.7);
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.2);
}

.info-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-label i {
    color: #8b5cf6;
}

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    word-break: break-all;
}

/* Buttons dengan Gradient dan Animasi */
.btn {
    padding: 0.65rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn i {
    transition: transform 0.3s ease;
}

.btn:hover i {
    transform: scale(1.2);
}

.btn-primary {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(139, 92, 246, 0.4);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(139, 92, 246, 0.5);
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.btn-success:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(245, 158, 11, 0.5);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.5);
}

.btn-info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
}

.btn-info:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.5);
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.35);
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(107, 114, 128, 0.45);
}

.btn-sm {
    padding: 0.5rem 0.85rem;
    font-size: 0.8rem;
}

.btn-block {
    display: block;
    width: 100%;
}

/* Form Styling */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-primary);
    margin-bottom: 0.65rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.form-group label i {
    color: #8b5cf6;
    flex-shrink: 0;
}

.form-control {
    border: 2px solid rgba(226, 232, 240, 0.8);
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 0.85rem 1.15rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    width: 100%;
    min-height: 48px;
    color: var(--text-primary);
    line-height: 1.5;
}

.form-control:focus {
    border-color: #8b5cf6;
    background: white;
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.15);
    outline: none;
    transform: translateY(-2px);
}

.form-control:disabled {
    background: rgba(226, 232, 240, 0.3);
    cursor: not-allowed;
}

select.form-control {
    appearance: none;
    background-color: white !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238b5cf6' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 1rem center !important;
    padding-right: 3rem;
    cursor: pointer;
    padding-top: 1.1rem;
    padding-bottom: 1.1rem;
    height: auto;
    min-height: 52px;
}

select.form-control option {
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: white;
    line-height: 1.8;
}

/* Styling khusus untuk modal form */
.modal-body .form-group {
    margin-bottom: 1.25rem;
}

.modal-body .form-control {
    min-height: 56px;
    font-size: 1rem;
    padding: 1.15rem 1.25rem;
}

.modal-body select.form-control {
    padding-right: 3.5rem;
    padding-top: 1.15rem;
    padding-bottom: 1.15rem;
    min-height: 56px;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: rgba(16, 185, 129, 0.15);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #991b1b;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

/* Role Badge */
.role-badge {
    display: inline-block;
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 0.5rem;
}

.role-super_admin {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.role-admin {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.role-user {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

/* Table Styling */
.table-responsive {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.table {
    margin-bottom: 0;
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
}

.table thead th {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    border: none;
}

.table tbody tr {
    transition: all 0.3s ease;
    border-bottom: 1px solid rgba(226, 232, 240, 0.5);
}

.table tbody tr:hover {
    background: rgba(139, 92, 246, 0.08);
    transform: scale(1.01);
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
    color: var(--text-primary);
}

/* User Management Cards */
.user-management-card {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.user-management-card:hover {
    background: rgba(255, 255, 255, 0.6);
    box-shadow: 0 6px 20px rgba(139, 92, 246, 0.2);
    transform: translateY(-3px);
}

.user-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(139, 92, 246, 0.2);
}

.user-info h6 {
    margin: 0 0 0.25rem 0;
    font-weight: 700;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.user-email {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0;
}

.user-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Footer dengan Glass Effect */
footer {
    background: rgba(255, 255, 255, 0.35) !important;
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border-top: 1px solid rgba(255, 255, 255, 0.3);
    padding: 1.5rem 0;
    margin-top: 3rem;
}

footer .text-muted {
    color: var(--text-secondary) !important;
    font-size: 0.875rem;
}

/* Alert Styling */
.alert {
    border-radius: 12px;
    border: none;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(10px);
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #92400e;
    border-left: 4px solid #f59e0b;
}

.alert-info {
    background: rgba(59, 130, 246, 0.15);
    color: #1e3a8a;
    border-left: 4px solid #3b82f6;
}

.alert-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

.alert strong {
    font-weight: 700;
}

.alert i {
    margin-right: 0.5rem;
}

/* Modal Styling - DIPERBAIKI AGAR SAMA DENGAN INDEX.PHP */
.modal-dialog {
    max-width: 600px;
}

.modal-content {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalZoomIn 0.3s ease-out;
}

@keyframes modalZoomIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.modal-header {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    padding: 1.25rem 1.5rem;
    border-radius: 16px 16px 0 0;
}

.modal-header.bg-danger {
    background: rgba(239, 68, 68, 0.15) !important;
}

.modal-title {
    font-weight: 600;
    font-size: 1.25rem;
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}

.modal-body p {
    word-wrap: break-word;
    overflow-wrap: break-word;
    margin-bottom: 1rem;
}

.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-track {
    background: rgba(226, 232, 240, 0.3);
    border-radius: 10px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border-radius: 10px;
}

.modal-footer {
    border-top: 1px solid rgba(226, 232, 240, 0.5);
    padding: 1rem 1.5rem;
}

/* Close button styling */
.close {
    color: var(--text-secondary);
    opacity: 0.7;
    font-size: 1.5rem;
    font-weight: 400;
    background: transparent;
    border: none;
    padding: 0;
    transition: all 0.2s;
}

.close:hover {
    opacity: 1;
}

/* Responsive Design */
@media (max-width: 768px) {
    .brand-logo {
        height: 32px;
    }
    
    .brand-text {
        font-size: 1.1rem;
    }
    
    h1 {
        font-size: 1.5rem;
    }
    
    .container-fluid {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
    }
    
    .profile-avatar i {
        font-size: 4rem;
    }
    
    .user-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .user-actions {
        width: 100%;
    }
    
    .user-actions .btn {
        flex: 1;
        justify-content: center;
    }
    
    .table-responsive {
        font-size: 0.85rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-body {
        padding: 1rem;
    }
}

@media (max-width: 576px) {
    .brand-logo {
        height: 28px;
    }
    
    .brand-text {
        font-size: 1rem;
    }
    
    h1 {
        font-size: 1.35rem;
    }
    
    .container-fluid {
        padding: 0.75rem;
    }
    
    .card {
        border-radius: 12px;
    }
    
    .card-header {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
    }
    
    .profile-avatar i {
        font-size: 3.5rem;
    }
    
    .modal-body .form-control,
    #filterForm .form-control {
        font-size: 16px;
    }
}

/* Scrollbar Styling */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(226, 232, 240, 0.3);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    border-radius: 10px;
    transition: all 0.3s ease;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
}

/* Selection Styling */
::selection {
    background: rgba(139, 92, 246, 0.3);
    color: #5b21b6;
}

::-moz-selection {
    background: rgba(139, 92, 246, 0.3);
    color: #5b21b6;
}

/* Theme Toggle Switch */
.theme-toggle-container {
    padding: 0.75rem 1rem !important;
    cursor: default !important;
}

.theme-toggle-container:hover {
    background: transparent !important;
    transform: none !important;
}

.theme-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.theme-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    margin: 0;
}

.theme-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.theme-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
    transition: 0.4s;
    border-radius: 34px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.theme-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: 0.4s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.theme-switch input:checked + .theme-slider {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.theme-switch input:checked + .theme-slider:before {
    transform: translateX(24px);
}

.dropdown-divider {
    margin: 0.5rem 0;
    border-top: 1px solid rgba(226, 232, 240, 0.5);
}

/* Dark Mode Styles */
body.dark-mode {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    color: #e2e8f0;
}

body.dark-mode .sb-topnav {
    background: rgba(15, 23, 42, 0.8) !important;
    border: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .brand-text {
    color: #60a5fa;
}

body.dark-mode #sidebarToggle {
    background: rgba(59, 130, 246, 0.25);
    border: 1px solid rgba(59, 130, 246, 0.5);
    color: #60a5fa !important;
}

body.dark-mode #sidebarToggle i {
    color: #60a5fa;
}

body.dark-mode .navbar-nav .nav-link {
    background: rgba(59, 130, 246, 0.25);
    color: #60a5fa !important;
}

body.dark-mode .dropdown-menu {
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(71, 85, 105, 0.5);
}

body.dark-mode .dropdown-item {
    color: #e2e8f0;
}

body.dark-mode .dropdown-item:hover {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

body.dark-mode .sb-sidenav-dark {
    background: rgba(15, 23, 42, 0.8) !important;
    border-right: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .sb-sidenav-menu-heading {
    color: #60a5fa !important;
}

body.dark-mode .sb-sidenav-dark .sb-sidenav-menu .nav-link {
    color: #e2e8f0;
}

body.dark-mode .sb-sidenav-dark .sb-sidenav-menu .nav-link:hover {
    background: rgba(59, 130, 246, 0.25);
    color: #60a5fa;
}

body.dark-mode .sb-sidenav-dark .sb-sidenav-menu .nav-link.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

body.dark-mode .card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .card-header {
    background: rgba(51, 65, 85, 0.5);
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .card-body {
    background: rgba(15, 23, 42, 0.3);
}

body.dark-mode h1,
body.dark-mode h3,
body.dark-mode h5,
body.dark-mode h6 {
    color: #e2e8f0;
}

body.dark-mode .info-card {
    background: rgba(51, 65, 85, 0.5);
    border: 1px solid rgba(71, 85, 105, 0.4);
}

body.dark-mode .info-label {
    color: #94a3b8;
}

body.dark-mode .info-value {
    color: #e2e8f0;
}

body.dark-mode .form-control {
    background: rgba(30, 41, 59, 0.8) !important;
    border: 2px solid rgba(71, 85, 105, 0.5);
    color: #e2e8f0;
}

body.dark-mode .form-control:focus {
    background: rgba(30, 41, 59, 0.9) !important;
    border-color: #3b82f6;
}

body.dark-mode select.form-control {
    background: rgba(30, 41, 59, 0.8) !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2360a5fa' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 1rem center !important;
}

body.dark-mode select.form-control option {
    background: #1e293b !important;
    color: #e2e8f0;
}

body.dark-mode .table {
    background: rgba(30, 41, 59, 0.6);
}

body.dark-mode .table tbody tr:hover {
    background: rgba(59, 130, 246, 0.15);
}

body.dark-mode .table tbody td {
    color: #e2e8f0;
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .alert-info {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
    border-left: 4px solid #3b82f6;
}

body.dark-mode .alert-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
    border-left: 4px solid #f59e0b;
}

body.dark-mode .alert-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
    border-left: 4px solid #ef4444;
}

body.dark-mode .modal-content {
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(71, 85, 105, 0.5);
}

body.dark-mode .modal-header {
    background: rgba(15, 23, 42, 0.8);
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .modal-header.bg-danger {
    background: rgba(127, 29, 29, 0.8) !important;
}

body.dark-mode .modal-title {
    color: #e2e8f0;
}

body.dark-mode .modal-body {
    color: #e2e8f0;
}

body.dark-mode .user-management-card {
    background: rgba(51, 65, 85, 0.5);
    border: 1px solid rgba(71, 85, 105, 0.4);
}

body.dark-mode .user-info h6 {
    color: #e2e8f0;
}

body.dark-mode .user-email {
    color: #94a3b8;
}

body.dark-mode footer {
    background: rgba(15, 23, 42, 0.8) !important;
    border-top: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode footer .text-muted {
    color: #94a3b8 !important;
}

body.dark-mode .theme-label {
    color: #e2e8f0;
}

body.dark-mode .dropdown-divider {
    border-top: 1px solid rgba(71, 85, 105, 0.5);
}
        </style>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-light">
            <a class="navbar-brand" href="dashboard.php">
                <img src="assets/img/Setjen_DPDRI.png" alt="Logo" class="brand-logo">
                <span class="brand-text">ANDALAN</span>
            </a>
            <button class="btn btn-link btn-sm ms-auto me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-user fa-fw"></i></a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="profil.php">
                            <i class="bi bi-person-circle"></i> Profil
                            <span class="role-badge role-<?= strtolower(str_replace('_', '-', $user_data['role'])); ?>">
                                <?= strtoupper(str_replace('_', ' ', $user_data['role'])); ?>
                            </span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <div class="dropdown-item theme-toggle-container">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="theme-label">
                                    <i class="bi bi-moon-stars-fill"></i> Mode Gelap
                                </span>
                                <label class="theme-switch">
                                    <input type="checkbox" id="themeToggle">
                                    <span class="theme-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Menu Utama</div>
                            <a class="nav-link" href="dashboard.php">
                                <div class="sb-nav-link-icon"><i class="bi bi-speedometer2"></i></div>
                                Dashboard
                            </a>
                            
                            <!-- Stock Barang - ID unik: collapseStock -->
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseStock" aria-expanded="false" aria-controls="collapseStock">
                                <div class="sb-nav-link-icon"><i class="bi bi-box-seam"></i></div>
                                Stok Barang
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-chevron-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseStock" aria-labelledby="headingStock" data-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="index.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-file-text"></i></div>
                                        Stok Barang ATK
                                    </a>
                                    <a class="nav-link" href="stock_nonatk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-file-earmark"></i></div>
                                        Stok Barang Non ATK
                                    </a>
                                </nav>
                            </div>
                            
                            <!-- Pemrosesan ATK - ID unik: collapseATK -->
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseATK" aria-expanded="false" aria-controls="collapseATK">
                                <div class="sb-nav-link-icon"><i class="bi bi-gear-fill"></i></div>
                                Pemrosesan ATK
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-chevron-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseATK" aria-labelledby="headingATK" data-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="masuk_atk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                                        Barang Masuk
                                    </a>
                                    <a class="nav-link" href="keluar_atk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-arrow-up-circle-fill"></i></div>
                                        Barang Keluar
                                    </a>
                                </nav>
                            </div>
                            
                            <!-- Pemrosesan Non ATK - ID unik: collapseNonATK -->
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseNonATK" aria-expanded="false" aria-controls="collapseNonATK">
                                <div class="sb-nav-link-icon"><i class="bi bi-gear-fill"></i></div>
                                Pemrosesan Non ATK
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-chevron-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseNonATK" aria-labelledby="headingNonATK" data-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link" href="masuk_nonatk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                                        Barang Masuk
                                    </a>
                                    <a class="nav-link" href="keluar_nonatk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-arrow-up-circle-fill"></i></div>
                                        Barang Keluar
                                    </a>
                                </nav>
                            </div>
                            
                            <div class="sb-sidenav-menu-heading">Akun</div>
                            <a class="nav-link active" href="profil.php">
                                <div class="sb-nav-link-icon"><i class="bi bi-person-circle"></i></div>
                                Profil Pengguna
                            </a>
                        </div>
                    </div>
                </nav>
            </div>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid">
                        <h1><i class="bi bi-person-circle"></i> Profil Pengguna</h1>

                        <div class="row">
                            <!-- Profil Card -->
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-person-badge"></i> Informasi Akun</h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="profile-avatar">
                                            <i class="bi bi-person-circle"></i>
                                        </div>
                                        <h3 style="margin-bottom: 0.5rem; color: var(--text-primary);">
                                            <?= $user_data['name']; ?>
                                            <span class="role-badge role-<?= $user_data['role']; ?>">
                                                <?= strtoupper(str_replace('_', ' ', $user_data['role'])); ?>
                                            </span>
                                        </h3>
                                        <p style="color: var(--text-secondary); margin-bottom: 1rem;"><?= $user_data['email']; ?></p>
                                        <span class="status-badge status-<?= strtolower($user_data['status']); ?>">
                                            <?= ucfirst($user_data['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-info-circle"></i> Detail Akun</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="info-card">
                                            <div class="info-label">
                                                <i class="bi bi-calendar-plus"></i>
                                                Dibuat Pada
                                            </div>
                                            <div class="info-value">
                                                <?= date('d M Y, H:i', strtotime($user_data['created_at'])); ?>
                                            </div>
                                        </div>

                                        <div class="info-card">
                                            <div class="info-label">
                                                <i class="bi bi-calendar-check"></i>
                                                Terakhir Diperbarui
                                            </div>
                                            <div class="info-value">
                                                <?= date('d M Y, H:i', strtotime($user_data['updated_at'])); ?>
                                            </div>
                                        </div>

                                        <?php if($user_data['last_login']): ?>
                                        <div class="info-card">
                                            <div class="info-label">
                                                <i class="bi bi-clock-history"></i>
                                                Login Terakhir
                                            </div>
                                            <div class="info-value">
                                                <?= date('d M Y, H:i', strtotime($user_data['last_login'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="info-card">
                                            <div class="info-label">
                                                <i class="bi bi-hash"></i>
                                                ID Pengguna
                                            </div>
                                            <div class="info-value">
                                                #<?= $user_data['id']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Profil & Ganti Password -->
                            <div class="col-lg-8">
                                <!-- Edit Profil Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-pencil-square"></i> Edit Profil</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post">
                                            <div class="form-group">
                                                <label>
                                                    <i class="bi bi-person"></i>
                                                    Nama Lengkap
                                                </label>
                                                <input type="text" name="name" class="form-control" value="<?= $user_data['name']; ?>" required>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <i class="bi bi-envelope"></i>
                                                    Email
                                                </label>
                                                <input type="email" name="email" class="form-control" value="<?= $user_data['email']; ?>" required>
                                            </div>

                                            <button type="submit" class="btn btn-primary btn-block" name="updateprofil">
                                                <i class="bi bi-check-circle"></i> Simpan Perubahan
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Ganti Password Card -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="bi bi-shield-lock"></i> Ganti Password</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post">
                                            <div class="form-group">
                                                <label>
                                                    <i class="bi bi-key"></i>
                                                    Password Lama
                                                </label>
                                                <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password lama" required>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <i class="bi bi-key-fill"></i>
                                                    Password Baru
                                                </label>
                                                <input type="password" name="password_baru" class="form-control" placeholder="Masukkan password baru" required>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <i class="bi bi-check-circle"></i>
                                                    Konfirmasi Password Baru
                                                </label>
                                                <input type="password" name="konfirmasi_password" class="form-control" placeholder="Konfirmasi password baru" required>
                                            </div>

                                            <button type="submit" class="btn btn-success btn-block" name="gantipassword">
                                                <i class="bi bi-shield-check"></i> Ganti Password
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if($user_data['role'] == 'super_admin'): ?>
<!-- MANAJEMEN USER - HANYA UNTUK SUPER ADMIN -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-people-fill"></i> Manajemen Pengguna</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill"></i> 
                    <strong>Super Admin Panel:</strong> Anda dapat mengelola semua pengguna, mengubah role, status, dan menghapus akun yang tidak sesuai.
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Terdaftar</th>
                                <th style="min-width: 200px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            // Reset pointer untuk loop pertama (tabel)
                            mysqli_data_seek($all_users, 0);
                            while($user = mysqli_fetch_array($all_users)): 
                            ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td>
                                    <strong><?= $user['name']; ?></strong>
                                    <?php if($user['id'] == $user_id): ?>
                                    <span class="badge badge-primary badge-sm" style="font-size: 0.7rem; padding: 0.25rem 0.5rem;">Anda</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $user['email']; ?></td>
                                <td>
                                    <span class="role-badge role-<?= $user['role']; ?>">
                                        <?= strtoupper(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($user['status']); ?>">
                                        <?= ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if($user['id'] != $user_id): ?>
                                    <div class="d-flex flex-column gap-2" style="gap: 0.5rem;">
                                        <!-- Button Edit Role -->
                                        <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#editRoleModal<?= $user['id']; ?>" style="white-space: nowrap;">
                                            <i class="bi bi-pencil"></i> Edit Role
                                        </button>
                                        
                                        <!-- Button Toggle Status -->
                                        <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#editStatusModal<?= $user['id']; ?>" style="white-space: nowrap;">
                                            <i class="bi bi-toggle-on"></i> Edit Status
                                        </button>
                                        
                                        <!-- Button Hapus -->
                                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal<?= $user['id']; ?>" style="white-space: nowrap;">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted"><i>Akun Anda</i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SEMUA MODAL DI LUAR TABEL -->
<?php 
// Reset pointer untuk loop kedua (modals)
mysqli_data_seek($all_users, 0);
while($user = mysqli_fetch_array($all_users)): 
    if($user['id'] != $user_id): // Hanya buat modal untuk user lain
?>
    <!-- Modal Edit Role -->
    <div class="modal fade" id="editRoleModal<?= $user['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Ubah Role: <?= $user['name']; ?></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="target_user_id" value="<?= $user['id']; ?>">
                        
                        <div class="form-group">
                            <label><i class="bi bi-person-badge"></i> Pilih Role Baru</label>
                            <select name="new_role" class="form-control" required>
                                <option value="user" <?= $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            </select>
                        </div>

                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            <strong>Peringatan:</strong> Mengubah role akan mempengaruhi hak akses pengguna ini.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" name="ubah_role" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Status -->
    <div class="modal fade" id="editStatusModal<?= $user['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-toggle-on"></i> Ubah Status: <?= $user['name']; ?></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="target_user_id" value="<?= $user['id']; ?>">
                        
                        <div class="form-group">
                            <label><i class="bi bi-check-circle"></i> Pilih Status Baru</label>
                            <select name="new_status" class="form-control" required>
                                <option value="active" <?= $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill"></i> 
                            Status <strong>Inactive</strong> akan menonaktifkan akses login pengguna.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" name="ubah_status" class="btn btn-warning">
                            <i class="bi bi-check-circle"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus User -->
    <div class="modal fade" id="deleteModal<?= $user['id']; ?>" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Konfirmasi Hapus</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="target_user_id" value="<?= $user['id']; ?>">
                        
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            <strong>PERINGATAN!</strong> Tindakan ini tidak dapat dibatalkan.
                        </div>

                        <p>Apakah Anda yakin ingin menghapus user berikut?</p>
                        
                        <div class="user-management-card">
                            <div class="user-header">
                                <div class="user-info">
                                    <h6><?= $user['name']; ?></h6>
                                    <p class="user-email"><?= $user['email']; ?></p>
                                </div>
                                <span class="role-badge role-<?= $user['role']; ?>">
                                    <?= strtoupper(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" name="hapus_user" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Ya, Hapus User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php 
    endif;
endwhile; 
?>
<?php endif; ?>
</div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Copyright &copy; 2025 Awal Cahyo &mdash; DPD RI Provinsi Kalimantan Barat</div>                        </div>
                    </div>
                </footer>
            </div>
        </div>
        
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        
        <script>
        // Dark Mode Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        // Cek preferensi tema dari localStorage
        const currentTheme = localStorage.getItem('theme');
        if (currentTheme === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.checked = true;
        }
        
        // Event listener untuk toggle
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });
        
        // Update icon based on theme
        themeToggle.addEventListener('change', function() {
            const icon = document.querySelector('.theme-label i');
            if (this.checked) {
                icon.classList.remove('bi-moon-stars-fill');
                icon.classList.add('bi-sun-fill');
            } else {
                icon.classList.remove('bi-sun-fill');
                icon.classList.add('bi-moon-stars-fill');
            }
        });
        
        // Set initial icon
        if (currentTheme === 'dark') {
            const icon = document.querySelector('.theme-label i');
            icon.classList.remove('bi-moon-stars-fill');
            icon.classList.add('bi-sun-fill');
        }
        </script>
    </body>
</html>