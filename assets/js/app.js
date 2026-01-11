/**
 * CurrentRMS Report Builder - Main JavaScript Application
 */

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    App.init();
});

// Main Application Object
const App = {
    // Configuration
    config: {
        apiBase: 'api/',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
    },

    // Initialize application
    init() {
        this.initSidebar();
        this.initDropdowns();
        this.initModals();
        this.initTabs();
        this.initForms();
        this.initTooltips();
        this.initCharts();
    },

    // Sidebar toggle for mobile
    initSidebar() {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                document.body.classList.toggle('sidebar-open');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', (e) => {
                if (sidebar.classList.contains('open') &&
                    !sidebar.contains(e.target) &&
                    !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                    document.body.classList.remove('sidebar-open');
                }
            });
        }
    },

    // Dropdown menus
    initDropdowns() {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = toggle.closest('.dropdown');
                const wasOpen = dropdown.classList.contains('active');

                // Close all dropdowns
                document.querySelectorAll('.dropdown.active').forEach(d => {
                    d.classList.remove('active');
                });

                if (!wasOpen) {
                    dropdown.classList.add('active');
                }
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown.active').forEach(d => {
                d.classList.remove('active');
            });
        });
    },

    // Modal dialogs
    initModals() {
        // Open modal
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const modalId = trigger.dataset.modal;
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            });
        });

        // Close modal
        document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
            el.addEventListener('click', (e) => {
                if (e.target === el) {
                    const modal = el.closest('.modal-overlay');
                    if (modal) {
                        modal.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                }
            });
        });

        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });
    },

    // Tab navigation
    initTabs() {
        document.querySelectorAll('.tabs').forEach(tabGroup => {
            const tabs = tabGroup.querySelectorAll('.tab');
            const panes = document.querySelectorAll(`[data-tab-group="${tabGroup.dataset.tabGroup}"]`);

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    panes.forEach(pane => {
                        pane.classList.toggle('hidden', pane.dataset.tab !== tab.dataset.tab);
                    });
                });
            });
        });
    },

    // Form enhancements
    initForms() {
        // Auto-submit on select change
        document.querySelectorAll('select[data-auto-submit]').forEach(select => {
            select.addEventListener('change', () => {
                select.closest('form').submit();
            });
        });

        // Confirm before destructive actions
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', (e) => {
                if (!confirm(el.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });

        // AJAX form submission
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.submitForm(form);
            });
        });
    },

    // Initialize tooltips
    initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = el.dataset.tooltip;
                document.body.appendChild(tooltip);

                const rect = el.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

                el._tooltip = tooltip;
            });

            el.addEventListener('mouseleave', () => {
                if (el._tooltip) {
                    el._tooltip.remove();
                    el._tooltip = null;
                }
            });
        });
    },

    // Initialize charts (placeholder - use Chart.js)
    initCharts() {
        if (typeof Chart === 'undefined') return;

        document.querySelectorAll('[data-chart]').forEach(canvas => {
            const config = JSON.parse(canvas.dataset.chart);
            new Chart(canvas, config);
        });
    },

    // AJAX form submission
    async submitForm(form) {
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn?.innerHTML;

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
            }

            const formData = new FormData(form);
            formData.append('_token', this.config.csrfToken);

            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (data.success) {
                this.showNotification(data.message || 'Success!', 'success');
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            } else {
                this.showNotification(data.message || 'An error occurred', 'error');
            }
        } catch (error) {
            this.showNotification('Request failed. Please try again.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }
    },

    // Show notification
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;

        document.body.appendChild(notification);

        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });

        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    },

    // API request helper
    async api(endpoint, options = {}) {
        const url = this.config.apiBase + endpoint;

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.config.csrfToken,
            },
        };

        const response = await fetch(url, { ...defaultOptions, ...options });
        return response.json();
    },
};

