# Graph Report - consucorner  (2026-07-21)

## Corpus Check
- 176 files · ~638,156 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 2006 nodes · 3469 edges · 164 communities (153 shown, 11 thin omitted)
- Extraction: 93% EXTRACTED · 7% INFERRED · 0% AMBIGUOUS · INFERRED: 243 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `87cd8f62`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 21|Community 21]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 24|Community 24]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 39|Community 39]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]
- [[_COMMUNITY_Community 44|Community 44]]
- [[_COMMUNITY_Community 45|Community 45]]
- [[_COMMUNITY_Community 46|Community 46]]
- [[_COMMUNITY_Community 47|Community 47]]
- [[_COMMUNITY_Community 48|Community 48]]
- [[_COMMUNITY_Community 49|Community 49]]
- [[_COMMUNITY_Community 50|Community 50]]
- [[_COMMUNITY_Community 51|Community 51]]
- [[_COMMUNITY_Community 52|Community 52]]
- [[_COMMUNITY_Community 53|Community 53]]
- [[_COMMUNITY_Community 54|Community 54]]
- [[_COMMUNITY_Community 55|Community 55]]
- [[_COMMUNITY_Community 56|Community 56]]
- [[_COMMUNITY_Community 57|Community 57]]
- [[_COMMUNITY_Community 58|Community 58]]
- [[_COMMUNITY_Community 59|Community 59]]
- [[_COMMUNITY_Community 60|Community 60]]
- [[_COMMUNITY_Community 61|Community 61]]
- [[_COMMUNITY_Community 62|Community 62]]
- [[_COMMUNITY_Community 63|Community 63]]
- [[_COMMUNITY_Community 64|Community 64]]
- [[_COMMUNITY_Community 65|Community 65]]
- [[_COMMUNITY_Community 66|Community 66]]
- [[_COMMUNITY_Community 67|Community 67]]
- [[_COMMUNITY_Community 68|Community 68]]
- [[_COMMUNITY_Community 69|Community 69]]
- [[_COMMUNITY_Community 70|Community 70]]
- [[_COMMUNITY_Community 74|Community 74]]
- [[_COMMUNITY_Community 75|Community 75]]
- [[_COMMUNITY_Community 76|Community 76]]
- [[_COMMUNITY_Community 77|Community 77]]
- [[_COMMUNITY_Community 78|Community 78]]
- [[_COMMUNITY_Community 79|Community 79]]
- [[_COMMUNITY_Community 80|Community 80]]
- [[_COMMUNITY_Community 81|Community 81]]
- [[_COMMUNITY_Community 82|Community 82]]
- [[_COMMUNITY_Community 84|Community 84]]
- [[_COMMUNITY_Community 85|Community 85]]
- [[_COMMUNITY_Community 87|Community 87]]
- [[_COMMUNITY_Community 90|Community 90]]
- [[_COMMUNITY_Community 156|Community 156]]
- [[_COMMUNITY_Community 157|Community 157]]
- [[_COMMUNITY_Community 158|Community 158]]
- [[_COMMUNITY_Community 160|Community 160]]
- [[_COMMUNITY_Community 161|Community 161]]
- [[_COMMUNITY_Community 166|Community 166]]

## God Nodes (most connected - your core abstractions)
1. `Consucorner_Order_Return_Workflow` - 90 edges
2. `Consucorner_Vendor_Ledger` - 55 edges
3. `Consucorner_Returns_Report` - 51 edges
4. `CC_Returns_Refund_Service` - 43 edges
5. `Consucorner_Order_Cancel_Requests` - 29 edges
6. `$()` - 28 edges
7. `init()` - 26 edges
8. `Consucorner_Returns_Rma_Config` - 21 edges
9. `ConsuCorner WordPress Theme` - 20 edges
10. `consucorner_scripts()` - 18 edges

