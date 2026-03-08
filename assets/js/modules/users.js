/**
 * User Management Module
 */

const usersModule = {
    table: null,

    init() {
        this.initDataTable();
        this.bindEvents();
        console.log('Users Module Loaded');
    },

    bindEvents() {
        // Add User Button
        document.getElementById('btn-add-user').onclick = () => {
            document.getElementById('userForm').reset();
            document.getElementById('user-id').value = '';
            document.getElementById('user-password').placeholder = 'Set account password';
            document.getElementById('user-password').required = true;
            document.getElementById('userModalLabel').textContent = 'Add New User';
            new bootstrap.Modal(document.getElementById('userModal')).show();
        };

        // Form Submit
        document.getElementById('userForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveUser();
        };
    },

    initDataTable() {
        this.table = $('#usersTable').DataTable({
            ajax: 'api/users.php?action=list',
            columns: [
                {
                    data: null,
                    render: (data) => `
                        <div class="d-flex align-items-center">
                            <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(data.name)}&background=00BFA6&color=fff" class="rounded-circle me-3" width="40">
                            <div>
                                <h6 class="mb-0 fw-bold">${data.name}</h6>
                                <small class="text-muted">${data.email}</small>
                            </div>
                        </div>
                    `
                },
                {
                    data: 'role',
                    render: (data) => {
                        let color = 'secondary';
                        if (data === 'Admin') color = 'primary';
                        if (data === 'Stock Manager') color = 'info';
                        return `<span class="badge bg-${color}-subtle text-${color} px-3">${data}</span>`;
                    }
                },
                {
                    data: 'phone',
                    render: (data) => data || '<span class="text-muted small">Not provided</span>'
                },
                {
                    data: 'status',
                    render: (data) => `
                        <span class="badge bg-${data === 'Active' ? 'success' : 'danger'}-subtle text-${data === 'Active' ? 'success' : 'danger'} px-3">
                            <i class="fas fa-circle me-1 small"></i>${data}
                        </span>
                    `
                },
                {
                    data: 'created_at',
                    render: (data) => `<small class="text-muted">${new Date(data).toLocaleDateString()}</small>`
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-end',
                    render: (data) => `
                        <button class="btn btn-sm btn-light-primary me-2" onclick="usersModule.editUser(${data.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-light-danger" onclick="usersModule.deleteUser(${data.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `
                }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search users...",
                lengthMenu: "_MENU_",
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    async saveUser() {
        const form = document.getElementById('userForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/users.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                this.table.ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', 'Failed to save user');
        }
    },

    editUser(id) {
        const user = this.table.rows().data().toArray().find(u => u.id == id);
        if (!user) return;

        document.getElementById('user-id').value = user.id;
        document.querySelector('[name="name"]').value = user.name;
        document.querySelector('[name="email"]').value = user.email;
        document.querySelector('[name="phone"]').value = user.phone || '';
        document.querySelector('[name="role"]').value = user.role;
        document.querySelector('[name="status"]').value = user.status;
        document.getElementById('user-password').value = '';
        document.getElementById('user-password').placeholder = 'Leave blank to keep current';
        document.getElementById('user-password').required = false;

        document.getElementById('userModalLabel').textContent = 'Edit User Account';
        new bootstrap.Modal(document.getElementById('userModal')).show();
    },

    async deleteUser(id) {
        const confirm = await Swal.fire({
            title: 'Delete User?',
            text: "This user will lose all system access!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete!'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`users.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            }
        }
    }
};

// Initialize
if (document.getElementById('usersTable')) {
    usersModule.init();
}

window.usersModule = usersModule;
