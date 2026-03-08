/**
 * Sales History Module
 */

const sales_historyModule = {
    table: null,

    init() {
        this.initDataTable();
        this.bindEvents();
        console.log('Sales History Module Loaded');
    },

    bindEvents() {
        document.getElementById('btn-export-sales').onclick = () => {
            App.toast('info', 'Export functionality coming soon');
        };

        document.getElementById('btn-print-receipt').onclick = () => {
            window.print();
        };
    },

    initDataTable() {
        this.table = $('#salesHistoryTable').DataTable({
            destroy: true,
            ajax: 'api/sales.php?action=history',
            columns: [
                {
                    data: 'id',
                    render: (data) => `<span class="fw-bold">#INV-${data}</span>`
                },
                {
                    data: 'date',
                    render: (data, type) => {
                        if (type === 'display') {
                            return new Date(data).toLocaleString();
                        }
                        return data;
                    }
                },
                {
                    data: 'customer_name',
                    render: (data) => data || '<span class="text-muted">Walk-in Customer</span>'
                },
                { data: 'user_name' },
                {
                    data: 'payment_method',
                    render: (data) => {
                        const colors = {
                            'Cash': 'bg-success-subtle text-success',
                            'Card': 'bg-primary-subtle text-primary',
                            'Insurance': 'bg-info-subtle text-info'
                        };
                        return `<span class="badge ${colors[data] || 'bg-secondary'} px-3 rounded-pill">${data}</span>`;
                    }
                },
                {
                    data: 'invoice_type',
                    render: (data) => {
                        const style = data === 'BV' ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning';
                        return `<span class="badge ${style} px-3 rounded-pill">${data || 'BV'}</span>`;
                    }
                },
                {
                    data: 'total',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            return `<span class="fw-bold text-navy">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="sales_historyModule.viewDetails(${data.id})"><i class="fas fa-eye me-2 text-info"></i>View Details</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="sales_historyModule.directPrint(${data.id})"><i class="fas fa-print me-2 text-teal"></i>Print Receipt</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search transactions...",
                lengthMenu: "_MENU_",
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
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
            <div class="d-flex justify-content-between mb-2">
                <span>Doc Type:</span>
                <span class="badge ${sale.invoice_type === 'BL' ? 'bg-warning text-dark' : 'bg-primary'} px-2">${sale.invoice_type || 'BV'}</span>
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
        const vatRate = App.state.settings.vat_rate || 15;
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
    }
};

// Initialize
if (document.getElementById('salesHistoryTable')) {
    sales_historyModule.init();
}

window.sales_historyModule = sales_historyModule;