## Surprising Connections (you probably didn't know these)
- `consucorner_scripts()` --calls--> `consucorner_get_checkout_phone_strings()`  [INFERRED]
  functions.php → inc/checkout-phone.php
- `cc_mrsa_seed_set_fulfillment()` --calls--> `Consucorner_Order_Return_Workflow`  [INFERRED]
  tests/seed-mrsa-medical-orders.php → inc/order-return-workflow.php
- `cc_mrsa_seed_vendor_id()` --calls--> `Consucorner_Order_Return_Workflow`  [INFERRED]
  tests/seed-mrsa-medical-orders.php → inc/order-return-workflow.php
- `cc_seed_set_fulfillment()` --calls--> `Consucorner_Order_Return_Workflow`  [INFERRED]
  tests/seed-scenario-orders.php → inc/order-return-workflow.php
- `cc_seed_vendor_id()` --calls--> `Consucorner_Order_Return_Workflow`  [INFERRED]
  tests/seed-scenario-orders.php → inc/order-return-workflow.php

## Import Cycles
- None detected.

## Communities (164 total, 11 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.06
Nodes (75): addFilter(), anchorDesktopFilterPanel(), appendActiveChip(), applyTermAvailability(), bindFilterTermGroups(), buildUrlParams(), canOpenSubcategoryPanel(), clampPriceFilterMax() (+67 more)

### Community 1 - "Community 1"
Cohesion: 0.05
Nodes (84): cc_offer_campaign_admin_assets(), cc_offer_campaign_admin_column_content(), cc_offer_campaign_all_products_cache_key(), cc_offer_campaign_build_banner_images(), cc_offer_campaign_build_bundles_pack_url(), cc_offer_campaign_build_bundles_url(), cc_offer_campaign_build_offers_url(), cc_offer_campaign_cache_key() (+76 more)

### Community 2 - "Community 2"
Cohesion: 0.07
Nodes (54): consucorner_ajax_apply_coupon(), consucorner_avatar_resolve_user_id(), consucorner_filter_my_account_menu_items(), consucorner_get_current_account_endpoint(), consucorner_get_hidden_account_menu_endpoints(), consucorner_get_profile_extension_menu_markup(), consucorner_get_profile_template_partial(), consucorner_get_unlisted_account_endpoints() (+46 more)

### Community 3 - "Community 3"
Cohesion: 0.05
Nodes (39): 10. Implementation map, 11. Build phases, 12. Test scenarios (high level), 13. Non-goals (explicit), 14. Glossary, 15. Revision history, 1. Executive summary, 2. Locked business decisions (+31 more)

### Community 4 - "Community 4"
Cohesion: 0.08
Nodes (48): cc_build_interest_tax_query(), cc_filter_product_ids_by_tax_query(), cc_get_home_bestsellers_order_count_rankings(), cc_get_home_bestsellers_product_ids(), cc_get_home_manual_bestseller_product_ids(), cc_get_most_ordered_home_bestseller_product_ids(), cc_home_bestsellers_countable_order_statuses(), cc_render_home_bestsellers_product_picker() (+40 more)

### Community 7 - "Community 7"
Cohesion: 0.07
Nodes (37): CC_Admin_Wallet_Refunds, cc_add_to_custom_wallet(), cc_adjust_custom_wallet_balance(), cc_ajax_order_wallet_charge(), cc_ajax_toggle_checkout_wallet_credit(), cc_apply_checkout_wallet_credit_fee(), cc_checkout_wallet_is_enabled(), cc_current_user_can_charge_wallet_orders() (+29 more)

### Community 8 - "Community 8"
Cohesion: 0.07
Nodes (38): addFeature(), apiRequest(), applyCoupon(), applyServerProfile(), checkWishlistEmpty(), escapeHtml(), fillAccountForm(), fillPreferenceForms() (+30 more)

