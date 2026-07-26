# ConsuCorner — Codebase Brief for GTM & dataLayer

> **Purpose:** Share this document with developers or AI assistants when implementing Google Tag Manager (GTM) and `dataLayer` on the ConsuCorner website.  
> **Last reviewed:** 2026-06-01  
> **Repo root:** `app/public` (Local WP; production domain: `consucorner.com`)

---

## 1. What this site is

| Item | Detail |
|------|--------|
| **Product** | Egyptian B2B medical e-commerce marketplace (“ConsuCorner”) |
| **Stack** | WordPress 6.x, PHP 8.x, **WooCommerce**, custom theme only |
| **Active theme** | `wp-content/themes/consucorner` (v1.0.2, text domain `consucorner`) |
| **Marketplace** | **Dokan Lite** (multi-vendor) |
| **Payments** | **Paymob**, **Geidea**, plus WooCommerce **COD** / standard gateways |
| **SEO** | **Rank Math** (can inject GA4/gtag itself — see §8) |

**There is no GTM, `dataLayer`, or marketing pixels in the custom theme today.**  
The only “tracking-like” front-end code is **personalization**, not analytics (see §6).

---

## 2. Installed plugins (relevant to tagging)

```
consucorner-security          (custom)
Referral-Discounts-for-WooCommerce-RDFW-main (custom)
woocommerce
dokan-lite
paymob-for-woocommerce
geidea-online-payments
seo-by-rank-math
forminator
wt-smart-coupons-for-woocommerce
wp-all-import-pro + wpai-woocommerce-add-on
import-xml-csv-settings-to-rank-math-seo
updraftplus
```

No dedicated GTM/GA plugin is installed. Tagging will be **new work**.

---

## 3. Theme architecture (where to hook GTM)

### Bootstrap

- **`functions.php`** — theme setup, **page-conditional asset loading**, WooCommerce checkout/cart tweaks, AJAX auth, personalization, `require` of all `inc/*` modules.
- **`header.php`** — standard shell: `wp_head()`, `wp_body_open()`, global header/mini-cart/drawer.
- **`footer.php`** — `wp_footer()`.
- **`page-template-marketing-blank.php`** — landing pages: **no site header/footer**, only `wp_head()` / `wp_body_open()` / `wp_footer()` — GTM must still work here.

### Modular PHP (`inc/`)

Important for data context:

| File | Role |
|------|------|
| `category-filters.php` | Shop/category AJAX product grid, product card HTML |
| `profile-account.php` | Custom My Account (large) |
| `customer-wallet.php` | Wallet credit at checkout |
| `search-experience.php` | Live search AJAX |
| `product-specialties-taxonomy.php` | Custom taxonomy `specialty` on products |
| `product-procedures-taxonomy.php` | Custom taxonomy `procedure` |
| `dokan-overrides.php` | Dokan behavior tweaks |
| `meta-boxes.php`, `customizer.php` | CMS content |

### WooCommerce overrides (`woocommerce/`)

Custom templates for the full funnel:

- `archive-product.php`, `content-single-product.php`
- `cart/cart.php`, `checkout/form-checkout.php`, `checkout/thankyou.php`
- `myaccount/*` (custom account UX)

Thank-you page has **full `$order` object** server-side (`woocommerce/checkout/thankyou.php`) — best place for **purchase** `dataLayer` from PHP.

---

## 4. Front-end scripts (page-specific loading)

Assets are **not** one global bundle. `consucorner_scripts()` in `functions.php` enqueues different CSS/JS per route.

### Always on every page (common JS)

`mega-menu-dynamic`, `cart-badge`, `drawer`, `mini-cart`, `auth-modal`, `site-search`, **`consu-tracker`**

### Key route → extra scripts

| Route | Extra JS (examples) |
|-------|---------------------|
| Front page | sliders, `browse-specialty-ajax`, `recommended-for-you-ajax`, `fp-filter`, … |
| Shop / category / `specialty` taxonomy | `category-filter`, `ab-shop-promo` |
| Single product | `single-product`, sliders, accordion |
| Cart | `cart` |
| Checkout | `checkout` |
| Order received (thank you) | common only |
| My Account | `profile` |
| Search | `product-card-click` |

