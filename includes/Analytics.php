<?php
/**
 * Analytics Engine - Data aggregation and chart data preparation
 */

class Analytics
{
    private CurrentRMSClient $api;

    public function __construct(CurrentRMSClient $api)
    {
        $this->api = $api;
    }

    /**
     * Get summary statistics for a module
     */
    public function getSummary(string $module, array $filters = []): array
    {
        $builder = new ReportBuilder($this->api);
        $builder->setModule($module)->setFilters($filters);

        $data = $builder->fetchAll(5000);

        $summary = [
            'total_count' => count($data),
            'metrics' => [],
        ];

        // Calculate module-specific metrics
        switch ($module) {
            case 'opportunities':
                $summary['metrics'] = $this->calculateOpportunityMetrics($data);
                break;
            case 'invoices':
                $summary['metrics'] = $this->calculateInvoiceMetrics($data);
                break;
            case 'products':
                $summary['metrics'] = $this->calculateProductMetrics($data);
                break;
            case 'members':
                $summary['metrics'] = $this->calculateMemberMetrics($data);
                break;
            default:
                $summary['metrics'] = $this->calculateGenericMetrics($data);
        }

        return $summary;
    }

    /**
     * Get chart data grouped by field
     */
    public function getChartData(string $module, string $groupBy, string $aggregateField = null, string $function = 'count', array $filters = []): array
    {
        $builder = new ReportBuilder($this->api);
        $builder->setModule($module)->setFilters($filters);

        $data = $builder->fetchAll(5000);

        $groups = [];
        foreach ($data as $row) {
            $key = $this->extractGroupKey($row, $groupBy);
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            if ($aggregateField) {
                $groups[$key][] = $row[$aggregateField] ?? 0;
            } else {
                $groups[$key][] = 1;
            }
        }

        $result = [];
        foreach ($groups as $key => $values) {
            $result[$key] = $this->aggregate($values, $function);
        }

        // Sort by key for consistent display
        ksort($result);

        return [
            'labels' => array_keys($result),
            'values' => array_values($result),
            'total' => array_sum(array_values($result)),
        ];
    }

    /**
     * Get time series data
     */
    public function getTimeSeries(string $module, string $dateField, string $aggregateField = null, string $function = 'count', string $interval = 'day', array $filters = []): array
    {
        $builder = new ReportBuilder($this->api);
        $builder->setModule($module)->setFilters($filters);

        $data = $builder->fetchAll(5000);

        $groups = [];
        foreach ($data as $row) {
            $date = $row[$dateField] ?? null;
            if (!$date) continue;

            $key = $this->formatDateForInterval($date, $interval);

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            if ($aggregateField) {
                $groups[$key][] = $row[$aggregateField] ?? 0;
            } else {
                $groups[$key][] = 1;
            }
        }

        $result = [];
        foreach ($groups as $key => $values) {
            $result[$key] = $this->aggregate($values, $function);
        }

        // Sort by date
        ksort($result);

        return [
            'labels' => array_keys($result),
            'values' => array_values($result),
            'interval' => $interval,
        ];
    }

    /**
     * Get timeline events
     */
    public function getTimeline(string $module, string $dateField, string $titleField, array $filters = [], int $limit = 20): array
    {
        $builder = new ReportBuilder($this->api);
        $builder->setModule($module)
            ->setFilters($filters)
            ->setSorting($dateField, 'asc');

        $result = $builder->execute();
        $data = array_slice($result['data'], 0, $limit);

        $events = [];
        foreach ($data as $row) {
            $events[] = [
                'id' => $row['id'] ?? null,
                'date' => $row[$dateField] ?? null,
                'title' => $row[$titleField] ?? 'Untitled',
                'data' => $row,
            ];
        }

        return $events;
    }