### Community 9 - "Community 9"
Cohesion: 0.04
Nodes (48): 10. خارطة الطريق (Roadmap), 11. تدفق العمل الكامل (Lifecycle), 12.1 قرارات محسومة بالفعل (جزء من الخطة — للتأكيد فقط), 12.2 قرارات مطلوبة من الإدارة قبل المرحلة 2, 12. قرارات محسومة وقرارات مطلوبة, 13. المخاطر والتخفيف, 14. ملخص للعرض على المدير (Slide-ready), 15. مسرد المصطلحات (+40 more)

### Community 10 - "Community 10"
Cohesion: 0.09
Nodes (45): addVariableToCartFromForm(), applyOfferDeal(), applyVariationState(), bindStickyActions(), bindStickyQty(), findMatch(), getCurrentQty(), getMinQty() (+37 more)

### Community 11 - "Community 11"
Cohesion: 0.07
Nodes (19): cc_get_quote_forminator_form_id(), cc_quote_form_capture_data(), cc_quote_form_flatten_value(), consucorner_account_body_class(), consucorner_ajax_auth_login(), consucorner_ajax_auth_lost_password(), consucorner_ajax_auth_signup(), consucorner_auth_json_error() (+11 more)

### Community 13 - "Community 13"
Cohesion: 0.07
Nodes (63): showNotice(), getFilters(), loadOpsOrder(), loadRows(), showNotice(), activateTab(), appendFilterParams(), bindEnterToApply() (+55 more)

### Community 14 - "Community 14"
Cohesion: 0.06
Nodes (35): author, bugs, url, description, devDependencies, dir-archiver, node-sass, rtlcss (+27 more)

### Community 15 - "Community 15"
Cohesion: 0.12
Nodes (30): escapeHtml(), formatPrice(), initBrowseCategoriesCarousel(), loadSpecialtyProducts(), renderProducts(), desktopNext(), desktopPrev(), getCenteredScrollLeft() (+22 more)

### Community 16 - "Community 16"
Cohesion: 0.15
Nodes (31): bindWishlistEvent(), completeTour(), createDriverInstance(), defaultState(), getState(), globalDisable(), hasPageTourShownInSession(), initPageTour() (+23 more)

### Community 18 - "Community 18"
Cohesion: 0.14
Nodes (26): consucorner_shop_promo_all_theme_mods_empty_for_slide(), consucorner_shop_promo_customize_controls_scripts(), consucorner_shop_promo_customize_register(), consucorner_shop_promo_fallback_slide_is_meaningful(), consucorner_shop_promo_get_slides(), consucorner_shop_promo_max_slots(), consucorner_shop_promo_render_origins(), consucorner_shop_promo_render_slide() (+18 more)

### Community 19 - "Community 19"
Cohesion: 0.15
Nodes (22): consucorner_setup_footer_menus(), consucorner_customize_register(), consucorner_footer_defaults(), consucorner_get_footer_setting(), consucorner_get_sp_banner_background_url(), consucorner_single_product_defaults(), consucorner_sp_banner_bg_style_attr(), consucorner_footer_has_social_icons() (+14 more)

### Community 20 - "Community 20"
Cohesion: 0.16
Nodes (23): consucorner_enqueue_product_tour(), consucorner_get_product_tour_config(), consucorner_get_product_tour_strings(), consucorner_tours_asset_version(), consucorner_tours_rest_get_state(), consucorner_tours_rest_permission(), consucorner_tours_rest_post_state(), consucorner_product_tour_phase() (+15 more)

### Community 21 - "Community 21"
Cohesion: 0.08
Nodes (24): A) Fulfillment scenarios — vendor dashboard, B1 — Pending cancel (live steps), B2 — Rejected cancel (live steps), B3 — Item-level cancel (manual only; not in MRSA seed), B) Cancellation scenarios — vendor + customer + ops, C6 — Live return from customer (manual), C) Return scenarios — vendor dashboard (read-only), Cancel request rules (+16 more)

