## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-10-27 - Translating Multiple Attributes via i18n
**Learning:** The frontend internationalization system (`data-i18n-target`) previously only supported single hardcoded targets. When adding `aria-label` to elements that already use `data-i18n-target="title"`, the `aria-label`s would not be translated.
**Action:** Modified `assets/js/app.js` to support comma-separated targets in `data-i18n-target` (e.g., `data-i18n-target="title, aria-label"`). Use this pattern when translating elements that require both a visual tooltip and an accessible label.
