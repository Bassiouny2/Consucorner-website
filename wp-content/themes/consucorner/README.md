# ConsuCorner WordPress Theme

Private WordPress and WooCommerce theme built exclusively for **ConsuCorner** — Egypt’s medical supplies marketplace for healthcare professionals and clinics.

This theme is owned and maintained by **Ahmed Magdy / ConsuCorner Team**. It is not a public theme, not a starter theme, and not intended for reuse by other websites, companies, clients, freelancers, or developers without written approval from Ahmed Magdy.

|                   |                                                          |
| ----------------- | -------------------------------------------------------- |
| **Theme version** | 2.9.9 (`_S_VERSION` in `functions.php`)                  |
| **Owner**         | Ahmed Magdy / ConsuCorner Team                           |
| **Access**        | Private internal project                                 |
| **Requires**      | WordPress 6.x, WooCommerce, PHP 7.4+                     |
| **Market**        | Egypt (EGP, local shipping, governorate checkout fields) |

### Changelog (recent)

**2.9.9**

- **Banner image overrides:** Each bundle pack can set up to 3 custom collage images (top / main / bottom). Empty slots still auto-fill from pool and offer products.

**2.9.8**

- **Per-pack bundle URLs:** Each pack gets `/bundles/{campaign-slug}/{pack-key}/` instead of hash anchors. Banner CTAs and admin copy links updated.

**2.9.7**

- **Multi-bundle campaigns:** Each campaign supports one or more bundle packs (pool, size N, flat price P, per-pack banner copy). Legacy single-bundle meta auto-migrates on load.
- **Offers slug slider:** `/offers/{slug}/` shows the campaign banner slider when a campaign has 2+ active packs; single pack keeps one banner.
- **Bundles slug page:** `/bundles/{slug}/` lists all packs; each pack has its own URL `/bundles/{slug}/{pack-key}/`.
- **Collage fill:** Banner circles fill to 3 images from pool products, then offer product thumbnails, then cycle existing images.
- **Cart pack key:** Add-to-cart AJAX sends `pack_key`; cart/order lines store `_cc_bundle_pack_key` for correct multi-pack pricing.

**2.9.6**

- **Link Builder (Products menu):** New **Products → Link Builder** screen (after Campaigns) builds shareable shop/specialty filter URLs with live product count. Specialty uses archive path only (`/specialty/{slug}/`), never `?specialty=`.
- **AJAX-only storefront filters:** Shop and specialty filter pages no longer update the browser URL while filtering; pre-built admin URLs still apply on first page load.
- **Specialty URL cleanup:** `cc_parse_url_filters()` ignores `?specialty=` on specialty archives; frontend init skips specialty query params on specialty pages.

**2.9.5**

- **Offers Link Builder (admin):** New **Pages → Offers Link Builder** screen for legacy vendor + tag offers URLs and shop filter campaign URLs with live product-count preview. The Offers page edit screen links here; the Pages list shows a **Link Builder** row action on the Offers page.
- **Shop campaign URLs:** Shared shop filter links no longer include `specialty` in the query string (specialty scope stays on archive permalinks). Admin shop campaign builder also excludes specialty.
- **Offers index:** `/offers/` auto-lists all active campaign products; campaign slug URLs unchanged.
- **Offers UI:** Campaign banner slider on `/offers/` when multiple campaigns are active; bundles pool product title/image links to product pages; single-product bundle promo CTA when a product is in an active pool.

**2.9.4**

- **Cancel vs return (customer):** Customers can **Cancel order** for Confirmed → Out for delivery (not Delivered / WooCommerce Completed). After delivery/completion they use **Request return** (self-service). Ops can still create manual returns when needed.
- **Cancel UI + emails:** Profile cancel form opens reliably from the orders list; confirmation emails go to customer, ops (`admin_email`), and vendors. Approve/reject also emails customer + vendors. Customer return submit emails customer + ops.
- **Vendor Bosta column:** Dokan React Orders DataViews shows a **Bosta status** column (plus classic list header); REST payloads include Bosta/fulfillment fields.
- **Quote price in live search:** Products ≥ 100,000 EGP show **Price on request** in header live-search suggestions (no numeric price leak).
- **Fulfillment start:** Flow starts at **Confirmed** (legacy `ordered` normalized). Ops HTML guides refreshed for the new cancel/return gates.

**2.8.1**

- **Bundle-driven campaign pages:** Each Campaign uses one custom slug for clean URLs `/offers/{slug}/` and `/bundles/{slug}/`. The product pool is the single source for both the offers grid and the automatic collage banner.
- **Automatic collage banner:** Banner images come from the bundle pool (no manual background upload). The CTA always opens that exact campaign’s bundle builder.
- **Admin simplification:** Removed offer vendor/tag targeting and manual banner image fields; kept editable banner/hero text, schedule/status, and a live slug + copyable URLs panel.
- **Legacy link compatibility:** Old `/offers/?vendor=&tag=` links redirect to the matching campaign slug when a valid campaign exists.

**2.8.0**

- **Unified Campaigns admin:** Existing bundle records are now managed as **Products → Campaigns**, combining offer vendor/tag targeting, composed banner content, optional hero overrides, status/schedule, bundle setup, and both shareable URLs on one edit screen.
- **Single campaign banner:** `/offers/` resolves the newest active, in-schedule, complete campaign for its vendor + product-tag URL and renders one composed banner linking to the filtered `/bundles/` page. No valid campaign means no banner.
- **Slider retirement:** Removed the offers product-slider template, JavaScript, view-model helpers, enqueue, and obsolete CSS.
- **Performance and operations:** Campaign resolution is transient-cached for one hour with save/trash invalidation; the current operations workflow is documented in `docs/campaign-operations-guide.html` and linked from Campaign admin screens.

**2.7.3**

- **Order & return operations workflow:** New `inc/order-return-workflow.php` service with separate fulfillment (`ordered → delivered`) and return (`requested → resolved`) state machines stored on order meta per vendor.
- **Operations dashboard:** WooCommerce → Returns now includes order lookup, per-vendor fulfillment controls, vendor-split manual RMA creation, and return workflow actions (reviewing, approve, in transit, received, reject) before Wallet/Direct resolution.
- **Customer returns:** Profile orders and Dokan return form are gated on operations fulfillment (`shipped` / `out for delivery` / `delivered`), with fulfillment and return status chips in the profile order UI.
- **Vendor visibility:** Dokan vendor order list/detail shows read-only ConsuCorner fulfillment/return status; vendor RMA status mutation controls are hidden (operations remains authoritative).
- **Notifications:** Email alerts to customer and vendor on operations-created returns and workflow status changes; Geidea is never auto-reversed.

**2.7.2**