### Community 22 - "Community 22"
Cohesion: 0.17
Nodes (25): applyCardFormatters(), applyUiState(), attachEgyptPhoneField(), attachFormatter(), attachFormGuard(), checkRadio(), clearAllFieldErrors(), clearFieldError() (+17 more)

### Community 24 - "Community 24"
Cohesion: 0.13
Nodes (22): consucorner_explore_get_menu(), consucorner_explore_get_menu_by_location(), consucorner_explore_post_category_label(), consucorner_explore_reading_time(), consucorner_render_explore_featured_post(), consucorner_render_explore_link_group(), consucorner_render_explore_mega_menu(), consucorner_render_explore_recent_post() (+14 more)

### Community 25 - "Community 25"
Cohesion: 0.22
Nodes (24): abortPendingQty(), addItem(), changeQty(), clearCartCache(), close(), createDrawer(), esc(), getItemToken() (+16 more)

### Community 27 - "Community 27"
Cohesion: 0.23
Nodes (12): cc_build_mini_cart_items(), cc_format_cart_item_variation_labels(), cc_get_cart_json_ajax(), cc_get_product_max_qty(), cc_update_cart_qty_ajax(), consucorner_enforce_bulk_min_qty(), consucorner_enforce_bulk_min_qty_on_update(), consucorner_remove_quote_products_from_cart() (+4 more)

### Community 28 - "Community 28"
Cohesion: 0.20
Nodes (16): ABPromoSlider(), bindOriginMoreButtons(), closeAllOriginPopovers(), closeOriginPopover(), closeOriginPopoverFromEvent(), ensureOriginModalAnchor(), getOriginPopoverTrigger(), initPromo() (+8 more)

### Community 29 - "Community 29"
Cohesion: 0.09
Nodes (21): authors, description, homepage, keywords, license, name, require, require-dev (+13 more)

### Community 30 - "Community 30"
Cohesion: 0.21
Nodes (21): addToCartAjax(), bindAddToCartDelegation(), bindWishlistDelegation(), buildWooUrl(), extractItemData(), getRequestedQuantity(), getStoredCount(), getWishlist() (+13 more)

### Community 33 - "Community 33"
Cohesion: 0.22
Nodes (19): buildContextLines(), buildWhatsAppUrl(), clearGeideaFlowFlags(), closeModal(), getFlowTtl(), getModal(), getNoticesWrap(), handlePendingAlert() (+11 more)

### Community 34 - "Community 34"
Cohesion: 0.11
Nodes (19): AJAX & Frontend APIs, Auto-Provisioned Content, Changelog (recent), Coding Rules, ConsuCorner WordPress Theme, Customer Account & Wallet, Customization Map, Folder Structure (+11 more)

### Community 35 - "Community 35"
Cohesion: 0.14
Nodes (19): cc_front_meta(), cc_admin_icon_picker_enqueue(), cc_admin_icon_picker_get_edited_post_id(), cc_admin_icon_picker_render_modal(), cc_admin_icon_picker_screen_active(), cc_get_fa_icon_catalog(), cc_meta_fa_icon_picker(), consucorner_enqueue_theme_icons() (+11 more)

### Community 36 - "Community 36"
Cohesion: 0.18
Nodes (15): addToCartAjax(), bindAddToCartDelegation(), buildWooUrl(), extractItemData(), getRequestedQuantity(), getStoredCount(), initBadgeFromCache(), parseCountFromFragments() (+7 more)

### Community 37 - "Community 37"
Cohesion: 0.32
Nodes (15): buildAccountSteps(), buildCartSteps(), buildHomeSteps(), buildShopSteps(), buildWishlistSteps(), cfg(), enforceCap(), findWishlistAccountTarget() (+7 more)

### Community 38 - "Community 38"
Cohesion: 0.34
Nodes (16): adjustPanelForViewport(), bindViewportSync(), closeAll(), closePanel(), escapeHtml(), getPanel(), getSearchInput(), getSearchUrl() (+8 more)

