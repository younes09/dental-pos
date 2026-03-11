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
            const paidContainer = document.getElementById('po-paid-amount-container');
            const accountContainer = document.getElementById('po-account-container');

            if (e.target.value === 'Received') {
                container.classList.remove('d-none');
                document.getElementById('po-purchase-type').required = true;
                paidContainer.classList.remove('d-none');
                accountContainer.classList.remove('d-none');
            } else {
                container.classList.add('d-none');
                document.getElementById('po-purchase-type').required = false;
                paidContainer.classList.add('d-none');
                accountContainer.classList.add('d-none');
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

        document.getElementById('btn-confirm-purchase-return').onclick = () => {
            this.confirmPurchaseReturn();
        };
    },

    initDataTable() {
        this.table = $('#poTable').DataTable({
            ajax: 'api/purchase_orders.php?action=list',
            columns: [
                {
                    data: 'id',
                    render: (data, type) => {
                        if (type === 'display') {
                            return `<span class="fw-bold">#PO-${data}</span>`;
                        }
                        return data;
                    }
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
                    type: 'num',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            return `<span class="fw-bold text-navy">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
                },
                {
                    data: 'paid_amount',
                    type: 'num',
                    render: (data, type) => {
                        const val = parseFloat(data || 0);
                        if (type === 'display') {
                            return `<span class="fw-bold text-success">${App.formatCurrency(val)}</span>`;
                        }
                        return val;
                    }
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
                        const displayStatus = data ? App.t(`po.js.${data.toLowerCase()}`) : data;
                        return `<span class="badge ${badges[data] || 'bg-secondary'} px-3 rounded-pill">${displayStatus}</span>`;
                    }
                },
                {
                    data: 'payment_status',
                    render: (data) => {
                        const badges = {
                            'Unpaid': 'bg-danger-subtle text-danger',
                            'Paid': 'bg-success-subtle text-success',
                            'Partial': 'bg-info-subtle text-info'
                        };
                        const ds = data || 'Unpaid';
                        const displayStatus = App.t(`po.js.${ds.toLowerCase()}`);
                        return `<span class="badge ${badges[data] || 'bg-secondary'} px-3 rounded-pill">${displayStatus}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => {
                        let receiveBtn = '';
                        if (data.status !== 'Received' && data.status !== 'Cancelled') {
                            receiveBtn = `<li><a class="dropdown-item text-success" href="javascript:void(0)" onclick="purchase_ordersModule.openReceiveModal(${data.id})"><i class="fas fa-check-circle me-2"></i>${App.t('po.js.status_receive')}</a></li>`;
                        }

                        return `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="purchase_ordersModule.viewDetails(${data.id})"><i class="fas fa-eye me-2 text-info"></i>${App.t('po.js.status_view')}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="purchase_ordersModule.printBC(${data.id})"><i class="fas fa-print me-2 text-primary"></i>${App.t('po.js.btn_print_bc')}</a></li>
                                ${receiveBtn}
                                ${data.status === 'Received' || data.status === 'Partial' ? `
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="purchase_ordersModule.openReturnModal(${data.id})"><i class="fas fa-undo me-2 text-warning"></i>${App.t('po.js.status_return')}</a></li>
                                ` : ''}
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="purchase_ordersModule.deletePO(${data.id})"><i class="fas fa-trash me-2"></i>${App.t('po.js.status_del')}</a></li>
                            </ul>
                        </div>
                        `;
                    }
                }
            ],
            language: App.getDataTableLanguage(),
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
            select.innerHTML = `<option value="">${App.t('po.modal.supplier_select')}</option>` +
                this.suppliers.map(s => `<option value="${s.id}">${s.name} (${s.company})</option>`).join('');
        }

        // Load products for item selectors
        const productRes = await App.api('purchase_orders.php?action=get_products');
        if (productRes) {
            this.products = productRes.data;
        }

        // Load accounts for vault integration
        const accountRes = await App.api('vault.php?action=list_accounts');
        if (accountRes && accountRes.data) {
            const options = `<option value="">${App.t('po.modal.account_no')}</option>` +
                accountRes.data.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');

            const createSelect = document.getElementById('po-account-id');
            if (createSelect) createSelect.innerHTML = options;

            const receiveSelect = document.getElementById('receive-po-account-id');
            if (receiveSelect) receiveSelect.innerHTML = options;
        }
    },

    addItemRow() {
        const tbody = document.querySelector('#po-items-table tbody');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <select class="form-select form-select-sm item-product" required>
                    <option value="">${App.t('po.js.opt_select_product')}</option>
                    ${this.products.map(p => {
            let colorStyle = '';
            if (p.stock_qty <= 0) {
                colorStyle = 'style="color: #dc3545; font-weight: bold;"'; // Red for Out of Stock
            } else if (p.stock_qty <= p.min_stock) {
                colorStyle = 'style="color: #ffc107; font-weight: bold;"'; // Yellow for Low Stock
            }
            return `<option value="${p.id}" ${colorStyle} data-price="${p.purchase_price}">${p.name} (${App.t('po.js.stock')}: ${p.stock_qty})</option>`;
        }).join('')}
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
            App.toast('error', App.t('po.js.error_no_items'));
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
            data.paid_amount = document.getElementById('po-paid-amount').value || 0;
            data.account_id = document.getElementById('po-account-id').value;
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
            document.getElementById('po-paid-amount-container').classList.add('d-none');
            document.getElementById('po-paid-amount').value = '';
            document.getElementById('po-account-container').classList.add('d-none');
            document.getElementById('po-account-id').value = '';

            document.querySelector('#po-items-table tbody').innerHTML = '';
            document.getElementById('po-total-display').textContent = App.formatCurrency(0);
        }
    },

    async deletePO(id) {
        const confirm = await Swal.fire({
            title: App.t('po.js.del_title'),
            text: App.t('po.js.del_text'),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: App.t('po.js.btn_del'),
            cancelButtonText: App.t('po.modal.btn_cancel')
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`purchase_orders.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            }
        }
    },

    async openReceiveModal(id) {
        document.getElementById('receive-po-id').value = id;
        document.getElementById('receivePoForm').reset();

        // Fetch details to populate the items table
        const result = await App.api(`purchase_orders.php?action=get_details&id=${id}`);
        const tbody = document.querySelector('#receive-po-items-table tbody');

        if (!result || !result.items) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${App.t('po.js.load_err')}</td></tr>`;
            return;
        }

        tbody.innerHTML = result.items.map(item => {
            const remaining = item.qty - (item.received_qty || 0);

            if (remaining <= 0) {
                return `
                    <tr class="bg-light opacity-75">
                        <td>${item.product_name} <br><small class="text-muted">${App.t('po.js.barcode')}: ${item.barcode || 'N/A'}</small></td>
                        <td class="text-center">${item.qty}</td>
                        <td class="text-center text-success"><i class="fas fa-check-circle me-1"></i>${item.received_qty}</td>
                        <td class="text-center"><span class="badge bg-success">${App.t('po.js.fully_received')}</span></td>
                    </tr>
                `;
            }

            return `
                <tr>
                    <td>${item.product_name} <br><small class="text-muted">${App.t('po.js.barcode')}: ${item.barcode || 'N/A'}</small></td>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-center">${item.received_qty || 0}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-center receive-item-input" 
                            data-item-id="${item.id}" 
                            data-product-id="${item.product_id}"
                            data-max="${remaining}" 
                            max="${remaining}" 
                            min="0" 
                            value="${remaining}">
                        <div class="invalid-feedback" style="font-size: 0.7rem;">${App.t('po.js.max')}: ${remaining}</div>
                    </td>
                </tr>
            `;
        }).join('');

        // Add validation styling on input
        document.querySelectorAll('.receive-item-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const max = parseInt(e.target.dataset.max);
                const val = parseInt(e.target.value) || 0;
                if (val > max || val < 0) {
                    e.target.classList.add('is-invalid');
                } else {
                    e.target.classList.remove('is-invalid');
                }
            });
        });

        new bootstrap.Modal(document.getElementById('receivePoModal')).show();
    },

    async submitReceivePO() {
        const id = document.getElementById('receive-po-id').value;
        const purchaseType = document.getElementById('receive-po-purchase-type').value;

        if (!id || !purchaseType) return;

        // Gather items
        const receivedItems = [];
        let hasErrors = false;
        let totalReceivingNow = 0;

        document.querySelectorAll('.receive-item-input').forEach(input => {
            const max = parseInt(input.dataset.max);
            const val = parseInt(input.value) || 0;

            if (val > max || val < 0) {
                input.classList.add('is-invalid');
                hasErrors = true;
            } else if (val > 0) {
                receivedItems.push({
                    item_id: input.dataset.itemId,
                    product_id: input.dataset.productId,
                    receiving_qty: val
                });
                totalReceivingNow += val;
            }
        });

        if (hasErrors) {
            App.toast('error', App.t('po.js.err_qty'));
            return;
        }

        if (totalReceivingNow === 0) {
            App.toast('warning', App.t('po.js.err_min_one'));
            return;
        }

        const paidAmount = document.getElementById('receive-po-paid-amount').value || 0;
        const accountId = document.getElementById('receive-po-account-id').value;

        const data = {
            po_id: id,
            purchase_type: purchaseType,
            paid_amount: paidAmount,
            account_id: accountId,
            items: receivedItems
        };

        const result = await App.api('purchase_orders.php?action=receive_order', 'POST', data);

        if (result && result.success) {
            App.toast('success', App.t('po.js.receive_success'));
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
        document.getElementById('po-details-title').textContent = `${App.t('po.details.title')} #PO-${order.id}`;
        document.getElementById('po-details-subtitle').textContent = `${App.t('po.modal.status_label')}: ${App.t(`po.js.${order.status.toLowerCase()}`)}`;

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
                <span>${App.t('po.js.order_date')}</span>
                <span class="fw-bold">${order.date}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>${App.t('po.js.created_at')}</span>
                <span class="fw-bold">${new Date(order.created_at).toLocaleString()}</span>
            </div>
            <div class="d-flex justify-content-between">
                <span>${App.t('po.js.current_status')}</span>
                <span class="badge ${order.status === 'Received' ? 'bg-success' : 'bg-warning'} px-2">${App.t(`po.js.${order.status.toLowerCase()}`)}</span>
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
                <td class="text-end">
                    ${App.formatCurrency(item.old_unit_cost)}
                    ${(parseFloat(item.unit_cost) !== parseFloat(item.old_unit_cost)) ? `<br><small class="text-danger">${App.t('po.js.changed')}</small>` : ''}
                </td>
                <td class="text-end">${App.formatCurrency(item.unit_cost)}</td>
                <td class="text-end fw-bold text-navy">${App.formatCurrency(item.qty * item.unit_cost)}</td>
            </tr>
        `).join('');

        document.getElementById('po-details-grand-total').textContent = App.formatCurrency(order.total);

        // Update footer buttons
        const footer = document.querySelector('#poDetailsModal .modal-footer');
        footer.innerHTML = `
            <button type="button" class="btn btn-primary px-4" onclick="purchase_ordersModule.printBC(${order.id})"><i class="fas fa-print me-2"></i>${App.t('po.js.btn_print_bc')}</button>
            <button type="button" class="btn btn-navy px-4" data-bs-dismiss="modal">${App.t('po.details.btn_close')}</button>
        `;

        // Show Modal
        new bootstrap.Modal(document.getElementById('poDetailsModal')).show();
    },

    async printBC(id) {
        const result = await App.api(`purchase_orders.php?action=get_details&id=${id}`);
        if (!result) return;

        const { order, items } = result;
        const settings = App.state.settings || {};

        const printWindow = window.open('views/bc_template.html', '_blank', 'width=900,height=800');

        // Wait for window to load then send data
        printWindow.onload = function () {
            printWindow.postMessage({
                type: 'POPULATE_BC',
                payload: { order, items, settings }
            }, window.location.origin);
        };
    },

    // Shortcut from Suppliers module
    createForSupplier(supplierId) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('poModal')).show();
        document.getElementById('po-supplier-select').value = supplierId;
    },

    async openReturnModal(id) {
        const result = await App.api(`purchase_orders.php?action=get_details&id=${id}`);
        if (!result) return;

        const { order, items } = result;
        document.getElementById('return-po-id').value = order.id;
        document.getElementById('purchase-return-subtitle').textContent = `${App.t('po.details.title')} #PO-${order.id} | ${order.supplier_name}`;
        document.getElementById('purchase-return-reason').value = '';

        const tbody = document.querySelector('#purchase-return-items-table tbody');
        tbody.innerHTML = items.map(item => {
            const maxReturnable = (item.received_qty || 0) - (item.returned_qty || 0);
            return `
                <tr>
                    <td>
                        <div class="fw-medium">${item.product_name}</div>
                        <small class="text-muted small">${item.barcode || ''}</small>
                    </td>
                    <td class="text-center">${item.received_qty || 0}</td>
                    <td class="text-center">${item.returned_qty || 0}</td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm purchase-return-qty-input text-center" 
                               data-product-id="${item.product_id}" 
                               data-cost="${item.unit_cost}"
                               max="${maxReturnable}" 
                               min="0" value="0" 
                               onchange="purchase_ordersModule.updatePurchaseReturnTotal()">
                    </td>
                    <td class="text-end fw-bold purchase-return-item-total">0.00 ${App.state.settings.currency || '$'}</td>
                </tr>
            `;
        }).join('');

        this.updatePurchaseReturnTotal();
        new bootstrap.Modal(document.getElementById('purchaseReturnModal')).show();
    },

    updatePurchaseReturnTotal() {
        let total = 0;
        document.querySelectorAll('.purchase-return-qty-input').forEach(input => {
            const qty = parseInt(input.value) || 0;
            const cost = parseFloat(input.dataset.cost);
            const itemTotal = qty * cost;
            total += itemTotal;
            input.closest('tr').querySelector('.purchase-return-item-total').textContent = App.formatCurrency(itemTotal);
        });
        document.getElementById('purchase-return-total').textContent = App.formatCurrency(total);
    },

    async confirmPurchaseReturn() {
        const poId = document.getElementById('return-po-id').value;
        const reason = document.getElementById('purchase-return-reason').value;
        const items = [];

        document.querySelectorAll('.purchase-return-qty-input').forEach(input => {
            const qty = parseInt(input.value) || 0;
            if (qty > 0) {
                items.push({
                    product_id: input.dataset.productId,
                    qty: qty
                });
            }
        });

        if (items.length === 0) {
            App.toast('warning', App.t('po.js.return_err_one'));
            return;
        }

        const confirmed = await App.confirm(
            App.t('po.js.return_confirm_title'),
            App.t('po.js.return_confirm_text')
        );
        if (!confirmed) return;

        const result = await App.api('purchase_orders.php?action=process_return', 'POST', {
            po_id: poId,
            reason: reason,
            items: items
        });

        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('purchaseReturnModal')).hide();
            this.table.ajax.reload(null, false);
        }
    }
};

// Initialize
if (document.getElementById('poTable')) {
    purchase_ordersModule.init();
}

window.purchase_ordersModule = purchase_ordersModule;