- **Mini-cart price fix (single product):** `extractItemData()` now reads `.sp-price` on product pages; add-to-cart buttons carry `data-product-price` / `data-product-name` / `data-product-image` so the drawer shows the correct price immediately instead of `0 EGP`.
- **Mini-cart server sync:** `cc_get_cart_json` now runs `WC()->cart->calculate_totals()` before building items; `cc_build_mini_cart_items()` no longer overwrites the catalog unit price when `line_subtotal` is still `0` (un-calculated cart).
- **Variable products:** Optimistic mini-cart update on AJAX add-to-cart (instant price/qty in drawer before server sync).

**2.7.1**

- **Bundles page redesign:** Rebuilt `/bundles/` with a branded hero — dynamic "N for P EGP" pack tag and a product-image grid pulled from the active bundle — plus step-by-step instructions and a card-based bundle products layout. All bundle styles moved into a dedicated `assets/css/bundles-page.css` (split out of `offers-page.css`).
- **Bundle cards:** Product image mosaic + flat price pill, pool thumbnails per item, and a live selection progress bar with an `is-complete` state (`assets/js/bundle-builder.js`).
- **Bundle admin — vendor fix:** The vendor dropdown on the Bundle edit screen now lists all Dokan sellers; `cc_offers_get_vendors_list()` handles the `stdClass` records returned by `Consucorner_Vendor_Ledger::get_vendors()` (with Dokan store-name labels).
- **Bundle admin — tags:** Bundle tags are now selectable and creatable directly in the Bundle details metabox (checkboxes + "create new tag"), saved via `wp_set_post_terms()`/`wp_insert_term()`, and kept in sync with the campaign link builder dropdowns.

**2.7.0**

- **Return Orders Monitoring:** New **WooCommerce → Returns** admin report on Dokan RMA tables — filter by date, vendor, status, type, order; CSV export; HPOS-safe order lookups.
- **Resolution routes:** **Wallet** credits `cc_add_to_custom_wallet()` with shared Dokan vendor balance adjust; **Direct (manual)** records a WooCommerce refund (`refund_payment` off) for offline refunds — no Geidea auto-reversal.
- **Shared service:** `inc/returns-refund-service.php` centralizes vendor earning adjustment and resolution meta on orders/line items.
- **Dedupe guard:** Order wallet meta box warns on open RMA requests and blocks items already resolved via RMA or WC refund.
- **Setup doc:** `RETURN-ORDERS-SETUP.md` checklist for activating/configuring Dokan RMA (`rma_enable_refund_request` OFF recommended initially).

**2.6.0**

- **Mix-and-Match Bundles:** Reworked `cc_bundle` from a fixed hidden-product pack into a customer-built model — admin sets a product pool, bundle size N, flat price P, and optional vendor; customers pick any mix totaling exactly N items on `/bundles/`.
- **Bundles page:** Dedicated `/bundles/?vendor=&tag=` campaign filtering (same pattern as Offers), builder cards with pool steppers, live "X / N selected" counter, and AJAX add-to-cart.
- **Cart pricing:** Each complete group of N is priced so the instance totals exactly P (last line absorbs rounding remainder); Offer Deal pricing skips bundle lines.
- **Cart / mini-cart:** Bundle instances render as a framed group with a single price P, locked per-line quantities, and one "Remove bundle" control that clears the whole instance.
- **Checkout:** Individual pool products remain as native order lines; `_cc_bundle_id`, instance, and bundle name are persisted on each line item.
- **Admin:** Pool multi-picker, N/P/vendor/active fields, and vendor+tag campaign link builder on the Bundles CPT edit screen.

**2.5.0**

- **Pricing & Deals product tab:** Offer Deal (bundle qty + total) and new Bulk pricing (repeatable quantity-tier per-unit price) fields moved into a native WooCommerce Product Data tab, replacing the old "Offer Deal (offers page)" meta-box section.
- **Bulk pricing (site-wide):** New `_cc_bulk_enabled` / `_cc_bulk_tiers` fields define whole-order quantity tiers (e.g. 5-25 units, 26-50 units...) with a per-unit price and Save % each, shown on the shop/offers cards, the single product page, and applied in the cart from anywhere.
- **Single product display:** Offer Deal highlight and an interactive Bulk pricing tier grid render under the price; selecting a tier updates the quantity field and live unit price (and the mobile sticky bar) without a page reload.
- **Cards (site-wide):** Compact "Bulk pricing from X EGP" badge on every product card (shop, offers, home sections), alongside the existing offers-only Offer Deal block.
- **Cheapest-price cart rule:** When a product has both an Offer Deal and Bulk tiers applicable to the cart quantity, the cheaper per-unit price is charged automatically.

**2.4.0**

- **Per-product offer deals:** Product editor fields for bundle qty + total price (e.g. 10 pcs for 800 EGP). Displayed on the Offers page only with strikethrough regular total, deal price, and Save % badge.
- **Exact-qty cart pricing:** When cart line quantity exactly matches the deal qty, bundle unit price is applied site-wide (shop or offers). Other quantities use the normal per-piece price.
- **Offers add-to-cart:** Deal products use an "Add 10 for 800 EGP" button that adds the exact bundle quantity.

**2.3.4**

- **Offers vendor param:** Campaign URLs now use WordPress `user_login` (`?vendor=username`) instead of store nicename/slug; admin link builder shows `@username` in the vendor dropdown. Older nicename links still resolve.

**2.3.3**

- **Dynamic Offers page:** `/offers/?vendor=username&tag=product-tag` loads vendor + tag filtered products on one landing page with normal site header/footer.
- **Shop-identical cards:** Reuses `cc_render_product_card()` inside `.fp-products-main .fp-products-grid` (desktop grid, mobile list layout).
- **Offers qty bar:** Optional stock quantity bar on offers cards when inventory is managed (`show_qty_bar`).
- **Hero copy:** Marketing hero defaults — Certified Excellence badge, Flash Deals title, and campaign description (editable in Pages → Offers).
- **Admin link builder:** Pick Dokan vendor + `product_tag` on the Offers page edit screen → copy shareable campaign URL; live product count preview.
- **Card title styling:** `category-archive.css` enqueued on offers so `.product-card-title-link` matches shop.
- **Auto-provision:** Theme seeds `/offers/` page with `page-offers.php` template on setup.

**2.2.5**

- **Shop filters (desktop):** Dropdown panels mount into `.cc-filter-dropdown-host` and re-anchor on scroll/resize; sticky filter bar preserved when a panel is open.
- **Sticky add-to-cart (mobile):** Two-row bar with title (2 lines max), price, qty, Buy now, and Add to cart.
- **Homepage vector banner:** Optional clickable link via meta box (`_cc_home_vector_banner_link`).
- **Footer:** Services column hidden; `--gradient-dark` background; simplified Explore/Legal layout; mobile bottom bar centered; 2-column nav on narrow screens.
- **Geidea + WhatsApp:** Payment failure and HPP cancel open help modal (`geidea-alert-bridge.js`, cart `geidea-session` flow); Geidea plugin untouched.
- **Checkout errors:** Field-level validation only (no duplicate top banner); WhatsApp modal only for Geidea payment issues.

