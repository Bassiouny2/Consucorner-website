# MRSA Medical — Live Test Scenarios

Use this checklist when testing order workflows on **local/staging** with vendor **mrsa-medical** only.

---

## Quick setup

| Item | Value |
|------|--------|
| **Site (local)** | `https://new-consucorner.local` |
| **Vendor login** | `mrsa-medical` |
| **Vendor dashboard** | `/dashboard/orders/` |
| **Customer account** | Any WooCommerce customer (used by seed script) |
| **Admin / Ops** | WP admin with `manage_woocommerce` |
| **Returns hub (ops)** | WP Admin → **WooCommerce → Returns** |
| **All orders (admin)** | WP Admin → **WooCommerce → Orders** |

### Seed demo orders (recommended before live QA)

Run from site root (`app/public`):

```powershell
$env:PHPRC = "C:/Users/DELL/AppData/Roaming/Local/run/MuOxUxI3w/conf/php"
$env:CONSUCORNER_CLI_DB_HOST = "127.0.0.1:10043"
& "C:\Users\DELL\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe" `
  "wp-content/themes/consucorner/tests/run-seed-mrsa-medical-orders.php"
```

**What the seed creates**

- Orders use **only products authored by mrsa-medical**
- Each order is synced to **Dokan** (`dokan_orders`) so it appears in the vendor dashboard
- Order meta: `_cc_demo_vendor = mrsa-medical`
- Order note prefix: `[MRSA DEMO]`
- Scenario label meta: `_cc_demo_scenario`

**Find seeded orders**

- Vendor dashboard → Orders (login as `mrsa-medical`)
- Admin → Orders → search order note: `MRSA DEMO`
- Or filter by custom field `_cc_demo_vendor` = `mrsa-medical`

---

## Status reference

### Fulfillment statuses (ops-controlled)

| Key | Label | Typical Bosta |
|-----|-------|---------------|
| `confirmed` | Confirmed | — |
| `preparing` | Preparing | — |
| `shipped` | Shipped | Received at warehouse (12) |
| `out_for_delivery` | Out for delivery | Out for delivery (41) |
| `delivered` | Delivered | Delivered (45) |
| `cancelled` | Cancelled | — |

**Allowed progression:** Confirmed → Preparing → Shipped → Out for delivery → Delivered  
Ops can only move **forward** along this path (no skipping backwards in normal flow).

### Return statuses (ops-controlled)

| Key | Label |
|-----|-------|
| `requested` | Requested |
| `reviewing` | Reviewing |
| `approved` | Approved |
| `return_in_transit` | Return in transit |
| `received` | Received |
| `resolved` | Resolved |
| `rejected` | Rejected |

**Return can start when fulfillment is:** Shipped, Out for delivery, or Delivered.

### Cancel request rules

| Rule | Detail |
|------|--------|
| **Customer can request cancel when** | Fulfillment is Confirmed, Preparing, Shipped, or Out for delivery |
| **After Delivered** | Customer uses **return flow**, not cancel |
| **Vendor dashboard** | Read-only — vendor **sees** cancel/return status, ops controls changes |

---

## Who tests what

| Role | Where to test | Can change status? |
|------|---------------|--------------------|
| **Vendor (mrsa-medical)** | `/dashboard/orders/` + order detail | No — read-only workflow panel |
| **Customer** | My Account → Orders / Profile | Cancel request, return request (when eligible) |
| **Operations / Admin** | WooCommerce → Returns + order tools | Fulfillment, cancel review, return workflow |

---

## A) Fulfillment scenarios — vendor dashboard

Login as **mrsa-medical** → **Dashboard → Orders**.

For each scenario, open the order and verify the **ConsuCorner operations status** panel and list columns.

| # | Scenario label (seed) | Expected fulfillment | Expected Bosta | Vendor checks |
|---|------------------------|----------------------|----------------|---------------|
| A1 | `FULFILLMENT: Confirmed` | Confirmed | — or empty | Order appears in list; fulfillment badge = Confirmed |
| A2 | `FULFILLMENT: Preparing` | Preparing | — | Badge = Preparing |
| A3 | `FULFILLMENT: Shipped` | Shipped | Received at warehouse + tracking `MRSA-SHIP-{id}` | Bosta column + tracking visible |
| A4 | `FULFILLMENT: Out for delivery` | Out for delivery | Out for delivery + tracking `MRSA-OFD-{id}` | Same |
| A5 | `FULFILLMENT: Delivered` | Delivered | Delivered + tracking `MRSA-DEL-{id}` | Same |

**Pass criteria (all A rows)**

- [ ] Order shows in vendor orders list (not missing from dashboard)
- [ ] Product belongs to **mrsa-medical** only (no other vendor items)
- [ ] **Bosta status** column shows correct text (or — when none)
- [ ] **Fulfillment** label matches table above
- [ ] **Your earnings on this order** panel shows total, commission, net payable
- [ ] Vendor **cannot** edit fulfillment (read-only message shown)

---

## B) Cancellation scenarios — vendor + customer + ops

| # | Scenario label (seed) | Fulfillment at start | Cancel state | Who verifies |
|---|------------------------|----------------------|--------------|--------------|
| B1 | `CANCEL: Pending ops review` | Confirmed | Pending | Customer submitted; ops not decided yet |
| B2 | `CANCEL: Rejected by ops` | Preparing | Rejected | Ops rejected; order still open |

### B1 — Pending cancel (live steps)

1. **Customer:** Place order with any **mrsa-medical** product (or use seeded B1 order)
2. **Customer:** Request full order cancellation from My Account
3. **Vendor:** Open order → see **Cancellation: Pending** (or equivalent label)
4. **Ops:** WooCommerce → Returns → load order → review pending cancel
5. **Do not approve yet** — leave pending for dashboard check

**Pass criteria**

- [ ] Vendor sees cancellation badge on order list
- [ ] Order remains **Processing** (not cancelled)
- [ ] Ops sees pending cancel request

### B2 — Rejected cancel (live steps)

1. Use seeded B2 or create new order → customer requests cancel
2. **Ops:** Reject cancel with reason (e.g. “Already packed”)
3. **Vendor:** Refresh order → **Cancellation: Rejected**
4. **Customer:** Order still active; can continue to fulfillment

**Pass criteria**

- [ ] Vendor sees rejected cancellation state
- [ ] Fulfillment can still advance (Preparing → Shipped → …)
- [ ] Order was **not** auto-cancelled

### B3 — Item-level cancel (manual only; not in MRSA seed)

1. Create order with **2× same mrsa-medical product**
2. Customer requests cancel for **one line item only**
3. Verify partial cancel request in ops + vendor summary

**Pass criteria**

- [ ] Partial qty reflected in cancel request
- [ ] Remaining item still fulfillable

---

## C) Return scenarios — vendor dashboard (read-only)

All return seed orders start at **Shipped** with Bosta “In transit between Hubs”.

| # | Scenario label (seed) | Return status | Vendor checks |
|---|------------------------|---------------|---------------|
| C1 | `RETURN: Requested` | Requested | Return badge on list + detail |
| C2 | `RETURN: Reviewing` | Reviewing | Same |
| C3 | `RETURN: Approved` | Approved | Same |
| C4 | `RETURN: In transit` | Return in transit | Same |
| C5 | `RETURN: Received` | Received | Same; financial panel may show deductions later |

**Pass criteria (all C rows)**

- [ ] Vendor sees **Return: {status}** on order row and detail panel
- [ ] Vendor **cannot** change return status (ops only)
- [ ] RMA / warranty area shows read-only notice if applicable
- [ ] Order still visible in vendor dashboard

### C6 — Live return from customer (manual)

1. Use order at **Delivered** (or Shipped+)
2. **Customer:** Start return from My Account (if self-service enabled for delivered)
3. **Ops:** Advance return through Returns hub
4. **Vendor:** Confirm each stage appears read-only

---

## D) Customer-facing scenarios

| # | Scenario | Preconditions | Expected UX |
|---|----------|---------------|-------------|
| D1 | View order status | Any seeded fulfillment order | Customer sees ConsuCorner fulfillment summary (not raw Woo status only) |
| D2 | Cancel button visible | Confirmed / Preparing / Shipped / Out for delivery | Cancel option available |
| D3 | Cancel hidden | Delivered | No cancel; support/return path instead |
| D4 | Return after delivery | Delivered order, no open return | Return via account or support per product rules |
| D5 | Delivered = Completed | Delivered fulfillment | Customer order feels “complete” |

**Pass criteria**

- [ ] Status text matches ops fulfillment, not confusing duplicate labels
- [ ] Cancel CTA only when eligible
- [ ] Return CTA only when eligible

---

## E) Operations / admin scenarios

Test from **WooCommerce → Returns** (and order edit where applicable).

| # | Action | Steps | Expected |
|---|--------|-------|----------|
| E1 | Advance fulfillment | Load mrsa order → set Preparing → Shipped | Vendor dashboard updates on refresh |
| E2 | Bosta auto-sync | Set Bosta “Received at warehouse” on Preparing order | Fulfillment may sync to Shipped |
| E3 | Approve cancel | Pending cancel order → Approve | Order cancelled; vendor sees cancelled state |
| E4 | Reject cancel | Pending cancel → Reject | Order continues |
| E5 | Create return | Shipped+ order → manual return | Return = Requested |
| E6 | Advance return | Requested → … → Received | Vendor sees each stage |
| E7 | Resolve return | Received → Resolved + refund/wallet | Financial panel updates |

**Pass criteria**

- [ ] All actions require admin capability + nonce
- [ ] Changes logged in order notes where applicable
- [ ] Vendor dashboard reflects changes without vendor action

---

## F) Bosta integration spot checks

| # | Bosta status | State code | Expected fulfillment mapping |
|---|--------------|------------|------------------------------|
| F1 | Received at warehouse | 12 | Shipped |
| F2 | Out for delivery | 41 | Out for delivery |
| F3 | Delivered | 45 | Delivered |
| F4 | In transit between Hubs | 30 | Used on return demo orders |

**Pass criteria**

- [ ] Tracking number displays on vendor order list when meta present
- [ ] React DataViews orders table includes Bosta column (modern Dokan UI)

---

## G) Financial / earnings panel (vendor)

On any mrsa-medical order detail in vendor dashboard:

- [ ] **Items total** matches order line items
- [ ] **Platform commission** = total − net payable (approx.)
- [ ] **Return deductions** show when refunds processed
- [ ] **Net payable** shown per vendor line item state (pending / earned / deducted)

---

## H) Regression guards

| # | Check | Pass? |
|---|-------|-------|
| H1 | Order with **only** mrsa-medical products appears in **mrsa-medical** dashboard | [ ] |
| H2 | Order does **not** appear in another vendor’s dashboard | [ ] |
| H3 | Multi-vendor cart splits correctly (optional: mix mrsa + another vendor) | [ ] |
| H4 | GeIdeA / checkout not broken by workflow meta | [ ] |
| H5 | Seeded orders have row in `dokan_orders` (seller_id = mrsa user ID) | [ ] |

---

## Manual order creation (no seed script)

If you prefer placing orders through the storefront:

1. Log in as **customer**
2. Add products from store **mrsa-medical** only (filter by vendor store page if needed)
3. Checkout with **Cash on delivery**
4. Log in as **ops** → advance fulfillment in Returns hub
5. Log in as **mrsa-medical** → confirm order appears at `/dashboard/orders/`

> **Important:** Storefront checkout auto-syncs Dokan. Programmatic/admin-created orders need the seed script (or manual Dokan sync) to appear in the vendor dashboard.

---

## Test session log (fill during live QA)

| Date | Tester | Scenario IDs | Order # / ID | Pass / Fail | Notes |
|------|--------|--------------|--------------|-------------|-------|
| | | A1 | | | |
| | | A2 | | | |
| | | A3 | | | |
| | | A4 | | | |
| | | A5 | | | |
| | | B1 | | | |
| | | B2 | | | |
| | | C1–C5 | | | |
| | | D1–D5 | | | |
| | | E1–E7 | | | |

---

## Related files

| File | Purpose |
|------|---------|
| `tests/seed-mrsa-medical-orders.php` | Creates mrsa-only demo orders + Dokan sync |
| `tests/run-seed-mrsa-medical-orders.php` | Standalone runner (no WP-CLI) |
| `tests/seed-scenario-orders.php` | Full scenario set (any vendor product) |
| `inc/order-return-workflow.php` | Fulfillment + vendor dashboard UI |
| `inc/order-cancel-requests.php` | Cancel request flow |
| `inc/returns-report.php` | Ops Returns admin hub |

---

## Known notes

- Demo orders are tagged `_cc_demo_vendor = mrsa-medical` — safe to delete after QA
- Seed script does **not** fully cancel orders (cancel stays pending/rejected only)
- Vendor workflow is **read-only by design**; all status changes go through operations
- Re-run seed script to **repair** older demo orders missing from Dokan + create fresh scenarios
