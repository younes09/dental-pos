/**
 * Reports Module
 */

const reportsModule = {
    init() {
        this.setDefaultDates();
        this.bindEvents();
        this.generateReport();
        console.log('Reports Module Loaded');
    },

    setDefaultDates() {
        const today = new Date().toISOString().split('T')[0];
        const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        document.getElementById('date-from').value = thirtyDaysAgo;
        document.getElementById('date-to').value = today;
    },

    bindEvents() {
        document.getElementById('report-filter-form').onsubmit = (e) => {
            e.preventDefault();
            this.generateReport();
        };

        document.getElementById('btn-export-pdf').onclick = () => App.toast('info', 'PDF Exporting...');
        document.getElementById('btn-export-excel').onclick = () => App.toast('info', 'Excel Exporting...');
    },

    async generateReport() {
        const type = document.getElementById('report-type').value;
        const from = document.getElementById('date-from').value;
        const to = document.getElementById('date-to').value;

        document.getElementById('report-title').textContent = this.capitalize(type) + ' Report';

        const result = await App.api(`reports.php?type=${type}&from=${from}&to=${to}`);
        if (result) {
            this.renderTable(type, result.data);
        }
    },

    renderTable(type, data) {
        const header = document.getElementById('reportsTableHeader');
        const body = document.getElementById('reportsTableBody');

        // Clear
        header.innerHTML = '';
        body.innerHTML = '';

        if (data.length === 0) {
            body.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">No data found for the selected period</td></tr>`;
            return;
        }

        let columns = [];

        switch (type) {
            case 'sales':
                columns = ['ID', 'Customer', 'Date', 'Subtotal', 'Disc', 'Tax', 'Total', 'Payment'];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => `
                    <tr>
                        <td>#${r.id}</td>
                        <td>${r.customer || 'Walking'}</td>
                        <td>${new Date(r.date).toLocaleDateString()}</td>
                        <td>${r.subtotal} ${App.state.settings.currency}</td>
                        <td class="text-danger">-${r.discount} ${App.state.settings.currency}</td>
                        <td>${r.tax} ${App.state.settings.currency}</td>
                        <td class="fw-bold">${r.total} ${App.state.settings.currency}</td>
                        <td><span class="badge bg-light text-dark">${r.payment_method}</span></td>
                    </tr>
                `).join('');
                break;

            case 'stock':
                columns = ['Product', 'Category', 'Buy Price', 'Sell Price', 'Qty', 'Total Value'];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => `
                    <tr>
                        <td class="fw-bold">${r.name}</td>
                        <td>${r.category || 'N/A'}</td>
                        <td>${r.purchase_price} ${App.state.settings.currency}</td>
                        <td>${r.selling_price} ${App.state.settings.currency}</td>
                        <td><span class="badge bg-navy">${r.stock_qty}</span></td>
                        <td class="fw-bold text-teal">${parseFloat(r.inventory_value).toFixed(2)} ${App.state.settings.currency}</td>
                    </tr>
                `).join('');
                break;

            case 'customers':
                columns = ['Name', 'Phone', 'Orders', 'Total Spent', 'Balance', 'Points'];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => `
                    <tr>
                        <td class="fw-bold">${r.name}</td>
                        <td>${r.phone || 'N/A'}</td>
                        <td>${r.total_orders}</td>
                        <td class="fw-bold text-teal">${parseFloat(r.total_spent || 0).toFixed(2)} ${App.state.settings.currency}</td>
                        <td class="text-danger">${r.balance} ${App.state.settings.currency}</td>
                        <td><span class="badge bg-info">${r.loyalty_points}</span></td>
                    </tr>
                `).join('');
                break;
        }
    },

    capitalize(s) {
        return s.charAt(0).toUpperCase() + s.slice(1);
    }
};

// Initialize
if (document.getElementById('reportsTable')) {
    reportsModule.init();
}

window.reportsModule = reportsModule;