**2.2.4**

- **Shop filters:** Category filter order matches the mega menu (`menu_order` + WooCommerce term order).
- **Checkout validation:** Required-field and WooCommerce errors show inline under each input.
- **Card payment errors:** Visa/card (Geidea) failures open the WhatsApp help modal; COD keeps standard inline notices.
- **Cart tour:** Share cart and coupon steps added; page scroll and clicks locked during the tour.

**2.2.3**

- **Checkout errors:** Error modal disabled by default; standard WooCommerce inline notices are shown again.
- **Share cart:** Trigger button uses primary brand color on cart and checkout pages.

**2.2.2**

- **Hero slider:** CTA buttons wired to meta Button link; fixed overlap and click/accessibility on active slide.
- **Shop promo:** Mobile subtitle 3-line clamp; static term banner taller aspect ratio (1208/570).
- **Footer:** Removed Connect/Resources columns; Customizer social URLs; dark navy layout redesign.

**2.2.1**

- **Checkout error modal:** Validation failures, WooCommerce AJAX errors, and page-load notices open a modal with WhatsApp CTA (`01555458555`).
- **Shareable cart:** “Share cart” on cart and checkout generates a restore link so recipients load the same products.
- **Modal design:** Fixed share-link input overflow and matched button heights/borders on both modals.

**2.1.49**

- **Checkout error modal:** Validation failures, WooCommerce AJAX errors, and page-load notices open a modal with WhatsApp support (`01555458555`).
- **Shareable cart:** Cart and checkout include a “Share cart” button that generates a restore link so recipients load the same products.

**2.1.48**

- **Banner tag icons:** Homepage meta “Tag icon” picker now controls the white icon next to tag text (homepage + shop sidebar banners). Button cart icon is not editable.

**2.1.47**

- **Hero banner icons (frontend):** Restored the original white stroke SVG tag + cart button icons. Font Awesome classes no longer render on the live banner (they were picking up the mobile drawer `.cc-icon` green bubble). Admin icon picker is unchanged.

**2.1.46**

- **Icon picker:** Homepage hero banner tag/button icons use a searchable Font Awesome popup in wp-admin (click “Choose icon” next to each field).

**2.1.45**

- **Hero banner icons:** Product-banner slider uses Font Awesome (Dokan library on site) via `consucorner_icon()` — tag + cart icons on homepage and filter sidebar banners. Editable per slide in Homepage meta (`Tag icon` / `Button icon` FA class fields).

**2.1.44**

- **Static promo mobile ratio:** Term archive banners use `aspect-ratio: 1208 / 500` on mobile.

**2.1.43**

- **Mobile promo height:** Banner uses 2:1 aspect ratio with 176px min-height on mobile so title, button, and origin flags fit without clipping.

**2.1.42**

- **Mobile origin popup:** `+N` opens a bottom-sheet modal (backdrop, title, close button, scrollable country list) portaled to `body` so it is never clipped by the banner.
- **Desktop:** Keeps the compact anchored popover above the flag cluster.

**2.1.41**

- **+N popover fix:** `ab-shop-promo.js` now loads on specialty/category archives (not only main `/shop/`), so the origin popover toggle works on static term banners.
- **Popover visibility:** Opens above the cluster; viewport allows overflow while the popover is open.

**2.1.40**

- **Origin overflow popover:** Removed sourced-from subtitle on 5+ country promo clusters; `+N` toggles a popover with all countries (flag + name + filter link). Closes on outside click or Escape.

**2.1.39**

- **Adaptive archive origins:** Specialty and `product_cat` promo banners show Country of Origin from catalog products — single flag (1), overlapping cluster (2–4), or top 3 + `+N` with sourced-from text (5+). Links use `cc_build_archive_filter_url()` to filter the current archive.
- **Term promo admin:** Removed flag/badge upload fields from specialty and category term screens; ops only assign Country of Origin on products.
- **Banner ratio:** Promo viewports use `aspect-ratio: 1208 / 390`; `cc_shop_promo_banner` image size registered for term and Customizer backgrounds.
- **+N UX:** `ab-shop-promo.js` opens the mobile country filter sheet or scrolls to the desktop Country of Origin panel.

**2.1.38**

- **Shop price filter:** Get Quote products (price ≥ `CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD`) are excluded from slider max, histogram buckets, product-in-range counts, and active price queries via `cc_get_price_filter_ceiling()` / `cc_price_filter_exclude_quote_meta_query()`; they remain visible in the grid when no price filter is applied.
- **Price histogram UI:** Fixed canvas overflow past the filter panel — uses rendered width, `overflow: hidden` wrapper, canvas clip, and draws on all `.cc-price-canvas` instances (desktop + mobile).
- **Profile orders:** In-popup order detail view (list ↔ detail), `consucorner_profile_get_order` AJAX, deep links (`?cc_order=` + `?cc_key=`), guest order modal on login, Bosta tracking in order details.
- **Track order:** `cc_resolve_order_id_from_reference()` accepts display order numbers; Bosta tracking URL row on track-order results.
- **Shop promo:** Main `/shop/` uses Customizer slider (`inc/shop-promo-customizer.php`); specialty/category archives use per-term static banners (`inc/term-promo-banner.php`).

**2.1.37**

- **Blog `[cc_shop_now]`:** Site logo replaces text brand lockup; button uses white-on-blue with higher specificity so blog content link styles cannot override it; responsive card layout without mobile overlap.

**2.1.36**

- **Blog `[cc_shop_now]` shortcode:** Shop Now pill + ConsuCorner banner CTA for posts, with optional `url`, `text`, and `label` attributes.

**2.1.35**

- **Often Ordered With fatal:** `cc_render_product_card()` now accepts string product IDs from `wc_get_related_products()` fallback.
- **Mini-cart:** Debug instrumentation removed; production qty race + stock cap behavior from 2.1.34 unchanged.

**2.1.34**

- **Mini-cart qty race:** `qtyRequestSeq` latest-request-wins guard and `AbortController` prevent stale AJAX responses from reverting quantity after rapid +/- clicks.
- **Stock cap:** `cc_get_product_max_qty()` clamps `cc_update_cart_qty_ajax()` and exposes `maxQty` per mini-cart line so customers cannot exceed available stock.

**2.1.31**

- **Often Ordered With:** Scoped archive mobile list CSS to `.fp-products-main .fp-products-grid` only; OOW carousel reuses homepage card layout (`single-product.css`).

**2.1.30**

- **Profile desktop:** Full display name and email (no ellipsis truncation).
- **Profile mobile:** Hero padding/vertical centering so “Profile” title and breadcrumbs are not clipped.

**2.1.29**

