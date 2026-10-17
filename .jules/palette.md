## 2025-08-07 - Empty State Action Buttons
**Learning:** In the POS module, rendering standard action buttons (checkout, clear, hold) without conditional disabled states when the cart is empty led to potential user confusion and technically invalid actions.
**Action:** When working on UI components managing collections or carts, always dynamically compute and set the `disabled` DOM property for associated action buttons based on the collection's emptiness (`length === 0`).
