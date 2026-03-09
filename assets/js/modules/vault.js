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
            container.innerHTML = '<div class="col-12 text-center py-5">No accounts configured.</div>';
            return;
        }

        container.innerHTML = this.accounts.map(acc => `
            <div class="col-md-4 mb-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                    <div class="card-body p-4 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="rounded-circle bg-${acc.type === 'Bank' ? 'info' : 'teal'}-subtle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="fas fa-${acc.type === 'Bank' ? 'university' : 'vault'} text-${acc.type === 'Bank' ? 'info' : 'teal'}"></i>
                            </div>
                            <span class="badge ${acc.status === 'Active' ? 'bg-success-subtle text-success' : 'bg-light'} rounded-pill px-3 small">${acc.status}</span>
                        </div>
                        <h6 class="text-muted small fw-bold text-uppercase mb-1 tracking-wider">${acc.name}</h6>
                        <h3 class="fw-bold mb-0 text-navy">${App.formatCurrency(acc.balance)}</h3>
                        
                        <!-- Decorative element -->
                        <div class="position-absolute opacity-10" style="bottom: -20px; right: -10px; font-size: 80px;">
                            <i class="fas fa-${acc.type === 'Bank' ? 'university' : 'vault'}"></i>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    populateSelectors() {
        const selectors = [
            document.querySelector('#formAddTransaction select[name="account_id"]'),
            document.querySelector('#formTransfer select[name="from_id"]'),
            document.querySelector('#formTransfer select[name="to_id"]'),
            document.getElementById('filter-account')
        ];

        const options = this.accounts.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');

        selectors.forEach(s => {
            if (!s) return;
            const isFilter = s.id === 'filter-account';
            s.innerHTML = (isFilter ? '<option value="">All accounts</option>' : '') + options;
        });
    },

    async loadTransactions() {
        const accountId = document.getElementById('filter-account')?.value || '';
        const result = await App.api(`vault.php?action=list_transactions&account_id=${accountId}`);

        const tbody = document.querySelector('#vaultTransactionsTable tbody');
        if (!tbody) return;

        if (!result || !result.data || result.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">No transactions recorded.</td></tr>';
            return;
        }

        tbody.innerHTML = result.data.map(tx => {
            let typeBadge = '';
            switch (tx.type) {
                case 'Income': typeBadge = '<span class="badge bg-success-subtle text-success rounded-pill px-3">Income</span>'; break;
                case 'Expense': typeBadge = '<span class="badge bg-danger-subtle text-danger rounded-pill px-3">Expense</span>'; break;
                case 'Transfer_In': typeBadge = '<span class="badge bg-info-subtle text-info rounded-pill px-3">Transfer In</span>'; break;
                case 'Transfer_Out': typeBadge = '<span class="badge bg-warning-subtle text-warning rounded-pill px-3">Transfer Out</span>'; break;
            }

            const amountClass = (tx.type === 'Income' || tx.type === 'Transfer_In') ? 'text-success' : 'text-danger';
            const amountPrefix = (tx.type === 'Income' || tx.type === 'Transfer_In') ? '+' : '-';

            return `
                <tr>
                    <td>
                        <div class="fw-bold small">${App.formatDate(tx.date)}</div>
                        <small class="text-muted">${new Date(tx.date).toLocaleTimeString()}</small>
                    </td>
                    <td><span class="fw-medium">${tx.account_name}</span></td>
                    <td>${typeBadge}</td>
                    <td>
                        <div class="small fw-medium">${tx.description}</div>
                        ${tx.related_type ? `<small class="text-muted">Réf: ${tx.related_type} #${tx.related_id}</small>` : ''}
                    </td>
                    <td class="text-end fw-bold ${amountClass}">${amountPrefix}${App.formatCurrency(tx.amount)}</td>
                    <td><small class="text-muted">${tx.user_name}</small></td>
                </tr>
            `;
        }).join('');
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
    }
};

// Initialize
if (document.getElementById('vaultTransactionsTable')) {
    vaultModule.init();
}
window.vaultModule = vaultModule;