    /**
     * Get comparison data (e.g., this month vs last month)
     */
    public function getComparison(string $module, string $dateField, string $aggregateField = null, string $function = 'sum'): array
    {
        $now = new DateTime();
        $thisMonthStart = $now->format('Y-m-01');
        $thisMonthEnd = $now->format('Y-m-t');

        $lastMonth = (clone $now)->modify('-1 month');
        $lastMonthStart = $lastMonth->format('Y-m-01');
        $lastMonthEnd = $lastMonth->format('Y-m-t');

        // This month
        $thisMonthFilters = [
            ['field' => $dateField, 'predicate' => 'gteq', 'value' => $thisMonthStart],
            ['field' => $dateField, 'predicate' => 'lteq', 'value' => $thisMonthEnd],
        ];

        // Last month
        $lastMonthFilters = [
            ['field' => $dateField, 'predicate' => 'gteq', 'value' => $lastMonthStart],
            ['field' => $dateField, 'predicate' => 'lteq', 'value' => $lastMonthEnd],
        ];

        $builder = new ReportBuilder($this->api);

        $builder->setModule($module)->setFilters($thisMonthFilters);
        $thisMonthData = $builder->fetchAll(5000);

        $builder->reset()->setModule($module)->setFilters($lastMonthFilters);
        $lastMonthData = $builder->fetchAll(5000);

        $thisMonthValue = $this->aggregateDataset($thisMonthData, $aggregateField, $function);
        $lastMonthValue = $this->aggregateDataset($lastMonthData, $aggregateField, $function);

        $change = $lastMonthValue > 0
            ? (($thisMonthValue - $lastMonthValue) / $lastMonthValue) * 100
            : ($thisMonthValue > 0 ? 100 : 0);

        return [
            'current' => [
                'label' => $now->format('F Y'),
                'value' => $thisMonthValue,
            ],
            'previous' => [
                'label' => $lastMonth->format('F Y'),
                'value' => $lastMonthValue,
            ],
            'change' => round($change, 1),
            'trend' => $change >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Calculate opportunity-specific metrics
     */
    private function calculateOpportunityMetrics(array $data): array
    {
        $totalValue = 0;
        $statusCounts = [];

        foreach ($data as $row) {
            $totalValue += $row['grand_total'] ?? 0;
            $status = $row['status'] ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        return [
            'total_value' => $totalValue,
            'average_value' => count($data) > 0 ? $totalValue / count($data) : 0,
            'by_status' => $statusCounts,
        ];
    }

    /**
     * Calculate invoice-specific metrics
     */
    private function calculateInvoiceMetrics(array $data): array
    {
        $totalBilled = 0;
        $totalPaid = 0;
        $totalOutstanding = 0;
        $overdue = 0;

        foreach ($data as $row) {
            $totalBilled += $row['total'] ?? 0;
            $totalPaid += $row['amount_paid'] ?? 0;
            $totalOutstanding += $row['balance'] ?? 0;

            $dueDate = $row['due_date'] ?? null;
            $balance = $row['balance'] ?? 0;
            if ($dueDate && $balance > 0 && strtotime($dueDate) < time()) {
                $overdue++;
            }
        }

        return [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
            'overdue_count' => $overdue,
        ];
    }

    /**
     * Calculate product-specific metrics
     */
    private function calculateProductMetrics(array $data): array
    {
        $totalValue = 0;
        $totalOwned = 0;
        $totalAvailable = 0;
        $typeCounts = [];

        foreach ($data as $row) {
            $totalValue += ($row['replacement_charge'] ?? 0) * ($row['quantity_owned'] ?? 1);
            $totalOwned += $row['quantity_owned'] ?? 0;
            $totalAvailable += $row['quantity_available'] ?? 0;

            $type = $row['type'] ?? 'Unknown';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
        }

        return [
            'total_value' => $totalValue,
            'total_owned' => $totalOwned,
            'total_available' => $totalAvailable,
            'utilization_rate' => $totalOwned > 0 ? (($totalOwned - $totalAvailable) / $totalOwned) * 100 : 0,
            'by_type' => $typeCounts,
        ];
    }

    /**
     * Calculate member-specific metrics
     */
    private function calculateMemberMetrics(array $data): array
    {
        $typeCounts = [];
        $totalBalance = 0;

        foreach ($data as $row) {
            $type = $row['type'] ?? 'Unknown';
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            $totalBalance += $row['balance'] ?? 0;
        }

        return [
            'by_type' => $typeCounts,
            'total_balance' => $totalBalance,
        ];
    }

    /**
     * Calculate generic metrics for any module
     */
    private function calculateGenericMetrics(array $data): array
    {
        return [
            'record_count' => count($data),
        ];
    }

    /**
     * Extract group key from row
     */
    private function extractGroupKey(array $row, string $field): string
    {
        $value = $row[$field] ?? null;

        if ($value === null || $value === '') {
            return 'Unknown';
        }

        // Check if it's a date field
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return date('Y-m', strtotime($value));
        }

        return (string) $value;
    }

    /**
     * Format date for interval grouping
     */
    private function formatDateForInterval(string $date, string $interval): string
    {
        $timestamp = strtotime($date);

        switch ($interval) {
            case 'hour':
                return date('Y-m-d H:00', $timestamp);
            case 'day':
                return date('Y-m-d', $timestamp);
            case 'week':
                return date('Y-W', $timestamp);
            case 'month':
                return date('Y-m', $timestamp);
            case 'quarter':
                $month = (int) date('m', $timestamp);
                $quarter = ceil($month / 3);
                return date('Y', $timestamp) . '-Q' . $quarter;
            case 'year':
                return date('Y', $timestamp);
            default:
                return date('Y-m-d', $timestamp);
        }
    }

    /**
     * Aggregate values
     */
    private function aggregate(array $values, string $function)
    {
        if (empty($values)) {
            return 0;
        }

        switch ($function) {
            case 'sum':
                return array_sum($values);
            case 'avg':
                return array_sum($values) / count($values);
            case 'count':
                return count($values);
            case 'min':
                return min($values);
            case 'max':
                return max($values);
            default:
                return count($values);
        }
    }

    /**
     * Aggregate entire dataset
     */
    private function aggregateDataset(array $data, ?string $field, string $function)
    {
        if ($function === 'count') {
            return count($data);
        }

        $values = array_map(fn($row) => $row[$field] ?? 0, $data);
        return $this->aggregate($values, $function);
    }
}
