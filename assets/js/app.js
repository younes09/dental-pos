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
        }
    },

    async init() {
        await this.loadAppSettings();
        this.bindEvents();
        this.handleRouting();
        this.applySettings();
        console.log('DentalPOS Initialized');
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
                if (href && href.startsWith('#')) {
                    // Force navigation if it's the same hash (optional but helps refresh)
                    if (window.location.hash === href) {
                        this.handleRouting();
                    }

                    // Close sidebar on small screens after clicking a link
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
        const route = hash.substring(1);

        console.log('Handling route:', route);

        // Allow re-loading the same route if requested (e.g. clicking active link)
        this.state.currentRoute = route;
        this.updateActiveNavLink(hash);
        await this.loadView(route);
    },

    updateActiveNavLink(hash) {
        console.log('Updating active nav link for:', hash);
        let found = false;

        document.querySelectorAll('.sidebar ul li').forEach(li => {
            li.classList.remove('active');
            const link = li.querySelector('a');

            if (link && link.getAttribute('href') === hash) {
                li.classList.add('active');
                found = true;

                // Update Page Title
                const titleSpan = link.querySelector('span');
                if (titleSpan) {
                    const title = titleSpan.textContent;
                    const titleEl = document.getElementById('currentViewTitle');
                    if (titleEl) titleEl.textContent = title;
                }
            }
        });

        // Fallback for dashboard if nothing else matches
        if (!found && hash !== '#dashboard') {
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
        if (!document.getElementById(scriptId)) {
            const script = document.createElement('script');
            script.id = scriptId;
            script.src = `assets/js/modules/${viewName}.js`;
            script.onerror = () => {
                console.warn(`No logic module found for ${viewName}`);
            };
            document.body.appendChild(script);
        } else {
            // If already loaded, we might need to re-init some parts
            // This depends on how module JS is structured
            if (window[viewName + 'Module'] && window[viewName + 'Module'].init) {
                window[viewName + 'Module'].init();
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
