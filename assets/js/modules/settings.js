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
            this.saveTaxInfo();
        };

        const loyaltyForm = document.getElementById('loyalty-settings-form');
        if (loyaltyForm) {
            loyaltyForm.onsubmit = (e) => {
                e.preventDefault();
                this.saveLoyaltyInfo();
            };
        }

        const uploadLogoBtn = document.getElementById('btn-upload-logo');
        if (uploadLogoBtn) {
            uploadLogoBtn.onclick = async () => {
                const fileInput = document.getElementById('store-logo-file');
                const file = fileInput.files[0];
                if (!file) {
                    App.toast('error', App.t('settings.js.select_logo') || 'Please select a logo file first');
                    return;
                }

                const formData = new FormData();
                formData.append('logo_file', file);

                try {
                    uploadLogoBtn.disabled = true;
                    uploadLogoBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Uploading...`;

                    const response = await fetch('api/settings.php?action=upload_logo', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        App.toast('success', App.t('settings.js.logo_uploaded') || 'Logo uploaded successfully');
                        document.getElementById('store-logo-preview').src = result.logo_path;
                        App.state.settings.store_logo = result.logo_path;
                        fileInput.value = ''; // Reset input
                    } else {
                        App.toast('error', result.error || 'Failed to upload logo');
                    }
                } catch (error) {
                    App.toast('error', 'Error: ' + error.message);
                } finally {
                    uploadLogoBtn.disabled = false;
                    uploadLogoBtn.innerHTML = App.t('settings.business.btn_upload_logo') || 'Upload Logo';
                }
            };
        }

        document.getElementById('settingsDarkModeToggle').onchange = (e) => {
            App.toggleDarkMode();
        };

        // Sync toggle state
        document.getElementById('settingsDarkModeToggle').checked = document.body.classList.contains('dark-mode');
    },

    async loadSettings() {
        const settings = await App.api('settings.php');
        if (settings) {
            // Fill Business Info Form
            const businessForm = document.getElementById('business-settings-form');
            if (businessForm) {
                if (settings.store_name) businessForm.elements['store_name'].value = settings.store_name;
                if (settings.tax_number) businessForm.elements['tax_number'].value = settings.tax_number;
                if (settings.currency) businessForm.elements['currency'].value = settings.currency;
                if (settings.store_phone) businessForm.elements['store_phone'].value = settings.store_phone;
                if (settings.address) businessForm.elements['address'].value = settings.address;
                if (settings.nif) businessForm.elements['nif'].value = settings.nif;
                if (settings.nis) businessForm.elements['nis'].value = settings.nis;
                if (settings.rc) businessForm.elements['rc'].value = settings.rc;
            }

            // Fill Tax Rate Form
            const taxForm = document.getElementById('tax-settings-form');
            if (taxForm) {
                if (settings.vat_rate) taxForm.elements['vat_rate'].value = settings.vat_rate;
            }

            // Fill Loyalty Form
            const loyaltyForm = document.getElementById('loyalty-settings-form');
            if (loyaltyForm) {
                if (settings.loyalty_earning_rate) loyaltyForm.elements['loyalty_earning_rate'].value = settings.loyalty_earning_rate;
                if (settings.loyalty_point_value) loyaltyForm.elements['loyalty_point_value'].value = settings.loyalty_point_value;
            }

            // Fill Logo Preview
            const logoPreview = document.getElementById('store-logo-preview');
            if (logoPreview) {
                if (settings.store_logo) {
                    logoPreview.src = settings.store_logo;
                } else {
                    logoPreview.src = 'assets/img/logo.png';
                }
            }
        }
    },

    async saveBusinessInfo() {
        const form = document.getElementById('business-settings-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await App.api('settings.php', 'POST', data);
        if (result && result.success) {
            App.toast('success', App.t('settings.js.business_saved') || 'Business settings saved successfully');

            // Update app state for immediate effect
            Object.assign(App.state.settings, data);
            App.applySettings();
        }
    },

    async saveTaxInfo() {
        const form = document.getElementById('tax-settings-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await App.api('settings.php', 'POST', data);
        if (result && result.success) {
            App.toast('success', App.t('settings.js.tax_updated') || 'Tax configuration updated');

            // Update app state
            if (data.vat_rate) App.state.settings.vat_rate = data.vat_rate;
        }
    },

    async saveLoyaltyInfo() {
        const form = document.getElementById('loyalty-settings-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await App.api('settings.php', 'POST', data);
        if (result && result.success) {
            App.toast('success', App.t('settings.js.loyalty_updated') || 'Loyalty settings updated');

            // Immediately update the app state
            if (data.loyalty_earning_rate) App.state.settings.loyalty_earning_rate = data.loyalty_earning_rate;
            if (data.loyalty_point_value) App.state.settings.loyalty_point_value = data.loyalty_point_value;
        }
    },

    backupDB() {
        App.toast('info', App.t('settings.js.backup_generating') || 'Generating SQL backup... please wait');
        // Trigger download via hidden iframe or direct navigation
        // Direct navigation is often simplest for downloads
        window.location.href = 'api/settings.php?action=backup';

        // Hide loading message after a short delay (the browser will handle the download in background)
        setTimeout(() => {
            App.toast('success', App.t('settings.js.backup_sent') || 'Backup request sent');
        }, 2000);
    },

    triggerRestore() {
        document.getElementById('dbRestoreInput').click();
    },

    async restoreDB(input) {
        if (!input.files || !input.files[0]) return;

        const file = input.files[0];

        const { isConfirmed } = await Swal.fire({
            title: App.t('settings.js.restore_title') || 'Restore Database?',
            text: App.t('settings.js.restore_text') || "This will OVERWRITE your current data with the backup file. This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: App.t('settings.js.btn_restore') || 'Yes, restore it!',
            cancelButtonText: App.t('users.js.btn_cancel') || 'Cancel'
        });

        if (!isConfirmed) {
            input.value = ''; // Reset input
            return;
        }

        App.toast('info', App.t('settings.js.restoring') || 'Restoring database... please do not close the window');

        const formData = new FormData();
        formData.append('backup_file', file);

        try {
            const response = await fetch('api/settings.php?action=restore', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result && result.success) {
                await Swal.fire({
                    title: App.t('settings.js.restore_success_title') || 'Restored!',
                    text: App.t('settings.js.restore_success_text') || 'Database has been successfully restored. The application will now reload.',
                    icon: 'success'
                });
                window.location.reload();
            } else {
                App.toast('error', result.error || App.t('settings.js.restore_fail') || 'Restore failed');
            }
        } catch (error) {
            console.error('Restore Error:', error);
            App.toast('error', App.t('settings.js.restore_error') || 'Network error during restore');
        } finally {
            input.value = ''; // Reset input
        }
    },

    clearCache() {
        Swal.fire({
            title: App.t('settings.js.cache_title') || 'Clear Cache?',
            text: App.t('settings.js.cache_text') || "This will reset local preferences!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00BFA6',
            confirmButtonText: App.t('settings.js.btn_clear') || 'Yes, clear it',
            cancelButtonText: App.t('users.js.btn_cancel') || 'Cancel'
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
