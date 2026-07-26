# Specialty Assignment Guide For Next AI

Goal: products may not be linked to the custom WooCommerce product taxonomy `specialty`. For each product, understand what it is used for medically, choose the correct Specialty, create that Specialty term if needed, then assign the product to it.

## Important Project Context

- WordPress/WooCommerce local site.
- Products live in `wp_posts` with `post_type = 'product'`.
- Product metadata/attributes live in `wp_postmeta` and WooCommerce taxonomies.
- Specialty taxonomy slug is: `specialty`.
- The taxonomy is registered in:
  - `wp-content/themes/consucorner/inc/product-specialties-taxonomy.php`
- Do not modify WordPress core files.
- Prefer WP-CLI or existing project functions for term creation/assignment.

## Workflow

1. Read the product:
   - Get product ID, title, short description, long description, categories, brands, attributes, and existing terms.
   - Useful WP-CLI examples:
     ```bash
     wp post get <product_id> --fields=ID,post_title,post_excerpt,post_content
     wp post term list <product_id> product_cat
     wp post term list <product_id> specialty
     ```

2. Determine the Specialty:
   - Decide the medical specialty from product name, description, attributes, and category context.
   - Example: trial frames, lenses, refraction tools, ophthalmic surgical tools → `Ophthalmology`.

3. Create the Specialty if missing:
   ```bash
   wp term list specialty
   wp term create specialty "<specialty_name>"
   ```

4. Link the product:
   ```bash
   wp post term set <product_id> specialty "<specialty_name>"
   ```

5. Verify:
   ```bash
   wp post term list <product_id> specialty
   ```

## Batch assignment (automated)

Theme file `wp-content/themes/consucorner/inc/assign-specialty-batch-cli.php` assigns `specialty` to every published/draft/private product that currently has none:

- Longest substring match against **existing** `specialty` term names (title + short + long description).
- Otherwise uses the **root** `product_cat` (Yoast / Rank Math primary category preferred; skips Uncategorized).
- Otherwise creates **`General specialty`** and assigns.

Run:

```powershell
$env:CONSUCORNER_CLI_DB_HOST = '127.0.0.1:10030'   # use your Local site MySQL port from Local's sites.json
php.exe -d extension_dir=...ext -d extension=mysqli wp-cli.phar eval-file wp-content/themes/consucorner/inc/assign-specialty-batch-cli.php --path="C:\path\to\wordpress"
```

## Local WP-CLI Note

This Local site may require the MySQL forwarded host/port for WP-CLI. If WP-CLI cannot connect to the database, set:

```powershell
$env:CONSUCORNER_CLI_DB_HOST = '127.0.0.1:10030'
```

Then run WP-CLI from the site root. If `wp` is unavailable, use the downloaded `wp-cli.phar` with Local's PHP binary and enable `mysqli`.

**Database host:** `wp-config.php` supports `CONSUCORNER_CLI_DB_HOST` when `WP_CLI` is defined so CLI can use `127.0.0.1:<port>` while the site still uses `localhost` in the browser.

## Final Outcome

The final goal is simple:

> Go understand this product, decide what Specialty it belongs to, and link it to that field.

Report back with:

- Product name and ID.
- Specialty chosen and why.
- Whether the term was created or already existed.
- Confirmation that the product is linked to `specialty`.
