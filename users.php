<?php
/**
 * User Management Page
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::MANAGE_USERS);

$pageTitle = 'User Management';
$success = flash('success');
$error = flash('error');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';
                Auth::createUser([
                    'name' => $_POST['name'],
                    'email' => $_POST['email'],
                    'password' => $_POST['password'],
                    'role' => $_POST['role'],
                    'permissions' => $permissions,
                    'status' => 'active',
                ]);
                flash('success', 'User created successfully.');
                break;

            case 'update':
                $data = [
                    'name' => $_POST['name'],
                    'email' => $_POST['email'],
                    'role' => $_POST['role'],
                    'permissions' => isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]',
                ];
                if (!empty($_POST['password'])) {
                    $data['password'] = $_POST['password'];
                }
                Auth::updateUser((int) $_POST['user_id'], $data);
                flash('success', 'User updated successfully.');
                break;

            case 'delete':
                if ((int) $_POST['user_id'] === Auth::id()) {
                    flash('error', 'You cannot delete your own account.');
                } else {
                    Auth::deleteUser((int) $_POST['user_id']);
                    flash('success', 'User deleted successfully.');
                }
                break;

            case 'toggle_status':
                $prefix = Database::getPrefix();
                $user = Database::fetch("SELECT status FROM {$prefix}users WHERE id = ?", [$_POST['user_id']]);
                $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
                Database::update('users', ['status' => $newStatus], 'id = ?', [$_POST['user_id']]);
                flash('success', 'User status updated.');
                break;
        }
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }

    header('Location: users.php');
    exit;
}

// Get all users
$users = Auth::getAllUsers();
$allPermissions = Permissions::all();
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

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Users</h3>
                        <button class="btn btn-primary btn-sm" data-modal="add-user-modal">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            Add User
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </div>
                                                    <?php echo e($user['name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo e($user['email']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'primary' : 'gray'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <button class="btn btn-sm btn-secondary"
                                                            onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                        Edit
                                                    </button>
                                                    <?php if ($user['id'] !== Auth::id()): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger"
                                                                    data-confirm="Are you sure you want to delete this user?">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal-overlay" id="add-user-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Add New User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="create">

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Permissions</label>
                        <div class="checkbox-group">
                            <?php foreach ($allPermissions as $key => $label): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="permissions[]" value="<?php echo e($key); ?>" class="checkbox-input">
                                    <span><?php echo e($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="edit-user-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Edit User</h3>
                <button class="modal-close">&times;</button>
            </div>
            <form method="POST" id="edit-user-form">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="edit-user-id">

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit-email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" minlength="8">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" id="edit-role" class="form-control">
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Permissions</label>
                        <div class="checkbox-group" id="edit-permissions">
                            <?php foreach ($allPermissions as $key => $label): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="permissions[]" value="<?php echo e($key); ?>" class="checkbox-input">
                                    <span><?php echo e($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit-user-id').value = user.id;
            document.getElementById('edit-name').value = user.name;
            document.getElementById('edit-email').value = user.email;
            document.getElementById('edit-role').value = user.role;

            // Clear all permission checkboxes
            document.querySelectorAll('#edit-permissions input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });

            // Set permissions
            const permissions = JSON.parse(user.permissions || '[]');
            permissions.forEach(perm => {
                const checkbox = document.querySelector(`#edit-permissions input[value="${perm}"]`);
                if (checkbox) checkbox.checked = true;
            });

            // Show modal
            document.getElementById('edit-user-modal').classList.add('active');
        }
    </script>
</body>
</html>
