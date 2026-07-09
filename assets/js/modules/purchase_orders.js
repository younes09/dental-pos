/**
 * Purchase Orders Module
 */

const purchase_ordersModule = {
    table: null,
    products: [],
    suppliers: [],
    accountBalances: {}, // map of account_id -> balance

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

        // Inline Product Creation from PO
        const btnCreateProductPo = document.getElementById('btn-create-product-po');
        if (btnCreateProductPo) {
            btnCreateProductPo.onclick = () => {
                document.getElementById('productForm').reset();
                document.getElementById('product-id').value = '';
                document.getElementById('existing-image').value = '';
                document.getElementById('product-img-preview').src = 'assets/img/img_holder.png';
                const removeImageBtn = document.getElementById('btn-remove-image');
                if(removeImageBtn) removeImageBtn.classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
            };
        }

        // Image Preview & Upload for Inline Product
        const imageUploadBtn = document.getElementById('imageUploadBtn');
        const productImageInput = document.getElementById('product-image-input');
        const btnRemoveImage = document.getElementById('btn-remove-image');

        if (imageUploadBtn && productImageInput) {
            imageUploadBtn.onclick = () => {
                productImageInput.click();
            };

            productImageInput.onchange = (e) => {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const preview = document.getElementById('product-img-preview');
                        if (preview) preview.src = event.target.result;
                        if (btnRemoveImage) btnRemoveImage.classList.remove('d-none');
                    };
                    reader.readAsDataURL(e.target.files[0]);
                }
            };
        }

        if (btnRemoveImage && productImageInput) {
            btnRemoveImage.onclick = (e) => {
                e.stopPropagation();
                productImageInput.value = '';
                const existingImg = document.getElementById('existing-image');
                if (existingImg) existingImg.value = '';
                const preview = document.getElementById('product-img-preview');
                if (preview) preview.src = 'assets/img/img_holder.png';
                btnRemoveImage.classList.add('d-none');
            };
        }


        // Inline Create Category (in PO Modal)
        const btnAddCat = document.getElementById('btn-add-category');
        if (btnAddCat) {
            btnAddCat.onclick = async () => {
                const { value: name } = await Swal.fire({
                    target: document.getElementById('productModal'),
                    title: 'New Category',
                    input: 'text',
                    inputPlaceholder: 'Enter category name',
                    showCancelButton: true,
                    confirmButtonText: 'Add',
                    confirmButtonColor: '#00BFA6',
                    inputValidator: (value) => {
                        if (!value) return 'You need to write something!';
                    }
                });
                if (name) {
                    const res = await App.api('products.php?action=add_category', 'POST', { name });
                    if (res && res.success) {
                        App.toast('success', res.success);
                        const newOption = new Option(res.data.name, res.data.id, true, true);
                        document.getElementById('product-category').appendChild(newOption);
                    } else if (res && res.error) {
                        App.toast('error', res.error);
                    }
                }
            };
        }

        // Inline Create Brand (in PO Modal)
        const btnAddBrand = document.getElementById('btn-add-brand');
        if (btnAddBrand) {
            btnAddBrand.onclick = async () => {
                const { value: name } = await Swal.fire({
                    target: document.getElementById('productModal'),
                    title: 'New Brand',
                    input: 'text',
                    inputPlaceholder: 'Enter brand name',
                    showCancelButton: true,
                    confirmButtonText: 'Add',
                    confirmButtonColor: '#00BFA6',
                    inputValidator: (value) => {
                        if (!value) return 'You need to write something!';
                    }
                });
                if (name) {
                    const res = await App.api('products.php?action=add_brand', 'POST', { name });
                    if (res && res.success) {
                        App.toast('success', res.success);
                        const newOption = new Option(res.data.name, res.data.id, true, true);
                        document.getElementById('product-brand').appendChild(newOption);
                    } else if (res && res.error) {
                        App.toast('error', res.error);
                    }
                }
            };
        }

        // Handle inline product form submission
        const productForm = document.getElementById('productForm');
        if (productForm) {
            productForm.onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(productForm);
                try {
                    const response = await fetch('api/products.php?action=save', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        App.toast('success', result.success);
                        bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                        
                        // Reload products
                        await this.loadMeta();
                        
                        // Automatically add the new item row if we have the ID
                        if (result.product_id) {
                            this.addItemRow();
                            
                            // Get the last added row and select the new product
                            const rows = document.querySelectorAll('#po-items-table tbody tr');
                            if (rows.length > 0) {
                                const lastRow = rows[rows.length - 1];
                                const productSelect = lastRow.querySelector('.item-product');
                                productSelect.value = result.product_id;
                                
                                // Trigger change event to update cost
                                productSelect.dispatchEvent(new Event('change'));
                            }
                        }
                    } else {
                        App.toast('error', result.error);
                    }
                } catch (error) {
                    App.toast('error', App.t('error.save_product') || 'Failed to save product');
                }
            };
        }
        // Scan Invoice Events
        const dragDropZone = document.getElementById('drag-drop-zone');
        const fileInput = document.getElementById('invoice-file-input');
        
        if (dragDropZone && fileInput) {
            dragDropZone.onclick = () => fileInput.click();
            
            fileInput.onchange = (e) => {
                if (e.target.files && e.target.files[0]) {
                    this.uploadAndParseInvoice(e.target.files[0]);
                }
            };
            
            dragDropZone.ondragover = (e) => {
                e.preventDefault();
                dragDropZone.classList.add('bg-secondary-subtle');
            };
            
            dragDropZone.ondragleave = () => {
                dragDropZone.classList.remove('bg-secondary-subtle');
            };
            
            dragDropZone.ondrop = (e) => {
                e.preventDefault();
                dragDropZone.classList.remove('bg-secondary-subtle');
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    this.uploadAndParseInvoice(e.dataTransfer.files[0]);
                }
            };
        }

        // Toggling new supplier
        const btnToggleNewSupplier = document.getElementById('btn-toggle-new-supplier');
        const supplierSelect = document.getElementById('scan-supplier-select');
        const newSupplierContainer = document.getElementById('scan-new-supplier-container');
        const newSupplierNameInput = document.getElementById('scan-new-supplier-name');

        if (btnToggleNewSupplier) {
            btnToggleNewSupplier.onclick = () => {
                const isNew = newSupplierContainer.classList.contains('d-none');
                if (isNew) {
                    newSupplierContainer.classList.remove('d-none');
                    supplierSelect.value = '__NEW__';
                    supplierSelect.required = false;
                    newSupplierNameInput.required = true;
                    btnToggleNewSupplier.innerHTML = `<i class="fas fa-list"></i> <span data-i18n="po.scan_modal.existing_supplier_btn">Existant</span>`;
                    App.translate(btnToggleNewSupplier.parentElement);
                } else {
                    newSupplierContainer.classList.add('d-none');
                    supplierSelect.value = '';
                    supplierSelect.required = true;
                    newSupplierNameInput.required = false;
                    newSupplierNameInput.value = '';
                    btnToggleNewSupplier.innerHTML = `<i class="fas fa-plus"></i> <span data-i18n="po.scan_modal.new_supplier_btn">Nouveau</span>`;
                    App.translate(btnToggleNewSupplier.parentElement);
                }
            };
        }

        if (supplierSelect) {
            supplierSelect.onchange = (e) => {
                if (e.target.value === '__NEW__') {
                    newSupplierContainer.classList.remove('d-none');
                    newSupplierNameInput.required = true;
                    if (btnToggleNewSupplier) {
                        btnToggleNewSupplier.innerHTML = `<i class="fas fa-list"></i> <span data-i18n="po.scan_modal.existing_supplier_btn">Existant</span>`;
                        App.translate(btnToggleNewSupplier.parentElement);
                    }
                } else {
                    newSupplierContainer.classList.add('d-none');
                    newSupplierNameInput.required = false;
                    newSupplierNameInput.value = '';
                    if (btnToggleNewSupplier) {
                        btnToggleNewSupplier.innerHTML = `<i class="fas fa-plus"></i> <span data-i18n="po.scan_modal.new_supplier_btn">Nouveau</span>`;
                        App.translate(btnToggleNewSupplier.parentElement);
                    }
                }
            };
        }

        // Toggle finance fields in scan modal
        const scanPoStatus = document.getElementById('scan-po-status');
        if (scanPoStatus) {
            scanPoStatus.onchange = (e) => {
                const container = document.getElementById('scan-po-purchase-type-container');
                const financeContainer = document.getElementById('scan-po-finance-container');
                
                if (e.target.value === 'Received') {
                    container.classList.remove('d-none');
                    document.getElementById('scan-po-purchase-type').required = true;
                    financeContainer.classList.remove('d-none');
                } else {
                    container.classList.add('d-none');
                    document.getElementById('scan-po-purchase-type').required = false;
                    financeContainer.classList.add('d-none');
                }
            };
        }

        // Add manual item in scan modal
        const btnScanAddItem = document.getElementById('scan-btn-add-item');
        if (btnScanAddItem) {
            btnScanAddItem.onclick = () => {
                this.addScannedItemRow({
                    product_name: '',
                    qty: 1,
                    unit_cost: 0.00
                });
            };
        }

        // Reset step view when modal shows up
        const scanModalEl = document.getElementById('scanInvoiceModal');
        if (scanModalEl) {
            scanModalEl.addEventListener('show.bs.modal', () => {
                document.getElementById('scan-step-upload').classList.remove('d-none');
                document.getElementById('scan-step-loading').classList.add('d-none');
                document.getElementById('scan-step-edit').classList.add('d-none');
                if (fileInput) fileInput.value = '';
                document.getElementById('scannedInvoiceForm').reset();
                document.querySelector('#scan-items-table tbody').innerHTML = '';
                
                // Repopulate supplier dropdowns just in case meta loaded/changed
                const scanSupplierSelect = document.getElementById('scan-supplier-select');
                if (scanSupplierSelect) {
                    scanSupplierSelect.innerHTML = `<option value="">${App.t('po.modal.supplier_select')}</option>` +
                        `<option value="__NEW__">${App.t('po.scan_modal.new_supplier_option') || 'Créer nouveau fournisseur'}</option>` +
                        this.suppliers.map(s => `<option value="${s.id}">${s.name} (${s.company})</option>`).join('');
                }
                
                // Populate source accounts dropdown
                const scanPoAccount = document.getElementById('scan-po-account-id');
                if (scanPoAccount && window.vaultAccountsOptions) {
                    scanPoAccount.innerHTML = window.vaultAccountsOptions;
                } else if (scanPoAccount) {
                    // fall back to default loaded accounts options
                    const origAcctSelect = document.getElementById('po-account-id');
                    if (origAcctSelect) scanPoAccount.innerHTML = origAcctSelect.innerHTML;
                }
            });
        }

        // Form submission in scan modal
        const scannedInvoiceForm = document.getElementById('scannedInvoiceForm');
        if (scannedInvoiceForm) {
            scannedInvoiceForm.onsubmit = async (e) => {
                e.preventDefault();
                await this.submitScannedInvoice();
            };
        }
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

                        let cancelBtn = '';
                        if (data.status !== 'Cancelled') {
                            cancelBtn = `<li><a class="dropdown-item text-warning" href="javascript:void(0)" onclick="purchase_ordersModule.cancelPO(${data.id}, '${data.status}')"><i class="fas fa-ban me-2"></i>${App.t('po.js.status_cancel')}</a></li>`;
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
                                ${cancelBtn}
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="purchase_ordersModule.deletePO(${data.id}, '${data.status}')"><i class="fas fa-trash me-2"></i>${App.t('po.js.status_del')}</a></li>
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
            // Cache balances for client-side validation
            this.accountBalances = {};
            accountRes.data.forEach(acc => {
                this.accountBalances[acc.id] = parseFloat(acc.balance);
            });

            const options = `<option value="">${App.t('po.modal.account_no')}</option>` +
                accountRes.data.map(acc => `<option value="${acc.id}">${acc.name} (${App.formatCurrency(acc.balance)})</option>`).join('');

            const createSelect = document.getElementById('po-account-id');
            if (createSelect) createSelect.innerHTML = options;

            const receiveSelect = document.getElementById('receive-po-account-id');
            if (receiveSelect) receiveSelect.innerHTML = options;

            const scanSelect = document.getElementById('scan-po-account-id');
            if (scanSelect) scanSelect.innerHTML = options;
        }

        // Load product metadata (categories/brands) for the inline product creator
        const prodMetaRes = await App.api('products.php?action=get_meta');
        if (prodMetaRes) {
            const catSelect = document.getElementById('product-category');
            const brandSelect = document.getElementById('product-brand');
            
            if (catSelect) {
                catSelect.innerHTML = `<option value="">${App.t('stock.modal.category_select') || 'Select Category'}</option>` +
                    prodMetaRes.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            }
            if (brandSelect) {
                brandSelect.innerHTML = `<option value="">${App.t('stock.modal.brand_select') || 'Select Brand'}</option>` +
                    prodMetaRes.brands.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
            }
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
            <td><input type="date" class="form-control form-control-sm item-expiry" min="${new Date().toISOString().split('T')[0]}" required></td>
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

        // Update max hint for paid amount
        const hint = document.getElementById('po-paid-amount-max-hint');
        if (hint) {
            hint.textContent = total > 0 ? `Max payable: ${App.formatCurrency(total)}` : '';
        }
        const paidInput = document.getElementById('po-paid-amount');
        if (paidInput) paidInput.max = total;
    },

    /**
     * Validate paid_amount against order total and selected account balance.
     * Returns true if valid, false (and shows toast) if not.
     */
    validatePayment(paidAmount, orderTotal, accountId) {
        if (paidAmount < 0) {
            App.toast('error', App.t('po.js.err_negative_payment') || 'Payment amount cannot be negative.');
            return false;
        }
        if (paidAmount > orderTotal) {
            App.toast('error', App.t('po.js.err_overpayment') || `Payment (${App.formatCurrency(paidAmount)}) cannot exceed order total (${App.formatCurrency(orderTotal)}).`);
            return false;
        }
        if (accountId && paidAmount > 0) {
            const balance = this.accountBalances[accountId];
            if (balance !== undefined && paidAmount > balance) {
                App.toast('error', App.t('po.js.err_insufficient_balance') || `Insufficient account balance. Available: ${App.formatCurrency(balance)}, Required: ${App.formatCurrency(paidAmount)}.`);
                return false;
            }
        }
        return true;
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
                    unit_cost: cost,
                    expiry_date: row.querySelector('.item-expiry').value || null
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
            data.paid_amount = parseFloat(document.getElementById('po-paid-amount').value) || 0;
            data.account_id = document.getElementById('po-account-id').value;

            // Client-side payment validation
            if (!this.validatePayment(data.paid_amount, total, data.account_id)) return;
        }

        const confirmed = await App.confirm(
            App.t('po.js.save_confirm_title') || 'Save Purchase Order?',
            App.t('po.js.save_confirm_text') || 'Are you sure you want to save this purchase order?'
        );
        if (!confirmed) return;

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

    async deletePO(id, status) {
        const hasReceivedStock = (status === 'Received' || status === 'Partial');
        const warningText = hasReceivedStock ? 
            'This order has received stock or returns. Deleting it will reverse any remaining inventory stock, reverse supplier debt, refund vault payments, and delete all associated returns. Are you sure you want to delete it permanently?' : 
            (App.t('po.js.del_text') || 'This action cannot be undone!');

        const confirm = await Swal.fire({
            title: App.t('po.js.del_title') || 'Delete Purchase Order?',
            text: warningText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: App.t('po.js.btn_del') || 'Delete',
            cancelButtonText: App.t('po.modal.btn_cancel') || 'Cancel'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`purchase_orders.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
            } else if (result && result.error) {
                App.toast('error', result.error);
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
                        <input type="number" class="form-control form-control-sm receive-item-input" 
                            data-item-id="${item.id}" data-product-id="${item.product_id}" data-max="${remaining}"
                            data-cost="${item.unit_cost}"
                            value="${remaining}">
                        <div class="invalid-feedback" style="font-size: 0.7rem;">${App.t('po.js.max')}: ${remaining}</div>
                    </td>
                    <td>
                        <input type="date" class="form-control form-control-sm receive-item-expiry" min="${new Date().toISOString().split('T')[0]}" required data-item-id="${item.id}">
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

        // Compute total receivable value and update max hint
        let totalReceivableValue = 0;
        document.querySelectorAll('.receive-item-input').forEach(input => {
            const qty = parseInt(input.value) || 0;
            const cost = parseFloat(input.dataset.cost) || 0;
            totalReceivableValue += qty * cost;
        });
        const receiveHint = document.getElementById('receive-paid-amount-max-hint');
        if (receiveHint) receiveHint.textContent = totalReceivableValue > 0 ? `Max payable: ${App.formatCurrency(totalReceivableValue)}` : '';
        const receivePaidInput = document.getElementById('receive-po-paid-amount');
        if (receivePaidInput) receivePaidInput.max = totalReceivableValue;

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
                const expiryInput = document.querySelector(`.receive-item-expiry[data-item-id="${input.dataset.itemId}"]`);
                receivedItems.push({
                    item_id: input.dataset.itemId,
                    product_id: input.dataset.productId,
                    receiving_qty: val,
                    expiry_date: expiryInput ? expiryInput.value : null
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

        const paidAmount = parseFloat(document.getElementById('receive-po-paid-amount').value) || 0;
        const accountId = document.getElementById('receive-po-account-id').value;

        // Calculate the total value of items being received now for overpayment check
        let receivingTotal = 0;
        receivedItems.forEach(item => {
            // unit cost is stored in the input's closest row context; read from data attr if available
            const input = document.querySelector(`.receive-item-input[data-item-id="${item.item_id}"]`);
            const cost = input ? parseFloat(input.dataset.cost || 0) : 0;
            receivingTotal += item.receiving_qty * cost;
        });

        // Client-side payment validation
        if (!this.validatePayment(paidAmount, receivingTotal, accountId)) return;

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

    async cancelPO(id, status) {
        const hasReceivedStock = (status === 'Received' || status === 'Partial');
        const warningText = hasReceivedStock ? 
            (App.t('po.js.cancel_received_text') || 'This order has received stock. Cancelling it will deduct the received quantities from inventory, reverse supplier debt, and refund vault payments. Are you sure you want to continue?') : 
            (App.t('po.js.cancel_text') || 'This will mark the order as Cancelled. This action cannot be undone.');

        const confirmed = await Swal.fire({
            title: App.t('po.js.cancel_title') || 'Cancel Purchase Order?',
            text: warningText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f39c12',
            cancelButtonColor: '#6c757d',
            confirmButtonText: App.t('po.js.cancel_confirm') || 'Yes, Cancel It',
            cancelButtonText: App.t('po.js.cancel_abort') || 'Keep Order'
        });

        if (!confirmed.isConfirmed) return;

        const result = await App.api(`purchase_orders.php?action=cancel&id=${id}`);
        if (!result) return;

        if (result.error) {
            App.toast('error', result.error);
            return;
        }

        App.toast('success', result.success || App.t('po.js.cancelled'));
        this.table.ajax.reload(null, false);
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
    },

    async uploadAndParseInvoice(file) {
        document.getElementById('scan-step-upload').classList.add('d-none');
        document.getElementById('scan-step-loading').classList.remove('d-none');
        document.getElementById('scan-step-edit').classList.add('d-none');

        const formData = new FormData();
        formData.append('invoice_file', file);

        try {
            const response = await fetch('api/purchase_orders.php?action=parse_invoice', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result && result.error_code === 'MISSING_GEMINI_KEY') {
                bootstrap.Modal.getInstance(document.getElementById('scanInvoiceModal')).hide();
                const confirm = await Swal.fire({
                    title: App.t('po.js.err_no_gemini_key') || 'Gemini API Key Missing',
                    text: App.t('po.js.err_no_gemini_key_text') || 'You need to set a Gemini API key in settings to scan invoices. Would you like to go to settings now?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#00BFA6',
                    confirmButtonText: App.t('sidebar.settings') || 'Settings',
                    cancelButtonText: App.t('btn.cancel') || 'Cancel'
                });
                if (confirm.isConfirmed) {
                    window.location.hash = '#settings';
                }
                return;
            }

            if (result && result.error) {
                App.toast('error', result.error);
                document.getElementById('scan-step-upload').classList.remove('d-none');
                document.getElementById('scan-step-loading').classList.add('d-none');
                return;
            }

            if (result && result.data) {
                this.loadScannedData(result.data);
            } else {
                App.toast('error', 'Erreur de lecture de la facture.');
                document.getElementById('scan-step-upload').classList.remove('d-none');
                document.getElementById('scan-step-loading').classList.add('d-none');
            }
        } catch (error) {
            console.error('Invoice Upload Error:', error);
            App.toast('error', 'Erreur réseau lors de l\'analyse.');
            document.getElementById('scan-step-upload').classList.remove('d-none');
            document.getElementById('scan-step-loading').classList.add('d-none');
        }
    },

    loadScannedData(data) {
        if (data.date) {
            document.getElementById('scan-invoice-date').value = data.date;
        } else {
            document.getElementById('scan-invoice-date').valueAsDate = new Date();
        }

        const supplierSelect = document.getElementById('scan-supplier-select');
        const newSupplierContainer = document.getElementById('scan-new-supplier-container');
        const newSupplierNameInput = document.getElementById('scan-new-supplier-name');
        const btnToggleNewSupplier = document.getElementById('btn-toggle-new-supplier');

        let matchedSupplier = null;
        if (data.supplier_name) {
            const queryName = data.supplier_name.toLowerCase();
            matchedSupplier = this.suppliers.find(s => 
                s.name.toLowerCase().includes(queryName) || 
                queryName.includes(s.name.toLowerCase()) ||
                (s.company && (s.company.toLowerCase().includes(queryName) || queryName.includes(s.company.toLowerCase())))
            );
        }

        if (matchedSupplier) {
            supplierSelect.value = matchedSupplier.id;
            newSupplierContainer.classList.add('d-none');
            newSupplierNameInput.required = false;
            newSupplierNameInput.value = '';
            if (btnToggleNewSupplier) {
                btnToggleNewSupplier.innerHTML = `<i class="fas fa-plus"></i> <span data-i18n="po.scan_modal.new_supplier_btn">Nouveau</span>`;
                App.translate(btnToggleNewSupplier.parentElement);
            }
        } else {
            supplierSelect.value = '__NEW__';
            newSupplierContainer.classList.remove('d-none');
            newSupplierNameInput.required = true;
            newSupplierNameInput.value = data.supplier_name || '';
            if (btnToggleNewSupplier) {
                btnToggleNewSupplier.innerHTML = `<i class="fas fa-list"></i> <span data-i18n="po.scan_modal.existing_supplier_btn">Existant</span>`;
                App.translate(btnToggleNewSupplier.parentElement);
            }
        }

        const tbody = document.querySelector('#scan-items-table tbody');
        tbody.innerHTML = '';

        if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                this.addScannedItemRow(item);
            });
        }

        this.calculateScannedTotals();

        document.getElementById('scan-step-upload').classList.add('d-none');
        document.getElementById('scan-step-loading').classList.add('d-none');
        document.getElementById('scan-step-edit').classList.remove('d-none');
    },

    addScannedItemRow(item) {
        const tbody = document.querySelector('#scan-items-table tbody');
        const row = document.createElement('tr');

        let matchedProduct = null;
        if (item.product_name) {
            const queryName = item.product_name.toLowerCase();
            matchedProduct = this.products.find(p => 
                p.name.toLowerCase().includes(queryName) || 
                queryName.includes(p.name.toLowerCase()) ||
                (p.barcode && p.barcode.toLowerCase() === queryName)
            );
        }

        const selectedVal = matchedProduct ? matchedProduct.id : '__NEW__';
        const cost = parseFloat(item.unit_cost) || 0;
        const qty = parseInt(item.qty) || 1;
        const sellingPrice = matchedProduct ? matchedProduct.selling_price : (cost * 1.2).toFixed(2);

        row.innerHTML = `
            <td data-label="${App.t('po.modal.th_product') || 'Produit'}">
                <input type="text" class="form-control form-control-sm scan-item-product-name" value="${App.escapeHtml(item.product_name)}" required>
            </td>
            <td data-label="${App.t('po.scan_modal.map_product') || 'Association'}">
                <select class="form-select form-select-sm scan-item-product-map" required>
                    <option value="__NEW__">-- ${App.t('po.modal.btn_create_product') || 'Nouveau'} --</option>
                    ${this.products.map(p => `<option value="${p.id}" ${p.id == selectedVal ? 'selected' : ''}>${App.escapeHtml(p.name)}</option>`).join('')}
                </select>
            </td>
            <td data-label="${App.t('po.modal.th_qty') || 'Qté'}">
                <input type="number" class="form-control form-control-sm scan-item-qty text-center" value="${qty}" min="1" required>
            </td>
            <td data-label="${App.t('po.modal.th_unit_cost') || 'Coût Unit.'}">
                <input type="number" step="0.01" class="form-control form-control-sm scan-item-cost" value="${cost.toFixed(2)}" min="0" required>
            </td>
            <td data-label="${App.t('po.scan_modal.selling_price') || 'Prix Vente'}">
                <input type="number" step="0.01" class="form-control form-control-sm scan-item-selling" value="${parseFloat(sellingPrice).toFixed(2)}" min="0.01" ${selectedVal !== '__NEW__' ? 'disabled' : ''} required>
            </td>
            <td data-label="${App.t('po.modal.th_expiry') || 'Expiration'}">
                <input type="date" class="form-control form-control-sm scan-item-expiry" style="font-size: 0.75rem;">
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm text-danger btn-remove-scan-item">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;

        const mapSelect = row.querySelector('.scan-item-product-map');
        const sellingInput = row.querySelector('.scan-item-selling');
        const costInput = row.querySelector('.scan-item-cost');
        const qtyInput = row.querySelector('.scan-item-qty');

        mapSelect.onchange = (e) => {
            if (e.target.value === '__NEW__') {
                sellingInput.disabled = false;
                sellingInput.value = (parseFloat(costInput.value) * 1.2).toFixed(2);
            } else {
                sellingInput.disabled = true;
                const pId = e.target.value;
                const p = this.products.find(prod => prod.id == pId);
                if (p) {
                    sellingInput.value = parseFloat(p.selling_price || 0).toFixed(2);
                    costInput.value = parseFloat(p.purchase_price || 0).toFixed(2);
                }
            }
            this.calculateScannedTotals();
        };

        costInput.oninput = () => {
            if (mapSelect.value === '__NEW__') {
                sellingInput.value = (parseFloat(costInput.value) * 1.2).toFixed(2);
            }
            this.calculateScannedTotals();
        };

        qtyInput.oninput = () => this.calculateScannedTotals();

        row.querySelector('.btn-remove-scan-item').onclick = () => {
            row.remove();
            this.calculateScannedTotals();
        };

        tbody.appendChild(row);
    },

    calculateScannedTotals() {
        let total = 0;
        document.querySelectorAll('#scan-items-table tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.scan-item-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.scan-item-cost').value) || 0;
            total += qty * cost;
        });
        document.getElementById('scan-total-display').textContent = App.formatCurrency(total);

        const paidInput = document.getElementById('scan-po-paid-amount');
        if (paidInput) {
            paidInput.max = total;
        }
    },

    async submitScannedInvoice() {
        const supplier_id = document.getElementById('scan-supplier-select').value;
        const new_supplier_name = document.getElementById('scan-new-supplier-name').value;
        const date = document.getElementById('scan-invoice-date').value;
        const status = document.getElementById('scan-po-status').value;
        const purchase_type = document.getElementById('scan-po-purchase-type').value;
        
        let paid_amount = 0;
        let account_id = null;
        
        if (status === 'Received') {
            paid_amount = parseFloat(document.getElementById('scan-po-paid-amount').value) || 0;
            account_id = document.getElementById('scan-po-account-id').value;
        }

        const items = [];
        let error = false;

        document.querySelectorAll('#scan-items-table tbody tr').forEach(row => {
            const productName = row.querySelector('.scan-item-product-name').value.trim();
            const productMap = row.querySelector('.scan-item-product-map').value;
            const qty = parseInt(row.querySelector('.scan-item-qty').value) || 0;
            const cost = parseFloat(row.querySelector('.scan-item-cost').value) || 0;
            const selling = parseFloat(row.querySelector('.scan-item-selling').value) || 0;
            const expiry = row.querySelector('.scan-item-expiry').value || null;

            if (!productName) {
                App.toast('error', 'Chaque ligne doit avoir un nom de produit.');
                error = true;
                return;
            }
            if (qty <= 0) {
                App.toast('error', 'La quantité doit être supérieure à zéro.');
                error = true;
                return;
            }

            items.push({
                product_id: productMap,
                new_product_name: productName,
                qty: qty,
                unit_cost: cost,
                selling_price: selling,
                expiry_date: expiry
            });
        });

        if (error) return;
        if (items.length === 0) {
            App.toast('error', 'Veuillez ajouter au moins un article.');
            return;
        }

        let total = 0;
        items.forEach(it => total += it.qty * it.unit_cost);

        if (status === 'Received') {
            if (paid_amount < 0) {
                App.toast('error', 'Le montant payé ne peut pas être négatif.');
                return;
            }
            if (paid_amount > total) {
                App.toast('error', 'Le montant payé ne peut pas dépasser le total.');
                return;
            }
            if (account_id && paid_amount > 0) {
                const balance = this.accountBalances[account_id];
                if (balance !== undefined && paid_amount > balance) {
                    App.toast('error', `Solde insuffisant sur ce compte. Disponible : ${App.formatCurrency(balance)}`);
                    return;
                }
            }
        }

        const confirmed = await App.confirm(
            App.t('po.js.save_confirm_title') || 'Save Purchase Order?',
            'Voulez-vous enregistrer cette facture d\'achat en créant automatiquement les produits/fournisseurs manquants ?'
        );
        if (!confirmed) return;

        const payload = {
            supplier_id,
            new_supplier_name,
            date,
            status,
            purchase_type,
            paid_amount,
            account_id,
            items
        };

        const result = await App.api('purchase_orders.php?action=save_scanned_invoice', 'POST', payload);
        if (result && result.success) {
            App.toast('success', result.success);
            bootstrap.Modal.getInstance(document.getElementById('scanInvoiceModal')).hide();
            this.table.ajax.reload();
            
            await this.loadMeta();
        } else if (result && result.error) {
            App.toast('error', result.error);
        }
    }
    // End scanned invoice helper methods
}

// Initialize
if (document.getElementById('poTable')) {
    purchase_ordersModule.init();
}

window.purchase_ordersModule = purchase_ordersModule;
