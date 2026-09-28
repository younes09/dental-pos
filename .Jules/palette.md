## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2026-10-10 - ARIA labels with Custom i18n
**Learning:** Dynamic custom i18n engines often fail to target a11y attributes by default, leaving icon-only buttons unlocalized for screen readers.
**Action:** Enhance the i18n engine (`app.js`, `store.php`) to actively update `aria-label` when `data-i18n-target="aria-label"` is passed, and automatically duplicate translated `title` attributes into `aria-label` to ensure dual visual/auditory accessibility.
