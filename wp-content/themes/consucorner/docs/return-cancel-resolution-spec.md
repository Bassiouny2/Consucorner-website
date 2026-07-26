# Return, Cancel & Resolution Status — Locked Specification

**Version:** 1.0  
**Date:** 21 July 2026  
**Status:** Approved for implementation  
**Audience:** Operations, Product, Development  
**Related:** [order-vendor-bosta-status-plan.md](./order-vendor-bosta-status-plan.md)

---

## 1. Executive summary

This document locks business rules for **cancellation**, **returns**, **Bosta sync**, and the vendor dashboard **Resolution status** column on ConsuCorner (WooCommerce + Dokan + Bosta).

**Core principles:**

- **One Bosta shipment per vendor** (Dokan sub-order), not one shipment for the whole parent order.
- **Multi-item orders** (2+ line items or 2+ different products): cancel/return **full order only**, **all units**.
- **Cancel** before pickup; **return only** after ship; **self-service return** after delivery.
- **Simplified return flow** for completed orders: `Requested → Return in transit → Wallet / Direct`.
- **Bosta “Returned to business”** auto-confirms **Cancelled** and reflects on the vendor dashboard.
- Vendor dashboard shows **two columns**: raw **Bosta status** + derived **Resolution status**.

---

## 2. Locked business decisions

| Topic | Decision |
|--------|----------|
| Shipping model | **One Bosta shipment per vendor** (Dokan sub-order) |
| Multi-item rule | **2+ line items OR 2+ different products** → full order only |
| Quantity rule | **All units** on every line (no partial quantity) |
| Before pickup | **Cancel only** (full order) |
| Shipped / Out for delivery | **Return only** (no cancel) |
| After Delivered | **Customer self-service return** |
| Partial cancel | Only **before pickup requested** (see §4) |
| Ops completes return | WooCommerce status → **`refunded`** |
| Bosta terminal (cancelled/returned) | **Auto-apply** to fulfillment, WC, and vendor dashboard |
| Bosta “Returned to business” | **Auto-cancelled** (confirmed via live orders screenshot) |
| Preparing starts | When Bosta = **Pickup requested** (state codes **21**, **22**) |
| Vendor columns | **Bosta status** (raw) + **Resolution status** (derived) |
| Return resolution | **Wallet** or **Direct** (manual Geidea — no auto gateway reversal) |

---

## 3. Status layers (unchanged architecture)

