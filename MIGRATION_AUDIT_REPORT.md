# ConsuCorner WordPress/WooCommerce Migration Audit Report

## 1. Executive Summary

Based on the provided `public` folder scan, this is a local migration workspace (not a clean production package).  
Core WordPress files are present, and most business-critical custom logic is in:

- `wp-content/themes/consucorner`
- `wp-content/plugins/consucorner-security`
- `wp-content/plugins/Referral-Discounts-for-WooCommerce-RDFW-main`

Safest migration approach:

1. Migrate database + custom theme + custom plugins + real production uploads first.
2. Reinstall standard plugins from official/licensed sources (matching versions).
3. Avoid manually copying logs/cache/temp/import staging.
4. Remove sensitive migration artifacts (CSV/log/scripts) from web root before go-live.

---

## 2. Folder Classification Table

| Path | Type | Purpose | Keep / Move / Delete / Review | Destination in new site | Risk Level | Notes |
|---|---|---|---|---|---|---|
| `wp-admin` | WP core | Admin core | Keep | Same path | Low | Standard WP core |
| `wp-includes` | WP core | Core libraries | Keep | Same path | Low | Standard WP core |
| `wp-content/themes/consucorner` | Custom theme | Main frontend + WC overrides | Keep/Move | `wp-content/themes/consucorner` | High | Must migrate fully |
| `wp-content/themes/twentytwentyfive` | Default theme | Fallback | Keep or Delete | Same | Low | Optional on production |
| `wp-content/themes/consucorner-security` | Non-standard theme folder | Looks incomplete/orphan | Review | N/A | Medium | No clear valid theme structure |
| `wp-content/themes/consucorner.zip` | Archive artifact | Theme backup copy | Delete (after verify) | N/A | Low | Duplicate packaging artifact |
| `wp-content/plugins/consucorner-security` | Custom plugin | Security/business logic | Keep | Same path | High | Migrate with DB options/tables |
| `wp-content/plugins/Referral-Discounts-for-WooCommerce-RDFW-main` | Custom plugin | Referral discount logic | Keep/Review | Same (possibly rename slug) | High | `-main` naming suggests manual/GitHub install |
| `wp-content/plugins/woocommerce` | Standard plugin | E-commerce core | Reinstall | WP plugin install | High | Do not manually patch-copy |
| `wp-content/plugins/dokan-lite` | Standard plugin | Marketplace vendors | Reinstall | WP plugin install | High | Confirm Lite vs Pro gap |
| `wp-content/plugins/*` (other standard plugins) | Standard plugins | Payments/SEO/forms/imports/backups | Reinstall | WP plugin install | Medium | Restore settings from DB |
| `wp-content/uploads/cc-blog-images` | Uploads media | Branded SVGs | Keep | `wp-content/uploads/...` | Low | Valid media assets |
| `wp-content/uploads/forminator` | Plugin-generated uploads | Form CSS/assets | Keep/Regenerate | Same | Low | Usually regenerable |
| `wp-content/uploads/wpallimport/files` | Import source files | CSV import data | Review/Move | Secure archive outside web root | High | Contains orders/coupons data |
| `wp-content/uploads/wpallimport/uploads` | Temp/import cache | Duplicated generated copies | Delete | N/A | Medium | Regenerable/staging artifacts |
| `wp-content/uploads/wpallimport/logs` | Logs | Import traces | Delete | N/A | Medium | Not needed after verification |
| `wp-content/uploads/wc-logs` | Logs | WC/runtime logs | Delete old / Keep recent | N/A | Medium | Keep short retention only |
| `wp-content/updraft` | Backups/temp | Updraft backup sets | Review | Offsite backup storage | Medium | Keep one verified backup set only |
| `wp-content/upgrade-temp-backup` | Temp | Upgrade temp backup | Delete | N/A | Low | Usually safe post-upgrade |
| `front-end` | Non-WP project dir | Static prototype files | Move/Review | Outside web root | Medium | Not runtime WP core |
| `spec-kit` | Dev tooling | Specs/scripts/docs | Move | Outside web root | Low | Should not be publicly served |
| `.cursor`, `.specify` | IDE/dev metadata | AI/editor tooling | Move/Exclude | Dev repo only | Low | Exclude from deploy |
| `migrate-*.php`, `master-migration.php` | Custom migration scripts | Data migration execution | Move/Delete (post migration) | Private scripts dir outside web root | Critical | Contains sensitive integration logic/keys risk |
| `Users-Export-2026-May-21-1049.csv` | Export/PII | User export | Move/Delete | Secure offline storage | Critical | Sensitive data |
| `cc-user-migration.log` | Log | Migration run log | Move/Delete | Secure archive | High | May expose emails/paths |
| `local-xdebuginfo.php` | Debug script | Environment info | Delete | N/A | High | Info disclosure risk |
| `wp-cli.phar` | CLI binary | WP-CLI runtime | Move/Block | Outside web root | Medium | Avoid serving from document root |

---

## 3. Theme-Related Files

These should live in the active custom theme (`wp-content/themes/consucorner`) because they directly control frontend and WooCommerce behavior:

