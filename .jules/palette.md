## 2024-05-24 - Disable action buttons on empty state
**Learning:** Disabling action buttons (like checkout, clear, or hold) when a cart is empty provides immediate, invisible feedback, preventing the user from performing invalid actions that would trigger error toasts.
**Action:** Always verify if an action can actually be performed based on the current state. Disable buttons related to empty collections/carts to prevent unnecessary user frustration or confusing error messages.