// Report Builder Module
const ReportBuilder = {
    currentModule: null,
    selectedColumns: [],
    filters: [],
    sorting: null,

    init() {
        this.bindEvents();
    },

    bindEvents() {
        // Module selection
        document.querySelectorAll('.module-item').forEach(item => {
            item.addEventListener('click', () => this.selectModule(item.dataset.module));
        });

        // Column selection
        document.querySelectorAll('.column-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateColumns());
        });

        // Filter addition
        document.getElementById('add-filter')?.addEventListener('click', () => this.addFilter());

        // Preview update
        document.getElementById('preview-report')?.addEventListener('click', () => this.preview());

        // Export buttons
        document.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', () => this.export(btn.dataset.export));
        });
    },

    selectModule(module) {
        this.currentModule = module;

        // Update UI
        document.querySelectorAll('.module-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.module === module);
        });

        // Load module columns
        this.loadColumns(module);
    },

    async loadColumns(module) {
        const container = document.getElementById('column-list');
        if (!container) return;

        container.innerHTML = '<div class="spinner"></div>';

        try {
            const response = await fetch(`api/modules.php?module=${module}&action=columns`);
            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            container.innerHTML = data.columns.map(col => `
                <label class="checkbox-item">
                    <input type="checkbox" class="column-checkbox" value="${col.key}" checked>
                    <span>${col.label}</span>
                </label>
            `).join('');

            this.selectedColumns = data.columns.map(c => c.key);
        } catch (error) {
            container.innerHTML = '<p class="text-danger">Failed to load columns: ' + error.message + '</p>';
        }
    },

    updateColumns() {
        this.selectedColumns = Array.from(document.querySelectorAll('.column-checkbox:checked'))
            .map(cb => cb.value);
    },

    addFilter() {
        const filterContainer = document.getElementById('filter-list');
        if (!filterContainer) return;

        const filterId = Date.now();
        const filterHtml = `
            <div class="filter-row" data-filter-id="${filterId}">
                <select class="form-control filter-field" style="width: 150px;">
                    <option value="">Select field...</option>
                </select>
                <select class="form-control filter-predicate" style="width: 120px;">
                    <option value="eq">Equals</option>
                    <option value="cont">Contains</option>
                    <option value="gt">Greater than</option>
                    <option value="lt">Less than</option>
                    <option value="gteq">Greater or equal</option>
                    <option value="lteq">Less or equal</option>
                </select>
                <input type="text" class="form-control filter-value" placeholder="Value">
                <button type="button" class="btn btn-icon btn-danger remove-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

        filterContainer.insertAdjacentHTML('beforeend', filterHtml);

        // Bind remove
        filterContainer.querySelector(`[data-filter-id="${filterId}"] .remove-filter`)
            .addEventListener('click', (e) => {
                e.target.closest('.filter-row').remove();
            });
    },

    getFilters() {
        return Array.from(document.querySelectorAll('.filter-row')).map(row => ({
            field: row.querySelector('.filter-field').value,
            predicate: row.querySelector('.filter-predicate').value,
            value: row.querySelector('.filter-value').value,
        })).filter(f => f.field && f.value);
    },

    async preview() {
        const previewContainer = document.getElementById('report-preview');
        if (!previewContainer || !this.currentModule) return;

        previewContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

        try {
            const response = await fetch('api/reports/preview.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    module: this.currentModule,
                    columns: this.selectedColumns,
                    filters: this.getFilters(),
                }),
            });

            const data = await response.json();

            if (data.error) {
                throw new Error(data.error);
            }

            this.renderPreview(data, previewContainer);
        } catch (error) {
            previewContainer.innerHTML = '<p class="text-danger">Failed to load preview: ' + error.message + '</p>';
        }
    },

    renderPreview(data, container) {
        if (!data.data || data.data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="empty-state-title">No Data Found</h3>
                    <p class="empty-state-desc">Try adjusting your filters or selecting a different module.</p>
                </div>
            `;
            return;
        }

        const columns = data.columns || Object.keys(data.data[0]);
        const columnConfig = data.column_config || {};

        let html = '<div class="table-container"><table class="table"><thead><tr>';
        columns.forEach(col => {
            html += `<th>${columnConfig[col]?.label || col}</th>`;
        });
        html += '</tr></thead><tbody>';

        data.data.forEach(row => {
            html += '<tr>';
            columns.forEach(col => {
                const value = row[col] ?? '';
                const type = columnConfig[col]?.type || 'string';
                html += `<td>${this.formatValue(value, type)}</td>`;
            });
            html += '</tr>';
        });

        html += '</tbody></table></div>';

        // Pagination
        if (data.meta) {
            html += `
                <div class="pagination">
                    <span class="text-muted">
                        Showing ${data.data.length} of ${data.meta.total} records
                    </span>
                </div>
            `;
        }

        container.innerHTML = html;
    },

    formatValue(value, type) {
        if (value === null || value === undefined) return '';

        switch (type) {
            case 'currency':
                return '$' + parseFloat(value).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            case 'number':
                return parseFloat(value).toLocaleString();
            case 'date':
                return new Date(value).toLocaleDateString();
            case 'datetime':
                return new Date(value).toLocaleString();
            case 'boolean':
                return value ? 'Yes' : 'No';
            default:
                return String(value).substring(0, 100);
        }
    },

    async export(format) {
        if (!this.currentModule) {
            App.showNotification('Please select a module first', 'warning');
            return;
        }

        const params = new URLSearchParams({
            module: this.currentModule,
            columns: this.selectedColumns.join(','),
            filters: JSON.stringify(this.getFilters()),
            format: format,
        });

        window.location.href = `api/reports/export?${params.toString()}`;
    },

    async save() {
        const name = prompt('Enter report name:');
        if (!name) return;

        try {
            const data = await App.api('reports/save', {
                method: 'POST',
                body: JSON.stringify({
                    name: name,
                    module: this.currentModule,
                    columns: this.selectedColumns,
                    filters: this.getFilters(),
                }),
            });

            if (data.success) {
                App.showNotification('Report saved successfully!', 'success');
            }
        } catch (error) {
            App.showNotification('Failed to save report', 'error');
        }
    },
};

