=== ConsuCorner ===

Contributors: automattic
Tags: custom-background, custom-logo, custom-menu, featured-images, threaded-comments, translation-ready

Requires at least: 4.5
Tested up to: 5.4
Requires PHP: 5.6
Stable tag: 2.9.9
License: GNU General Public License v2 or later
License URI: LICENSE

A starter theme called ConsuCorner.

== Description ==

Description

== Installation ==

1. In your admin panel, go to Appearance > Themes and click the Add New button.
2. Click Upload Theme and Choose File, then select the theme's .zip file. Click Install Now.
3. Click Activate to use your new theme right away.

== Frequently Asked Questions ==

= Does this theme support any plugins? =

ConsuCorner includes support for WooCommerce and for Infinite Scroll in Jetpack.

== Changelog ==

= 2.9.9 =
* Optional per-pack banner collage image overrides (3 circles) on Campaign bundles admin; empty slots auto-fill from pool/offer products.

= 2.9.8 =
* Each bundle pack has its own URL: /bundles/{campaign-slug}/{pack-key}/ (banner CTAs and admin copy links updated).

= 2.9.7 =
* Multi-bundle campaigns: repeater meta `_cc_campaign_bundles` with legacy migration; admin Campaign bundles UI.
* /offers/{slug}/ banner slider when 2+ packs; /bundles/{slug}/ shows all active pack builder cards.
* Banner collage fills 3 circles from pool then offer products; cart AJAX uses pack_key.

= 2.9.6 =
* Products → Link Builder admin screen (after Campaigns) for shop/specialty filter campaign URLs with product count preview.
* Specialty scope uses archive path only; ?specialty= stripped on specialty pages.
* Storefront filters are AJAX-only again (no URL bar updates while filtering); admin-built URLs still work on first load.

= 2.9.5 =
* Admin Offers Link Builder under Pages → Offers Link Builder (legacy vendor + tag URLs and shop filter campaign URLs with product count preview).
* Shop shared filter URLs omit specialty query params; specialty remains available as an on-page filter and via specialty archive permalinks.
* /offers/ auto-shows all active campaign products; offers banner slider; bundle pool product links; single-product bundle promo CTA.

= 2.8.1 =
* Bundle-driven campaigns: clean slug URLs /offers/{slug}/ and /bundles/{slug}/; product pool drives offers grid and automatic collage banner.
* Banner CTA always opens the exact campaign bundle; admin no longer needs offer vendor/tag or manual banner image.
* Legacy /offers/?vendor=&tag= links redirect to the campaign slug when a match exists.

= 2.8.0 =
* Unified Campaigns: the existing bundle post type is now managed as Products → Campaigns, combining offer targeting, banner content, hero overrides, scheduling, bundle setup, and shareable links on one screen.
* Offers campaign banner: a single composed banner replaces the rotating product slider and links directly to the matching vendor + bundle-tag URL.
* Campaign resolver: newest valid vendor + product-tag campaign wins; inactive, out-of-schedule, incomplete, and bundle-less campaigns render no banner; lookups are transient-cached.
* Operations documentation: added docs/campaign-operations-guide.html and linked it from Campaign admin screens.

= 2.7.3 =
* Order & return operations workflow: fulfillment state per vendor (ordered → delivered) and return workflow (requested → resolved) on order meta.
* WooCommerce → Returns: order lookup, fulfillment controls, manual vendor-split RMA creation, workflow actions before Wallet/Direct resolution.
* Customer returns gated on operations fulfillment (shipped/out for delivery/delivered); profile orders show fulfillment and return status chips.
* Dokan vendor dashboard: read-only ConsuCorner status; vendor RMA status controls hidden (operations authoritative).
* Email notifications to customer and vendor on operations-created returns and workflow updates.