- **Profile CSS cascade:** Enqueue order `shop-page → responsive → profile` so mobile profile hero overrides generic `responsive.css` page-head rules.

**2.1.28**

- **Profile mobile:** Reference design — teal gradient background, glass profile card, 2-column menu, bottom-sheet modals, profile-specific footer.

**2.1.27**

- **Single product filter links:** Country and brand pills link to shop filter URLs via `cc_build_shop_filter_url()` when a filterable term exists.

**2.1.26**

- **Blog FAQ sizing:** Smaller type, tighter padding, 900px max-width, mobile adjustments.

**2.1.25**

- **Blog Arabic RTL:** Title, lead, body, and FAQ use `direction: rtl`; `dir="rtl"` on article + FAQ; detection includes excerpt and FAQ text.

**2.1.24**

- **Term order propagation:** `consucorner_sort_terms_by_order()` applied to mobile drawer (category + specialty) and homepage categories slider; specialty `order` meta updates bust drawer cache (v7).

**2.1.23**

- **Get A Quote modal render:** The shared quote modal now explicitly reveals Forminator forms when opened, removing Forminator's initial `display:none` state inside the hidden shop/archive modal and triggering Forminator's frontend loaded event. This fixes the modal header showing without the form fields on shop pages.

**2.1.22**

- **Mini-cart remove race:** Remove clicks are now isolated with `preventDefault()` / `stopPropagation()`, the cart row is locked with `is-processing`, row buttons are disabled, pending quantity requests for the same item are aborted/ignored, and WooCommerce fragments refresh only after the remove AJAX succeeds. This prevents stale quantity responses from re-saving an item after it was deleted.

**2.1.21**

- **Get A Quote cart guard:** Quote-product CTAs no longer use the generic `.btn-add-cart` class, so the custom mini-cart/cart-badge handlers cannot treat them as cart actions. Those handlers also explicitly ignore `btn-add-cart--quote` / `js-cc-quote-trigger` for stale cached markup, and WooCommerce now blocks/removes quote-only products from cart sessions so the mini-cart, cart icon, and cart page stay clean.

**2.1.20**

- **Get A Quote archive cards:** Quote-product CTAs in shared product cards no longer use WooCommerce AJAX add-to-cart selectors. They now render as `js-cc-quote-trigger` modal triggers, open the same `ccQuoteModal`/Forminator quote form used by single product pages, and use delegated JavaScript so buttons still work after AJAX archive filtering replaces the grid.

**2.1.19**

- **Specialty drag handle fix:** Explicitly loads WooCommerce's `term-ordering` script on **Products → Specialties** and adds a visible drag handle column, so operations can drag specialty rows reliably even when WooCommerce's normal enqueue condition is skipped. Saving still uses WooCommerce's nonce-protected AJAX handler and only updates the `order` term meta.

**2.1.18**

- **Specialty order (drag-and-drop):** Specialties now reorder by dragging rows on **Products → Specialties**, exactly like WooCommerce product categories. This registers `specialty` via the `woocommerce_sortable_taxonomies` filter, so it reuses WooCommerce's drag handles, jQuery-UI sortable, nonce-protected `woocommerce_term_ordering` AJAX handler, and `order`-meta LEFT-JOIN sorting (terms without an order value are never hidden). The numeric "Display order" field/column was removed in favor of dragging. Reordering only changes each term's `order` meta — **no specialties are created or deleted**. Because `consucorner_sort_terms_by_order()` reads that same `order` meta, the new order is reflected in the shop mega-menu specialty section (`.mega-section-specialty`) and in every shop archive specialty filter (`.fp-sidebar`), and the mega-menu transient is flushed automatically on reorder.

**2.1.17**

- **Homepage testimonials:** Removed all testimonial-section override rules (legacy name/text replacement and duplicate-name guards). The homepage now reads testimonial meta exactly like other homepage fields via `cc_front_meta()` — saved meta-box content is what displays.

**2.1.16**

- **Single product:** Often Ordered With cards now use the shared shop `cc_render_product_card()` template and `fp-products-grid` styling so they match the shop archive product cards.

**2.1.15**

- **Homepage testimonials:** Meta-box edits now display on the homepage again. Removed duplicate-name override logic that could ignore saved reviewer names; testimonial defaults and resolution are centralized in `cc_home_get_testimonials()`.

**2.1.14**

- **Explore menu:** The Explore "links" column (`.explore-links-col`) is now editable from **Appearance → Menus**. Assign menus to the new **Explore - Important** and **Explore - Help** locations, or name them `Important desktop menu` / `Help Desktop menu`. Resolution order is location → menu name → built-in fallback.
- **Specialty order (operations-editable):** Each Specialty term now has a **Display order** field (Products → Specialties) plus a sortable **Order** column. Lower numbers come first; ties fall back to alphabetical. The same order drives both the shop mega-menu specialty section (`.mega-section-specialty`) and the shop archive specialty filter (`.fp-sidebar`), via the shared `consucorner_sort_terms_by_order()` helper.
- **Shop filters:** Generated filter URLs use standard `%2C`-encoded commas again (for example, `?specialty=orthopedic%2Cgynecology%2Cent&min_price=105&max_price=56000`); the server decodes them into the full multi-select filter.

**2.1.13**

- **Shop filters (root-cause fix):** Query-string filter parameters (for example, `/shop/?specialty=a,b,c`) are no longer routed by WordPress as single-term taxonomy archives. A `request` filter strips filterable taxonomy query vars that arrive via the query string so the Shop page renders with the full multi-select filter applied. Pretty-permalink archives (such as `/specialty/ent/`) are untouched because their term comes from the rewrite path, not `$_GET`.

**2.1.12**

- **Shop filters:** Specialty panel keeps all rendered specialty options visible while preserving selected URL specialties after availability updates.

**2.1.11**

- **Shop filters:** Generated browser URLs now preserve visible comma-separated taxonomy slugs (for example, `specialty=a,b,c`) for campaign sharing.

**2.1.10**

- **Shop filters:** Shared campaign URLs keep comma-separated taxonomy slugs while the frontend uses server-resolved term IDs, preserving multi-specialty filters on first load.
- **Shop filters:** Filtered URLs no longer trigger an immediate duplicate AJAX fetch that can collapse selected terms after server render.

**2.1.9**

- **Single product:** Often Ordered With now shows products from the same specialty taxonomy (fallback to WooCommerce related products).
- **Single product:** Vendor pill is hidden on Get A Quote products.

**2.1.8**

- **Shop promo slider:** Each slide opens its own Customizer URL after next/prev/autoplay loop (`data-promo-url`, `syncSlides()`, defensive overlay pointer rules).
- **Shop filters:** Price panel count (`#fpPriceCount`) updates live with the price range, scoped to the current archive and active filters.

**2.1.7**

