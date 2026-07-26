# Return Orders — Admin Setup Checklist

Customer return requests use **Dokan Pro RMA**. Money is resolved by staff from **WooCommerce → Returns** (wallet credit or recorded manual refund). Geidea is never auto-reversed from the theme.

## 1. Activate the RMA module

1. **Dokan → Modules**
2. Enable **RMA / Return and Warranty**
3. Save

## 2. Configure RMA settings

**Dokan → Settings → RMA**

| Setting | Recommended (first launch) | Notes |
|--------|----------------------------|--------|
| Eligible order status | `Completed` | Customers can request after delivery |
| Return reasons | Configure per store policy | Shown on customer form |
| Warranty types | As needed | Replace / refund types |
| **Enable refund request** (`rma_enable_refund_request`) | **ON** (theme default) | Shows **Return / Refund** on the customer form only — does **not** auto-reverse Geidea; staff still resolves money on **WooCommerce → Returns** |
| Enable coupon / store-credit request | Optional | Can stay OFF if you only use the theme wallet |

## 3. Product eligibility (automatic on this theme)

The theme file `inc/returns-rma-config.php` applies **site-wide** return eligibility:

- **Dokan → Settings → RMA** — order status `Completed`, **refund request ON** (form label only), coupon requests OFF
- **Every vendor** — default **Warranty Included (lifetime)** on `_dokan_rma_settings`
- **Every line item** — `_dokan_item_warranty` stamped at checkout
- **Historical orders** — backfill runs once on theme load (all old line items get warranty meta)

You do **not** need per-product RMA tabs for returns to work. Vendors can still override per product later if needed.

Manual re-run (e.g. after staging deploy): switch theme away and back, or delete option `consucorner_rma_config_version` in the database and reload any page.

## 3b. Where customers request a return

1. **My Account → Order History** → **Request return** on a **Delivered** / WooCommerce **Completed** order, **or**  
2. **My Account → Returns & Refunds** → list of past requests + link to start a new one from an eligible order  

If **Request return** is missing, the order is still in progress (customer should use **Cancel order** instead), not yet Delivered/Completed, or all quantities were already returned.

**Cancel order** is available from Confirmed through Out for delivery. After delivery/completion, cancel is blocked and return is used instead.

If the return form URL shows **404** or the **dashboard** instead of the form, reload any page once (theme flushes Dokan RMA rewrite rules automatically) or visit **Settings → Permalinks → Save** in wp-admin.

## 4. Customer flow

1. Customer: **My Account → Orders** (profile) or Dokan RMA endpoint  
2. Before delivery: **Cancel order** → ops approve/reject  
3. After delivery/completed: **Request return** → choose item(s) → submit  
4. Return status progresses via ops: reviewing → approved → in transit → received → Wallet/Direct  

## 5. Manager flow (theme)

1. **WooCommerce → Returns** — filter, monitor, export CSV  
   - Pending count badge appears on **WooCommerce** and **Returns** (like processing orders)  
2. For each open request, choose:
   - **Refund to wallet** — credits `cc_add_to_custom_wallet()`; reduces Dokan vendor earning + balance (ledger/payouts)  
   - **Direct (manual)** — records a WooCommerce refund for bookkeeping after offline payment (bank/Geidea portal); **also** reduces Dokan vendor earning + balance for monthly vendor analysis  

### Where returns appear for ops / vendors

| Place | What you see |
|--------|----------------|
| **WooCommerce → Returns** | Master list + Wallet / Direct actions + pending badge |
| **WooCommerce → Orders** | **Returns** column: Open / Resolved |
| **Order edit screen** | Yellow notice + **Returns & Refunds** meta box |
| **Vendor Dashboard → RMA** | Vendor sees their store’s return requests (Dokan) |
| **Vendor Ledger** | After Wallet/Direct, `dokan_orders.net_amount` and `dokan_vendor_balance` (type `dokan_refund`) are reduced so month-end payouts stay accurate |

## 5b. Admin refund without a customer RMA request

| Method | Where | Use case |
|--------|--------|----------|
| **Returns report** | WooCommerce → Returns | Customer submitted RMA — preferred |
| **Wallet meta box** | Orders → edit order → **Wallet Operations** sidebar | Partial refund to site wallet; select line items + qty |
| **WooCommerce refund** | Orders → edit order → **Refund** (per line item qty at bottom) | Record manual refund in WC after offline payment |

Do not refund the same line item twice (wallet meta box + Returns report).

## 6. Legacy wallet meta box

**Orders → Wallet Operations** remains for edge cases but is **blocked** when the same items are already covered by an RMA resolution or WooCommerce refund.

## 7. Geidea / Dokan safety

- Do **not** enable automated Dokan refund approval that hits the payment gateway until you explicitly want card reversals.  
- Geidea webhook paths (`/wc-api/geidea*`, `/wp-json/geidea*`) are untouched by this feature.  
- Dokan REST routes are not rate-limited or blocked by the security plugin.

## 8. Verify after go-live

- [ ] Customer can submit a return on a completed order  
- [ ] Request appears on **WooCommerce → Returns**  
- [ ] Wallet resolution credits customer wallet and marks request `completed`  
- [ ] Direct resolution creates a WC refund (payment **not** refunded online) and marks request `completed`  
- [ ] Same line item cannot be refunded twice via wallet meta box  
