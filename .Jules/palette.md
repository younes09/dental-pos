## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-06-20 - Global ARIA labels for Bootstrap Close Buttons
**Learning:** Bootstrap's `.btn-close` class does not provide accessible names by default in this implementation, and custom icon-only actions (clear search, remove image) frequently lack accessibility labels.
**Action:** When working with Bootstrap modals or custom clear/close buttons, always explicitly add `aria-label="Close"` or appropriate localized ARIA labels so screen reader users can navigate out of modals or clear inputs.
