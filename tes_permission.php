<?php
// test_permission.php — Debug & Test Sistem Permission
// ⚠️  HAPUS FILE INI SETELAH SELESAI DEBUGGING!

session_start();

// SIMULASI LOGIN — ganti sesuai kebutuhan
$_SESSION['user_id'] = 1;
$_SESSION['nama']    = 'Budi Santoso';
$_SESSION['username'] = 'kepala01';
$_SESSION['role']    = 'kepala';

$configLoaded = false;
$configError  = '';

if (file_exists('config.php')) {
    require_once 'config.php';
    $configLoaded = true;
} else {
    $configError = 'config.php tidak ditemukan di direktori yang sama.';
}

// ── Jalankan semua tes ───────────────────────────────────────────────────────
$tests = [];

if ($configLoaded) {
    // 1. Konstanta role
    $tests['constants'] = [
        'ROLE_KEPALA'  => ROLE_KEPALA,
        'ROLE_PAMDAL'  => ROLE_PAMDAL,
    ];

    // 2. Sesi
    $tests['session'] = [
        'user_id'  => $_SESSION['user_id']  ?? '–',
        'nama'     => $_SESSION['nama']      ?? '–',
        'username' => $_SESSION['username']  ?? '–',
        'role'     => $_SESSION['role']      ?? '–',
    ];

    // 3. Fungsi dasar
    $tests['basic_fns'] = [
        'isLoggedIn()'         => isLoggedIn(),
        'isKepala()'           => isKepala(),
        'isPamdal()'           => isPamdal(),
        'hasRole(kepala)'      => hasRole(ROLE_KEPALA),
        'hasRole(pamdal)'      => hasRole(ROLE_PAMDAL),
        'hasAccess([kepala,pamdal])' => hasAccess([ROLE_KEPALA, ROLE_PAMDAL]),
    ];

    // 4. Fungsi fitur
    $tests['feature_fns'] = [
        'canReviewLaporan()'   => canReviewLaporan(),
        'canManageShift()'     => canManageShift(),
        'canManagePengaturan()' => canManagePengaturan(),
        'canViewAllAbsensi()'  => canViewAllAbsensi(),
        'canSubmitAbsensi()'   => canSubmitAbsensi(),
        'canSubmitLaporan()'   => canSubmitLaporan(),
    ];

    // 5. Database
    $dbUser = null;
    $dbError = '';
    try {
        $stmt = $conn->prepare("SELECT id, nama, username, role, aktif FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $dbUser = $res->fetch_assoc();
        } else {
            $dbError = "User ID {$_SESSION['user_id']} tidak ditemukan di tabel users.";
        }
        $stmt->close();
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }

    // 6. Pengaturan sistem
    $settings = [];
    try {
        $resSet = $conn->query("SELECT kunci, nilai, keterangan FROM pengaturan ORDER BY id");
        while ($row = $resSet->fetch_assoc()) {
            $settings[] = $row;
        }
    } catch (Exception $e) {
        // silent
    }

    // 7. Shift
    $shifts = [];
    try {
        $resShift = $conn->query("SELECT * FROM shift ORDER BY id");
        while ($row = $resShift->fetch_assoc()) {
            $shifts[] = $row;
        }
    } catch (Exception $e) {
        // silent
    }
}

