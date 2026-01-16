<?php
/**
 * Report Manager - Save, Load, and Manage Reports
 */

class ReportManager
{
    /**
     * Save a new report
     */
    public static function save(array $data): int
    {
        $report = [
            'user_id' => $data['user_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'module' => $data['module'],
            'config' => json_encode([
                'columns' => $data['columns'] ?? [],
                'filters' => $data['filters'] ?? [],
                'sorting' => $data['sorting'] ?? null,
            ]),
            'is_public' => $data['is_public'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return Database::insert('reports', $report);
    }

    /**
     * Update an existing report
     */
    public static function update(int $id, array $data): bool
    {
        $report = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'module' => $data['module'],
            'config' => json_encode([
                'columns' => $data['columns'] ?? [],
                'filters' => $data['filters'] ?? [],
                'sorting' => $data['sorting'] ?? null,
            ]),
            'is_public' => $data['is_public'] ?? 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return Database::update('reports', $report, 'id = ?', [$id]) > 0;
    }

    /**
     * Get a report by ID
     */
    public static function get(int $id): ?array
    {
        $prefix = Database::getPrefix();
        $report = Database::fetch(
            "SELECT r.*, u.name as user_name
             FROM {$prefix}reports r
             LEFT JOIN {$prefix}users u ON r.user_id = u.id
             WHERE r.id = ?",
            [$id]
        );

        if ($report) {
            $report['config'] = json_decode($report['config'], true);
        }

        return $report;
    }

    /**
     * Get reports for a user (including public reports)
     */
    public static function getForUser(int $userId): array
    {
        $prefix = Database::getPrefix();
        $reports = Database::fetchAll(
            "SELECT r.*, u.name as user_name
             FROM {$prefix}reports r
             LEFT JOIN {$prefix}users u ON r.user_id = u.id
             WHERE r.user_id = ? OR r.is_public = 1
             ORDER BY r.updated_at DESC",
            [$userId]
        );

        foreach ($reports as &$report) {
            $report['config'] = json_decode($report['config'], true);
        }

        return $reports;
    }

    /**
     * Get all reports (admin)
     */
    public static function getAll(): array
    {
        $prefix = Database::getPrefix();
        $reports = Database::fetchAll(
            "SELECT r.*, u.name as user_name
             FROM {$prefix}reports r
             LEFT JOIN {$prefix}users u ON r.user_id = u.id
             ORDER BY r.updated_at DESC"
        );

        foreach ($reports as &$report) {
            $report['config'] = json_decode($report['config'], true);
        }

        return $reports;
    }

    /**
     * Delete a report
     */
    public static function delete(int $id): bool
    {
        return Database::delete('reports', 'id = ?', [$id]) > 0;
    }

    /**
     * Check if user can access report
     */
    public static function canAccess(int $reportId, int $userId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return true;
        }

        $prefix = Database::getPrefix();
        $report = Database::fetch(
            "SELECT user_id, is_public FROM {$prefix}reports WHERE id = ?",
            [$reportId]
        );

        if (!$report) {
            return false;
        }

        return $report['user_id'] == $userId || $report['is_public'] == 1;
    }

    /**
     * Check if user can edit report
     */
    public static function canEdit(int $reportId, int $userId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return true;
        }

        $prefix = Database::getPrefix();
        $report = Database::fetch(
            "SELECT user_id FROM {$prefix}reports WHERE id = ?",
            [$reportId]
        );

        return $report && $report['user_id'] == $userId;
    }

    /**
     * Duplicate a report
     */
    public static function duplicate(int $id, int $newUserId): int
    {
        $report = self::get($id);

        if (!$report) {
            throw new Exception('Report not found');
        }

        return self::save([
            'user_id' => $newUserId,
            'name' => $report['name'] . ' (Copy)',
            'description' => $report['description'],
            'module' => $report['module'],
            'columns' => $report['config']['columns'],
            'filters' => $report['config']['filters'],
            'sorting' => $report['config']['sorting'],
            'is_public' => 0,
        ]);
    }

    /**
     * Get recent reports for user
     */
    public static function getRecent(int $userId, int $limit = 5): array
    {
        $prefix = Database::getPrefix();
        $reports = Database::fetchAll(
            "SELECT r.*, u.name as user_name
             FROM {$prefix}reports r
             LEFT JOIN {$prefix}users u ON r.user_id = u.id
             WHERE r.user_id = ? OR r.is_public = 1
             ORDER BY r.updated_at DESC
             LIMIT {$limit}",
            [$userId]
        );

        foreach ($reports as &$report) {
            $report['config'] = json_decode($report['config'], true);
        }

        return $reports;
    }

    /**
     * Increment the run count for a report
     */
    public static function incrementRunCount(int $id): bool
    {
        $prefix = Database::getPrefix();
        $stmt = Database::query(
            "UPDATE {$prefix}reports SET run_count = COALESCE(run_count, 0) + 1 WHERE id = ?",
            [$id]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Get reports suitable for widgets (with minimal data)
     */
    public static function getForWidgets(int $userId, bool $isAdmin = false): array
    {
        $prefix = Database::getPrefix();

        if ($isAdmin) {
            $reports = Database::fetchAll(
                "SELECT r.id, r.name, r.module, r.description, r.is_public, r.user_id, u.name as user_name
                 FROM {$prefix}reports r
                 LEFT JOIN {$prefix}users u ON r.user_id = u.id
                 ORDER BY r.name ASC"
            );
        } else {
            $reports = Database::fetchAll(
                "SELECT r.id, r.name, r.module, r.description, r.is_public, r.user_id, u.name as user_name
                 FROM {$prefix}reports r
                 LEFT JOIN {$prefix}users u ON r.user_id = u.id
                 WHERE r.user_id = ? OR r.is_public = 1
                 ORDER BY r.name ASC",
                [$userId]
            );
        }

        return $reports;
    }
}
