/**
 * Vault and Bank Module
 */

const vaultModule = {
    accounts: [],

    init() {
        this.loadAccounts();
        this.loadTransactions();
        this.bindEvents();
    },

    bindEvents() {
        document.getElementById('formAddTransaction').onsubmit = (e) => {
            e.preventDefault();
            this.addTransaction(new FormData(e.target));
        };

        document.getElementById('formTransfer').onsubmit = (e) => {
            e.preventDefault();
            this.transfer(new FormData(e.target));
        };

        const formAddAccount = document.getElementById('formAddAccount');
        if (formAddAccount) {
            formAddAccount.onsubmit = (e) => {
                e.preventDefault();
                this.addAccount(new FormData(e.target));
            };
        }

        const formEditAccount = document.getElementById('formEditAccount');
        if (formEditAccount) {
            formEditAccount.onsubmit = (e) => {
                e.preventDefault();
                this.updateAccount(new FormData(e.target));
            };
        }
    },

    async loadAccounts() {
        const result = await App.api('vault.php?action=list_accounts');
        if (result && result.data) {
            this.accounts = result.data;
            this.renderAccounts();
            this.populateSelectors();
        }
    },

    renderAccounts() {
        const container = document.getElementById('vault-accounts-container');
        if (!container) return;

        if (this.accounts.length === 0) {
            container.innerHTML = `<div class="col-12 text-center py-5">${App.t('vault.msg.no_accounts') || 'No accounts configured.'}</div>`;
            return;
        }

        const typeConfig = {
            'bank': { icon: 'university', color: 'info' },
            'cash': { icon: 'cash-register', color: 'success' },
            'safe': { icon: 'vault', color: 'teal' }
        };

        container.innerHTML = this.accounts.map(acc => {
            const typeKey = String(acc.type || '').toLowerCase();
            const config = typeConfig[typeKey] || { icon: 'vault', color: 'teal' };
            return `
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="rounded-circle bg-${config.color}-subtle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="fas fa-${config.icon} text-${config.color}"></i>
                            </div>
                            <!-- Actions dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light rounded-circle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4">
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="vaultModule.openEditAccountModal(${acc.id})"><i class="fas fa-edit me-2 text-muted"></i> <span data-i18n="vault.modal.edit_account">Edit</span></a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="vaultModule.deleteAccount(${acc.id})"><i class="fas fa-trash-alt me-2"></i> Supprimer</a></li>
                                </ul>
                            </div>
                        </div>
                        <h6 class="text-muted small fw-bold text-uppercase mb-1 tracking-wider">
                            ${acc.name}
                            ${acc.is_default == 1 ? '<span class="badge bg-primary-subtle text-primary rounded-pill ms-2"><i class="fas fa-star text-warning"></i> Default</span>' : ''}
                        </h6>
                        <h3 class="fw-bold mb-0 text-navy">${App.formatCurrency(acc.balance)}</h3>
                        
                        <!-- Decorative element -->
                        <div class="position-absolute opacity-10" style="bottom: -20px; right: -10px; font-size: 80px;">
                            <i class="fas fa-${config.icon}"></i>
                        </div>
                    </div>
                </div>
            </div>
        `}).join('');
    },

    populateSelectors() {
        const selectors = [
            document.querySelector('#formAddTransaction select[name="account_id"]'),
            document.querySelector('#formTransfer select[name="from_id"]'),
            document.querySelector('#formTransfer select[name="to_id"]'),
            document.getElementById('filter-account')
        ];

        const options = this.accounts.map(acc => {
            const selected = acc.is_default == 1 ? 'selected' : '';
            return `<option value="${acc.id}" ${selected}>${acc.name} (${App.formatCurrency(acc.balance)})</option>`;
        }).join('');

        selectors.forEach(s => {
            if (!s) return;
            const isFilter = s.id === 'filter-account';
            // For the filter, we don't want a pre-selected specific account usually, or we do?
            // Usually "All Accounts" is better for filtering by default.
            s.innerHTML = (isFilter ? `<option value="" selected>${App.t('vault.filter.all_accounts') || 'All accounts'}</option>` : '') + options;
        });
    },

    async loadTransactions() {
        if (this.table) {
            this.table.destroy();
        }

        const accountId = document.getElementById('filter-account')?.value || '';
        const result = await App.api(`vault.php?action=list_transactions&account_id=${accountId}`);

        const tbody = document.querySelector('#vaultTransactionsTable tbody');
        if (!tbody) return;

        if (!result || !result.data || result.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">${App.t('vault.js.no_transactions') || 'No transactions recorded.'}</td></tr>`;
            return;
        }

        tbody.innerHTML = result.data.map(tx => {
            let typeBadge = '';
            switch (tx.type) {
                case 'Income': typeBadge = `<span class="badge bg-success-subtle text-success rounded-pill px-3">${App.t('vault.js.type_income') || 'Income'}</span>`; break;
                case 'Expense': typeBadge = `<span class="badge bg-danger-subtle text-danger rounded-pill px-3">${App.t('vault.js.type_expense') || 'Expense'}</span>`; break;
                case 'Transfer_In': typeBadge = `<span class="badge bg-info-subtle text-info rounded-pill px-3">${App.t('vault.js.type_transfer_in') || 'Transfer In'}</span>`; break;
                case 'Transfer_Out': typeBadge = `<span class="badge bg-warning-subtle text-warning rounded-pill px-3">${App.t('vault.js.type_transfer_out') || 'Transfer Out'}</span>`; break;
            }

            const amountClass = (tx.type === 'Income' || tx.type === 'Transfer_In') ? 'text-success' : 'text-danger';
            const amountPrefix = (tx.type === 'Income' || tx.type === 'Transfer_In') ? '+' : '-';

            return `
                <tr>
                    <td data-order="${tx.date}">
                        <div class="fw-bold small">${App.formatDate(tx.date)}</div>
                        <small class="text-muted">${new Date(tx.date).toLocaleTimeString()}</small>
                    </td>
                    <td><span class="fw-medium">${tx.account_name}</span></td>
                    <td>${typeBadge}</td>
                    <td>
                        <div class="small fw-medium">${tx.description}</div>
                        ${tx.related_type ? `<small class="text-muted">${App.t('vault.js.ref') || 'Ref'}: ${tx.related_type} #${tx.related_id}</small>` : ''}
                    </td>
                    <td class="text-end fw-bold ${amountClass}" data-order="${tx.amount}">${amountPrefix}${App.formatCurrency(tx.amount)}</td>
                    <td><small class="text-muted">${tx.user_name}</small></td>
                </tr>
            `;
        }).join('');

        this.table = $('#vaultTransactionsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            language: App.getDataTableLanguage(),
        });
    },

    openAddAccountModal() {
        document.getElementById('formAddAccount').reset();
        new bootstrap.Modal(document.getElementById('modalAddAccount')).show();
    },

    openAddTransactionModal() {
        document.getElementById('formAddTransaction').reset();
        new bootstrap.Modal(document.getElementById('modalAddTransaction')).show();
    },

    openTransferModal() {
        document.getElementById('formTransfer').reset();
        new bootstrap.Modal(document.getElementById('modalTransfer')).show();
    },

    async addTransaction(formData) {
        const data = Object.fromEntries(formData.entries());
        const result = await App.api('vault.php?action=add_transaction', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('modalAddTransaction')).hide();
            this.loadAccounts();
            this.loadTransactions();
        }
    },

    async transfer(formData) {
        const data = Object.fromEntries(formData.entries());
        const result = await App.api('vault.php?action=transfer', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('modalTransfer')).hide();
            this.loadAccounts();
            this.loadTransactions();
        }
    },

    async addAccount(formData) {
        const data = Object.fromEntries(formData.entries());
        data.is_default = formData.get('is_default') ? 1 : 0;
        const result = await App.api('vault.php?action=add_account', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('modalAddAccount')).hide();
            this.loadAccounts();
        }
    },

    openEditAccountModal(id) {
        const acc = this.accounts.find(a => a.id == id);
        if (!acc) return;

        const form = document.getElementById('formEditAccount');
        form.reset();
        
        form.elements['id'].value = acc.id;
        form.elements['name'].value = acc.name;
        form.elements['type'].value = acc.type.toLowerCase();
        form.elements['is_default'].checked = acc.is_default == 1;

        new bootstrap.Modal(document.getElementById('modalEditAccount')).show();
    },

    async updateAccount(formData) {
        const data = Object.fromEntries(formData.entries());
        data.is_default = formData.get('is_default') ? 1 : 0;
        const result = await App.api('vault.php?action=update_account', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('modalEditAccount')).hide();
            this.loadAccounts();
        }
    },

    async deleteAccount(id) {
        const confirmMsg = App.t('vault.msg.delete_confirm') || 'Are you sure you want to delete this account?';
        if (confirm(confirmMsg)) {
            const result = await App.api(`vault.php?action=delete_account&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.loadAccounts();
            }
        }
    }
};

// Initialize
if (document.getElementById('vaultTransactionsTable')) {
    vaultModule.init();
}
window.vaultModule = vaultModule;
