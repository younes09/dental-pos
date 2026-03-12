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

        document.getElementById('btn-export-pdf').onclick = () => this.exportPDF();
        document.getElementById('btn-export-excel').onclick = () => this.exportExcel();
    },

    async generateReport() {
        const type = document.getElementById('report-type').value;
        const from = document.getElementById('date-from').value;
        const to = document.getElementById('date-to').value;

        const titleEl = document.getElementById('report-title');
        titleEl.textContent = App.t(`reports.filter.type.${type}`) || (this.capitalize(type) + ' Report');
        titleEl.removeAttribute('data-i18n'); // Since we're setting it dynamically now

        const result = await App.api(`reports.php?type=${type}&from=${from}&to=${to}`);
        if (result) {
            this.currentData = result.data;
            this.currentType = type;
            this.renderTable(type, result.data);
        }
    },

    exportPDF() {
        if (!this.currentData || this.currentData.length === 0) {
            App.toast('warning', App.t('reports.js.no_data_export') || 'No data to export');
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4');
        
        const type = this.currentType;
        const title = App.t(`reports.filter.type.${type}`) || (this.capitalize(type) + ' Report');
        const from = document.getElementById('date-from').value;
        const to = document.getElementById('date-to').value;

        doc.setFontSize(18);
        doc.text(title, 40, 40);
        doc.setFontSize(10);
        doc.text(`${App.t('reports.filter.from_date')}: ${from}  |  ${App.t('reports.filter.to_date')}: ${to}`, 40, 60);

        const table = document.getElementById('reportsTable');
        doc.autoTable({
            html: '#reportsTable',
            startY: 80,
            theme: 'striped',
            headStyles: { fillColor: [0, 191, 166] }, // Teal color
            styles: { fontSize: 9 },
            margin: { top: 80 }
        });

        doc.save(`Report_${type}_${from}_to_${to}.pdf`);
    },

    exportExcel() {
        if (!this.currentData || this.currentData.length === 0) {
            App.toast('warning', App.t('reports.js.no_data_export') || 'No data to export');
            return;
        }

        const type = this.currentType;
        const from = document.getElementById('date-from').value;
        const to = document.getElementById('date-to').value;

        const table = document.getElementById('reportsTable');
        const wb = XLSX.utils.table_to_book(table, { sheet: "Report" });
        XLSX.writeFile(wb, `Report_${type}_${from}_to_${to}.xlsx`);
    },

    renderTable(type, data) {
        const header = document.getElementById('reportsTableHeader');
        const body = document.getElementById('reportsTableBody');

        // Clear
        header.innerHTML = '';
        body.innerHTML = '';

        if (data.length === 0) {
            body.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">${App.t('reports.js.no_data') || 'No data found for the selected period'}</td></tr>`;
            return;
        }

        let columns = [];

        switch (type) {
            case 'sales':
                columns = [
                    App.t('reports.col.id'),
                    App.t('reports.col.customer'),
                    App.t('reports.col.date'),
                    App.t('reports.col.subtotal'),
                    App.t('reports.col.disc'),
                    App.t('reports.col.tax'),
                    App.t('reports.col.total'),
                    App.t('reports.col.payment')
                ];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => {
                    const colors = {
                        'Cash': 'bg-success-subtle text-success',
                        'Card': 'bg-primary-subtle text-primary',
                        'Insurance': 'bg-info-subtle text-info'
                    };
                    const badgeClass = colors[r.payment_method] || 'bg-secondary-subtle text-secondary';

                    return `
                        <tr>
                            <td>#${r.id}</td>
                            <td>${r.customer || App.t('reports.table.walking')}</td>
                            <td>${new Date(r.date).toLocaleDateString()}</td>
                            <td>${r.subtotal} ${App.state.settings.currency}</td>
                            <td class="text-danger">-${r.discount} ${App.state.settings.currency}</td>
                            <td>${r.tax} ${App.state.settings.currency}</td>
                            <td class="fw-bold">${r.total} ${App.state.settings.currency}</td>
                            <td><span class="badge ${badgeClass} px-3 rounded-pill">${r.payment_method}</span></td>
                        </tr>
                    `;
                }).join('');
                break;

            case 'stock':
                columns = [
                    App.t('reports.col.product'),
                    App.t('reports.col.category'),
                    App.t('reports.col.buy_price'),
                    App.t('reports.col.sell_price'),
                    App.t('reports.col.qty'),
                    App.t('reports.col.total_value')
                ];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => `
                    <tr>
                        <td class="fw-bold">${r.name}</td>
                        <td>${r.category || App.t('reports.table.na')}</td>
                        <td>${r.purchase_price} ${App.state.settings.currency}</td>
                        <td>${r.selling_price} ${App.state.settings.currency}</td>
                        <td><span class="badge bg-navy">${r.stock_qty}</span></td>
                        <td class="fw-bold text-teal">${parseFloat(r.inventory_value).toFixed(2)} ${App.state.settings.currency}</td>
                    </tr>
                `).join('');
                break;

            case 'customers':
                columns = [
                    App.t('reports.col.name'),
                    App.t('reports.col.phone'),
                    App.t('reports.col.orders'),
                    App.t('reports.col.total_spent'),
                    App.t('reports.col.balance'),
                    App.t('reports.col.points')
                ];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => `
                    <tr>
                        <td class="fw-bold">${r.name}</td>
                        <td>${r.phone || App.t('reports.table.na')}</td>
                        <td>${r.total_orders}</td>
                        <td class="fw-bold text-teal">${parseFloat(r.total_spent || 0).toFixed(2)} ${App.state.settings.currency}</td>
                        <td class="text-danger">${r.balance} ${App.state.settings.currency}</td>
                        <td><span class="badge bg-info">${r.loyalty_points}</span></td>
                    </tr>
                `).join('');
                break;

            case 'suppliers':
                columns = [
                    App.t('reports.col.name'),
                    App.t('reports.col.company'),
                    App.t('reports.col.email'),
                    App.t('reports.col.orders'),
                    App.t('reports.col.total_purchases')
                ];
                header.innerHTML = `<tr>${columns.map(c => `<th>${c}</th>`).join('')}</tr>`;
                body.innerHTML = data.map(r => `
                    <tr>
                        <td class="fw-bold">${r.name}</td>
                        <td>${r.company || App.t('reports.table.na')}</td>
                        <td>${r.email || App.t('reports.table.na')}</td>
                        <td>${r.total_orders}</td>
                        <td class="fw-bold text-teal">${parseFloat(r.total_purchases || 0).toFixed(2)} ${App.state.settings.currency}</td>
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
