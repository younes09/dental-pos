/**
 * Profile Module Logic
 */

const profileModule = {
    init() {
        if (!document.getElementById('profileNameDisplay')) {
            console.warn('Profile view elements not found, skipping init');
            return;
        }
        console.log('Profile Module Initialized');
        this.loadProfile();
        this.bindEvents();
    },

    async loadProfile() {
        const response = await App.api('profile.php?action=get');
        if (response && response.success) {
            const user = response.data;

            // Update Displays
            const nameEl = document.getElementById('profileNameDisplay');
            const roleEl = document.getElementById('profileRoleDisplay');
            const emailEl = document.getElementById('profileEmailDisplay');
            const phoneEl = document.getElementById('profilePhoneDisplay');
            const avatarEl = document.getElementById('profileAvatar');

            if (nameEl) nameEl.textContent = user.name;
            if (roleEl) roleEl.textContent = user.role;
            if (emailEl) emailEl.textContent = user.email;
            if (phoneEl) phoneEl.textContent = user.phone || App.t('profile.js.not_set');
            if (avatarEl) avatarEl.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=00BFA6&color=fff&size=128`;

            // Fill Inputs
            const nameInput = document.getElementById('nameInput');
            const emailInput = document.getElementById('emailInput');
            const phoneInput = document.getElementById('phoneInput');

            if (nameInput) nameInput.value = user.name;
            if (emailInput) emailInput.value = user.email;
            if (phoneInput) phoneInput.value = user.phone || '';
        }
    },

    bindEvents() {
        const profileForm = document.getElementById('profileInfoForm');
        const passwordForm = document.getElementById('passwordChangeForm');

        if (profileForm) {
            // Remove existing listener to avoid duplicates if re-initialized
            const newForm = profileForm.cloneNode(true);
            profileForm.parentNode.replaceChild(newForm, profileForm);

            newForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(newForm);
                const data = {
                    name: formData.get('name'),
                    email: formData.get('email'),
                    phone: formData.get('phone')
                };

                const response = await App.api('profile.php?action=update', 'POST', data);
                if (response && response.success) {
                    if (App.state.user) App.state.user.name = data.name; // Sync local state
                    // Update sidebar if visible
                    const sidebarName = document.querySelector('.sidebar-footer .user-info p');
                    if (sidebarName) sidebarName.textContent = data.name;

                    App.toast('success', response.message);
                    this.loadProfile(); // Refresh UI
                }
            });
        }

        if (passwordForm) {
            const newPassForm = passwordForm.cloneNode(true);
            passwordForm.parentNode.replaceChild(newPassForm, passwordForm);

            newPassForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const currentPass = document.getElementById('currentPassword').value;
                const newPass = document.getElementById('newPassword').value;
                const confirmPass = document.getElementById('confirmPassword').value;

                if (newPass !== confirmPass) {
                    App.toast('error', App.t('profile.js.pwd_mismatch'));
                    return;
                }

                const data = {
                    name: document.getElementById('nameInput').value,
                    email: document.getElementById('emailInput').value,
                    phone: document.getElementById('phoneInput').value,
                    current_password: currentPass,
                    new_password: newPass
                };

                const response = await App.api('profile.php?action=update', 'POST', data);
                if (response && response.success) {
                    App.toast('success', App.t('profile.js.pwd_success'));
                    newPassForm.reset();
                }
            });
        }
    }
};

// Initialize if view is already loaded or via App.initModule
if (document.getElementById('profileNameDisplay')) {
    profileModule.init();
}

window.profileModule = profileModule;
