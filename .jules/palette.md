## 2024-07-26 - Guarding Destructive Actions
**Learning:** Actions that clear active user state (like emptying a POS cart) can lead to frustrating data loss if clicked accidentally, especially on fast-paced interfaces.
**Action:** Always add a confirmation dialog (e.g., using `App.confirm`) before destructive actions if the target state contains user data (e.g., cart is not empty).
