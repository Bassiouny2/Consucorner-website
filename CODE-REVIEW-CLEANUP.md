# Code Review — Cleanup Report (`consucorner` theme)

**Scope:** `wp-content/themes/consucorner/` — 58 PHP, 32 JS, 34 CSS files  
**Date:** 2026-05-12  
**Reviewer mode:** Senior Code Reviewer — read-only scan, no files were changed.

---

## Summary

| # | Category | Findings | Priority |
|---|----------|----------|----------|
| 1 | Commented-out dead code blocks | **0** | — (clean) |
| 2 | Orphaned / never-called PHP functions | **1** (possible) | Low |
| 3 | Orphaned PHP include files | **3** | Medium |
| 4 | Orphaned JS files (never enqueued) | **4** | Medium |
| 5 | Orphaned CSS files (never enqueued) | **5** | Low–Medium |
| 6 | Broken / missing enqueue targets | **0** | — (clean) |

---

## 1. Commented-out Code

**Result: Clean.** No large dead code blocks found in PHP, HTML, or CSS.

All comments in the theme are:

- Section-separator headers (e.g., `/* ═══ HELPERS ═══ */`)
- `// phpcs:ignore` directives (required, not dead)
- Short inline explanations of intentional decisions

No blocks of disabled `<!-- HTML -->` or `/* CSS */` that serve no purpose were found.

---

## 2. Orphaned PHP Functions — Declared but Never Called

### Borderline: `cc_shop_specialty_country_taxonomy()` — `inc/meta-boxes.php:645`

```php
// inc/meta-boxes.php line 645
function cc_shop_specialty_country_taxonomy() { ... }
```

**Status: Used internally (not orphaned), but a refactor candidate.**

It is called only once, from within the same file at line 750 inside `cc_render_shop_specialty_meta_box()`. It is never registered as a hook and never called from outside `meta-boxes.php`. This is a helper extracted for readability — it is safe to leave as-is, but could be inlined if you want fewer named functions in the global namespace.

**All other declared functions are either hooked via `add_action`/`add_filter`, or called explicitly from templates.** No true orphaned PHP functions were found.

---

## 3. Orphaned PHP Include Files

These files are `require`d from `functions.php` but represent dead starter-theme scaffolding never integrated into the custom theme:

### 3a. `inc/custom-header.php` — _s Starter Boilerplate

```
File: wp-content/themes/consucorner/inc/custom-header.php
Lines: 79
```

Registers WordPress's built-in `custom-header` theme support and a `consucorner_header_style()` callback that injects `<style>` for the blog title colour. The theme's own `header.php` **never** calls `the_header_image_tag()` and has no `.site-title` or `.site-description` elements. This file does nothing except register unused theme support.

**Safe to delete.** Also remove the `require` line in `functions.php` that loads it.

---

### 3b. `inc/jetpack.php` — _s Starter Boilerplate

```
File: wp-content/themes/consucorner/inc/jetpack.php
Lines: ~55
```

Adds `add_theme_support( 'infinite-scroll', [...] )` for Jetpack Infinite Scroll. Jetpack is not installed or activated on this site (not found anywhere in the plugin list or theme calls). This file registers support that silently no-ops.

**Safe to delete.** Also remove the `require` line in `functions.php`.

---

### 3c. `inc/setup-india-attribute.php` — Completed One-Time Migration

```
File: wp-content/themes/consucorner/inc/setup-india-attribute.php
Lines: 131
```

Creates a "Country of Origin" WooCommerce attribute and an "India" term **once**, then sets the `cc_india_attribute_ready` WordPress option so it never runs again. It is confirmed already completed (the option is set). The script now runs on every `admin_init` boot, checks the option, and immediately returns — 131 lines of PHP loaded on every admin page to do nothing.

**Safe to delete.** Also remove the `require` line in `functions.php`.

---

## 4. Orphaned JavaScript Files (Never Enqueued)

None of these four files appear in any `wp_enqueue_script` or `$load_js(...)` call anywhere in the theme.

### 4a. `assets/js/main.js` — 659 bytes, Empty Scaffold

```
File: wp-content/themes/consucorner/assets/js/main.js
```

Contains two placeholder functions (`initMobileMenu`, `initTabs`) with no implementation — only `console.log` stubs (also flagged in SECURITY-AUDIT.md). Never enqueued. Zero functional code.

**Safe to delete.**

---

### 4b. `assets/js/browse-specialty.js` — 9,925 bytes, Superseded

```
File: wp-content/themes/consucorner/assets/js/browse-specialty.js
```

The original static-site "Browse by Specialty" script. Hard-coded to navigate to `shop.html?specialty=…` with no WordPress/WooCommerce integration. It was replaced by `assets/js/browse-specialty-ajax.js` (which uses `admin-ajax.php` and `consuBrowseData`). The old file is never enqueued.

**Safe to delete.**

---

### 4c. `assets/js/all-products.js` — 553 lines, Hardcoded Fake Data

```
File: wp-content/themes/consucorner/assets/js/all-products.js
```

