## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-07-24 - Localizing Icon-only Buttons
**Learning:** Hardcoded tooltips (`title`) on font-awesome icons inside unlabelled buttons defeat both accessibility and localization systems. When mapping custom i18n data targets, `aria-label` must be supported natively by the mapping function to properly translate structural accessibility tags without destroying the visual icon (like `innerHTML` would).
**Action:** When adding localized labels to icon-only buttons, set `data-i18n-target="aria-label"`, add `aria-hidden="true"` to the icon itself, and ensure the UI's JS/PHP translation function explicitly supports setting `aria-label`.
