<?php
/**
 * Authentication and Session Management
 */

class Auth
{
    private static ?array $user = null;

    /**
     * Initialize authentication
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_id'])) {
            self::$user = self::getUserById($_SESSION['user_id']);
        }
    }

    /**
     * Attempt to login a user
     */
    public static function login(string $email, string $password): bool
    {
        $prefix = Database::getPrefix();
        $user = Database::fetch(
            "SELECT * FROM {$prefix}users WHERE email = ? AND status = 'active' LIMIT 1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        // Update last login
        Database::update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        $_SESSION['user_id'] = $user['id'];
        self::$user = $user;

        return true;
    }

    /**
     * Logout current user
     */
    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        return self::$user !== null;
    }

    /**
     * Get current user
     */
    public static function user(): ?array
    {
        return self::$user;
    }

    /**
     * Get user ID
     */
    public static function id(): ?int
    {
        return self::$user['id'] ?? null;
    }

    /**
     * Check if current user has a specific permission
     */
    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        // Admin has all permissions
        if (self::$user['role'] === 'admin') {
            return true;
        }

        $permissions = json_decode(self::$user['permissions'] ?? '[]', true);
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool
    {
        return self::$user && self::$user['role'] === 'admin';
    }

    /**
     * Get user by ID
     */
    private static function getUserById(int $id): ?array
    {
        $prefix = Database::getPrefix();
        return Database::fetch(
            "SELECT * FROM {$prefix}users WHERE id = ? AND status = 'active' LIMIT 1",
            [$id]
        );
    }

    /**
     * Create a new user
     */
    public static function createUser(array $data): int
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return Database::insert('users', $data);
    }

    /**
     * Update user
     */
    public static function updateUser(int $id, array $data): bool
    {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        return Database::update('users', $data, 'id = ?', [$id]) > 0;
    }

    /**
     * Delete user
     */
    public static function deleteUser(int $id): bool
    {
        return Database::update('users', ['status' => 'deleted'], 'id = ?', [$id]) > 0;
    }

    /**
     * Get all users
     */
    public static function getAllUsers(): array
    {
        $prefix = Database::getPrefix();
        return Database::fetchAll(
            "SELECT id, name, email, role, permissions, status, last_login, created_at
             FROM {$prefix}users WHERE status != 'deleted' ORDER BY name"
        );
    }

    /**
     * Generate password reset token
     */
    public static function generateResetToken(string $email): ?string
    {
        $prefix = Database::getPrefix();
        $user = Database::fetch(
            "SELECT id FROM {$prefix}users WHERE email = ? AND status = 'active'",
            [$email]
        );

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        Database::update('users', [
            'reset_token' => $token,
            'reset_expires' => $expires
        ], 'id = ?', [$user['id']]);

        return $token;
    }

    /**
     * Reset password with token
     */
    public static function resetPassword(string $token, string $newPassword): bool
    {
        $prefix = Database::getPrefix();
        $user = Database::fetch(
            "SELECT id FROM {$prefix}users
             WHERE reset_token = ? AND reset_expires > NOW() AND status = 'active'",
            [$token]
        );

        if (!$user) {
            return false;
        }

        Database::update('users', [
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_expires' => null
        ], 'id = ?', [$user['id']]);

        return true;
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Require specific permission
     */
    public static function requirePermission(string $permission): void
    {
        self::requireAuth();
        if (!self::can($permission)) {
            http_response_code(403);
            die('Access denied');
        }
    }

    /**
     * Require admin role
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Admin access required');
        }
    }
}

// Available permissions
class Permissions
{
    const VIEW_REPORTS = 'view_reports';
    const CREATE_REPORTS = 'create_reports';
    const EDIT_REPORTS = 'edit_reports';
    const DELETE_REPORTS = 'delete_reports';
    const EXPORT_REPORTS = 'export_reports';
    const VIEW_DASHBOARD = 'view_dashboard';
    const EDIT_DASHBOARD = 'edit_dashboard';
    const MANAGE_USERS = 'manage_users';
    const VIEW_ANALYTICS = 'view_analytics';
    const SYSTEM_SETTINGS = 'system_settings';

    public static function all(): array
    {
        return [
            self::VIEW_REPORTS => 'View Reports',
            self::CREATE_REPORTS => 'Create Reports',
            self::EDIT_REPORTS => 'Edit Reports',
            self::DELETE_REPORTS => 'Delete Reports',
            self::EXPORT_REPORTS => 'Export Reports',
            self::VIEW_DASHBOARD => 'View Dashboard',
            self::EDIT_DASHBOARD => 'Edit Dashboard',
            self::MANAGE_USERS => 'Manage Users',
            self::VIEW_ANALYTICS => 'View Analytics',
            self::SYSTEM_SETTINGS => 'System Settings',
        ];
    }
}
