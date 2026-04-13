<?php
// keluar_nonatk.php
require_once 'config.php';
require_once 'function.php';

// Cek apakah user sudah login
requireLogin();

// Cek permission - Super Admin dan Admin bisa akses halaman Non-ATK
if(!canRead('non_atk')){
    setNotification("Anda tidak memiliki akses ke halaman Barang Keluar Non-ATK!", "danger");
    header('location:dashboard.php');
    exit();
}

// Ambil data user dari session
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];

// Ambil notifikasi jika ada
$notification = getNotification();
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title>Barang Keluar Non-ATK - SELARAS</title>
        <link href="css/styles.css" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.10.20/css/dataTables.bootstrap4.min.css" rel="stylesheet" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/js/all.min.js" crossorigin="anonymous"></script>
        <style>
/* ===== CSS LENGKAP - GLASS MORPHISM & MODERN ANIMATIONS WITH DARK MODE ===== */

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
    --accent-teal: #14b8a6;
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

/* ===== NAVBAR ===== */
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
    top: 0; left: 0; right: 0;
    display: flex !important;
    flex-wrap: nowrap;
    align-items: center;
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
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

.navbar-brand:hover { transform: scale(1.05); }

.brand-logo {
    height: 40px;
    width: auto;
    object-fit: contain;
    display: block;
    transition: transform 0.3s ease;
    filter: drop-shadow(2px 2px 4px rgba(59, 130, 246, 0.2));
}

.navbar-brand:hover .brand-logo { transform: rotate(5deg) scale(1.1); }

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

#sidebarToggle:focus { outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
#sidebarToggle i { color: #1e40af; font-size: 1rem; }

.navbar-nav { order: 4; margin: 0 !important; }

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

.navbar-nav .nav-link:hover { background: rgba(59, 130, 246, 0.25); transform: scale(1.1); }

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
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
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

.theme-switch input { opacity: 0; width: 0; height: 0; }

.theme-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
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

.theme-switch input:checked + .theme-slider:before { transform: translateX(24px); }

.dropdown-divider {
    margin: 0.5rem 0;
    border-top: 1px solid rgba(226, 232, 240, 0.5);
}

/* ===== SIDEBAR ===== */
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
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.15), transparent);
    transition: left 0.5s ease;
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link:hover::before { left: 100%; }

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

.sb-nav-link-icon i { color: inherit !important; }

.sb-sidenav-dark .sb-sidenav-menu .nav-link:hover .sb-nav-link-icon {
    color: #1e40af !important;
    transform: scale(1.2);
}

.sb-sidenav-dark .sb-sidenav-menu .nav-link.active .sb-nav-link-icon { color: white !important; }

.sb-sidenav-menu-nested .nav-link {
    padding-left: 3rem !important;
    font-size: 0.9rem;
}

/* ===== MAIN CONTENT ===== */
#layoutSidenav_content { background: transparent; }

.container-fluid {
    padding: 2rem;
    max-width: 1400px;
    animation: fadeIn 0.6s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

h1 {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 2rem;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

h1 i {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ===== ALERTS ===== */
.alert {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin-bottom: 1rem;
    border-left: 4px solid;
    animation: slideInLeft 0.4s ease-out;
}

@keyframes slideInLeft {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}

.alert-success {
    background: rgba(240, 253, 244, 0.9);
    backdrop-filter: blur(10px);
    color: #065f46;
    border-left-color: #10b981;
}

.alert-danger {
    background: rgba(254, 242, 242, 0.9);
    backdrop-filter: blur(10px);
    color: #991b1b;
    border-left-color: #ef4444;
}

.alert-info {
    background: rgba(239, 246, 255, 0.9);
    backdrop-filter: blur(10px);
    border-left-color: #3b82f6;
    color: #1e40af;
}

.alert-warning {
    background: rgba(254, 252, 232, 0.9);
    backdrop-filter: blur(10px);
    border-left-color: #f59e0b;
    color: #92400e;
}

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

.close:hover { opacity: 1; }

/* ===== CARDS ===== */
.card {
    background: rgba(255, 255, 255, 0.35);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(59, 130, 246, 0.1);
    overflow: hidden;
    animation: slideUp 0.6s ease-out 0.3s both;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.card-header {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    padding: 1.25rem 1.5rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.card-body { padding: 1.5rem; overflow: visible; }

/* ===== BUTTONS ===== */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.875rem;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(6, 182, 212, 0.35);
}

.btn-info:hover {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(6, 182, 212, 0.45);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
}

.btn-warning:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.45);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.45);
    color: white;
}

.btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; }
.btn-block { display: block; width: 100%; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; }

