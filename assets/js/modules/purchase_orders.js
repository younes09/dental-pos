/**
 * Purchase Orders Module
 */

const purchase_ordersModule = {
    table: null,
    products: [],
    suppliers: [],

    async init() {
        this.initDataTable();
        await this.loadMeta();
        this.bindEvents();

        // Handle shortcut from other modules (e.g. Suppliers)
        const params = new URLSearchParams(window.location.hash.split('?')[1]);
        const supplierId = params.get('supplier_id');
        if (supplierId) {
            this.createForSupplier(supplierId);
        }

        // Default date to today
        document.getElementById('po-date').valueAsDate = new Date();

        console.log('Purchase Orders Module Loaded');
    },

    bindEvents() {
        document.getElementById('btn-add-item').onclick = () => this.addItemRow();

        document.getElementById('poForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.savePO();
        };

        // Toggle purchase type in create modal based on status
        document.getElementById('po-status').onchange = (e) => {
            const container = document.getElementById('po-purchase-type-container');
            if (e.target.value === 'Received') {
                container.classList.remove('d-none');
                document.getElementById('po-purchase-type').required = true;
            } else {
                container.classList.add('d-none');
                document.getElementById('po-purchase-type').required = false;
            }
        };

        // Handle receive PO form submission
        document.getElementById('receivePoForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.submitReceivePO();
        };

        // Delegate row calculations and removals
        document.querySelector('#po-items-table tbody').addEventListener('input', (e) => {
            if (e.target.classList.contains('item-qty') || e.target.classList.contains('item-cost')) {
                this.calculateRowTotal(e.target.closest('tr'));
                this.calculateOrderTotal();
            }
        });

        document.querySelector('#po-items-table tbody').addEventListener('click', (e) => {
            if (e.target.closest('.btn-remove-item')) {
                e.target.closest('tr').remove();
                this.calculateOrderTotal();
            }
        });
    },

    initDataTable() {
        this.table = $('#poTable').DataTable({
            ajax: 'api/purchase_orders.php?action=list',
            columns: [
                {
                    data: 'id',
                    render: (data) => `<span class="fw-bold">#PO-${data}</span>`
                },
                { data: 'date' },
                {
                    data: 'supplier_name',
                    render: (data, type, row) => `
                        <div class="fw-medium">${data}</div>
                        <small class="text-muted small">${row.supplier_company || ''}</small>
                    `
                },
                {
                    data: 'total',
                    render: (data) => `<span class="fw-bold text-navy">${App.formatCurrency(data)}</span>`
                },
                {
                    data: 'status',
                    render: (data) => {
                        const badges = {
                            'Pending': 'bg-warning-subtle text-warning',
                            'Received': 'bg-success-subtle text-success',
                            'Partial': 'bg-info-subtle text-info',
                            'Cancelled': 'bg-danger-subtle text-danger'
                        };
                        return `<span class="badge ${badges[data] || 'bg-secondary'} px-3 rounded-pill">${data}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => {
                        let receiveBtn = '';
                        if (data.status !== 'Received' && data.status !== 'Cancelled') {
                            receiveBtn = `<li><a class="dropdown-item text-success" href="javascript:void(0)" onclick="purchase_ordersModule.openReceiveModal(${data.id})"><i class="fas fa-check-circle me-2"></i>Receive Order</a></li>`;
                        }

                        return `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="purchase_ordersModule.viewDetails(${data.id})"><i class="fas fa-eye me-2 text-info"></i>View Details</a></li>
                                ${receiveBtn}
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="purchase_ordersModule.deletePO(${data.id})"><i class="fas fa-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                        `;
                    }
                }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search orders...",
                lengthMenu: "_MENU_",
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            drawCallback: () => this.updateStats()
        });
    },

    async loadMeta() {
        // Load suppliers for dropdown
        const supplierRes = await App.api('purchase_orders.php?action=get_suppliers');
        if (supplierRes) {
            this.suppliers = supplierRes.data;
            const select = document.getElementById('po-supplier-select');
            select.innerHTML = '<option value="">Select Supplier</option>' +
                this.suppliers.map(s => `<option value="${s.id}">${s.name} (${s.company})</option>`).join('');
        }

        // Load products for item selectors
        const productRes = await App.api('purchase_orders.php?action=get_products');
        if (productRes) {
            this.products = productRes.data;
        }
    },

    addItemRow() {
        const tbody = document.querySelector('#po-items-table tbody');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-select form-select-sm item-product" required>
                    <option value="">Select Product</option>
                    ${this.products.map(p => `<option value="${p.id}" data-price="${p.purchase_price}">${p.name}</option>`).join('')}
                </select>
            </td>
            <td><input type="number" class="form-control form-control-sm item-qty" value="1" min="1" required></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm item-cost" value="0.00" required></td>
            <td class="item-total fw-bold text-navy">${App.formatCurrency(0)}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm text-danger btn-remove-item">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;

        // Update cost when product changes
        row.querySelector('.item-product').onchange = (e) => {
            const option = e.target.selectedOptions[0];
            const price = option ? option.dataset.price : 0;
            row.querySelector('.item-cost').value = price;
            this.calculateRowTotal(row);
            this.calculateOrderTotal();
        };

        tbody.appendChild(row);
    },

    calculateRowTotal(row) {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
        const total = qty * cost;
        row.querySelector('.item-total').textContent = App.formatCurrency(total);
    },

    calculateOrderTotal() {
        let total = 0;
        document.querySelectorAll('#po-items-table tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
            total += qty * cost;
        });
        document.getElementById('po-total-display').textContent = App.formatCurrency(total);
    },

    async savePO() {
        const supplier_id = document.getElementById('po-supplier-select').value;
        const date = document.getElementById('po-date').value;
        const status = document.getElementById('po-status').value;
        const items = [];
        let total = 0;

        document.querySelectorAll('#po-items-table tbody tr').forEach(row => {
            const productId = row.querySelector('.item-product').value;
            const qty = parseFloat(row.querySelector('.item-qty').value);
            const cost = parseFloat(row.querySelector('.item-cost').value);

            if (productId && qty > 0) {
                items.push({
                    product_id: productId,
                    qty: qty,
                    unit_cost: cost
                });
                total += qty * cost;
            }
        });

        if (items.length === 0) {
            App.toast('error', 'Please add at least one product');
            return;
        }

        const data = {
            supplier_id,
            date,
            status,
            total,
            items
        };

        if (status === 'Received') {
            data.purchase_type = document.getElementById('po-purchase-type').value;
        }

        const result = await App.api('purchase_orders.php?action=save', 'POST', data);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('poModal')).hide();
            this.table.ajax.reload();
            document.getElementById('poForm').reset();

            // Reset fields
            document.getElementById('po-status').value = 'Pending';
            document.getElementById('po-purchase-type-container').classList.add('d-none');
            document.getElementById('po-purchase-type').required = false;

            document.querySelector('#po-items-table tbody').innerHTML = '';
            document.getElementById('po-total-display').textContent = App.formatCurrency(0);
        }
    },

    async deletePO(id) {
        const confirm = await Swal.fire({
            title: 'Delete Purchase Order?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`purchase_orders.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            }
        }
    },

    openReceiveModal(id) {
        document.getElementById('receive-po-id').value = id;
        document.getElementById('receivePoForm').reset();
        new bootstrap.Modal(document.getElementById('receivePoModal')).show();
    },

    async submitReceivePO() {
        const id = document.getElementById('receive-po-id').value;
        const purchaseType = document.getElementById('receive-po-purchase-type').value;

        if (!id || !purchaseType) return;

        const data = {
            po_id: id,
            purchase_type: purchaseType
        };

        const result = await App.api('purchase_orders.php?action=receive_order', 'POST', data);

        if (result && result.success) {
            App.toast('success', 'Purchase order received successfully!');
            bootstrap.Modal.getInstance(document.getElementById('receivePoModal')).hide();
            this.table.ajax.reload();
        } else if (result && result.error) {
            App.toast('error', result.error);
        }
    },

    updateStats() {
        if (!this.table) return; // Guard against null during initialization
        const data = this.table.rows().data().toArray();
        const totalCount = data.length;
        const pendingCount = data.filter(d => d.status === 'Pending').length;
        const receivedCount = data.filter(d => d.status === 'Received').length;
        const totalValue = data.reduce((sum, d) => sum + parseFloat(d.total), 0);

        document.getElementById('stat-total-po').textContent = totalCount;
        document.getElementById('stat-pending-po').textContent = pendingCount;
        document.getElementById('stat-received-po').textContent = receivedCount;
        document.getElementById('stat-total-value').textContent = App.formatCurrency(totalValue);
    },

    async viewDetails(id) {
        const result = await App.api(`purchase_orders.php?action=get_details&id=${id}`);
        if (!result) return;

        const { order, items } = result;

        // Populate Modal Header
        document.getElementById('po-details-title').textContent = `Purchase Order #PO-${order.id}`;
        document.getElementById('po-details-subtitle').textContent = `Status: ${order.status}`;

        // Populate Supplier Info
        document.getElementById('po-details-supplier-info').innerHTML = `
            <div class="fw-bold fs-5 text-teal mb-1">${order.supplier_name}</div>
            <div class="text-muted small mb-2"><i class="fas fa-building me-2"></i>${order.supplier_company || 'N/A'}</div>
            <div class="small mb-1"><i class="fas fa-phone me-2"></i>${order.supplier_phone || 'N/A'}</div>
            <div class="small"><i class="fas fa-envelope me-2"></i>${order.supplier_email || 'N/A'}</div>
        `;

        // Populate Order Metadata
        document.getElementById('po-details-meta-info').innerHTML = `
            <div class="d-flex justify-content-between mb-2">
                <span>Order Date:</span>
                <span class="fw-bold">${order.date}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Created At:</span>
                <span class="fw-bold">${new Date(order.created_at).toLocaleString()}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>Current Status:</span>
                <span class="badge ${order.status === 'Received' ? 'bg-success' : 'bg-warning'} px-2">${order.status}</span>
            </div>
        `;

        // Populate Item Table
        const tbody = document.querySelector('#po-details-items-table tbody');
        tbody.innerHTML = items.map(item => `
            <tr>
                <td>
                    <div class="fw-medium">${item.product_name}</div>
                </td>
                <td><small class="text-muted">${item.barcode || 'N/A'}</small></td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">${App.formatCurrency(item.unit_cost)}</td>
                <td class="text-end fw-bold text-navy">${App.formatCurrency(item.qty * item.unit_cost)}</td>
            </tr>
        `).join('');

        document.getElementById('po-details-grand-total').textContent = App.formatCurrency(order.total);

        // Show Modal
        new bootstrap.Modal(document.getElementById('poDetailsModal')).show();
    },

    // Shortcut from Suppliers module
    createForSupplier(supplierId) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('poModal')).show();
        document.getElementById('po-supplier-select').value = supplierId;
    }
};

// Initialize
if (document.getElementById('poTable')) {
    purchase_ordersModule.init();
}

window.purchase_ordersModule = purchase_ordersModule;
