<?php
/**
 * Dashboard Widget Manager
 * Handles user dashboard widgets and layouts
 */

class Dashboard
{
    const WIDGET_TYPES = [
        'chart_bar' => ['name' => 'Bar Chart', 'icon' => 'bar-chart-2'],
        'chart_line' => ['name' => 'Line Chart', 'icon' => 'trending-up'],
        'chart_pie' => ['name' => 'Pie Chart', 'icon' => 'pie-chart'],
        'chart_doughnut' => ['name' => 'Doughnut Chart', 'icon' => 'circle'],
        'stat_card' => ['name' => 'Stat Card', 'icon' => 'hash'],
        'timeline' => ['name' => 'Timeline', 'icon' => 'clock'],
        'table' => ['name' => 'Data Table', 'icon' => 'table'],
        'calendar' => ['name' => 'Calendar View', 'icon' => 'calendar'],
    ];

    const GRID_SIZES = [
        'small' => ['cols' => 3, 'rows' => 2],
        'medium' => ['cols' => 6, 'rows' => 3],
        'large' => ['cols' => 9, 'rows' => 4],
        'wide' => ['cols' => 12, 'rows' => 3],
        'tall' => ['cols' => 6, 'rows' => 6],
        'full' => ['cols' => 12, 'rows' => 6],
    ];

    /**
     * Get dashboard for user
     */
    public static function getForUser(int $userId): array
    {
        $prefix = Database::getPrefix();
        $dashboard = Database::fetch(
            "SELECT * FROM {$prefix}dashboards WHERE user_id = ?",
            [$userId]
        );

        if (!$dashboard) {
            // Create default dashboard
            $dashboardId = self::createDefault($userId);
            $dashboard = Database::fetch(
                "SELECT * FROM {$prefix}dashboards WHERE id = ?",
                [$dashboardId]
            );
        }

        $dashboard['config'] = json_decode($dashboard['config'] ?? '{}', true);

        // Get widgets
        $widgets = Database::fetchAll(
            "SELECT * FROM {$prefix}widgets WHERE dashboard_id = ? ORDER BY position",
            [$dashboard['id']]
        );

        foreach ($widgets as &$widget) {
            $widget['config'] = json_decode($widget['config'] ?? '{}', true);
        }

        $dashboard['widgets'] = $widgets;

        return $dashboard;
    }

