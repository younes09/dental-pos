## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2026-07-09 - Accessible Clear Search Button in Public Store
**Learning:** Icon-only buttons for search clearance often lack proper accessible names, rendering them invisible or confusing to screen reader users navigating dynamically translated UI. Custom i18n logic often misses attributes like aria-label.
**Action:** When creating clear-search or icon-only buttons, add aria-label attributes, hide internal icons from screen readers (aria-hidden="true"), and update custom translation scripts to support data-i18n-target="aria-label".