### Community 39 - "Community 39"
Cohesion: 0.28
Nodes (15): boot(), getProfile(), ingestContext(), isString(), readCookie(), readHistory(), readListCookie(), record() (+7 more)

### Community 40 - "Community 40"
Cohesion: 0.20
Nodes (12): cc_get_quote_price_threshold(), cc_is_quote_product(), consucorner_block_quote_product_cart_add(), cc_ajax_create_cart_share(), cc_cart_share_build_snapshot(), cc_cart_share_client_ip(), cc_cart_share_create_url(), cc_cart_share_maybe_restore() (+4 more)

### Community 41 - "Community 41"
Cohesion: 0.22
Nodes (9): cc_drawer_svg_icon(), consucorner_clear_drawer_cache_on_order_meta(), consucorner_clear_mobile_drawer_cache(), consucorner_render_mobile_explore_drawer(), consucorner_render_mobile_shop_drawer(), cc_get_product_cat_icon_id(), cc_get_product_cat_icon_info(), cc_get_product_cat_icon_url() (+1 more)

### Community 42 - "Community 42"
Cohesion: 0.18
Nodes (7): consucorner_shop_specialty_brand_logo(), consucorner_shop_specialty_country_image(), consucorner_shop_specialty_country_taxonomy(), consucorner_shop_specialty_product_slide(), consucorner_shop_specialty_vendor_logo(), consucorner_shop_specialty_vendor_name(), WP_Term

### Community 43 - "Community 43"
Cohesion: 0.25
Nodes (14): consucorner_order_email_customer_name(), consucorner_order_email_estimated_delivery(), consucorner_order_email_footer_links(), consucorner_order_email_logo_url(), consucorner_order_email_order_date(), consucorner_order_email_price(), consucorner_order_email_product_meta_line(), consucorner_order_email_shipping_address_html() (+6 more)

### Community 44 - "Community 44"
Cohesion: 0.27
Nodes (11): cc_offers_build_url(), cc_offers_get_base_url(), cc_offers_get_filters_from_request(), cc_offers_get_vendor_slug_for_url(), cc_offers_get_vendor_username_for_url(), cc_offers_query_products(), cc_offers_resolve_tag_slug(), cc_offers_resolve_vendor_id() (+3 more)

### Community 45 - "Community 45"
Cohesion: 0.36
Nodes (12): consucorner_checkout_error_modal_enabled(), consucorner_checkout_error_modal_is_active_page(), consucorner_enqueue_checkout_error_modal_assets(), consucorner_enqueue_geidea_alert_bridge(), consucorner_get_checkout_error_modal_config(), consucorner_get_geidea_session_context(), consucorner_get_support_whatsapp_url(), consucorner_is_geidea_cart_flow() (+4 more)

### Community 46 - "Community 46"
Cohesion: 0.35
Nodes (12): buildModal(), getState(), initWelcome(), markWelcomeShown(), navigateCategories(), navigateSpecialty(), openModal(), saveState() (+4 more)

### Community 47 - "Community 47"
Cohesion: 0.15
Nodes (11): 10. Safety Rules (Read This Twice), 12. Help! Something Broke, 13. Glossary (Big Words, Simple Meaning), 1. The Picture (How Everything Talks to Each Other), 2. Tools You Need (One Time Only), 3. Get the Project on Your Computer, 5. Branches: Working Without Breaking Things, ConsuCorner Theme — Maintenance Guide (+3 more)

### Community 48 - "Community 48"
Cohesion: 0.15
Nodes (12): 1. Activate the RMA module, 2. Configure RMA settings, 3. Product eligibility (automatic on this theme), 3b. Where customers request a return, 4. Customer flow, 5. Manager flow (theme), 5b. Admin refund without a customer RMA request, 6. Legacy wallet meta box (+4 more)