// Dashboard Module
const Dashboard = {
    widgets: [],

    init() {
        this.loadWidgets();
        this.initDragDrop();
    },

    async loadWidgets() {
        const container = document.getElementById('dashboard-grid');
        if (!container) return;

        try {
            const data = await App.api('dashboard/widgets');
            this.widgets = data.widgets;
            this.renderWidgets(container);
        } catch (error) {
            console.error('Failed to load widgets:', error);
        }
    },

    renderWidgets(container) {
        container.innerHTML = this.widgets.map(widget => `
            <div class="widget" style="grid-column: span ${widget.grid_cols}; grid-row: span ${widget.grid_rows};"
                 data-widget-id="${widget.id}">
                <div class="widget-header">
                    <span class="widget-title">${widget.title}</span>
                    <div class="dropdown">
                        <button class="btn btn-icon dropdown-toggle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                        <div class="dropdown-menu">
                            <div class="dropdown-item" data-action="edit">Edit</div>
                            <div class="dropdown-item" data-action="refresh">Refresh</div>
                            <div class="dropdown-divider"></div>
                            <div class="dropdown-item text-danger" data-action="delete">Delete</div>
                        </div>
                    </div>
                </div>
                <div class="widget-body" id="widget-${widget.id}">
                    <div class="spinner"></div>
                </div>
            </div>
        `).join('');

        // Load widget data
        this.widgets.forEach(widget => this.loadWidgetData(widget));

        // Re-init dropdowns
        App.initDropdowns();
    },

    async loadWidgetData(widget) {
        const container = document.getElementById(`widget-${widget.id}`);
        if (!container) return;

        try {
            const data = await App.api(`dashboard/widget/${widget.id}/data`);
            this.renderWidgetContent(widget, data, container);
        } catch (error) {
            container.innerHTML = '<p class="text-danger text-center">Failed to load data</p>';
        }
    },

    renderWidgetContent(widget, data, container) {
        switch (widget.type) {
            case 'stat_card':
                this.renderStatCard(data, container, widget.config);
                break;
            case 'chart_bar':
            case 'chart_line':
            case 'chart_pie':
            case 'chart_doughnut':
                this.renderChart(widget.type, data, container);
                break;
            case 'timeline':
                this.renderTimeline(data, container);
                break;
            case 'table':
                this.renderTable(data, container);
                break;
            default:
                container.innerHTML = '<p class="text-muted">Unknown widget type</p>';
        }
    },

    renderStatCard(data, container, config) {
        const value = config.format === 'currency'
            ? '$' + parseFloat(data.value || 0).toLocaleString(undefined, {minimumFractionDigits: 2})
            : parseFloat(data.value || 0).toLocaleString();

        const changeClass = data.change >= 0 ? 'up' : 'down';
        const changeIcon = data.change >= 0 ? '↑' : '↓';

        container.innerHTML = `
            <div class="stat-value">${value}</div>
            ${data.change !== undefined ? `
                <div class="stat-change ${changeClass}">
                    ${changeIcon} ${Math.abs(data.change)}% from last period
                </div>
            ` : ''}
        `;
    },

    renderChart(type, data, container) {
        const chartType = type.replace('chart_', '');
        const canvas = document.createElement('canvas');
        container.innerHTML = '';
        container.appendChild(canvas);

        if (typeof Chart !== 'undefined') {
            new Chart(canvas, {
                type: chartType,
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: this.getChartColors(data.values.length),
                        borderColor: chartType === 'line' ? '#667eea' : undefined,
                        fill: chartType === 'line' ? false : undefined,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: chartType === 'pie' || chartType === 'doughnut',
                            position: 'bottom',
                        },
                    },
                },
            });
        } else {
            container.innerHTML = '<p class="text-muted">Charts require Chart.js library</p>';
        }
    },

    renderTimeline(data, container) {
        if (!data.events || data.events.length === 0) {
            container.innerHTML = '<p class="text-muted">No upcoming events</p>';
            return;
        }

        container.innerHTML = `
            <div class="timeline">
                ${data.events.map(event => `
                    <div class="timeline-item">
                        <div class="timeline-date">${new Date(event.date).toLocaleDateString()}</div>
                        <div class="timeline-title">${event.title}</div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    renderTable(data, container) {
        if (!data.data || data.data.length === 0) {
            container.innerHTML = '<p class="text-muted">No data available</p>';
            return;
        }

        const columns = data.columns || Object.keys(data.data[0]);

        container.innerHTML = `
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>${columns.map(col => `<th>${col}</th>`).join('')}</tr>
                    </thead>
                    <tbody>
                        ${data.data.map(row => `
                            <tr>${columns.map(col => `<td>${row[col] ?? ''}</td>`).join('')}</tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    },

    getChartColors(count) {
        const colors = [
            '#667eea', '#764ba2', '#10b981', '#f59e0b', '#ef4444',
            '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316',
        ];
        return colors.slice(0, count);
    },

    initDragDrop() {
        // Placeholder for drag-and-drop widget reordering
        // Would require a library like SortableJS for full implementation
    },
};

// Initialize modules when needed
if (document.getElementById('report-builder')) {
    ReportBuilder.init();
}

if (document.getElementById('dashboard-grid')) {
    Dashboard.init();
}
