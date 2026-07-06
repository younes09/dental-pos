<?php
// DentalPOS Public Product Catalogue
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="store.title">Product Catalogue - DentalPOS</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/css/bootstrap-icons.css">
    <link href="assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        :root {
            --primary-navy: #0A1628;
            --primary-teal: #00BFA6;
            --primary-teal-hover: #00A690;
        }
        body {
            background-color: #f4f7f6;
            transition: background-color 0.3s ease;
        }
        body.dark-mode {
            background-color: #0b1320 !important;
        }
        .store-hero {
            background: linear-gradient(135deg, var(--primary-navy) 0%, #172a45 100%);
            color: white;
            border-radius: 20px;
            padding: 45px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        .store-hero::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0, 191, 166, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .search-container .form-control {
            border-radius: 12px;
            padding: 14px 20px 14px 45px;
            border: 1px solid #e1e7ed;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }
        .search-container {
            position: relative;
        }
        .search-container i.search-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        .search-container .btn-clear {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            display: none;
        }
        .filter-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }
        .product-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            background-color: #ffffff;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        .product-img-wrapper {
            position: relative;
            width: 100%;
            padding-top: 80%; /* 5:4 aspect ratio */
            background-color: #f8f9fa;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        body.dark-mode .product-img-wrapper {
            background-color: #172237;
        }
        .product-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
            transition: transform 0.5s ease;
        }
        .product-card:hover .product-img {
            transform: scale(1.06);
        }
        .product-info {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .product-brand {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #95a5a6;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .product-title {
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.4;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.7rem;
        }
        body.dark-mode .product-title {
            color: #ecf0f1;
        }
        .product-meta {
            margin-top: auto;
        }
        .price-tag {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary-teal);
            font-family: 'Outfit', sans-serif;
        }
        .availability-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }
        .lang-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .lang-btn:hover, .lang-btn.active {
            opacity: 1;
        }
        .modal-product-img {
            max-height: 300px;
            object-fit: contain;
            width: 100%;
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }
        body.dark-mode .modal-product-img {
            background-color: #172237;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        .btn-teal {
            background-color: var(--primary-teal);
            color: white;
            border: none;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }
        .btn-teal:hover {
            background-color: var(--primary-teal-hover);
            color: white;
        }
        .btn-teal-outline {
            border: 1px solid var(--primary-teal);
            color: var(--primary-teal);
            background: transparent;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-teal-outline:hover {
            background-color: var(--primary-teal);
            color: white;
        }
        /* Dark mode custom overrides */
        body.dark-mode .filter-card {
            background-color: #121c2c;
            border: 1px solid #1a2a40;
        }
        body.dark-mode .product-card {
            background-color: #121c2c;
            border: 1px solid #1a2a40;
        }
        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #1a2a40;
            border-color: #243b55;
            color: white;
        }
        body.dark-mode .form-control::placeholder {
            color: #7f8c8d;
        }
        body.dark-mode .modal-content {
            background-color: #121c2c;
            border: 1px solid #1a2a40;
            color: white;
        }
        body.dark-mode .modal-header, body.dark-mode .modal-footer {
            border-color: #1a2a40;
        }
    </style>
    
    <script>
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
            document.addEventListener('DOMContentLoaded', () => {
                document.body.classList.add('dark-mode');
            });
        }
    </script>