= 2.7.0 =
* Return Orders Monitoring: WooCommerce → Returns report for Dokan RMA requests (filter, CSV export, HPOS-safe).
* Wallet and Direct (manual) resolution routes — wallet credits custom wallet + shared Dokan vendor adjust; direct records WC refund without gateway reversal.
* Shared returns-refund-service.php; wallet meta box guarded against duplicate refunds when RMA/WC refund exists.
* RETURN-ORDERS-SETUP.md admin checklist for Dokan RMA configuration.

= 2.6.0 =
* Mix-and-Match Bundles: admin sets product pool + size N + flat price P + vendor; customers pick any N items on /bundles/.
* Bundles page supports ?vendor=&tag= campaign filtering with builder cards and AJAX add-to-cart.
* Cart prices each complete group so the instance totals exactly P; framed group UI in cart and mini-cart with locked qty and remove-all.
* Checkout lists individual pool products and persists bundle id/instance/name on each order line item.

= 2.5.0 =
* Offer Deal and new Bulk pricing (quantity tiers) fields moved to a "Pricing & Deals" Product Data tab.
* Bulk pricing is site-wide: tier grid on single product, compact badge on shop/offers cards, tier price applied in the cart.
* Single product page shows the Offer Deal highlight and an interactive Bulk pricing grid with live unit-price updates.
* When both an Offer Deal and Bulk tiers apply to the same cart quantity, the cheaper per-unit price is charged.

= 2.4.0 =
* Per-product offer bundle deals: enable qty + total price on simple products (e.g. 10 for 800 EGP).
* Offers page shows bundle pricing block and Add N for X EGP button; shop cards unchanged.
* Cart applies deal unit price when line quantity exactly matches bundle qty (site-wide).

= 2.3.4 =
* Offers page: vendor query param uses WordPress user_login (username) in generated links instead of store nicename/slug.
* Admin link builder vendor dropdown shows store name with @username; legacy nicename URLs still resolve.

= 2.3.3 =
* Dynamic Offers page at /offers/?vendor=username&tag=product-tag — Dokan vendor + product_tag AND filter.
* Product grid reuses shop archive cards (fp-products-main / fp-products-grid); mobile list layout matches shop.
* Offers-only stock qty bar when show_qty_bar is enabled and stock is managed.
* Marketing hero defaults (Certified Excellence, Flash Deals copy) with admin meta box + campaign link builder.
* category-archive.css on offers page for product-card-title-link styling parity with shop.
* Auto-creates /offers/ page assigned to page-offers.php template.

= 2.2.5 =
* Shop filters (desktop): filter dropdown panels mount into a host element and re-anchor on scroll/resize so panels stay attached to their triggers.
* Single product (mobile): sticky add-to-cart bar redesigned — two-row layout, title up to 2 lines, larger price, Buy now + Add to cart on Geidea-safe flow.
* Homepage: vector banner image is clickable when a Banner link URL is set in the homepage meta box.
* Footer: Services column removed; dark gradient background (--gradient-dark); Explore + Legal layout; mobile bottom bar centered; nav columns side-by-side on small screens.
* Geidea payments: WhatsApp help modal on payment failure and HPP cancel (cart geidea-session flow + alert interceptor); no Geidea plugin edits.
* Checkout: validation errors show inline under fields only (top error banner hidden); WhatsApp popup limited to Geidea payment errors/cancel, not field validation.

= 2.2.4 =
* Shop category filters now use menu_order to match the mega menu display order.
* Checkout: inline validation messages appear under each required field; WooCommerce server errors map to the matching input.
* Checkout: card/Visa payment errors reopen the WhatsApp help modal (COD and other methods keep inline notices only).
* Cart product tour: added Share cart and coupon steps (5 steps max); full scroll and interaction lock while the tour is active.

= 2.2.3 =
* Checkout error modal disabled by default; WooCommerce inline error notices show as usual (re-enable via CONSUCORNER_CHECKOUT_ERROR_MODAL_ENABLED or filter).
* Share cart button on cart and checkout styled with primary brand color.

