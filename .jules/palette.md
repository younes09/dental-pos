## 2024-05-18 - Prevent action on empty cart
**Learning:** Action buttons (checkout, clear, hold) associated with empty collections or carts should be disabled by default and reactively updated to provide immediate visual feedback and prevent users from attempting invalid actions.
**Action:** When updating the cart in JS, toggle the disabled attribute of `btn-clear-cart`, `btn-checkout`, and `btn-hold-sale` based on whether `this.cart.length > 0`. Start them disabled in the HTML template.
