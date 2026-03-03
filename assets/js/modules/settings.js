/**
 * Settings Module
 */

const settingsModule = {
    init() {
        this.loadSettings();
        this.bindEvents();
        console.log('Settings Module Loaded');
    },

    bindEvents() {
        document.getElementById('business-settings-form').onsubmit = (e) => {
            e.preventDefault();
            this.saveBusinessInfo();
        };

        document.getElementById('tax-settings-form').onsubmit = (e) => {
            e.preventDefault();
            App.toast('success', 'Tax configuration updated');
        };

        document.getElementById('settingsDarkModeToggle').onchange = (e) => {
            App.toggleDarkMode();
        };

        // Sync toggle state
        document.getElementById('settingsDarkModeToggle').checked = document.body.classList.contains('dark-mode');
    },

    loadSettings() {
        // Load from localStorage or API
    },

    saveBusinessInfo() {
        App.toast('success', 'Business settings saved successfully');
    },

    backupDB() {
        App.toast('info', 'Generating SQL backup... please wait');
        setTimeout(() => {
            App.toast('success', 'Backup ready! Download starting...');
        }, 1500);
    },

    clearCache() {
        Swal.fire({
            title: 'Clear Cache?',
            text: "This will reset local preferences!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            confirmButtonText: 'Yes, clear it'
        }).then((result) => {
            if (result.isConfirmed) {
                localStorage.clear();
                window.location.reload();
            }
        });
    }
};

// Initialize
if (document.getElementById('settingsDarkModeToggle')) {
    settingsModule.init();
}

window.settingsModule = settingsModule;