= 2.2.2 =
* Homepage hero slider: CTA buttons use meta Button link; clickability, z-index, and aria fixes.
* Shop promo: mobile subtitle 3-line clamp; static term banner aspect-ratio 1208/570.
* Footer: removed Connect and Resources columns; Customizer social URLs (Instagram, Facebook, LinkedIn); dark layout redesign.

= 2.2.1 =
* Checkout error modal with WhatsApp support (01555458555) for validation, AJAX, and page-load errors.
* Shareable cart restore links on cart and checkout (7-day token, copy + native share).
* Modal UI polish: fixed input overflow and consistent button styling on cart-share and checkout-error dialogs.

= 2.1.49 =
* Checkout: error modal with WhatsApp support (01555458555) on validation failures, AJAX checkout errors, and page-load notices.
* Cart & checkout: shareable cart restore link — recipients open the link to load the same products into their cart.

= 2.1.48 =
* Hero banner tag icons: editable via admin icon picker; any Font Awesome icon renders white in `.banner-tag` (no green bubble). Button cart icon stays fixed.

= 2.1.47 =
* Hero banner: restored original white stroke SVG tag/button icons on the frontend (fixes green bubble from drawer `.cc-icon` styles). Admin icon picker unchanged.

= 2.1.46 =
* Homepage hero banner: tag and button icons now use a visual Font Awesome icon picker (searchable popup) instead of typing class names manually.

= 2.1.45 =
* Hero banner slider: tag and button icons now use Font Awesome (site library via Dokan), WoodMart-style, with editable FA classes in homepage meta boxes.

= 2.1.44 =
* Shop promo: static term banner on mobile uses aspect-ratio 1208 / 500 for more vertical space.

= 2.1.43 =
* Shop promo: taller mobile banner (2:1 aspect ratio, 176px min-height) so title, CTA, and country flags are no longer clipped.

= 2.1.42 =
* Shop promo: mobile +N opens a full-screen bottom-sheet popup with backdrop, title, close button, and scrollable country list.
* Shop promo: origin modal portals to document body on mobile to avoid banner clipping.

= 2.1.41 =
* Shop promo: load ab-shop-promo.js on specialty and category archives so +N origin popover works on static term banners.
* Shop promo: origin popover opens above the flag cluster and escapes viewport clipping while open.

= 2.1.40 =
* Shop promo: removed "Sourced from … & N more" caption under overflow origin clusters.
* Shop promo: +N opens an in-banner popover listing all countries of origin with flags and filter links; closes on outside click or Escape.

= 2.1.39 =
* Shop promo: specialty and product_cat archive banners show adaptive Country of Origin badges (1 flag, 2–4 cluster, 5+ with +N) auto-detected from catalog products; flag links filter the current archive.
* Shop promo: removed manual flag/badge fields from specialty and category term promo meta boxes — countries come only from product Country of Origin assignments.
* Shop promo: all promo banners use 1208 × 390 landscape aspect ratio; registered cc_shop_promo_banner image size for term and Customizer backgrounds.
* Shop promo: +N control scrolls to or opens the Country of Origin filter panel.

= 2.1.38 =
* Shop filters: Get Quote products (price at or above CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD) are excluded from price slider max, histogram buckets, and filtered results; still visible in the grid when no price filter is active.
* Shop filters: fixed price histogram canvas overflowing its panel (correct sizing, overflow clip, draw on desktop + mobile canvases).
* Profile orders: in-popup single-order detail view with line items, totals, and Bosta tracking; deep links via ?cc_order= and ?cc_key=; guest order view on login page.
* Track order: custom display order number lookup (cc_resolve_order_id_from_reference) and Bosta tracking row on results.
* Shop promo: main /shop/ keeps Customizer multi-slide slider; specialty and product_cat archives use per-term static banners from term edit screens (inc/term-promo-banner.php).

