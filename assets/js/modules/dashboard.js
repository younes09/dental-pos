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

        window.addEventListener('themeChanged', () => {
            if (this.currentData) {
                this.renderCharts(this.currentData.chart, this.currentData.top_products);
            }
        });
    },

    async fetchData(range = 'daily') {
        const data = await App.api(`dashboard.php?filter=${range}`);
        if (!data) return;

        this.currentData = data;
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
        document.getElementById('kpi-profit').textContent = App.formatCurrency(kpis.profit);

        this.updateGrowthIndicator('today-revenue-growth', kpis.revenue_growth, 'vs yesterday');
        this.updateGrowthIndicator('monthly-sales-growth', kpis.sales_growth, 'vs last month');
        this.updateGrowthIndicator('profit-growth', kpis.profit_growth);
    },

    updateGrowthIndicator(elementId, growth, suffix = '') {
        const element = document.getElementById(elementId);
        if (!element) return;

        const isPositive = growth >= 0;
        const absGrowth = Math.abs(growth).toFixed(1);
        const icon = isPositive ? 'fa-arrow-up' : 'fa-arrow-down';
        const colorClass = isPositive ? 'text-success' : 'text-danger';

        element.className = colorClass;
        element.innerHTML = `<i class="fas ${icon}"></i> ${absGrowth}% ${suffix}`;
    },

    renderCharts(salesData, productsData) {
        const isDark = document.body.classList.contains('dark-mode');
        const textColor = isDark ? '#e1e7ed' : '#6c757d';
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const barColor = isDark ? '#00BFA6' : '#00BFA6';

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
                        ticks: { color: textColor },
                        grid: { color: gridColor, borderDash: [5, 5] }
                    },
                    x: {
                        ticks: { color: textColor },
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
                    backgroundColor: barColor,
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
                    x: {
                        beginAtZero: true,
                        ticks: { color: textColor },
                        grid: { color: gridColor }
                    },
                    y: {
                        ticks: { color: textColor },
                        grid: { display: false }
                    }
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
                    <button class="btn btn-sm btn-link text-teal p-0" onclick="dashboardModule.viewDetails(${t.id})"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-sm btn-link text-dark p-0 ms-2" onclick="dashboardModule.directPrint(${t.id})"><i class="fas fa-print"></i></button>
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

    async directPrint(id) {
        const result = await App.api(`sales.php?action=sale_details&id=${id}`);
        if (result) this.printReceipt(result);
    },

    async viewDetails(id) {
        const result = await App.api(`sales.php?action=sale_details&id=${id}`);
        if (!result) return;

        const { sale, items } = result;

        // Populate Modal Header
        document.getElementById('sale-details-subtitle').textContent = `Invoice #INV-${sale.id} | ${new Date(sale.date).toLocaleString()}`;

        // Populate Customer Info
        document.getElementById('sale-details-customer-info').innerHTML = `
            <div class="fw-bold fs-5 text-teal mb-1">${sale.customer_name || 'Walk-in Customer'}</div>
            <div class="small mb-1"><i class="fas fa-phone me-2 text-muted"></i>${sale.customer_phone || 'No phone provided'}</div>
            <div class="small"><i class="fas fa-user-tag me-2 text-muted"></i>Payment: <strong>${sale.payment_method}</strong></div>
        `;

        // Populate Sale Metadata
        document.getElementById('sale-details-meta-info').innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <span>Cashier:</span>
                <span class="fw-bold">${sale.user_name}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Status:</span>
                <span class="badge bg-success px-2">Completed</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Date:</span>
                <span class="fw-bold text-nowrap">${new Date(sale.date).toLocaleDateString()}</span>
            </div>
        `;

        // Populate Item Table
        const tbody = document.querySelector('#sale-details-items-table tbody');
        tbody.innerHTML = items.map(item => `
            <tr>
                <td>
                    <div class="fw-medium">${item.product_name}</div>
                    <small class="text-muted small">${item.barcode || ''}</small>
                </td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">${App.formatCurrency(item.unit_price)}</td>
                <td class="text-end fw-bold">${App.formatCurrency(item.total)}</td>
            </tr>
        `).join('');

        // Populate Totals
        document.getElementById('sale-details-subtotal').textContent = App.formatCurrency(sale.subtotal);
        document.getElementById('sale-details-discount').textContent = `-${App.formatCurrency(sale.discount)}`;
        document.getElementById('sale-details-tax').textContent = App.formatCurrency(sale.tax);
        document.getElementById('sale-details-total').textContent = App.formatCurrency(sale.total);

        // Update VAT Percentage label
        const vatDisplays = document.querySelectorAll('.vat-rate-display');
        const vatRate = App.state.settings.vat_rate || 0;
        vatDisplays.forEach(el => el.textContent = vatRate);

        // Show Modal
        new bootstrap.Modal(document.getElementById('saleDetailsModal')).show();

        // Bind Print Receipt button in modal to this specific sale
        document.getElementById('btn-print-receipt').onclick = () => {
            this.printReceipt(result);
        };
    },

    printReceipt(data) {
        const printWindow = window.open('views/receipt-template.html', '_blank', 'width=900,height=800');

        // Wait for window to load then send data
        printWindow.onload = function () {
            printWindow.postMessage({
                type: 'POPULATE_RECEIPT',
                payload: { ...data, settings: App.state.settings }
            }, window.location.origin);
        };
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
