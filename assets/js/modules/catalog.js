/**
 * Catalog Module
 */

const catalogModule = {
    categoriesTable: null,
    brandsTable: null,

    init() {
        this.initDataTables();
        this.bindEvents();
        console.log('Catalog Module Loaded');
    },

    initDataTables() {
        // Categories Table
        this.categoriesTable = $('#categoriesTable').DataTable({
            ajax: 'api/catalog.php?action=list&type=categories',
            columns: [
                { data: 'id' },
                { data: 'name', render: (data) => `<span class="fw-bold">${App.escapeHtml(data)}</span>` },
                {
                    data: null,
                    className: 'text-end',
                    orderable: false,
                    render: (data) => `
                        <button class="btn btn-sm btn-light-primary me-2" onclick="catalogModule.editItem('categories', ${data.id}, this)" data-name="${App.escapeHtml(data.name)}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-light-danger" onclick="catalogModule.deleteItem('categories', ${data.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `
                }
            ],
            language: App.getDataTableLanguage(),
            dom: 'frtp'
        });

        // Brands Table
        this.brandsTable = $('#brandsTable').DataTable({
            ajax: 'api/catalog.php?action=list&type=brands',
            columns: [
                { data: 'id' },
                { data: 'name', render: (data) => `<span class="fw-bold">${App.escapeHtml(data)}</span>` },
                {
                    data: null,
                    className: 'text-end',
                    orderable: false,
                    render: (data) => `
                        <button class="btn btn-sm btn-light-primary me-2" onclick="catalogModule.editItem('brands', ${data.id}, this)" data-name="${App.escapeHtml(data.name)}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-light-danger" onclick="catalogModule.deleteItem('brands', ${data.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `
                }
            ],
            language: App.getDataTableLanguage(),
            dom: 'frtp'
        });
    },

    bindEvents() {
        document.getElementById('catalogForm').onsubmit = async (e) => {
            e.preventDefault();
            await this.saveItem();
        };

        // Reset form on modal close
        document.getElementById('catalogModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('catalogForm').reset();
            document.getElementById('catalog-id').value = '';
        });
    },

    openModal(type) {
        document.getElementById('catalog-type').value = type;
        const singularType = type === 'categories' ? App.t('catalog.category_singular') || 'Category' : App.t('catalog.brand_singular') || 'Brand';
        document.getElementById('catalogModalLabel').textContent = type === 'categories' ? (App.t('catalog.modal.title.add_category') || 'Add Category') : (App.t('catalog.modal.title.add_brand') || 'Add Brand');
        document.getElementById('catalog-name-label').textContent = type === 'categories' ? (App.t('catalog.table.category_name') || 'Category Name') : (App.t('catalog.table.brand_name') || 'Brand Name');
        document.getElementById('catalog-name').placeholder = `e.g. ${type === 'categories' ? 'Diagnostic Tools' : 'Oral-B'}`;

        new bootstrap.Modal(document.getElementById('catalogModal')).show();
    },

    editItem(type, id, btnEl) {
        const name = btnEl.dataset.name || '';
        document.getElementById('catalog-id').value = id;
        document.getElementById('catalog-type').value = type;
        document.getElementById('catalog-name').value = name;

        const singularType = type === 'categories' ? App.t('catalog.category_singular') || 'Category' : App.t('catalog.brand_singular') || 'Brand';
        document.getElementById('catalogModalLabel').textContent = type === 'categories' ? (App.t('catalog.modal.title.edit_category') || 'Edit Category') : (App.t('catalog.modal.title.edit_brand') || 'Edit Brand');
        document.getElementById('catalog-name-label').textContent = type === 'categories' ? (App.t('catalog.table.category_name') || 'Category Name') : (App.t('catalog.table.brand_name') || 'Brand Name');

        new bootstrap.Modal(document.getElementById('catalogModal')).show();
    },

    async saveItem() {
        const form = document.getElementById('catalogForm');
        const formData = new FormData(form);
        const type = formData.get('type');

        try {
            const response = await fetch(`api/catalog.php?action=save&type=${type}`, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                App.toast('success', result.success); // Keep server message if any, though could be translated locally if standardized
                bootstrap.Modal.getInstance(document.getElementById('catalogModal')).hide();
                this[`${type}Table`].ajax.reload();
            } else {
                App.toast('error', result.error);
            }
        } catch (error) {
            App.toast('error', App.t('error.save_failed') || 'Failed to save item');
        }
    },

    async deleteItem(type, id) {
        const singularType = type === 'categories' ? App.t('catalog.category_singular') || 'Category' : App.t('catalog.brand_singular') || 'Brand';
        const confirmTitle = App.t('catalog.modal.delete_title', { type: singularType }) || `Delete ${singularType}?`;
        const confirm = await Swal.fire({
            title: confirmTitle,
            text: App.t('catalog.modal.delete_desc') || "This action cannot be undone if not used in products.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#d33',
            confirmButtonText: App.t('catalog.modal.btn.delete') || 'Yes, delete!',
            cancelButtonText: App.t('btn.cancel') || 'Cancel'
        });

        if (confirm.isConfirmed) {
            const result = await App.api(`catalog.php?action=delete&type=${type}&id=${id}`);
            if (result && result.success) {
                App.toast('success', result.success);
                this[`${type}Table`].ajax.reload();
            }
        }
    }
};

// Initialize
if (document.getElementById('categoriesTable')) {
    catalogModule.init();
}

window.catalogModule = catalogModule;