= 2.1.37 =
* Blog: [cc_shop_now] CTA banner uses site logo, white-on-blue button (immune to blog link styles), and responsive layout without overlap.

= 2.1.36 =
* Blog: added [cc_shop_now] shortcode — Shop Now pill + ConsuCorner brand banner linking to the WooCommerce shop page.

= 2.1.35 =
* Single product: fixed fatal error when Often Ordered With falls back to wc_get_related_products() string IDs in cc_render_product_card().
* Mini-cart: removed temporary debug instrumentation; qty race and stock-cap fixes from 2.1.34 retained.

= 2.1.34 =
* Mini-cart: fixed quantity desync on rapid +/- clicks (latest-request-wins guard, abort stale AJAX).
* Cart: server-side stock cap via cc_get_product_max_qty() prevents ordering more than available inventory.

= 2.1.31 =
* Single product: Often Ordered With cards match homepage product card layout; scoped shop archive mobile list styles so they no longer break the OOW carousel.

= 2.1.30 =
* Profile: desktop shows full name and email without truncation; mobile hero title/breadcrumbs positioning fixed.

= 2.1.29 =
* Profile mobile: fixed CSS load order (shop-page → responsive → profile) so profile hero and card styles win over generic mobile page-head rules.

= 2.1.28 =
* Profile mobile: applied reference design — teal gradient hero, glass card, 2-column menu grid, bottom-sheet modals.

= 2.1.27 =
* Single product: country and brand pills link to filtered shop URLs (/shop/?country_of_origin=…, /shop/?product_brand=…).

= 2.1.26 =
* Blog: reduced FAQ section typography, padding, and max-width for better readability.

= 2.1.25 =
* Blog: Arabic posts use RTL on title, body, and FAQ sections; improved Arabic detection (excerpt + FAQ text).

= 2.1.24 =
* Specialty order: mobile drawer and homepage categories slider now respect consucorner_sort_terms_by_order(); specialty order meta changes clear drawer cache (transient v7).

= 2.1.23 =
* Get A Quote modal: explicitly reveals Forminator forms when the quote modal opens on shop/archive pages so the form fields render instead of staying display:none.

= 2.1.22 =
* Mini-cart: fixed remove/quantity AJAX race by isolating remove clicks, locking the row, aborting pending quantity requests, and refreshing WooCommerce fragments only after successful removal.

= 2.1.21 =
* Get A Quote cart guard: quote CTAs no longer use the generic cart button class, custom mini-cart handlers ignore quote triggers, and WooCommerce blocks/removes quote-only products from cart sessions.

= 2.1.20 =
* Get A Quote archive cards: quote CTAs no longer match WooCommerce AJAX add-to-cart selectors. They now open the shared Request a Quote modal through delegated JavaScript, including after AJAX filter updates.

= 2.1.19 =
* Specialty order: explicitly loads the WooCommerce term-ordering script on Products -> Specialties and adds a visible drag handle so operations can drag rows reliably.

= 2.1.18 =
* Specialty order: specialties now reorder by drag-and-drop (like product categories) on Products -> Specialties. Sorting only updates the term order; no specialties are added or removed. The order drives the shop mega-menu specialty section and every shop archive specialty filter.

= 2.1.17 =
* Homepage testimonials: removed all testimonial-section override rules; homepage now displays saved meta-box content directly.

= 2.1.16 =
* Single product: Often Ordered With cards now use the shared shop product card template and styling.

= 2.1.15 =
* Homepage testimonials: meta-box edits now display on the homepage again; removed duplicate-name override logic that could ignore saved reviewer content.

= 2.1.14 =
* Explore menu: the Explore "links" column is now editable from Appearance > Menus via the "Explore - Important" and "Explore - Help" menu locations (also matched by menu name "Important desktop menu" / "Help Desktop menu").
* Specialty order: added an editable "Display order" field to each Specialty term (with a sortable Order column). The order now drives both the shop mega-menu specialty section and the shop archive specialty filter.
* Shop filters: generated filter URLs use standard %2C-encoded commas again (for example, ?specialty=orthopedic%2Cgynecology%2Cent&min_price=105&max_price=56000).