- **Orders:** Custom WooCommerce processing-order email (Order Confirmed design) with dynamic fields, inline email-safe markup, and plain-text fallback (`inc/order-email.php`).
- **Orders:** Custom display order numbers (`MM` + order ID + `SS`) via `inc/order-number.php`.
- **Product:** Removed Report Abuse link from single product pages.
- **Email:** Dropped default WooCommerce additional content; fixed CSS showing in inbox; centered header; full-width info cards with column/row gaps; single-color footer feature icons.

**2.1.6**

- **Testimonials:** Replaced homepage and checkout reviews with three Arabic doctor testimonials (DR. Khalid Elbeltagui, DR. Shady Abd Elsalam, DR. Salah Helmy).
- **Home/mobile:** Homepage product card Add to Cart buttons are centered on mobile across Browse, Bestsellers, and Recommended sections.

**2.1.5**

- **Bestsellers:** Sale prices now render as a cleaner discount stack on desktop and mobile.
- **Testimonials:** Homepage display names now guard against repeated saved defaults without changing product review authors.
- **Shop filters:** Price inputs no longer reset while typing, and the price total uses the server-provided non-price-filtered count.

**2.1.4**

- **Home/mobile UI:** Bestsellers card spacing improved and mobile carousel shows one card with a next-card peek.
- **Mobile drawer:** Logo now links to the homepage.
- **Testimonials:** Default reviewer names are unique across cards.
- **Shop filters:** Min price input supports free numeric typing; price total count reflects the non-price filtered product set.
- **Tours:** Highlighted page controls are blocked during tours so users only use tour controls.
- **Mobile filters:** Filter labels align left and the results row is hidden.

**2.1.3**

- Cart page quantity +/- uses the same `cc_update_cart_qty` AJAX endpoint as mini-cart; page reloads after success.

**2.1.2**

- **Mobile drawer Shop by Category:** Removed the "Sourced from top manufacturers" subtitle.
- **Mobile drawer sliders:** Arrow controls moved beside section titles in a `bestsellers-header`-style pill nav for Shop by Category and Shop by Specialty.
- **Cache:** Shop drawer transient bumped to v6.

**2.1.1**

- **Mobile drawer sliders:** Added previous/next arrow controls to `Shop by Category` and `Shop by Specialty`.
- **Shop by Specialty:** Removed shadows from all gradient specialty cards.
- **Cache:** Shop drawer transient bumped to v5.

**2.1.0**

- **Checkout:** Removed `billing_city`; Governorate is full-width below Shipping Address
- **Mobile drawer Explore:** Important Links cards match Figma — taller layout, bottom-aligned titles/subtitles, Partners card always active (green) with decorative ring accents
- **Cache:** Explore drawer transient bumped to v4

**2.0.9**

- **Checkout payment default:** Cash on Delivery is pre-selected when available; submitted payment choice is preserved if checkout validation returns errors.
- **Cart mobile order:** Mobile cart now displays `cart-list-card` first and `cart-summary-card` after it.

**2.0.8**

- **Product category icons:** `product_cat` terms now have a Category Icon field in WP Admin; helpers `cc_get_product_cat_icon_url()` and `cc_get_product_cat_icon_info()` for reuse across the theme
- **SVG uploads:** Media Library accepts SVG/SVGZ for users with upload capability (MIME fix + admin preview styles)
- **Mobile drawer sliders:** Shop by Category and Shop by Specialty use horizontal snap sliders showing all terms (4 per slide); Explore Important Links remain a static 2×2 grid
- **Mobile drawer sizing:** Category cards fixed to compact Figma height (105px) so slider slides no longer stretch oversized

**2.0.7**

- **Mobile drawer redesign:** New card-based design matching Figma — light background with subtle radial gradient, Manrope font, no teal background
- **Shop tab:** Top-4 product categories in 2×2 card grid with category icon support (`cc_get_product_cat_icon_url`); top-4 specialty terms as gradient cards (teal / blue / purple / teal variants)
- **Explore tab:** 4 Important Links (About, Contact, Blog, Partners) as static 2×2 card grid — not a slider; Help & Guidelines as a white rounded list with SVG icons
- **Cache:** Transients bumped to v3; specialty term edits and `_cc_product_cat_icon` meta updates now bust the drawer cache

**2.0.6**

- **Get A Quote:** Forminator form ID is centralized in `functions.php` via `CONSUCCORNER_GET_QUOTE_FORMINATOR_ID` and `cc_get_quote_forminator_form_id()`; the quote modal and submission capture both read from this single source (filter: `consucorner_get_quote_forminator_form_id`)
- **Get A Quote threshold:** Quote-only price condition is centralized in `functions.php` via `CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD` and `cc_is_quote_product()`; single product pages, product cards, and Often Ordered With cards now use the same helper
- **Privacy Policy:** Frontend policy body now uses the main WordPress editor content, with legacy section meta used only as a fallback when the editor is empty

**2.0.5**

- **Get A Quote:** Products priced at 50,000 EGP or higher now use a quote-only flow with a styled Forminator popup and dynamic Quote Order Thank You page data
- **Quote pricing:** Quote-product prices are hidden on single product pages, shared product cards, and Often Ordered With cards; card CTAs route users to the product quote flow
- **Country of Origin taxonomy:** Added `country_of_origin` as a custom product taxonomy with term image support, product admin filtering, and one-time migration from legacy WooCommerce country attributes
- **Country display/filtering:** Single product pills, product cards, archive filters, AJAX filter availability, and the shop-specialty country slider now prefer the new taxonomy

**2.0.4**

- **Checkout:** Hidden `shipping_method` fields now output the active WooCommerce rate (flat rate / free shipping), fixing live "No shipping method has been selected" on place order
- **Checkout:** Governorate rendered as a native `<select>` with Egypt governorate fallback; `checkout.js` refreshes totals/shipping when address fields change
- **Mini-cart ↔ WC cart:** Drawer loads from WooCommerce via `cc_get_cart_json`; remove and qty changes call `cc_update_cart_qty` (with `product_id` fallback when `wcKey` is missing)
- **Cart badge:** `consuSiteData.cartCount` localized on `cart-badge` script; after add-to-cart, mini-cart re-syncs from WC for correct price and keys; sale items use `<ins>` price from product cards

**2.0.3**

- **Free shipping UI:** Cart `.cart-top-sub` and mini-cart `.mc-shipping-bar` read enabled WooCommerce free-shipping methods (`inc/wc-free-shipping.php`); both hide when free shipping is off or coupon-only; threshold text and progress bar use the configured minimum order amount
- **Shop filters:** Shareable filter URLs with PHP SSR on archive load, `history.pushState` in `category-filter.js`, canonical URLs for filtered archives
- **Sub-category filter:** Always shown on shop/archive when options exist (no parent-category lock)
- **Guided tours:** Contextual welcome (home/search only), shop tour steps (filter bar → Specialty → Category), top-centered home popovers, search-bar Driver tour, per-page skip (no global “skip all”)

