/**
 * Customers Module
 */

const customersModule = {
    table: null,

    init() {
        this.initDataTable();
        this.bindEvents();
        console.log('Customers Module Loaded');
    },

    bindEvents() {
        document.getElementById('customerForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveCustomer();
        };

        document.getElementById('btn-add-customer').onclick = () => {
            document.getElementById('customerForm').reset();
            document.getElementById('customer-id').value = '';
            document.getElementById('customerModalLabel').textContent = 'Add New Customer';
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
        this.table = $('#customersTable').DataTable({
            destroy: true,
            ajax: 'api/customers.php?action=list',
            columns: [
                { data: 'name', className: 'fw-semibold' },
                { data: 'phone', render: (data) => data || '<span class="text-muted">N/A</span>' },
                { data: 'email', render: (data) => data || '<span class="text-muted">N/A</span>' },
                {
                    data: 'balance',
                    type: 'num',
                    render: (data, type, row) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            const color = val > 0 ? 'danger' : 'success';
                            return `<span class="fw-bold text-${color}">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: 'loyalty_points',
                    render: (data, type) => {
                        if (type === 'display') {
                            return `<span class="badge bg-info-subtle text-info px-3">${data} pts</span>`;
                        }
                        return data;
                    }
                },
                {
                    data: 'last_visit',
                    render: (data, type) => {
                        if (type === 'display') {
                            return data ? new Date(data).toLocaleDateString() : '<span class="text-muted">Never</span>';
                        }
                        return data;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.editCustomer(${data.id})"><i class="fas fa-edit me-2 text-primary"></i>Edit Profile</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.viewHistory(${data.id})"><i class="fas fa-history me-2 text-teal"></i>Purchase History</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.openSettleDebt(${data.id})"><i class="fas fa-money-bill-wave me-2 text-success"></i>Settle Debt</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="customersModule.deleteCustomer(${data.id})"><i class="fas fa-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search customers...",
                lengthMenu: "_MENU_",
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    async saveCustomer() {
        const form = document.getElementById('customerForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/customers.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                bootstrap.Modal.getInstance(document.getElementById('customerModal')).hide();
                this.table.ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', 'Failed to save customer');
        }
    },

    editCustomer(id) {
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        document.getElementById('customer-id').value = customer.id;
        document.querySelector('[name="name"]').value = customer.name;
        document.querySelector('[name="phone"]').value = customer.phone;
        document.querySelector('[name="email"]').value = customer.email;
        document.querySelector('[name="balance"]').value = customer.balance;
        document.querySelector('[name="loyalty_points"]').value = customer.loyalty_points;

        document.getElementById('customerModalLabel').textContent = 'Edit Customer Profile';
        new bootstrap.Modal(document.getElementById('customerModal')).show();
    },

    async deleteCustomer(id) {
        const confirm = await Swal.fire({
            title: 'Delete Customer?',
            text: "This will remove the customer and their history!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete!'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`customers.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            }
        }
    },

    async viewHistory(id) {
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        document.getElementById('historyCustomerName').textContent = customer.name;
        const historyBody = document.getElementById('historyBody');
        historyBody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm text-teal"></div> Loading...</td></tr>';

        const modal = new bootstrap.Modal(document.getElementById('historyModal'));
        modal.show();

        try {
            const result = await App.api(`sales.php?action=history&customer_id=${id}`);
            if (result && result.data) {
                if (result.data.length === 0) {
                    historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No purchase history found</td></tr>';
                } else {
                    historyBody.innerHTML = result.data.map(sale => `
                        <tr>
                            <td>${new Date(sale.date).toLocaleDateString()}</td>
                            <td class="fw-medium">#${sale.id}</td>
                            <td class="fw-bold">${App.formatCurrency(sale.total)}</td>
                            <td><span class="badge bg-light text-dark border">${sale.payment_method}</span></td>
                            <td><span class="badge bg-success-subtle text-success">Completed</span></td>
                        </tr>
                    `).join('');
                }
            }
        } catch (error) {
            historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load history</td></tr>';
        }
    },

    async openSettleDebt(id) {
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        document.getElementById('debt-customer-id').value = customer.id;
        document.getElementById('debt-customer-name').value = customer.name;
        document.getElementById('debt-current-balance').textContent = App.formatCurrency(customer.balance || 0);

        const amountInput = document.getElementById('debt-payment-amount');
        amountInput.value = '';
        amountInput.max = customer.balance || 0;
        amountInput.dataset.max = customer.balance || 0;

        // Load accounts for treasury integration
        const accountSelect = document.getElementById('debt-account-id');
        if (accountSelect) {
            const result = await App.api('vault.php?action=list_accounts');
            if (result && result.data) {
                accountSelect.innerHTML = '<option value="">-- No Treasury Update --</option>' +
                    result.data.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');
            }
        }

        new bootstrap.Modal(document.getElementById('settleDebtModal')).show();
    },

    async submitSettleDebt() {
        const amountInput = document.getElementById('debt-payment-amount');
        const amount = parseFloat(amountInput.value) || 0;
        const maxAmount = parseFloat(amountInput.dataset.max) || 0;

        if (amount > maxAmount) {
            App.toast('error', `Payment cannot exceed the current debt of ${App.formatCurrency(maxAmount)}`);
            amountInput.classList.add('is-invalid');
            return;
        }

        amountInput.classList.remove('is-invalid');

        const form = document.getElementById('settleDebtForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/customers.php?action=add_payment', {
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
            App.toast('error', 'Failed to settle debt: ' + error.message);
        }
    }
};

// Initialize
if (document.getElementById('customersTable')) {
    customersModule.init();
}

window.customersModule = customersModule;
