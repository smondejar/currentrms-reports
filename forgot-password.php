<?php
/**
 * Forgot Password Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

    $token = Auth::generateResetToken($email);

    // Always show success to prevent email enumeration
    $success = 'If an account with that email exists, you will receive password reset instructions.';

    // In production, you would send an email here
    // For demo purposes, we just show the message
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - CurrentRMS Report Builder</title>
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
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1 class="login-title">Reset Password</h1>
            <p class="login-subtitle">Enter your email to receive reset instructions</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 20px;"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="margin-bottom: 20px;"><?php echo e($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>

            <button type="submit" class="btn btn-primary w-full">Send Reset Link</button>
        </form>

        <div style="text-align: center; margin-top: 20px;">
            <a href="login.php" style="color: #667eea; font-size: 14px;">← Back to Login</a>
        </div>
    </div>
</body>
</html>
