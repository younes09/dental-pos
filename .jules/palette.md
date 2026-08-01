## 2024-05-30 - Disable Actions on Empty Collections
**Learning:** Found that key actions in the POS interface (Clear, Checkout, Hold) were active by default on an empty cart, which could allow users to attempt invalid actions or get confused about the state.
**Action:** Added `disabled` attributes to the HTML and dynamically toggled this state in JavaScript based on collection/cart length to provide immediate visual feedback.
