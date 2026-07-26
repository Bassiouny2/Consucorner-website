# Security Audit — `consucorner` theme

**Scope:** `wp-content/themes/consucorner/` (58 PHP files, 32 JS files)
**Date:** 2026-05-12
**Auditor mode:** Senior WordPress Cybersecurity Auditor — read-only scan, no changes made.

---

## Summary

| # | Category                              | Findings | Severity                  |
|---|---------------------------------------|----------|---------------------------|
| 1 | Hardcoded secrets / API keys / tokens | **0**    | n/a (clean)               |
| 2 | Hardcoded webhook URLs                | **0**    | n/a (clean)               |
| 3 | Private keys / certificates           | **0**    | n/a (clean)               |
| 4 | Dangerous PHP execution sinks         | **0**    | n/a (clean)               |
| 5 | Debugging leftovers — PHP             | **0**    | n/a (clean)               |
| 6 | Debugging leftovers — JS              | **7**    | 1 High, 6 Low (info leak) |
| 7 | PHP files missing `ABSPATH` guard     | **21**   | Medium                    |

Overall the theme is in solid shape: no real secrets, no eval-style sinks, no `var_dump`/`print_r`/`error_log` in PHP. The two real issues are a handful of `console.log/warn/error` calls in JS, and ~21 template files that don't refuse direct execution.

---

## 1. Hardcoded Secrets / API Keys / Tokens

**Patterns scanned (case-insensitive where appropriate):**

- Stripe: `sk_live_`, `sk_test_`, `pk_live_`, `pk_test_`, `rk_live_`, `rk_test_`
- AWS access keys: `AKIA[0-9A-Z]{16}`
- GitHub: `ghp_`, `gho_`
- GitLab PAT: `glpat-`
- Slack: `xoxp-`, `xoxb-`
- Bearer tokens: `Bearer <token>`
- Generic assignments: `api_key`, `access_token`, `secret_key`, `client_secret`, `auth_token`, `password`, `passwd` followed by a quoted value of 8+ chars
- Constant defines: `define( 'API_KEY' / 'SECRET_KEY' / 'ACCESS_KEY' / 'PRIVATE_KEY' / ... )`
- Service-specific: `MAILGUN_`, `SENDGRID_`, `TWILIO_`, `RECAPTCHA_(SITE|SECRET)_KEY`
- Google Maps: `maps.googleapis.com…key=`
- PEM material: `BEGIN (RSA|EC|DSA|OPENSSH|PRIVATE) KEY`, `BEGIN CERTIFICATE`

**Findings:** **None.** No live or test secrets, no PEM blocks, no AWS keys, no bearer tokens.

> Note: the auth-modal JS literal mentions `"password"` only as a form-field name / button label, not as a value — false-positive, discarded.

---

## 2. Hardcoded Webhook / Outbound Service URLs

**Patterns scanned:** `hooks.slack.com`, `discord.com/api/webhooks`, `chat.googleapis.com`, `outlook.office.com/webhook`, `webhook.site`, `api.telegram.org/bot`, `maker.ifttt.com`, `hooks.zapier.com`.

**Findings:** **None.**

---

## 3. Dangerous PHP Execution Sinks

**Patterns scanned:** `phpinfo()`, `eval()`, `assert()`, `exec()`, `system()`, `shell_exec()`, `passthru()`, `proc_open()`, `popen()`.

**Findings:** **None.**

---

## 4. Debugging Leftovers — PHP

**Patterns scanned:** `var_dump(`, `print_r(`, `error_log(`, `var_export(`, `dd(`, `dump(`, `wp_die($...)`.

**Findings:** **None.** The PHP codebase contains no debug-dumping calls.

---

## 5. Debugging Leftovers — JavaScript

**Patterns scanned:** `console.log/debug/info/warn/error/trace/dir(...)`, `debugger;`.

### HIGH — true debug stubs (remove)

| # | File                                                          | Line | Code                                          | Notes                                                                       |
|---|---------------------------------------------------------------|------|-----------------------------------------------|-----------------------------------------------------------------------------|
| 1 | `wp-content/themes/consucorner/assets/js/main.js`             | 10   | `console.log('Mobile menu initialized');`     | Inside `initMobileMenu` stub — function has no real implementation.         |
| 2 | `wp-content/themes/consucorner/assets/js/main.js`             | 16   | `console.log('Tabs initialized');`            | Inside `initTabs` stub — function has no real implementation.               |

