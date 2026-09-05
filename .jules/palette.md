## 2026-07-09 - Adding loading states to async form submissions
**Learning:** Users lack visual feedback and might double-click submit buttons on async auth forms.
**Action:** Always disable buttons and show spinners during async operations, and ensure icon-only buttons have aria-labels.
## 2024-05-19 - ARIA labels in Dynamically Injected UI
**Learning:** Screen readers often fail to interpret dynamically injected icon-only buttons (e.g. inside a POS cart powered by innerHTML) properly, treating them as generic unlabelled buttons, and FontAwesome icons as raw characters. Also dynamic number changes (like cart quantity) are missed without aria-live.
**Action:** When creating dynamic UIs with template literals, consistently apply `aria-label`, `type="button"`, and `aria-hidden="true"` on icons, and use `aria-live="polite"` on dynamically updating text (like quantities).
## 2024-05-19 - ARIA labels with Client-Side i18n
**Learning:** Screen readers won't announce localized tooltip titles if they aren't explicitly mirrored to `aria-label` attributes on interactive elements, and icon-only clear buttons often miss translations.
**Action:** Enhance the client-side `App.translate()` function to automatically mirror `data-i18n-target="title"` to `aria-label`, and support `data-i18n-target="aria-label"` directly for icon-only buttons (like search clears) so that screen readers correctly announce the function in the user's selected language.
