## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-20 - Disabling Action Buttons for Empty Collections
**Learning:** Users might attempt invalid actions (like clearing an already empty cart or checking out without items) if action buttons remain enabled by default. This leads to confusing error messages or a flash of enabled state before JavaScript initializes.
**Action:** Always add the `disabled` attribute directly in the HTML template for action buttons associated with empty collections (e.g., carts, lists), and dynamically toggle this state in JavaScript as the collection changes.
