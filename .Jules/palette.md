## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-20 - Preserving i18n & Default Disabled States on Empty Collections
**Learning:** Initializing empty collection action buttons (e.g. POS cart checkout, clear, hold) as enabled causes a "flash of active UI" before JavaScript disables them. Furthermore, overwriting `innerHTML` dynamically for loading states destroys static `data-i18n` elements, breaking translations on dynamically re-enabled buttons.
**Action:** Always set the `disabled` attribute directly in the HTML template for elements operating on empty lists. When updating `innerHTML` for loading feedback, ensure text is wrapped in `<span data-i18n="...">` so the translation system can target it, and reset disabled state contextually rather than hardcoding `false`.
