## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2026-09-20 - Synchronizing title and aria-label in custom i18n
**Learning:** Screen readers and accessibility testing tools flag icon-only buttons when custom localization systems translate `title` but miss updating `aria-label`. Statically injected tooltips fail a11y criteria if the element lacks a native accessible name.
**Action:** Enhance custom translation routines to explicitly set `aria-label` when targeting `title`, and add dedicated `aria-label` i18n targets for statically visible icon buttons.