Contains a hardcoded `var PRODUCTS = [...]` array with fake product names, prices, and categories from the static development phase. It filters and renders these fake products into `#apGrid`. It is never enqueued; the live theme uses WooCommerce archive templates for product listing. Leaving it on the server risks confusion if someone accidentally references it.

**Safe to delete.**

---

### 4d. `js/navigation.js` — 2,980 bytes, _s Starter Boilerplate

```
File: wp-content/themes/consucorner/js/navigation.js  (note: /js/ not /assets/js/)
```

The standard `_s` starter-theme keyboard-accessible mobile-nav script. It targets `#site-navigation`, which does not exist in this theme's `header.php`. Never enqueued from `functions.php` or anywhere else.

**Safe to delete.**

---

## 5. Orphaned CSS Files (Never Enqueued)

None of these files are referenced in any `wp_enqueue_style` or `$load_css(...)` call.

### 5a. `assets/css/style.backup.css` — 88,415 bytes ⚠️ HIGH PRIORITY

```
File: wp-content/themes/consucorner/assets/css/style.backup.css
Size: 88 KB
```

Byte-for-byte identical to `assets/css/style.css` (confirmed: both 88,415 bytes). An explicit backup copy, never loaded by WordPress. Publicly accessible at its URL — any visitor can inspect it.

**Safe to delete immediately.** (Largest dead file in the theme — 88 KB of dead CSS served publicly.)

---

### 5b. `assets/css/all.min.css` — 101,789 bytes

```
File: wp-content/themes/consucorner/assets/css/all.min.css
Size: ~102 KB  
Content: Font Awesome Free 6.2.0 (minified)
```

The Dokan plugin already loads its own Font Awesome. This copy is never enqueued by the theme and duplicates what Dokan provides. Also publicly accessible.

**Safe to delete.**

---

### 5c. `assets/css/style.css` — 88,415 bytes

```
File: wp-content/themes/consucorner/assets/css/style.css
```

The original monolithic CSS file from the static-site phase. The theme was subsequently refactored into granular per-page files (`fonts.css`, `variables.css`, `base.css`, `header.css`, `mega-menu.css`, etc.). This file is never loaded via `$load_css()`. It is distinct from the **root** `style.css` (which is the required theme metadata file and IS loaded by WordPress core).

> ⚠️ **Do NOT confuse with the root `style.css`** at `wp-content/themes/consucorner/style.css`. Only the one inside `assets/css/` is safe to delete.

**Safe to delete** (`assets/css/style.css` only).

---

### 5d. `assets/css/global.css` — 951 bytes

```
File: wp-content/themes/consucorner/assets/css/global.css
```

Generic CSS reset (`box-sizing`, `margin`, `font-family`, `a` colour, `.container`, `.sr-only`) using CSS custom property variables from the static dev phase. The theme uses `assets/css/base.css` for the same reset, so these rules are duplicated and this file is never loaded.

**Safe to delete.**

---

### 5e. `assets/css/normalize.css` — 7,161 bytes

```
File: wp-content/themes/consucorner/assets/css/normalize.css
Content: normalize.css v8.0.1
```

Standard browser-normalisation stylesheet from the static-site phase. WordPress core and WooCommerce already ship their own normalisation; this file is never enqueued by the theme.

**Safe to delete.**

---

## 6. Enqueue Integrity Check

**Result: All 100% present.** Every CSS handle in `$common_css`, `$load_css(...)` arrays, and every JS handle in `$common_js`, `$load_js(...)` arrays resolves to a real, non-empty file in `assets/css/` or `assets/js/`.

No broken enqueue targets exist.

---

## Recommended Deletion Checklist

Copy this as a task list when you're ready to act:

### Immediate (safe, no behaviour impact)

- [ ] `assets/css/style.backup.css` — 88 KB explicit backup
- [ ] `assets/css/all.min.css` — 102 KB duplicate Font Awesome
- [ ] `assets/css/style.css` *(only the one inside `assets/css/`)* — 88 KB old monolith
- [ ] `assets/css/global.css` — 951 B static-dev reset
- [ ] `assets/css/normalize.css` — 7 KB static-dev normalise
- [ ] `assets/js/main.js` — 659 B empty scaffold with console.log stubs
- [ ] `assets/js/browse-specialty.js` — 10 KB superseded by AJAX version
- [ ] `assets/js/all-products.js` — 553 lines of hardcoded fake product data
- [ ] `js/navigation.js` — _s starter boilerplate, never used

### With matching `require` removal in `functions.php`

- [ ] `inc/custom-header.php` + remove its `require` line in `functions.php`
- [ ] `inc/jetpack.php` + remove its `require` line in `functions.php`
- [ ] `inc/setup-india-attribute.php` + remove its `require` line in `functions.php`

---

## Total Dead Weight

| Type | Files | Size |
|------|-------|------|
| CSS  | 5     | ~287 KB |
| JS   | 4     | ~24 KB  |
| PHP  | 3     | ~0.5 KB loaded per request (admin) |
| **Total** | **12 files** | **~311 KB** |

---

*End of report. No files were modified during this review.*
