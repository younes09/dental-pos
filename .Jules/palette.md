## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-19 - Disable Action Buttons on Empty State
**Learning:** Users can click "Complete Sale", "Clear", or "Hold" buttons when the cart is empty, causing confusing flash messages, poor feedback, or unexpected behaviors.
**Action:** When a collection like a shopping cart is empty, always add logic to disable actions that require items. Also ensure that initial HTML templates have the `disabled` attribute to prevent a flash of enabled buttons before JS kicks in. Finally, preserve existing `data-i18n` elements when modifying `innerHTML` in `finally` blocks.
