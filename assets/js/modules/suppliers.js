/**
 * Suppliers Module
 */

const suppliersModule = {
    table: null,

    init() {
        this.initDataTable();
        this.bindEvents();
        console.log('Suppliers Module Loaded');
    },

    bindEvents() {
        document.getElementById('supplierForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveSupplier();
        };

        document.getElementById('btn-add-supplier').onclick = () => {
            document.getElementById('supplierForm').reset();
            document.getElementById('supplier-id').value = '';
            document.getElementById('supplierModalLabel').textContent = App.t('suppliers.js.add_title') || 'Add New Supplier';
        };

        const settleDebtForm = document.getElementById('settleDebtForm');
        if (settleDebtForm) {
            settleDebtForm.onsubmit = async (e) => {
                e.preventDefault();
                await this.submitSettleDebt();
            };
        }
    },

    initDataTable() {
        this.table = $('#suppliersTable').DataTable({
            destroy: true,
            ajax: 'api/suppliers.php?action=list',
            columns: [
                {
                    data: 'name',
                    render: (data, type, row) => `
                        <div class="fw-bold">${data}</div>
                        <small class="text-muted">${row.company || ''}</small>
                    `
                },
                { data: 'company', visible: false },
                { data: 'phone', render: (data) => data || `<span class="text-muted">${App.t('suppliers.js.na') || 'N/A'}</span>` },
                { data: 'email', render: (data) => data || `<span class="text-muted">${App.t('suppliers.js.na') || 'N/A'}</span>` },
                {
                    data: 'total_purchases',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            return `<span class="fw-bold text-muted">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: 'balance',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            const colorClass = val > 0 ? 'text-danger' : 'text-success';
                            return `<span class="fw-bold ${colorClass}">${App.formatCurrency(val)}</span>`;
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
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="suppliersModule.editSupplier(${data.id})"><i class="fas fa-edit me-2 text-primary"></i>${App.t('suppliers.js.action.edit') || 'Edit Supplier'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="suppliersModule.viewProducts(${data.id}, '${data.name.replace(/'/g, "\\'")}')"><i class="fas fa-box me-2 text-info"></i>${App.t('suppliers.js.action.view_products') || 'View Products'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="suppliersModule.createPO(${data.id})"><i class="fas fa-cart-plus me-2 text-teal"></i>${App.t('suppliers.js.action.create_po') || 'Create Purchase Order'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="suppliersModule.openSettleDebt(${data.id})"><i class="fas fa-money-bill-wave me-2 text-success"></i>${App.t('suppliers.js.action.settle') || 'Settle Debt'}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="suppliersModule.deleteSupplier(${data.id})"><i class="fas fa-trash me-2"></i>${App.t('suppliers.js.action.delete') || 'Delete'}</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: App.getDataTableLanguage(),
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    async saveSupplier() {
        const form = document.getElementById('supplierForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/suppliers.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
                this.table.ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', App.t('suppliers.js.save_fail') || 'Failed to save supplier');
        }
    },

    editSupplier(id) {
        const supplier = this.table.rows().data().toArray().find(s => s.id == id);
        if (!supplier) return;

        document.getElementById('supplier-id').value = supplier.id;
        document.querySelector('[name="name"]').value = supplier.name;
        document.querySelector('[name="company"]').value = supplier.company;
        document.querySelector('[name="phone"]').value = supplier.phone;
        document.querySelector('[name="email"]').value = supplier.email;

        document.getElementById('supplierModalLabel').textContent = App.t('suppliers.js.edit_title') || 'Edit Supplier Info';
        new bootstrap.Modal(document.getElementById('supplierModal')).show();
    },

    async deleteSupplier(id) {
        const confirm = await Swal.fire({
            title: App.t('suppliers.js.delete_title') || 'Delete Supplier?',
            text: App.t('suppliers.js.delete_text') || "All associated order history will be affected!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('suppliers.js.btn_yes') || 'Yes, delete!',
            cancelButtonText: App.t('btn_cancel') || 'Cancel'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`suppliers.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            }
        }
    },

    createPO(id) {
        window.location.hash = `#purchase_orders?supplier_id=${id}`;
    },

    openSettleDebt(id) {
        const supplier = this.table.rows().data().toArray().find(s => s.id == id);
        if (!supplier) return;

        document.getElementById('debt-supplier-id').value = supplier.id;
        document.getElementById('debt-supplier-name').value = supplier.name;
        document.getElementById('debt-current-balance').textContent = App.formatCurrency(supplier.balance || 0);

        const amountInput = document.getElementById('debt-payment-amount');
        amountInput.value = '';
        amountInput.max = supplier.balance || 0;
        amountInput.dataset.max = supplier.balance || 0;

        new bootstrap.Modal(document.getElementById('settleDebtModal')).show();
    },

    async submitSettleDebt() {
        const amountInput = document.getElementById('debt-payment-amount');
        const amount = parseFloat(amountInput.value) || 0;
        const maxAmount = parseFloat(amountInput.dataset.max) || 0;

        if (amount > maxAmount) {
            App.toast('error', (App.t('customers.js.error_exceed') || `Payment cannot exceed the current debt of `) + App.formatCurrency(maxAmount)); // reused from customers
            amountInput.classList.add('is-invalid');
            return;
        }

        amountInput.classList.remove('is-invalid');

        const form = document.getElementById('settleDebtForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/suppliers.php?action=add_payment', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                bootstrap.Modal.getInstance(document.getElementById('settleDebtModal')).hide();
                this.table.ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', (App.t('customers.js.error_settle_fail') || 'Failed to settle debt: ') + error.message);
        }
    },

    viewProducts(id, name) {
        document.getElementById('supplierProductsModalTitle').textContent = name;
        new bootstrap.Modal(document.getElementById('supplierProductsModal')).show();

        if ($.fn.DataTable.isDataTable('#supplierProductsTable')) {
            $('#supplierProductsTable').DataTable().destroy();
        }

        $('#supplierProductsTable').DataTable({
            ajax: `api/suppliers.php?action=products&id=${id}`,
            columns: [
                { data: 'name', render: (data) => `<span class="fw-bold">${data}</span>` },
                { data: 'barcode', render: (data) => data || `<span class="text-muted">${App.t('suppliers.js.na') || 'N/A'}</span>` },
                {
                    data: null,
                    render: (data, type, row) => `${row.category_name || `<span class="text-muted">${App.t('suppliers.js.uncategorized') || 'Uncategorized'}</span>`} <br> <small class="text-muted">${row.brand_name || App.t('suppliers.js.no_brand') || 'No Brand'}</small>`
                },
                {
                    data: 'total_supplied_qty',
                    render: (data) => `<span class="badge bg-teal-subtle text-teal rounded-pill px-3">${data || 0}</span>`
                },
                {
                    data: 'avg_unit_cost',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        return type === 'display' ? `<span class="fw-semibold">${App.formatCurrency(val)}</span>` : val;
                    }
                },
                {
                    data: 'last_purchase_date',
                    render: (data) => data ? App.formatDate(data) : `<span class="text-muted">${App.t('suppliers.js.never') || 'Never'}</span>`
                }
            ],
            language: App.getDataTableLanguage(),
            dom: '<"d-flex justify-content-between align-items-center mb-3"f>rt<"d-flex justify-content-between align-items-center mt-3"p>',
            pageLength: 5
        });
    }
};

// Initialize
if (document.getElementById('suppliersTable')) {
    suppliersModule.init();
}

window.suppliersModule = suppliersModule;
