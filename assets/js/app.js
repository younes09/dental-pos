/**
 * DentalPOS Core Application
 */

const App = {
    config: {
        apiBase: 'api/',
        viewBase: 'views/'
    },

    state: {
        currentRoute: '',
        user: null,
        notifications: [],
        settings: {
            currency: '$',
            store_name: 'DentalPOS',
            vat_rate: 0
        },
        permissions: {
            'dashboard': ['Admin', 'Cashier', 'Stock Manager'],
            'pos': ['Admin', 'Cashier'],
            'sales_history': ['Admin', 'Cashier'],
            'purchase_orders': ['Admin', 'Stock Manager'],
            'stock': ['Admin', 'Stock Manager'],
            'catalog': ['Admin', 'Cashier', 'Stock Manager'],
            'customers': ['Admin', 'Cashier'],
            'suppliers': ['Admin', 'Stock Manager'],
            'users': ['Admin'],
            'reports': ['Admin'],
            'settings': ['Admin'],
            'profile': ['Admin', 'Cashier', 'Stock Manager']
        }
    },

    async init() {
        this.applyTheme();
        await this.loadAppSettings();
        this.bindEvents();
        this.handleRouting();
        this.applySettings();
        console.log('DentalPOS Initialized');
    },

    applyTheme() {
        const theme = localStorage.getItem('theme');
        const icon = document.querySelector('#darkModeToggle i');

        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            if (icon) icon.classList.replace('fa-moon', 'fa-sun');
        } else {
            document.body.classList.remove('dark-mode');
            if (icon) icon.classList.replace('fa-sun', 'fa-moon');
        }
    },

    async loadAppSettings() {
        const settings = await this.api('settings.php');
        if (settings) {
            this.state.settings = { ...this.state.settings, ...settings };
        }
    },

    applySettings() {
        // Apply global settings like theme or store name in title
        if (this.state.settings.store_name) {
            document.title = `${this.state.settings.store_name} - Premium Stock & POS`;
        }

        this.updateStaticCurrency();
    },

    updateStaticCurrency() {
        const symbol = this.state.settings.currency || '$';
        document.querySelectorAll('.currency-symbol').forEach(el => {
            // If it's an input-group-text, it usually stays as is but we can ensure it's correct
            el.textContent = symbol;

            // For elements that contain both value and symbol class, we might need more logic
            // but usually it's used as a standalone label or prefix/suffix
        });
    },

    bindEvents() {
        // Sidebar Toggles
        document.getElementById('sidebarToggle').addEventListener('click', () => {
            this.toggleSidebar();
        });

        document.getElementById('sidebarCollapse')?.addEventListener('click', () => {
            this.toggleSidebar();
        });

        // Navigation clicks
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                const href = link.getAttribute('href');

                // If it's a dropdown toggle (submenu trigger), handled by Bootstrap data-attributes
                if (link.classList.contains('dropdown-toggle')) {
                    return;
                }

                if (href && href.startsWith('#')) {
                    // Force navigation if it's the same hash
                    if (window.location.hash === href) {
                        this.handleRouting();
                    }

                    // Close sidebar on small screens after clicking a final link
                    if (window.innerWidth <= 992) {
                        this.closeSidebar();
                    }
                }
            });
        });

        // Hash Change Router
        window.addEventListener('hashchange', () => {
            console.log('Hash changed:', window.location.hash);
            this.handleRouting();
        });

        // Global Search
        document.getElementById('globalSearchInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const query = e.target.value.trim();
                if (query) {
                    window.location.hash = `#pos?search=${encodeURIComponent(query)}`;
                    e.target.value = '';
                }
            }
        });

        // Dark Mode Toggle
        document.getElementById('darkModeToggle').addEventListener('click', () => {
            this.toggleDarkMode();
        });
    },

    toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('content').classList.toggle('active');
    },

    closeSidebar() {
        document.getElementById('sidebar').classList.add('active'); // On mobile 'active' means hidden or shown? 
        // Wait, let's check CSS again.
        // @media (max-width: 992px) { .sidebar { margin-left: -260px; } .sidebar.active { margin-left: 0; } }
        // So on mobile, 'active' means SHOWN. To CLOSE it, we REMOVE 'active'.
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('content').classList.remove('active');
    },

    async handleRouting() {
        const hash = window.location.hash || '#dashboard';
        const fullRoute = hash.substring(1);

        // Split view name from query parameters (e.g., #pos?search=... -> pos)
        const [viewName, queryStr] = fullRoute.split('?');

        console.log('Handling route:', viewName, 'with params:', queryStr);

        // RBAC Check
        const userRole = (this.state.user?.role || 'Cashier').trim();
        const allowedRoles = this.state.permissions[viewName];

        if (allowedRoles && !allowedRoles.includes(userRole)) {
            console.warn(`Access denied for ${userRole} to view: ${viewName}`);
            this.toast('error', 'You do not have permission to access this module.');
            window.location.hash = '#dashboard';
            return;
        }

        this.state.currentRoute = viewName;
        this.updateActiveNavLink(`#${viewName}`);
        await this.loadView(viewName);
    },

    updateActiveNavLink(hash) {
        console.log('Updating active nav link for:', hash);
        let found = false;

        // Clear all active and section-active states first
        document.querySelectorAll('.sidebar li, .sidebar-footer a').forEach(el => {
            el.classList.remove('active');
            el.classList.remove('section-active');
        });

        // Find and activate the link
        document.querySelectorAll('.sidebar a, .sidebar-footer a').forEach(link => {
            if (link.getAttribute('href') === hash) {
                // For main list items, activate the parent LI
                const li = link.closest('li');
                if (li) {
                    li.classList.add('active');
                } else {
                    // For footer links, activate the link itself
                    link.classList.add('active');
                }
                found = true;

                // Update Page Title
                let titleText = link.querySelector('span')?.textContent || link.textContent.trim();

                // Specific override for Profile link in footer (shows user name instead of "Profile")
                if (hash === '#profile') titleText = 'User Profile';

                const titleEl = document.getElementById('currentViewTitle');
                if (titleEl) titleEl.textContent = titleText;

                // If it's a submenu link, make sure the parent is expanded and highlighted
                const parentSubmenu = link.closest('.submenu');
                if (parentSubmenu) {
                    const collapseInstance = bootstrap.Collapse.getOrCreateInstance(parentSubmenu, { toggle: false });
                    collapseInstance.show();

                    // Mark the parent nav item as section-active
                    const parentNavItem = parentSubmenu.closest('li');
                    if (parentNavItem) {
                        parentNavItem.classList.add('section-active');
                    }
                }
            }
        });

        // Fallback for dashboard if nothing else matches
        if (!found && hash !== '#dashboard' && !hash.includes('?')) {
            this.updateActiveNavLink('#dashboard');
        }
    },

    async loadView(viewName) {
        console.log('Loading view:', viewName);
        const viewport = document.getElementById('app-viewport');
        const loader = document.getElementById('view-loader');

        if (!viewport) {
            console.error('App viewport NOT found!');
            return;
        }

        // Show loader
        if (loader) {
            loader.classList.remove('d-none');
            viewport.innerHTML = '';
            viewport.appendChild(loader);
        } else {
            viewport.innerHTML = '<div class="text-center py-5">Loading...</div>';
        }

        try {
            const response = await fetch(`${this.config.viewBase}${viewName}.html?v=${Date.now()}`);
            if (!response.ok) throw new Error(`View "${viewName}" not found (HTTP ${response.status})`);

            const html = await response.text();

            // Inject content
            viewport.innerHTML = `<div class="fade-in">${html}</div>`;

            // Update static currency symbols in the newly loaded view
            this.updateStaticCurrency();

            // Initialize module logic
            this.initModule(viewName);

            // Scroll to top
            window.scrollTo(0, 0);

        } catch (error) {
            console.error('Routing Error:', error);
            viewport.innerHTML = `
                <div class="text-center py-5 fade-in">
                    <i class="fas fa-exclamation-circle text-danger display-1 mb-3"></i>
                    <h3 class="fw-bold">Oops! Module Error</h3>
                    <p class="text-muted">${error.message}</p>
                    <a href="#dashboard" class="btn btn-teal mt-3 px-4">
                        <i class="fas fa-home me-2"></i>Return to Dashboard
                    </a>
                </div>
            `;
        }
    },

    initModule(viewName) {
        // Load module-specific JS if exists
        const scriptId = `script-${viewName}`;
        const moduleObjectName = `${viewName}Module`;

        if (!document.getElementById(scriptId)) {
            const script = document.createElement('script');
            script.id = scriptId;
            script.src = `assets/js/modules/${viewName}.js?v=${Date.now()}`;
            script.onerror = () => {
                console.warn(`No logic module found for ${viewName}`);
            };
            document.body.appendChild(script);
        } else {
            // If already loaded, re-initialize to refresh data
            if (window[moduleObjectName] && typeof window[moduleObjectName].init === 'function') {
                console.log(`Re-initializing module: ${moduleObjectName}`);
                window[moduleObjectName].init();
            }
        }
    },

    toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        const icon = document.querySelector('#darkModeToggle i');
        if (document.body.classList.contains('dark-mode')) {
            icon.classList.replace('fa-moon', 'fa-sun');
            localStorage.setItem('theme', 'dark');
        } else {
            icon.classList.replace('fa-sun', 'fa-moon');
            localStorage.setItem('theme', 'light');
        }

        // Notify modules of theme change
        window.dispatchEvent(new Event('themeChanged'));
    },

    // Global API Utility
    async api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };

        if (data) options.body = JSON.stringify(data);

        try {
            const response = await fetch(`${this.config.apiBase}${endpoint}`, options);
            const result = await response.json();

            if (result.error) {
                this.toast('error', result.error);
                return null;
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            this.toast('error', 'Network error or server unavailable');
            return null;
        }
    },

    formatCurrency(amount) {
        const symbol = this.state.settings.currency || '$';
        return `${parseFloat(amount).toFixed(2)} ${symbol}`;
    },

    toast(icon, title) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            icon: icon,
            title: title
        });
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => App.init());
