<?php
/**
 * Settings Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requireAdmin();

$pageTitle = 'Settings';
$success = flash('success');
$error = flash('error');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $section = $_POST['section'] ?? '';

    try {
        switch ($section) {
            case 'api':
                // Update API configuration
                $apiConfig = "<?php\nreturn [\n" .
                    "    'subdomain' => '" . addslashes($_POST['api_subdomain']) . "',\n" .
                    "    'api_token' => '" . addslashes($_POST['api_token']) . "',\n" .
                    "    'base_url' => 'https://api.current-rms.com/api/v1',\n" .
                    "    'timeout' => 30,\n" .
                    "    'verify_ssl' => true,\n" .
                    "];\n";

                file_put_contents(__DIR__ . '/config/api.php', $apiConfig);
                flash('success', 'API settings updated successfully.');
                break;

            case 'general':
                // Update general settings
                $prefix = Database::getPrefix();
                Database::query("UPDATE {$prefix}settings SET value = ?, updated_at = NOW() WHERE `key` = 'company_name'", [$_POST['company_name']]);
                Database::query("UPDATE {$prefix}settings SET value = ?, updated_at = NOW() WHERE `key` = 'timezone'", [$_POST['timezone']]);
                Database::query("UPDATE {$prefix}settings SET value = ?, updated_at = NOW() WHERE `key` = 'date_format'", [$_POST['date_format']]);
                Database::query("UPDATE {$prefix}settings SET value = ?, updated_at = NOW() WHERE `key` = 'currency_symbol'", [$_POST['currency_symbol']]);
                flash('success', 'General settings updated successfully.');
                break;
        }
    } catch (Exception $e) {
        flash('error', 'Failed to save settings: ' . $e->getMessage());
    }

    header('Location: settings.php');
    exit;
}

// Load current settings
$apiConfig = require __DIR__ . '/config/api.php';
$prefix = Database::getPrefix();
$settings = [];
$settingsRows = Database::fetchAll("SELECT `key`, `value` FROM {$prefix}settings");
foreach ($settingsRows as $row) {
    $settings[$row['key']] = $row['value'];
}

// Test API connection
$apiConnected = false;
if (!empty($apiConfig['subdomain']) && !empty($apiConfig['api_token'])) {
    try {
        $api = getApiClient();
        $apiConnected = $api && $api->testConnection();
    } catch (Exception $e) {
        $apiConnected = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <title><?php echo e($pageTitle); ?> - CurrentRMS Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        // Load theme immediately to prevent flash of wrong theme
        (function() {
            var theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <div class="app-layout">
        <?php include 'includes/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/partials/header.php'; ?>

            <div class="content">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <path d="M22 4L12 14.01l-3-3"/>
                        </svg>
                        <?php echo e($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M15 9l-6 6M9 9l6 6"/>
                        </svg>
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                    <!-- API Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">CurrentRMS API Settings</h3>
                            <?php if ($apiConnected): ?>
                                <span class="badge badge-success">Connected</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Not Connected</span>
                            <?php endif; ?>
                        </div>
                        <form method="POST">
                            <div class="card-body">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="api">

                                <div class="form-group">
                                    <label class="form-label">Subdomain</label>
                                    <input type="text" name="api_subdomain" class="form-control"
                                           value="<?php echo e($apiConfig['subdomain']); ?>"
                                           placeholder="your-company">
                                    <div class="form-hint">Your CurrentRMS subdomain (from your-company.current-rms.com)</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">API Token</label>
                                    <input type="password" name="api_token" class="form-control"
                                           value="<?php echo e($apiConfig['api_token']); ?>"
                                           placeholder="Enter your API token">
                                    <div class="form-hint">Get this from System Setup → Integrations → API in CurrentRMS</div>
                                </div>
                            </div>
                            <div class="card-footer" style="display: flex; gap: 12px;">
                                <button type="submit" class="btn btn-primary">Save API Settings</button>
                                <button type="button" class="btn btn-secondary" id="test-api-btn">Test Connection</button>
                            </div>
                        </form>
                        <div id="api-test-result" style="display: none; padding: 16px; border-top: 1px solid var(--gray-200);"></div>
                    </div>

                    <!-- General Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">General Settings</h3>
                        </div>
                        <form method="POST">
                            <div class="card-body">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="general">

                                <div class="form-group">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control"
                                           value="<?php echo e($settings['company_name'] ?? ''); ?>">
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Timezone</label>
                                    <select name="timezone" class="form-control">
                                        <?php
                                        $timezones = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Asia/Tokyo', 'Australia/Sydney'];
                                        foreach ($timezones as $tz):
                                        ?>
                                            <option value="<?php echo $tz; ?>" <?php echo ($settings['timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>><?php echo $tz; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Date Format</label>
                                    <select name="date_format" class="form-control">
                                        <option value="M j, Y" <?php echo ($settings['date_format'] ?? '') === 'M j, Y' ? 'selected' : ''; ?>>Jan 1, 2024</option>
                                        <option value="Y-m-d" <?php echo ($settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-01</option>
                                        <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>01/01/2024</option>
                                        <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>01/01/2024</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Currency Symbol</label>
                                    <select name="currency_symbol" class="form-control" style="width: 200px;">
                                        <?php
                                        $currencies = [
                                            '£' => '£ - British Pound',
                                            '$' => '$ - US Dollar',
                                            '€' => '€ - Euro',
                                            'A$' => 'A$ - Australian Dollar',
                                            'C$' => 'C$ - Canadian Dollar',
                                            'NZ$' => 'NZ$ - New Zealand Dollar',
                                            'CHF' => 'CHF - Swiss Franc',
                                            'R' => 'R - South African Rand',
                                            'AED' => 'AED - UAE Dirham',
                                        ];
                                        $currentSymbol = $settings['currency_symbol'] ?? config('app.currency_symbol') ?? '£';
                                        foreach ($currencies as $symbol => $label):
                                        ?>
                                            <option value="<?php echo e($symbol); ?>" <?php echo $currentSymbol === $symbol ? 'selected' : ''; ?>><?php echo e($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Save General Settings</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- System Info -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">System Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <tr>
                                    <td style="width: 200px;"><strong>PHP Version</strong></td>
                                    <td><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Application Version</strong></td>
                                    <td><?php echo config('app.version'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Database</strong></td>
                                    <td><?php echo config('database.driver'); ?> (<?php echo config('database.host'); ?>)</td>
                                </tr>
                                <tr>
                                    <td><strong>Server</strong></td>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Installed</strong></td>
                                    <td><?php echo file_exists(__DIR__ . '/.installed') ? file_get_contents(__DIR__ . '/.installed') : 'Unknown'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        document.getElementById('test-api-btn').addEventListener('click', async function() {
            const btn = this;
            const resultDiv = document.getElementById('api-test-result');
            const subdomain = document.querySelector('input[name="api_subdomain"]').value;
            const apiToken = document.querySelector('input[name="api_token"]').value;

            if (!subdomain || !apiToken) {
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="alert alert-warning" style="margin: 0;">Please enter both subdomain and API token.</div>';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Testing...';
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="text-muted">Connecting to CurrentRMS API...</div>';

            try {
                const formData = new FormData();
                formData.append('subdomain', subdomain);
                formData.append('api_token', apiToken);

                const response = await fetch('api/test-connection.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success" style="margin: 0;"><strong>Success!</strong> ' + data.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-danger" style="margin: 0;"><strong>Connection Failed:</strong> ' + data.error + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="alert alert-danger" style="margin: 0;"><strong>Error:</strong> Failed to test connection. Please try again.</div>';
            }

            btn.disabled = false;
            btn.textContent = 'Test Connection';
        });
    </script>
</body>
</html>
