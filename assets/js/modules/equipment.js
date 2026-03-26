/**
 * Equipment Management Module
 */
const equipmentModule = {
    table: null,

    init() {
        console.log('Initializing Equipment Module');
        this.loadTable();
        this.resetForm();
    },

    loadTable() {
        if ($.fn.DataTable.isDataTable('#equipmentTable')) {
            $('#equipmentTable').DataTable().destroy();
        }

        this.table = $('#equipmentTable').DataTable({
            ajax: 'api/equipment.php?action=list',
            columns: [
                { data: 'id' },
                { data: 'name', className: 'fw-bold' },
                {
                    data: 'purchase_price',
                    render: (data) => App.formatCurrency(data)
                },
                {
                    data: 'condition_status',
                    render: (data) => {
                        let badgeClass = 'bg-success';
                        let label = App.t('equipment.condition.new') || 'New';
                        if (data === 'Needs Repair') { badgeClass = 'bg-danger'; label = App.t('equipment.condition.repair') || 'Needs Repair'; }
                        else if (data === 'Poor') { badgeClass = 'bg-warning text-dark'; label = App.t('equipment.condition.poor') || 'Poor'; }
                        else if (data === 'Fair') { badgeClass = 'bg-info'; label = App.t('equipment.condition.fair') || 'Fair'; }
                        else if (data === 'Good') { label = App.t('equipment.condition.good') || 'Good'; }
                        return `<span class="badge ${badgeClass}">${label}</span>`;
                    }
                },
                { data: 'quantity' },
                {
                    data: 'created_at',
                    render: (data) => App.formatDate(data)
                },
                {
                    data: null,
                    className: 'text-end',
                    render: (data) => {
                        return `
                            <button class="btn btn-sm btn-light-primary me-1" onclick="equipmentModule.edit(${JSON.stringify(data).replace(/"/g, '&quot;')})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-light-danger" onclick="equipmentModule.delete(${data.id})" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        `;
                    }
                }
            ],
            language: App.getDataTableLanguage(),
            order: [[0, 'desc']],
            drawCallback: function (settings) {
                const api = this.api();
                const totalItems = api.data().length;
                let totalValue = 0;

                api.data().each(function (row) {
                    totalValue += parseFloat(row.purchase_price) * parseInt(row.quantity);
                });

                $('#totalEquipment').text(totalItems);
                $('#totalValue').text(totalValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
        });
    },

    async resetForm() {
        $('#equipmentForm')[0].reset();
        $('#equipmentId').val('');
        $('#equipmentModalLabel').text(App.t('equipment.modal.add_title') || 'Add Equipment');
        $('#equipment-account-container').removeClass('d-none');

        // Load accounts
        const accountRes = await App.api('vault.php?action=list_accounts');
        if (accountRes && accountRes.data) {
            const select = document.getElementById('equipment-account-id');
            select.innerHTML = `<option value="">-- Select account --</option>` +
                accountRes.data.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');
        }
    },

    edit(data) {
        this.resetForm();
        $('#equipmentId').val(data.id);
        $('#equipmentName').val(data.name);
        $('#equipmentPrice').val(data.purchase_price);
        $('#equipmentQuantity').val(data.quantity);
        $('#equipmentCondition').val(data.condition_status);
        $('#equipmentModalLabel').text(App.t('equipment.js.edit_title') || 'Edit Equipment');
        $('#equipment-account-container').addClass('d-none'); // Hide for edits
        $('#equipmentModal').modal('show');
    },

    async save() {
        const form = document.getElementById('equipmentForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);

        try {
            const response = await fetch('api/equipment.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                $('#equipmentModal').modal('hide');
                this.table.ajax.reload();
            } else {
                App.toast('error', result.error || App.t('equipment.js.save_fail') || 'Failed to save equipment');
            }
        } catch (error) {
            console.error('Save Error:', error);
            App.toast('error', App.t('equipment.js.network_error') || 'Network error');
        }
    },

    async delete(id) {
        const confirmed = await Swal.fire({
            title: App.t('equipment.js.delete_title') || 'Are you sure?',
            text: App.t('equipment.js.delete_text') || "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('equipment.js.btn_yes') || 'Yes, delete it!',
            cancelButtonText: App.t('users.js.btn_cancel') || 'Cancel'
        });

        if (confirmed.isConfirmed) {
            try {
                const response = await fetch(`api/equipment.php?action=delete&id=${id}`);
                const result = await response.json();

                if (result.success) {
                    App.toast('success', result.success);
                    this.table.ajax.reload();
                } else {
                    App.toast('error', result.error || App.t('equipment.js.delete_fail') || 'Failed to delete');
                }
            } catch (error) {
                console.error('Delete Error:', error);
                App.toast('error', App.t('equipment.js.network_error') || 'Network error');
            }
        }
    }
};

// Auto-initialize when file is loaded if view is active
if (document.getElementById('equipmentTable')) {
    equipmentModule.init();
}

window.equipmentModule = equipmentModule;