Scripts load **in footer** (`true` in `wp_enqueue_script`). GTM container should load **as early as possible in `<head>`**, with `dataLayer` **before** the GTM snippet.

### `wp_head` usage in theme

- `consucorner_resource_hints` @ priority **1** (font preloads)
- `consucorner_pingback_header` (template-functions)
- Wallet inline styles on some pages

**Recommended hook order for GTM:**

1. Priority **0–1**: initialize `window.dataLayer = window.dataLayer || []` + server pushes
2. GTM container snippet
3. Existing theme hints @ 1

Body: place GTM `<noscript>` immediately after `wp_body_open()` in `header.php` (and marketing-blank template).

---

## 5. PHP → JavaScript data already exposed (`wp_localize_script`)

These objects are the **best existing hooks** for enriching `dataLayer`:

| Global JS object | Script handle | Typical data |
|------------------|-----------------|--------------|
| `consuSiteData` | `consucorner-mega-menu` | `siteUrl`, **`cartCount`**, **`isLoggedIn`**, `userDisplayName`, `accountUrl`, `logoutUrl` |
| `consuTrackerContext` | `consucorner-consu-tracker` | Page context: product id, category/specialty slugs, search query (see below) |
| `consuCategoryFilter` | `consucorner-category-filter` | AJAX URL, category id/taxonomy, price range, locked filters |
| `consuMiniCartData` | `consucorner-mini-cart` | cart/checkout/shop URLs |
| `consuProfileData` | `consucorner-profile` | **PII**: userId, email, name, etc. — avoid pushing raw email to `dataLayer` unless required and consent-covered |
| `consuAuthData`, `consuSearchData`, … | various | AJAX endpoints, nonces |

### `consuTrackerContext` shape (built in PHP `cc_build_tracker_context()`)

```javascript
// product page
{ type: "product", product_id: 123, categories: ["urology"], specialties: ["endoscopy"] }

// product category
{ type: "category", slug: "urology", taxonomy: "product_cat" }

// specialty taxonomy archive
{ type: "specialty", slug: "ophthalmology", taxonomy: "specialty" }

// search
{ type: "search", query: "ledger v63" }

// default
{ type: "none" }
```

This is for **on-site personalization** (`consu-tracker.js` writes cookies like `consu_pref_categories`). It is **not** wired to GA4/GTM today but is useful for **content grouping** in tags.

---

## 6. E-commerce behavior critical for dataLayer

### Add to cart — multiple paths (important)

1. **Classic form** on single product (`content-single-product.php`) — standard POST/add-to-cart button classes: `single_add_to_cart_button`, `ajax_add_to_cart`.
2. **AJAX** via `fetch` to `/?wc-ajax=add_to_cart` in:
   - `assets/js/cart-badge.js`
   - `assets/js/mega-menu.js`
   - `assets/js/profile.js` (wishlist/reorder flows)

These custom AJAX flows **may not fire** WooCommerce’s default jQuery `added_to_cart` document event. For GTM, plan either:

- **GTM Custom Event** pushed in JS after successful `fetch` in those files, or
- **GTM trigger** on XHR to `wc-ajax=add_to_cart` (fragile), or
- **server-side** WC hooks (`woocommerce_add_to_cart`) + session flag (harder for pure client GTM).

Product cards in AJAX-loaded grids (`category-filter.js`) use classes like `.btn-add-cart`, `.ajax_add_to_cart` with `data-product_id`.

### Cart & checkout

- Custom cart/checkout templates under `woocommerce/cart/`, `woocommerce/checkout/`.
- **Wallet credit** can appear as order fees/meta (`_cc_wallet_*` on thank-you page).
- **Coupons**: Smart Coupons plugin; referral plugin — discount lines affect purchase value.

### Purchase / thank you

- URL: WooCommerce **`order-received`** endpoint.
- Body class: `page-thankyou` (added in theme).
- Template: `woocommerce/checkout/thankyou.php` — exposes order id, totals, payment method (COD vs online), wallet usage.
- **`woocommerce_thankyou`** hooks still run (some default output removed for design).

