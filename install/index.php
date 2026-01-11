<?php
/**
 * Installation Script
 * Run this once to set up the database and create the admin user
 */

session_start();

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Check if already installed
if (file_exists(__DIR__ . '/../.installed') && $step < 4) {
    header('Location: ../index.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Test database connection
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;charset=utf8mb4',
                    $_POST['db_host'],
                    $_POST['db_port']
                );

                $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // Create database if not exists
                $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                // Store in session
                $_SESSION['install'] = [
                    'db_host' => $_POST['db_host'],
                    'db_port' => $_POST['db_port'],
                    'db_name' => $dbName,
                    'db_user' => $_POST['db_user'],
                    'db_pass' => $_POST['db_pass'],
                ];

                header('Location: ?step=2');
                exit;
            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;

        case 2:
            // Save API configuration
            $_SESSION['install']['api_subdomain'] = $_POST['api_subdomain'];
            $_SESSION['install']['api_token'] = $_POST['api_token'];

            // Test API if credentials provided
            if (!empty($_POST['api_subdomain']) && !empty($_POST['api_token'])) {
                $ch = curl_init('https://api.current-rms.com/api/v1/stores?per_page=1');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_HTTPHEADER => [
                        'X-SUBDOMAIN: ' . $_POST['api_subdomain'],
                        'X-AUTH-TOKEN: ' . $_POST['api_token'],
                        'Accept: application/json',
                    ],
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                    $error = 'API connection failed. Please check your subdomain and API token. You can skip this step and configure later.';
                    break;
                }
            }

            header('Location: ?step=3');
            exit;

        case 3:
            // Create admin user and run schema
            try {
                $install = $_SESSION['install'];

                // Connect to database
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $install['db_host'],
                    $install['db_port'],
                    $install['db_name']
                );

                $pdo = new PDO($dsn, $install['db_user'], $install['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // Run schema
                $schema = file_get_contents(__DIR__ . '/schema.sql');
                $pdo->exec($schema);

                // Update admin user
                $adminPassword = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE crms_users SET name = ?, email = ?, password = ? WHERE email = 'admin@example.com'");
                $stmt->execute([$_POST['admin_name'], $_POST['admin_email'], $adminPassword]);

                if ($stmt->rowCount() === 0) {
                    // Insert if not exists
                    $stmt = $pdo->prepare("INSERT INTO crms_users (name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW())");
                    $stmt->execute([$_POST['admin_name'], $_POST['admin_email'], $adminPassword]);
                }

                // Create config files
                $dbConfig = "<?php\nreturn [\n" .
                    "    'driver' => 'mysql',\n" .
                    "    'host' => '" . addslashes($install['db_host']) . "',\n" .
                    "    'port' => '" . addslashes($install['db_port']) . "',\n" .
                    "    'database' => '" . addslashes($install['db_name']) . "',\n" .
                    "    'username' => '" . addslashes($install['db_user']) . "',\n" .
                    "    'password' => '" . addslashes($install['db_pass']) . "',\n" .
                    "    'charset' => 'utf8mb4',\n" .
                    "    'collation' => 'utf8mb4_unicode_ci',\n" .
                    "    'prefix' => 'crms_',\n" .
                    "];\n";

                file_put_contents(__DIR__ . '/../config/database.php', $dbConfig);

                $apiConfig = "<?php\nreturn [\n" .
                    "    'subdomain' => '" . addslashes($install['api_subdomain'] ?? '') . "',\n" .
                    "    'api_token' => '" . addslashes($install['api_token'] ?? '') . "',\n" .
                    "    'base_url' => 'https://api.current-rms.com/api/v1',\n" .
                    "    'timeout' => 30,\n" .
                    "    'verify_ssl' => true,\n" .
                    "];\n";

                file_put_contents(__DIR__ . '/../config/api.php', $apiConfig);

                // Create storage directories
                @mkdir(__DIR__ . '/../storage/cache', 0755, true);
                @mkdir(__DIR__ . '/../storage/logs', 0755, true);
                @mkdir(__DIR__ . '/../storage/uploads', 0755, true);

                // Create .installed file
                file_put_contents(__DIR__ . '/../.installed', date('c'));

                // Clear session
                unset($_SESSION['install']);

                header('Location: ?step=4');
                exit;
            } catch (Exception $e) {
                $error = 'Installation failed: ' . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install CurrentRMS Report Builder</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }
        .logo p { color: #666; font-size: 14px; }
        .steps {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
        }
        .step-indicator {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        .step-indicator.active {
            background: #667eea;
            color: white;
        }
        .step-indicator.completed {
            background: #10b981;
            color: white;
        }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -10px rgba(102, 126, 234, 0.5);
        }
        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .success-icon svg {
            width: 40px;
            height: 40px;
            stroke: white;
        }
        .skip-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #6b7280;
            font-size: 14px;
            text-decoration: none;
        }
        .skip-link:hover { color: #667eea; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>CurrentRMS Report Builder</h1>
            <p>Installation Wizard</p>
        </div>

        <div class="steps">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="step-indicator <?php echo $i < $step ? 'completed' : ($i == $step ? 'active' : ''); ?>">
                    <?php echo $i < $step ? '✓' : $i; ?>
                </div>
            <?php endfor; ?>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <form method="POST">
                <h2 style="margin-bottom: 20px; font-size: 18px;">Database Configuration</h2>

                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>

                <div class="form-group">
                    <label>Database Port</label>
                    <input type="text" name="db_port" value="3306" required>
                </div>

                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="currentrms_reports" required>
                    <div class="hint">Will be created if it doesn't exist</div>
                </div>

                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" name="db_user" required>
                </div>

                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass">
                </div>

                <button type="submit" class="btn">Continue →</button>
            </form>

        <?php elseif ($step == 2): ?>
            <form method="POST">
                <h2 style="margin-bottom: 20px; font-size: 18px;">CurrentRMS API Configuration</h2>
                <p style="color: #666; font-size: 14px; margin-bottom: 20px;">
                    Get your API credentials from CurrentRMS:<br>
                    System Setup → Integrations → API
                </p>

                <div class="form-group">
                    <label>Subdomain</label>
                    <input type="text" name="api_subdomain" placeholder="your-company">
                    <div class="hint">Your CurrentRMS subdomain (from your-company.current-rms.com)</div>
                </div>

                <div class="form-group">
                    <label>API Token</label>
                    <input type="text" name="api_token" placeholder="Enter your API token">
                </div>

                <button type="submit" class="btn">Continue →</button>
                <a href="?step=3" class="skip-link">Skip - Configure Later</a>
            </form>

        <?php elseif ($step == 3): ?>
            <form method="POST">
                <h2 style="margin-bottom: 20px; font-size: 18px;">Create Admin Account</h2>

                <div class="form-group">
                    <label>Admin Name</label>
                    <input type="text" name="admin_name" required>
                </div>

                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" required>
                </div>

                <div class="form-group">
                    <label>Admin Password</label>
                    <input type="password" name="admin_password" required minlength="8">
                    <div class="hint">Minimum 8 characters</div>
                </div>

                <button type="submit" class="btn">Complete Installation</button>
            </form>

        <?php elseif ($step == 4): ?>
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h2 style="text-align: center; margin-bottom: 10px;">Installation Complete!</h2>
            <p style="text-align: center; color: #666; margin-bottom: 30px;">
                CurrentRMS Report Builder has been installed successfully.
            </p>
            <a href="../index.php" class="btn" style="display: block; text-align: center; text-decoration: none;">
                Go to Login →
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
