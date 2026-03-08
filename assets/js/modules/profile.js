/**
 * Profile Module Logic
 */

const profileModule = {
    init() {
        console.log('Profile Module Loaded');
        this.loadProfile();
        this.bindEvents();
    },

    async loadProfile() {
        const response = await App.api('profile.php?action=get');
        if (response && response.success) {
            const user = response.data;

            // Update Displays
            document.getElementById('profileNameDisplay').textContent = user.name;
            document.getElementById('profileRoleDisplay').textContent = user.role;
            document.getElementById('profileEmailDisplay').textContent = user.email;
            document.getElementById('profilePhoneDisplay').textContent = user.phone || 'Not set';
            document.getElementById('profileAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=00BFA6&color=fff&size=128`;

            // Fill Inputs
            document.getElementById('nameInput').value = user.name;
            document.getElementById('emailInput').value = user.email;
            document.getElementById('phoneInput').value = user.phone || '';
        }
    },

    bindEvents() {
        const profileForm = document.getElementById('profileInfoForm');
        const passwordForm = document.getElementById('passwordChangeForm');

        if (profileForm) {
            profileForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(profileForm);
                const data = {
                    name: formData.get('name'),
                    email: formData.get('email'),
                    phone: formData.get('phone')
                };

                const response = await App.api('profile.php?action=update', 'POST', data);
                if (response && response.success) {
                    App.state.user.name = data.name; // Sync local state
                    // Update sidebar if visible
                    const sidebarName = document.querySelector('.sidebar-footer .user-info p');
                    if (sidebarName) sidebarName.textContent = data.name;

                    App.toast('success', response.message);
                    this.loadProfile(); // Refresh UI
                }
            });
        }

        if (passwordForm) {
            passwordForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const currentPass = document.getElementById('currentPassword').value;
                const newPass = document.getElementById('newPassword').value;
                const confirmPass = document.getElementById('confirmPassword').value;

                if (newPass !== confirmPass) {
                    App.toast('error', 'Passwords do not match');
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
                    App.toast('success', 'Password updated successfully');
                    passwordForm.reset();
                }
            });
        }
    }
};

// Auto-init on load
profileModule.init();