**2.0.2**

- Cart & checkout: compact branded page head on mobile/tablet (100px banner, 40px title, 12px breadcrumbs); desktop checkout copy no longer swaps to “Cart” on small screens
- Single product: mobile sticky bottom bar (price, quantity, Buy now, Add to cart) when the main form scrolls out of view
- Checkout: removed `cc-checkout-help` widget and its asset enqueue

**2.0.1**

- Guided product tours v2 (Driver.js), welcome modal, REST state sync

---

## Table of Contents

1. [What Is ConsuCorner?](#what-is-consucorner)
2. [Platform & Integrations](#platform--integrations)
3. [Main Features](#main-features)
4. [Guided Product Tours (New)](#guided-product-tours-new)
5. [Homepage & Storefront](#homepage--storefront)
6. [Taxonomies & Product Model](#taxonomies--product-model)
7. [Customer Account & Wallet](#customer-account--wallet)
8. [Vendor & Admin Tools](#vendor--admin-tools)
9. [Auto-Provisioned Content](#auto-provisioned-content)
10. [AJAX & Frontend APIs](#ajax--frontend-apis)
11. [Folder Structure](#folder-structure)
12. [Customization Map](#customization-map)
13. [Related Projects](#related-projects)
14. [Local Development](#local-development)
15. [Maintenance & Deployment](#maintenance--deployment)
16. [Coding Rules](#coding-rules)
17. [Team Access & Ownership](#team-access--ownership)

---

## What Is ConsuCorner?

ConsuCorner is a **multi-vendor WooCommerce marketplace** focused on medical instruments, consumables, and specialty equipment. The theme delivers:

- A UX-heavy storefront (filters, live search, mega menus, mobile drawer)
- Custom checkout and account flows tuned for Egyptian buyers
- Dokan Pro vendor storefront behavior with theme-level overrides
- **GeIdeA** as the primary online payment gateway (must never be broken by theme or security changes)
- Editable marketing sections via WordPress admin meta boxes on key pages

The theme is the presentation and business-logic layer. Heavy security runtime modules live in the separate **ConsuCorner Security** plugin (`wp-content/plugins/consucorner-security/`).

---

## Platform & Integrations

| Layer                | Technology                           | Notes                                                      |
| -------------------- | ------------------------------------ | ---------------------------------------------------------- |
| CMS                  | WordPress                            | Static front page, custom page templates                   |
| Commerce             | WooCommerce                          | Cart, checkout, orders, coupons                            |
| Marketplace          | Dokan Pro                            | Multi-vendor products, vendor dashboards                   |
| Payments             | GeIdeA                               | Webhooks at `/wc-api/geidea*` — do not block or rate-limit |
| Hosting (production) | Cloudways VPS (Nginx)                | Theme assets are page-conditional for performance          |
| Analytics            | GTM / DataLayer (`consu-tracker.js`) | Product and commerce events where configured               |

**Compatibility rules (non-negotiable):**

- Do not break Dokan or WooCommerce REST routes used by vendors and apps
- Do not block verified Google crawlers or SEO paths (`sitemap.xml`, `robots.txt`)
- Do not interfere with GeIdeA callback URLs or payment iframes

---

## Main Features

### Storefront & discovery

- Custom **shop archive** with specialty, category, procedure, condition, brand, and price filters (`inc/category-filters.php`, `assets/js/category-filter.js`)
- **Live AJAX search** in the header (`inc/search-experience.php`, `assets/js/site-search.js`)
- **Product mega menu** and **Explore mega menu** with Customizer controls
- **Mobile drawer** navigation (`inc/mobile-drawer-menu.php`, `assets/js/drawer.js`)
- Specialty archive template (`taxonomy-specialty.php`)
- Dedicated shop landing pages: Shop Instruments, Shop Specialty
- A/B shop promo section (Customizer + `template-parts/shop-promo-section.php`)

### Product experience

- Custom single-product layout (`woocommerce/content-single-product.php`, `assets/css/single-product.css`)
- **Mobile sticky add-to-cart bar** on single product (price, qty, Buy now / Add to cart) — `assets/js/single-product.js`
- Attribute images for variations (`inc/attribute-images.php`)
- Dokan catalog-mode overrides so products stay purchasable (`inc/dokan-overrides.php`)

### Cart & checkout

- Custom cart and empty-cart templates
- Branded **page head** on cart and checkout (compact on viewports ≤900px)
- Checkout form tailored for Egypt (governorate, phone, address labels in `functions.php`)
- Customer **wallet credit** at checkout (`inc/customer-wallet.php`)
- GeIdeA-safe checkout markup (no framing or script blocking of payment domains)

### Account & engagement

- Full **My Account dashboard** replaced by theme UI (`front-end/profile.html`, `inc/profile-account.php`, `assets/js/profile.js`)
- Wishlist, orders, privacy, notifications, avatar upload via AJAX
- Auth modals: login, signup, lost password (AJAX in `functions.php`)
- Contact, vendor, FAQ, Help hub, About, Privacy, Terms page templates
- Blog archive (`page-archive-posts.php`, `assets/css/blog.css`)

### Homepage (dynamic)

- Hero, payment trust block, category grids, browse-by-specialty (AJAX)
- Best sellers / new arrivals sliders
- Recommended-for-you and overall bestsellers (AJAX)
- Testimonials, fast delivery, marketing banners — mostly editable via homepage meta boxes (`inc/meta-boxes.php`)

### Operations

- **Admin vendor ledger** (filter + CSV export)
- **Wallet refunds** workflow for admins
- WP-CLI helpers for specialty assignment and migration stats (in `inc/*-cli.php`)
- RTL stylesheet (`style-rtl.css`) for Arabic layout support

---

## Guided Product Tours (v2)

Opt-in onboarding powered by [Driver.js](https://github.com/kamranahmedse/driver.js) (spotlight + popover) and a custom Welcome modal. Checkout does not load tour assets (GeIdeA-safe).

### Architecture

| Piece         | Location                                                     | Role                                                   |
| ------------- | ------------------------------------------------------------ | ------------------------------------------------------ |
| State + gates | `inc/product-tour-state.php`                                 | `cc_site_tours_v2` schema, enqueue guards, idle cookie |
| REST sync     | `inc/product-tour-rest.php`                                  | `GET/POST /wp-json/cc/v1/tours/state` (logged-in)      |
| Bootstrap     | `inc/product-tour.php`                                       | Assets, localized `ccProductTour`                      |
| Core          | `assets/js/product-tour-core.js`                             | State, session rules, Driver runner, REST debounce     |
| Phases        | `assets/js/product-tour-phases.js`                           | Step builders (max 3 steps, element required)          |
| Welcome       | `assets/js/welcome-modal.js`, `assets/css/welcome-modal.css` | Opt-in modal (no spotlight)                            |
| Analytics     | `assets/js/product-tour-analytics.js`                        | `consucorner_tour` DataLayer events                    |
| Driver theme  | `assets/css/product-tour.css`                                | Popover + overlay z-index                              |
| GTM ops       | `docs/gtm-tours-v2.md`                                       | Tag/trigger setup (outside theme)                      |
| QA            | `docs/tours-v2-qa-checklist.md`                              | Staging checklist before launch                        |

### Phases

| Phase    | Auto-start                             | Notes                                                        |
| -------- | -------------------------------------- | ------------------------------------------------------------ |
| Welcome  | First visit (`welcome_seen === false`) | Never with page tour same load                               |
| home     | `welcome_path === specialty`           | `.browse-specialties-section`, `.browse-categories-carousel` |
| home     | `welcome_path === categories`          | `.popular-categories`                                        |
| shop     | Not after search path                  | Skip step 1 on specialty archive                             |
| product  | Single product                         | Skips add-to-cart if OOS/hidden                              |
| cart     | Cart count &gt; 0                      | `.cart-list-card`, `.cart-checkout-btn`                      |
| account  | Logged-in dashboard                    | Orders + wallet modals                                       |
| wishlist | Event `cc:wishlist-saved` only         | 2 steps max, after save animation                            |

**Storage:** `localStorage` key `cc_site_tours_v2`; logged-in users sync via usermeta + REST. Legacy `cc_product_tour_v1` migrates on first load.

### Changing steps

1. Add `data-cc-tour="slug"` (or stable IDs like `#browse-grid`) on the target element.
2. Update builders in `assets/js/product-tour-phases.js`.
3. Add strings under `consucorner_get_product_tour_strings()` in `inc/product-tour.php`.

---

## Homepage & Storefront

`front-page.php` is bound to the auto-created **Home** page. Major sections:

1. Hero + payment logos / trust copy
2. Popular categories
3. Browse specialties (AJAX product grid)
4. Best sellers slider (page meta + JS)
5. Overall bestsellers block
6. New collection / category tiles
7. Vector marketing banner
8. Recommended for you (AJAX)
9. Fast delivery CTA
10. Testimonials

Most hero and section copy/images are controlled from **Home page meta boxes** in wp-admin, not hard-coded in the template.

---

## Taxonomies & Product Model

| Taxonomy               | Slug          | Purpose                                                                         |
| ---------------------- | ------------- | ------------------------------------------------------------------------------- |
| WooCommerce categories | `product_cat` | Standard catalog hierarchy                                                      |
| Specialties            | `specialty`   | Medical specialty browsing (archive + shop filters)                             |
| Procedures             | `procedure`   | Procedure-based filtering                                                       |
| Condition              | `condition`   | Product condition (e.g. new/refurbished) — `inc/product-condition-taxonomy.php` |

Products also use WooCommerce attributes (with image swatches where configured) and Dokan vendor metadata.

---

## Customer Account & Wallet

The theme replaces the default WooCommerce account UI with a single-page app pattern:

- **Template shell:** `front-end/profile.html` loaded inside `woocommerce/myaccount/my-account.php`
- **Backend:** `inc/profile-account.php` — all profile AJAX actions require login + `consucorner_profile_nonce`
- **Wallet:** `inc/customer-wallet.php` — balance display, checkout credit toggle, order wallet charges
- **Refunds:** `inc/admin-wallet-refunds.php` — admin processing of wallet refunds

Typical account AJAX actions: get/save profile, avatar, orders, cancel order, wishlist, privacy, notifications, change password, wallet data, apply coupon.

---

## Vendor & Admin Tools

| Tool                  | File                                                   | Description                                                          |
| --------------------- | ------------------------------------------------------ | -------------------------------------------------------------------- |
| Dokan overrides       | `inc/dokan-overrides.php`                              | Keeps add-to-cart and pricing behavior consistent with catalog rules |
| Vendor ledger         | `inc/admin-vendor-ledger.php`                          | Admin screen: filter vendor transactions, export CSV                 |
| Vendor page           | `page-vendor.php` + `inc/page-content/vendor-data.php` | Become-a-vendor marketing content                                    |
| Report form installer | `inc/profile-report-form-installer.php`                | Sets up customer report forms when needed                            |

---

## Auto-Provisioned Content

On `after_setup_theme`, the theme may create pages and menus **once** (guarded by options like `consucorner_home_page_ready`):

| Page              | Slug                   | Template                        |
| ----------------- | ---------------------- | ------------------------------- |
| Home (front page) | `home`                 | `front-page.php`                |
| About             | `about`                | `page-about.php`                |
| Contact           | `contact`              | `page-contact.php`              |
| Privacy Policy    | `privacy-policy`       | `page-privacy-policy.php`       |
| Terms             | `terms-and-conditions` | `page-terms-and-conditions.php` |
| Vendor            | `vendor`               | `page-vendor.php`               |
| Blogs             | `blogs`                | `page-archive-posts.php`        |
| FAQ               | `faq`                  | `page-faq.php`                  |
| Shop Instruments  | `shop-instruments`     | `page-shop-instruments.php`     |
| Shop Specialty    | `shop-specialty`       | `page-shop-specialty.php`       |

**Help hub** child pages are seeded from `inc/help-pages.php` (shipping, returns, payments, etc.) as editable block content.

**Footer menus** (Explore, Services, Connect, Resources, Legal) are auto-created and assigned to theme menu locations when the seed version changes.

> Do not assume every page was created manually in production — check the database before duplicating slugs.

---

## AJAX & Frontend APIs

Public or logged-in AJAX actions (theme prefix `consucorner_` unless noted):

| Action                                 | Module              | Purpose                      |
| -------------------------------------- | ------------------- | ---------------------------- |
| `consucorner_live_search`              | search-experience   | Header typeahead results     |
| `consucorner_filter_category_products` | category-filters    | Shop filter refresh          |
| `consucorner_get_specialty_products`   | functions.php       | Homepage specialty grid      |
| `consucorner_get_recommended_products` | functions.php       | Homepage recommendations     |
| `consucorner_get_overall_bestsellers`  | functions.php       | Homepage bestsellers         |
| `consucorner_auth_*`                   | functions.php       | Login, signup, lost password |
| `consucorner_profile_*`                | profile-account     | Account dashboard CRUD       |
| `consucorner_apply_coupon`             | profile-account     | Coupon from account UI       |
| `cc_toggle_checkout_wallet_credit`     | customer-wallet     | Checkout wallet toggle       |
| `cc_charge_order_wallet`               | customer-wallet     | Post-order wallet charge     |
| `cc_vlg_filter` / `cc_vlg_export`      | admin-vendor-ledger | Ledger admin UI              |

Scripts receive nonces via `wp_localize_script` — always verify nonces when adding new endpoints.

---

## Folder Structure

```
consucorner/
├── assets/
│   ├── css/              # Page-specific styles (shop, product, header, tour, …)
│   ├── js/               # Filters, search, profile, tours, mega menu, trackers
│   ├── images/           # Theme images and placeholders
│   └── vendor/
│       └── driver.js/    # Driver.js (product tours)
├── front-end/
│   └── profile.html      # My Account SPA shell
├── inc/                  # PHP modules (see list below)
│   └── page-content/     # Default copy for static pages
├── languages/
├── template-parts/       # Reusable partials (e.g. shop promo)
├── woocommerce/          # WooCommerce template overrides
├── front-page.php        # Homepage template
├── page-*.php              # Static page templates
├── taxonomy-specialty.php
├── functions.php           # Bootstrap, enqueue, auto-pages, checkout fields
├── style.css               # Theme header + base styles
├── style-rtl.css
├── MAINTENANCE.md          # Private team workflow (deploy, Git, safety)
└── README.md               # This file
```

### `inc/` module reference

| File                                                                      | Responsibility                                      |
| ------------------------------------------------------------------------- | --------------------------------------------------- |
| `category-filters.php`                                                    | Shop filtering, price buckets, AJAX archive refresh |
| `search-experience.php`                                                   | Live search                                         |
| `product-tour.php`, `product-tour-state.php`, `product-tour-rest.php`     | Tours v2 enqueue, state, REST                       |
| `product-specialties-taxonomy.php`                                        | `specialty` taxonomy                                |
| `product-procedures-taxonomy.php`                                         | `procedure` taxonomy                                |
| `product-condition-taxonomy.php`                                          | `condition` taxonomy                                |
| `product-mega-menu.php` / `explore-mega-menu.php`                         | Header navigation                                   |
| `mobile-drawer-menu.php`                                                  | Mobile nav                                          |
| `profile-account.php`                                                     | Account dashboard AJAX + routing                    |
| `customer-wallet.php` / `admin-wallet-refunds.php`                        | Wallet system                                       |
| `dokan-overrides.php`                                                     | Dokan compatibility                                 |
| `admin-vendor-ledger.php`                                                 | Vendor ledger admin                                 |
| `meta-boxes.php`                                                          | Page/product admin fields                           |
| `help-pages.php`                                                          | Help hub seeding                                    |
| `customizer.php`, `shop-promo-customizer.php`, `mega-menu-customizer.php` | Theme Customizer                                    |
| `attribute-images.php`                                                    | Variation images                                    |
| `template-functions.php`, `template-tags.php`                             | Shared helpers                                      |

---

## Customization Map

| You want to change…           | Start here                                                                                                                      |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Homepage layout & hero copy   | `front-page.php` + Home page meta boxes (`inc/meta-boxes.php`)                                                                  |
| Guided tour copy or phases    | `inc/product-tour.php`, `assets/js/product-tour-phases.js`                                                                      |
| Tour highlight targets        | Add `data-cc-tour` in `woocommerce/archive-product.php` or `content-single-product.php`                                         |
| Shop filters & AJAX           | `inc/category-filters.php`, `assets/js/category-filter.js`, `woocommerce/archive-product.php`                                   |
| Live search                   | `inc/search-experience.php`, `assets/js/site-search.js`, `header.php`                                                           |
| Mega menus                    | `inc/product-mega-menu.php`, `inc/explore-mega-menu.php`, Customizer                                                            |
| My Account dashboard          | `front-end/profile.html`, `inc/profile-account.php`, `assets/js/profile.js`                                                     |
| Wallet / checkout credit      | `inc/customer-wallet.php`, `woocommerce/checkout/form-checkout.php`                                                             |
| Cart / checkout / thank you   | `woocommerce/cart/`, `woocommerce/checkout/`                                                                                    |
| Single product layout         | `woocommerce/content-single-product.php`, `assets/css/single-product.css`, `assets/js/single-product.js` (sticky ATC on mobile) |
| Checkout field labels (Egypt) | `functions.php` (WooCommerce checkout filters)                                                                                  |
| Static page default text      | `inc/page-content/*.php` + matching `page-*.php`                                                                                |
| Footer menus                  | `functions.php` (`consucorner_setup_footer_menus`) or Appearance → Menus                                                        |
| Mobile / responsive styles    | `assets/css/responsive.css`                                                                                                     |
| Global theme styles           | `style.css`, `assets/css/sections.css`, `assets/css/header.css`                                                                 |
| Dokan behavior                | `inc/dokan-overrides.php`                                                                                                       |
| Vendor ledger admin           | `inc/admin-vendor-ledger.php`                                                                                                   |

---

## Related Projects

| Project                  | Path                                       | Role                                                               |
| ------------------------ | ------------------------------------------ | ------------------------------------------------------------------ |
| **ConsuCorner Security** | `wp-content/plugins/consucorner-security/` | Dashboard, logs, IP tools, planned runtime firewall/bot protection |
| **MAINTENANCE.md**       | Theme root                                 | Private deploy workflow, branch rules, Cloudways notes             |

For plugin setup, server configuration, and deployment credentials, use [`MAINTENANCE.md`](./MAINTENANCE.md) — never store secrets in this README.

---

## Local Development

Use a local WordPress stack (e.g. Local by Flywheel). Required plugins for full behavior typically include **WooCommerce**, **Dokan Pro**, and **GeIdeA** gateway (test mode).

Workflow:

1. Clone the private theme repository into `wp-content/themes/consucorner/`
2. Activate the theme and WooCommerce; run through setup wizard if needed
3. Let auto-provisioned pages seed on first load (or import staging DB)
4. Test shop filters, tours, checkout (GeIdeA sandbox), and vendor flows on a **local copy first**

Do not commit passwords, API keys, `.env`, database dumps, or customer exports.

---

## Maintenance & Deployment

- Detailed steps: [`MAINTENANCE.md`](./MAINTENANCE.md)
- Only approved team members deploy to Cloudways production
- Path: **Laptop → GitHub → Cloudways** (never edit live files directly)
- Keep this README descriptive but **credential-free**

---

## Coding Rules

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Prefix functions/hooks with `consucorner_`; shared filter helpers may use `cc_`
- Text domain: `consucorner` for all user-facing strings
- Escape output: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`
- Sanitize all `$_POST` / `$_GET` input; use nonces on every AJAX handler
- Enqueue assets **only on pages that need them** (`consucorner_scripts` in `functions.php`)
- Test locally before review; keep PRs focused
- Never commit secrets, backups, or production customer data

---

## Team Access & Ownership

Only Ahmed Magdy and approved ConsuCorner developers may access, edit, review, or deploy this theme.

Developers must not copy this theme to another client, reuse the design, share the repository, publish the code, or grant access to third parties without written approval from Ahmed Magdy.

This theme is a private ConsuCorner project. No person or company may copy, resell, redistribute, publish, or reuse it without written approval from Ahmed Magdy or an approved project lead.
