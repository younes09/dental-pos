## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2026-07-10 - Adding ARIA labels to standard static UI buttons
**Learning:** Native bootstrap `btn-close` components and custom icon-only clear buttons (using `fas fa-times`) in static HTML views lack contextual labels by default, rendering them invisible to screen readers, and redundantly announce their icon classes without `aria-hidden`.
**Action:** Always add `aria-label` to `.btn-close` and custom icon-only clear buttons. On inner FontAwesome `<i>` tags, add `aria-hidden="true"` and remove any hardcoded native `title` tags to favor `aria-label`s updated via the i18n system using `data-i18n-target="aria-label"`.
