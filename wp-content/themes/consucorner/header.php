<?php

/**
 * Theme header - front markup from static index.html
 *
 * @package ConsuCorner
 */

defined('ABSPATH') || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
  <?php wp_body_open(); ?>
  <!-- SVG Sprite (hidden, referenced via <use href="#icon-id">) -->
  <svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden">
    <symbol id="icon-login" viewBox="0 0 24 24">
      <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
      <polyline points="10 17 15 12 10 7" />
      <line x1="15" y1="12" x2="3" y2="12" />
    </symbol>
    <symbol id="icon-chevron-down" viewBox="0 0 24 24">
      <polyline points="6 9 12 15 18 9" />
    </symbol>
    <symbol id="icon-chevron-right" viewBox="0 0 24 24">
      <polyline points="9 18 15 12 9 6" />
    </symbol>
    <symbol id="icon-chevron-left" viewBox="0 0 24 24">
      <polyline points="15 18 9 12 15 6" />
    </symbol>
    <symbol id="icon-search" viewBox="0 0 24 24">
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </symbol>
    <symbol id="icon-cart" viewBox="0 0 24 24">
      <circle cx="9" cy="21" r="1" />
      <circle cx="20" cy="21" r="1" />
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    </symbol>
    <symbol id="icon-tag-cpu" viewBox="0 0 24 24">
      <rect x="4" y="4" width="16" height="16" rx="2" ry="2" />
      <rect x="9" y="9" width="6" height="6" />
      <line x1="9" y1="1" x2="9" y2="4" />
      <line x1="15" y1="1" x2="15" y2="4" />
      <line x1="9" y1="20" x2="9" y2="23" />
      <line x1="15" y1="20" x2="15" y2="23" />
      <line x1="20" y1="9" x2="23" y2="9" />
      <line x1="1" y1="9" x2="4" y2="9" />
      <line x1="1" y1="14" x2="4" y2="14" />
    </symbol>
    <symbol id="icon-tag-clock" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="10" />
      <polyline points="12 6 12 12 16 14" />
    </symbol>
    <symbol id="icon-tag-shield" viewBox="0 0 24 24">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
    </symbol>
    <symbol id="icon-facebook" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5" />
      <path d="M13.5 8.5H15V6.5H13C11.6 6.5 10.5 7.6 10.5 9V10.5H9V12.5H10.5V18H12.5V12.5H14L14.5 10.5H12.5V9C12.5 8.7 12.7 8.5 13 8.5H13.5Z" fill="currentColor" />
    </symbol>
    <symbol id="icon-linkedin" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="11" stroke="currentColor" stroke-width="1.5" />
      <rect x="7" y="10.5" width="2" height="6.5" fill="currentColor" />
      <circle cx="8" cy="8.5" r="1" fill="currentColor" />
      <path d="M11.5 10.5H13.5V11.3C13.9 10.8 14.5 10.5 15.2 10.5C16.8 10.5 17.5 11.5 17.5 13V17H15.5V13.5C15.5 12.8 15.2 12.3 14.5 12.3C13.8 12.3 13.5 12.8 13.5 13.5V17H11.5V10.5Z" fill="currentColor" />
    </symbol>
    <symbol id="icon-instagram" viewBox="0 0 24 24">
      <rect x="3.5" y="3.5" width="17" height="17" rx="4.5" stroke="currentColor" stroke-width="1.5" />
      <circle cx="12" cy="12" r="3.75" stroke="currentColor" stroke-width="1.5" />
      <circle cx="17.2" cy="6.8" r="1" fill="currentColor" />
    </symbol>
    <symbol id="icon-youtube" viewBox="0 0 24 24">
      <rect x="3" y="7" width="18" height="10" rx="2.5" stroke="currentColor" stroke-width="1.5" />
      <path d="M11 10.5L15 12.5L11 14.5V10.5Z" fill="currentColor" />
    </symbol>
    <symbol id="icon-twitter" viewBox="0 0 24 24">
      <path d="M4 4L10.2 13.2L4 20H5.8L11 14.7L15.2 20H20L13.4 10.1L19.2 4H17.4L12.6 8.6L8.8 4H4Z" fill="currentColor" />
    </symbol>
    <symbol id="icon-external-link" viewBox="0 0 24 24">
      <path d="M14 5H19V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      <path d="M10 14L19 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
      <path d="M19 14V19H5V5H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
    </symbol>
  </svg>

  <!-- Mobile Navigation Drawer -->
  <div class="mobile-drawer" id="mobile-drawer" aria-hidden="true">

    <!-- Logo + Close -->
    <div class="cc-drawer-header">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="cc-drawer-logo" aria-label="<?php esc_attr_e('Go to ConsuCorner home', 'consucorner'); ?>">CONSU<span>CORNER</span></a>
      <button class="cc-drawer-close" id="drawer-close-btn" type="button" aria-label="<?php esc_attr_e('Close menu', 'consucorner'); ?>">
        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
          <path d="M1 1L13 13M13 1L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
      </button>
    </div>

    <!-- Login / Account -->
    <?php
    if (is_user_logged_in()) :
      $cc_mob_user = wp_get_current_user();
      $cc_mob_acc  = function_exists('wc_get_account_endpoint_url')
        ? wc_get_account_endpoint_url('dashboard')
        : get_permalink((int) get_option('woocommerce_myaccount_page_id'));
    ?>
      <a href="<?php echo esc_url($cc_mob_acc); ?>" class="cc-drawer-login">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="8" r="4" />
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
        </svg>
        <?php echo esc_html($cc_mob_user->display_name); ?>
      </a>
    <?php else : ?>
      <a href="#" class="cc-drawer-login">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <use href="#icon-login" />
        </svg>
        <?php esc_html_e('Login / Signup', 'consucorner'); ?>
      </a>
    <?php endif; ?>

    <!-- Shop / Explore tabs -->
    <div class="drawer-tabs">
      <button class="drawer-tab active" data-tab="shop" data-url="<?php echo esc_url(home_url('/shop/')); ?>">
        <?php esc_html_e('Shop', 'consucorner'); ?>
      </button>
      <button class="drawer-tab" data-tab="explore">
        <?php esc_html_e('Explore', 'consucorner'); ?>
      </button>
    </div>

    <!-- Shop Tab Content -->
    <div class="drawer-content" data-content="shop">
      <?php
      if (function_exists('consucorner_render_mobile_shop_drawer')) {
        consucorner_render_mobile_shop_drawer();
      }
      ?>
    </div>

    <!-- Explore Tab Content -->
    <div class="drawer-content hidden" data-content="explore">
      <?php
      if (function_exists('consucorner_render_mobile_explore_drawer')) {
        consucorner_render_mobile_explore_drawer();
      }
      ?>
    </div>

  </div>
  <!-- Overlay -->
  <div class="drawer-overlay" id="drawer-overlay"></div>

  <header class="site-header">
    <div class="header-container">
      <!-- Hamburger (mobile only) -->
      <button class="hamburger-btn" aria-label="Open menu" id="hamburger-btn">
        <img
          src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/hamburger-menu.svg"
          alt="Menu"
          width="28"
          height="28" />
      </button>

      <!-- Left Group: Logo & Nav -->
      <div class="header-left">
        <!-- Logo -->
        <a href="<?php echo esc_url(home_url('/')); ?>" class="header-logo">
          <img src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/main - logo.svg" alt="ConsuCorner Logo" width="140" height="22" />
        </a>

        <!-- Navigation -->
        <nav class="header-nav">
          <div class="nav-item-shop" id="nav-item-shop">
            <a href="<?php echo esc_url(home_url('/shop/')); ?>" class="nav-link nav-link-shop">Shop</a>
          </div>
          <div class="nav-item-explore" id="nav-item-explore">
            <a href="#" class="nav-link nav-link-explore">Explore</a>
          </div>
        </nav>
      </div>

      <!-- Search Bar (desktop) -->
      <form class="header-search cc-live-search" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get" data-cc-live-search-form>
        <button class="search-icon" type="submit" aria-label="<?php esc_attr_e('Search products', 'consucorner'); ?>">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round">
            <use href="#icon-search" />
          </svg>
        </button>
        <input
          type="search"
          name="s"
          placeholder="Find your product"
          class="search-input"
          data-cc-tour="header-search"
          value="<?php echo esc_attr(get_search_query()); ?>"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          inputmode="search"
          enterkeyhint="search"
          aria-label="<?php esc_attr_e('Search products and categories', 'consucorner'); ?>"
          aria-autocomplete="list"
          aria-expanded="false" />
        <div class="cc-live-search-panel" data-cc-live-search-panel hidden></div>
      </form>

      <!-- Right Actions -->
      <div class="header-actions<?php echo is_front_page() ? ' cc-header-actions--home' : ' cc-header-actions--inner'; ?>">
        <?php
        $cc_cart_count = (function_exists('WC') && WC()->cart)
          ? (int) WC()->cart->get_cart_contents_count()
          : 0;
        ?>
        <a href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/')); ?>" class="cart-btn">
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round">
            <use href="#icon-cart" />
          </svg>
          <span
            class="cart-badge"
            <?php if ($cc_cart_count <= 0) : ?>style="display:none" <?php endif; ?>><?php echo $cc_cart_count > 0 ? esc_html($cc_cart_count) : ''; ?></span>
        </a>

        <?php if (is_user_logged_in()) :
          $cc_user    = wp_get_current_user();
          $cc_acc_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('dashboard')
            : get_permalink((int) get_option('woocommerce_myaccount_page_id'));
        ?>
          <a href="<?php echo esc_url($cc_acc_url); ?>" class="auth-account-link">
            <img
              src="<?php echo esc_url(function_exists('consucorner_get_user_profile_avatar_url') ? consucorner_get_user_profile_avatar_url($cc_user->ID, 56) : get_avatar_url($cc_user->ID, array('size' => 56))); ?>"
              alt="<?php echo esc_attr($cc_user->display_name); ?>"
              class="auth-avatar" />
            <span class="auth-account-name"><?php echo esc_html($cc_user->display_name); ?></span>
          </a>
        <?php else : ?>
          <a href="#" class="auth-link">LOGIN/SIGNUP</a>
        <?php endif; ?>

        <a href="<?php echo esc_url(home_url('/vendor/')); ?>" class="vendor-btn">JOIN AS A VENDOR</a>
      </div>
    </div>

    <!-- Desktop Mega Menu (Shop hover) -->
    <?php
    if (function_exists('consucorner_render_product_mega_menu')) {
      consucorner_render_product_mega_menu();
    }
    ?>

    <!-- Desktop Mega Menu (Explore hover) -->
    <?php
    if (function_exists('consucorner_render_explore_mega_menu')) {
      consucorner_render_explore_mega_menu();
    }
    ?>

    <!-- Mobile Search Row (hidden on desktop) -->
    <div class="mobile-search-row">
      <form class="mobile-search-inner cc-live-search" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get" data-cc-live-search-form data-cc-tour="header-search">
        <input
          type="text"
          name="s"
          placeholder="Find your product"
          class="mobile-search-input"
          value="<?php echo esc_attr(get_search_query()); ?>"
          autocomplete="off"
          autocorrect="off"
          autocapitalize="off"
          spellcheck="false"
          inputmode="search"
          enterkeyhint="search"
          role="searchbox"
          aria-label="<?php esc_attr_e('Search products and categories', 'consucorner'); ?>"
          aria-autocomplete="list"
          aria-expanded="false" />
        <button class="mobile-search-btn" type="submit">
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
            stroke-linecap="round"
            stroke-linejoin="round">
            <use href="#icon-search" />
          </svg>
          Search
        </button>
        <div class="cc-live-search-panel cc-live-search-panel--mobile" data-cc-live-search-panel hidden></div>
      </form>
    </div>
  </header>