/**
 * Stock Management Module
 */

const stockModule = {
    table: null,

    init() {
        this.initDataTable();
        this.fetchStats();
        this.loadMeta();
        this.bindEvents();
        console.log('Stock Module Loaded');
    },

    bindEvents() {
        // Bulk Import
        const importBtn = document.getElementById('btn-import-csv');
        if (importBtn) {
            importBtn.onclick = () => {
                new bootstrap.Modal(document.getElementById('importModal')).show();
            };
        }

        const importDropZone = document.getElementById('importDropZone');
        const importInput = document.getElementById('import-file-input');
        const importForm = document.getElementById('importForm');

        if (importDropZone && importInput) {
            importDropZone.onclick = () => importInput.click();

            importInput.onchange = (e) => this.handleImportFileSelect(e.target.files[0]);

            importDropZone.ondragover = (e) => {
                e.preventDefault();
                importDropZone.classList.add('bg-teal-subtle', 'border-teal');
            };

            importDropZone.ondragleave = () => {
                importDropZone.classList.remove('bg-teal-subtle', 'border-teal');
            };

            importDropZone.ondrop = (e) => {
                e.preventDefault();
                importDropZone.classList.remove('bg-teal-subtle', 'border-teal');
                if (e.dataTransfer.files.length) {
                    this.handleImportFileSelect(e.dataTransfer.files[0]);
                }
            };
        }

        document.getElementById('btn-remove-import-file').onclick = () => {
            this.handleImportFileSelect(null);
        };

        importForm.onsubmit = async (e) => {
            e.preventDefault();
            await this.processImport();
        };

        // Form Submission
        document.getElementById('productForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveProduct();
        };

        // Table Filters
        const filterCat = document.getElementById('filter-category');
        const filterBrand = document.getElementById('filter-brand');

        if (filterCat) {
            filterCat.onchange = (e) => {
                this.table.column(2).search(e.target.value).draw();
            };
        }

        if (filterBrand) {
            filterBrand.onchange = (e) => {
                this.table.column(3).search(e.target.value).draw();
            };
        }

        // Image Preview
        document.getElementById('imageUploadBtn').onclick = () => {
            document.getElementById('product-image-input').click();
        };

        document.getElementById('product-image-input').onchange = (e) => {
            if (e.target.files && e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    document.getElementById('product-img-preview').src = event.target.result;
                    document.getElementById('btn-remove-image').classList.remove('d-none');
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        };

        // Remove Image
        document.getElementById('btn-remove-image').onclick = (e) => {
            e.stopPropagation();
            document.getElementById('product-image-input').value = '';
            document.getElementById('existing-image').value = ''; // Clear for DB update
            document.getElementById('product-img-preview').src = 'assets/img/img_holder.png';
            document.getElementById('btn-remove-image').classList.add('d-none');
        };

        // Reset form on open
        document.getElementById('btn-add-product').onclick = () => {
            document.getElementById('productForm').reset();
            document.getElementById('product-id').value = '';
            document.getElementById('existing-image').value = '';
            document.getElementById('productModalLabel').textContent = App.t('stock.modal.title.add_product') || 'Add New Product';
            document.getElementById('product-img-preview').src = 'assets/img/img_holder.png';
            document.getElementById('btn-remove-image').classList.add('d-none');
        };

        // Stock Adjustment Form
        const adjForm = document.getElementById('adjustmentForm');
        if (adjForm) {
            adjForm.onsubmit = async (e) => {
                e.preventDefault();
                const formData = new FormData(adjForm);
                const result = await App.api('products.php?action=adjust_stock', 'POST', {
                    id: formData.get('id'),
                    type: formData.get('adjustment_type'),
                    qty: formData.get('quantity'),
                    expiry_date: formData.get('expiry_date'),
                    purchase_type: formData.get('purchase_type') || 'BA',
                    reason: formData.get('reason') || 'Manual adjustment'
                });

                if (result && result.success) {
                    App.toast('success', result.success); // Server success
                    bootstrap.Modal.getInstance(document.getElementById('adjustmentModal')).hide();
                    this.table.ajax.reload();
                    this.fetchStats();
                    adjForm.reset();
                } else {
                    App.toast('error', result ? result.error : (App.t('stock.msg.adj_failed') || 'Adjustment failed'));
                }
            };
        }
    },

    initDataTable() {
        this.table = $('#productsTable').DataTable({
            destroy: true,
            ajax: 'api/products.php?action=list',
            columns: [
                {
                    data: 'image',
                    render: (data) => `<img src="assets/img/products/${data || 'default.png'}" class="rounded" width="40" height="40" onerror="this.src='assets/img/img_holder.png'">`
                },
                {
                    data: 'name',
                    className: 'fw-semibold'
                },
                { data: 'category_name', render: (data) => `<span class="badge bg-light text-dark border">${data || (App.t('stock.badge.uncategorized') || 'Uncategorized')}</span>` },
                { data: 'brand_name', render: (data) => `<span class="badge bg-light text-dark border">${data || (App.t('stock.badge.generic') || 'Generic')}</span>` },
                {
                    data: 'selling_price',
                    type: 'num',
                    render: (data, type, row) => {
                        const val = parseFloat(data || 0);
                        if (type === 'sort' || type === 'type') {
                            return val;
                        }
                        if (type === 'display') {
                            return `
                                <small class="text-muted">B: ${App.formatCurrency(row.purchase_price)}</small><br>
                                <span class="text-teal fw-bold">S: ${App.formatCurrency(val)}</span>
                            `;
                        }
                        return val;
                    }
                },
                {
                    data: 'stock_qty',
                    render: (data, type, row) => {
                        const qty = parseInt(data || 0);
                        if (type !== 'display') return qty;

                        if (qty <= 0) {
                            return `<span class="badge bg-danger-subtle text-danger border border-danger p-1 small" style="font-size: 0.75rem;"><i class="fas fa-exclamation-circle me-1"></i>${App.t('stock.badge.out_of_stock', { qty: qty }) || `Out of Stock (${qty})`}</span>`;
                        } else if (qty <= parseInt(row.min_stock || 5)) {
                            return `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning p-1 small" style="font-size: 0.75rem;"><i class="fas fa-clock me-1"></i>${App.t('stock.badge.low_stock', { qty: qty }) || `Low Stock (${qty})`}</span>`;
                        }
                        return `<span class="badge bg-success-subtle text-success border border-success p-1 small" style="font-size: 0.75rem;"><i class="fas fa-check-circle me-1"></i>${App.t('stock.badge.in_stock', { qty: qty }) || `In Stock (${qty})`}</span>`;
                    }
                },
                {
                    data: 'batch_expiry_date',
                    render: (data, type, row) => {
                        if (type === 'display') {
                            const dateToUse = data || row.expiry_date;
                            if (!dateToUse) return '<small class="text-muted">N/A</small>';
                            const today = new Date();
                            const expiry = new Date(dateToUse);

                            // Expired
                            if (expiry <= today) {
                                return `<span class="badge bg-danger-subtle text-danger border border-danger p-1 small" style="font-size: 0.75rem;"><i class="fas fa-exclamation-circle me-1"></i>${App.t('stock.badge.expired', { date: dateToUse }) || `Expired (${dateToUse})`}</span>`;
                            }

                            // Near Expiry (within 30 days)
                            const oneMonthFromNow = new Date();
                            oneMonthFromNow.setMonth(today.getMonth() + 1);

                            if (expiry <= oneMonthFromNow) {
                                return `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning p-1 small" style="font-size: 0.75rem;"><i class="fas fa-clock me-1"></i>${App.t('stock.badge.near_expiry', { date: dateToUse }) || `Near (${dateToUse})`}</span>`;
                            }

                            return `<span class="small">${dateToUse}</span>`;
                        }
                        return data;
                    }
                },
                {
                    data: 'status',
                    render: (data) => {
                        const status = data || 'Active';
                        const color = status === 'Active' ? 'success' : 'secondary';
                        const label = status === 'Active' ? (App.t('stock.badge.status_active') || 'Active') : (App.t('stock.badge.status_inactive') || 'Inactive');
                        return `<span class="badge bg-${color}-subtle text-${color} px-3">${label}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="stockModule.viewDetails(${data.id})"><i class="fas fa-eye me-2 text-info"></i>${App.t('stock.action.details') || 'View Details'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="stockModule.editProduct(${data.id})"><i class="fas fa-edit me-2 text-primary"></i>${App.t('stock.action.edit') || 'Edit'}</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="stockModule.adjustStock(${data.id})"><i class="fas fa-arrows-rotate me-2 text-warning"></i>${App.t('stock.action.adjust') || 'Adjust Stock'}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="stockModule.deleteProduct(${data.id})"><i class="fas fa-trash me-2"></i>${App.t('stock.action.delete') || 'Delete'}</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: App.getDataTableLanguage(),
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    async fetchStats() {
        const stats = await App.api('products.php?action=get_stats');
        if (stats) {
            document.getElementById('stat-total-products').textContent = stats.total;
            document.getElementById('stat-low-stock').textContent = stats.low;
            document.getElementById('stat-expired').textContent = stats.expired;
            document.getElementById('stat-inventory-value').textContent = stats.value + ' ' + App.state.settings.currency;
        }
    },

    async loadMeta() {
        const meta = await App.api('products.php?action=get_meta');
        if (meta) {
            const catSelect = document.getElementById('product-category');
            const brandSelect = document.getElementById('product-brand');
            const filterCat = document.getElementById('filter-category');
            const filterBrand = document.getElementById('filter-brand');

            const catOptions = meta.categories.map(c => `<option value="${c.name}">${c.name}</option>`).join('');
            const catIdOptions = meta.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            const brandOptions = meta.brands.map(b => `<option value="${b.name}">${b.name}</option>`).join('');
            const brandIdOptions = meta.brands.map(b => `<option value="${b.id}">${b.name}</option>`).join('');

            const selectCategoryTxt = App.t('stock.modal.category_select') || 'Select Category';
            const selectBrandTxt = App.t('stock.modal.brand_select') || 'Select Brand';
            const allCategoriesTxt = App.t('stock.filter.all_categories') || 'All Categories';
            const allBrandsTxt = App.t('stock.filter.all_brands') || 'All Brands';

            if (catSelect) catSelect.innerHTML = `<option value="">${selectCategoryTxt}</option>` + catIdOptions;
            if (brandSelect) brandSelect.innerHTML = `<option value="">${selectBrandTxt}</option>` + brandIdOptions;

            if (filterCat) filterCat.innerHTML = `<option value="">${allCategoriesTxt}</option>` + catOptions;
            if (filterBrand) filterBrand.innerHTML = `<option value="">${allBrandsTxt}</option>` + brandOptions;
        }
    },

    async saveProduct() {
        const form = document.getElementById('productForm');
        const formData = new FormData(form);

        try {
            const response = await fetch('api/products.php?action=save', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success);
                bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                this.table.ajax.reload();
                this.fetchStats();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', App.t('error.save_product') || 'Failed to save product');
        }
    },

    async editProduct(id) {
        const product = this.table.rows().data().toArray().find(p => p.id == id);
        if (!product) return;

        document.getElementById('product-id').value = product.id;
        document.querySelector('[name="name"]').value = product.name;
        document.querySelector('[name="category_id"]').value = product.category_id;
        document.querySelector('[name="brand_id"]').value = product.brand_id;
        document.querySelector('[name="barcode"]').value = product.barcode;
        document.querySelector('[name="purchase_price"]').value = product.purchase_price;
        document.querySelector('[name="selling_price"]').value = product.selling_price;

        // Hide initial-only fields on edit
        document.getElementById('product-stock-qty').value = product.stock_qty;
        document.getElementById('product-stock-qty').readOnly = true;
        document.getElementById('product-purchase-type').style.display = 'none';
        document.getElementById('product-purchase-type-help').classList.remove('d-none');

        document.querySelector('[name="min_stock"]').value = product.min_stock;
        document.querySelector('[name="expiry_date"]').value = product.expiry_date;
        document.querySelector('[name="status"]').value = product.status || 'Active';

        document.getElementById('existing-image').value = product.image || '';
        document.getElementById('product-img-preview').src = product.image ? `assets/img/products/${product.image}` : `assets/img/img_holder.png`;

        if (product.image) {
            document.getElementById('btn-remove-image').classList.remove('d-none');
        } else {
            document.getElementById('btn-remove-image').classList.add('d-none');
        }

        document.getElementById('productModalLabel').textContent = App.t('stock.modal.title.edit_product') || 'Edit Product';

        new bootstrap.Modal(document.getElementById('productModal')).show();
    },

    async deleteProduct(id) {
        const confirm = await Swal.fire({
            title: App.t('stock.modal.delete_title') || 'Delete Product?',
            text: App.t('stock.modal.delete_desc') || "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('stock.modal.btn.delete') || 'Yes, delete it!',
            cancelButtonText: App.t('btn.cancel') || 'Cancel'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`products.php?action=delete&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this.table.ajax.reload();
                this.fetchStats();
            }
        }
    },

    adjustStock(id) {
        const product = this.table.rows().data().toArray().find(p => p.id == id);
        if (!product) return;

        document.getElementById('adj-product-id').value = product.id;
        document.getElementById('adj-product-name').value = product.name;
        document.getElementById('adj-current-stock').value = `${product.stock_qty} units`;

        // Bind adj-type toggle
        const adjType = document.getElementById('adj-type');
        const adjPurchaseTypeContainer = document.getElementById('adj-purchase-type-container');
        const adjExpiryContainer = document.getElementById('adj-expiry-container');

        adjType.onchange = (e) => {
            if (e.target.value === 'add') {
                adjPurchaseTypeContainer.classList.remove('d-none');
                adjExpiryContainer.classList.remove('d-none');
            } else {
                adjPurchaseTypeContainer.classList.add('d-none');
                adjExpiryContainer.classList.add('d-none');
            }
        };
        adjType.dispatchEvent(new Event('change'));

        new bootstrap.Modal(document.getElementById('adjustmentModal')).show();
    },

    async viewDetails(id) {
        try {
            const response = await App.api(`products.php?action=get_details&id=${id}`);
            if (response && response.error) {
                App.toast('error', response.error);
                return;
            }
            
            const product = response.product || {};
            const batches = response.batches || [];
            const history = response.history || [];
            
            // Populate Overview
            document.getElementById('detail-product-name').textContent = product.name;
            document.getElementById('detail-product-barcode').innerHTML = `<i class="fas fa-barcode me-1"></i>${product.barcode || 'N/A'}`;
            document.getElementById('detail-product-image').src = product.image ? `assets/img/products/${product.image}` : `assets/img/img_holder.png`;
            
            document.getElementById('detail-category').textContent = product.category_name || '-';
            document.getElementById('detail-brand').textContent = product.brand_name || '-';
            document.getElementById('detail-stock-qty').textContent = product.stock_qty;
            
            const statusColor = product.status === 'Active' ? 'success' : 'secondary';
            document.getElementById('detail-status').innerHTML = `<span class="badge bg-${statusColor}-subtle text-${statusColor} px-3">${product.status || 'Active'}</span>`;
            
            document.getElementById('detail-purchase-price').textContent = App.formatCurrency(product.purchase_price);
            document.getElementById('detail-selling-price').textContent = App.formatCurrency(product.selling_price);
            document.getElementById('detail-min-stock').textContent = product.min_stock;
            document.getElementById('detail-stock-value').textContent = App.formatCurrency(parseFloat(product.stock_qty) * parseFloat(product.purchase_price));
            
            const pp = parseFloat(product.purchase_price) || 0;
            const sp = parseFloat(product.selling_price) || 0;
            const margin = pp > 0 ? ((sp - pp) / pp * 100).toFixed(1) : 100;
            document.getElementById('detail-margin').textContent = `${margin}%`;

            // Destory existing DataTables if present
            if ($.fn.DataTable.isDataTable('#detailBatchesTable')) {
                $('#detailBatchesTable').DataTable().destroy();
            }
            if ($.fn.DataTable.isDataTable('#detailHistoryTable')) {
                $('#detailHistoryTable').DataTable().destroy();
            }

            // Populate Batches
            const batchesBody = document.getElementById('detail-batches-body');
            batchesBody.innerHTML = '';
            batches.forEach(b => {
                const today = new Date();
                const expiry = b.expiry_date ? new Date(b.expiry_date) : null;
                let expClass = '';
                if (expiry) {
                    if (expiry <= today) expClass = 'text-danger fw-bold';
                    else {
                        const oneMonth = new Date();
                        oneMonth.setMonth(today.getMonth() + 1);
                        if (expiry <= oneMonth) expClass = 'text-warning fw-bold';
                    }
                }
                
                const addedDate = new Date(b.created_at).toLocaleDateString() || '-';
                
                batchesBody.innerHTML += `
                    <tr>
                        <td>${addedDate}</td>
                        <td><span class="badge bg-secondary-subtle border border-secondary-subtle">${b.purchase_type || 'BA'}</span></td>
                        <td>${b.initial_qty}</td>
                        <td class="fw-bold text-primary">${b.remaining_qty}</td>
                        <td class="${expClass}">${b.expiry_date || '<span class="text-muted">N/A</span>'}</td>
                    </tr>
                `;
            });

            // Populate History
            const historyBody = document.getElementById('detail-history-body');
            historyBody.innerHTML = '';
            history.forEach(h => {
                historyBody.innerHTML += `
                    <tr>
                        <td>${new Date(h.po_date).toLocaleDateString()}</td>
                        <td class="fw-bold">#${h.po_id}</td>
                        <td>${h.supplier_name}</td>
                        <td class="text-center">${h.qty}</td>
                        <td class="text-center text-success">${h.received_qty}</td>
                        <td class="text-end">${App.formatCurrency(h.unit_cost)}</td>
                    </tr>
                `;
            });

            // Re-initialize translations for potentially dynamic content
            if(typeof App.initI18n === 'function') {
                App.initI18n(document.getElementById('productDetailsModal'));
            }

            // Initialize DataTables
            $('#detailBatchesTable').DataTable({
                language: App.getDataTableLanguage(),
                order: [[4, 'asc']], // Expiry sort
                pageLength: 5,
                lengthMenu: [5, 10, 25],
                info: false,
                autoWidth: false
            });

            $('#detailHistoryTable').DataTable({
                language: App.getDataTableLanguage(),
                order: [[0, 'desc']], // Date sort
                pageLength: 5,
                lengthMenu: [5, 10, 25],
                info: false,
                autoWidth: false
            });

            // Reset tabs to first tab
            const firstTabEl = document.querySelector('#productDetailsModal .nav-link');
            if(firstTabEl) {
                const firstTab = new bootstrap.Tab(firstTabEl);
                firstTab.show();
            }

            new bootstrap.Modal(document.getElementById('productDetailsModal')).show();
        } catch (error) {
            console.error(error);
            App.toast('error', App.t('error.generic') || 'An error occurred fetching details');
        }
    },

    handleImportFileSelect(file) {
        const info = document.getElementById('file-selected-info');
        const dropZone = document.getElementById('importDropZone');
        const btnSubmit = document.getElementById('btn-submit-import');
        const nameEl = document.getElementById('selected-file-name');
        const sizeEl = document.getElementById('selected-file-size');

        if (file) {
            if (!file.name.endsWith('.csv')) {
                App.toast('error', App.t('stock.msg.import_invalid') || 'Please select a valid CSV file');
                return;
            }
            nameEl.textContent = file.name;
            sizeEl.textContent = `${(file.size / 1024).toFixed(2)} KB`;
            info.classList.remove('d-none');
            dropZone.classList.add('d-none');
            btnSubmit.disabled = false;
        } else {
            document.getElementById('import-file-input').value = '';
            info.classList.add('d-none');
            dropZone.classList.remove('d-none');
            btnSubmit.disabled = true;
        }
    },

    async processImport() {
        const form = document.getElementById('importForm');
        const formData = new FormData(form);
        const progressContainer = document.getElementById('import-progress-container');
        const progressBar = document.getElementById('import-progress-bar');
        const statusText = document.getElementById('import-status-text');
        const btnSubmit = document.getElementById('btn-submit-import');

        progressContainer.classList.remove('d-none');
        btnSubmit.disabled = true;
        progressBar.style.width = '0%';
        statusText.textContent = App.t('stock.msg.import_uploading') || 'Uploading...';

        try {
            // Using XHR for progress tracking
            const xhr = new XMLHttpRequest();

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    progressBar.style.width = percent + '%';
                    if (percent === 100) statusText.textContent = App.t('stock.msg.import_processing') || 'Processing CSV data...';
                }
            };

            xhr.onload = () => {
                const result = JSON.parse(xhr.responseText);
                if (result.success) {
                    App.toast('success', result.success); // Keep dynamic backend messages if translated, otherwise you could pass dynamic messages too
                    bootstrap.Modal.getInstance(document.getElementById('importModal')).hide();
                    this.table.ajax.reload();
                    this.fetchStats();

                    // Reset UI
                    this.handleImportFileSelect(null);
                    progressContainer.classList.add('d-none');
                } else {
                    App.toast('error', result.error || (App.t('stock.msg.import_failed') || 'Import failed'));
                    progressBar.style.width = '0%';
                    statusText.textContent = App.t('stock.msg.import_error') || 'Error occurred';
                    btnSubmit.disabled = false;
                }
            };

            xhr.onerror = () => {
                App.toast('error', App.t('stock.msg.import_network') || 'Network error occurred');
                btnSubmit.disabled = false;
            };

            xhr.open('POST', 'api/products.php?action=import_csv');
            xhr.send(formData);

        } catch (error) {
            App.toast('error', App.t('stock.msg.import_failed') || 'Failed to process import');
            btnSubmit.disabled = false;
        }
    }
};

// Initialize
if (document.getElementById('productsTable')) {
    stockModule.init();
}

window.stockModule = stockModule;