```
┌─────────────────────────────────────────────────────────────────┐
│  Layer 1 — Customer status (My Account / Profile)               │
│  Simple labels derived from fulfillment aggregate               │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│  Layer 2 — Operations fulfillment (per vendor)                  │
│  confirmed → preparing → shipped → out_for_delivery → delivered │
│  cancelled (terminal)                                           │
│  Meta: _cc_ops_fulfillment[vendor_id] on parent order           │
└────────────────────────────┬────────────────────────────────────┘
                             │
┌────────────────────────────▼────────────────────────────────────┐
│  Layer 3 — Bosta raw (per vendor sub-order)                     │
│  bosta_status, bosta_state_code, bosta_tracking_number          │
│  Source: Bosta webhook → Dokan sub-order                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Order lifecycle timeline

### 4.1 Fulfillment progression

| Key | Label | Bosta trigger |
|-----|-------|---------------|
| `confirmed` | Confirmed | Order placed; before pickup |
| `preparing` | Preparing | **Pickup requested** (codes 21, 22) |
| `shipped` | Shipped | In transit / received at warehouse (20, 30, 31) |
| `out_for_delivery` | Out for delivery | Code 41 |
| `delivered` | Delivered | Codes 45, 46 |
| `cancelled` | Cancelled | Bosta terminal / ops cancel / Returned to business |

**Allowed forward path:** Confirmed → Preparing → Shipped → Out for delivery → Delivered  
**Bosta sync:** forward-only (no automatic downgrade except terminal → cancelled).

### 4.2 Customer actions by stage

| Fulfillment | Customer can | Notes |
|-------------|--------------|-------|
| **Confirmed** | Cancel **full order** | Only reliable cancel window |
| **Preparing** | See §4.3 | Pickup requested = Preparing starts |
| **Shipped / Out for delivery** | **Return full order** only | No cancel |
| **Delivered / Completed** | **Self-service return** (full order if multi-item) | Simplified 3-step return flow (§6) |

### 4.3 Open decision — cancel during Preparing

| Option | Rule |
|--------|------|
| **A (recommended)** | Cancel only while **Confirmed**. Preparing (pickup requested) closes cancel. |
| **B** | Cancel in Confirmed + Preparing until Bosta confirms physical pickup (extra state required). |

**Default for implementation:** **Option A** unless operations requests Option B.

---

## 5. Cancel rules

### 5.1 Multi-item orders

When the order has **2+ line items** OR **2+ different products**:

- Customer and admin: **full order only**.
- UI: hide per-item checkboxes; force `whole_order = true`.
- Backend: reject partial `item_quantities` in `Consucorner_Order_Cancel_Requests::create_request()`.

### 5.2 Single line item, single product

- Still **all units** — quantity must equal the full line quantity.
- No partial quantity selector (display fixed full qty only).

### 5.3 Partial cancel (legacy)

Current code supports item-level partial cancel. Under this spec:

- Partial cancel is only meaningful **before pickup requested**.
- Combined with “all units” and multi-item full-order rules, **production effectively has no partial cancel**.
- Remove or hard-block partial cancel in customer profile and ops UI.

### 5.4 Ops approval side effects

| Scenario | WooCommerce status | Fulfillment |
|----------|-------------------|-------------|
| Approve **whole-order** cancel | `cancelled` | Affected vendors → `cancelled` |
| All vendor sub-orders cancelled | Parent → `cancelled` | — |

---

## 6. Return flow (simplified)

### 6.1 Post-delivery refund path

For **completed / delivered** orders requiring refund:

```
Requested  →  Return in transit  →  Wallet / Direct
```

| Step | Who | System action |
|------|-----|----------------|
| **Requested** | Customer (self-service) or Operations | Create Dokan RMA; workflow status = `requested` |
| **Return in transit** | Operations | Physical return / Bosta return leg in progress |
| **Wallet / Direct** | Operations | `CC_Returns_Refund_Service::resolve_to_wallet()` or direct refund; WC → **`refunded`**; vendor ledger updated |

### 6.2 Pre-delivery return (Shipped / Out for delivery)

Same 3-step flow. Resolution may end as **`cancelled`** (not `refunded`) if the order was never delivered.

### 6.3 Statuses to use (return workflow)

| Key | Label | Next actions (ops) |
|-----|-------|-------------------|
| `requested` | Requested | → Return in transit, Reject |
| `return_in_transit` | Return in transit | → Wallet, Direct |
| `rejected` | Rejected | Terminal |

**Deprecate in ops UI** (collapse into above): `reviewing`, `approved`, `received`, `resolved` as separate manual steps.

### 6.4 Vendor visibility

- Vendors see return/cancel state **read-only** (existing design).
- Operations owns all status changes.

---

## 7. Bosta integration

### 7.1 Per-vendor shipment model

**Current gap:** Bosta meta on parent order syncs **all vendors** together.

**Target model:**

```
Parent Order #45541930
├── Sub-order Vendor A #45541931  → bosta_status, bosta_tracking, bosta_state_code
├── Sub-order Vendor B #45541932  → own Bosta meta
└── Sub-order Vendor C #45541933  → own Bosta meta

