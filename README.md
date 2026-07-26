# Consucorner Website (WordPress)

WordPress marketplace site for [ConsuCorner](https://github.com/Bassiouny2/Consucorner-website) — WooCommerce + Dokan multi-vendor, custom `consucorner` theme, and ConsucCorner custom plugins.

## What's included

- WordPress core (`wp-admin`, `wp-includes`, root files)
- Theme: `wp-content/themes/consucorner`
- Custom plugins:
  - `consucorner-security`
  - `consucorner-gtm`
  - `consucorner-order-migration`
  - `additional-gateways` (vendor SDK excluded — run Composer locally if needed)
- Static `front-end/` HTML prototypes

## Not included (install separately)

Commercial / third-party plugins are **not** published here (license + size), including WooCommerce, Dokan, GeIdeA, Digits, Rank Math, etc.

Media uploads, caches, and `wp-config.php` are also excluded.

## Setup

1. Clone this repo into your web root (or Local WP `app/public`).
2. Copy `wp-config-sample.php` → `wp-config.php` and set DB credentials + salts.
3. Install required plugins (WooCommerce, Dokan, GeIdeA, …) via WP Admin or Composer.
4. Activate the `consucorner` theme and ConsucCorner custom plugins.
5. Import DB / uploads from your environment backup as needed.

## Security note

Never commit `wp-config.php`, `.env`, database dumps, or customer export CSVs.
