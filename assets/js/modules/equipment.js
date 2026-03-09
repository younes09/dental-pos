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
                        if (data === 'Needs Repair') badgeClass = 'bg-danger';
                        if (data === 'Poor') badgeClass = 'bg-warning text-dark';
                        if (data === 'Fair') badgeClass = 'bg-info';
                        return `<span class="badge ${badgeClass}">${data}</span>`;
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

    resetForm() {
        $('#equipmentForm')[0].reset();
        $('#equipmentId').val('');
        $('#equipmentModalLabel').text('Add Equipment');
    },

    edit(data) {
        this.resetForm();
        $('#equipmentId').val(data.id);
        $('#equipmentName').val(data.name);
        $('#equipmentPrice').val(data.purchase_price);
        $('#equipmentQuantity').val(data.quantity);
        $('#equipmentCondition').val(data.condition_status);
        $('#equipmentModalLabel').text('Edit Equipment');
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
                App.toast('error', result.error || 'Failed to save equipment');
            }
        } catch (error) {
            console.error('Save Error:', error);
            App.toast('error', 'Network error');
        }
    },

    async delete(id) {
        const confirmed = await Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        });

        if (confirmed.isConfirmed) {
            try {
                const response = await fetch(`api/equipment.php?action=delete&id=${id}`);
                const result = await response.json();

                if (result.success) {
                    App.toast('success', result.success);
                    this.table.ajax.reload();
                } else {
                    App.toast('error', result.error || 'Failed to delete');
                }
            } catch (error) {
                console.error('Delete Error:', error);
                App.toast('error', 'Network error');
            }
        }
    }
};

// Auto-initialize when file is loaded if view is active
if (document.getElementById('equipmentTable')) {
    equipmentModule.init();
}

window.equipmentModule = equipmentModule;