**Best practice here:** push `purchase` from **PHP on thank-you page only once** (use order meta flag e.g. `_cc_gtm_purchased` to prevent refresh duplicates).

### Shop listing — heavy AJAX

`category-filter.js` (~1200 lines) refetches product HTML via `admin-ajax.php`. Listing views are **not full page loads** → need explicit `view_item_list` / filter events in JS when grid updates, not only on `DOMContentLoaded`.

### Login / registration

- Custom **auth modal** (`auth-modal.js`) with AJAX login/register in `functions.php`.
- Standard WC My Account login template also exists.
- Track `login` / `sign_up` via AJAX success handlers or server redirect, not only WC defaults.

### Wishlist (client-only today)

`cart-badge.js` / `profile.js` use **localStorage** keys `cc_saved_products` and event `cc:wishlist-updated` — not server wishlist. Tag as `add_to_wishlist` only if product id is known in the handler.

---

## 7. Product data model (for item-scoped events)

| Dimension | Source |
|-----------|--------|
| **Categories** | Woo `product_cat` |
| **Specialty** | Custom taxonomy `specialty` (slug URLs) |
| **Procedure** | Custom taxonomy `procedure` |
| **Vendor** | Dokan — vendor id/name on product if needed for marketplace reporting |
| **Currency** | WooCommerce store currency (localized in `consuCategoryFilter.currency`) |

Use **WooCommerce product ID** as `item_id`; add `item_category` / custom dimensions for specialty.

---

## 8. Conflicts & compliance

### Rank Math Analytics

Rank Math includes `RankMath\Analytics\GTag` and can output **GA4 gtag** when “Install analytics code” is enabled in wp-admin.  
**Do not run Rank Math gtag + GTM GA4 Configuration + duplicate purchase events.** Pick one:

- **Preferred:** GTM only → disable Rank Math front-end snippet, keep Search Console integration in Rank Math if needed.

### Privacy / cookies

- Privacy policy content (theme) mentions analytics/device data and third-party services.
- Help pages reference cookie policy (`/help/cookies/`).
- Theme sets personalization cookies (`consu_pref_*`, 60 days, `SameSite=Lax`) — separate from marketing cookies; CMP should classify them.

### PII

- Avoid pushing email/phone in `dataLayer` unless using **Google Ads enhanced conversions** with consent and hashing.
- `consuProfileData` contains PII — do not blindly mirror to `dataLayer`.

### Payment redirects

Paymob/Geidea may redirect off-site and back — purchase event must fire on **order-received**, not on checkout button click alone.

---

## 9. Suggested GTM implementation plan

### A. Installation layer (choose one)

| Approach | Pros | Cons |
|----------|------|------|
| **Small custom plugin** `consucorner-gtm` | Survives theme changes; options page for container ID | New artifact to maintain |
| **Theme `inc/gtm.php`** + Customizer/options | Fastest in this repo | Lost if theme replaced |
| **GTM plugin from WP repo** | Quick | Less control over dataLayer order with this theme |

**Must implement:**

1. `dataLayer` init + pushes in `wp_head` priority 0–1
2. GTM script in head
3. GTM noscript after `wp_body_open`
4. Optional: filter `script_loader_tag` if CSP nonce needed (not present in theme today)

### B. Baseline dataLayer (every page, PHP)

```javascript
dataLayer.push({
  event: 'page_context',
  pageType: 'home|shop|category|product|cart|checkout|purchase|account|search|content',
  loggedIn: true, // or false — from is_user_logged_in()
  cartQuantity: 0, // WC()->cart->get_cart_contents_count()
  currency: 'EGP',
  // mirror consuTrackerContext when not 'none'
  specialty: 'slug-or-null',
  productCategory: 'slug-or-null',
});
```

Map `pageType` using same conditionals as `consucorner_scripts()` (`is_front_page`, `is_singular('product')`, `is_checkout`, `is_wc_endpoint_url('order-received')`, etc.).

### C. GA4 ecommerce events (priority)

