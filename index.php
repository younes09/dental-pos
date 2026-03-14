<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DentalPOS - Premium Stock & POS</title>
    
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <!-- Google Fonts -->
    <!-- Note: Fonts could also be localized if needed, but they are often cached or handled separately. For now, keepingInter/Outfit linkage but localizing CSS/JS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icon Fonts -->
    <link rel="stylesheet" href="assets/vendor/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/css/bootstrap-icons.css">
    
    <!-- Flag Icons -->
    <link rel="stylesheet" href="assets/vendor/css/flag-icons.min.css"/>
    
    <!-- Bootstrap 5 -->
    <link href="assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="assets/vendor/css/dataTables.bootstrap5.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="assets/vendor/css/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Early Theme Load -->
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

    <!-- Sidebar -->
    <nav id="sidebar" class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="fas fa-tooth text-teal"></i>
                <span>Dental<span>POS</span></span>
            </div>
            <button id="sidebarCollapseDesktop" class="btn btn-link d-none d-md-block text-white p-0">
                <i class="fas fa-angles-left"></i>
            </button>
            <button id="sidebarCollapse" class="btn btn-link d-md-none text-white p-0">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <div class="sidebar-content">
            <ul class="list-unstyled components">
            <!-- Main Section -->
            <li class="sidebar-label" data-i18n="sidebar.main">Main</li>
            <li class="active">
                <a href="#dashboard" class="nav-link">
                    <i class="fas fa-chart-pie"></i>
                    <span data-i18n="sidebar.dashboard">Dashboard</span>
                </a>
            </li>

            <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])): ?>
            <li>
                <a href="#salesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-cash-register"></i>
                    <span data-i18n="sidebar.sales">Sales</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="salesSubmenu">
                    <li>
                        <a href="#pos">
                            <i class="fas fa-plus"></i> <span data-i18n="sidebar.pos">Point of Sale</span>
                        </a>
                    </li>
                    <li>
                        <a href="#cash_register">
                            <i class="fas fa-money-bill-transfer"></i> <span data-i18n="sidebar.cash_register">Cash Register</span>
                        </a>
                    </li>
                    <li>
                        <a href="#sales_history">
                            <i class="fas fa-history"></i> <span data-i18n="sidebar.sales_history">Sales History</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Inventory Section -->
            <li class="sidebar-label" data-i18n="sidebar.stock_ops">Stock & Operations</li>
            <li>
                <a href="#inventorySubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-boxes-stacked"></i>
                    <span data-i18n="sidebar.inventory">Inventory</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="inventorySubmenu">
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])): ?>
                    <li>
                        <a href="#purchase_orders">
                            <i class="fas fa-cart-arrow-down"></i> <span data-i18n="sidebar.purchase_orders">Purchase Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="#stock">
                            <i class="fas fa-warehouse"></i> <span data-i18n="sidebar.stock">Stock Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="#equipment">
                            <i class="fas fa-stethoscope"></i> <span data-i18n="sidebar.equipment">Equipment</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#catalog">
                            <i class="fas fa-book-open"></i> <span data-i18n="sidebar.catalog">Catalog</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Contacts Section -->
            <li class="sidebar-label" data-i18n="sidebar.contacts">Contacts</li>
            <li>
                <a href="#contactsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-user-group"></i>
                    <span data-i18n="sidebar.contacts">Contacts</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="contactsSubmenu">
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])): ?>
                    <li>
                        <a href="#customers">
                            <i class="fas fa-users"></i> <span data-i18n="sidebar.customers">Customers</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])): ?>
                    <li>
                        <a href="#suppliers">
                            <i class="fas fa-truck-field"></i> <span data-i18n="sidebar.suppliers">Suppliers</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- Finance Section -->
            <li class="sidebar-label" data-i18n="sidebar.finance">Finance</li>
            <li>
                <a href="#vault" class="nav-link">
                    <i class="fas fa-vault"></i>
                    <span data-i18n="sidebar.treasury">Treasury</span>
                </a>
            </li>
            <?php if (($_SESSION['user_role'] ?? '') === 'Admin'): ?>
            <li>
                <a href="#balance" class="nav-link">
                    <i class="fas fa-scale-balanced"></i>
                    <span data-i18n="sidebar.balance">Financial Balance</span>
                </a>
            </li>
            <li>
                <a href="#salaries" class="nav-link">
                    <i class="fas fa-hand-holding-dollar"></i>
                    <span data-i18n="sidebar.payroll">Payroll</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Administration Section -->
            <?php if (($_SESSION['user_role'] ?? '') === 'Admin'): ?>
            <li class="sidebar-label" data-i18n="sidebar.admin">Administration</li>
            <li>
                <a href="#adminSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-user-shield"></i>
                    <span data-i18n="sidebar.system_admin">System Admin</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="adminSubmenu">
                    <li>
                        <a href="#users">
                            <i class="fas fa-users-gear"></i> <span data-i18n="sidebar.users">User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="#reports">
                            <i class="fas fa-file-invoice-dollar"></i> <span data-i18n="sidebar.reports">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="#analytics">
                            <i class="fas fa-chart-line"></i> <span data-i18n="sidebar.analytics">Analytics</span>
                        </a>
                    </li>
                    <li>
                        <a href="#settings">
                            <i class="fas fa-cog"></i> <span data-i18n="sidebar.settings">Settings</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
        
        </div>
        
        <div class="sidebar-footer">
            <a href="#profile" class="user-info text-decoration-none">
                <img src="assets/vendor/img/default-avatar.png" alt="User" class="rounded-circle">
                <div class="ms-2">
                    <p class="mb-0 fw-bold text-truncate text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                    <small class="text-primary"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'User'); ?></small>
                </div>
            </a>
            <a href="api/auth.php?action=logout" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </nav>

    <!-- Page Content -->
    <div id="content" class="main-content">
        
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg sticky-top">
            <div class="container-fluid">
                <button type="button" id="sidebarToggle" class="btn btn-teal-outline me-3">
                    <i class="fas fa-align-left"></i>
                </button>
                
                <h4 class="mb-0 page-title d-none d-sm-block" id="currentViewTitle">Dashboard</h4>
                
                <div class="ms-auto d-flex align-items-center">
                    <div class="search-box me-3 d-none d-lg-block">
                        <i class="fas fa-search"></i>
                        <input type="text" id="globalSearchInput" class="form-control" placeholder="Search products..." data-i18n="topbar.search_placeholder" data-i18n-target="placeholder">
                    </div>
                    
                    <div id="realtimeClock" class="me-3 d-none d-md-block fw-bold text-teal" style="font-size: 0.95rem;">
                        <!-- Clock will be injected here -->
                    </div>
                    
                    <div class="d-flex align-items-center me-2 border-end pe-2" id="languageSelector">
                        <!-- Flag will be injected by app.js -->
                    </div>

                    <button id="fullscreenToggle" class="btn btn-link text-dark p-2 me-2" title="Toggle Fullscreen" data-i18n="topbar.fullscreen" data-i18n-target="title">
                        <i class="fas fa-expand"></i>
                    </button>
                    
                    <button id="darkModeToggle" class="btn btn-link text-dark p-2 me-2">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-2 position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount" style="display: none;">
                                0
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-3 p-0" style="width: 300px;">
                            <li class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold" data-i18n="topbar.notifications">Notifications</h6>
                                <small class="text-teal pointer" onclick="App.markAllNotificationsRead()" data-i18n="topbar.mark_all_read">Mark all as read</small>
                            </li>
                            <ul class="notification-list list-unstyled mb-0" style="max-height: 300px; overflow-y: auto;">
                                <!-- Dynamic content -->
                            </ul>
                            <li class="text-center p-2 border-top notification-footer">
                                <a href="#notifications" class="small text-muted text-decoration-none" data-i18n="topbar.view_all">View All Notifications</a>
                            </li>
                        </ul>
                    </div>

                    <div class="dropdown ms-3">
                        <button class="btn btn-link dropdown-toggle text-decoration-none d-flex align-items-center p-0" type="button" data-bs-toggle="dropdown">
                            <img src="assets/vendor/img/default-avatar.png" alt="User" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                            <span class="d-none d-md-inline text-dark fw-medium small user-name-nav"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-3 p-2">
                            <li>
                                <a class="dropdown-item rounded-2 py-2" href="#profile">
                                    <i class="fas fa-user-circle me-2 text-teal"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2" href="#settings">
                                    <i class="fas fa-cog me-2 text-teal"></i> Settings
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item rounded-2 py-2 text-danger" href="api/auth.php?action=logout">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Dynamic View Container -->
        <main id="app-viewport" class="p-4">
            <!-- Loader -->
            <div id="view-loader" class="text-center py-5">
                <div class="spinner-border text-teal" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading your view...</p>
            </div>
            
            <!-- View content will be injected here -->
        </main>
        
        <footer class="text-center py-3 text-muted small">
            &copy; 2026 DentalPOS v1.0. All Rights Reserved.
        </footer>
    </div>

    <!-- Scripts -->
    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/js/jquery.dataTables.min.js"></script>
    <script src="assets/vendor/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/vendor/js/chart.min.js"></script>
    <script src="assets/vendor/js/sweetalert2.all.min.js"></script>
    
    <!-- Export Libraries -->
    <script src="assets/vendor/js/jspdf.umd.min.js"></script>
    <script src="assets/vendor/js/jspdf.plugin.autotable.min.js"></script>
    <script src="assets/vendor/js/xlsx.full.min.js"></script>
    
    <!-- Translation Dictionary -->
    <script src="assets/js/locales.js?v=<?= time() ?>"></script>
    
    <!-- Core App Logic -->
    <script src="assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        // Inject user session data for RBAC
        App.state.user = {
            role: <?php echo json_encode($_SESSION['user_role'] ?? 'Cashier'); ?>,
            name: <?php echo json_encode($_SESSION['user_name'] ?? 'User'); ?>
        };
    </script>
</body>
</html>