    /**
     * Create default dashboard
     */
    public static function createDefault(int $userId): int
    {
        $dashboardId = Database::insert('dashboards', [
            'user_id' => $userId,
            'name' => 'My Dashboard',
            'config' => json_encode(['columns' => 12]),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Add default widgets
        $defaultWidgets = [
            [
                'type' => 'stat_card',
                'title' => 'Active Opportunities',
                'size' => 'small',
                'position' => 0,
                'config' => [
                    'module' => 'opportunities',
                    'metric' => 'count',
                    'filters' => [['field' => 'state', 'predicate' => 'eq', 'value' => 'active']],
                    'color' => 'primary',
                    'icon' => 'target',
                ],
            ],
            [
                'type' => 'stat_card',
                'title' => 'Total Revenue (Month)',
                'size' => 'small',
                'position' => 1,
                'config' => [
                    'module' => 'invoices',
                    'metric' => 'sum',
                    'field' => 'total',
                    'filters' => [],
                    'color' => 'success',
                    'icon' => 'dollar-sign',
                    'format' => 'currency',
                ],
            ],
            [
                'type' => 'stat_card',
                'title' => 'Pending Invoices',
                'size' => 'small',
                'position' => 2,
                'config' => [
                    'module' => 'invoices',
                    'metric' => 'count',
                    'filters' => [['field' => 'state', 'predicate' => 'eq', 'value' => 'sent']],
                    'color' => 'warning',
                    'icon' => 'file-text',
                ],
            ],
            [
                'type' => 'stat_card',
                'title' => 'Products in Stock',
                'size' => 'small',
                'position' => 3,
                'config' => [
                    'module' => 'products',
                    'metric' => 'count',
                    'filters' => [],
                    'color' => 'info',
                    'icon' => 'box',
                ],
            ],
            [
                'type' => 'chart_bar',
                'title' => 'Revenue by Month',
                'size' => 'medium',
                'position' => 4,
                'config' => [
                    'module' => 'invoices',
                    'group_by' => 'invoice_date',
                    'group_format' => 'month',
                    'aggregate' => 'sum',
                    'field' => 'total',
                ],
            ],
            [
                'type' => 'chart_pie',
                'title' => 'Opportunities by Status',
                'size' => 'medium',
                'position' => 5,
                'config' => [
                    'module' => 'opportunities',
                    'group_by' => 'status',
                    'aggregate' => 'count',
                ],
            ],
            [
                'type' => 'timeline',
                'title' => 'Upcoming Events',
                'size' => 'medium',
                'position' => 6,
                'config' => [
                    'module' => 'opportunities',
                    'date_field' => 'starts_at',
                    'title_field' => 'subject',
                    'filters' => [['field' => 'starts_at', 'predicate' => 'gteq', 'value' => 'today']],
                    'limit' => 10,
                ],
            ],
            [
                'type' => 'table',
                'title' => 'Recent Invoices',
                'size' => 'medium',
                'position' => 7,
                'config' => [
                    'module' => 'invoices',
                    'columns' => ['number', 'member_name', 'total', 'status'],
                    'sorting' => ['field' => 'created_at', 'direction' => 'desc'],
                    'limit' => 10,
                ],
            ],
        ];

        foreach ($defaultWidgets as $widget) {
            self::addWidget($dashboardId, $widget);
        }

        return $dashboardId;
    }

    /**
     * Add widget to dashboard
     */
    public static function addWidget(int $dashboardId, array $data): int
    {
        $size = self::GRID_SIZES[$data['size'] ?? 'small'];

        return Database::insert('widgets', [
            'dashboard_id' => $dashboardId,
            'type' => $data['type'],
            'title' => $data['title'],
            'position' => $data['position'] ?? 0,
            'grid_cols' => $size['cols'],
            'grid_rows' => $size['rows'],
            'config' => json_encode($data['config'] ?? []),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update widget
     */
    public static function updateWidget(int $widgetId, array $data): bool
    {
        $update = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($data['title'])) {
            $update['title'] = $data['title'];
        }
        if (isset($data['position'])) {
            $update['position'] = $data['position'];
        }
        if (isset($data['size'])) {
            $size = self::GRID_SIZES[$data['size']];
            $update['grid_cols'] = $size['cols'];
            $update['grid_rows'] = $size['rows'];
        }
        if (isset($data['config'])) {
            $update['config'] = json_encode($data['config']);
        }

        return Database::update('widgets', $update, 'id = ?', [$widgetId]) > 0;
    }

    /**
     * Delete widget
     */
    public static function deleteWidget(int $widgetId): bool
    {
        return Database::delete('widgets', 'id = ?', [$widgetId]) > 0;
    }

    /**
     * Update widget positions (for drag & drop)
     */
    public static function updatePositions(array $positions): void
    {
        foreach ($positions as $widgetId => $position) {
            Database::update('widgets', ['position' => $position], 'id = ?', [$widgetId]);
        }
    }

    /**
     * Get widget types
     */
    public static function getWidgetTypes(): array
    {
        return self::WIDGET_TYPES;
    }

    /**
     * Get available sizes
     */
    public static function getSizes(): array
    {
        return self::GRID_SIZES;
    }

    /**
     * Duplicate dashboard
     */
    public static function duplicate(int $dashboardId, int $newUserId): int
    {
        $prefix = Database::getPrefix();
        $dashboard = Database::fetch(
            "SELECT * FROM {$prefix}dashboards WHERE id = ?",
            [$dashboardId]
        );

        if (!$dashboard) {
            throw new Exception('Dashboard not found');
        }

        $newDashboardId = Database::insert('dashboards', [
            'user_id' => $newUserId,
            'name' => $dashboard['name'] . ' (Copy)',
            'config' => $dashboard['config'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Copy widgets
        $widgets = Database::fetchAll(
            "SELECT * FROM {$prefix}widgets WHERE dashboard_id = ?",
            [$dashboardId]
        );

        foreach ($widgets as $widget) {
            Database::insert('widgets', [
                'dashboard_id' => $newDashboardId,
                'type' => $widget['type'],
                'title' => $widget['title'],
                'position' => $widget['position'],
                'grid_cols' => $widget['grid_cols'],
                'grid_rows' => $widget['grid_rows'],
                'config' => $widget['config'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $newDashboardId;
    }

    /**
     * Reset dashboard to default
     */
    public static function resetToDefault(int $dashboardId): void
    {
        $prefix = Database::getPrefix();
        $dashboard = Database::fetch(
            "SELECT user_id FROM {$prefix}dashboards WHERE id = ?",
            [$dashboardId]
        );

        if (!$dashboard) {
            throw new Exception('Dashboard not found');
        }

        // Delete all widgets
        Database::delete('widgets', 'dashboard_id = ?', [$dashboardId]);

        // Delete dashboard
        Database::delete('dashboards', 'id = ?', [$dashboardId]);

        // Create new default
        self::createDefault($dashboard['user_id']);
    }
}