= 2.1.13 =
* Shop filters: query-string filter params (for example, /shop/?specialty=a,b,c) are no longer routed as single-term taxonomy archives, so shared campaign URLs apply the full multi-select filter on the Shop page. Pretty-permalink archives (such as /specialty/ent/) are unaffected.

= 2.1.12 =
* Shop filters: Specialty panel keeps all rendered specialty options visible while preserving selected URL specialties after availability updates.

= 2.1.11 =
* Shop filters: generated browser URLs now preserve visible comma-separated taxonomy slugs (for example, specialty=a,b,c) for campaign sharing.

= 2.1.10 =
* Shop filters: shared campaign URLs now keep comma-separated taxonomy slugs while the frontend uses server-resolved term IDs, preserving multi-specialty filters on first load.
* Shop filters: filtered URLs no longer trigger an immediate duplicate AJAX fetch that can collapse selected terms after server render.

= 2.1.9 =
* Single product: Often Ordered With now shows products from the same specialty taxonomy (fallback to WooCommerce related products).
* Single product: vendor pill is hidden on Get A Quote products.

= 2.1.8 =
* Shop promo slider: each slide now opens its own Customizer URL after next/prev/autoplay loop (`data-promo-url`, `syncSlides()`, defensive overlay pointer rules).
* Shop filters: price panel count (`#fpPriceCount`) updates live with the price range, scoped to the current archive and active filters.

= 2.1.7 =
* Orders: custom WooCommerce processing-order email (Order Confirmed design) with dynamic order data, inline email-safe styles, and plain-text fallback (`inc/order-email.php`).
* Orders: custom display order numbers (minutes + order ID + seconds) via `inc/order-number.php`.
* Product: removed Report Abuse link from single product pages.
* Email: removed default WooCommerce “Thanks again…” additional content; fixed raw CSS leaking into the message; centered header; full-width info boxes with column/row gaps; single-color feature icons.

= 2.1.6 =
* Testimonials: replaced homepage and checkout reviews with three Arabic doctor testimonials (DR. Khalid Elbeltagui, DR. Shady Abd Elsalam, DR. Salah Helmy).
* Home/mobile: homepage product card Add to Cart buttons are centered on mobile across Browse, Bestsellers, and Recommended sections.

= 2.1.5 =
* Bestsellers: sale prices now render as a cleaner discount stack on desktop and mobile.
* Testimonials: homepage display names now guard against repeated saved defaults without changing product review authors.
* Shop filters: price inputs no longer reset while typing, and the price total uses the server-provided non-price-filtered count.

= 2.1.4 =
* Home: bestsellers cards now have clearer brand/country spacing and mobile shows one card with a peek of the next.
* Mobile drawer: logo links to the homepage.
* Testimonials: default homepage reviewer names are unique.
* Shop filters: min price input supports free numeric typing; price total count reflects the non-price filtered product set.
* Tour: highlighted site controls are no longer clickable during tours; only tour controls remain interactive.
* Mobile filters: label alignment starts at the left and results row is hidden.

= 2.1.3 =
* Cart page: quantity +/- uses AJAX (`cc_update_cart_qty`) like mini-cart; reloads after success (no form POST / wc-cart conflict).

= 2.1.2 =
* Mobile drawer: removed "Sourced from top manufacturers" subtitle from Shop by Category.
* Mobile drawer: moved Shop by Category and Shop by Specialty slider arrows beside section titles (bestsellers-header style pill nav).
* Mobile drawer cache: shop drawer transient bumped to v6.

= 2.1.1 =
* Mobile drawer: added previous/next arrow controls to Shop by Category and Shop by Specialty sliders.
* Mobile drawer: removed shadows from Shop by Specialty gradient cards.
* Mobile drawer cache: shop drawer transient bumped to v5.