### Community 49 - "Community 49"
Cohesion: 0.38
Nodes (11): closeModal(), copyLink(), createShareLink(), fallbackCopy(), getErrorEl(), getInput(), getModal(), handleShareClick() (+3 more)

### Community 50 - "Community 50"
Cohesion: 0.27
Nodes (7): consucorner_egypt_mobile_local_digits(), consucorner_egypt_mobile_operator_prefixes(), consucorner_get_checkout_phone_strings(), consucorner_is_valid_egypt_mobile_local_digits(), consucorner_normalize_egypt_mobile(), consucorner_sanitize_checkout_billing_phone(), consucorner_validate_checkout_egypt_phone()

### Community 51 - "Community 51"
Cohesion: 0.24
Nodes (5): ensureDefaultCategoryActive(), hideExploreMenu(), setActiveCategory(), setActiveCategoryWithIntent(), showMenu()

### Community 52 - "Community 52"
Cohesion: 0.20
Nodes (9): extends, ignoreFiles, rules, block-no-empty, font-family-no-duplicate-names, font-family-no-missing-generic-family-keyword, no-descending-specificity, no-duplicate-selectors (+1 more)

### Community 53 - "Community 53"
Cohesion: 0.42
Nodes (8): consucorner_cli_default_product_cat_id(), consucorner_cli_get_or_create_specialty(), consucorner_cli_match_specialty(), consucorner_cli_product_haystack(), consucorner_cli_skip_product_cat(), consucorner_cli_specialty_from_categories(), consucorner_cli_specialty_walk_to_root(), consucorner_cli_sync_one()

### Community 54 - "Community 54"
Cohesion: 0.50
Nodes (8): collectSelections(), getSelectedTotal(), handleStep(), init(), refreshCard(), resetCard(), showMessage(), submitBundle()

### Community 55 - "Community 55"
Cohesion: 0.42
Nodes (8): buildGrid(), closeModal(), filterGrid(), markSelected(), normalizeIcon(), openModal(), selectIcon(), updateFieldPreview()

### Community 56 - "Community 56"
Cohesion: 0.28
Nodes (3): consucorner_shop_instruments_product_slide(), consucorner_shop_instruments_vendor_logo(), consucorner_shop_instruments_vendor_name()

### Community 57 - "Community 57"
Cohesion: 0.22
Nodes (8): css, cssPath, __dirname, icons, names, outDir, outPath, skip

### Community 58 - "Community 58"
Cohesion: 0.43
Nodes (6): cc_attribute_swatch_admin_edit_field(), cc_attribute_swatch_option_key(), cc_get_attribute_swatch_display(), cc_get_attribute_term_image_url(), cc_render_variation_attribute_field(), cc_save_attribute_swatch_display_for_slug()

### Community 59 - "Community 59"
Cohesion: 0.46
Nodes (6): consucorner_assign_product_condition_test_data(), consucorner_get_product_condition_default_terms(), consucorner_maybe_flush_product_condition_rewrites(), consucorner_product_condition_one_time_setup(), consucorner_register_product_condition_taxonomy(), consucorner_seed_product_condition_terms()

### Community 60 - "Community 60"
Cohesion: 0.20
Nodes (12): cc_extract_interest_search_keyword(), cc_read_user_interest_profile(), cc_sanitize_interest_list(), consucorner_ajax_get_overall_bestsellers(), consucorner_ajax_get_recommended_products(), consucorner_ajax_get_specialty_products(), cc_render_product_card(), cc_offers_get_qty_bar_percent() (+4 more)

### Community 61 - "Community 61"
Cohesion: 0.46
Nodes (7): closeModal(), getModal(), getQuoteToken(), openModal(), prefillProductName(), revealForminatorForm(), setCookie()

### Community 62 - "Community 62"
Cohesion: 0.48
Nodes (5): cc_variable_product_form_end(), cc_variable_product_form_start(), cc_variable_product_get_args(), cc_variable_product_render_actions(), cc_variable_product_render_variations()

