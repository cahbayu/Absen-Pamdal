<?php
// dashboard.php
require_once 'config.php';
require_once 'function.php';

// Cek apakah user sudah login
requireLogin();

// Ambil data user dari session
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];

// Gunakan fungsi dari function.php untuk mendapatkan total stok ATK (jika user punya akses)
$totalstok_atk = 0;
$totalstokmasuk_atk = 0;
$totalstokkeluar_atk = 0;

if(canRead('atk')) {
    $totalstok_atk = getTotalStokAkhir($conn, 'atk');
    $totalstokmasuk_atk = getTotalBarangMasuk($conn, 6, 'atk'); // 6 bulan terakhir
    $totalstokkeluar_atk = getTotalBarangKeluar($conn, 6, 'atk'); // 6 bulan terakhir
}

// Gunakan fungsi dari function.php untuk mendapatkan total stok Non-ATK (jika user punya akses)
$totalstok_nonatk = 0;
$totalstokmasuk_nonatk = 0;
$totalstokkeluar_nonatk = 0;

if(canRead('non_atk')) {
    $totalstok_nonatk = getTotalStokAkhir($conn, 'non_atk');
    $totalstokmasuk_nonatk = getTotalBarangMasuk($conn, 6, 'non_atk'); // 6 bulan terakhir
    $totalstokkeluar_nonatk = getTotalBarangKeluar($conn, 6, 'non_atk'); // 6 bulan terakhir
}

