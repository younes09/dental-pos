/**
 * Customers Module
 */

const customersModule = {
    table: null,
    wilayas: [
        { id: '01', name: '01 - Adrar' }, { id: '02', name: '02 - Chlef' }, { id: '03', name: '03 - Laghouat' },
        { id: '04', name: '04 - Oum El Bouaghi' }, { id: '05', name: '05 - Batna' }, { id: '06', name: '06 - Béjaïa' },
        { id: '07', name: '07 - Biskra' }, { id: '08', name: '08 - Béchar' }, { id: '09', name: '09 - Blida' },
        { id: '10', name: '10 - Bouira' }, { id: '11', name: '11 - Tamanrasset' }, { id: '12', name: '12 - Tébessa' },
        { id: '13', name: '13 - Tlemcen' }, { id: '14', name: '14 - Tiaret' }, { id: '15', name: '15 - Tizi Ouzou' },
        { id: '16', name: '16 - Alger' }, { id: '17', name: '17 - Djelfa' }, { id: '18', name: '18 - Jijel' },
        { id: '19', name: '19 - Sétif' }, { id: '20', name: '20 - Saïda' }, { id: '21', name: '21 - Skikda' },
        { id: '22', name: '22 - Sidi Bel Abbès' }, { id: '23', name: '23 - Annaba' }, { id: '24', name: '24 - Guelma' },
        { id: '25', name: '25 - Constantine' }, { id: '26', name: '26 - Médéa' }, { id: '27', name: '27 - Mostaganem' },
        { id: '28', name: '28 - M\'Sila' }, { id: '29', name: '29 - Mascara' }, { id: '30', name: '30 - Ouargla' },
        { id: '31', name: '31 - Oran' }, { id: '32', name: '32 - El Bayadh' }, { id: '33', name: '33 - Illizi' },
        { id: '34', name: '34 - Bordj Bou Arreridj' }, { id: '35', name: '35 - Boumerdès' }, { id: '36', name: '36 - El Tarf' },
        { id: '37', name: '37 - Tindouf' }, { id: '38', name: '38 - Tissemsilt' }, { id: '39', name: '39 - El Oued' },
        { id: '40', name: '40 - Khenchela' }, { id: '41', name: '41 - Souk Ahras' }, { id: '42', name: '42 - Tipaza' },
        { id: '43', name: '43 - Mila' }, { id: '44', name: '44 - Aïn Defla' }, { id: '45', name: '45 - Naâma' },
        { id: '46', name: '46 - Aïn Témouchent' }, { id: '47', name: '47 - Ghardaïa' }, { id: '48', name: '48 - Relizane' },
        { id: '49', name: '49 - El M\'Ghair' }, { id: '50', name: '50 - El Meniaa' }, { id: '51', name: '51 - Ouled Djellal' },
        { id: '52', name: '52 - Bordj Baji Mokhtar' }, { id: '53', name: '53 - Béni Abbès' }, { id: '54', name: '54 - Timimoun' },
        { id: '55', name: '55 - Touggourt' }, { id: '56', name: '56 - Djanet' }, { id: '57', name: '57 - In Salah' },
        { id: '58', name: '58 - In Guezzam' }
    ],

    init() {
        this.populateWilayas();
        this.initDataTable();
        this.bindEvents();
        console.log('Customers Module Loaded');
    },

    populateWilayas() {
        const select = document.getElementById('customer-wilaya');
        if (!select) return;
        
        // Clear existing except first
        select.innerHTML = `<option value="">${App.t('customers.modal.select_wilaya') || 'Select Wilaya'}</option>`;
        
        this.wilayas.forEach(w => {
            const option = document.createElement('option');
            option.value = w.id;
            option.textContent = w.name;
            select.appendChild(option);
        });
    },

    bindEvents() {
        document.getElementById('customerForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveCustomer();
        };

        document.getElementById('btn-add-customer').onclick = () => {
            document.getElementById('customerForm').reset();
            document.getElementById('customer-id').value = '';
            document.getElementById('customerModalLabel').textContent = App.t('customers.js.add_title') || 'Add New Customer';
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
                { data: 'phone', render: (data) => data || `<span class="text-muted">${App.t('customers.js.na') || 'N/A'}</span>` },
                { data: 'email', render: (data) => data || `<span class="text-muted">${App.t('customers.js.na') || 'N/A'}</span>` },
                { 
                    data: 'wilaya', 
                    render: (data) => {
                        const w = this.wilayas.find(x => x.id === data);
                        return w ? w.name : `<span class="text-muted">${App.t('customers.js.na') || 'N/A'}</span>`;
                    }
                },
                {
                    data: 'total_purchases',
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
                            return data ? new Date(data).toLocaleDateString() : `<span class="text-muted">${App.t('customers.js.never') || 'Never'}</span>`;
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
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.editCustomer(${data.id})"><i class="fas fa-edit me-2 text-primary"></i>${App.t('customers.js.action.edit') || 'Edit Profile'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.viewHistory(${data.id})"><i class="fas fa-history me-2 text-teal"></i>${App.t('customers.js.action.history') || 'Purchase History'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="customersModule.openSettleDebt(${data.id})"><i class="fas fa-money-bill-wave me-2 text-success"></i>${App.t('customers.js.action.settle') || 'Settle Debt'}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="customersModule.deleteCustomer(${data.id})"><i class="fas fa-trash me-2"></i>${App.t('customers.js.action.delete') || 'Delete'}</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: App.getDataTableLanguage(),
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
            App.toast('error', App.t('customers.js.save_fail') || 'Failed to save customer');
        }
    },

    editCustomer(id) {
        const customer = this.table.rows().data().toArray().find(c => c.id == id);
        if (!customer) return;

        document.getElementById('customer-id').value = customer.id;
        document.querySelector('[name="name"]').value = customer.name;
        document.querySelector('[name="phone"]').value = customer.phone;
        document.querySelector('[name="email"]').value = customer.email;
        document.querySelector('[name="wilaya"]').value = customer.wilaya || '';
        document.querySelector('[name="balance"]').value = customer.balance;
        document.querySelector('[name="loyalty_points"]').value = customer.loyalty_points;

        document.getElementById('customerModalLabel').textContent = App.t('customers.js.edit_title') || 'Edit Customer Profile';
        new bootstrap.Modal(document.getElementById('customerModal')).show();
    },

    async deleteCustomer(id) {
        const confirm = await Swal.fire({
            title: App.t('customers.js.delete_title') || 'Delete Customer?',
            text: App.t('customers.js.delete_text') || "This will remove the customer and their history!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('customers.js.btn_yes') || 'Yes, delete!',
            cancelButtonText: App.t('btn_cancel') || 'Cancel'
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
        historyBody.innerHTML = `<tr><td colspan="5" class="text-center"><div class="spinner-border spinner-border-sm text-teal"></div> ${App.t('customers.js.loading') || 'Loading...'}</td></tr>`;

        const modal = new bootstrap.Modal(document.getElementById('historyModal'));
        modal.show();

        try {
            const result = await App.api(`sales.php?action=history&customer_id=${id}`);
            if (result && result.data) {
                if (result.data.length === 0) {
                    historyBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">${App.t('customers.js.no_history') || 'No purchase history found'}</td></tr>`;
                } else {
                    historyBody.innerHTML = result.data.map(sale => `
                        <tr>
                            <td>${new Date(sale.date).toLocaleDateString()}</td>
                            <td class="fw-medium">#${sale.id}</td>
                            <td class="fw-bold">${App.formatCurrency(sale.total)}</td>
                            <td><span class="badge bg-light text-dark border">${App.t(`customers.method.${sale.payment_method.toLowerCase().split(' ')[0]}`) || sale.payment_method}</span></td>
                            <td><span class="badge bg-success-subtle text-success">${App.t('customers.js.completed') || 'Completed'}</span></td>
                        </tr>
                    `).join('');
                }
            }
        } catch (error) {
            historyBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${App.t('customers.js.history_fail') || 'Failed to load history'}</td></tr>`;
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
                accountSelect.innerHTML = `<option value="">${App.t('customers.js.no_treasury') || '-- No Treasury Update --'}</option>` +
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
            App.toast('error', (App.t('customers.js.error_exceed') || `Payment cannot exceed the current debt of `) + App.formatCurrency(maxAmount));
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
            App.toast('error', (App.t('customers.js.error_settle_fail') || 'Failed to settle debt: ') + error.message);
        }
    }
};

// Initialize
if (document.getElementById('customersTable')) {
    customersModule.init();
}

window.customersModule = customersModule;
