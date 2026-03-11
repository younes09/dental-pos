/**
 * Salary Management Module
 */
const salariesModule = {
    staffTable: null,
    paymentsTable: null,
    staff: [],
    accounts: [],

    init() {
        console.log('Salaries Module Initialized');
        this.loadStaff();
        this.loadAccounts();
        this.initPaymentsTable();
        this.bindEvents();
    },

    setPaymentDefaults() {
        const today = new Date().toISOString().split('T')[0];
        const form = document.getElementById('paymentForm');
        form.querySelector('[name="payment_date"]').value = today;
        form.querySelector('[name="period_year"]').value = new Date().getFullYear();
        form.querySelector('[name="period_month"]').value = new Date().getMonth() + 1;
    },

    bindEvents() {
        // Staff Form
        document.getElementById('staffForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveStaff();
        });

        // Payment Form
        document.getElementById('paymentForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.savePayment();
        });

        // Update amount when staff is selected
        document.getElementById('paymentStaffSelect').addEventListener('change', (e) => {
            const staffId = e.target.value;
            const staffMember = this.staff.find(s => s.id == staffId);
            if (staffMember) {
                document.getElementById('paymentAmountInput').value = staffMember.base_salary;
            }
        });

        // Reset forms on modal hide and set defaults
        ['staffModal', 'paymentModal'].forEach(id => {
            const modalEl = document.getElementById(id);
            modalEl.addEventListener('hidden.bs.modal', () => {
                document.getElementById(id).querySelector('form').reset();
                if (id === 'staffModal') document.getElementById('staffId').value = '';
            });

            if (id === 'paymentModal') {
                modalEl.addEventListener('show.bs.modal', () => {
                    this.setPaymentDefaults();
                });
            }
        });

        // Refresh tables on tab switch
        document.getElementById('payments-tab').addEventListener('shown.bs.tab', () => {
            this.loadPayments();
        });
    },

    async loadStaff() {
        const result = await App.api('salaries.php?action=list_staff');
        if (result && result.data) {
            this.staff = result.data;
            this.renderStaffTable();
            this.updateStaffDropdown();
        }
    },

    renderStaffTable() {
        if ($.fn.DataTable.isDataTable('#staffTable')) {
            $('#staffTable').DataTable().destroy();
        }

        const tbody = document.querySelector('#staffTable tbody');
        tbody.innerHTML = this.staff.map(s => `
            <tr>
                <td class="fw-bold">${s.name}</td>
                <td><span class="badge bg-navy-subtle text-navy border-0 rounded-pill px-3">${s.position || 'N/A'}</span></td>
                <td>${s.phone || '-'}</td>
                <td>${App.formatCurrency(s.base_salary)}</td>
                <td>
                    <span class="badge bg-${s.status === 'Active' ? 'success' : 'danger'}-subtle text-${s.status === 'Active' ? 'success' : 'danger'}">
                        ${s.status === 'Active' ? (App.t('salaries.js.status.active') || 'Active') : (App.t('salaries.js.status.inactive') || 'Inactive')}
                    </span>
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-light-primary rounded-pill me-2" onclick="salariesModule.editStaff(${s.id})" title="${App.t('salaries.js.btn_edit') || 'Edit'}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-light-danger rounded-pill" onclick="salariesModule.deleteStaff(${s.id})" title="${App.t('salaries.js.btn_delete') || 'Delete'}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        this.staffTable = $('#staffTable').DataTable({
            language: App.getDataTableLanguage(),
            pageLength: 10,
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
        });
    },

    updateStaffDropdown() {
        const select = document.getElementById('paymentStaffSelect');
        const currentValue = select.value;
        const activeStaff = this.staff.filter(s => s.status === 'Active');

        select.innerHTML = `<option value="">${App.t('salaries.js.choose') || '-- Choose --'}</option>` +
            activeStaff.map(s => {
                const position = s.position ? `(${s.position})` : '';
                return `<option value="${s.id}">${s.name} ${position}</option>`;
            }).join('');

        select.value = currentValue;
    },

    async loadAccounts() {
        const result = await App.api('vault.php?action=list_accounts');
        if (result && result.data) {
            this.accounts = result.data;
            const select = document.getElementById('paymentVaultSelect');
            select.innerHTML = `<option value="">${App.t('salaries.js.choose') || '-- Choose --'}</option>` +
                this.accounts.map(a =>
                    `<option value="${a.id}">${a.name} (${App.formatCurrency(a.balance)})</option>`
                ).join('');
        }
    },

    async saveStaff() {
        const form = document.getElementById('staffForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await App.api('salaries.php?action=save_staff', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('staffModal')).hide();
            this.loadStaff();
        }
    },

    editStaff(id) {
        const staffMember = this.staff.find(s => s.id == id);
        if (staffMember) {
            const form = document.getElementById('staffForm');
            document.getElementById('staffId').value = staffMember.id;
            form.querySelector('[name="name"]').value = staffMember.name;
            form.querySelector('[name="position"]').value = staffMember.position || '';
            form.querySelector('[name="phone"]').value = staffMember.phone || '';
            form.querySelector('[name="email"]').value = staffMember.email || '';
            form.querySelector('[name="base_salary"]').value = staffMember.base_salary;
            form.querySelector('[name="status"]').value = staffMember.status;
            form.querySelector('[name="hiring_date"]').value = staffMember.hiring_date || '';

            const modalEl = document.getElementById('staffModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    },

    async deleteStaff(id) {
        const confirmed = await Swal.fire({
            title: App.t('salaries.js.delete_staff_title') || 'Are you sure?',
            text: App.t('salaries.js.delete_staff_text') || "This action will also delete this member's history.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('salaries.js.btn_yes') || 'Yes, delete!',
            cancelButtonText: App.t('salaries.js.btn_cancel') || 'Cancel'
        });

        if (confirmed.isConfirmed) {
            const result = await App.api(`salaries.php?action=delete_staff&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.loadStaff();
            }
        }
    },

    initPaymentsTable() {
        if ($.fn.DataTable.isDataTable('#paymentsTable')) {
            $('#paymentsTable').DataTable().destroy();
        }
        this.paymentsTable = $('#paymentsTable').DataTable({
            language: App.getDataTableLanguage(),
            order: [[0, 'desc']],
            columns: [
                { data: 'payment_date' },
                { data: 'staff_name', className: 'fw-bold' },
                {
                    data: null,
                    render: (data) => {
                        const months = [
                            App.t('salaries.js.months.jan') || "Jan", App.t('salaries.js.months.feb') || "Feb", App.t('salaries.js.months.mar') || "Mar",
                            App.t('salaries.js.months.apr') || "Apr", App.t('salaries.js.months.may') || "May", App.t('salaries.js.months.jun') || "Jun",
                            App.t('salaries.js.months.jul') || "Jul", App.t('salaries.js.months.aug') || "Aug", App.t('salaries.js.months.sep') || "Sep",
                            App.t('salaries.js.months.oct') || "Oct", App.t('salaries.js.months.nov') || "Nov", App.t('salaries.js.months.dec') || "Dec"
                        ];
                        return `${months[data.period_month - 1]} ${data.period_year}`;
                    }
                },
                {
                    data: 'amount',
                    render: (data) => App.formatCurrency(data)
                },
                { data: 'account_name' },
                {
                    data: 'payment_method',
                    render: (data) => `<span class="badge bg-teal-subtle text-teal border-0 rounded-pill px-3">${data}</span>`
                },
                {
                    data: 'notes',
                    render: (data) => data ? `<i class="fas fa-info-circle text-muted" title="${data}"></i>` : '-'
                },
                {
                    data: 'id',
                    className: 'text-end',
                    render: (data) => `
                        <button class="btn btn-light-danger rounded-pill px-2 py-1" onclick="salariesModule.deletePayment(${data})" title="${App.t('salaries.js.delete_pay_btn_title') || 'Delete payment'}">
                            <i class="fas fa-trash fa-xs"></i>
                        </button>
                    `
                }
            ]
        });
    },

    async loadPayments() {
        const result = await App.api('salaries.php?action=list_payments');
        if (result && result.data) {
            this.paymentsTable.clear().rows.add(result.data).draw();
        }
    },

    async savePayment() {
        const form = document.getElementById('paymentForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await App.api('salaries.php?action=add_payment', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            this.loadPayments();
            this.loadAccounts(); // Refresh account balances
        }
    },

    async deletePayment(id) {
        const confirmed = await Swal.fire({
            title: App.t('salaries.js.delete_pay_title') || 'Sure you want to delete this payment?',
            text: App.t('salaries.js.delete_pay_text') || "This action does not cancel the treasury transaction.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('salaries.js.btn_yes_del') || 'Yes, delete',
            cancelButtonText: App.t('salaries.js.btn_cancel') || 'Cancel'
        });

        if (confirmed.isConfirmed) {
            const result = await App.api(`salaries.php?action=delete_payment&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.loadPayments();
            }
        }
    }
};

// Initialize module
if (document.getElementById('staffTable')) {
    salariesModule.init();
}
window.salariesModule = salariesModule;