`assets/js/main.js` reads as a scaffolding file (`// Implementation for mobile menu will go here`). Both `console.log` calls are pure debug leftovers and should be removed (or the whole file dropped if it's not enqueued).

### LOW — diagnostic logs inside `.catch()` error handlers (consider removing or gating)

These are not strictly debug leftovers — they're error-path diagnostics — but in production they leak internal selectors / endpoint names to anyone with DevTools. A senior reviewer would either remove them or gate them behind a `window.CC_DEBUG` flag.

| # | File                                                                | Line | Code                                                                                |
|---|---------------------------------------------------------------------|------|-------------------------------------------------------------------------------------|
| 3 | `wp-content/themes/consucorner/assets/js/cart-badge.js`             | 412  | `console.warn('[ConsuCorner cart] ' + err.message);`                                |
| 4 | `wp-content/themes/consucorner/assets/js/category-filter.js`        | 166  | `if (window.console) window.console.error('[category-filter]', err);`               |
| 5 | `wp-content/themes/consucorner/assets/js/category-filter.js`        | 192  | `if (window.console) window.console.error('[category-filter-infinite]', err);`      |
| 6 | `wp-content/themes/consucorner/assets/js/mega-menu.js`              | 702  | `console.warn('[ConsuCorner cart] ' + err.message);`                                |
| 7 | `wp-content/themes/consucorner/assets/js/browse-specialty.js`       | 253  | `console.warn('[ConsuCorner] Could not load specialty products:', err.message);`    |

**Recommended approach for LOW items:** wrap each in `if (window.CC_DEBUG) { ... }`, and only set `CC_DEBUG = true` via `wp_add_inline_script` when `WP_DEBUG` is on.

---

## 6. PHP Files Missing `ABSPATH` Guard (Unprotected Direct Access)

**Guard pattern required at top of every PHP file (any equivalent variant is fine):**

```php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// or
defined( 'ABSPATH' ) || exit;
```

**Pattern searched:** `defined\s*\(\s*['"]ABSPATH['"]` (matches both quote styles, both forms).

**Result:** 37 / 58 PHP files have the guard. **21 PHP files are missing it.**

### Missing guard (sorted by directory)

> All paths are relative to `wp-content/themes/consucorner/`.

#### Top-level template files (10)

| #  | File                  | Risk                                                              |
|----|-----------------------|-------------------------------------------------------------------|
|  1 | `404.php`             | Renders the 404 template; if requested directly it bootstraps OK but should still refuse. |
|  2 | `archive.php`         | Standard archive template.                                        |
|  3 | `comments.php`        | Comment template — included from theme, but should still guard.   |
|  4 | `footer.php`          | Reads theme-mod settings; direct access could surface notices.    |
|  5 | `front-page.php`      | Front-page template.                                              |
|  6 | `functions.php`       | ⚠️ Most important file in the theme — must guard.                  |
|  7 | `header.php`          | Outputs `<head>` + body open.                                     |
|  8 | `index.php`           | Required theme file.                                              |
|  9 | `page.php`            | Generic page template.                                            |
| 10 | `search.php`          | Search results template.                                          |
| 11 | `sidebar.php`         | Sidebar template.                                                 |
| 12 | `single.php`          | Single-post template.                                             |

(Count = 12 above; the table covers items 1–12 of the 21.)

#### `inc/` includes (5)

| #  | File                            | Risk                                                                 |
|----|---------------------------------|----------------------------------------------------------------------|
| 13 | `inc/custom-header.php`         | Theme include — direct access leaks internals.                       |
| 14 | `inc/customizer.php`            | Registers Customizer panels; should refuse direct execution.         |
| 15 | `inc/jetpack.php`               | Jetpack integration include.                                         |
| 16 | `inc/template-functions.php`    | Helper functions; should be unreachable directly.                    |
| 17 | `inc/template-tags.php`         | Custom template tags; same.                                          |

#### `template-parts/` (4)

| #  | File                                       | Risk                                            |
|----|--------------------------------------------|-------------------------------------------------|
| 18 | `template-parts/content-none.php`          | Renders "no results" block.                     |
| 19 | `template-parts/content-page.php`          | Renders page content.                           |
| 20 | `template-parts/content-search.php`        | Renders search-result item.                     |
| 21 | `template-parts/content.php`               | Renders generic post item.                      |

### Why this matters

While most servers / WordPress configurations make direct execution of these files inert (functions like `get_header()` will fatal-error without WP loaded), it's defense-in-depth:

- Hides PHP warnings/notices that may leak file paths.
- Stops information disclosure if `display_errors=On` on a misconfigured staging environment.
- Standardizes the entire codebase — easier to audit at a glance.

### Suggested one-liner to apply later

Add this as the very first executable line after the opening `<?php` (and optional header docblock):

```php
defined( 'ABSPATH' ) || exit;
```

---

## 7. Files With Guard (Confirmed Safe) — Reference

37 files contain a valid `ABSPATH` guard. Listed for completeness so you can use this as a checklist when you patch the 21 above:

```
inc/meta-boxes.php
page-vendor.php
page-contact.php
inc/setup-india-attribute.php
woocommerce/checkout/thankyou.php
inc/category-filters.php
woocommerce/cart/cart-empty.php
inc/attribute-images.php
woocommerce/cart/cart.php
woocommerce/myaccount/my-account.php
inc/profile-account.php
inc/customer-wallet.php
woocommerce/content-single-product.php
woocommerce/checkout/form-checkout.php
inc/admin-wallet-refunds.php
inc/admin-vendor-ledger.php
inc/product-mega-menu.php
inc/help-pages.php
inc/mobile-drawer-menu.php
inc/explore-mega-menu.php
page-help.php
woocommerce/myaccount/form-login.php
page-terms-and-conditions.php
inc/page-content/vendor-data.php
woocommerce/archive-product.php
page-shop-specialty.php
page-shop-instruments.php
page-faq.php
inc/page-content/faq-data.php
inc/product-procedures-taxonomy.php
page-archive-posts.php
page-about.php
woocommerce/single-product.php
page-privacy-policy.php
inc/page-content/privacy-policy-data.php
inc/page-content/contact-data.php
inc/page-content/about-data.php
```

---

## Recommended Remediation Order

1. **Add `defined( 'ABSPATH' ) || exit;` to the 21 files** listed in section 6. Quick mechanical change, zero behavior impact.
2. **Remove the 2 HIGH `console.log` calls** in `assets/js/main.js` (or delete the file if it's an unused scaffold — worth checking whether it's enqueued anywhere).
3. **Decide on the 5 LOW JS error-handler logs** — either remove or gate behind a `CC_DEBUG` flag.
4. (Optional) Add a CI grep gate (`rg "var_dump|print_r|console\.log" wp-content/themes/consucorner`) to catch regressions on every PR.

---

*End of report. No files were modified during this scan.*
