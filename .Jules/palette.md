## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).

## 2024-05-19 - Localizing aria-labels on Icon-only Buttons
**Learning:** Icon-only buttons (like a search clear `x` button) need explicit `aria-label`s for screen readers. In this design system, those text values must also be dynamically localized. This is achieved by combining `data-i18n` (for the translation key) with `data-i18n-target="aria-label"`. If `data-i18n-target` is omitted, the translation system might inject the text as `innerHTML`, destroying the icon and visual layout.
**Action:** When adding accessible labels to icon-only interactive elements in this codebase, always apply both `aria-label` (for initial fallback) and `data-i18n-target="aria-label"` alongside the translation key to preserve layout while ensuring robust accessibility across languages.