### Community 64 - "Community 64"
Cohesion: 0.43
Nodes (4): getSessionId(), pushTourEvent(), tryFireCartAfterTour(), tryFireOrderAfterTour()

### Community 65 - "Community 65"
Cohesion: 0.52
Nodes (6): appendInterestProfile(), escapeHtml(), formatPrice(), getLastSeenCategory(), loadRecommended(), renderRecommended()

### Community 66 - "Community 66"
Cohesion: 0.52
Nodes (6): getStep(), setTransition(), shiftNext(), shiftPrev(), startAuto(), stopAuto()

### Community 67 - "Community 67"
Cohesion: 0.29
Nodes (7): 4. The Daily Workflow (Edit → Save → Share), Step 1 — Get the latest code, Step 2 — Make a branch (your own safe lane), Step 3 — Edit the code, Step 4 — Save your work in Git ("commit"), Step 5 — Send your branch to GitHub ("push"), Step 6 — Open a Pull Request on GitHub

### Community 68 - "Community 68"
Cohesion: 0.29
Nodes (7): Account & engagement, Cart & checkout, Homepage (dynamic), Main Features, Operations, Product experience, Storefront & discovery

### Community 69 - "Community 69"
Cohesion: 0.48
Nodes (5): consucorner_single_post_card(), consucorner_single_post_category_label(), consucorner_single_post_image(), consucorner_single_post_image_url(), consucorner_single_post_reading_time()

### Community 70 - "Community 70"
Cohesion: 0.33
Nodes (4): cc_seed_log(), cc_seed_set_fulfillment(), cc_seed_track(), cc_seed_vendor_id()

### Community 74 - "Community 74"
Cohesion: 0.60
Nodes (5): appendInterestProfile(), escapeHtml(), formatPrice(), loadOverallBestsellers(), renderBestsellers()

### Community 75 - "Community 75"
Cohesion: 0.60
Nodes (5): clearAll(), clearTimers(), goToSlide(), restartAnimation(), setTimer()

### Community 76 - "Community 76"
Cohesion: 0.33
Nodes (6): 11. Quick Cheat Sheet, Deploy to live (senior dev), Open a Pull Request, Save your progress, Start a new task, Update your branch with the latest `main` (if `main` moved while you worked)

### Community 77 - "Community 77"
Cohesion: 0.70
Nodes (4): consucorner_help_index_content(), consucorner_help_page_definitions(), consucorner_help_support_block(), consucorner_setup_help_pages()

### Community 79 - "Community 79"
Cohesion: 0.40
Nodes (5): 7. Deploying to the Live Server (Cloudways + Git), Day‑to‑day deploy (after the setup above), One‑time setup (already done — keep for reference), Roll back if something is wrong, The picture (deploy version)

### Community 84 - "Community 84"
Cohesion: 0.50
Nodes (4): 8. Working with Other Developers, Daily rhythm (suggested), Recommended team setup, Workflow rules everyone follows

### Community 85 - "Community 85"
Cohesion: 0.50
Nodes (4): 9. Roles: Who Does What, How to hire / find help, Senior Developer (the helper you need), You (Project Owner / Junior Dev)

### Community 87 - "Community 87"
Cohesion: 0.50
Nodes (4): Architecture, Changing steps, Guided Product Tours (v2), Phases

### Community 90 - "Community 90"
Cohesion: 0.67
Nodes (3): 6. Pull Requests: Showing Your Work, Do not merge your own PR until you have:, Steps

### Community 156 - "Community 156"
Cohesion: 0.25
Nodes (7): cc_mrsa_seed_create_order(), cc_mrsa_seed_log(), cc_mrsa_seed_repair_existing(), cc_mrsa_seed_set_fulfillment(), cc_mrsa_seed_sync_dokan(), cc_mrsa_seed_track(), cc_mrsa_seed_vendor_id()

