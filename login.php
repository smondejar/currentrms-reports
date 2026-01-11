<?php
/**
 * Login Page
 */

// Check if already installed FIRST
if (!file_exists(__DIR__ . '/.installed')) {
    header('Location: install/');
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';

// Check if database is properly configured
if (!Database::isInitialized()) {
    die('<h1>Database Not Configured</h1><p>Please <a href="install/">run the installer</a> to configure the database.</p>');
}

// Redirect if already logged in
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (Auth::login($email, $password)) {
        $redirect = $_GET['redirect'] ?? 'index.php';
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CurrentRMS Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            max-width: 420px;
            width: 100%;
            padding: 40px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        .login-logo svg {
            width: 32px;
            height: 32px;
            color: white;
        }
        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        .login-subtitle {
            color: #6b7280;
            font-size: 14px;
        }
        .login-form .form-group {
            margin-bottom: 20px;
        }
        .login-form .form-control {
            padding: 14px 16px;
            font-size: 15px;
        }
        .login-form .btn {
            width: 100%;
            padding: 14px;
            font-size: 16px;
        }
        .login-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .login-footer {
            margin-top: 24px;
            text-align: center;
        }
        .login-footer a {
            color: #667eea;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to your CurrentRMS Report Builder</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>

        <div class="login-footer">
            <a href="forgot-password.php">Forgot your password?</a>
        </div>
    </div>
</body>
</html>
