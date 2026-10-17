## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-19 - Disabling Empty State Actions Early
**Learning:** Action buttons tied to empty collections or carts can momentarily flash as "enabled" and clickable before JavaScript dynamically initializes. Users expect immediate visual feedback that invalid actions (like checking out an empty cart) are impossible.
**Action:** Set the `disabled` attribute directly in the HTML template for elements that should be disabled by default, and update these states dynamically in JS based on context changes to preserve a robust, seamless UX and prevent initialization jank.
