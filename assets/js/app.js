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
            'dashboard': ['Admin', 'Stock Manager'],
            'pos': ['Admin', 'Cashier'],
            'cash_register': ['Admin', 'Cashier'],
            'sales_history': ['Admin', 'Cashier'],
            'purchase_orders': ['Admin', 'Stock Manager'],
            'stock': ['Admin', 'Stock Manager'],
            'vault': ['Admin'],
            'catalog': ['Admin', 'Cashier', 'Stock Manager'],
            'customers': ['Admin', 'Cashier'],
            'suppliers': ['Admin', 'Stock Manager'],
            'equipment': ['Admin', 'Stock Manager'],
            'users': ['Admin'],
            'reports': ['Admin'],
            'analytics': ['Admin'],
            'salaries': ['Admin'],
            'balance': ['Admin'],
            'settings': ['Admin'],
            'profile': ['Admin', 'Cashier', 'Stock Manager'],
            'notifications': ['Admin', 'Cashier', 'Stock Manager']
        }
    },

    async init() {
        this.applyTheme();
        this.renderLanguageSelector();
        this.translate(); // Translate index.php core UI

        await this.loadAppSettings();
        this.bindEvents();
        this.handleRouting();
        this.applySettings();
        this.loadSidebarState();

        // Start Realtime Clock
        this.startRealtimeClock();

        // Start Notifications
        this.loadNotifications();
        setInterval(() => this.loadNotifications(), 60000); // Check every minute

        console.log('DentalPOS Initialized');
    },

    // Translation Helpers
    t(key, params = null) {
        const lang = localStorage.getItem('app_language') || 'fr';
        // Fallback to English if translation is missing
        let translation = (locales[lang] && locales[lang][key]) || (locales['en'] && locales['en'][key]) || key;

        if (params) {
            Object.keys(params).forEach(p => {
                translation = translation.replace(`{${p}}`, params[p]);
            });
        }
        return translation;
    },

    escapeHtml(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    },

    getDataTableLanguage() {
        return {
            emptyTable: this.t('dt.emptyTable'),
            info: this.t('dt.info'),
            infoEmpty: this.t('dt.infoEmpty'),
            infoFiltered: this.t('dt.infoFiltered'),
            lengthMenu: this.t('dt.lengthMenu'),
            loadingRecords: this.t('dt.loadingRecords'),
            processing: this.t('dt.processing'),
            search: this.t('dt.search'),
            zeroRecords: this.t('dt.zeroRecords'),
            paginate: {
                first: this.t('dt.paginate.first'),
                last: this.t('dt.paginate.last'),
                next: this.t('dt.paginate.next'),
                previous: this.t('dt.paginate.previous')
            }
        };
    },

    translate(container = document) {
        container.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const target = el.getAttribute('data-i18n-target') || 'text';
            const translation = this.t(key);

            if (target === 'text') {
                el.textContent = translation;
            } else if (target === 'html') {
                el.innerHTML = translation;
            } else if (target === 'placeholder') {
                el.setAttribute('placeholder', translation);
            }
        });
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

    renderLanguageSelector() {
        const container = document.getElementById('languageSelector');
        if (!container) return;

        const currentLang = localStorage.getItem('app_language') || 'fr';

        if (currentLang === 'fr') {
            container.innerHTML = `
                <button class="btn btn-link text-decoration-none p-1 tooltip-btn" title="Switch to English" onclick="App.setLanguage('en')">
                    <span class="fi fi-gb fis fs-5 shadow-sm rounded-circle"></span>
                </button>
            `;
        } else {
            container.innerHTML = `
                <button class="btn btn-link text-decoration-none p-1 tooltip-btn" title="Passer en Français" onclick="App.setLanguage('fr')">
                    <span class="fi fi-fr fis fs-5 shadow-sm rounded-circle"></span>
                </button>
            `;
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

        document.getElementById('sidebarCollapseDesktop')?.addEventListener('click', () => {
            this.toggleSidebarCollapse();
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
                if (!query) return;

                const viewName = this.state.currentRoute;

                // Try to find ANY DataTable API instance on the current page
                let dataTable = null;
                try {
                    // Method 1: Check the expected module
                    const module = window[`${viewName}Module`];
                    if (module && module.table) dataTable = module.table;

                    // Method 2: Use DataTables internal tracker
                    if (!dataTable || typeof dataTable.search !== 'function') {
                        const tables = $.fn.dataTable.tables({ api: true });
                        if (tables.length > 0) dataTable = tables[0];
                    }

                    // Method 3: Direct attempt on common table IDs
                    if (!dataTable || typeof dataTable.search !== 'function') {
                        const commonIds = ['#productsTable', '#customersTable', '#suppliersTable', '#salesHistoryTable'];
                        for (const id of commonIds) {
                            if ($(id).length) {
                                dataTable = $(id).DataTable();
                                break;
                            }
                        }
                    }
                } catch (err) {
                    console.error('Error finding DataTable:', err);
                }

                if (dataTable && typeof dataTable.search === 'function') {
                    console.log(`Global search: filtering table for "${query}"`);
                    dataTable.search(query).draw();
                } else if (viewName === 'pos') {
                    if (window.posModule && typeof window.posModule.filterProducts === 'function') {
                        window.posModule.filterProducts(query);
                    }
                } else {
                    console.log('Global search: no table found, redirecting to POS');
                    window.location.hash = `#pos?search=${encodeURIComponent(query)}`;
                }
            }
        });

        // Dark Mode Toggle
        document.getElementById('darkModeToggle').addEventListener('click', () => {
            this.toggleDarkMode();
        });

        // Fullscreen Toggle
        document.getElementById('fullscreenToggle')?.addEventListener('click', () => {
            this.toggleFullscreen();
        });

        document.addEventListener('fullscreenchange', () => {
            const icon = document.querySelector('#fullscreenToggle i');
            if (icon) {
                if (document.fullscreenElement) {
                    icon.classList.replace('fa-expand', 'fa-compress');
                } else {
                    icon.classList.replace('fa-compress', 'fa-expand');
                }
            }
        });
    },

    toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('content').classList.toggle('active');
    },

    closeSidebar() {
        document.getElementById('sidebar').classList.remove('active');
        document.getElementById('content').classList.remove('active');
    },

    toggleSidebarCollapse() {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        const icon = document.querySelector('#sidebarCollapseDesktop i');

        sidebar.classList.toggle('collapsed');
        content.classList.toggle('sidebar-collapsed');

        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);

        if (icon) {
            icon.classList.replace(isCollapsed ? 'fa-angles-left' : 'fa-angles-right', isCollapsed ? 'fa-angles-right' : 'fa-angles-left');
        }
    },

    loadSidebarState() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            const icon = document.querySelector('#sidebarCollapseDesktop i');

            sidebar.classList.add('collapsed');
            content.classList.add('sidebar-collapsed');
            if (icon) icon.classList.replace('fa-angles-left', 'fa-angles-right');
        }
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

            // Translate View
            this.translate(viewport);

            // Initialize module logic
            this.initModule(viewName);

            // Scroll to top
            window.scrollTo(0, 0);

        } catch (error) {
            console.error('Routing Error:', error);
            // Fix #14: Escape error.message to prevent XSS
            viewport.innerHTML = `
                <div class="text-center py-5 fade-in">
                    <i class="fas fa-exclamation-circle text-danger display-1 mb-3"></i>
                    <h3 class="fw-bold">Oops! Module Error</h3>
                    <p class="text-muted">${this.escapeHtml(error.message)}</p>
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

    setLanguage(lang) {
        localStorage.setItem('app_language', lang);
        window.location.reload();
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

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.error(`Error attempting to enable full-screen mode: ${err.message}`);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
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
        const num = parseFloat(amount);
        return `${(isNaN(num) ? 0 : num).toFixed(2)} ${symbol}`;
    },

    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
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
    },

    async confirm(title, text, icon = 'question') {
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirm',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        });
        return result.isConfirmed;
    },

    startRealtimeClock() {
        const clockEl = document.getElementById('realtimeClock');
        if (!clockEl) return;

        const updateClock = () => {
            const now = new Date();
            const lang = localStorage.getItem('app_language') || 'fr';

            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const dateOptions = { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' };

            const timeStr = now.toLocaleTimeString(lang, timeOptions);
            const dateStr = now.toLocaleDateString(lang, dateOptions);

            clockEl.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="far fa-calendar-alt text-muted me-2"></i>
                    <span class="me-3 ">${dateStr}</span>
                    <i class="far fa-clock text-muted me-2"></i>
                    <span class="">${timeStr}</span>
                </div>
            `;
        };

        updateClock();
        setInterval(updateClock, 1000);
    },

    async loadNotifications() {
        const result = await this.api('notifications.php?action=list');
        if (result && result.data) {
            this.renderNotifications(result.data);
        }
    },

    renderNotifications(notifications) {
        const countEl = document.getElementById('notificationCount');
        const listEl = document.querySelector('.notification-list');

        if (!countEl) return;

        const count = notifications.length;

        // Real-time toast logic: If count increased, show the newest notification
        const previousCount = parseInt(countEl.textContent) || 0;
        if (count > previousCount && notifications.length > 0) {
            const newest = notifications[0]; // Assuming API returns newest first
            Swal.fire({
                toast: true,
                position: 'bottom-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                icon: newest.type === 'danger' ? 'error' : newest.type, // Map 'danger' to SweetAlert's 'error'
                title: newest.title,
                text: newest.message
            });
        }

        countEl.textContent = count;
        countEl.style.display = count > 0 ? 'block' : 'none';

        if (!listEl) return;

        if (count === 0) {
            listEl.innerHTML = `
                <li class="p-4 text-center text-muted">
                    <i class="fas fa-bell-slash fa-2x mb-2 opacity-25"></i>
                    <p class="mb-0 small" data-i18n="topbar.no_notifications">No new notifications</p>
                </li>`;
        } else {
            listEl.innerHTML = notifications.map(n => {
                const safeLink = this.escapeHtml(n.link || '');
                return `
                <li class="notification-item p-3 border-bottom unread pointer" data-notif-id="${n.id}" data-notif-link="${safeLink}">
                    <div class="d-flex align-items-start">
                        <div class="icon-circle bg-${this.escapeHtml(n.type)}-subtle text-${this.escapeHtml(n.type)} me-3">
                            <i class="fas ${this.getNotificationIcon(n.type)}"></i>
                        </div>
                        <div class="flex-grow-1" style="width: 80%;">
                            <p class="mb-0 small fw-bold">${this.escapeHtml(n.title)}</p>
                            <p class="mb-0 small text-muted text-wrap" style="line-height: 1.3;">${this.escapeHtml(n.message)}</p>
                        </div>
                    </div>
                </li>
            `;
            }).join('');

            // Bind click events safely instead of inline onclick
            listEl.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', () => {
                    const id = item.dataset.notifId;
                    const link = item.dataset.notifLink;
                    App.handleNotificationClick(id, link);
                });
            });
        }

        // Translate the newly injected content
        this.translate(listEl);
    },

    getNotificationIcon(type) {
        const icons = {
            'warning': 'fa-triangle-exclamation',
            'info': 'fa-circle-info',
            'success': 'fa-circle-check',
            'danger': 'fa-circle-xmark'
        };
        return icons[type] || 'fa-bell';
    },

    async handleNotificationClick(id, link) {
        // Bug #13 Fix: Use POST for mutative action to prevent CSRF
        await this.api('notifications.php?action=mark_read', 'POST', { id: id });
        if (link) window.location.hash = link;
        this.loadNotifications();
    },

    async markAllNotificationsRead() {
        // Bug #13 Fix: Use POST for mutative action
        await this.api('notifications.php?action=mark_all_read', 'POST');
        this.loadNotifications();
    }
};

// Initialize App
document.addEventListener('DOMContentLoaded', () => App.init());