/* ===== TABLE ===== */
.table-responsive {
    margin-top: 1rem;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table { margin: 0; width: 100%; }

.table thead th {
    background: rgba(59, 130, 246, 0.1);
    color: var(--text-primary);
    border: none;
    font-weight: 700;
    font-size: 0.75rem;
    padding: 0.75rem 0.5rem;
    border-bottom: 2px solid #3b82f6;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.table tbody tr {
    border-bottom: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(59, 130, 246, 0.08);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.table tbody td {
    padding: 0.75rem 0.5rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    vertical-align: middle;
}

.table tbody td strong { color: #1e40af; font-weight: 600; }

/* ===== MODALS ===== */
.modal-dialog { max-width: 500px; }

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
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.modal-header {
    background: rgba(255, 255, 255, 0.5);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.3);
    padding: 1.25rem 1.5rem;
    border-radius: 16px 16px 0 0;
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

/* ===== FORMS ===== */
.form-group { margin-bottom: 1.25rem; }

.form-group label {
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    display: block;
}

.form-control {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s;
    width: 100%;
    height: auto;
    min-height: 42px;
    background-color: white;
    color: var(--text-primary);
}

select.form-control {
    padding-right: 2.5rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236c757d' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 12px 12px;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
}

select.form-control::-ms-expand { display: none; }

select.form-control option {
    padding: 0.75rem 1rem;
    background: white;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.5;
}

.form-control:focus {
    border-color: var(--accent-blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

.form-control:disabled, .form-control[readonly] {
    background: #f8f9fa;
    color: var(--text-secondary);
    cursor: not-allowed;
}

textarea.form-control { min-height: 80px; resize: vertical; }

.form-text, .text-muted {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

/* ===== PERIODE FILTER STYLE ===== */
.periode-filter-box {
    background: rgba(59, 130, 246, 0.06);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 10px;
    padding: 0.875rem 1rem;
    margin-bottom: 1.25rem;
}

.periode-filter-box label {
    font-weight: 600;
    font-size: 0.8rem;
    color: #1e40af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.filter-row {
    display: flex;
    gap: 0.5rem;
}

.filter-row select {
    flex: 1;
}

.badge-stok-periode {
    display: inline-block;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 20px;
    margin-left: 0.4rem;
    vertical-align: middle;
}

/* ===== FOOTER ===== */
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

/* ===== DATATABLES ===== */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter { margin-bottom: 1.5rem; }

.dataTables_wrapper .dataTables_filter input {
    border: 2px solid var(--border-color);
    border-radius: 10px;
    padding: 0.65rem 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.5);
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

/* ===== DARK MODE ===== */
body.dark-mode {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    color: #e2e8f0;
}

body.dark-mode .sb-topnav {
    background: rgba(15, 23, 42, 0.8) !important;
    border: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .brand-text { color: #60a5fa; }

body.dark-mode #sidebarToggle {
    background: rgba(59, 130, 246, 0.25);
    border: 1px solid rgba(59, 130, 246, 0.5);
    color: #60a5fa !important;
}

body.dark-mode #sidebarToggle i { color: #60a5fa; }

body.dark-mode .navbar-nav .nav-link {
    background: rgba(59, 130, 246, 0.25);
    color: #60a5fa !important;
}

body.dark-mode .dropdown-menu {
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(71, 85, 105, 0.5);
}

body.dark-mode .dropdown-item { color: #e2e8f0; }

body.dark-mode .dropdown-item:hover {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

body.dark-mode .sb-sidenav-dark {
    background: rgba(15, 23, 42, 0.8) !important;
    border-right: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .sb-sidenav-menu-heading { color: #60a5fa !important; }
body.dark-mode .sb-sidenav-dark .sb-sidenav-menu .nav-link { color: #e2e8f0; }

body.dark-mode .sb-sidenav-dark .sb-sidenav-menu .nav-link:hover {
    background: rgba(59, 130, 246, 0.25);
    color: #60a5fa;
}

body.dark-mode .sb-sidenav-dark .sb-sidenav-menu .nav-link.active {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

body.dark-mode h1 { color: #e2e8f0; }

body.dark-mode .card {
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .card-header {
    background: rgba(15, 23, 42, 0.8);
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .alert-info {
    background: rgba(30, 41, 59, 0.8);
    color: #93c5fd;
    border-left-color: #3b82f6;
}

body.dark-mode .alert-warning {
    background: rgba(30, 27, 23, 0.8);
    color: #fbbf24;
    border-left-color: #f59e0b;
}

body.dark-mode .alert-danger {
    background: rgba(30, 23, 23, 0.8);
    color: #fca5a5;
    border-left-color: #ef4444;
}

body.dark-mode .alert-success {
    background: rgba(20, 30, 20, 0.8);
    color: #86efac;
    border-left-color: #10b981;
}

body.dark-mode .table thead th {
    background: rgba(59, 130, 246, 0.2);
    color: #e2e8f0;
}

body.dark-mode .table tbody tr:hover { background: rgba(59, 130, 246, 0.15); }

body.dark-mode .table tbody td {
    color: #e2e8f0;
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .table tbody td strong { color: #93c5fd; }

body.dark-mode .modal-content {
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(71, 85, 105, 0.5);
}

body.dark-mode .modal-header {
    background: rgba(15, 23, 42, 0.8);
    border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode .modal-title { color: #e2e8f0; }
body.dark-mode .form-group label { color: #e2e8f0; }

body.dark-mode .form-control {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(71, 85, 105, 0.5);
    color: #e2e8f0;
}

body.dark-mode .form-control:focus {
    background: rgba(30, 41, 59, 0.9);
    border-color: #3b82f6;
}

body.dark-mode .form-control:disabled,
body.dark-mode .form-control[readonly] {
    background: rgba(15, 23, 42, 0.6);
    color: #94a3b8;
}

body.dark-mode select.form-control {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2394a3b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 12px 12px;
}

body.dark-mode select.form-control option {
    background: #1e293b;
    color: #e2e8f0;
}

body.dark-mode .form-control::placeholder { color: #94a3b8; opacity: 1; }
body.dark-mode .form-text, body.dark-mode .text-muted { color: #94a3b8; }

body.dark-mode footer {
    background: rgba(15, 23, 42, 0.8) !important;
    border-top: 1px solid rgba(71, 85, 105, 0.3);
}

body.dark-mode footer .text-muted { color: #94a3b8 !important; }
body.dark-mode .theme-label { color: #e2e8f0; }
body.dark-mode .dropdown-divider { border-top: 1px solid rgba(71, 85, 105, 0.5); }

body.dark-mode .dataTables_wrapper .dataTables_filter input {
    background: rgba(30, 41, 59, 0.8);
    border: 2px solid rgba(71, 85, 105, 0.5);
    color: #e2e8f0;
}

body.dark-mode .dataTables_wrapper .dataTables_filter input:focus {
    background: rgba(30, 41, 59, 0.9);
    border-color: #3b82f6;
}

body.dark-mode .dataTables_wrapper .dataTables_info,
body.dark-mode .dataTables_wrapper .dataTables_length label { color: #e2e8f0; }

body.dark-mode .dataTables_wrapper .dataTables_length select {
    background: rgba(30, 41, 59, 0.8);
    border: 1px solid rgba(71, 85, 105, 0.5);
    color: #e2e8f0;
}

body.dark-mode #stok-info {
    background: rgba(30, 41, 59, 0.8);
    color: #93c5fd;
    border-left-color: #3b82f6;
}

body.dark-mode .periode-filter-box {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
}

body.dark-mode .periode-filter-box label {
    color: #93c5fd;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .brand-logo { height: 32px; }
    .brand-text { font-size: 1.1rem; }
    h1 { font-size: 1.5rem; }
    .container-fluid { padding: 1rem; }

    .modal-dialog { margin: 0.5rem; max-width: calc(100% - 1rem); }
    .modal-body { padding: 1rem; }
    .modal-body .form-control { font-size: 16px; }

    .card-header { flex-direction: column; align-items: stretch; }
    .card-header .btn { width: 100%; margin-bottom: 0.5rem; }
    .card-header .btn:last-child { margin-bottom: 0; }

    .filter-row { flex-direction: column; }
}

@media (max-width: 576px) {
    .brand-logo { height: 28px; }
    .brand-text { font-size: 1rem; }
    .navbar-brand { gap: 0.5rem; }
    .container-fluid { padding: 1rem; }
    h1 { font-size: 1.5rem; }
}

/* ===== SCROLLBAR ===== */
::-webkit-scrollbar { width: 10px; height: 10px; }

::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
}

body.dark-mode ::-webkit-scrollbar-track { background: rgba(30, 41, 59, 0.5); }
body.dark-mode ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }

html { scroll-behavior: smooth; }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-light">
            <a class="navbar-brand" href="dashboard.php">
                <img src="assets/img/Setjen_DPDRI.png" alt="Logo" class="brand-logo">
                <span class="brand-text">SELARAS</span>
            </a>
            <button class="btn btn-link btn-sm ms-auto me-3" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="userDropdown" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user fa-fw"></i>
                    </a>
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
                            <a class="nav-link" href="dashboard.php">
                                <div class="sb-nav-link-icon"><i class="bi bi-speedometer2"></i></div>
                                Dashboard
                            </a>
                            
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
                            
                            <?php if(canRead('non_atk')): ?>
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'masuk_nonatk.php' || basename($_SERVER['PHP_SELF']) == 'keluar_nonatk.php') ? '' : 'collapsed'; ?>" 
                            href="#" 
                            data-toggle="collapse" 
                            data-target="#collapseNonATK" 
                            aria-expanded="<?php echo (basename($_SERVER['PHP_SELF']) == 'masuk_nonatk.php' || basename($_SERVER['PHP_SELF']) == 'keluar_nonatk.php') ? 'true' : 'false'; ?>" 
                            aria-controls="collapseNonATK">
                                <div class="sb-nav-link-icon"><i class="bi bi-gear-fill"></i></div>
                                Pemrosesan Non ATK
                                <div class="sb-sidenav-collapse-arrow"><i class="fas fa-chevron-down"></i></div>
                            </a>
                            <div class="collapse <?php echo (basename($_SERVER['PHP_SELF']) == 'masuk_nonatk.php' || basename($_SERVER['PHP_SELF']) == 'keluar_nonatk.php') ? 'show' : ''; ?>" 
                                id="collapseNonATK" 
                                aria-labelledby="headingNonATK" 
                                data-parent="#sidenavAccordion">
                                <nav class="sb-sidenav-menu-nested nav">
                                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'masuk_nonatk.php') ? 'active' : ''; ?>" href="masuk_nonatk.php">
                                        <div class="sb-nav-link-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                                        Barang Masuk
                                    </a>
                                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'keluar_nonatk.php') ? 'active' : ''; ?>" href="keluar_nonatk.php">
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
                        <h1><i class="bi bi-arrow-up-circle"></i> Barang Keluar Non-ATK</h1>

                        <?php if($notification): ?>
                        <div class="alert alert-<?=$notification['type'];?> alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="bi bi-<?php 
                                echo $notification['type'] == 'success' ? 'check-circle-fill' : 
                                    ($notification['type'] == 'danger' ? 'exclamation-triangle-fill' : 
                                    ($notification['type'] == 'warning' ? 'exclamation-circle-fill' : 'info-circle-fill')); 
                            ?>"></i>
                            <?=$notification['message'];?>
                        </div>
                        <?php endif; ?>

                        <div class="card mb-4">
                            <div class="card-header">
                                <?php if(canCreate('non_atk')): ?>
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#myModal">
                                    <i class="bi bi-plus-circle"></i> Tambah Barang Keluar
                                </button>
                                <?php endif; ?>
                                <a href="exportkeluar_nonatk.php" class="btn btn-info">
                                    <i class="bi bi-download"></i> Export Data
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="dataTable" width="100%" cellspacing="0">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>ID Barang</th>
                                                <th>Nama Barang</th>
                                                <th>Jumlah</th>
                                                <th>Penerima</th>
                                                <?php if(canUpdate('non_atk') || canDelete('non_atk')): ?>
                                                <th>Aksi</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                            $ambilsemuadatastock = mysqli_query($conn, "SELECT k.*, s.namabarang, s.satuan FROM keluar_non_atk k, stok_non_atk s WHERE s.idbarang = k.idbarang ORDER BY k.tanggal DESC");
                                            while($data = mysqli_fetch_array($ambilsemuadatastock)){
                                                $idb        = $data['idbarang'];
                                                $idk        = $data['idkeluar'];
                                                $tanggal    = $data['tanggal'];
                                                $namabarang = $data['namabarang'];
                                                $qty        = $data['qty'];
                                                $satuan     = $data['satuan'];
                                                $penerima   = $data['penerima'];
                                        ?>
                                            <tr>
                                                <td><?=date('d M Y', strtotime($tanggal));?></td>
                                                <td><?=$idb;?></td>
                                                <td><strong><?=$namabarang;?></strong></td>
                                                <td><?=$qty;?> <?=$satuan;?></td>
                                                <td><?=$penerima;?></td>
                                                <?php if(canUpdate('non_atk') || canDelete('non_atk')): ?>
                                                <td>
                                                    <?php if(canUpdate('non_atk')): ?>
                                                    <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#edit<?=$idk;?>">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <?php if(canDelete('non_atk')): ?>
                                                    <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#delete<?=$idk;?>">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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

        <!-- ===== ADD MODAL - dengan filter periode ===== -->
        <?php if(canCreate('non_atk')): ?>
        <div class="modal fade" id="myModal">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="bi bi-plus-circle"></i> Tambah Barang Keluar</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form method="post">
                        <div class="modal-body">

                            <!-- STEP 1: Filter Periode Stok -->
                            <div class="periode-filter-box">
                                <label>
                                    <i class="bi bi-calendar-range"></i>
                                    Filter Periode Stok Masuk
                                </label>
                                <div class="filter-row">
                                    <select id="filter_bulan" class="form-control" onchange="filterBarang()">
                                        <option value="">-- Semua Bulan --</option>
                                        <?php
                                        $nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
                                                       'Juli','Agustus','September','Oktober','November','Desember'];
                                        for($b = 1; $b <= 12; $b++){
                                            $selected = ($b == (int)date('n')) ? 'selected' : '';
                                            echo "<option value='$b' $selected>{$nama_bulan[$b]}</option>";
                                        }
                                        ?>
                                    </select>
                                    <select id="filter_tahun" class="form-control" onchange="filterBarang()">
                                        <?php
                                        $tahun_skrg = (int)date('Y');
                                        for($t = $tahun_skrg + 2; $t >= $tahun_skrg - 4; $t--){
                                            $selected = ($t == $tahun_skrg) ? 'selected' : '';
                                            echo "<option value='$t' $selected>$t</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <small class="form-text" style="margin-top: 0.4rem;">
                                    <i class="bi bi-info-circle"></i>
                                    Filter berdasarkan bulan/tahun stok barang ditambahkan ke sistem
                                </small>
                            </div>

                            <!-- STEP 2: Pilih Barang hasil filter -->
                            <div class="form-group">
                                <label>
                                    Pilih Barang
                                    <span id="badge-jumlah-barang" class="badge-stok-periode" style="display:none;"></span>
                                </label>
                                <select name="barangnya" id="barangnya" class="form-control" required onchange="updateStokInfo()">
                                    <option value="">-- Pilih barang --</option>
                                </select>
                                <small class="form-text" id="barang-kosong-info" style="display:none; color: #ef4444;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Tidak ada barang dengan stok > 0 pada periode ini.
                                </small>
                            </div>

                            <!-- Info stok tersedia -->
                            <div id="stok-info" class="alert alert-info" style="display: none;">
                                <i class="bi bi-box-seam"></i>
                                Stok tersedia: <strong><span id="stok-value">0</span> <span id="stok-satuan"></span></strong>
                                <br>
                                <small id="tgl-input-info" style="opacity: 0.8;"></small>
                            </div>

                            <div class="form-group">
                                <label>Jumlah</label>
                                <input type="number" name="qty" id="qty" placeholder="Masukkan jumlah" class="form-control" required min="1">
                                <small class="form-text" id="max-qty" style="display: none;">
                                    <i class="bi bi-info-circle"></i> Maksimal: <span id="max-value">0</span> <span id="max-satuan"></span>
                                </small>
                            </div>
                            <div class="form-group">
                                <label>Penerima</label>
                                <input type="text" name="penerima" placeholder="Masukkan nama penerima" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Tanggal</label>
                                <input type="date" name="tanggal" class="form-control" required value="<?=date('Y-m-d');?>">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" name="barangkeluar_nonatk">
                                <i class="bi bi-check-circle"></i> Tambah Barang Keluar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Data semua barang Non-ATK dari PHP (JSON) -->
        <?php if(canCreate('non_atk')): ?>
        <script>
        var allBarangData = <?php
            $all_barang = mysqli_query($conn, "
                SELECT idbarang, namabarang, satuan, tanggal_input
                FROM stok_non_atk
                ORDER BY namabarang ASC, tanggal_input DESC
            ");
            $barang_arr = [];
            while($row = mysqli_fetch_assoc($all_barang)){
                $stok = getStokAkhir($conn, $row['idbarang'], 'non_atk');
                $barang_arr[] = [
                    'id'        => $row['idbarang'],
                    'nama'      => $row['namabarang'],
                    'satuan'    => $row['satuan'],
                    'stok'      => (int)$stok,
                    'tgl_input' => $row['tanggal_input'],
                    'bulan'     => (int)date('n', strtotime($row['tanggal_input'])),
                    'tahun'     => (int)date('Y', strtotime($row['tanggal_input'])),
                    'tgl_label' => date('d M Y', strtotime($row['tanggal_input']))
                ];
            }
            echo json_encode($barang_arr, JSON_UNESCAPED_UNICODE);
        ?>;
        </script>
        <?php endif; ?>

        <!-- Edit & Delete Modals -->
        <?php
        mysqli_data_seek($ambilsemuadatastock, 0);
        while($data = mysqli_fetch_array($ambilsemuadatastock)){
            $idb        = $data['idbarang'];
            $idk        = $data['idkeluar'];
            $tanggal    = $data['tanggal'];
            $namabarang = $data['namabarang'];
            $qty        = $data['qty'];
            $satuan     = $data['satuan'];
            $penerima   = $data['penerima'];
        ?>

        <!-- Edit Modal -->
        <?php if(canUpdate('non_atk')): ?>
        <div class="modal fade" id="edit<?=$idk;?>">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Barang Keluar</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form method="post"> 
                        <div class="modal-body">
                            <?php
                                $stok_tersedia  = getStokAkhir($conn, $idb, 'non_atk');
                                $query_qty_lama = mysqli_query($conn, "SELECT qty FROM keluar_non_atk WHERE idkeluar='$idk'");
                                $data_qty       = mysqli_fetch_array($query_qty_lama);
                                $qty_lama       = $data_qty['qty'];
                                $stok_available = $stok_tersedia + $qty_lama;
                            ?>
                            <div class="alert alert-info">
                                <small><i class="bi bi-info-circle"></i> Stok tersedia: <strong><?=$stok_available;?> <?=$satuan;?></strong></small>
                            </div>
                            <div class="form-group">
                                <label>Nama Barang</label>
                                <input type="text" value="<?=$namabarang;?>" class="form-control" readonly>
                            </div>
                            <div class="form-group">
                                <label>Penerima</label>
                                <input type="text" name="penerima" value="<?=$penerima;?>" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Jumlah</label>
                                <input type="number" name="qty" value="<?=$qty;?>" class="form-control" required min="1" max="<?=$stok_available;?>">
                                <small class="form-text"><i class="bi bi-info-circle"></i> Maksimal: <?=$stok_available;?> <?=$satuan;?></small>
                            </div>
                            <div class="form-group">
                                <label>Tanggal</label>
                                <input type="date" name="tanggal" value="<?=date("Y-m-d", strtotime($tanggal));?>" class="form-control" required>
                            </div>
                            <input type="hidden" name="idb" value="<?=$idb;?>">
                            <input type="hidden" name="idk" value="<?=$idk;?>">
                            <button type="submit" class="btn btn-primary btn-block" name="updatekeluar_nonatk">
                                <i class="bi bi-check-circle"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Delete Modal -->
        <?php if(canDelete('non_atk')): ?>
        <div class="modal fade" id="delete<?=$idk;?>">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title"><i class="bi bi-trash-fill"></i> Hapus Barang Keluar</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <form method="post"> 
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <strong>Peringatan!</strong> Tindakan ini tidak dapat dibatalkan.
                            </div>
                            <p style="color: var(--text-primary);">Apakah Anda yakin ingin menghapus data barang keluar berikut?</p>
                            <ul style="color: var(--text-primary); margin-bottom: 1rem;">
                                <li><strong>Barang:</strong> <?=$namabarang;?></li>
                                <li><strong>Jumlah:</strong> <?=$qty;?> <?=$satuan;?></li>
                                <li><strong>Penerima:</strong> <?=$penerima;?></li>
                                <li><strong>Tanggal:</strong> <?=date('d M Y', strtotime($tanggal));?></li>
                            </ul>
                            <input type="hidden" name="idb" value="<?=$idb;?>">
                            <input type="hidden" name="idk" value="<?=$idk;?>">
                            <button type="submit" class="btn btn-danger btn-block" name="hapuskeluar_nonatk">
                                <i class="bi bi-trash-fill"></i> Ya, Hapus Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php } ?>

        <script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.datatables.net/1.10.20/js/dataTables.bootstrap4.min.js" crossorigin="anonymous"></script>
        <script>
            // ===== DATATABLE =====
            $(document).ready(function() {
                $('#dataTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Indonesian.json"
                    },
                    "order": [[0, "desc"]]
                });

                // Jalankan filter setiap kali modal ditampilkan
                $('#myModal').on('shown.bs.modal', function() {
                    filterBarang();
                });
            });

            // ===== FILTER BARANG BERDASARKAN PERIODE =====
            function filterBarang() {
                var bulan  = parseInt(document.getElementById('filter_bulan').value) || 0;
                var tahun  = parseInt(document.getElementById('filter_tahun').value) || 0;
                var select = document.getElementById('barangnya');
                var badge  = document.getElementById('badge-jumlah-barang');
                var kosong = document.getElementById('barang-kosong-info');

                if (!select) return;

                // Reset dropdown
                while (select.options.length > 0) { select.remove(0); }
                var defOpt = document.createElement('option');
                defOpt.value = ''; defOpt.textContent = '-- Pilih barang --';
                select.appendChild(defOpt);
                document.getElementById('stok-info').style.display = 'none';
                document.getElementById('max-qty').style.display   = 'none';
                document.getElementById('qty').value = '';

                // Filter data barang berdasarkan periode
                var filtered = allBarangData.filter(function(b) {
                    var matchBulan = (bulan === 0) || (b.bulan === bulan);
                    var matchTahun = (tahun === 0) || (b.tahun === tahun);
                    return matchBulan && matchTahun && b.stok > 0;
                });

                if (filtered.length === 0) {
                    kosong.style.display = 'block';
                    badge.style.display  = 'none';
                } else {
                    kosong.style.display = 'none';
                    badge.textContent    = filtered.length + ' barang';
                    badge.style.display  = 'inline-block';

                    filtered.forEach(function(b) {
                        var opt = document.createElement('option');
                        opt.value           = b.id;
                        opt.textContent     = b.nama + ' — Stok: ' + b.stok + ' ' + b.satuan + ' (masuk: ' + b.tgl_label + ')';
                        opt.setAttribute('data-stok',   b.stok);
                        opt.setAttribute('data-satuan', b.satuan);
                        opt.setAttribute('data-tgl',    b.tgl_label);
                        select.appendChild(opt);
                    });
                }
            }

            // ===== UPDATE INFO STOK SAAT BARANG DIPILIH =====
            function updateStokInfo() {
                var select = document.getElementById('barangnya');
                var opt    = select.options[select.selectedIndex];

                if (select.value) {
                    var stok   = opt.getAttribute('data-stok');
                    var satuan = opt.getAttribute('data-satuan');
                    var tgl    = opt.getAttribute('data-tgl');

                    document.getElementById('stok-value').textContent    = stok;
                    document.getElementById('stok-satuan').textContent    = satuan;
                    document.getElementById('max-value').textContent      = stok;
                    document.getElementById('max-satuan').textContent     = satuan;
                    document.getElementById('tgl-input-info').textContent = 'Stok ini masuk pada: ' + tgl;

                    document.getElementById('stok-info').style.display = 'block';
                    document.getElementById('max-qty').style.display   = 'block';
                    document.getElementById('qty').setAttribute('max', stok);
                    document.getElementById('qty').value = '';
                } else {
                    document.getElementById('stok-info').style.display = 'none';
                    document.getElementById('max-qty').style.display   = 'none';
                    document.getElementById('qty').value = '';
                }
            }

            // ===== DARK MODE TOGGLE =====
            const themeToggle = document.getElementById('themeToggle');
            const body = document.body;

            const currentTheme = localStorage.getItem('theme');
            if (currentTheme === 'dark') {
                body.classList.add('dark-mode');
                themeToggle.checked = true;
            }

            themeToggle.addEventListener('change', function() {
                if (this.checked) {
                    body.classList.add('dark-mode');
                    localStorage.setItem('theme', 'dark');
                } else {
                    body.classList.remove('dark-mode');
                    localStorage.setItem('theme', 'light');
                }
                updateThemeIcon();
            });

            function updateThemeIcon() {
                const icon = document.querySelector('.theme-label i');
                if (themeToggle.checked) {
                    icon.classList.remove('bi-moon-stars-fill');
                    icon.classList.add('bi-sun-fill');
                } else {
                    icon.classList.remove('bi-sun-fill');
                    icon.classList.add('bi-moon-stars-fill');
                }
            }

            if (currentTheme === 'dark') { updateThemeIcon(); }
        </script>
    </body>
</html>