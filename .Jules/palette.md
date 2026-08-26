## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2026-07-10 - Ensuring empty states disable primary actions
**Learning:** In dynamically rendered interfaces (like POS carts), buttons intended for populated states (like checkout, clear, or hold) can flash as enabled before JS initializes, causing confusion or errors if clicked.
**Action:** Set `disabled` attributes directly in the HTML template for actions that depend on populated collections, and actively toggle this property via JS during render cycles.
