## 2024-05-24 - Accessibility improvements for close buttons
**Learning:** Many standard `btn-close` (X) buttons within Bootstrap modals lack the standard `aria-label="Close"` attribute, which makes them inaccessible to screen readers. In the context of this app, `views/pos.html` and other views are affected.
**Action:** Always ensure that icon-only interactive elements like close buttons have descriptive `aria-label`s.

## 2024-05-24 - Accessibility improvements for clear search buttons
**Learning:** Icon-only interactive elements like "clear search" buttons, especially when dynamically handled, often lack screen reader accessibility. If localization is implemented via custom `data-i18n` attributes, using `data-i18n-target="aria-label"` ensures screen readers get translated labels.
**Action:** Always add descriptive, localized `aria-label`s to icon-only buttons instead of hardcoding strings in title attributes.
