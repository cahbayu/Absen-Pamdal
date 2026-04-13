<?php
// test_permission.php - Test Permission Secara Langsung
// Letakkan file ini di root folder project

session_start();

// SIMULASI LOGIN SUPER ADMIN
// Ganti dengan data user super admin Anda yang sebenarnya
$_SESSION['user_id'] = 1; // Ganti dengan ID user super admin Anda
$_SESSION['name'] = 'Super Admin Test';
$_SESSION['email'] = 'superadmin@test.com';
$_SESSION['role'] = 'super_admin'; // PENTING: harus persis 'super_admin'

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Permission</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { color: #3b82f6; font-weight: bold; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 4px; overflow-x: auto; }
        h2 { color: #1f2937; border-bottom: 2px solid #3b82f6; padding-bottom: 8px; }
    </style>
</head>
<body>
    <h1>🔍 Test Permission System</h1>
    
    <div class="box">
        <h2>1. Session Data</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <div class="box">
        <h2>2. Load config.php</h2>
        <?php
        if (file_exists('config.php')) {
            require_once 'config.php';
            echo '<p class="success">✓ config.php loaded successfully</p>';
            
            echo '<p><strong>ROLE_SUPER_ADMIN:</strong> <code>' . ROLE_SUPER_ADMIN . '</code></p>';
            echo '<p><strong>ROLE_ADMIN:</strong> <code>' . ROLE_ADMIN . '</code></p>';
            echo '<p><strong>ROLE_USER:</strong> <code>' . ROLE_USER . '</code></p>';
            
            echo '<p><strong>Session Role:</strong> <code>' . $_SESSION['role'] . '</code></p>';
            echo '<p><strong>Match Super Admin?</strong> ';
            if ($_SESSION['role'] === ROLE_SUPER_ADMIN) {
                echo '<span class="success">✓ YES - MATCH!</span>';
            } else {
                echo '<span class="error">✗ NO - NOT MATCH!</span>';
                echo '<br><small>Session: "' . $_SESSION['role'] . '" vs Constant: "' . ROLE_SUPER_ADMIN . '"</small>';
            }
            echo '</p>';
        } else {
            echo '<p class="error">✗ config.php NOT FOUND!</p>';
        }
        ?>
    </div>

    <div class="box">
        <h2>3. Test Permission Functions</h2>
        <?php
        if (function_exists('canCreate')) {
            echo '<h3>Testing canCreate() function:</h3>';
            echo '<p><strong>canCreate("atk"):</strong> ';
            $result = canCreate('atk');
            if ($result) {
                echo '<span class="success">✓ TRUE - CAN CREATE ATK</span>';
            } else {
                echo '<span class="error">✗ FALSE - CANNOT CREATE ATK</span>';
            }
            echo '</p>';
            
            echo '<p><strong>canCreate("non_atk"):</strong> ';
            $result = canCreate('non_atk');
            if ($result) {
                echo '<span class="success">✓ TRUE</span>';
            } else {
                echo '<span class="error">✗ FALSE</span>';
            }
            echo '</p>';
            
            echo '<p><strong>canCreate("all"):</strong> ';
            $result = canCreate('all');
            if ($result) {
                echo '<span class="success">✓ TRUE</span>';
            } else {
                echo '<span class="error">✗ FALSE</span>';
            }
            echo '</p>';
        } else {
            echo '<p class="error">✗ Function canCreate() NOT FOUND!</p>';
        }
        
        if (function_exists('canUpdate')) {
            echo '<h3>Testing canUpdate() function:</h3>';
            echo '<p><strong>canUpdate("atk"):</strong> ';
            $result = canUpdate('atk');
            if ($result) {
                echo '<span class="success">✓ TRUE - CAN UPDATE ATK</span>';
            } else {
                echo '<span class="error">✗ FALSE - CANNOT UPDATE ATK</span>';
            }
            echo '</p>';
        }
        
        if (function_exists('canDelete')) {
            echo '<h3>Testing canDelete() function:</h3>';
            echo '<p><strong>canDelete("atk"):</strong> ';
            $result = canDelete('atk');
            if ($result) {
                echo '<span class="success">✓ TRUE - CAN DELETE ATK</span>';
            } else {
                echo '<span class="error">✗ FALSE - CANNOT DELETE ATK</span>';
            }
            echo '</p>';
        }
        ?>
    </div>

    <div class="box">
        <h2>4. Debug canCreate() Function Step by Step</h2>
        <?php
        echo '<pre>';
        echo "Step 1: isLoggedIn() check\n";
        echo "  - isset(\$_SESSION['user_id']): " . (isset($_SESSION['user_id']) ? 'TRUE' : 'FALSE') . "\n";
        echo "  - isset(\$_SESSION['role']): " . (isset($_SESSION['role']) ? 'TRUE' : 'FALSE') . "\n";
        echo "  - isLoggedIn() result: " . (isLoggedIn() ? 'TRUE' : 'FALSE') . "\n\n";
        
        echo "Step 2: Role check\n";
        echo "  - \$_SESSION['role']: '{$_SESSION['role']}'\n";
        echo "  - ROLE_SUPER_ADMIN: '" . ROLE_SUPER_ADMIN . "'\n";
        echo "  - Are they equal? " . ($_SESSION['role'] === ROLE_SUPER_ADMIN ? 'YES' : 'NO') . "\n\n";
        
        echo "Step 3: canCreate('atk') execution\n";
        if (!isLoggedIn()) {
            echo "  - STOPPED: Not logged in\n";
        } else {
            $role = $_SESSION['role'];
            echo "  - User role: '$role'\n";
            
            if ($role === ROLE_SUPER_ADMIN) {
                echo "  - RESULT: TRUE (Super Admin check passed)\n";
            } else {
                echo "  - Super Admin check: FAILED\n";
                if ($role === ROLE_ADMIN && 'atk' === 'non_atk') {
                    echo "  - RESULT: TRUE (Admin & non_atk)\n";
                } else {
                    echo "  - Admin check: FAILED\n";
                    echo "  - RESULT: FALSE\n";
                }
            }
        }
        echo '</pre>';
        ?>
    </div>

    <div class="box">
        <h2>5. Database Role Check</h2>
        <?php
        if (isset($conn)) {
            $user_id = $_SESSION['user_id'];
            $query = mysqli_query($conn, "SELECT id, name, email, role FROM login WHERE id = $user_id");
            if ($query && mysqli_num_rows($query) > 0) {
                echo '<p class="success">✓ User found in database</p>';
                $user = mysqli_fetch_assoc($query);
                echo '<pre>';
                print_r($user);
                echo '</pre>';
                
                echo '<p><strong>Database Role:</strong> <code>' . $user['role'] . '</code></p>';
                echo '<p><strong>Session Role:</strong> <code>' . $_SESSION['role'] . '</code></p>';
                echo '<p><strong>Match?</strong> ';
                if ($user['role'] === $_SESSION['role']) {
                    echo '<span class="success">✓ YES</span>';
                } else {
                    echo '<span class="error">✗ NO - MISMATCH!</span>';
                    echo '<br><small>Database has "' . $user['role'] . '" but session has "' . $_SESSION['role'] . '"</small>';
                }
                echo '</p>';
            } else {
                echo '<p class="error">✗ User ID ' . $user_id . ' not found in database!</p>';
            }
        } else {
            echo '<p class="error">✗ Database connection not available</p>';
        }
        ?>
    </div>

    <div class="box">
        <h2>6. Recommendations</h2>
        <?php
        $issues = [];
        
        if (!isset($_SESSION['role'])) {
            $issues[] = "Session 'role' not set - You need to login first";
        } elseif ($_SESSION['role'] !== ROLE_SUPER_ADMIN) {
            $issues[] = "Session role ('{$_SESSION['role']}') does not match ROLE_SUPER_ADMIN ('" . ROLE_SUPER_ADMIN . "')";
        }
        
        if (!canCreate('atk')) {
            $issues[] = "canCreate('atk') returns FALSE for current user";
        }
        
        if (count($issues) > 0) {
            echo '<p class="error"><strong>⚠️ Issues Found:</strong></p>';
            echo '<ol>';
            foreach ($issues as $issue) {
                echo '<li>' . $issue . '</li>';
            }
            echo '</ol>';
            
            echo '<p class="info"><strong>💡 Solutions:</strong></p>';
            echo '<ol>';
            echo '<li>Run this SQL query in phpMyAdmin:<br><code>UPDATE login SET role = \'super_admin\' WHERE id = ' . $_SESSION['user_id'] . ';</code></li>';
            echo '<li>Logout and login again</li>';
            echo '<li>Clear browser cache (Ctrl+Shift+Delete)</li>';
            echo '<li>Try again</li>';
            echo '</ol>';
        } else {
            echo '<p class="success"><strong>✓ Everything looks good!</strong></p>';
            echo '<p>If buttons still don\'t show up in index.php, the issue might be:</p>';
            echo '<ul>';
            echo '<li>Browser cache - Clear it completely</li>';
            echo '<li>File not updated - Make sure you uploaded the new config.php and function.php</li>';
            echo '<li>Wrong index.php - Make sure you\'re editing the correct file</li>';
            echo '</ul>';
        }
        ?>
    </div>

    <div class="box">
        <h2>7. Quick Fix SQL</h2>
        <p>Copy and run this SQL in phpMyAdmin:</p>
        <pre>-- Fix role for user ID <?= $_SESSION['user_id'] ?>

UPDATE login SET role = 'super_admin' WHERE id = <?= $_SESSION['user_id'] ?>;

-- Verify
SELECT id, name, email, role FROM login WHERE id = <?= $_SESSION['user_id'] ?>;</pre>
    </div>

    <p style="margin-top: 20px; padding: 10px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
        <strong>⚠️ IMPORTANT:</strong> Delete this file (test_permission.php) after debugging for security!
    </p>
</body>
</html>