Parent order meta:
└── _cc_ops_fulfillment[vendor_id]  (already per-vendor)
```

| Area | Required change |
|------|-----------------|
| Bosta webhook / sync | Attach shipment to **Dokan sub-order ID** |
| `resolve_bosta_source_order()` | Read Bosta from **current sub-order**, not parent |
| `maybe_sync_fulfillment_from_bosta()` | Update **only that vendor’s** fulfillment |
| Vendor dashboard | Each vendor sees **their** Bosta + Resolution |
| Customer view | Aggregate slowest vendor (phase 1); per-vendor bar (phase 2) |

### 7.2 Bosta → fulfillment mapping

| Bosta status / codes | Fulfillment |
|----------------------|-------------|
| Created | `confirmed` (10, 11) |
| **Pickup requested** | **`preparing`** (21, 22) |
| In transit / warehouse / picked up | `shipped` (20, 30, 31) |
| Out for delivery | `out_for_delivery` (41) |
| Delivered | `delivered` (45, 46) |
| Terminated / Returned to business / failed | `cancelled` (48, 100, 101, 104 + text match) |

### 7.3 “Returned to business” (live reference)

Observed on production admin orders:

| Order | WC status | Bosta text | Sync |
|-------|-----------|------------|------|
| 7027138372 | Completed | Delivered | SYNCED |
| 8151557897 | Cancelled | **Returned to business** | SYNCED |

**Required automation when Bosta = `Returned to business`:**

1. Match text: `returned to business` (case-insensitive) **and** state codes 48, 100, 101, 104.
2. Fulfillment (**that vendor only**): → `cancelled`.
3. WC status (**sub-order**): → `cancelled`.
4. Resolution status (vendor dashboard): → **Cancelled**.
5. Order note: log auto-sync from Bosta.

**Code gap:** `map_bosta_text_to_fulfillment()` matches `returned to sender` but not `returned to business` — must add.

### 7.4 Bosta → WooCommerce status (auto)

| Event | Sub-order WC status | Parent order |
|-------|---------------------|--------------|
| Delivered (45, 46) | `completed` (optional — confirm with ops) | Aggregate when all vendors delivered |
| Returned to business / terminal fail | `cancelled` | `cancelled` when all vendors terminal |
| Ops return resolved (Wallet/Direct) | `refunded` | `refunded` when all vendors resolved |

> Geidea is **not** auto-reversed. Wallet/direct remains manual operations.

---

## 8. Resolution status column (vendor dashboard)

### 8.1 Display

Two columns side by side in Dokan vendor orders (classic table + React DataViews):

| Column | Content |
|--------|---------|
| **Bosta status** | Raw `bosta_status` + tracking number |
| **Resolution status** | Derived label from fulfillment + requests + WC status |

### 8.2 Resolution values (priority order)

| Priority | Condition | Label |
|----------|-----------|-------|
| 1 | Bosta = `Returned to business` | **Cancelled** |
| 2 | WC = `refunded` or return resolved (Wallet/Direct) | **Refunded** |
| 3 | WC = `cancelled` or fulfillment = `cancelled` | **Cancelled** |
| 4 | Open cancel request | **Cancel requested** |
| 5 | Return = `return_in_transit` | **Return in transit** |
| 6 | Return = `requested` | **Return requested** |
| 7 | No open request | Mirror fulfillment: Confirmed / Preparing / Shipped / Out for delivery / Delivered |

### 8.3 Example pairs

| Bosta status | Resolution status |
|--------------|-------------------|
| Delivered | Delivered |
| Returned to business | Cancelled |
| Out for delivery | Return requested *(if open)* |
| Pickup requested | Preparing |

### 8.4 REST / React fields

Extend Dokan vendor order REST payload:

- `bosta_status`
- `bosta_tracking_number`
- `cc_fulfillment_status` / `cc_fulfillment_label`
- `cc_resolution_status` / `cc_resolution_label` *(new)*

Hook: `dokan_rest_prepare_shop_order_object`  
Script: `assets/js/dokan-orders-bosta-column.js` — add second column.

---

## 9. WooCommerce status automation summary

| Event | WC status (sub-order) | Parent |
|-------|----------------------|--------|
| Ops approves full cancel (pre-shipment) | `cancelled` | `cancelled` when all vendors cancelled |
| Ops completes return (Wallet/Direct) | `refunded` | `refunded` when all resolved |
| Bosta Returned to business | `cancelled` | aggregate |
| Bosta Delivered | `completed` *(if enabled)* | aggregate |

---

## 10. Implementation map

| File | Changes |
|------|---------|
| `inc/order-return-workflow.php` | `returned to business` text map; per-vendor Bosta sync; tighten cancel/return eligibility; simplified return statuses; auto WC hooks; `get_resolution_status()` helper |
| `inc/order-cancel-requests.php` | Full-order validation; block partial after pickup; cancel only in `confirmed` (Option A) |
| `inc/returns-refund-service.php` | On resolve → set WC `refunded` |
| `inc/returns-report.php` | Ops UI: 3-step return flow only |
| `inc/profile-account.php` | Payload: full-order gates, cancel vs return by stage |
| `assets/js/profile.js` | Hide item checkboxes when multi-item; full-order only |
| `assets/js/dokan-orders-bosta-column.js` | Add **Resolution status** column |
| Bosta webhook / theme bridge | Map webhook → Dokan sub-order per vendor |
| `tests/MRSA-MEDICAL-LIVE-TEST-SCENARIOS.md` | Update scenarios for new rules |

---

## 11. Build phases

| Phase | Scope | Effort estimate |
|-------|--------|-----------------|
| **1 — Rules** | Full-order-only, cancel/return gates, simplified return flow, WC `refunded` on resolve | 3–5 days |
| **2 — Bosta terminal** | `Returned to business` mapping, auto cancel, Resolution column | 2–3 days |
| **3 — Per-vendor Bosta** | Sub-order meta, webhook routing, per-vendor sync | 3–5 days |
| **4 — UX & QA** | Customer profile, vendor React column, MRSA test doc | 2–3 days |

---

## 12. Test scenarios (high level)

### Cancel

- [ ] Multi-item order: customer can only submit **whole order** cancel.
- [ ] Single-item order: cancel requires **all units**.
- [ ] Cancel blocked at Shipped / Out for delivery.
- [ ] Ops approve cancel → WC `cancelled`, vendor Resolution = **Cancelled**.

### Return

- [ ] Delivered order: customer self-service return → **Requested**.
- [ ] Ops: Requested → Return in transit → Wallet/Direct → WC **`refunded`**.
- [ ] Multi-item: return request is **full order only**.

### Bosta

- [ ] Pickup requested → fulfillment **Preparing** (vendor-scoped after phase 3).
- [ ] Returned to business → auto **Cancelled** on vendor dashboard + WC.
- [ ] Delivered → fulfillment Delivered; WC Completed per policy.

### Vendor dashboard

- [ ] **Bosta status** column shows raw text + tracking.
- [ ] **Resolution status** column shows derived label.
- [ ] Vendor cannot mutate workflow (read-only).

---

## 13. Non-goals (explicit)

| Item | Reason |
|------|--------|
| Auto Geidea refund | Manual ops / wallet |
| Auto bank payout to vendor | Contract is manual transfer |
| Auto Bosta shipment cancel API | Future phase |
| Customer sees raw Bosta terminology | UX — fulfillment labels only |

---

## 14. Glossary

| Term | Meaning |
|------|---------|
| Parent order | Customer’s main WooCommerce order |
| Sub-order | Dokan order per vendor |
| Fulfillment | Operations status per vendor (`_cc_ops_fulfillment`) |
| Resolution status | Vendor-facing derived label (cancel/return/refund state) |
| Returned to business | Bosta terminal status — physical return to merchant |
| Wallet / Direct | Ops refund routes via `CC_Returns_Refund_Service` |

---

## 15. Revision history

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| 1.0 | 2026-07-21 | Product + Dev | Locked from stakeholder Q&A session |
