## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).

## 2024-05-20 - Global Dynamic aria-label Mirroring for tooltips
**Learning:** Translating tooltips dynamically (e.g. `data-i18n-target="title"`) often leaves out `aria-label`, missing an important accessibility attribute for screen readers. Using hardcoded labels circumvents localization.
**Action:** When a UI localization script processes strings meant for native tooltips (`title`), automatically mirror the translated string to `aria-label`. For icon-only buttons, explicitly support `data-i18n-target="aria-label"` while adding `aria-hidden="true"` to the inner icons to avoid screen reader clutter.
