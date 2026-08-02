## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-19 - Disabling Contextually Invalid Actions
**Learning:** Action buttons associated with dynamic collections (like a POS shopping cart) should provide immediate visual feedback regarding their valid states, preventing users from attempting invalid actions (like clearing an already empty cart or attempting to checkout with no items).
**Action:** When building UIs that interact with dynamic arrays, always evaluate `array.length === 0` and bind it to the `.disabled` property of related action buttons during the render cycle. Furthermore, always verify initial states are correctly reflected in the static HTML payload as well.
