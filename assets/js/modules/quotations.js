/**
 * Quotations Module
 */

const quotationsModule = {
    table: null,
    products: [],
    customers: [],

    async init() {
        this.initDataTable();
        await this.loadMeta();
        this.bindEvents();
        console.log('Quotations Module Loaded');
    },

    bindEvents() {
        document.getElementById('btn-add-quo-item').onclick = () => this.addItemRow();

        document.getElementById('quotationForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveQuotation();
        };

        // Delegate row calculations and removals
        document.querySelector('#quo-items-table tbody').addEventListener('input', (e) => {
            if (e.target.classList.contains('quo-item-qty') || e.target.classList.contains('quo-item-price')) {
                this.calculateRowTotal(e.target.closest('tr'));
                this.calculateTotals();
            }
        });

        document.querySelector('#quo-items-table tbody').addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove-item')) {
                e.target.closest('tr').remove();
                this.calculateTotals();
            }
        });

        document.getElementById('quo-discount-amount').addEventListener('input', () => {
            this.calculateTotals();
        });
    },

    initDataTable() {
        this.table = $('#quotationsTable').DataTable({
            ajax: 'api/quotations.php?action=list',
            columns: [
                {
                    data: 'id',
                    render: (data, type) => {
                        if (type === 'display') {
                            return `<span class="fw-bold">#DEV-${data.toString().padStart(4, '0')}</span>`;
                        }
                        return data;
                    }
                },
                { data: 'date' },
                {
                    data: 'customer_name',
                    render: (data) => data ? `<span class="fw-medium">${data}</span>` : `<span class="text-muted fst-italic">${App.t('pos.walking_customer')}</span>`
                },
                {
                    data: 'total',
                    type: 'num',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            return `<span class="fw-bold text-teal">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: 'status',
                    render: (data) => {
                        const badges = {
                            'Pending': 'bg-warning-subtle text-warning',
                            'Converted': 'bg-success-subtle text-success',
                            'Cancelled': 'bg-danger-subtle text-danger'
                        };
                        const displayStatus = data ? App.t(`quo.js.${data.toLowerCase()}`) : data;
                        return `<span class="badge ${badges[data] || 'bg-secondary'} px-3 rounded-pill">${displayStatus}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => {
                        let cancelBtn = '';
                        if (data.status === 'Pending') {
                            cancelBtn = `<li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="quotationsModule.cancelQuotation(${data.id})"><i class="fas fa-ban me-2"></i>${App.t('quo.js.cancel')}</a></li>`;
                        }

                        return `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quotationsModule.viewDetails(${data.id})"><i class="fas fa-eye me-2 text-info"></i>${App.t('quo.js.view')}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                ${cancelBtn}
                            </ul>
                        </div>
                        `;
                    }
                }
            ],
            language: App.getDataTableLanguage(),
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            drawCallback: () => this.updateStats()
        });
    },

    async loadMeta() {
        const custRes = await App.api('customers.php?action=list');
        if (custRes && custRes.data) {
            this.customers = custRes.data;
            const select = document.getElementById('quo-customer-select');
            select.innerHTML = `<option value="">${App.t('pos.walking_customer')}</option>` +
                this.customers.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        }

        const prodRes = await App.api('sales.php?action=list_products');
        if (prodRes && prodRes.products) {
            this.products = prodRes.products;
        }
    },

    addItemRow() {
        const tbody = document.querySelector('#quo-items-table tbody');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-select form-select-sm quo-item-product" required>
                    <option value="">${App.t('po.js.opt_select_product')}</option>
                    ${this.products.map(p => {
            return `<option value="${p.id}" data-price="${p.selling_price}">${p.name} (Stock: ${p.stock_qty})</option>`;
        }).join('')}
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm quo-item-qty text-center" value="1" min="1" required></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm quo-item-price text-end" value="0.00" required></td>
            <td class="quo-item-total text-end fw-bold text-navy">${App.formatCurrency(0)}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm text-danger btn-remove-item">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;

        row.querySelector('.quo-item-product').onchange = (e) => {
            const option = e.target.selectedOptions[0];
            const price = option ? option.dataset.price : 0;
            row.querySelector('.quo-item-price').value = price;
            this.calculateRowTotal(row);
            this.calculateTotals();
        };

        tbody.appendChild(row);
    },

    calculateRowTotal(row) {
        const qty = parseFloat(row.querySelector('.quo-item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.quo-item-price').value) || 0;
        const total = qty * price;
        row.querySelector('.quo-item-total').textContent = App.formatCurrency(total);
    },

    calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('#quo-items-table tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.quo-item-qty').value) || 0;
            const price = parseFloat(row.querySelector('.quo-item-price').value) || 0;
            subtotal += qty * price;
        });

        let discount = parseFloat(document.getElementById('quo-discount-amount').value) || 0;
        if (discount > subtotal) discount = subtotal;

        const taxable = subtotal - discount;
        const taxRate = parseFloat(App.state.settings.vat_rate || 0) / 100;
        const tax = taxable * taxRate;
        const total = taxable + tax;

        document.getElementById('quo-subtotal-display').textContent = App.formatCurrency(subtotal);
        document.getElementById('quo-tax-display').textContent = App.formatCurrency(tax);
        document.getElementById('quo-total-display').textContent = App.formatCurrency(total);
    },

    async saveQuotation() {
        const customer_id = document.getElementById('quo-customer-select').value;
        const notes = document.getElementById('quo-notes').value;
        const discount_amount = document.getElementById('quo-discount-amount').value;
        const items = [];

        document.querySelectorAll('#quo-items-table tbody tr').forEach(row => {
            const productId = row.querySelector('.quo-item-product').value;
            const qty = parseFloat(row.querySelector('.quo-item-qty').value);
            const price = parseFloat(row.querySelector('.quo-item-price').value);

            if (productId && qty > 0) {
                items.push({
                    id: productId,
                    qty: qty,
                    price: price
                });
            }
        });

        if (items.length === 0) {
            App.toast('error', App.t('po.js.error_no_items'));
            return;
        }

        const data = {
            customer_id: customer_id ? customer_id : null,
            notes: notes,
            discount_amount: discount_amount,
            items: items
        };

        const result = await App.api('quotations.php?action=create', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('quotationModal')).hide();
            this.table.ajax.reload();
            document.getElementById('quotationForm').reset();
            document.querySelector('#quo-items-table tbody').innerHTML = '';
            this.calculateTotals();
        }
    },

    async cancelQuotation(id) {
        const confirm = await Swal.fire({
            title: App.t('po.js.cancel_title'),
            text: "Are you sure you want to cancel this quotation?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f39c12',
            cancelButtonColor: '#6c757d',
            confirmButtonText: App.t('po.js.cancel_confirm')
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`quotations.php?action=cancel`, 'POST', { id: id });
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload(null, false);
            }
        }
    },

    updateStats() {
        if (!this.table) return;
        const data = this.table.rows().data().toArray();
        const totalCount = data.length;
        const pendingCount = data.filter(d => d.status === 'Pending').length;
        const convertedCount = data.filter(d => d.status === 'Converted').length;
        const totalValue = data.filter(d => d.status !== 'Cancelled').reduce((sum, d) => sum + parseFloat(d.total), 0);

        document.getElementById('stat-total-quo').textContent = totalCount;
        document.getElementById('stat-pending-quo').textContent = pendingCount;
        document.getElementById('stat-converted-quo').textContent = convertedCount;
        document.getElementById('stat-total-quo-value').textContent = App.formatCurrency(totalValue);
    },

    async viewDetails(id) {
        const result = await App.api(`quotations.php?action=get&id=${id}`);
        if (!result || !result.success) return;

        const { quotation, items } = result;

        document.getElementById('quo-details-customer').innerHTML = `
            <div class="fw-bold fs-5 text-teal mb-1">${quotation.customer_name || App.t('pos.walking_customer')}</div>
            <div class="text-muted small mb-2"><i class="fas fa-phone me-2"></i>${quotation.customer_phone || 'N/A'}</div>
            <div class="small fw-semibold mt-2">${App.t('quo.modal.notes_label')}:</div>
            <div class="small text-muted fst-italic">${quotation.notes || 'None'}</div>
        `;

        document.getElementById('quo-details-meta').innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <span>Ref Number</span>
                <span class="fw-bold">DEV-${quotation.id.toString().padStart(4, '0')}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Date</span>
                <span class="fw-bold">${quotation.date}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Status</span>
                <span class="badge ${quotation.status === 'Converted' ? 'bg-success' : (quotation.status === 'Pending' ? 'bg-warning' : 'bg-danger')} px-2">${App.t(`quo.js.${quotation.status.toLowerCase()}`)}</span>
            </div>
        `;

        const tbody = document.querySelector('#quo-details-items-table tbody');
        tbody.innerHTML = items.map(item => `
            <tr>
                <td>
                    <div class="fw-medium">${item.product_name}</div>
                    <small class="text-muted small">${item.barcode || 'N/A'}</small>
                </td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">${App.formatCurrency(item.unit_price)}</td>
                <td class="text-end fw-bold text-navy">${App.formatCurrency(item.total)}</td>
            </tr>
        `).join('');

        document.getElementById('quo-details-subtotal').textContent = App.formatCurrency(quotation.subtotal);
        document.getElementById('quo-details-discount').textContent = App.formatCurrency(quotation.discount);
        document.getElementById('quo-details-tax').textContent = App.formatCurrency(quotation.tax);
        document.getElementById('quo-details-total').textContent = App.formatCurrency(quotation.total);

        // Print handler
        document.getElementById('btn-print-quo').onclick = () => this.printQuotation(quotation.id);

        new bootstrap.Modal(document.getElementById('quotationDetailsModal')).show();
    },

    async printQuotation(id) {
        const result = await App.api(`quotations.php?action=get&id=${id}`);
        if (!result) return;
        const { quotation, items } = result;
        const settings = App.state.settings || {};

        const printWindow = window.open('views/quotation_template.html', '_blank', 'width=900,height=800');
        printWindow.onload = function () {
            printWindow.postMessage({
                type: 'POPULATE_QUOTATION',
                payload: { quotation, items, settings }
            }, window.location.origin);
        };
    }
};

if (document.getElementById('quotationsTable')) {
    quotationsModule.init();
}

window.quotationsModule = quotationsModule;