// Helper render
function badge(bool $val, string $trueLabel = 'TRUE', string $falseLabel = 'FALSE'): string {
    $cls  = $val ? 'badge-ok'   : 'badge-fail';
    $icon = $val ? '✓' : '✗';
    $lbl  = $val ? $trueLabel : $falseLabel;
    return "<span class='badge $cls'>$icon $lbl</span>";
}
function matchBadge($a, $b): string {
    return $a === $b ? "<span class='badge badge-ok'>✓ Cocok</span>" : "<span class='badge badge-fail'>✗ Tidak Cocok</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test Permission — Sistem Absensi Pamdal</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
            --ok:  #3d6b3a;
            --ok-bg: rgba(61,107,58,0.08);
            --ok-border: rgba(61,107,58,0.25);
            --fail: #7a1f1f;
            --fail-bg: rgba(155,40,40,0.07);
            --fail-border: rgba(155,40,40,0.2);
        }
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--brown-950);
            color: var(--brown-100);
            min-height: 100vh;
        }

        /* Top warning bar */
        .warn-bar {
            background: linear-gradient(90deg, #7a3a10, #a05015);
            padding: 10px 24px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            color: var(--gold-light);
            letter-spacing: 0.3px;
            border-bottom: 1px solid rgba(201,168,76,0.3);
        }
        .warn-bar strong { font-weight: 700; }

        /* Page header */
        .page-header {
            background: linear-gradient(160deg, var(--brown-800), var(--brown-900));
            border-bottom: 1px solid rgba(201,168,76,0.15);
            padding: 32px 40px;
            position: relative;
            overflow: hidden;
        }
        .page-header::after {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23c9a84c' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        .page-header-inner { position: relative; z-index:1; max-width: 960px; margin: 0 auto; }
        .page-eyebrow {
            font-size: 11px; font-weight: 600; letter-spacing: 2px;
            text-transform: uppercase; color: var(--gold);
            margin-bottom: 8px;
        }
        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 30px; font-weight: 700;
            color: var(--cream);
        }
        .page-sub { font-size: 14px; color: var(--brown-300); margin-top: 6px; font-weight: 300; }

        /* Sim badge */
        .sim-badge {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 16px;
            background: rgba(201,168,76,0.12);
            border: 1px solid rgba(201,168,76,0.3);
            border-radius: 99px;
            padding: 6px 16px;
            font-size: 12.5px; font-weight: 500;
            color: var(--gold-light);
        }
        .sim-badge::before { content: '●'; color: #6bcf6b; font-size: 9px; }

        /* Tabs */
        .tabs-wrap {
            background: var(--brown-900);
            border-bottom: 1px solid rgba(201,168,76,0.1);
            padding: 0 40px;
            overflow-x: auto;
        }
        .tabs { display: flex; gap: 0; list-style: none; max-width: 960px; margin: 0 auto; }
        .tab-btn {
            padding: 14px 20px;
            font-size: 13px; font-weight: 500;
            color: var(--brown-400);
            cursor: pointer;
            border: none; background: none;
            border-bottom: 2px solid transparent;
            white-space: nowrap;
            transition: all 0.25s;
            font-family: 'DM Sans', sans-serif;
        }
        .tab-btn:hover { color: var(--brown-200); }
        .tab-btn.active { color: var(--gold); border-bottom-color: var(--gold); }

        /* Content */
        .content { max-width: 960px; margin: 0 auto; padding: 36px 40px; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        /* Cards */
        .card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(201,168,76,0.12);
            border-radius: 14px;
            margin-bottom: 22px;
            overflow: hidden;
        }
        .card-header {
            background: rgba(201,168,76,0.06);
            border-bottom: 1px solid rgba(201,168,76,0.1);
            padding: 14px 22px;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header h3 {
            font-family: 'Playfair Display', serif;
            font-size: 16px; font-weight: 600;
            color: var(--brown-200);
        }
        .card-number {
            width: 26px; height: 26px;
            background: linear-gradient(135deg, var(--gold), var(--brown-400));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
            color: var(--brown-950);
            flex-shrink: 0;
        }
        .card-body { padding: 20px 22px; }

        /* Table */
        .test-table { width: 100%; border-collapse: collapse; }
        .test-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--brown-400);
            padding: 0 0 10px;
            border-bottom: 1px solid rgba(201,168,76,0.1);
        }
        .test-table td {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 13.5px;
            vertical-align: middle;
        }
        .test-table tr:last-child td { border-bottom: none; }
        .test-table td:first-child { color: var(--brown-300); font-family: 'DM Mono', monospace; font-size: 13px; }
        .test-table td:last-child  { text-align: right; }

        /* Badges */
        .badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 11px;
            border-radius: 99px;
            font-size: 12px; font-weight: 600;
        }
        .badge-ok   { background: var(--ok-bg); border: 1px solid var(--ok-border); color: #6bcf6b; }
        .badge-fail { background: var(--fail-bg); border: 1px solid var(--fail-border); color: #e88; }
        .badge-info { background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.25); color: var(--gold-light); }

        /* Code block */
        .code-block {
            background: var(--brown-950);
            border: 1px solid rgba(201,168,76,0.12);
            border-radius: 10px;
            padding: 16px 20px;
            font-family: 'DM Mono', monospace;
            font-size: 12.5px;
            color: var(--brown-200);
            overflow-x: auto;
            line-height: 1.7;
        }
        .code-block .c-key   { color: var(--gold-light); }
        .code-block .c-val   { color: #a8d8a0; }
        .code-block .c-comment { color: var(--brown-500); }

        /* Info row */
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 13.5px;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--brown-300); }
        .info-value { font-family: 'DM Mono', monospace; font-size: 13px; color: var(--brown-100); }

        /* Settings table */
        .settings-table { width: 100%; border-collapse: collapse; }
        .settings-table th {
            text-align: left;
            font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
            color: var(--brown-400);
            padding: 0 12px 10px 0;
            border-bottom: 1px solid rgba(201,168,76,0.1);
        }
        .settings-table td {
            padding: 11px 12px 11px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            font-size: 13.5px;
            vertical-align: top;
        }
        .settings-table tr:last-child td { border-bottom: none; }
        .settings-table td:first-child { font-family: 'DM Mono', monospace; font-size: 12.5px; color: var(--gold-light); }
        .settings-table td:nth-child(2) {
            font-weight: 600;
            color: var(--cream);
        }
        .settings-table td:last-child { color: var(--brown-400); font-size: 13px; }

        /* SQL box */
        .sql-box {
            background: var(--brown-950);
            border: 1px dashed rgba(201,168,76,0.2);
            border-radius: 10px;
            padding: 16px 20px;
            font-family: 'DM Mono', monospace;
            font-size: 12.5px;
            color: var(--brown-200);
            line-height: 1.8;
        }
        .sql-box .kw { color: var(--gold-light); font-weight: 600; }
        .sql-box .str { color: #a8d8a0; }
        .sql-box .cmt { color: var(--brown-500); }

        /* Quick action button */
        .btn-copy {
            background: none; border: 1px solid rgba(201,168,76,0.3);
            border-radius: 8px; padding: 6px 14px;
            color: var(--brown-300); font-family: 'DM Sans', sans-serif;
            font-size: 12.5px; cursor: pointer; transition: all 0.2s;
        }
        .btn-copy:hover { background: rgba(201,168,76,0.1); color: var(--gold-light); }

        /* Grid 2 col */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        @media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } .content { padding: 24px 18px; } .tabs-wrap { padding: 0 18px; } }

        /* DB user card */
        .db-user-card {
            background: linear-gradient(135deg, rgba(169,111,58,0.12), rgba(201,168,76,0.06));
            border: 1px solid rgba(201,168,76,0.2);
            border-radius: 12px;
            padding: 18px 20px;
            display: flex; align-items: center; gap: 16px;
        }
        .db-avatar {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--gold), var(--brown-500));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 700;
            color: var(--brown-950); flex-shrink: 0;
        }
        .db-user-info h4 { font-size: 15px; font-weight: 600; color: var(--cream); margin-bottom: 3px; }
        .db-user-info p  { font-size: 12.5px; color: var(--brown-300); }

        /* Notice */
        .notice {
            padding: 14px 18px; border-radius: 10px;
            font-size: 13.5px;
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 18px;
        }
        .notice-warn { background: rgba(180,100,20,0.12); border: 1px solid rgba(180,100,20,0.3); color: var(--brown-200); }
        .notice-ok   { background: var(--ok-bg); border: 1px solid var(--ok-border); color: #a8d8a0; }
        .notice-icon { flex-shrink: 0; margin-top: 1px; }
    </style>
</head>
<body>

<div class="warn-bar">
    ⚠️  <strong>FILE INI BERSIFAT RAHASIA</strong> — Hapus <code>test_permission.php</code> segera setelah selesai debugging!
</div>

<div class="page-header">
    <div class="page-header-inner">
        <p class="page-eyebrow">Debug Tools</p>
        <h1 class="page-title">Test Permission System</h1>
        <p class="page-sub">Verifikasi konfigurasi role, sesi, dan koneksi database.</p>
        <div class="sim-badge">
            Simulasi Login: <?= htmlspecialchars($_SESSION['nama'] ?? '–') ?>
            (<?= htmlspecialchars(strtoupper($_SESSION['role'] ?? '–')) ?>)
        </div>
    </div>
</div>

<div class="tabs-wrap">
    <ul class="tabs">
        <li><button class="tab-btn active" onclick="switchTab('overview',this)">Ringkasan</button></li>
        <li><button class="tab-btn" onclick="switchTab('session',this)">Sesi & Konstanta</button></li>
        <li><button class="tab-btn" onclick="switchTab('functions',this)">Fungsi Akses</button></li>
        <li><button class="tab-btn" onclick="switchTab('database',this)">Database</button></li>
        <li><button class="tab-btn" onclick="switchTab('settings',this)">Pengaturan</button></li>
        <li><button class="tab-btn" onclick="switchTab('sql',this)">SQL Helper</button></li>
    </ul>
</div>

<div class="content">

<?php if (!$configLoaded): ?>
<div class="notice notice-warn">
    <span class="notice-icon">⚠️</span>
    <div><strong>config.php tidak ditemukan.</strong><br><?= htmlspecialchars($configError) ?></div>
</div>
<?php else: ?>

    <!-- ── OVERVIEW ────────────────────────────────────── -->
    <div class="tab-pane active" id="tab-overview">

        <?php
        $allOk = isLoggedIn() && hasRole($_SESSION['role']) && ($dbUser !== null);
        ?>

        <?php if ($allOk): ?>
        <div class="notice notice-ok">
            <span class="notice-icon">✓</span>
            <div><strong>Semua sistem berjalan normal.</strong> Sesi aktif, role cocok, database terhubung.</div>
        </div>
        <?php else: ?>
        <div class="notice notice-warn">
            <span class="notice-icon">⚠️</span>
            <div><strong>Terdapat masalah yang perlu diperiksa.</strong> Lihat tab lain untuk detail.</div>
        </div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="card">
                <div class="card-header"><div class="card-number">1</div><h3>Status Sesi</h3></div>
                <div class="card-body">
                    <div class="info-row"><span class="info-label">User ID</span><span class="info-value"><?= $_SESSION['user_id'] ?? '–' ?></span></div>
                    <div class="info-row"><span class="info-label">Nama</span><span class="info-value"><?= htmlspecialchars($_SESSION['nama'] ?? '–') ?></span></div>
                    <div class="info-row"><span class="info-label">Username</span><span class="info-value"><?= htmlspecialchars($_SESSION['username'] ?? '–') ?></span></div>
                    <div class="info-row"><span class="info-label">Role</span><span><?= badge(in_array($_SESSION['role'] ?? '', [ROLE_KEPALA, ROLE_PAMDAL]), strtoupper($_SESSION['role'] ?? '?'), 'TIDAK VALID') ?></span></div>
                    <div class="info-row"><span class="info-label">isLoggedIn()</span><span><?= badge(isLoggedIn()) ?></span></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-number">2</div><h3>Koneksi Database</h3></div>
                <div class="card-body">
                    <div class="info-row"><span class="info-label">Host</span><span class="info-value"><?= DB_HOST ?></span></div>
                    <div class="info-row"><span class="info-label">Database</span><span class="info-value"><?= DB_NAME ?></span></div>
                    <div class="info-row"><span class="info-label">Koneksi</span><span><?= badge(!$conn->connect_error, 'Terhubung', 'Gagal') ?></span></div>
                    <div class="info-row"><span class="info-label">User DB</span><span><?= badge($dbUser !== null, 'Ditemukan', 'Tidak Ada') ?></span></div>
                    <?php if ($dbUser): ?>
                    <div class="info-row"><span class="info-label">Role DB vs Sesi</span><span><?= matchBadge($dbUser['role'], $_SESSION['role']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($dbUser): ?>
        <div class="db-user-card">
            <div class="db-avatar"><?= mb_strtoupper(mb_substr($dbUser['nama'], 0, 1)) ?></div>
            <div class="db-user-info">
                <h4><?= htmlspecialchars($dbUser['nama']) ?></h4>
                <p>@<?= htmlspecialchars($dbUser['username']) ?> &nbsp;·&nbsp;
                    <?= htmlspecialchars(getRoleName($dbUser['role'])) ?> &nbsp;·&nbsp;
                    <?= $dbUser['aktif'] ? '<span style="color:#6bcf6b">Aktif</span>' : '<span style="color:#e88">Nonaktif</span>' ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── SESSION ─────────────────────────────────────── -->
    <div class="tab-pane" id="tab-session">
        <div class="card">
            <div class="card-header"><div class="card-number">1</div><h3>Isi Sesi PHP</h3></div>
            <div class="card-body">
                <div class="code-block"><?php
                    foreach ($_SESSION as $k => $v) {
                        $safeK = htmlspecialchars($k);
                        $safeV = htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v);
                        echo "<span class='c-key'>$safeK</span> =&gt; <span class='c-val'>$safeV</span>\n";
                    }
                ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-number">2</div><h3>Konstanta Role</h3></div>
            <div class="card-body">
                <table class="test-table">
                    <thead><tr><th>Konstanta</th><th>Nilai</th><th>Match Sesi</th></tr></thead>
                    <tbody>
                    <tr>
                        <td>ROLE_KEPALA</td>
                        <td><span class="badge badge-info"><?= ROLE_KEPALA ?></span></td>
                        <td><?= matchBadge(ROLE_KEPALA, $_SESSION['role'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td>ROLE_PAMDAL</td>
                        <td><span class="badge badge-info"><?= ROLE_PAMDAL ?></span></td>
                        <td><?= matchBadge(ROLE_PAMDAL, $_SESSION['role'] ?? '') ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── FUNCTIONS ───────────────────────────────────── -->
    <div class="tab-pane" id="tab-functions">
        <div class="card">
            <div class="card-header"><div class="card-number">1</div><h3>Fungsi Dasar</h3></div>
            <div class="card-body">
                <table class="test-table">
                    <thead><tr><th>Fungsi</th><th>Hasil</th></tr></thead>
                    <tbody>
                    <?php foreach ($tests['basic_fns'] as $fn => $val): ?>
                    <tr>
                        <td><?= htmlspecialchars($fn) ?></td>
                        <td><?= badge((bool)$val) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-number">2</div><h3>Hak Akses Fitur</h3></div>
            <div class="card-body">
                <table class="test-table">
                    <thead><tr><th>Fungsi</th><th>Deskripsi</th><th>Hasil</th></tr></thead>
                    <tbody>
                    <?php
                    $featureDesc = [
                        'canReviewLaporan()'    => 'Kepala saja',
                        'canManageShift()'      => 'Kepala saja',
                        'canManagePengaturan()' => 'Kepala saja',
                        'canViewAllAbsensi()'   => 'Kepala saja',
                        'canSubmitAbsensi()'    => 'Semua user aktif',
                        'canSubmitLaporan()'    => 'Semua user aktif',
                    ];
                    foreach ($tests['feature_fns'] as $fn => $val): ?>
                    <tr>
                        <td><?= htmlspecialchars($fn) ?></td>
                        <td style="color:var(--brown-400);font-size:13px"><?= $featureDesc[$fn] ?? '' ?></td>
                        <td><?= badge((bool)$val) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── DATABASE ────────────────────────────────────── -->
    <div class="tab-pane" id="tab-database">
        <?php if ($dbError): ?>
        <div class="notice notice-warn"><span class="notice-icon">⚠️</span><div><?= htmlspecialchars($dbError) ?></div></div>
        <?php endif; ?>

        <?php if ($dbUser): ?>
        <div class="card">
            <div class="card-header"><div class="card-number">1</div><h3>Data User dari Database</h3></div>
            <div class="card-body">
                <table class="test-table">
                    <thead><tr><th>Kolom</th><th>Nilai DB</th><th>Nilai Sesi</th><th>Status</th></tr></thead>
                    <tbody>
                    <tr>
                        <td>id</td>
                        <td><?= $dbUser['id'] ?></td>
                        <td><?= $_SESSION['user_id'] ?? '–' ?></td>
                        <td><?= matchBadge((string)$dbUser['id'], (string)($_SESSION['user_id'] ?? '')) ?></td>
                    </tr>
                    <tr>
                        <td>username</td>
                        <td><?= htmlspecialchars($dbUser['username']) ?></td>
                        <td><?= htmlspecialchars($_SESSION['username'] ?? '–') ?></td>
                        <td><?= matchBadge($dbUser['username'], $_SESSION['username'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td>role</td>
                        <td><?= htmlspecialchars($dbUser['role']) ?></td>
                        <td><?= htmlspecialchars($_SESSION['role'] ?? '–') ?></td>
                        <td><?= matchBadge($dbUser['role'], $_SESSION['role'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td>aktif</td>
                        <td colspan="2"><?= $dbUser['aktif'] ? '1 (Aktif)' : '0 (Nonaktif)' ?></td>
                        <td><?= badge((bool)$dbUser['aktif'], 'Aktif', 'Nonaktif') ?></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($shifts)): ?>
        <div class="card">
            <div class="card-header"><div class="card-number">2</div><h3>Data Shift</h3></div>
            <div class="card-body">
                <table class="settings-table">
                    <thead><tr><th>ID</th><th>Nama Shift</th><th>Masuk</th><th>Keluar</th><th>Lintas Hari</th></tr></thead>
                    <tbody>
                    <?php foreach ($shifts as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td style="color:var(--brown-200)"><?= htmlspecialchars($s['nama_shift']) ?></td>
                        <td><?= $s['jam_masuk'] ?></td>
                        <td><?= $s['jam_keluar'] ?></td>
                        <td><?= $s['lintas_hari'] ? badge(false,'Ya','Tidak') : badge(true,'Tidak','Ya') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── SETTINGS ────────────────────────────────────── -->
    <div class="tab-pane" id="tab-settings">
        <?php if (!empty($settings)): ?>
        <div class="card">
            <div class="card-header"><div class="card-number">1</div><h3>Tabel Pengaturan Sistem</h3></div>
            <div class="card-body">
                <table class="settings-table">
                    <thead><tr><th>Kunci</th><th>Nilai</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    <?php foreach ($settings as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['kunci']) ?></td>
                        <td><?= htmlspecialchars($s['nilai']) ?></td>
                        <td><?= htmlspecialchars($s['keterangan'] ?? '–') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="notice notice-warn"><span>⚠️</span><div>Tidak ada data pengaturan ditemukan.</div></div>
        <?php endif; ?>
    </div>

    <!-- ── SQL ─────────────────────────────────────────── -->
    <div class="tab-pane" id="tab-sql">
        <div class="card">
            <div class="card-header"><div class="card-number">1</div><h3>Ubah Role User</h3></div>
            <div class="card-body">
                <p style="font-size:13.5px;color:var(--brown-300);margin-bottom:14px">Salin dan jalankan di phpMyAdmin / CLI MySQL:</p>
                <div class="sql-box">
<span class="cmt">-- Ubah ke Kepala</span>
<span class="kw">UPDATE</span> users <span class="kw">SET</span> role = <span class="str">'kepala'</span> <span class="kw">WHERE</span> id = <?= $_SESSION['user_id'] ?>;

<span class="cmt">-- Ubah ke Pamdal</span>
<span class="kw">UPDATE</span> users <span class="kw">SET</span> role = <span class="str">'pamdal'</span> <span class="kw">WHERE</span> id = <?= $_SESSION['user_id'] ?>;

<span class="cmt">-- Verifikasi</span>
<span class="kw">SELECT</span> id, nama, username, role, aktif <span class="kw">FROM</span> users <span class="kw">WHERE</span> id = <?= $_SESSION['user_id'] ?>;
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-number">2</div><h3>Reset Password (Bcrypt)</h3></div>
            <div class="card-body">
                <p style="font-size:13.5px;color:var(--brown-300);margin-bottom:14px">Hash baru untuk password <strong style="color:var(--brown-200)">pamdal123</strong>:</p>
                <div class="code-block"><?php
                    $newHash = password_hash('pamdal123', PASSWORD_DEFAULT);
                    echo htmlspecialchars($newHash);
                ?></div>
                <div class="sql-box" style="margin-top:14px">
<span class="kw">UPDATE</span> users <span class="kw">SET</span> password = <span class="str">'<?= htmlspecialchars($newHash) ?>'</span>
<span class="kw">WHERE</span> id = <?= $_SESSION['user_id'] ?>;
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-number">3</div><h3>Cek Semua User</h3></div>
            <div class="card-body">
                <div class="sql-box">
<span class="kw">SELECT</span> id, nama, username, role, aktif, created_at
<span class="kw">FROM</span> users
<span class="kw">ORDER BY</span> role, nama;
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
</div><!-- /content -->

<script>
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>