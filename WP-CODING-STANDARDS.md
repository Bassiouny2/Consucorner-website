# WordPress Coding Standards — Security Audit Report (`consucorner` theme)

**Scope:** `wp-content/themes/consucorner/` — PHP templates, `inc/`, `woocommerce/` overrides  
**Date:** 2026-05-12  
**Standards Reference:** [WordPress Security Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/#sanitization-and-validation)

---

## Executive Summary

| Severity | Category | Issues Found |
|----------|----------|-------------|
| 🔴 HIGH   | Missing nonce + capability check (term save) | 1 |
| 🟠 MEDIUM | Unsanitized `$_POST` (missing `wp_unslash`) | 2 |
| 🟠 MEDIUM | Unsafe `echo` constructing raw HTML | 2 |
| 🟡 LOW    | `$_GET` displayed without `sanitize_text_field` | 2 |
| 🟡 LOW    | JSON payload suppressed without documented guarantee | 1 |
| 🟡 LOW    | Static ternary in HTML attribute without `esc_attr()` | 5 locations |
| ✅ CLEAN  | AJAX nonce coverage | 14/14 endpoints verified |
| ✅ CLEAN  | Meta-box save nonces | All 8 save callbacks verified |

---

## 🔴 HIGH — Missing Nonce + Capability Check

### H-1 · `inc/attribute-images.php` · Lines 140–151

**Function:** `cc_save_attr_image()` — hooked to `created_{$taxonomy}` and `edited_{$taxonomy}`.

```php
// inc/attribute-images.php  lines 140-151
function cc_save_attr_image( $term_id ) {
    if ( ! isset( $_POST['cc_attribute_image_id'] ) ) {
        return;
    }
    $img_id = absint( $_POST['cc_attribute_image_id'] );
    if ( $img_id ) {
        update_term_meta( $term_id, '_cc_attribute_image', $img_id );
    } else {
        delete_term_meta( $term_id, '_cc_attribute_image' );
    }
}
```

**Problem:** The function writes term meta directly from `$_POST` with no `wp_verify_nonce()` and no `current_user_can()` check. WordPress core does verify an `add-tag`/`update-tag` nonce on the term form before these hooks fire during a normal admin flow, but the callback itself is not self-contained — it will blindly execute any time these hooks fire (e.g., from a programmatic `wp_insert_term` call by another plugin, or via a future REST route that triggers the hook without the WP admin nonce guard).

**Fix:**

```php
function cc_save_attr_image( $term_id ) {
    // Verify this came from a legitimate WP admin term form.
    if ( ! isset( $_POST['_wpnonce'] )
        || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'add-tag' )
            && ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'update-tag' )
    ) {
        return;
    }
    if ( ! current_user_can( 'manage_product_terms' ) ) {
        return;
    }
    if ( ! isset( $_POST['cc_attribute_image_id'] ) ) {
        return;
    }
    $img_id = absint( $_POST['cc_attribute_image_id'] );
    if ( $img_id ) {
        update_term_meta( $term_id, '_cc_attribute_image', $img_id );
    } else {
        delete_term_meta( $term_id, '_cc_attribute_image' );
    }
}
```

---

## 🟠 MEDIUM — Unsanitized `$_POST` Input

### M-1 · `inc/category-filters.php` · Lines 369–370

**Function:** `consucorner_ajax_filter_category_products()`

```php
// Line 369
$min_price = isset($_POST['min_price']) ? (float) $_POST['min_price'] : 0;
// Line 370
$max_price = isset($_POST['max_price']) ? (float) $_POST['max_price'] : 0;
```

**Problem:** `$_POST` values are cast to `float` **without** calling `wp_unslash()` first. WordPress magic-quotes the superglobals; the raw value may contain backslash-escaped characters that survive the float cast in some edge cases. WPCS Rule: every `$_POST` access must call `wp_unslash()` before any use.

**Fix:**

```php
$min_price = isset( $_POST['min_price'] ) ? (float) wp_unslash( $_POST['min_price'] ) : 0;
$max_price = isset( $_POST['max_price'] ) ? (float) wp_unslash( $_POST['max_price'] ) : 0;
```

---

### M-2 · `inc/admin-wallet-refunds.php` · Line 133

**Function:** `CC_Wallet_Refunds::ajax_process_wallet_refund()`

```php
// Line 133
$item_quantities = isset( $_POST['item_quantities'] ) ? (array) wp_unslash( $_POST['item_quantities'] ) : array();
```

**Problem:** The outer array is unslashed and cast correctly, but the individual values inside the array are **not individually sanitized**. At line 142 they are passed directly to `wc_stock_amount()`, which is a WooCommerce formatting helper — not a WPCS-recognised sanitization function. For comparison, `$item_ids` on line 124 correctly uses `array_map( 'absint', ... )`.

**Fix:**

```php
$item_quantities_raw = isset( $_POST['item_quantities'] ) ? (array) wp_unslash( $_POST['item_quantities'] ) : array();
$item_quantities     = array_map( 'absint', $item_quantities_raw );
```

---

## 🟠 MEDIUM — Unsafe `echo` Constructing Raw HTML

### M-3 · `woocommerce/content-single-product.php` · Line 348

```php
// Line 348
<<?php echo $vendor_url ? 'a href="' . esc_url($vendor_url) . '"' : 'div'; ?> class="sp-vendor-info">
// Line 351
</<?php echo $vendor_url ? 'a' : 'div'; ?>>
```

**Problem:** The `echo` statement on line 348 builds raw HTML by string concatenation (`'a href="' . esc_url(...) . '"'`). While the URL portion is correctly passed through `esc_url()`, the surrounding HTML attribute text is assembled and emitted as a raw string via `echo`. This pattern is fragile and violates WPCS Rule: _"do not output dynamic HTML through bare `echo`"_.

**Fix** — split into conditional HTML blocks:

```php
<?php if ( $vendor_url ) : ?>
    <a href="<?php echo esc_url( $vendor_url ); ?>" class="sp-vendor-info">
<?php else : ?>
    <div class="sp-vendor-info">
<?php endif; ?>
    <!-- ... content ... -->
<?php echo $vendor_url ? '</a>' : '</div>'; ?>
```

---

### M-4 · `single.php` · Line 408

```php
// Line 408
<path d="<?php echo $is_open ? 'M1 1L13 13M13 1L1 13' : 'M7 1V13M1 7H13'; ?>"
      stroke="<?php echo $is_open ? 'white' : '#00C8B3'; ?>"
      stroke-width="2" stroke-linecap="round"></path>
```

**Problem:** SVG attribute values are echoed directly into HTML attribute context without `esc_attr()`. Both values are hardcoded string literals today, so there is no immediate XSS risk — but the pattern is technically a WPCS violation and creates maintenance risk if the values ever become variable.

**Fix:**

```php
<path d="<?php echo esc_attr( $is_open ? 'M1 1L13 13M13 1L1 13' : 'M7 1V13M1 7H13' ); ?>"
      stroke="<?php echo esc_attr( $is_open ? 'white' : '#00C8B3' ); ?>"
      stroke-width="2" stroke-linecap="round"></path>
```

The same pattern also occurs in `page-vendor.php:148`, `woocommerce/content-single-product.php:526`, and `page-shop-specialty.php:471` / `page-shop-instruments.php:375` — same fix applies to all.

---

## 🟡 LOW — `$_GET` Displayed Without Full Sanitization

### L-1 · `inc/customer-wallet.php` · Line 902

```php
// Line 902
<p><?php echo esc_html( wp_unslash( $_GET['cc_wallet_error'] ) ); ?></p>
```

**Problem:** The value is output-escaped with `esc_html()`, which prevents HTML injection. However, the **input is not sanitized** with `sanitize_text_field()` before the output step. The value originates from a server-side `rawurlencode()` redirect (line 827), but it comes from a `WP_Error` message which could theoretically contain untrusted text (e.g. from a failed DB operation returning user-supplied data). WPCS Rule: sanitize on input, escape on output — both should be present.

**Fix:**

```php
<p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['cc_wallet_error'] ) ) ); ?></p>
```

---

### L-2 · `inc/customer-wallet.php` · Lines 911–912

```php
// Lines 911-912
absint( $_GET['items_synced'] ?? 0 ),
esc_html( wp_strip_all_tags( wc_price( (float) wc_format_decimal( wp_unslash( $_GET['amount_synced'] ?? 0 ) ) ) ) )
```

**Problem:** `$_GET['items_synced']` uses `absint()` — that is fine ✅. However `$_GET['amount_synced']` is passed to `wc_format_decimal()` which is a WooCommerce **formatter**, not a WordPress sanitizer. WPCS does not recognise it as a valid sanitization function. The value should be explicitly cast to `float` (or passed through `sanitize_text_field()`) before being handed off to `wc_format_decimal()`.

**Fix:**

```php
absint( $_GET['items_synced'] ?? 0 ),
esc_html( wp_strip_all_tags( wc_price( (float) wc_format_decimal( (float) wp_unslash( $_GET['amount_synced'] ?? 0 ) ) ) ) )
```

---

## 🟡 LOW — JSON Payload Without Immediate Sanitization

### L-3 · `inc/profile-account.php` · Line 339

```php
// Line 339
function consucorner_profile_payload() {
    $raw  = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '{}';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $data = json_decode( (string) $raw, true );
    return is_array( $data ) ? $data : array();
}
```

**Problem:** `$_POST['payload']` is unslashed and decoded via `json_decode()` without an immediate `sanitize_*` call. The `// phpcs:ignore` suppresses the warning without documenting the downstream guarantee. Individual callers of `consucorner_profile_payload()` do sanitize the extracted values (`sanitize_text_field`, `sanitize_email`, etc. — confirmed in `consucorner_profile_save_account()` lines 668–726), but this is an **implicit contract** — if a future caller forgets to sanitize, there is no safety net at the intake point.

**Fix (preferred):** Document the contract explicitly and add a note:

```php
function consucorner_profile_payload() {
    // Raw JSON string. Individual callers MUST sanitize every value they read
    // from the returned array before use (sanitize_text_field, sanitize_email…).
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-field by callers.
    $raw  = isset( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : '{}';
    $data = json_decode( (string) $raw, true );
    return is_array( $data ) ? $data : array();
}
```

Optionally, add a depth limit to `json_decode` to prevent memory exhaustion:

```php
$data = json_decode( (string) $raw, true, 8 ); // depth-limited
```

---

## 🟡 LOW — Static Ternary in HTML Attribute Context Without `esc_attr()`

These five locations echo PHP ternary expressions directly into HTML attribute values. Both branches are currently hardcoded static literals — there is **no runtime XSS risk** — but they violate WPCS and create maintenance debt if any branch ever becomes dynamic.

| # | File | Line(s) | Pattern |
|---|------|---------|---------|
| 1 | `inc/attribute-images.php` | 106, 127 | `echo $img_url ? '' : 'display:none;'` inside `style="…"` |
| 2 | `page-vendor.php` | 147–148 | `echo $is_open ? ' vendor-faq-item--open' : ''` in `class="…"` |
| 3 | `woocommerce/content-single-product.php` | 525–526 | `echo $is_open ? ' sp-faq-item--open' : ''` in `class="…"` |
| 4 | `single.php` | 402–403 | `echo $is_open ? ' blog-faq-item--open' : ''` in `class="…"` |
| 5 | `header.php` | 220 | `echo $cc_cart_count > 0 ? '' : 'style="display:none"'` |

**Fix pattern for each:**

```php
// Before
<div class="vendor-faq-item<?php echo $is_open ? ' vendor-faq-item--open' : ''; ?>">
// After
<div class="vendor-faq-item<?php echo $is_open ? esc_attr( ' vendor-faq-item--open' ) : ''; ?>">
```

> For `header.php:220` the full `style="display:none"` string should be separated from the attribute:
> ```php
> <span <?php echo $cc_cart_count > 0 ? '' : 'style="display:none"'; ?>>
> // becomes:
> <span <?php if ( ! $cc_cart_count ) : ?>style="display:none"<?php endif; ?>>
> ```

---

## ✅ CLEAN — Nonce Coverage on All AJAX Endpoints

All 14 custom AJAX actions were verified to call either `check_ajax_referer()` or `wp_verify_nonce()` as their **first statement** before reading any `$_POST` data:

| Handler | Nonce Action | Check Function |
|---------|-------------|----------------|
| `consucorner_ajax_auth_login` | `consucorner_auth_nonce` | `check_ajax_referer` ✅ |
| `consucorner_ajax_auth_signup` | `consucorner_auth_nonce` | `check_ajax_referer` ✅ |
| `consucorner_ajax_auth_lost_password` | `consucorner_auth_nonce` | `check_ajax_referer` ✅ |
| `consucorner_ajax_filter_category_products` | `consucorner_category_filter` | `check_ajax_referer` ✅ |
| `consucorner_ajax_get_specialty_products` | `consucorner_browse_nonce` | `check_ajax_referer` ✅ |
| `consucorner_ajax_get_recommended_products` | `consucorner_recommended_nonce` | `check_ajax_referer` ✅ |
| `consucorner_ajax_get_overall_bestsellers` | `consucorner_bestsellers_nonce` | `check_ajax_referer` ✅ |
| `consucorner_profile_*` (10 handlers) | `consucorner_profile_nonce` | via `consucorner_profile_require_user()` ✅ |
| `consucorner_ajax_apply_coupon` | `cc-apply-coupon` | `check_ajax_referer` ✅ |
| `cc_ajax_toggle_checkout_wallet_credit` | `cc_checkout_wallet` | `check_ajax_referer` ✅ |
| `cc_ajax_order_wallet_charge` | `cc_charge_order_wallet` | `check_ajax_referer` ✅ |
| `CC_Wallet_Refunds::ajax_process_wallet_refund` | `NONCE_ACTION` constant | `check_ajax_referer` ✅ |
| `CC_Vendor_Ledger::ajax_filter` | `NONCE_KEY` constant | `check_ajax_referer` ✅ |
| `CC_Vendor_Ledger::ajax_export_csv` | `NONCE_KEY` constant | `check_ajax_referer` ✅ |

---

## ✅ CLEAN — Admin Form Nonces

Both admin-page form actions use `check_admin_referer()`:

- `cc_handle_customer_wallet_admin_update()` (`customer-wallet.php:807`) — `check_admin_referer( 'cc_update_customer_wallet' )` ✅
- `cc_handle_customer_wallet_legacy_sync()` (`customer-wallet.php:859`) — `check_admin_referer( 'cc_sync_legacy_wallet_refunds' )` ✅

---

## ✅ CLEAN — Meta-Box Save Callbacks

`cc_save_meta_fields()` (the shared helper called by all 8 page save callbacks) correctly implements all three required WordPress guards:

```php
// inc/meta-boxes.php  lines 126-136
function cc_save_meta_fields( $post_id, array $keys ) {
    if ( ! isset( $_POST['_cc_meta_nonce'] )
        || ! wp_verify_nonce( wp_unslash( $_POST['_cc_meta_nonce'] ), '_cc_save_meta' ) ) {
        return;  // nonce check ✅
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;  // autosave guard ✅
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;  // capability check ✅
    }
    // ...
}
```

All meta values are further sanitized via `wp_kses_post( wp_unslash( $_POST[ $key ] ) )` ✅.

---

## Prioritised Fix Order

```
Priority 1 — Fix immediately (write protection)
  → H-1  inc/attribute-images.php:140   Add nonce + capability check to cc_save_attr_image()

Priority 2 — Fix in next dev session (sanitization standards)
  → M-1  inc/category-filters.php:369-370   Add wp_unslash() before float cast
  → M-2  inc/admin-wallet-refunds.php:133   Add array_map('absint') to $item_quantities
  → L-1  inc/customer-wallet.php:902        Add sanitize_text_field() before esc_html()
  → L-2  inc/customer-wallet.php:911-912    Cast amount_synced to (float) explicitly

Priority 3 — Fix in cleanup pass (code quality / WPCS compliance)
  → M-3  woocommerce/content-single-product.php:348   Refactor raw HTML echo
  → M-4  single.php:408 + 5 related locations         Wrap static ternaries in esc_attr()
  → L-3  inc/profile-account.php:339                  Add json_decode depth + document contract
```

---

*End of report. No files were modified during this audit.*
