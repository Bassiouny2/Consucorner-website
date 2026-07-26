# ConsuCorner GTM

WordPress plugin for Google Tag Manager, GA4-compatible `dataLayer` ecommerce events, and optional GTM API auto-setup.

## Activate

1. Upload / enable **ConsuCorner GTM** in **Plugins**.
2. Open **ConsuCorner GTM → Dashboard**.
3. Confirm **GTM Container ID** in **Settings** (default: `GTM-NDB325C8`).
4. Keep **Block Rank Math frontend analytics** enabled so all tags run through GTM only (Rank Math SEO stays active).

## One-click setup (recommended)

Use the dashboard wizard: **ConsuCorner GTM → Dashboard**.

| Step | What happens |
|------|----------------|
| 1 Settings | GTM ID saved; GTM + dataLayer enabled on the storefront |
| 2 Connect Google | OAuth with Tag Manager API scope |
| 3 Select Container | Auto-matched by public ID (`GTM-…`) during setup |
| 4 Run Setup | New workspace + variables, triggers, tags (GA4/Meta/Ads if enabled in Settings) |
| 5 Preview in GTM | Test in GTM Preview before publishing live |

**Primary action:** **Start One-Click Setup** — runs container match → workspace creation → API setup in sequence when OAuth is connected.

**Advanced:** **GTM Auto Setup** submenu for manual account/container pick, separate workspace/create/run steps, and checklist download.

### OAuth prerequisites (required for API setup)

1. [Google Cloud Console](https://console.cloud.google.com/) project with **Tag Manager API** enabled.
2. **OAuth 2.0 Client ID** (Web application).
3. Redirect URI from **Settings → Google OAuth** (must match exactly):
   `https://your-site.com/wp-admin/admin.php?page=cc-gtm-google-auth`
4. Save **Client ID** and **Client Secret** in plugin settings.
5. **Google Auth → Connect Google Account** (or use the dashboard Connect button).

Scope: `https://www.googleapis.com/auth/tagmanager.edit.containers`

The connected Google user needs **Edit** access to the GTM container.

## dataLayer testing

1. **ConsuCorner GTM → DataLayer Health** — follow the test flow.
2. Storefront console:

```js
window.dataLayer.map((x) => x.event)
window.dataLayer.filter((x) => x.event === "purchase")
```

3. Enable **Debug mode** in settings for an admin-only event overlay.

Quick server check:

```bash
curl -sL https://your-site/ | grep -E "gtm.js|dataLayer|GTM-"
```

## Platform tags (optional)

Before one-click setup, enable platforms in **Settings** and add IDs:

- **GA4** — measurement ID `G-…`
- **Meta** — pixel ID
- **Google Ads** — conversion ID `AW-…` and labels

Only enabled platforms receive GTM tags during auto-setup.

## GTM Auto Setup (advanced)

1. Configure platform IDs in **Settings**.
2. Connect Google (above).
3. **GTM Auto Setup** → pick account & container (dropdown loads containers via AJAX).
4. **Create workspace** → **Run GTM Auto Setup**.
5. **Open GTM Preview** and test on the live site.
6. Publish manually in [tagmanager.google.com](https://tagmanager.google.com/) when ready.

**Auto-publish is OFF by default.** Enable in settings only if you accept publishing live from WordPress (double confirmation on the advanced setup form).

## Avoid duplicate tracking

- Do not add a second GTM snippet in the theme.
- Keep Rank Math **install_code** disabled (plugin filter when guard is on).
- Do not enable GA4 in Rank Math, WooCommerce plugins, and GTM at the same time.

### Conflict guard (automatic)

If another GTM plugin is active (e.g. **GTM4WP**, **GTM Kit**, **Metronet Tag Manager**), it injects a second container and a duplicate ecommerce dataLayer — slowing the storefront and double-counting events.

- **Silence other GTM plugins** (Settings, ON by default) auto-suppresses GTM4WP's frontend container + dataLayer so only this plugin's container runs.
- The **Dashboard** shows a conflict card with a one-click **Deactivate conflicting plugin(s)** button for a fully clean setup.
- A warning also appears on the **Plugins** screen when a conflict is detected.

## Manual fallback

If OAuth is unavailable, use **Download checklist JSON** on the Auto Setup page and create items manually in GTM.

## File structure

- `consucorner-gtm.php` — bootstrap
- `includes/` — PHP classes (`CC_GTM_API`, `CC_GTM_Auto_Setup`, admin, dataLayer, etc.)
- `assets/cc-gtm.js` — storefront dataLayer helpers
- `assets/admin.*` — WordPress admin UI
