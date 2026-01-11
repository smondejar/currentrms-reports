<?php
/**
 * User Profile Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();

$pageTitle = 'My Profile';
$user = Auth::user();
$success = flash('success');
$error = flash('error');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $section = $_POST['section'] ?? '';

    try {
        switch ($section) {
            case 'profile':
                Auth::updateUser(Auth::id(), [
                    'name' => $_POST['name'],
                    'email' => $_POST['email'],
                ]);
                flash('success', 'Profile updated successfully.');
                break;

            case 'password':
                // Verify current password
                if (!password_verify($_POST['current_password'], $user['password'])) {
                    flash('error', 'Current password is incorrect.');
                    break;
                }

                if ($_POST['new_password'] !== $_POST['confirm_password']) {
                    flash('error', 'New passwords do not match.');
                    break;
                }

                Auth::updateUser(Auth::id(), [
                    'password' => $_POST['new_password'],
                ]);
                flash('success', 'Password changed successfully.');
                break;
        }
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }

    header('Location: profile.php');
    exit;
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
</head>
<body>
    <div class="app-layout">
        <?php include 'includes/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/partials/header.php'; ?>

            <div class="content">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
                    <!-- Profile Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Profile Information</h3>
                        </div>
                        <form method="POST">
                            <div class="card-body">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="profile">

                                <div style="text-align: center; margin-bottom: 24px;">
                                    <div class="user-avatar" style="width: 80px; height: 80px; font-size: 32px; margin: 0 auto;">
                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control"
                                           value="<?php echo e($user['name']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control"
                                           value="<?php echo e($user['email']); ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Change Password</h3>
                        </div>
                        <form method="POST">
                            <div class="card-body">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="section" value="password">

                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="8">
                                    <div class="form-hint">Minimum 8 characters</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required minlength="8">
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Activity -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Account Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <tr>
                                    <td style="width: 200px;"><strong>Account Created</strong></td>
                                    <td><?php echo formatDateTime($user['created_at']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Last Login</strong></td>
                                    <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status</strong></td>
                                    <td><span class="badge badge-success"><?php echo ucfirst($user['status']); ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
