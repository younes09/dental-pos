## 2026-08-06 - Protect DOM queries when manipulating elements
**Learning:** In a vanilla JS application where elements may be dynamically rendered or absent from certain views, directly accessing properties on `document.getElementById()` without checking for existence can cause `Uncaught TypeError: Cannot set properties of null`, crashing the script.
**Action:** Always wrap DOM manipulations in existence checks (e.g., `if (element)`) or use optional chaining (`element?.disabled = true`) when modifying element properties.