| Event | Where to implement |
|-------|-------------------|
| `view_item` | Single product — PHP push on load + product JSON |
| `view_item_list` | Shop/category — PHP initial + **JS on category-filter AJAX success** |
| `select_item` | Clicks on product cards (delegate listener or data attributes on cards) |
| `add_to_cart` | After successful `wc-ajax=add_to_cart` in theme JS **and** WC hook fallback |
| `view_cart` | Cart page load |
| `begin_checkout` | Checkout page load |
| `add_shipping_info` / `add_payment_info` | Checkout step JS if multi-step; else on field change |
| `purchase` | **Thank-you PHP only**, items from `$order->get_items()`, transaction_id = order id |

Use [GA4 ecommerce parameter names](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce).

### D. Custom events worth adding for this site

- `search` — when live search submits (`site-search.js`, min 3 chars) or search results page
- `filter_products` — category-filter apply/reset
- `auth_login` / `auth_register` — auth modal AJAX success
- `promo_slide_click` — shop promo slider (`ab-shop-promo.js`) if marketing needs it
- `wallet_applied` — if wallet used at checkout (business-specific)

### E. WooCommerce plugin alternative

Official **Google Listings and Ads** or third-party “GTM for WooCommerce” plugins exist but this theme’s **custom AJAX cart** and **AJAX shop grid** mean a plugin alone may miss events unless it supports hooks — **verify before relying on plugin-only**.

---

## 10. Key files reference

| Purpose | Path |
|---------|------|
| Asset routing & localization | `wp-content/themes/consucorner/functions.php` (~146–536, 546–606) |
| Head/footer hooks | `wp-content/themes/consucorner/header.php`, `footer.php` |
| Personalization (not GTM) | `wp-content/themes/consucorner/assets/js/consu-tracker.js` |
| AJAX add to cart | `wp-content/themes/consucorner/assets/js/cart-badge.js`, `mega-menu.js` |
| AJAX shop grid | `wp-content/themes/consucorner/assets/js/category-filter.js`, `inc/category-filters.php` |
| Single product template | `wp-content/themes/consucorner/woocommerce/content-single-product.php` |
| Purchase page | `wp-content/themes/consucorner/woocommerce/checkout/thankyou.php` |
| Marketing landings | `wp-content/themes/consucorner/page-template-marketing-blank.php` |
| Migration / stack overview | `MIGRATION_AUDIT_REPORT.md` (repo root) |

---

## 11. Current state summary

| Question | Answer |
|----------|--------|
| GTM installed? | **Yes** — plugin `wp-content/plugins/consucorner-gtm` (container `GTM-W7V3GV43`) |
| `dataLayer` used for marketing? | **No** |
| GA4 in theme? | **No** |
| Existing analytics in code? | Rank Math *can* inject gtag; admin-vendor-ledger is **internal business analytics**, not site tags |
| Personalization tracker? | **Yes** — `consu-tracker.js` + cookies; reuse context fields, don’t confuse with GA |

---

## 12. One-paragraph implementation prompt

> Implement Google Tag Manager on a WordPress + WooCommerce site using custom theme `consucorner`. No GTM exists today. Use `wp_head` (early) for `dataLayer` + GTM head snippet and `wp_body_open` for noscript. Disable Rank Math’s direct GA4 snippet to avoid duplicates. Push GA4 ecommerce events: account for custom AJAX add-to-cart (`/?wc-ajax=add_to_cart` in theme JS), AJAX product grids (`category-filter.js`), and server-side `purchase` on `order-received` via `thankyou.php`. Enrich events with Woo product id, `product_cat`, and custom taxonomy `specialty`. Use existing `consuSiteData` (cart count, login) and `consuTrackerContext` (page type) from `wp_localize_script`. Avoid raw PII in `dataLayer`. Respect cookie policy; marketing blank template must include GTM. Payments: Paymob, Geidea, COD.

---

## Related documents

- [`MIGRATION_AUDIT_REPORT.md`](./MIGRATION_AUDIT_REPORT.md) — deployment, plugins, theme structure
- [`wp-content/themes/consucorner/README.md`](./wp-content/themes/consucorner/README.md) — theme overview
- [`wp-content/themes/consucorner/MAINTENANCE.md`](./wp-content/themes/consucorner/MAINTENANCE.md) — private team maintenance (if available)