</head>
<body class="bg-light">

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-3" style="background-color: var(--primary-navy) !important;">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
                <i class="fas fa-tooth text-teal me-2" style="font-size: 1.5rem;"></i>
                <span class="font-outfit"><span id="nav-store-name">Dental</span><span>POS</span></span>
            </a>
            
            <div class="d-flex align-items-center ms-auto">
                <!-- Theme toggle -->
                <button id="darkModeToggle" class="btn btn-link text-white p-2 me-3 border-0">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Language Selector -->
                <div class="d-flex align-items-center me-3 border-end pe-3">
                    <button onclick="setLang('en')" id="lang-en" class="lang-btn mx-1 border-0">
                        <img src="assets/vendor/img/flags/us.png" width="20" alt="EN">
                    </button>
                    <button onclick="setLang('fr')" id="lang-fr" class="lang-btn mx-1 border-0">
                        <img src="assets/vendor/img/flags/fr.png" width="20" alt="FR">
                    </button>
                </div>
                
                <a href="login.php" class="btn btn-teal btn-sm px-3 rounded-pill" data-i18n="store.back_to_login">Back to Login</a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container my-4">
        
        <!-- Hero Section -->
        <div class="store-hero">
            <h1 class="fw-bold mb-2 font-outfit" data-i18n="store.title">Product Catalog</h1>
            <p class="mb-0 opacity-75" data-i18n="store.subtitle">View our real-time stock and product availability</p>
        </div>

        <div class="row g-4">
            <!-- Sidebar Filters -->
            <div class="col-lg-3">
                <div class="card filter-card p-4">
                    <h5 class="fw-bold mb-4 font-outfit" data-i18n="catalog.page_title">Filters</h5>
                    
                    <!-- Search Input -->
                    <div class="mb-4">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search..." data-i18n="store.search_placeholder" data-i18n-target="placeholder">
                            <button id="clearSearch" class="btn-clear"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.category">Category</label>
                        <select id="categoryFilter" class="form-select">
                            <option value="" data-i18n="store.filter_category">All Categories</option>
                        </select>
                    </div>
                    
                    <!-- Brand Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.brand">Brand</label>
                        <select id="brandFilter" class="form-select">
                            <option value="" data-i18n="store.filter_brand">All Brands</option>
                        </select>
                    </div>
                    
                    <!-- Availability Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.filter_availability">Availability</label>
                        <select id="availabilityFilter" class="form-select">
                            <option value="all" data-i18n="store.availability_all">All Products</option>
                            <option value="in_stock" data-i18n="store.availability_in_stock">In Stock Only</option>
                            <option value="out_of_stock" data-i18n="store.availability_out_of_stock">Out of Stock</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="col-lg-9">
                <!-- Products container -->
                <div class="row g-4" id="productsGrid">
                    <!-- Loader -->
                    <div class="col-12 text-center py-5" id="gridLoader">
                        <div class="spinner-border text-teal" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header p-4 border-bottom-0">
                    <h5 class="modal-title fw-bold font-outfit" id="modalProductTitle" data-i18n="store.product_details">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 pt-0">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <img id="modalImg" src="assets/img/products/default.jpg" class="modal-product-img img-fluid" alt="Product Image">
                        </div>
                        <div class="col-md-7">
                            <div class="product-brand mb-1 text-teal" id="modalBrand">BRAND</div>
                            <h3 class="fw-bold mb-3 font-outfit text-navy" id="modalName">Product Name</h3>
                            
                            <!-- Specs list -->
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="ps-0 py-2 text-muted fw-medium" width="130" data-i18n="store.category">Category</td>
                                            <td class="py-2 fw-semibold" id="modalCategory">-</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2 text-muted fw-medium" data-i18n="store.barcode">Barcode</td>
                                            <td class="py-2" id="modalBarcode"><code class="bg-light px-2 py-1 rounded">-</code></td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2 text-muted fw-medium" data-i18n="store.quantity">Available Quantity</td>
                                            <td class="py-2 fw-semibold">
                                                <span id="modalStockQty" class="badge bg-secondary">0</span>
                                                <span id="modalAvailabilityBadge" class="availability-badge ms-2">Badge</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-3">
                                <div>
                                    <div class="small text-muted mb-1" data-i18n="store.price">Price</div>
                                    <div class="price-tag fs-2" id="modalPrice">0.00 DZD</div>
                                </div>
                                <button type="button" class="btn btn-teal px-4 py-2 rounded-3" data-bs-dismiss="modal" data-i18n="btn.close">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/locales.js?v=1.0.0"></script>
    
    <script>
        let storeSettings = {
            currency: 'DZD',
            store_name: 'DentalPOS'
        };

        // Multi-language translation setup
        function t(key, params = null) {
            const lang = localStorage.getItem('app_language') || 'fr';
            let translation = (locales[lang] && locales[lang][key]) || (locales['en'] && locales['en'][key]) || key;
            if (params) {
                Object.keys(params).forEach(p => {
                    translation = translation.replace(`{${p}}`, params[p]);
                });
            }
            return translation;
        }

        function translatePage() {
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const target = el.getAttribute('data-i18n-target') || 'text';
                const translation = t(key);

                if (target === 'text') {
                    el.textContent = translation;
                } else if (target === 'html') {
                    el.innerHTML = translation;
                } else if (target === 'placeholder') {
                    el.setAttribute('placeholder', translation);
                } else if (target === 'title') {
                    el.setAttribute('title', translation);
                }
            });
            updateLangButtons();
        }

        function updateLangButtons() {
            const currentLang = localStorage.getItem('app_language') || 'fr';
            document.getElementById('lang-en').classList.toggle('active', currentLang === 'en');
            document.getElementById('lang-fr').classList.toggle('active', currentLang === 'fr');
        }

        function setLang(lang) {
            localStorage.setItem('app_language', lang);
            translatePage();
            loadFilters();
            loadProducts();
        }

        // Theme management
        const darkModeToggle = document.getElementById('darkModeToggle');
        darkModeToggle.onclick = () => {
            const isDark = document.body.classList.toggle('dark-mode');
            document.documentElement.classList.toggle('dark-mode', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const icon = darkModeToggle.querySelector('i');
            if (isDark) {
                icon.classList.replace('fa-moon', 'fa-sun');
            } else {
                icon.classList.replace('fa-sun', 'fa-moon');
            }
        };

        // Sync theme toggle icon on load
        if (localStorage.getItem('theme') === 'dark') {
            darkModeToggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
        }

        // Load Categories & Brands
        async function loadFilters() {
            try {
                const res = await fetch('api/public_store.php?action=filters');
                const result = await res.json();
                
                if (result.settings) {
                    storeSettings = result.settings;
                    document.getElementById('nav-store-name').innerHTML = storeSettings.store_name + '<span>POS</span>';
                }

                const catSelect = document.getElementById('categoryFilter');
                const brandSelect = document.getElementById('brandFilter');

                const selectedCat = catSelect.value;
                const selectedBrand = brandSelect.value;

                catSelect.innerHTML = `<option value="">${t('store.filter_category')}</option>`;
                brandSelect.innerHTML = `<option value="">${t('store.filter_brand')}</option>`;

                result.categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    if (c.id == selectedCat) opt.selected = true;
                    catSelect.appendChild(opt);
                });

                result.brands.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.name;
                    if (b.id == selectedBrand) opt.selected = true;
                    brandSelect.appendChild(opt);
                });
            } catch (err) {
                console.error("Error loading filters:", err);
            }
        }

        let productsList = [];

        // Load Products
        async function loadProducts() {
            const grid = document.getElementById('productsGrid');
            const search = document.getElementById('searchInput').value;
            const category_id = document.getElementById('categoryFilter').value;
            const brand_id = document.getElementById('brandFilter').value;
            const availability = document.getElementById('availabilityFilter').value;

            grid.innerHTML = `
                <div class="col-12 text-center py-5" id="gridLoader">
                    <div class="spinner-border text-teal" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;

            try {
                const params = new URLSearchParams({
                    action: 'products',
                    search: search,
                    category_id: category_id,
                    brand_id: brand_id,
                    availability: availability
                });

                const res = await fetch('api/public_store.php?' + params.toString());
                const result = await res.json();
                productsList = result.data || [];

                grid.innerHTML = '';

                if (productsList.length === 0) {
                    grid.innerHTML = `
                        <div class="col-12 empty-state">
                            <i class="fas fa-box-open text-muted"></i>
                            <h4 class="fw-bold font-outfit" data-i18n="table.empty">No products found</h4>
                            <p class="text-muted" data-i18n="store.no_products">No products found matching your search criteria.</p>
                        </div>
                    `;
                    translatePage();
                    return;
                }

                productsList.forEach(p => {
                    const col = document.createElement('div');
                    col.className = 'col-sm-6 col-md-4 col-lg-3';
                    
                    let stockBadge = '';
                    const minStock = parseInt(p.min_stock) || 5;
                    const stockQty = parseInt(p.stock_qty) || 0;

                    if (stockQty === 0) {
                        stockBadge = `<span class="availability-badge bg-danger-subtle text-danger"><i class="fas fa-times-circle me-1"></i>${t('store.status_out_of_stock')}</span>`;
                    } else if (stockQty <= minStock) {
                        stockBadge = `<span class="availability-badge bg-warning-subtle text-warning"><i class="fas fa-exclamation-triangle me-1"></i>${t('store.status_limited')} (${stockQty})</span>`;
                    } else {
                        stockBadge = `<span class="availability-badge bg-success-subtle text-success"><i class="fas fa-check-circle me-1"></i>${t('store.status_in_stock')} (${stockQty})</span>`;
                    }

                    const imgSrc = p.image && p.image !== 'default.jpg' ? 'assets/img/products/' + p.image : 'assets/img/products/default.jpg';
                    const brandName = p.brand_name ? p.brand_name : '-';
                    const catName = p.category_name ? p.category_name : '-';
                    const priceFormatted = parseFloat(p.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + storeSettings.currency;

                    col.innerHTML = `
                        <div class="card product-card">
                            <div class="product-img-wrapper">
                                <img src="${imgSrc}" class="product-img" alt="${App.escapeHtml(p.name)}" onerror="this.src='assets/img/products/default.jpg'">
                            </div>
                            <div class="product-info">
                                <div class="product-brand">${App.escapeHtml(brandName)}</div>
                                <h6 class="product-title" title="${App.escapeHtml(p.name)}">${App.escapeHtml(p.name)}</h6>
                                <div class="mb-2">
                                    <span class="badge bg-light text-secondary border">${App.escapeHtml(catName)}</span>
                                </div>
                                <div class="mb-3">
                                    ${stockBadge}
                                </div>
                                <div class="product-meta d-flex justify-content-between align-items-center mt-2 border-top pt-2">
                                    <span class="price-tag">${priceFormatted}</span>
                                    <button class="btn btn-sm btn-teal-outline rounded-3 px-2 py-1" onclick="viewProductDetails(${p.id})">
                                        <i class="fas fa-eye me-1"></i>${t('store.view_details')}
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    grid.appendChild(col);
                });
            } catch (err) {
                console.error("Error loading products:", err);
                grid.innerHTML = `
                    <div class="col-12 text-center py-5 text-danger">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                        <p data-i18n="app.error">Error loading products.</p>
                    </div>
                `;
                translatePage();
            }
        }

        window.viewProductDetails = function(productId) {
            const product = productsList.find(p => p.id == productId);
            if (!product) return;

            document.getElementById('modalName').textContent = product.name;
            document.getElementById('modalBrand').textContent = product.brand_name ? product.brand_name.toUpperCase() : '-';
            document.getElementById('modalCategory').textContent = product.category_name ? product.category_name : '-';
            document.getElementById('modalBarcode').innerHTML = product.barcode ? `<code class="bg-light px-2 py-1 rounded text-dark">${product.barcode}</code>` : `<span class="text-muted">-</span>`;
            
            const stockQty = parseInt(product.stock_qty) || 0;
            const minStock = parseInt(product.min_stock) || 5;
            document.getElementById('modalStockQty').textContent = stockQty;
            document.getElementById('modalStockQty').className = `badge ${stockQty === 0 ? 'bg-danger' : (stockQty <= minStock ? 'bg-warning text-dark' : 'bg-success')}`;

            const badgeSpan = document.getElementById('modalAvailabilityBadge');
            if (stockQty === 0) {
                badgeSpan.className = 'availability-badge bg-danger-subtle text-danger';
                badgeSpan.innerHTML = `<i class="fas fa-times-circle me-1"></i>${t('store.status_out_of_stock')}`;
            } else if (stockQty <= minStock) {
                badgeSpan.className = 'availability-badge bg-warning-subtle text-warning';
                badgeSpan.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i>${t('store.status_limited')}`;
            } else {
                badgeSpan.className = 'availability-badge bg-success-subtle text-success';
                badgeSpan.innerHTML = `<i class="fas fa-check-circle me-1"></i>${t('store.status_in_stock')}`;
            }

            const imgSrc = product.image && product.image !== 'default.jpg' ? 'assets/img/products/' + product.image : 'assets/img/products/default.jpg';
            document.getElementById('modalImg').src = imgSrc;
            document.getElementById('modalPrice').textContent = parseFloat(product.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + storeSettings.currency;

            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
        };

        const App = {
            escapeHtml(str) {
                if (str == null) return '';
                const div = document.createElement('div');
                div.textContent = String(str);
                return div.innerHTML;
            }
        };

        document.getElementById('categoryFilter').addEventListener('change', loadProducts);
        document.getElementById('brandFilter').addEventListener('change', loadProducts);
        document.getElementById('availabilityFilter').addEventListener('change', loadProducts);

        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const clearSearch = document.getElementById('clearSearch');

        searchInput.addEventListener('input', (e) => {
            const val = e.target.value;
            clearSearch.style.display = val ? 'block' : 'none';
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadProducts();
            }, 300);
        });

        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            clearSearch.style.display = 'none';
            loadProducts();
        });

        document.addEventListener('DOMContentLoaded', () => {
            if (!localStorage.getItem('app_language')) {
                localStorage.setItem('app_language', 'fr');
            }
            translatePage();
            loadFilters().then(() => {
                loadProducts();
            });
        });
    </script>
</body>
</html>