// Total keseluruhan
$totalstok_all = $totalstok_atk + $totalstok_nonatk;
$totalstokmasuk_all = $totalstokmasuk_atk + $totalstokmasuk_nonatk;
$totalstokkeluar_all = $totalstokkeluar_atk + $totalstokkeluar_nonatk;
?>
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="Sistem Informasi Manajemen Persediaan Barang SELARAS" />
        <meta name="author" content="Awal Cahyo" />
        <title>Dashboard - SELARAS</title>
        <link href="css/styles.css" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css" rel="stylesheet" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js" crossorigin="anonymous"></script>
        <style>
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
            --accent-cyan: #06b6d4;
            --accent-teal: #14b8a6;
            --accent-purple: #8b5cf6;
            --accent-pink: #ec4899;
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

        .dropdown-item:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            transform: translateX(5px);
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

        /* Role Badge untuk Welcome Banner */
        .welcome-banner .role-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 0.5rem;
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
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
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

        /* Collapse Arrow Animation */
        .sb-sidenav-collapse-arrow {
            transition: transform 0.3s ease;
            margin-left: auto;
        }

        .nav-link[aria-expanded="true"] .sb-sidenav-collapse-arrow {
            transform: rotate(180deg);
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
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }

        .breadcrumb-item.active {
            color: var(--text-secondary);
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: white;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.3);
        }

        .welcome-banner h3 {
            color: white;
            margin: 0;
            font-size: 1.25rem;
        }

        .welcome-banner p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        /* Section Title */
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 2.5rem 0 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #2563eb;
            font-size: 1.5rem;
        }

        .section-divider {
            height: 3px;
            background: linear-gradient(90deg, #3b82f6 0%, transparent 100%);
            margin-bottom: 1.5rem;
            border-radius: 10px;
        }

        /* Stats Cards dengan Glass Effect */
        .stats-card {
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 1.75rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            height: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6 0%, #06b6d4 100%);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .stats-card:hover::before {
            transform: scaleX(1);
        }

        .stats-card:hover {
            box-shadow: 0 15px 40px rgba(59, 130, 246, 0.25);
            transform: translateY(-8px) scale(1.02);
            background: rgba(255, 255, 255, 0.45);
        }

        .card-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.25rem;
            transition: all 0.4s ease;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .stats-card:hover .card-icon {
            transform: rotate(10deg) scale(1.1);
            animation: none;
        }

        .icon-blue { 
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.35);
        }

        .icon-green { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.35);
        }

        .icon-orange { 
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.35);
        }

        .icon-purple { 
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.35);
        }

        .icon-pink { 
            background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.35);
        }

        .icon-cyan { 
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            box-shadow: 0 8px 20px rgba(6, 182, 212, 0.35);
        }

        .card-title {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-value {
            font-size: 1.85rem;
            font-weight: 700;
            background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            transition: all 0.3s ease;
        }

        .stats-card:hover .card-value {
            transform: scale(1.05);
        }

        .card-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .card-link:hover {
            gap: 0.75rem;
            color: #1e40af;
        }

        /* Table Container dengan Glass Effect */
        .table-container {
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
            animation: slideUp 0.6s ease-out 0.3s both;
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

        .table-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-title i {
            color: #2563eb;
            font-size: 1.5rem;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: rgba(59, 130, 246, 0.1);
            color: var(--text-primary);
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            padding: 1.25rem 1rem;
            border-bottom: 2px solid #3b82f6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(59, 130, 246, 0.08);
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        }

        .table tbody td {
            padding: 1.25rem 1rem;
            font-size: 0.9rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .badge:hover {
            transform: scale(1.05);
        }

        .badge-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        .badge-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
        }

        .badge-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
        }

        .badge-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.35);
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

        /* DataTables Customization */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1.5rem;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 1rem;
            transition: all 0.3s ease;
            background: var(--bg-secondary);
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            background: white;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 8px;
            margin: 0 3px;
            transition: all 0.3s ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white !important;
            border-color: transparent;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            color: white !important;
            border-color: transparent !important;
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

        body.dark-mode h1 {
            color: #e2e8f0;
        }

        body.dark-mode .breadcrumb-item.active {
            color: #94a3b8;
        }

        body.dark-mode .stats-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(71, 85, 105, 0.3);
        }

        body.dark-mode .stats-card:hover {
            background: rgba(30, 41, 59, 0.8);
        }

        body.dark-mode .card-title {
            color: #94a3b8;
        }

        body.dark-mode .section-title {
            color: #e2e8f0;
        }

        body.dark-mode .table-container {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(71, 85, 105, 0.3);
        }

        body.dark-mode .table-title {
            color: #e2e8f0;
        }

        body.dark-mode .table thead th {
            background: rgba(59, 130, 246, 0.2);
            color: #e2e8f0;
        }

        body.dark-mode .table tbody tr:hover {
            background: rgba(59, 130, 246, 0.15);
        }

        body.dark-mode .table tbody td {
            color: #e2e8f0;
            border-bottom: 1px solid rgba(71, 85, 105, 0.3);
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

        body.dark-mode .dataTables_wrapper .dataTables_filter input {
            background: rgba(30, 41, 59, 0.8);
            border: 2px solid rgba(71, 85, 105, 0.5);
            color: #e2e8f0;
        }

        body.dark-mode .dataTables_wrapper .dataTables_filter input:focus {
            background: rgba(30, 41, 59, 0.9);
            border-color: #3b82f6;
        }

        /* Responsive */
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
            
            .section-title {
                font-size: 1.1rem;
            }
            
            .card-value {
                font-size: 1.5rem;
            }
            
            .container-fluid {
                padding: 1rem;
            }

            .stats-card {
                padding: 1.25rem;
            }

            .table-container {
                padding: 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .brand-logo {
                height: 28px;
            }
            
            .brand-text {
                font-size: 1rem;
            }
            
            .navbar-brand {
                gap: 0.5rem;
            }
            
            .stats-card {
                padding: 1.25rem;
            }
            
            .table-container {
                padding: 1.25rem;
            }

            .card-value {
                font-size: 1.35rem;
            }
        }

        /* Loading Animation */
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .loading {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-light">
            <a class="navbar-brand" href="dashboard.php">
                <img src="assets/img/Setjen_DPDRI.png" alt="Logo SELARAS" class="brand-logo">
                <span class="brand-text">SELARAS</span>
            </a>
            <button class="btn btn-link btn-sm ms-auto me-3" id="sidebarToggle" aria-label="Toggle Navigation"><i class="fas fa-bars"></i></button>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="User Menu"><i class="fas fa-user fa-fw"></i></a>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                        <a class="dropdown-item" href="profil.php">
                            <i class="bi bi-person-circle me-2"></i> Profil 
                            <span class="role-badge role-<?php echo strtolower(str_replace('_', '-', $user_role)); ?>">
                                <?php echo getRoleName($user_role); ?>
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
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
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
                            <a class="nav-link active" href="dashboard.php">
                                <div class="sb-nav-link-icon"><i class="bi bi-speedometer2"></i></div>
                                Dashboard
                            </a>
                            
                            <!-- Stock Barang -->
                            <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseStock" aria-expanded="false" aria-controls="collapseStock">
                                <div class="sb-nav-link-icon"><i class="bi bi-box-seam"></i></div>
                                Stok Barang
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-chevron-down"></i></div>
                            </a>
                            <div class="collapse" id="collapseStock" aria-labelledby="headingStock" data-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <?php if(canRead('atk')): ?>
                                    <a class="nav-link" href="index.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-file-text"></i></div>
                                        Stok Barang ATK
                                    </a>
                                    <?php endif; ?>
                                    <?php if(canRead('non_atk')): ?>
                                    <a class="nav-link" href="index_nonatk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-file-earmark"></i></div>
                                        Stok Barang Non ATK
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                            
                            <!-- Pemrosesan ATK - Hanya untuk yang punya akses ATK -->
                            <?php if(canRead('atk')): ?>
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
                            <?php endif; ?>
                            
                            <!-- Pemrosesan Non ATK - Hanya untuk yang punya akses Non-ATK -->
                            <?php if(canRead('non_atk')): ?>
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
                            <?php endif; ?>
                            
                            <div class="sb-sidenav-menu-heading">Akun</div>
                            <a class="nav-link" href="profil.php">
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
                        <h1><i class="bi bi-speedometer2"></i> Dashboard</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                        
                        <!-- Welcome Banner -->
                        <div class="welcome-banner">
                            <h3><i class="bi bi-hand-thumbs-up"></i> Selamat Datang, <?=htmlspecialchars($user_name);?>!</h3>
                            <p>Anda login sebagai: <span class="role-badge"><?=getRoleName($user_role);?></span></p>
                        </div>
                        
                        <!-- Ringkasan Keseluruhan - Hanya jika user punya akses ke keduanya -->
                        <?php if(canRead('atk') && canRead('non_atk')): ?>
                        <h3 class="section-title">
                            <i class="bi bi-graph-up-arrow"></i>
                            Ringkasan Keseluruhan
                        </h3>
                        <div class="section-divider"></div>
                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-blue">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div class="card-title">Total Stok Semua Barang</div>
                                    <h2 class="card-value"><?=number_format($totalstok_all);?> Unit</h2>
                                    <small class="text-muted">ATK: <?=number_format($totalstok_atk);?> | Non-ATK: <?=number_format($totalstok_nonatk);?></small>
                                </div>
                            </div>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-green">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </div>
                                    <div class="card-title">Total Barang Masuk (6 Bulan)</div>
                                    <h2 class="card-value"><?=number_format($totalstokmasuk_all);?> Unit</h2>
                                    <small class="text-muted">ATK: <?=number_format($totalstokmasuk_atk);?> | Non-ATK: <?=number_format($totalstokmasuk_nonatk);?></small>
                                </div>
                            </div>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-orange">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </div>
                                    <div class="card-title">Total Barang Keluar (6 Bulan)</div>
                                    <h2 class="card-value"><?=number_format($totalstokkeluar_all);?> Unit</h2>
                                    <small class="text-muted">ATK: <?=number_format($totalstokkeluar_atk);?> | Non-ATK: <?=number_format($totalstokkeluar_nonatk);?></small>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Statistik Barang ATK - Hanya jika user punya akses -->
                        <?php if(canRead('atk')): ?>
                        <h3 class="section-title">
                            <i class="bi bi-file-text"></i>
                            Statistik Barang ATK
                        </h3>
                        <div class="section-divider"></div>
                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-purple">
                                        <i class="bi bi-box"></i>
                                    </div>
                                    <div class="card-title">Stok Akhir ATK</div>
                                    <h2 class="card-value"><?=number_format($totalstok_atk);?> Unit</h2>
                                    <a href="index.php" class="card-link">
                                        Lihat Detail 
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-green">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </div>
                                    <div class="card-title">Barang Masuk ATK (6 Bulan)</div>
                                    <h2 class="card-value"><?=number_format($totalstokmasuk_atk);?> Unit</h2>
                                    <a href="masuk_atk.php" class="card-link">
                                        Lihat Detail 
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-orange">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </div>
                                    <div class="card-title">Barang Keluar ATK (6 Bulan)</div>
                                    <h2 class="card-value"><?=number_format($totalstokkeluar_atk);?> Unit</h2>
                                    <a href="keluar_atk.php" class="card-link">
                                        Lihat Detail 
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Statistik Barang Non-ATK - Hanya jika user punya akses -->
                        <?php if(canRead('non_atk')): ?>
                        <h3 class="section-title">
                            <i class="bi bi-file-earmark"></i>
                            Statistik Barang Non-ATK
                        </h3>
                        <div class="section-divider"></div>
                        <div class="row">
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-pink">
                                        <i class="bi bi-box"></i>
                                    </div>
                                    <div class="card-title">Stok Akhir Non-ATK</div>
                                    <h2 class="card-value"><?=number_format($totalstok_nonatk);?> Unit</h2>
                                    <a href="index_nonatk.php" class="card-link">
                                        Lihat Detail 
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-cyan">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </div>
                                    <div class="card-title">Barang Masuk Non-ATK (6 Bulan)</div>
                                    <h2 class="card-value"><?=number_format($totalstokmasuk_nonatk);?> Unit</h2>
                                    <a href="masuk_nonatk.php" class="card-link">
                                        Lihat Detail 
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-xl-4 col-md-6 mb-4">
                                <div class="stats-card">
                                    <div class="card-icon icon-orange">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </div>
                                    <div class="card-title">Barang Keluar Non-ATK (6 Bulan)</div>
                                    <h2 class="card-value"><?=number_format($totalstokkeluar_nonatk);?> Unit</h2>
                                    <a href="keluar_nonatk.php" class="card-link">
                                        Lihat Detail 
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Tabel Riwayat Barang ATK - Hanya jika user punya akses -->
                        <?php if(canRead('atk')): ?>
                        <h3 class="section-title">
                            <i class="bi bi-clock-history"></i>
                            Riwayat Pengambilan Barang Terbaru
                        </h3>
                        <div class="section-divider"></div>
                        
                        <div class="table-container">
                            <h4 class="table-title">
                                <i class="bi bi-file-text"></i> 
                                Barang ATK
                            </h4>
                            <div class="table-responsive">
                                <table class="table table-hover" id="dataTableATK">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Penerima</th>
                                            <th>Nama Barang</th>
                                            <th>Jumlah</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $ambilsemuadatakeluar_atk = mysqli_query($conn, "
                                            SELECT k.*, s.namabarang, s.satuan 
                                            FROM keluar k 
                                            JOIN stok s ON s.idbarang = k.idbarang 
                                            ORDER BY k.tanggal DESC 
                                            LIMIT 10
                                        ");
                                        $i = 1;
                                        while($data=mysqli_fetch_array($ambilsemuadatakeluar_atk)){
                                            $penerima = htmlspecialchars($data['penerima']);
                                            $namabarang = htmlspecialchars($data['namabarang']);
                                            $qty = $data['qty'];
                                            $satuan = htmlspecialchars($data['satuan']);
                                            $tanggal = date('d-m-Y', strtotime($data['tanggal']));
                                        ?>
                                        <tr>
                                            <td><?=$i++;?></td>
                                            <td><strong><?=$penerima;?></strong></td>
                                            <td><?=$namabarang;?></td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <i class="bi bi-box"></i>
                                                    <?=$qty;?> <?=$satuan;?>
                                                </span>
                                            </td>
                                            <td><?=$tanggal;?></td>
                                        </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Tabel Riwayat Barang Non-ATK - Hanya jika user punya akses -->
                        <?php if(canRead('non_atk')): ?>
                        <div class="table-container">
                            <h4 class="table-title">
                                <i class="bi bi-file-earmark"></i> 
                                Barang Non-ATK
                            </h4>
                            <div class="table-responsive">
                                <table class="table table-hover" id="dataTableNonATK">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Penerima</th>
                                            <th>Nama Barang</th>
                                            <th>Jumlah</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $ambilsemuadatakeluar_nonatk = mysqli_query($conn, "
                                            SELECT k.*, s.namabarang, s.satuan 
                                            FROM keluar_non_atk k 
                                            JOIN stok_non_atk s ON s.idbarang = k.idbarang 
                                            ORDER BY k.tanggal DESC 
                                            LIMIT 10
                                        ");
                                        $i = 1;
                                        while($data=mysqli_fetch_array($ambilsemuadatakeluar_nonatk)){
                                            $penerima = htmlspecialchars($data['penerima']);
                                            $namabarang = htmlspecialchars($data['namabarang']);
                                            $qty = $data['qty'];
                                            $satuan = htmlspecialchars($data['satuan']);
                                            $tanggal = date('d-m-Y', strtotime($data['tanggal']));
                                        ?>
                                        <tr>
                                            <td><?=$i++;?></td>
                                            <td><strong><?=$penerima;?></strong></td>
                                            <td><?=$namabarang;?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <i class="bi bi-box"></i>
                                                    <?=$qty;?> <?=$satuan;?>
                                                </span>
                                            </td>
                                            <td><?=$tanggal;?></td>
                                        </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
        <script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>
        <script>
            $(document).ready(function() {
                <?php if(canRead('atk')): ?>
                // Inisialisasi DataTable untuk ATK
                $('#dataTableATK').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Indonesian.json"
                    },
                    "order": [[4, "desc"]], // Urutkan berdasarkan tanggal (kolom ke-5) descending
                    "pageLength": 5,
                    "responsive": true
                });
                <?php endif; ?>

                <?php if(canRead('non_atk')): ?>
                // Inisialisasi DataTable untuk Non-ATK
                $('#dataTableNonATK').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Indonesian.json"
                    },
                    "order": [[4, "desc"]], // Urutkan berdasarkan tanggal (kolom ke-5) descending
                    "pageLength": 5,
                    "responsive": true
                });
                <?php endif; ?>
            });
            
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