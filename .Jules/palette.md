## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-24 - Accessibility for dynamic localized tooltips
**Learning:** When using custom JavaScript i18n systems (like setting text via `data-i18n-target="title"`), screen readers will not read the localized text unless it's also mapped to `aria-label`. However, replacing `title` entirely with `aria-label` degrades the experience for sighted users who rely on hover tooltips.
**Action:** When updating a custom i18n engine, ensure that targeting `title` programmatically updates BOTH the `title` attribute (for visual tooltips) and the `aria-label` attribute (for screen readers) simultaneously.