### Community 157 - "Community 157"
Cohesion: 0.26
Nodes (16): consucorner_ajax_live_search(), consucorner_format_search_term_card(), consucorner_get_product_categories_from_ids(), consucorner_get_product_search_categories(), consucorner_get_product_search_results(), consucorner_get_product_search_specialties(), consucorner_get_search_matched_term_rows(), consucorner_get_search_term_candidates() (+8 more)

### Community 158 - "Community 158"
Cohesion: 0.05
Nodes (62): cc_build_tracker_context(), cc_clamp_price_filter_max(), cc_get_price_filter_ceiling(), cc_get_specialty_related_product_ids(), cc_price_filter_exclude_quote_meta_query(), consucorner_scripts(), cc_begin_product_stock_order(), cc_build_archive_filter_url() (+54 more)

### Community 160 - "Community 160"
Cohesion: 0.48
Nodes (5): copyFromInput(), debounce(), getBaseSpecialtySlug(), initLinkBuilder(), postCount()

### Community 161 - "Community 161"
Cohesion: 0.24
Nodes (10): cc_add_bosta_tracking_order_total_row(), cc_assign_order_display_number(), cc_build_order_display_number(), cc_filter_order_tracking_order_id(), cc_filter_woocommerce_order_number(), cc_get_bosta_tracking_url(), cc_get_order_display_number(), cc_get_order_display_number_parts() (+2 more)

### Community 166 - "Community 166"
Cohesion: 0.21
Nodes (19): cc_apply_offer_deal_pricing(), consucorner_setup_static_pages(), cc_format_product_price_amount(), cc_bulk_should_show_exact_unit_price(), cc_find_bulk_tier_for_qty(), cc_format_bulk_unit_price_amount(), cc_format_bulk_unit_price_html(), cc_get_bulk_price_display_decimals() (+11 more)

## Knowledge Gaps
- **219 isolated node(s):** `extends`, `ignoreFiles`, `font-family-no-missing-generic-family-keyword`, `no-descending-specificity`, `block-no-empty` (+214 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **11 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Consucorner_Order_Return_Workflow` connect `Community 5` to `Community 161`, `Community 2`, `Community 70`, `Community 12`, `Community 17`, `Community 23`, `Community 156`, `Community 31`?**
  _High betweenness centrality (0.080) - this node is a cross-community bridge._
- **Why does `consucorner_scripts()` connect `Community 158` to `Community 2`, `Community 4`, `Community 11`, `Community 50`, `Community 60`?**
  _High betweenness centrality (0.077) - this node is a cross-community bridge._
- **Why does `Consucorner_Vendor_Ledger` connect `Community 6` to `Community 161`, `Community 1`?**
  _High betweenness centrality (0.050) - this node is a cross-community bridge._
- **Are the 24 inferred relationships involving `Consucorner_Order_Return_Workflow` (e.g. with `.build_dataset()` and `.ajax_review_cancel_request()`) actually correct?**
  _`Consucorner_Order_Return_Workflow` has 24 INFERRED edges - model-reasoned connections that need verification._
- **Are the 21 inferred relationships involving `CC_Returns_Refund_Service` (e.g. with `.ajax_process_wallet_refund()` and `.render_box_html()`) actually correct?**
  _`CC_Returns_Refund_Service` has 21 INFERRED edges - model-reasoned connections that need verification._
- **Are the 7 inferred relationships involving `Consucorner_Order_Cancel_Requests` (e.g. with `.get_order_ops_payload()` and `.render_vendor_order_detail()`) actually correct?**
  _`Consucorner_Order_Cancel_Requests` has 7 INFERRED edges - model-reasoned connections that need verification._
- **What connects `extends`, `ignoreFiles`, `font-family-no-missing-generic-family-keyword` to the rest of the system?**
  _219 weakly-connected nodes found - possible documentation gaps or missing edges._