= 2.1.0 =
* Checkout: removed billing_city field; Governorate is now full-width under Shipping Address
* Mobile drawer Explore: Important Links cards restyled to match Figma (taller cards, bottom-aligned text, Partners card active/green with decorative rings)
* Mobile drawer cache: explore drawer transient bumped to v4

= 2.0.9 =
* Checkout: Cash on Delivery is now the default selected payment method when available, while preserving a submitted payment method after validation errors.
* Cart: mobile layout now keeps cart-list-card before cart-summary-card.

= 2.0.8 =
* Product categories: added Category Icon upload field on product_cat terms (helpers: cc_get_product_cat_icon_url, cc_get_product_cat_icon_info)
* Media Library: SVG/SVGZ upload support for admins and editors (category icons and other theme assets)
* Mobile drawer Shop tab: Shop by Category and Shop by Specialty now use horizontal snap sliders (all main categories/specialties, 4 cards per slide)
* Mobile drawer: fixed oversized category cards in slider (compact 105px height, aligned slide grid)
* Mobile drawer cache: shop drawer transient bumped to v4

= 2.0.7 =
* Mobile drawer: redesigned to card-based Figma layout (light background, 2x2 grids, Manrope font, teal/blue/purple specialty gradients, help list, no horizontal slider)
* Mobile drawer Shop tab: top-4 product_cat cards with category icon support (cc_get_product_cat_icon_url), top-4 specialty term cards
* Mobile drawer Explore tab: 4 Important Links as static 2x2 grid; Help & Guidelines as row list
* Mobile drawer cache: bumped to v3 transients; specialty term and category icon meta changes now bust the cache

= 2.0.6 =
* Get A Quote: centralized Forminator form ID via CONSUCCORNER_GET_QUOTE_FORMINATOR_ID and cc_get_quote_forminator_form_id() in functions.php (modal + submission capture use the same source)
* Get A Quote: centralized quote-only price condition via CONSUCCORNER_GET_QUOTE_PRICE_THRESHOLD and cc_is_quote_product() in functions.php (single product, product cards, and Often Ordered With use the same source)
* Privacy Policy: frontend policy body now uses the main WordPress editor content, with legacy section meta used only as a fallback when the editor is empty

= 2.0.5 =
* Get A Quote: show quote-only flow for products priced at 50,000 EGP or higher using Forminator form 5422 and the Quote Order Thank You page
* Get A Quote: hide quote-product pricing on single product, product cards, and Often Ordered With cards; card CTAs now route to the product quote flow
* Product taxonomy: added custom Country of Origin taxonomy with term image support and one-time migration from legacy WooCommerce country attributes
* Product display: single product pills, archive filters, product cards, and shop-specialty country slider now prefer the new Country of Origin taxonomy

= 2.0.4 =
* Checkout: submit real WooCommerce shipping method instead of empty hidden field (fixes "No shipping method selected")
* Checkout: Governorate as native dropdown with Egypt fallback list; address fields trigger shipping recalculation
* Mini-cart: sync add/remove/qty with WooCommerce session via AJAX (`cc_get_cart_json`, `cc_update_cart_qty`)
* Cart badge: authoritative WC cart count from `consuSiteData`; sale price uses current `<ins>` price after add-to-cart
* Mini-cart delete on cart page reloads `/cart/` so server cart matches drawer

= 2.0.3 =
* Free shipping cart/mini-cart UI synced with WooCommerce shipping settings
* Shop filter URL state + SSR; sub-category always visible; guided tour updates

= 1.0 - May 12 2015 =
* Initial release

== Credits ==

* Based on Underscores https://underscores.me/, (C) 2012-2020 Automattic, Inc., [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
* normalize.css https://necolas.github.io/normalize.css/, (C) 2012-2018 Nicolas Gallagher and Jonathan Neal, [MIT](https://opensource.org/licenses/MIT)
