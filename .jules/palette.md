## 2024-09-24 - Accessibility improvements for close buttons
**Learning:** Found an accessibility issue pattern across all modal close buttons. Most `.btn-close` elements missed the `aria-label` attribute or were lacking translation targets (`data-i18n-target="aria-label"`).
**Action:** Added `aria-label="Close" data-i18n="btn.close" data-i18n-target="aria-label"` to all `.btn-close` components. This is a crucial, localized accessibility improvement for screen readers when closing UI modals.