- `functions.php`  
  Handles asset enqueueing, Woo hooks, checkout/cart behavior, and AJAX endpoint wiring.

- `woocommerce/` (template overrides)  
  Includes custom templates for shop, product, cart, checkout, thank-you, and account pages.

- `inc/` modules  
  Contains custom business logic (filters, wallet, profile/account, vendor ledger, taxonomy/meta features, Dokan integration).

- `template-parts/`  
  Reusable frontend partials used by page/archive templates.

- Main template files (`front-page.php`, `archive.php`, `single.php`, `taxonomy-specialty.php`, `page-*.php`, etc.)  
  Define page structure and custom page output.

- `assets/css`, `assets/js`  
  Required for theme design and interactive behavior.

- `assets/images`, `assets/fonts`  
  Theme brand/UI visual dependencies.

- `front-end/profile.html` (inside the theme folder)  
  Used by the custom profile/account flow.

Note: No confirmed `woocommerce/emails/` overrides in theme from this scan.

---

## 4. Uploads and Media Files

### Should stay in `wp-content/uploads`

- `uploads/cc-blog-images` (brand/blog SVG assets)
- `uploads/forminator` (plugin-generated assets; can be regenerated)
- Full production media library folders (`uploads/YYYY/MM`) from live source

### Migration-data or non-runtime files

- `uploads/wpallimport/files/*.csv` (import source files)
- `uploads/wpallimport/uploads/*` (import temp/generated duplicates)
- `uploads/wpallimport/logs/*` (import logs)

### Duplicate/old indicators

- Repeated hashed import folders under `uploads/wpallimport/uploads`
- Multiple Updraft backup generations and `.tmp` files in `wp-content/updraft`

---

## 5. Plugin-Related Files

| Plugin Group | Recommendation | Why |
|---|---|---|
| `consucorner-security` | Migrate as-is + DB data/options | Custom plugin with site-specific behavior |
| `Referral-Discounts-for-WooCommerce-RDFW-main` | Migrate as-is + review slug naming | Appears custom/manual source |
| WooCommerce, Dokan, Geidea, Paymob, Rank Math, Forminator, WP All Import, Smart Coupons, Updraft | Reinstall from official/licensed source (version matched) | Standard vendor plugins; avoid manual code copy unless modified |
| Generated plugin cache/log/temp files | Ignore/Delete | Regenerable artifacts |

Important: Do not manually copy standard plugin code unless custom edits are verified.

---

## 6. Files That Can Probably Be Deleted

| Path | Why probably safe | Confidence |
|---|---|---|
| `wp-content/upgrade-temp-backup` | Temporary upgrade folder | High |
| `wp-content/uploads/wpallimport/logs` | Import logs only | High |
| `wp-content/uploads/wpallimport/uploads` | Import cache/duplicates | High |
| Old files in `wp-content/uploads/wc-logs` | Runtime logs; not required for production | High |
| `.~lock.buttons_audit_report.csv#` | Editor lock/temp file | High |
| `wp-content/themes/consucorner.zip` | Archive duplicate of theme | High |
| `buttons_audit_report.csv` (if not needed) | Audit artifact | Medium |
| `cc-user-migration.log` (after validation) | Operational log only | Medium |
| `Users-Export-2026-May-21-1049.csv` (after secure backup) | Sensitive export not for web root | High |
| `local-xdebuginfo.php` | Debug file with disclosure risk | High |

---

## 7. Files That Need Manual Review Before Deletion

| Path | Why review first |
|---|---|
| `migrate-data.php`, `migrate-categories.php`, `migrate-users-vendors.php`, `migrate-dokan-complete.php`, `master-migration.php` | May still be needed for reruns; also security-sensitive |
| `front-end/` | Could still be used as design/dev source |
| `spec-kit/` | Could be active for engineering workflow |
| `wp-content/themes/consucorner-security/` | Role unclear (possibly orphan folder) |
| `wp-content/updraft/*` | Confirm at least one successful backup before pruning |
| `wp-cli.phar` | Useful locally but should not stay in public web root |
| `uploads/wpallimport/files/*.csv` | Needed if imports must be rerun |
| `buttons_audit_report.md` | Might be required for QA handoff |
| Any non-standard root `.php` files | Confirm purpose before deletion |

---

## 8. WooCommerce-Specific Files

### Migrate

- `wp-content/themes/consucorner/woocommerce/*` template overrides
- Woo custom hooks/logic in `functions.php` and `inc/*` (wallet, checkout, account/profile, filtering)
- Custom taxonomy/meta logic for products (`specialty`, `procedure`, term images, `_cc_*` meta)
- WooCommerce, Dokan, wallet/referral/security related DB data (orders, products, users, settings)

### Regenerate/Reinstall

- Standard plugin files (`woocommerce`, `dokan-lite`, gateways, imports, etc.)
- Logs, temp folders, cache-like generated import folders

### Import files found (review)

- Product/order/coupon CSVs in `uploads/wpallimport/files`
- Keep as controlled sources only if rerun is required

---

## 9. Security Check

### High/Critical flags

