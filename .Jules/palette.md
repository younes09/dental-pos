## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-08-19 - Disabling action buttons on empty states and preserving i18n
**Learning:** In POS carts and similar dynamic forms, users may get confused or encounter errors if checkout/clear/hold actions are enabled when the cart is empty. Additionally, dynamically replacing a button's `innerHTML` during async processing (e.g., loading states) destroys underlying `<span data-i18n="...">` translation elements, breaking client-side localization.
**Action:** Always disable action buttons by default in the HTML if the collection is empty initially, and dynamically toggle the `disabled` property when items are added/removed. When temporarily replacing `innerHTML` for loading states, wrap text in `<span data-i18n="...">` elements mirroring the original structure to preserve translation targeting.
