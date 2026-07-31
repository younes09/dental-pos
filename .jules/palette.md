
## 2024-10-27 - Disabled State for Empty Collection Actions
**Learning:** Action buttons (like Checkout, Clear, or Hold) that apply to collections or carts can create confusion if they remain enabled while the collection is empty. Relying solely on error messages after a user click is a poor experience.
**Action:** Always default these action buttons to a `disabled` state in the HTML when the collection is initially empty, and dynamically toggle the disabled property in JavaScript based on the collection's length. This provides immediate visual feedback and prevents invalid actions.