- `Users-Export-2026-May-21-1049.csv` in web root (sensitive user export)
- `cc-user-migration.log` in web root (may include emails/paths)
- Root migration scripts may expose integration details/credentials
- `local-xdebuginfo.php` should not exist in production path

### Pattern scan notes

- No strong malware signal in custom theme/plugin logic from this scan
- No PHP files found under `wp-content/uploads` (good)
- `eval/base64/gzinflate` mainly found in vendor/plugin libraries (common; keep plugins updated)

### Hardening recommendations

- Move sensitive artifacts outside web root
- Deny direct access to import/log directories
- Rotate credentials if sensitive files were publicly reachable
- Keep strict filesystem permissions and disable directory indexing

---

## 10. Old Domain and Path References

| File path | Found reference | Recommended replacement | Priority |
|---|---|---|---|
| `wp-content/updraft/log.*.txt` | `http://new-consucorner.local` | Production canonical `https://` domain | High |
| `wp-content/uploads/wpallimport/logs/*.html` | Mixed `http://new-consucorner.local` and `https://consucorner.com` | Normalize to target production domain and HTTPS | High |
| `wp-config.php` | Local DB host/env values (`localhost`, local env markers) | Production env-specific config/secrets | High |
| `wp-content/themes/consucorner/functions.php` | Hardcoded `/wp-content/themes/consucorner/assets/...` references | Use `get_template_directory_uri()`/WP path helpers | Medium |
| `wc-logs`, migration logs | Local absolute Windows paths | Remove logs before production | Medium |
| Plugin metadata/docs | `http://` vendor links | No runtime action required | Low |

---

## 11. Final Migration Plan

1. **Create immutable backups first**
   - Full database dump + full source `wp-content` archive.
   - Keep one verified offsite backup copy.

2. **Copy custom code**
   - `wp-content/themes/consucorner`
   - `wp-content/plugins/consucorner-security`
   - `wp-content/plugins/Referral-Discounts-for-WooCommerce-RDFW-main`

3. **Migrate uploads correctly**
   - Copy full production `wp-content/uploads/YYYY/MM`.
   - Include required custom upload subfolders (e.g., brand assets/downloadables).
   - Exclude logs/cache/import staging folders.

4. **Reinstall standard plugins**
   - Install from official/licensed packages at matching versions.
   - Restore plugin settings/options from DB.

5. **Clean up deploy tree**
   - Remove root exports/logs/migration/debug files from web root.
   - Remove temp/duplicate artifacts after validation.

6. **Security hardening**
   - Rotate salts/keys/credentials as needed.
   - Block direct access to import/log directories.
   - Ensure production `wp-config.php` is environment-safe.

7. **Post-migration testing**
   - Home/shop/category/product pages
   - Cart/checkout/payment flow
   - Coupons/referral logic
   - Vendor dashboard/functions (Dokan)
   - My Account/profile/wallet
   - Email notifications and order lifecycle
   - Media loading and product/category imagery

---

## 12. Final Decision Table

| File/Folder | Action | New Location | Delete Status | Notes |
|---|---|---|---|---|
| `wp-content/themes/consucorner` | Move | `wp-content/themes/consucorner` | Do not delete | Core custom theme |
| `wp-content/plugins/consucorner-security` | Move | Same path | Do not delete | Custom plugin |
| `wp-content/plugins/Referral-Discounts-for-WooCommerce-RDFW-main` | Move/Review | Same path (or normalized slug) | Do not delete yet | Custom plugin |
| `wp-content/plugins/woocommerce` + standard plugins | Reinstall | WordPress plugin manager | N/A | Prefer clean install |
| `wp-content/uploads/YYYY/MM` (from source live site) | Move | Same path | Do not delete | Main media library |
| `wp-content/uploads/cc-blog-images` | Move | Same path | Do not delete | Valid media assets |
| `wp-content/uploads/wpallimport/files` | Review/Archive | Outside web root | Delete after signoff | Import source files |
| `wp-content/uploads/wpallimport/uploads` | Delete | N/A | Probably safe | Import temp/duplicates |
| `wp-content/uploads/wpallimport/logs` | Delete | N/A | Probably safe | Logs only |
| `wp-content/uploads/wc-logs` | Keep recent / delete old | N/A | Safe for old logs | Short retention only |
| `wp-content/updraft` | Review then prune | Offsite backup storage | Partial delete | Keep one verified set |
| `migrate-*.php`, `master-migration.php` | Move/Delete | Private scripts folder | Delete from web root | Sensitive migration scripts |
| `Users-Export-2026-May-21-1049.csv` | Archive/Delete | Secure offline storage | Delete from web root | Critical PII risk |
| `cc-user-migration.log` | Archive/Delete | Secure offline storage | Delete from web root | Sensitive log |
| `local-xdebuginfo.php` | Delete | N/A | Safe | Debug disclosure risk |
| `front-end`, `spec-kit`, `.cursor`, `.specify` | Move/Exclude deploy | Outside public web root | Keep in dev only | Non-runtime content |
| `wp-content/themes/consucorner-security` | Review | N/A | Pending review | Unclear role |
