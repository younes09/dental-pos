<?php
// DentalPOS Public Product Catalogue
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="store.title">Catalogue Produits - DentalPOS</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/css/bootstrap-icons.css">
    <link href="assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo @file_exists('assets/css/style.css') ? filemtime('assets/css/style.css') : time(); ?>">
    
    <style>
        :root {
            --primary-navy: #0A1628;
            --primary-teal: #00BFA6;
            --primary-teal-hover: #00A690;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-main: #1E293B;
            --text-muted: #64748B;
        }

        body {
            background-color: #f8fafc;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        body.dark-mode {
            background-color: #0b1320 !important;
            color: #e2e8f0;
            --card-bg: #121c2c;
            --card-border: #1e293b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }

        .navbar-store {
            background-color: var(--primary-navy) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .store-logo-img {
            max-height: 38px;
            max-width: 140px;
            object-fit: contain;
        }

        .store-hero {
            background: linear-gradient(135deg, #0A1628 0%, #172a45 60%, #0d233a 100%);
            color: white;
            border-radius: 20px;
            padding: 38px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(10, 22, 40, 0.12);
            position: relative;
            overflow: hidden;
        }

        .store-hero::after {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(0, 191, 166, 0.22) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-meta-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.85rem;
            color: #f1f5f9;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .hero-meta-badge:hover {
            background: rgba(0, 191, 166, 0.25);
            color: #fff;
            border-color: var(--primary-teal);
        }

        .filter-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            position: sticky;
            top: 90px;
        }

        .search-container {
            position: relative;
        }

        .search-container .form-control {
            border-radius: 12px;
            padding: 12px 40px 12px 42px;
            font-size: 0.95rem;
            border: 1px solid #cbd5e1;
            transition: all 0.2s ease;
        }

        body.dark-mode .search-container .form-control {
            background-color: #1a2a40;
            border-color: #2a3c54;
            color: #fff;
        }

        .search-container .form-control:focus {
            border-color: var(--primary-teal);
            box-shadow: 0 0 0 3px rgba(0, 191, 166, 0.18);
        }

        .search-container i.search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
        }

        .search-container .btn-clear {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            display: none;
            padding: 4px;
        }

        .search-container .btn-clear:hover {
            color: #ef4444;
        }

        /* View Mode & Toolbar */
        .toolbar-box {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .view-btn {
            background: none;
            border: 1px solid #cbd5e1;
            color: #64748b;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        body.dark-mode .view-btn {
            border-color: #334155;
            color: #94a3b8;
        }

        .view-btn.active, .view-btn:hover {
            background-color: var(--primary-teal);
            color: #fff;
            border-color: var(--primary-teal);
        }

        /* Product Cards */
        .product-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
            border-color: rgba(0, 191, 166, 0.4);
        }

        .product-img-wrapper {
            position: relative;
            width: 100%;
            padding-top: 75%;
            background-color: #f1f5f9;
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
            padding: 14px;
            transition: transform 0.4s ease;
        }

        .product-card:hover .product-img {
            transform: scale(1.05);
        }

        .product-info {
            padding: 16px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-brand {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: var(--primary-teal);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .product-title {
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.4;
            color: var(--text-main);
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.7rem;
        }

        .price-tag {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary-teal);
            font-family: 'Outfit', sans-serif;
        }

        .availability-badge {
            font-size: 0.74rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* List View styles */
        .list-mode .col-product {
            width: 100% !important;
        }

        .list-mode .product-card {
            flex-direction: row;
            align-items: center;
        }

        .list-mode .product-img-wrapper {
            width: 130px;
            min-width: 130px;
            height: 130px;
            padding-top: 0;
        }

        .list-mode .product-title {
            -webkit-line-clamp: 1;
            min-height: auto;
            font-size: 1.05rem;
        }

        .btn-teal {
            background-color: var(--primary-teal);
            color: white;
            border: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-teal:hover {
            background-color: var(--primary-teal-hover);
            color: white;
            transform: translateY(-1px);
        }

        .btn-teal-outline {
            border: 1.5px solid var(--primary-teal);
            color: var(--primary-teal);
            background: transparent;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-teal-outline:hover {
            background-color: var(--primary-teal);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card-bg);
            border: 1px dashed var(--card-border);
            border-radius: 16px;
        }

        /* Pagination Styling */
        .pagination .page-link {
            border-radius: 8px !important;
            border: 1px solid var(--card-border);
            color: var(--text-main);
            background-color: var(--card-bg);
            padding: 6px 12px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-teal) !important;
            border-color: var(--primary-teal) !important;
            color: #ffffff !important;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(0, 191, 166, 0.3);
        }

        .pagination .page-link:hover:not(.disabled) {
            background-color: rgba(0, 191, 166, 0.1);
            border-color: var(--primary-teal);
            color: var(--primary-teal);
        }

        .pagination .page-item.disabled .page-link {
            opacity: 0.45;
            background-color: transparent;
            cursor: not-allowed;
        }

        body.dark-mode .pagination .page-link {
            background-color: #1a2a40;
            border-color: #2a3c54;
            color: #cbd5e1;
        }

        body.dark-mode .pagination .page-item.active .page-link {
            background-color: var(--primary-teal) !important;
            color: #ffffff !important;
        }

        .empty-state i {
            font-size: 3.5rem;
            color: #cbd5e1;
            margin-bottom: 15px;
        }

        .lang-btn {
            background: none;
            border: none;
            padding: 4px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.2s, transform 0.2s;
        }

        .lang-btn:hover, .lang-btn.active {
            opacity: 1;
            transform: scale(1.1);
        }

        .modal-product-img {
            max-height: 280px;
            object-fit: contain;
            width: 100%;
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid var(--card-border);
        }

        body.dark-mode .modal-product-img {
            background-color: #172237;
        }

        body.dark-mode .form-select, body.dark-mode .form-control {
            background-color: #1a2a40;
            border-color: #2a3c54;
            color: #fff;
        }

        body.dark-mode .modal-content {
            background-color: #121c2c;
            border-color: #1e293b;
            color: #fff;
        }

        .store-footer {
            margin-top: auto;
            background-color: #0A1628;
            color: #94a3b8;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.88rem;
        }

        .store-footer a {
            color: var(--primary-teal);
            text-decoration: none;
        }

        .store-footer a:hover {
            text-decoration: underline;
        }
    </style>
    
    <script>
        // Synchronous dark-mode detection to avoid flash
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-store sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="store.php">
                <span id="nav-brand-logo">
                    <i class="fas fa-tooth text-teal" style="font-size: 1.5rem;"></i>
                </span>
                <span class="font-outfit text-white fs-5 fw-bold" id="nav-store-name">DentalPOS</span>
            </a>
            
            <div class="d-flex align-items-center ms-auto gap-2">
                <!-- Theme toggle -->
                <button id="darkModeToggle" class="btn btn-link text-white p-2 border-0" aria-label="Toggle Dark Mode" title="Mode Sombre / Clair">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Language Selector -->
                <div class="d-flex align-items-center border-start border-secondary ps-2 ms-1">
                    <button onclick="setLang('fr')" id="lang-fr" class="lang-btn mx-1" title="Français">
                        <img src="assets/vendor/img/flags/fr.png" width="20" alt="FR">
                    </button>
                    <button onclick="setLang('en')" id="lang-en" class="lang-btn mx-1" title="English">
                        <img src="assets/vendor/img/flags/us.png" width="20" alt="EN">
                    </button>
                </div>
                
                <a href="login.php" class="btn btn-teal btn-sm px-3 rounded-pill ms-2" data-i18n="store.back_to_login">
                    <i class="fas fa-sign-in-alt me-1"></i>Connexion POS
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="container my-4 flex-grow-1">
        
        <!-- Hero Section -->
        <div class="store-hero">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="fw-bold mb-2 font-outfit" id="hero-title" data-i18n="store.title">Catalogue Produits</h1>
                    <p class="mb-3 opacity-75" data-i18n="store.subtitle">Consultez nos disponibilités et stocks en temps réel</p>
                    
                    <!-- Contact quick links inside hero -->
                    <div class="d-flex flex-wrap gap-2" id="hero-contact-links">
                        <!-- Populated by JS if store phone/email available -->
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Sidebar Filters -->
            <div class="col-lg-3">
                <div class="card filter-card p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold font-outfit mb-0" data-i18n="catalog.page_title">Filtres</h5>
                        <button id="btnResetFilters" class="btn btn-link btn-sm text-decoration-none p-0 text-muted" data-i18n="store.reset_filters" style="font-size: 0.8rem;">
                            <i class="fas fa-undo me-1"></i>Réinitialiser
                        </button>
                    </div>
                    
                    <!-- Search Input -->
                    <div class="mb-3">
                        <div class="search-container">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher..." data-i18n="store.search_placeholder" data-i18n-target="placeholder">
                            <button id="clearSearch" class="btn-clear" type="button" title="Effacer"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.category">Catégorie</label>
                        <select id="categoryFilter" class="form-select form-select-sm rounded-3">
                            <option value="" data-i18n="store.filter_category">Toutes les catégories</option>
                        </select>
                    </div>
                    
                    <!-- Brand Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.brand">Marque</label>
                        <select id="brandFilter" class="form-select form-select-sm rounded-3">
                            <option value="" data-i18n="store.filter_brand">Toutes les marques</option>
                        </select>
                    </div>
                    
                    <!-- Availability Filter -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.filter_availability">Disponibilité</label>
                        <select id="availabilityFilter" class="form-select form-select-sm rounded-3">
                            <option value="all" data-i18n="store.availability_all">Tous les produits</option>
                            <option value="in_stock" data-i18n="store.availability_in_stock">En stock uniquement</option>
                            <option value="out_of_stock" data-i18n="store.availability_out_of_stock">Rupture de stock</option>
                        </select>
                    </div>

                    <!-- Sort Filter -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-uppercase opacity-75" data-i18n="store.sort_by">Trier par</label>
                        <select id="sortFilter" class="form-select form-select-sm rounded-3">
                            <option value="name_asc" data-i18n="store.sort_name_asc">Nom (A à Z)</option>
                            <option value="name_desc" data-i18n="store.sort_name_desc">Nom (Z à A)</option>
                            <option value="price_asc" data-i18n="store.sort_price_asc">Prix : Croissant</option>
                            <option value="price_desc" data-i18n="store.sort_price_desc">Prix : Décroissant</option>
                            <option value="stock_desc" data-i18n="store.sort_stock_desc">Disponibilité maximale</option>
                            <option value="newest" data-i18n="store.sort_newest">Nouveautés</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Products Section -->
            <div class="col-lg-9">
                <!-- Toolbar: Products count & View Mode & Limit -->
                <div class="toolbar-box flex-wrap gap-2">
                    <div class="fw-semibold small text-muted d-flex align-items-center gap-2">
                        <span id="productsCountText">Chargement...</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <div class="d-flex align-items-center gap-1 small text-muted">
                            <span class="d-none d-sm-inline" data-i18n="store.per_page">Par page :</span>
                            <select id="limitSelect" class="form-select form-select-sm rounded-3 py-1 px-2" style="width: auto; font-size: 0.82rem;">
                                <option value="12" selected>12</option>
                                <option value="24">24</option>
                                <option value="48">48</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-1 border-start ps-2">
                            <button type="button" class="view-btn active" id="btnViewGrid" title="Affichage Grille">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button type="button" class="view-btn" id="btnViewList" title="Affichage Liste">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Products Grid / List container -->
                <div class="row g-3 g-md-4" id="productsGrid">
                    <!-- Loader -->
                    <div class="col-12 text-center py-5" id="gridLoader">
                        <div class="spinner-border text-teal" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>

                <!-- Pagination Controls -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top gap-3" id="paginationWrapper" style="display: none !important;">
                    <div class="small text-muted" id="paginationInfo">
                        <!-- Populated by JS -->
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0 gap-1 flex-wrap justify-content-center" id="paginationList">
                            <!-- Populated by JS -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header p-4 border-bottom pb-3">
                    <h5 class="modal-title fw-bold font-outfit" id="modalProductTitle" data-i18n="store.product_details">Détails du Produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-5 text-center">
                            <img id="modalImg" src="assets/img/img_holder.png" class="modal-product-img img-fluid" alt="Product Image" onerror="this.onerror=null; this.src='assets/img/img_holder.png';">
                        </div>
                        <div class="col-md-7">
                            <div class="product-brand mb-1" id="modalBrand">BRAND</div>
                            <h3 class="fw-bold mb-3 font-outfit" id="modalName">Product Name</h3>
                            
                            <!-- Specs list -->
                            <div class="table-responsive">
                                <table class="table table-borderless align-middle mb-3 small">
                                    <tbody>
                                        <tr>
                                            <td class="ps-0 py-1 text-muted fw-medium" width="150" data-i18n="store.category">Catégorie</td>
                                            <td class="py-1 fw-semibold" id="modalCategory">-</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-1 text-muted fw-medium" data-i18n="store.barcode">Code-barres</td>
                                            <td class="py-1" id="modalBarcode"><code class="bg-light px-2 py-1 rounded text-dark">-</code></td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-1 text-muted fw-medium" data-i18n="store.quantity">Stock Disponible</td>
                                            <td class="py-1 fw-semibold">
                                                <span id="modalStockQty" class="badge bg-secondary">0</span>
                                                <span id="modalAvailabilityBadge" class="availability-badge ms-1">Badge</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-1 text-muted fw-medium" data-i18n="store.expiry_date">Date d'expiration</td>
                                            <td class="py-1 fw-semibold" id="modalExpiryDate">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top gap-3">
                                <div>
                                    <div class="small text-muted mb-0" data-i18n="store.price">Prix</div>
                                    <div class="price-tag fs-3" id="modalPrice">0.00 DZD</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a id="modalInquireBtn" href="#" target="_blank" class="btn btn-teal px-3 py-2 rounded-3 shadow-sm" style="display: none;">
                                        <i class="fab fa-whatsapp me-1"></i><span data-i18n="store.inquire">Se renseigner</span>
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary px-3 py-2 rounded-3" data-bs-dismiss="modal" data-i18n="btn.close">Fermer</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Store Footer -->
    <footer class="store-footer py-4 mt-5">
        <div class="container text-center text-md-start">
            <div class="row align-items-center gy-3">
                <div class="col-md-6">
                    <div class="fw-bold text-white mb-1" id="footer-store-name">DentalPOS</div>
                    <div class="small" id="footer-store-info">Catalogue de produits en ligne</div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="small opacity-75">
                        &copy; <?php echo date('Y'); ?> DentalPOS. Tous droits réservés.
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/locales.js?v=<?php echo @file_exists('assets/js/locales.js') ? filemtime('assets/js/locales.js') : time(); ?>"></script>
    
    <script>
        // Fallback store dictionary to guarantee translations even without cache invalidation
        const storeDictionary = {
            fr: {
                "store.title": "Catalogue Produits",
                "store.subtitle": "Consultez nos disponibilités et stocks en temps réel",
                "store.search_placeholder": "Rechercher par nom, code-barres, marque...",
                "store.category": "Catégorie",
                "store.filter_category": "Toutes les catégories",
                "store.brand": "Marque",
                "store.filter_brand": "Toutes les marques",
                "store.filter_availability": "Disponibilité",
                "store.availability_all": "Tous les produits",
                "store.availability_in_stock": "En stock uniquement",
                "store.availability_out_of_stock": "Rupture de stock",
                "store.back_to_login": "Connexion POS",
                "store.view_details": "Détails",
                "store.status_in_stock": "En Stock",
                "store.status_limited": "Stock Faible",
                "store.status_out_of_stock": "Rupture",
                "store.status_not_applicable": "Non spécifié",
                "store.product_details": "Détails du Produit",
                "store.barcode": "Code-barres",
                "store.quantity": "Stock Disponible",
                "store.expiry_date": "Date d'expiration",
                "store.price": "Prix",
                "store.no_products": "Aucun produit ne correspond à vos critères de recherche.",
                "store.sort_by": "Trier par",
                "store.sort_name_asc": "Nom (A à Z)",
                "store.sort_name_desc": "Nom (Z à A)",
                "store.sort_price_asc": "Prix : Croissant",
                "store.sort_price_desc": "Prix : Décroissant",
                "store.sort_stock_desc": "Disponibilité maximale",
                "store.sort_newest": "Nouveautés",
                "store.reset_filters": "Réinitialiser les filtres",
                "store.products_found": "{count} produits trouvés",
                "store.contact_us": "Contactez-nous",
                "store.inquire": "Se renseigner",
                "store.inquire_msg": "Bonjour, je souhaite me renseigner sur le produit : {name} (Réf : {barcode})",
                "store.page_prev": "Précédent",
                "store.page_next": "Suivant",
                "store.page_info": "Affichage de {start} à {end} sur {total} produits",
                "store.per_page": "Par page :"
            },
            en: {
                "store.title": "Product Catalog",
                "store.subtitle": "View our real-time stock and product availability",
                "store.search_placeholder": "Search by product name, barcode, brand...",
                "store.category": "Category",
                "store.filter_category": "All Categories",
                "store.brand": "Brand",
                "store.filter_brand": "All Brands",
                "store.filter_availability": "Availability",
                "store.availability_all": "All Products",
                "store.availability_in_stock": "In Stock Only",
                "store.availability_out_of_stock": "Out of Stock",
                "store.back_to_login": "POS Login",
                "store.view_details": "Details",
                "store.status_in_stock": "In Stock",
                "store.status_limited": "Low Stock",
                "store.status_out_of_stock": "Out of Stock",
                "store.status_not_applicable": "Not specified",
                "store.product_details": "Product Details",
                "store.barcode": "Barcode",
                "store.quantity": "Available Stock",
                "store.expiry_date": "Expiry Date",
                "store.price": "Price",
                "store.no_products": "No products found matching your search criteria.",
                "store.sort_by": "Sort by",
                "store.sort_name_asc": "Name (A to Z)",
                "store.sort_name_desc": "Name (Z to A)",
                "store.sort_price_asc": "Price: Low to High",
                "store.sort_price_desc": "Price: High to Low",
                "store.sort_stock_desc": "Highest Stock",
                "store.sort_newest": "Newest Arrivals",
                "store.reset_filters": "Reset Filters",
                "store.products_found": "{count} products found",
                "store.contact_us": "Contact Us",
                "store.inquire": "Inquire",
                "store.inquire_msg": "Hello, I would like to inquire about the product: {name} (Ref: {barcode})",
                "store.page_prev": "Previous",
                "store.page_next": "Next",
                "store.page_info": "Showing {start} to {end} of {total} products",
                "store.per_page": "Per page:"
            }
        };

        let storeSettings = {
            currency: 'DZD',
            store_name: 'DentalPOS'
        };

        let currentViewMode = localStorage.getItem('store_view_mode') || 'grid';
        let productsList = [];

        // Safe Translation function
        function t(key, params = null) {
            const lang = localStorage.getItem('app_language') || 'fr';
            let translation = (typeof locales !== 'undefined' && locales[lang] && locales[lang][key]) ||
                              (storeDictionary[lang] && storeDictionary[lang][key]) ||
                              (typeof locales !== 'undefined' && locales['en'] && locales['en'][key]) ||
                              (storeDictionary['en'] && storeDictionary['en'][key]) ||
                              key;

            if (params && typeof translation === 'string') {
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

        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            darkModeToggle.querySelector('i').classList.replace('fa-moon', 'fa-sun');
        }

        // View Mode Toggle (Grid vs List)
        const btnViewGrid = document.getElementById('btnViewGrid');
        const btnViewList = document.getElementById('btnViewList');
        const productsGridEl = document.getElementById('productsGrid');

        function setViewMode(mode) {
            currentViewMode = mode;
            localStorage.setItem('store_view_mode', mode);
            btnViewGrid.classList.toggle('active', mode === 'grid');
            btnViewList.classList.toggle('active', mode === 'list');
            productsGridEl.classList.toggle('list-mode', mode === 'list');
            renderProducts(productsList);
        }

        btnViewGrid.onclick = () => setViewMode('grid');
        btnViewList.onclick = () => setViewMode('list');

        // Escape helper
        const App = {
            escapeHtml(str) {
                if (str == null) return '';
                const div = document.createElement('div');
                div.textContent = String(str);
                return div.innerHTML;
            }
        };

        // Load Categories, Brands & Settings
        async function loadFilters() {
            try {
                const res = await fetch('api/public_store.php?action=filters');
                const result = await res.json();
                
                if (result.settings) {
                    storeSettings = result.settings;
                    const storeName = storeSettings.store_name || 'DentalPOS';
                    
                    document.getElementById('nav-store-name').textContent = storeName;
                    document.getElementById('footer-store-name').textContent = storeName;
                    document.title = storeName + ' - ' + t('store.title');

                    // Store Logo
                    if (storeSettings.store_logo) {
                        document.getElementById('nav-brand-logo').innerHTML = `<img src="${storeSettings.store_logo}" alt="${App.escapeHtml(storeName)}" class="store-logo-img me-1">`;
                    }

                    // Contact Meta Links in Hero
                    const heroContact = document.getElementById('hero-contact-links');
                    let heroPills = [];
                    if (storeSettings.store_phone) {
                        heroPills.push(`
                            <a href="tel:${encodeURIComponent(storeSettings.store_phone)}" class="hero-meta-badge">
                                <i class="fas fa-phone-alt me-2 text-teal"></i>${App.escapeHtml(storeSettings.store_phone)}
                            </a>
                        `);
                    }
                    if (storeSettings.store_email) {
                        heroPills.push(`
                            <a href="mailto:${encodeURIComponent(storeSettings.store_email)}" class="hero-meta-badge">
                                <i class="fas fa-envelope me-2 text-teal"></i>${App.escapeHtml(storeSettings.store_email)}
                            </a>
                        `);
                    }
                    if (storeSettings.address) {
                        heroPills.push(`
                            <span class="hero-meta-badge">
                                <i class="fas fa-map-marker-alt me-2 text-teal"></i>${App.escapeHtml(storeSettings.address)}
                            </span>
                        `);
                    }
                    heroContact.innerHTML = heroPills.join('');

                    // Footer contact info
                    let footerDetails = [];
                    if (storeSettings.address) footerDetails.push(storeSettings.address);
                    if (storeSettings.store_phone) footerDetails.push('Tél: ' + storeSettings.store_phone);
                    if (storeSettings.store_email) footerDetails.push(storeSettings.store_email);
                    if (footerDetails.length > 0) {
                        document.getElementById('footer-store-info').textContent = footerDetails.join(' | ');
                    }
                }

                const catSelect = document.getElementById('categoryFilter');
                const brandSelect = document.getElementById('brandFilter');

                const selectedCat = catSelect.value;
                const selectedBrand = brandSelect.value;

                catSelect.innerHTML = `<option value="">${t('store.filter_category')}</option>`;
                brandSelect.innerHTML = `<option value="">${t('store.filter_brand')}</option>`;

                (result.categories || []).forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    if (c.id == selectedCat) opt.selected = true;
                    catSelect.appendChild(opt);
                });

                (result.brands || []).forEach(b => {
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

        // Render Products List
        function renderProducts(items) {
            const grid = document.getElementById('productsGrid');
            grid.innerHTML = '';

            const countEl = document.getElementById('productsCountText');
            countEl.textContent = t('store.products_found', { count: items.length });

            if (items.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 empty-state">
                        <i class="fas fa-box-open"></i>
                        <h4 class="fw-bold font-outfit">${t('table.empty')}</h4>
                        <p class="text-muted">${t('store.no_products')}</p>
                    </div>
                `;
                return;
            }

            const isList = currentViewMode === 'list';

            items.forEach(p => {
                const col = document.createElement('div');
                col.className = isList ? 'col-12 col-product' : 'col-sm-6 col-md-6 col-xl-4 col-product';
                
                let stockBadge = '';
                const minStock = parseInt(p.min_stock) || 5;
                const stockQty = parseInt(p.stock_qty) || 0;

                if (stockQty === 0) {
                    stockBadge = `<span class="availability-badge bg-danger-subtle text-danger"><i class="fas fa-times-circle"></i>${t('store.status_out_of_stock')}</span>`;
                } else if (stockQty <= minStock) {
                    stockBadge = `<span class="availability-badge bg-warning-subtle text-warning"><i class="fas fa-exclamation-triangle"></i>${t('store.status_limited')} (${stockQty})</span>`;
                } else {
                    stockBadge = `<span class="availability-badge bg-success-subtle text-success"><i class="fas fa-check-circle"></i>${t('store.status_in_stock')} (${stockQty})</span>`;
                }

                const imgSrc = p.image && p.image !== 'default.jpg' && p.image !== 'default.png' ? 'assets/img/products/' + p.image : 'assets/img/img_holder.png';
                const brandName = p.brand_name ? p.brand_name : '-';
                const catName = p.category_name ? p.category_name : '-';
                const priceFormatted = parseFloat(p.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + storeSettings.currency;

                col.innerHTML = `
                    <div class="card product-card" onclick="viewProductDetails(${p.id})">
                        <div class="product-img-wrapper">
                            <img src="${imgSrc}" class="product-img" alt="${App.escapeHtml(p.name)}" onerror="this.onerror=null; this.src='assets/img/img_holder.png';">
                        </div>
                        <div class="product-info">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div class="product-brand">${App.escapeHtml(brandName)}</div>
                                <span class="badge bg-light text-secondary border small">${App.escapeHtml(catName)}</span>
                            </div>
                            <h6 class="product-title" title="${App.escapeHtml(p.name)}">${App.escapeHtml(p.name)}</h6>
                            <div class="mb-2">
                                ${stockBadge}
                            </div>
                            <div class="product-meta d-flex justify-content-between align-items-center mt-auto border-top pt-2">
                                <span class="price-tag">${priceFormatted}</span>
                                <button class="btn btn-sm btn-teal-outline rounded-3 px-3 py-1" onclick="event.stopPropagation(); viewProductDetails(${p.id})">
                                    <i class="fas fa-eye me-1"></i>${t('store.view_details')}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                grid.appendChild(col);
            });
        }

        let currentPage = 1;
        let itemsLimit = 12;
        let totalRecords = 0;
        let totalPages = 1;

        // Render Pagination controls
        function renderPagination() {
            const wrapper = document.getElementById('paginationWrapper');
            const list = document.getElementById('paginationList');
            const info = document.getElementById('paginationInfo');

            if (totalRecords === 0 || totalPages <= 1) {
                wrapper.style.setProperty('display', 'none', 'important');
                return;
            }

            wrapper.style.removeProperty('display');
            wrapper.style.display = 'flex';

            const startIdx = (currentPage - 1) * itemsLimit + 1;
            const endIdx = Math.min(currentPage * itemsLimit, totalRecords);
            info.textContent = t('store.page_info', { start: startIdx, end: endIdx, total: totalRecords });

            let html = '';

            // Previous button
            const prevDisabled = currentPage <= 1 ? 'disabled' : '';
            html += `
                <li class="page-item ${prevDisabled}">
                    <button class="page-link" onclick="goToPage(${currentPage - 1})" ${prevDisabled ? 'disabled' : ''} aria-label="Previous">
                        <i class="fas fa-chevron-left me-1"></i><span class="d-none d-sm-inline">${t('store.page_prev')}</span>
                    </button>
                </li>
            `;

            // Calculate pagination window
            const delta = 2;
            const range = [];
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - delta && i <= currentPage + delta)) {
                    range.push(i);
                }
            }

            let lastNum = 0;
            for (const p of range) {
                if (lastNum > 0 && p - lastNum > 1) {
                    html += `<li class="page-item disabled"><span class="page-link border-0">...</span></li>`;
                }
                const activeClass = p === currentPage ? 'active' : '';
                html += `
                    <li class="page-item ${activeClass}">
                        <button class="page-link" onclick="goToPage(${p})">${p}</button>
                    </li>
                `;
                lastNum = p;
            }

            // Next button
            const nextDisabled = currentPage >= totalPages ? 'disabled' : '';
            html += `
                <li class="page-item ${nextDisabled}">
                    <button class="page-link" onclick="goToPage(${currentPage + 1})" ${nextDisabled ? 'disabled' : ''} aria-label="Next">
                        <span class="d-none d-sm-inline">${t('store.page_next')}</span><i class="fas fa-chevron-right ms-1"></i>
                    </button>
                </li>
            `;

            list.innerHTML = html;
        }

        window.goToPage = function(pageNum) {
            if (pageNum < 1 || pageNum > totalPages || pageNum === currentPage) return;
            loadProducts(pageNum);
            const gridEl = document.getElementById('productsGrid');
            if (gridEl) {
                gridEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };

        // Fetch Products with active filters & pagination
        async function loadProducts(page = 1) {
            currentPage = page;
            const grid = document.getElementById('productsGrid');
            const search = document.getElementById('searchInput').value.trim();
            const category_id = document.getElementById('categoryFilter').value;
            const brand_id = document.getElementById('brandFilter').value;
            const availability = document.getElementById('availabilityFilter').value;
            const sort = document.getElementById('sortFilter').value;
            itemsLimit = parseInt(document.getElementById('limitSelect').value) || 12;

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
                    availability: availability,
                    sort: sort,
                    page: currentPage,
                    limit: itemsLimit
                });

                const res = await fetch('api/public_store.php?' + params.toString());
                const result = await res.json();
                productsList = result.data || [];

                if (result.pagination) {
                    currentPage = result.pagination.page;
                    totalRecords = result.pagination.total_records;
                    totalPages = result.pagination.total_pages;
                } else {
                    totalRecords = productsList.length;
                    totalPages = 1;
                }

                renderProducts(productsList);
                renderPagination();
            } catch (err) {
                console.error("Error loading products:", err);
                grid.innerHTML = `
                    <div class="col-12 text-center py-5 text-danger">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                        <p>${t('app.error') || 'Error loading products.'}</p>
                    </div>
                `;
                document.getElementById('paginationWrapper').style.setProperty('display', 'none', 'important');
            }
        }

        // View product details in modal
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
                badgeSpan.innerHTML = `<i class="fas fa-times-circle"></i>${t('store.status_out_of_stock')}`;
            } else if (stockQty <= minStock) {
                badgeSpan.className = 'availability-badge bg-warning-subtle text-warning';
                badgeSpan.innerHTML = `<i class="fas fa-exclamation-triangle"></i>${t('store.status_limited')}`;
            } else {
                badgeSpan.className = 'availability-badge bg-success-subtle text-success';
                badgeSpan.innerHTML = `<i class="fas fa-check-circle"></i>${t('store.status_in_stock')}`;
            }

            // Expiry Date
            const expiryDateEl = document.getElementById('modalExpiryDate');
            if (product.expiry_date && product.expiry_date !== '0000-00-00') {
                expiryDateEl.textContent = product.expiry_date;
            } else {
                expiryDateEl.textContent = t('store.status_not_applicable');
            }

            // Image
            const imgSrc = product.image && product.image !== 'default.jpg' && product.image !== 'default.png' ? 'assets/img/products/' + product.image : 'assets/img/img_holder.png';
            const modalImg = document.getElementById('modalImg');
            modalImg.src = imgSrc;
            modalImg.onerror = function() {
                this.onerror = null;
                this.src = 'assets/img/img_holder.png';
            };

            // Price
            document.getElementById('modalPrice').textContent = parseFloat(product.selling_price).toLocaleString(undefined, {minimumFractionDigits: 2}) + ' ' + storeSettings.currency;

            // WhatsApp / Contact button in modal
            const inquireBtn = document.getElementById('modalInquireBtn');
            const storePhone = (storeSettings.store_phone || storeSettings.store_phone_2 || '').replace(/[^0-9]/g, '');
            if (storePhone) {
                const message = t('store.inquire_msg', { name: product.name, barcode: product.barcode || 'N/A' });
                // Check if international code needed, default Algeria 213 if starts with 0
                let waNumber = storePhone;
                if (waNumber.startsWith('0')) {
                    waNumber = '213' + waNumber.substring(1);
                }
                inquireBtn.href = `https://wa.me/${waNumber}?text=${encodeURIComponent(message)}`;
                inquireBtn.style.display = 'inline-flex';
            } else {
                inquireBtn.style.display = 'none';
            }

            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
        };

        // Filter Event Listeners
        document.getElementById('categoryFilter').addEventListener('change', () => loadProducts(1));
        document.getElementById('brandFilter').addEventListener('change', () => loadProducts(1));
        document.getElementById('availabilityFilter').addEventListener('change', () => loadProducts(1));
        document.getElementById('sortFilter').addEventListener('change', () => loadProducts(1));
        document.getElementById('limitSelect').addEventListener('change', () => loadProducts(1));

        // Reset Filters button
        document.getElementById('btnResetFilters').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearch').style.display = 'none';
            document.getElementById('categoryFilter').value = '';
            document.getElementById('brandFilter').value = '';
            document.getElementById('availabilityFilter').value = 'all';
            document.getElementById('sortFilter').value = 'name_asc';
            document.getElementById('limitSelect').value = '12';
            loadProducts(1);
        });

        // Search Input Events (Debounced & Enter)
        let searchTimeout;
        const searchInput = document.getElementById('searchInput');
        const clearSearch = document.getElementById('clearSearch');

        searchInput.addEventListener('input', (e) => {
            const val = e.target.value;
            clearSearch.style.display = val ? 'block' : 'none';
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadProducts(1);
            }, 300);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                loadProducts(1);
            }
        });

        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            clearSearch.style.display = 'none';
            loadProducts(1);
            searchInput.focus();
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            if (!localStorage.getItem('app_language')) {
                localStorage.setItem('app_language', 'fr');
            }
            if (currentViewMode === 'list') {
                btnViewGrid.classList.remove('active');
                btnViewList.classList.add('active');
                productsGridEl.classList.add('list-mode');
            }
            translatePage();
            loadFilters().then(() => {
                loadProducts();
            });
        });
    </script>
</body>
</html>
