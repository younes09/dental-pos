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
    },

    initDataTable() {
        this.table = $('#customersTable').DataTable({
            ajax: 'api/customers.php?action=list',
            columns: [
                { data: 'name', className: 'fw-semibold' },
                { data: 'phone', render: (data) => data || '<span class="text-muted">N/A</span>' },
                { data: 'email', render: (data) => data || '<span class="text-muted">N/A</span>' },
                {
                    data: 'balance',
                    render: (data) => {
                        const val = parseFloat(data);
                        const color = val > 0 ? 'danger' : 'success';
                        return `<span class="fw-bold text-${color}">$${val.toFixed(2)}</span>`;
                    }
                },
                { data: 'loyalty_points', render: (data) => `<span class="badge bg-info-subtle text-info px-3">${data} pts</span>` },
                {
                    data: 'last_visit',
                    render: (data) => data ? new Date(data).toLocaleDateString() : '<span class="text-muted">Never</span>'
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

    viewHistory(id) {
        App.toast('info', 'Purchase history view coming soon');
    }
};

// Initialize
if (document.getElementById('customersTable')) {
    customersModule.init();
}

window.customersModule = customersModule;
