/**
 * Dashboard Module
 */

const dashboardModule = {
    init() {
        this.currentRange = 'daily';
        this.salesChart = null;
        this.productsChart = null;
        this.bindEvents();
        this.fetchData();
        console.log('Dashboard Module Loaded');
    },

    bindEvents() {
        const rangeButtons = document.querySelectorAll('.btn-group [class*="btn-outline-secondary"]');
        rangeButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const range = e.target.textContent.toLowerCase();
                if (range === this.currentRange) return;

                // Update UI active state
                rangeButtons.forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');

                this.currentRange = range;
                this.fetchData(range);
            });
        });
    },

    async fetchData(range = 'daily') {
        const data = await App.api(`dashboard.php?filter=${range}`);
        if (!data) return;

        this.updateKPIs(data.kpis);
        this.renderCharts(data.chart, data.top_products);
        this.renderTransactions(data.recent_sales);
        this.renderStockAlerts(data.stock_alerts);
    },

    updateKPIs(kpis) {
        document.getElementById('kpi-revenue').textContent = App.formatCurrency(kpis.revenue);
        document.getElementById('kpi-sales').textContent = kpis.sales;
        document.getElementById('kpi-low-stock').textContent = kpis.low_stock;
        document.getElementById('kpi-customers').textContent = kpis.customers;
        document.getElementById('kpi-pending-po').textContent = kpis.pending_po;
        document.getElementById('kpi-profit').textContent = `${kpis.profit}%`;
    },

    renderCharts(salesData, productsData) {
        // Line Chart
        const ctxSales = document.getElementById('salesChart').getContext('2d');

        // Destroy existing chart if it exists
        if (this.salesChart) {
            this.salesChart.destroy();
        }

        this.salesChart = new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: salesData.map(d => d.day),
                datasets: [{
                    label: 'Revenue',
                    data: salesData.map(d => d.total),
                    borderColor: '#00BFA6',
                    backgroundColor: 'rgba(0, 191, 166, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#00BFA6',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [5, 5] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // Bar Chart
        const ctxProducts = document.getElementById('productsChart').getContext('2d');

        // Destroy existing chart if it exists
        if (this.productsChart) {
            this.productsChart.destroy();
        }

        this.productsChart = new Chart(ctxProducts, {
            type: 'bar',
            data: {
                labels: productsData.map(p => p.name.length > 15 ? p.name.substring(0, 12) + '...' : p.name),
                datasets: [{
                    label: 'Qty Sold',
                    data: productsData.map(p => p.total_sold),
                    backgroundColor: '#0A1628',
                    borderRadius: 8,
                    barThickness: 20
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true },
                    y: { grid: { display: false } }
                }
            }
        });
    },

    renderTransactions(transactions) {
        const tableBody = document.getElementById('recentTransactionsTable');
        if (transactions.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No recent transactions</td></tr>';
            return;
        }

        tableBody.innerHTML = transactions.map(t => `
            <tr>
                <td><strong>#${t.id}</strong></td>
                <td>${t.customer_name || 'Walking Customer'}</td>
                <td>${new Date(t.date).toLocaleDateString()}</td>
                <td class="fw-bold">${App.formatCurrency(t.total)}</td>
                <td>
                    <span class="badge ${this.getStatusBadgeClass(t.status)}">
                        ${t.status}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-link text-teal p-0"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-link text-dark p-0 ms-2"><i class="fas fa-print"></i></button>
                </td>
            </tr>
        `).join('');
    },

    renderStockAlerts(alerts) {
        const list = document.getElementById('stockAlertsList');
        if (alerts.length === 0) {
            list.innerHTML = '<div class="text-center py-4 text-muted">Inventory is healthy</div>';
            return;
        }

        list.innerHTML = alerts.map(a => `
            <div class="d-flex align-items-center mb-3 p-2 bg-light rounded-3 border-start border-danger border-4">
                <div class="ms-3 flex-grow-1">
                    <h6 class="mb-0 fw-bold small text-truncate" style="max-width: 180px;">${a.name}</h6>
                    <small class="text-muted">In stock: ${a.stock_qty} / Min: ${a.min_stock}</small>
                </div>
                <div class="text-danger fw-bold">
                    <i class="fas fa-arrow-down-short-wide"></i>
                </div>
            </div>
        `).join('');
    },

    getStatusBadgeClass(status) {
        switch (status) {
            case 'Completed': return 'bg-success-subtle text-success';
            case 'Hold': return 'bg-warning-subtle text-warning';
            case 'Cancelled': return 'bg-danger-subtle text-danger';
            default: return 'bg-secondary-subtle text-secondary';
        }
    }
};

// Initialize if view is already loaded or via App.initModule
if (document.getElementById('salesChart')) {
    dashboardModule.init();
}

window.dashboardModule = dashboardModule;
