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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icon Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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
            <!-- Operations Section -->
            <li class="sidebar-label">Operations</li>
            <li class="active">
                <a href="#dashboard" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])): ?>
            <li>
                <a href="#salesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-cash-register"></i>
                    <span>Sales</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="salesSubmenu">
                    <li>
                        <a href="#pos">
                            <i class="fas fa-plus"></i> Point of Sale
                        </a>
                    </li>
                    <li>
                        <a href="#cash_register">
                            <i class="fas fa-cash-register"></i> Gestion Caisse
                        </a>
                    </li>
                    <li>
                        <a href="#sales_history">
                            <i class="fas fa-history"></i> Sales History
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Inventory Section -->
            <li class="sidebar-label">Management</li>
            <li>
                <a href="#inventorySubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-boxes-stacked"></i>
                    <span>Inventory</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="inventorySubmenu">
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])): ?>
                    <li>
                        <a href="#stock">
                            <i class="fas fa-warehouse"></i> Stock Management
                        </a>
                    </li>
                    <li>
                        <a href="#purchase_orders">
                            <i class="fas fa-file-invoice"></i> Purchase Orders
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#catalog">
                            <i class="fas fa-list-ul"></i> Catalog
                        </a>
                    </li>
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])): ?>
                    <li>
                        <a href="#equipment">
                            <i class="fas fa-tools"></i> Equipment
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- Contacts Section -->
            <li>
                <a href="#contactsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-user-group"></i>
                    <span>Contacts</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="contactsSubmenu">
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Cashier'])): ?>
                    <li>
                        <a href="#customers">
                            <i class="fas fa-users"></i> Customers
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array($_SESSION['user_role'] ?? '', ['Admin', 'Stock Manager'])): ?>
                    <li>
                        <a href="#suppliers">
                            <i class="fas fa-truck-field"></i> Suppliers
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>

            <!-- Finance Section -->
            <li class="sidebar-label">Finance</li>
            <li>
                <a href="#vault" class="nav-link">
                    <i class="fas fa-vault"></i>
                    <span>Trésorerie</span>
                </a>
            </li>
            <?php if (($_SESSION['user_role'] ?? '') === 'Admin'): ?>
            <li>
                <a href="#salaries" class="nav-link">
                    <i class="fas fa-hand-holding-dollar"></i>
                    <span>Salaires</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Administration Section -->
            <?php if (($_SESSION['user_role'] ?? '') === 'Admin'): ?>
            <li class="sidebar-label">Administration</li>
            <li>
                <a href="#adminSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle nav-link">
                    <i class="fas fa-user-shield"></i>
                    <span>System Admin</span>
                </a>
                <ul class="collapse list-unstyled submenu" id="adminSubmenu">
                    <li>
                        <a href="#users">
                            <i class="fas fa-users-gear"></i> User Management
                        </a>
                    </li>
                    <li>
                        <a href="#reports">
                            <i class="fas fa-file-invoice-dollar"></i> Reports
                        </a>
                    </li>
                    <li>
                        <a href="#settings">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
        
        </div>
        
        <div class="sidebar-footer">
            <a href="#profile" class="user-info text-decoration-none">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=00BFA6&color=fff" alt="User" class="rounded-circle">
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
                        <input type="text" id="globalSearchInput" class="form-control" placeholder="Search products...">
                    </div>
                    
                    <button id="darkModeToggle" class="btn btn-link text-dark p-2 me-2">
                        <i class="fas fa-moon"></i>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-link text-dark p-2 position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount">
                                3
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-3 p-0" style="width: 300px;">
                            <li class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Notifications</h6>
                                <small class="text-teal pointer">Mark all as read</small>
                            </li>
                            <li class="notification-item p-3 border-bottom unread">
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle bg-warning-subtle text-warning me-3">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div>
                                        <p class="mb-1 small fw-medium">Low stock: Latex Gloves</p>
                                        <small class="text-muted">5 minutes ago</small>
                                    </div>
                                </div>
                            </li>
                            <li class="notification-item p-3 border-bottom">
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle bg-info-subtle text-info me-3">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div>
                                        <p class="mb-1 small fw-medium">New PO received: #PO-123</p>
                                        <small class="text-muted">1 hour ago</small>
                                    </div>
                                </div>
                            </li>
                            <li class="text-center p-2">
                                <a href="#" class="small text-muted text-decoration-none">View All Notifications</a>
                            </li>
                        </ul>
                    </div>

                    <div class="dropdown ms-3">
                        <button class="btn btn-link dropdown-toggle text-decoration-none d-flex align-items-center p-0" type="button" data-bs-toggle="dropdown">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=00BFA6&color=fff" alt="User" class="rounded-circle me-2" style="width: 32px; height: 32px;">
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
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Core App Logic -->
    <script src="assets/js/app.js"></script>
    <script>
        // Inject user session data for RBAC
        App.state.user = {
            role: '<?php echo $_SESSION['user_role'] ?? 'Cashier'; ?>',
            name: '<?php echo $_SESSION['user_name'] ?? 'User'; ?>'
        };
    </script>
</body>
</html>
