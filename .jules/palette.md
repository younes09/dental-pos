## 2025-02-18 - Dynamic ARIA labels via i18n
**Learning:** This app uses a custom translation system that supports comma-separated targets in the `data-i18n-target` attribute (e.g., `data-i18n-target="title, aria-label"`).
**Action:** When adding ARIA labels or tooltips to elements, check if they can be localized using the existing `data-i18n` and `data-i18n-target` attributes to ensure accessibility features are also translated.
