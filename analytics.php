<?php
/**
 * Analytics Dashboard
 */

require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireAuth();
Auth::requirePermission(Permissions::VIEW_ANALYTICS);

$pageTitle = 'Analytics';
$api = getApiClient();
$currencySymbol = config('app.currency_symbol') ?? '£';
$subdomain = config('currentrms.subdomain') ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo csrfToken(); ?>">
    <title><?php echo e($pageTitle); ?> - CurrentRMS Report Builder</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        (function() {
            var theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        /* Prevent horizontal overflow */
        html, body {
            overflow-x: hidden;
            max-width: 100vw;
        }
        .content {
            overflow-x: hidden;
        }
        .card-body {
            overflow: hidden;
        }

        /* Date Range Controls */
        .date-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 16px;
            background: var(--card-bg, #fff);
            border-radius: 8px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }
        .date-quick-btns {
            display: flex;
            gap: 6px;
        }
        .date-quick-btns .btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        .date-quick-btns .btn.active {
            background: var(--primary);
            color: #fff;
        }
        .date-pickers {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .date-pickers input[type="date"] {
            padding: 6px 8px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 12px;
            background: var(--input-bg, #fff);
            color: var(--text-color);
            max-width: 140px;
        }
        .date-pickers span {
            color: var(--gray-500);
            font-size: 12px;
        }
        @media (max-width: 600px) {
            .date-controls {
                padding: 10px 12px;
            }
            .date-quick-btns .btn {
                padding: 6px 10px;
                font-size: 11px;
            }
            .date-pickers {
                width: 100%;
                justify-content: center;
            }
            .date-pickers input[type="date"] {
                flex: 1;
                max-width: none;
            }
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary);
        }
        .section-header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }

        /* Two Column Layout */
        .two-col-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }
        @media (max-width: 768px) {
            .two-col-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        /* Table Wrapper for horizontal scroll */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Category Table */
        .category-table {
            width: 100%;
            min-width: 280px;
            border-collapse: collapse;
            font-size: 13px;
        }
        .category-table th,
        .category-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }
        .category-table th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .category-table td:first-child {
            white-space: normal;
            word-break: break-word;
            max-width: 150px;
        }
        .category-table tr:hover td {
            background: var(--gray-50);
        }
        .category-table .total-row {
            font-weight: 600;
            background: var(--primary-50, #eef2ff);
        }
        .category-table .total-row td {
            border-top: 2px solid var(--primary);
        }

        /* Product Cards */
        .product-list {
            display: block;
            max-height: 350px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 10px;
            border-bottom: 1px solid var(--gray-200);
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-item:hover {
            background: var(--gray-50);
        }
        .product-rank {
            width: 22px;
            height: 22px;
            background: var(--gray-200);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .product-rank.top-3 {
            background: var(--primary);
            color: #fff;
        }
        .product-info {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .product-name {
            font-weight: 500;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }
        .product-value {
            font-weight: 600;
            font-size: 12px;
            color: var(--primary);
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Owner Accordion */
        .owner-accordion {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
        }
        .owner-item {
            border-bottom: 1px solid var(--gray-200);
        }
        .owner-item:last-child {
            border-bottom: none;
        }
        .owner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            cursor: pointer;
            background: var(--gray-50);
            transition: background 0.2s;
            gap: 12px;
        }
        .owner-header:hover {
            background: var(--gray-100);
        }
        .owner-header.expanded {
            background: var(--primary-50, #eef2ff);
            border-bottom: 1px solid var(--gray-200);
        }
        .owner-name {
            font-weight: 600;
            font-size: 13px;
            flex-shrink: 0;
        }
        .owner-stats {
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 11px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .owner-stats .stat {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .owner-stats .stat-label {
            color: var(--gray-500);
            font-size: 9px;
            text-transform: uppercase;
        }
        .owner-stats .stat-value {
            font-weight: 600;
            font-size: 12px;
        }
        .owner-stats .stat-value.danger {
            color: var(--danger);
        }
        .owner-toggle {
            margin-left: 8px;
            transition: transform 0.2s;
            flex-shrink: 0;
        }
        .owner-header.expanded .owner-toggle {
            transform: rotate(180deg);
        }
        .owner-content {
            display: none;
            background: var(--card-bg);
            max-height: 400px;
            overflow-y: auto;
        }
        .owner-content.expanded {
            display: block;
        }
        /* Card-based item layout */
        .discount-item {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--gray-100);
            align-items: flex-start;
        }
        .discount-item:last-child {
            border-bottom: none;
        }
        .discount-item:hover {
            background: var(--gray-50);
        }
        .discount-item-main {
            flex: 1;
            min-width: 150px;
        }
        .discount-item-opp {
            font-weight: 500;
            font-size: 12px;
            color: var(--primary);
            text-decoration: none;
            display: block;
            margin-bottom: 2px;
        }
        .discount-item-opp:hover {
            text-decoration: underline;
        }
        .discount-item-product {
            font-size: 11px;
            color: var(--text-color);
            margin-bottom: 2px;
        }
        .discount-item-meta {
            font-size: 10px;
            color: var(--gray-500);
        }
        .discount-item-values {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .discount-val {
            text-align: right;
            min-width: 60px;
        }
        .discount-val-label {
            font-size: 9px;
            color: var(--gray-500);
            text-transform: uppercase;
        }
        .discount-val-amount {
            font-size: 12px;
            font-weight: 600;
        }
        .discount-val-amount.highlight {
            color: var(--primary);
        }
        .discount-val-amount.danger {
            color: var(--danger);
        }
        @media (max-width: 600px) {
            .owner-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .owner-stats {
                width: 100%;
                justify-content: space-between;
            }
            .owner-toggle {
                position: absolute;
                right: 12px;
                top: 12px;
            }
            .owner-header.expanded .owner-toggle {
                transform: rotate(180deg);
            }
            .owner-item {
                position: relative;
            }
            .discount-item {
                flex-direction: column;
            }
            .discount-item-values {
                width: 100%;
                justify-content: space-between;
            }
        }

        /* Under-Rate Date Controls */
        .under-rate-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding: 10px 12px;
            background: var(--gray-50);
            border-radius: 6px;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .control-group label {
            font-size: 11px;
            color: var(--gray-600);
            white-space: nowrap;
        }
        .control-group input,
        .control-group select {
            padding: 5px 8px;
            border: 1px solid var(--gray-300);
            border-radius: 4px;
            font-size: 11px;
            background: var(--input-bg, #fff);
            color: var(--text-color);
        }
        @media (max-width: 500px) {
            .control-group {
                flex: 1 1 45%;
            }
            .control-group:last-of-type {
                flex: 1 1 100%;
            }
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 400px) {
            .summary-cards {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
        }
        .summary-card {
            background: var(--card-bg);
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .summary-card .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-500);
            margin-bottom: 4px;
        }
        .summary-card .value {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-color);
        }
        .summary-card .value.danger {
            color: var(--danger);
        }
        @media (max-width: 500px) {
            .summary-card {
                padding: 10px 8px;
            }
            .summary-card .value {
                font-size: 16px;
            }
        }

        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-success {
            background: #d1fae5;
            color: #047857;
        }
        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        .badge-primary {
            background: var(--primary-100, #e0e7ff);
            color: var(--primary);
        }

        /* Text utilities */
        .text-danger { color: var(--danger) !important; }
        .text-warning { color: #d97706 !important; }
        .text-muted { color: var(--gray-500) !important; }
        .text-right { text-align: right !important; }

        /* Dark mode adjustments */
        [data-theme="dark"] .date-controls,
        [data-theme="dark"] .summary-card {
            background: var(--gray-800);
            border-color: var(--gray-700);
        }
        [data-theme="dark"] .date-pickers input[type="date"],
        [data-theme="dark"] .control-group input,
        [data-theme="dark"] .control-group select {
            background: var(--gray-700);
            border-color: var(--gray-600);
            color: var(--text-color);
        }
        [data-theme="dark"] .category-table th {
            background: var(--gray-700);
        }
        [data-theme="dark"] .category-table .total-row {
            background: rgba(102, 126, 234, 0.2);
        }
        [data-theme="dark"] .owner-header {
            background: var(--gray-700);
        }
        [data-theme="dark"] .owner-header:hover {
            background: var(--gray-600);
        }
        [data-theme="dark"] .owner-header.expanded {
            background: rgba(102, 126, 234, 0.2);
        }
        [data-theme="dark"] .under-rate-controls {
            background: var(--gray-700);
        }
        [data-theme="dark"] .owner-accordion {
            border-color: var(--gray-700);
        }
        [data-theme="dark"] .owner-item {
            border-color: var(--gray-700);
        }
        [data-theme="dark"] .badge-warning {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }
        [data-theme="dark"] .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        [data-theme="dark"] .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        [data-theme="dark"] .product-rank {
            background: var(--gray-600);
        }
        [data-theme="dark"] .product-rank.top-3 {
            background: var(--primary);
        }

        /* Loading spinner */
        .loading-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            color: var(--gray-500);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-overlay-text">Loading Analytics</div>
        <div class="loading-overlay-subtext">Please wait...</div>
    </div>

    <div class="app-layout">
        <?php include 'includes/partials/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/partials/header.php'; ?>

            <div class="content">
                <?php if (!$api): ?>
                    <div class="alert alert-warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div>
                            <strong>API Not Configured</strong>
                            <p>Please configure your CurrentRMS API credentials in <a href="settings.php">Settings</a> to view analytics.</p>
                        </div>
                    </div>
                <?php else: ?>

                <!-- Global Date Range Controls -->
                <div class="date-controls">
                    <div class="date-quick-btns">
                        <button class="btn btn-secondary" data-days="30">30 Days</button>
                        <button class="btn btn-secondary active" data-days="90">90 Days</button>
                        <button class="btn btn-secondary" data-days="365">Year</button>
                    </div>
                    <div class="date-pickers">
                        <input type="date" id="date-from" title="From date">
                        <span>to</span>
                        <input type="date" id="date-to" title="To date">
                    </div>
                    <button class="btn btn-primary btn-sm" id="apply-dates">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6M1 20v-6h6"/>
                            <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
                        </svg>
                        Apply
                    </button>
                </div>

                <!-- PROJECTS SECTION -->
                <div class="section-header">
                    <h2>Projects</h2>
                </div>
                <div class="two-col-grid">
                    <!-- Project Charges by Category -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Project Charges by Category</h3>
                        </div>
                        <div class="card-body">
                            <div id="project-charges-table" class="loading-placeholder">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Project Forecast -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Project Forecast</h3>
                        </div>
                        <div class="card-body">
                            <div id="project-forecast-table" class="loading-placeholder">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRODUCTS SECTION -->
                <div class="section-header">
                    <h2>Products</h2>
                </div>
                <div class="two-col-grid">
                    <!-- Top 20 Products by Charges -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top 20 Products by Charges</h3>
                        </div>
                        <div class="card-body">
                            <div id="top-products-charges" class="product-list loading-placeholder">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Top 20 Products by Utilisation -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Top 20 Products by Utilisation</h3>
                        </div>
                        <div class="card-body">
                            <div id="top-products-utilisation" class="product-list loading-placeholder">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- UNDER-RATE QUOTES SECTION -->
                <div class="section-header">
                    <h2>Under-Rate Quotes Analysis</h2>
                </div>
                <div class="card">
                    <div class="card-body">
                        <!-- Dedicated Date Range for Under-Rate -->
                        <div class="under-rate-controls">
                            <div class="control-group">
                                <label>Past Days:</label>
                                <input type="number" id="under-rate-past" value="90" min="0" max="365" style="width: 70px;">
                            </div>
                            <div class="control-group">
                                <label>Future Days:</label>
                                <input type="number" id="under-rate-future" value="365" min="0" max="730" style="width: 70px;">
                            </div>
                            <div class="control-group">
                                <label>Min Discount:</label>
                                <select id="under-rate-min-discount">
                                    <option value="5">5%</option>
                                    <option value="10" selected>10%</option>
                                    <option value="15">15%</option>
                                    <option value="20">20%</option>
                                    <option value="25">25%</option>
                                </select>
                            </div>
                            <button class="btn btn-sm btn-primary" onclick="loadUnderRateQuotes()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 4v6h-6M1 20v-6h6"/>
                                </svg>
                                Refresh
                            </button>
                        </div>

                        <!-- Summary Cards -->
                        <div class="summary-cards">
                            <div class="summary-card">
                                <div class="label">Total Items</div>
                                <div class="value" id="under-rate-total-items">-</div>
                            </div>
                            <div class="summary-card">
                                <div class="label">Potential Lost Revenue</div>
                                <div class="value danger" id="under-rate-lost-revenue">-</div>
                            </div>
                            <div class="summary-card">
                                <div class="label">Avg Discount</div>
                                <div class="value" id="under-rate-avg-discount">-</div>
                            </div>
                            <div class="summary-card">
                                <div class="label">Owners Involved</div>
                                <div class="value" id="under-rate-owners">-</div>
                            </div>
                        </div>

                        <!-- Collapsible Owners List -->
                        <div id="owners-accordion" class="owner-accordion">
                            <div class="loading-placeholder">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        const currencySymbol = '<?php echo e($currencySymbol); ?>';
        const subdomain = '<?php echo e($subdomain); ?>';

        // Date state
        let currentDateRange = {
            days: 90,
            from: null,
            to: null
        };

        // Initialize dates
        function initDates() {
            const today = new Date();
            const fromDate = new Date();
            fromDate.setDate(today.getDate() - 90);

            document.getElementById('date-from').value = fromDate.toISOString().split('T')[0];
            document.getElementById('date-to').value = today.toISOString().split('T')[0];

            currentDateRange.from = fromDate.toISOString().split('T')[0];
            currentDateRange.to = today.toISOString().split('T')[0];
        }

        // Quick date buttons
        document.querySelectorAll('.date-quick-btns .btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.date-quick-btns .btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const days = parseInt(this.dataset.days);
                currentDateRange.days = days;

                const today = new Date();
                const fromDate = new Date();
                fromDate.setDate(today.getDate() - days);

                document.getElementById('date-from').value = fromDate.toISOString().split('T')[0];
                document.getElementById('date-to').value = today.toISOString().split('T')[0];

                currentDateRange.from = fromDate.toISOString().split('T')[0];
                currentDateRange.to = today.toISOString().split('T')[0];

                loadAllData();
            });
        });

        // Apply custom dates
        document.getElementById('apply-dates')?.addEventListener('click', function() {
            document.querySelectorAll('.date-quick-btns .btn').forEach(b => b.classList.remove('active'));

            currentDateRange.from = document.getElementById('date-from').value;
            currentDateRange.to = document.getElementById('date-to').value;
            currentDateRange.days = null;

            loadAllData();
        });

        function showLoading(show = true) {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.classList.toggle('hidden', !show);
            }
        }

        // Load all data
        async function loadAllData() {
            showLoading(true);
            try {
                await Promise.all([
                    loadProjectCharges(),
                    loadProjectForecast(),
                    loadTopProductsByCharges(),
                    loadTopProductsByUtilisation(),
                    loadUnderRateQuotes()
                ]);
            } catch (error) {
                console.error('Error loading data:', error);
            } finally {
                showLoading(false);
            }
        }

        // Load Project Charges by Category
        async function loadProjectCharges() {
            const container = document.getElementById('project-charges-table');
            container.innerHTML = '<div class="loading-placeholder"><div class="spinner"></div></div>';

            try {
                const params = new URLSearchParams({
                    from: currentDateRange.from,
                    to: currentDateRange.to
                });
                const response = await fetch(`api/analytics/project-charges.php?${params}`);
                const data = await response.json();

                if (!data.success) throw new Error(data.error || 'Failed to load');

                renderProjectCharges(data.data);
            } catch (error) {
                container.innerHTML = `<p class="text-muted text-center">Error: ${escapeHtml(error.message)}</p>`;
            }
        }

        function renderProjectCharges(data) {
            const container = document.getElementById('project-charges-table');

            if (!data.categories || data.categories.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No project data available</p>';
                return;
            }

            let html = `
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Projects</th>
                            <th style="text-align: right;">Charges</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.categories.forEach(cat => {
                html += `
                    <tr>
                        <td>${escapeHtml(cat.name)}</td>
                        <td style="text-align: right;">${cat.count}</td>
                        <td style="text-align: right;">${currencySymbol}${parseFloat(cat.charges).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });

            html += `
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td style="text-align: right;"><strong>${data.total_projects}</strong></td>
                        <td style="text-align: right;"><strong>${currencySymbol}${parseFloat(data.total_charges).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong></td>
                    </tr>
                </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        // Load Project Forecast
        async function loadProjectForecast() {
            const container = document.getElementById('project-forecast-table');
            container.innerHTML = '<div class="loading-placeholder"><div class="spinner"></div></div>';

            try {
                const params = new URLSearchParams({
                    from: currentDateRange.from,
                    to: currentDateRange.to
                });
                const response = await fetch(`api/analytics/project-forecast.php?${params}`);
                const data = await response.json();

                if (!data.success) throw new Error(data.error || 'Failed to load');

                renderProjectForecast(data.data);
            } catch (error) {
                container.innerHTML = `<p class="text-muted text-center">Error: ${escapeHtml(error.message)}</p>`;
            }
        }

        function renderProjectForecast(data) {
            const container = document.getElementById('project-forecast-table');

            if (!data.categories || data.categories.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No forecast data available</p>';
                return;
            }

            let html = `
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th style="text-align: right;">Projects</th>
                            <th style="text-align: right;">Forecast</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.categories.forEach(cat => {
                html += `
                    <tr>
                        <td>${escapeHtml(cat.name)}</td>
                        <td style="text-align: right;">${cat.count}</td>
                        <td style="text-align: right;">${currencySymbol}${parseFloat(cat.charges).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });

            html += `
                    <tr class="total-row">
                        <td><strong>Total Forecast</strong></td>
                        <td style="text-align: right;"><strong>${data.total_projects}</strong></td>
                        <td style="text-align: right;"><strong>${currencySymbol}${parseFloat(data.total_charges).toLocaleString(undefined, {minimumFractionDigits: 2})}</strong></td>
                    </tr>
                </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        // Load Top Products by Charges
        async function loadTopProductsByCharges() {
            const container = document.getElementById('top-products-charges');
            container.innerHTML = '<div class="loading-placeholder"><div class="spinner"></div></div>';

            try {
                const params = new URLSearchParams({
                    from: currentDateRange.from,
                    to: currentDateRange.to,
                    limit: 20
                });
                const response = await fetch(`api/analytics/top-products-charges.php?${params}`);
                const data = await response.json();

                if (!data.success) throw new Error(data.error || 'Failed to load');

                renderProductList(container, data.data, 'charges');
            } catch (error) {
                container.innerHTML = `<p class="text-muted text-center">Error: ${escapeHtml(error.message)}</p>`;
            }
        }

        // Load Top Products by Utilisation
        async function loadTopProductsByUtilisation() {
            const container = document.getElementById('top-products-utilisation');
            container.innerHTML = '<div class="loading-placeholder"><div class="spinner"></div></div>';

            try {
                const params = new URLSearchParams({
                    from: currentDateRange.from,
                    to: currentDateRange.to,
                    limit: 20
                });
                const response = await fetch(`api/analytics/top-products-utilisation.php?${params}`);
                const data = await response.json();

                if (!data.success) throw new Error(data.error || 'Failed to load');

                renderProductList(container, data.data, 'utilisation');
            } catch (error) {
                container.innerHTML = `<p class="text-muted text-center">Error: ${escapeHtml(error.message)}</p>`;
            }
        }

        function renderProductList(container, products, type) {
            if (!products || products.length === 0) {
                container.innerHTML = '<p class="text-muted text-center">No product data available</p>';
                return;
            }

            let html = '';
            products.forEach((product, index) => {
                const rankClass = index < 3 ? 'top-3' : '';
                const value = type === 'charges'
                    ? `${currencySymbol}${parseFloat(product.charges).toLocaleString(undefined, {minimumFractionDigits: 2})}`
                    : `${product.count} times`;

                html += `
                    <div class="product-item">
                        <span class="product-rank ${rankClass}">${index + 1}</span>
                        <div class="product-info">
                            <div class="product-name" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</div>
                        </div>
                        <div class="product-value">${value}</div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Under-Rate Quotes
        async function loadUnderRateQuotes() {
            const pastDays = document.getElementById('under-rate-past')?.value || 90;
            const futureDays = document.getElementById('under-rate-future')?.value || 365;
            const minDiscount = document.getElementById('under-rate-min-discount')?.value || 10;

            // Show loading
            document.getElementById('under-rate-total-items').textContent = '-';
            document.getElementById('under-rate-lost-revenue').textContent = '-';
            document.getElementById('under-rate-avg-discount').textContent = '-';
            document.getElementById('under-rate-owners').textContent = '-';
            document.getElementById('owners-accordion').innerHTML = '<div class="loading-placeholder"><div class="spinner"></div></div>';

            try {
                const params = new URLSearchParams({
                    past_days: pastDays,
                    future_days: futureDays,
                    min_discount: minDiscount
                });
                const response = await fetch(`api/analytics/under-rate-quotes.php?${params}`);
                const result = await response.json();

                if (!result.success) throw new Error(result.error || 'Failed to load');

                const data = result.data;
                const totals = data.totals;

                // Update summary
                document.getElementById('under-rate-total-items').textContent = totals.total_items.toLocaleString();
                document.getElementById('under-rate-lost-revenue').textContent = currencySymbol + totals.total_lost_revenue.toLocaleString(undefined, {minimumFractionDigits: 2});
                document.getElementById('under-rate-avg-discount').textContent = totals.avg_discount + '%';
                document.getElementById('under-rate-owners').textContent = totals.unique_owners.toLocaleString();

                // Render owners accordion
                renderOwnersAccordion(data.owner_summary, data.items);

            } catch (error) {
                console.error('Under-rate quotes error:', error);
                document.getElementById('owners-accordion').innerHTML = `<p class="text-muted text-center" style="padding: 20px;">Error: ${escapeHtml(error.message)}</p>`;
            }
        }

        function renderOwnersAccordion(owners, allItems) {
            const container = document.getElementById('owners-accordion');

            if (!owners || owners.length === 0) {
                container.innerHTML = '<p class="text-muted text-center" style="padding: 20px;">No under-rate quotes found</p>';
                return;
            }

            // Group items by owner
            const itemsByOwner = {};
            allItems.forEach(item => {
                const ownerName = item.owner || 'Unknown';
                if (!itemsByOwner[ownerName]) {
                    itemsByOwner[ownerName] = [];
                }
                itemsByOwner[ownerName].push(item);
            });

            let html = '';
            owners.forEach((owner, index) => {
                const ownerItems = itemsByOwner[owner.owner] || [];
                const ownerId = `owner-${index}`;

                html += `
                    <div class="owner-item">
                        <div class="owner-header" onclick="toggleOwner('${ownerId}')">
                            <span class="owner-name">${escapeHtml(owner.owner)}</span>
                            <div class="owner-stats">
                                <div class="stat">
                                    <span class="stat-label">Items</span>
                                    <span class="stat-value">${owner.total_items}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Lost Revenue</span>
                                    <span class="stat-value danger">${currencySymbol}${owner.total_lost_revenue.toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Avg Discount</span>
                                    <span class="stat-value">${owner.avg_discount}%</span>
                                </div>
                                <svg class="owner-toggle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="owner-content" id="${ownerId}">
                            ${renderOwnerItems(ownerItems)}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function renderOwnerItems(items) {
            if (items.length === 0) {
                return '<p class="text-muted text-center" style="padding: 16px;">No items</p>';
            }

            let html = '<div class="discount-items-list">';

            items.slice(0, 50).forEach(item => {
                const startsAt = item.starts_at ? new Date(item.starts_at).toLocaleDateString() : '-';
                const discountClass = item.discount_percent >= 50 ? 'danger' : (item.discount_percent >= 25 ? 'highlight' : '');

                html += `
                    <div class="discount-item">
                        <div class="discount-item-main">
                            <a href="https://${subdomain}.current-rms.com/opportunities/${item.opportunity_id}" target="_blank" class="discount-item-opp">
                                ${escapeHtml((item.opportunity_subject || '').substring(0, 35))}${(item.opportunity_subject || '').length > 35 ? '...' : ''}
                            </a>
                            <div class="discount-item-product">${escapeHtml((item.item_name || '').substring(0, 40))}${(item.item_name || '').length > 40 ? '...' : ''}</div>
                            <div class="discount-item-meta">${startsAt} &bull; ${getStatusBadge(item.opportunity_status)}</div>
                        </div>
                        <div class="discount-item-values">
                            <div class="discount-val">
                                <div class="discount-val-label">Discount</div>
                                <div class="discount-val-amount ${discountClass}">${item.discount_percent}%</div>
                            </div>
                            <div class="discount-val">
                                <div class="discount-val-label">Lost</div>
                                <div class="discount-val-amount danger">${currencySymbol}${(item.lost_revenue || 0).toFixed(0)}</div>
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';

            if (items.length > 50) {
                html += `<p class="text-muted text-center" style="font-size: 11px; padding: 8px;">Showing 50 of ${items.length} items</p>`;
            }

            return html;
        }

        function toggleOwner(ownerId) {
            const content = document.getElementById(ownerId);
            const header = content.previousElementSibling;

            content.classList.toggle('expanded');
            header.classList.toggle('expanded');
        }

        function getStatusBadge(status) {
            const statusLower = (status || '').toLowerCase();
            let badgeClass = 'badge-secondary';
            if (statusLower.includes('confirmed') || statusLower.includes('order')) {
                badgeClass = 'badge-success';
            } else if (statusLower.includes('quote') || statusLower.includes('provisional')) {
                badgeClass = 'badge-warning';
            } else if (statusLower.includes('closed') || statusLower.includes('cancelled')) {
                badgeClass = 'badge-danger';
            }
            return `<span class="badge ${badgeClass}">${escapeHtml(status || 'Unknown')}</span>`;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initDates();
            loadAllData();
        });

        // Listen for control changes
        document.getElementById('under-rate-past')?.addEventListener('change', loadUnderRateQuotes);
        document.getElementById('under-rate-future')?.addEventListener('change', loadUnderRateQuotes);
        document.getElementById('under-rate-min-discount')?.addEventListener('change', loadUnderRateQuotes);
    </script>
</body>
</html>
