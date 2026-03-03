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
        // Form Submission
        document.getElementById('productForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveProduct();
        };

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
            document.getElementById('productModalLabel').textContent = 'Add New Product';
            document.getElementById('product-img-preview').src = 'assets/img/img_holder.png';
            document.getElementById('btn-remove-image').classList.add('d-none');
        };
    },

    initDataTable() {
        this.table = $('#productsTable').DataTable({
            ajax: 'api/products.php?action=list',
            columns: [
                {
                    data: 'image',
                    render: (data) => `<img src="assets/img/products/${data || 'default.png'}" class="rounded" width="40" height="40" onerror="this.src='https://ui-avatars.com/api/?name=Product&background=random'">`
                },
                { data: 'name', className: 'fw-semibold' },
                { data: 'category_name', render: (data) => `<span class="badge bg-light text-dark border">${data || 'Uncategorized'}</span>` },
                { data: 'brand_name', render: (data) => `<span class="badge bg-light text-dark border">${data || 'Generic'}</span>` },
                {
                    data: null,
                    render: (data) => `
                        <small class="text-muted">B: ${App.formatCurrency(data.purchase_price)}</small><br>
                        <span class="text-teal fw-bold">S: ${App.formatCurrency(data.selling_price)}</span>
                    `
                },
                {
                    data: 'stock_qty',
                    render: (data, type, row) => {
                        const color = data <= row.min_stock ? 'danger' : 'success';
                        return `<span class="badge bg-${color}">${data} units</span>`;
                    }
                },
                {
                    data: 'expiry_date',
                    render: (data) => {
                        if (!data) return '<small class="text-muted">N/A</small>';
                        const today = new Date();
                        const expiry = new Date(data);
                        const isExpired = expiry <= today;
                        return `<span class="text-${isExpired ? 'danger' : 'dark'} small">${data}</span>`;
                    }
                },
                {
                    data: 'status',
                    render: (data) => `<span class="badge bg-${data === 'Active' ? 'success' : 'secondary'}-subtle text-${data === 'Active' ? 'success' : 'secondary'} px-3">${data}</span>`
                },
                {
                    data: null,
                    orderable: false,
                    render: (data) => `
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="stockModule.editProduct(${data.id})"><i class="fas fa-edit me-2 text-primary"></i>Edit</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="stockModule.adjustStock(${data.id})"><i class="fas fa-arrows-rotate me-2 text-warning"></i>Adjust Stock</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="stockModule.deleteProduct(${data.id})"><i class="fas fa-trash me-2"></i>Delete</a></li>
                            </ul>
                        </div>
                    `
                }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search inventory...",
                lengthMenu: "_MENU_",
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
        });
    },

    async fetchStats() {
        const stats = await App.api('products.php?action=get_stats');
        if (stats) {
            document.getElementById('stat-total-products').textContent = stats.total;
            document.getElementById('stat-low-stock').textContent = stats.low;
            document.getElementById('stat-expired').textContent = stats.expired;
            document.getElementById('stat-inventory-value').textContent = App.formatCurrency(stats.value);
        }
    },

    async loadMeta() {
        const meta = await App.api('products.php?action=get_meta');
        if (meta) {
            const catSelect = document.getElementById('product-category');
            const brandSelect = document.getElementById('product-brand');

            catSelect.innerHTML = '<option value="">Select Category</option>' + meta.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            brandSelect.innerHTML = '<option value="">Select Brand</option>' + meta.brands.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
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
            App.toast('error', 'Failed to save product');
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
        document.querySelector('[name="stock_qty"]').value = product.stock_qty;
        document.querySelector('[name="min_stock"]').value = product.min_stock;
        document.querySelector('[name="expiry_date"]').value = product.expiry_date;

        document.getElementById('existing-image').value = product.image || '';
        document.getElementById('product-img-preview').src = product.image ? `assets/img/products/${product.image}` : `assets/img/img_holder.png`;

        if (product.image) {
            document.getElementById('btn-remove-image').classList.remove('d-none');
        } else {
            document.getElementById('btn-remove-image').classList.add('d-none');
        }

        document.getElementById('productModalLabel').textContent = 'Edit Product';

        new bootstrap.Modal(document.getElementById('productModal')).show();
    },

    async deleteProduct(id) {
        const confirm = await Swal.fire({
            title: 'Delete Product?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
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
        // Placeholder for stock adjustment logic
        App.toast('info', 'Stock adjustment feature coming soon');
    }
};

// Initialize
if (document.getElementById('productsTable')) {
    stockModule.init();
}

window.stockModule = stockModule;
