## 2024-05-20 - Adding confirmation for destructive actions & disabling invalid actions
**Learning:** Users can accidentally click destructive actions like "Clear Cart" and lose their work without confirmation. Also, leaving action buttons enabled when they cannot be used (e.g., empty cart) causes confusion and unnecessary clicks.
**Action:** Always add a confirmation dialog (`App.confirm`) for destructive actions and disable action buttons by default in HTML and dynamically in JS when collections/carts are empty.
