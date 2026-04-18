/**
 * Cash Register Module
 */

const cash_registerModule = {
    currentSession: null,
    table: null,

    init() {
        this.checkStatus();
        this.loadHistory();
        this.loadAccounts();
        this.bindEvents();
    },

    async loadAccounts() {
        const selectOpen = document.getElementById('open-session-account-id');
        const selectClose = document.getElementById('close-session-account-id');
        if (!selectOpen && !selectClose) return;

        const result = await App.api('vault.php?action=list_accounts');
        if (result && result.data) {
            // Filter out 'Caisse' or 'Cash' type account to prevent choosing it as source/destination
            const filteredAccounts = result.data.filter(acc => acc.name !== 'Caisse' && acc.type !== 'Cash');

            const options = filteredAccounts.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');
            if (selectOpen) {
                selectOpen.innerHTML = `<option value="">${App.t('cr.js.select_source') || '-- Select source --'}</option>` + options;
            }
            if (selectClose) {
                selectClose.innerHTML = `<option value="">${App.t('cr.js.select_dest') || '-- select destination --'}</option>` + options;
            }
        }
    },

    bindEvents() {
        document.getElementById('formOpenSession').onsubmit = (e) => {
            e.preventDefault();
            this.openSession(new FormData(e.target));
        };

        document.getElementById('formCloseSession').onsubmit = (e) => {
            e.preventDefault();
            this.closeSession(new FormData(e.target));
        };

        const closeForm = document.getElementById('formCloseSession');
        if (closeForm) {
            const closingInput = closeForm.querySelector('input[name="closing_balance"]');
            const transferInput = closeForm.querySelector('input[name="transfer_amount"]');

            if (closingInput && transferInput) {
                closingInput.addEventListener('input', (e) => {
                    transferInput.value = e.target.value;
                });
            }
        }

        // Real-time update: Refresh accounts when modals are opened
        const modalOpen = document.getElementById('modalOpenSession');
        if (modalOpen) {
            modalOpen.addEventListener('show.bs.modal', () => this.loadAccounts());
        }

        const modalClose = document.getElementById('modalCloseSession');
        if (modalClose) {
            modalClose.addEventListener('show.bs.modal', () => this.loadAccounts());
        }
    },

    async checkStatus() {
        const container = document.getElementById('session-status-container');
        const closedTpl = document.getElementById('tpl-session-closed');
        const openTpl = document.getElementById('tpl-session-open');

        container.classList.remove('d-none');
        closedTpl.classList.add('d-none');
        openTpl.classList.add('d-none');

        const result = await App.api('cash_register.php?action=get_status');

        container.classList.add('d-none');

        if (result && result.status === 'open') {
            this.currentSession = result.session;
            openTpl.classList.remove('d-none');

            document.getElementById('session-user').textContent = this.currentSession.user_name || App.state.user.name;
            document.getElementById('session-start').textContent = App.formatDate(this.currentSession.opening_date) + ' ' + new Date(this.currentSession.opening_date).toLocaleTimeString();
            document.getElementById('session-opening-bal').textContent = App.formatCurrency(this.currentSession.opening_balance);
            document.getElementById('session-sales').textContent = App.formatCurrency(this.currentSession.current_total_sales || 0);
            document.getElementById('session-expected').textContent = App.formatCurrency(this.currentSession.expected_balance);

            document.getElementById('close-modal-expected').textContent = App.formatCurrency(this.currentSession.expected_balance);
        } else {
            this.currentSession = null;
            closedTpl.classList.remove('d-none');
        }
    },

    async openSession(formData) {
        const data = {
            opening_balance: formData.get('opening_balance'),
            account_id: formData.get('account_id')
        };

        const result = await App.api('cash_register.php?action=open_session', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('modalOpenSession')).hide();
            this.checkStatus();
            this.loadHistory();
            this.loadAccounts(); // Refresh balances
        }
    },

    async closeSession(formData) {
        const data = {
            closing_balance: formData.get('closing_balance'),
            transfer_amount: formData.get('transfer_amount'),
            account_id: formData.get('account_id'),
            notes: formData.get('notes')
        };

        const result = await App.api('cash_register.php?action=close_session', 'POST', data);
        if (result && result.success) {
            const diff = parseFloat(result.difference);
            let msg = App.t('cr.js.msg.close_success') || 'Register closed successfully.';
            if (diff !== 0) {
                msg += ` ${App.t('cr.js.msg.discrepancy') || 'Discrepancy of'} ${App.formatCurrency(diff)}`;
            }

            App.toast(diff === 0 ? 'success' : 'info', msg);
            bootstrap.Modal.getInstance(document.getElementById('modalCloseSession')).hide();
            this.checkStatus();
            this.loadHistory();
        }
    },

    async loadHistory() {
        if (this.table) {
            this.table.destroy();
        }

        const result = await App.api('cash_register.php?action=history');
        if (!result || !result.data) return;

        const tbody = document.querySelector('#sessionsTable tbody');
        tbody.innerHTML = result.data.map(row => {
            const diff = parseFloat(row.difference || 0);
            const diffClass = diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : 'text-muted');

            return `
                <tr>
                    <td data-order="${row.opening_date}">
                        <span class="fw-bold small d-block">${App.formatDate(row.opening_date)}</span>
                        <small class="text-muted">${new Date(row.opening_date).toLocaleTimeString()}</small>
                    </td>
                    <td data-order="${row.closing_date || '9999-12-31'}">
                        ${row.closing_date ? `
                            <span class="fw-bold small d-block">${App.formatDate(row.closing_date)}</span>
                            <small class="text-muted">${new Date(row.closing_date).toLocaleTimeString()}</small>
                        ` : `<span class="badge bg-teal-soft text-teal rounded-pill">${App.t('cr.js.status.in_progress') || 'In progress'}</span>`}
                    </td>
                    <td><small class="fw-medium">${row.user_name}</small></td>
                    <td class="small fw-bold" data-order="${row.expected_balance}">${App.formatCurrency(row.expected_balance)}</td>
                    <td class="small fw-bold" data-order="${row.closing_balance || 0}">${row.closing_balance ? App.formatCurrency(row.closing_balance) : '-'}</td>
                    <td class="small fw-bold ${diffClass}" data-order="${diff}">${row.closing_date ? App.formatCurrency(diff) : '-'}</td>
                    <td>
                        <span class="badge ${row.status === 'Open' ? 'bg-teal-soft text-teal' : 'bg-light text-muted'} rounded-pill">
                            ${row.status === 'Open' ? (App.t('cr.js.status.open') || 'Open') : (App.t('cr.js.status.closed') || 'Closed')}
                        </span>
                    </td>
                </tr>
            `;
        }).join('');

        this.table = $('#sessionsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            language: App.getDataTableLanguage(),
        });
    }
};

// Initialize
if (document.getElementById('sessionsTable')) {
    cash_registerModule.init();
}
window.cash_registerModule = cash_registerModule;
