# LTS Chargeback

Adds a "Chargeback Information" checkout pane and supporting order fields
to Drupal Commerce, enabling index-based billing for Lehigh University
Software Library purchases.

## What it does

When a customer adds software with `field_access_type = 'chargeback'` to
their cart and proceeds to checkout, this module:

1. Conditionally displays a checkout pane requesting:
   - Authorized requester name
   - Lehigh email address (validated against @lehigh.edu)
   - Department
   - Banner index number (basic format validation)
   - Authorized signer
2. Stores submitted values directly on the `commerce_order` entity via
   base fields defined in `lts_chargeback.module`.
3. Renders a read-only summary of the captured data on the checkout
   review step and order confirmation page.

The pane is automatically hidden for orders that contain no chargeback
items (e.g. download-only orders).

## Why a custom module instead of Webform + commerce_webform_order

This was a deliberate architectural decision. We considered two paths:

**Option A (chosen): Custom Commerce checkout pane plugin**
**Option B (rejected): Webform module + commerce_webform_order contrib**

We rejected Option B for the following reasons:

1. **Data locality.** Webform submissions are a separate entity from the
   order. Querying "which orders need fulfillment, and what index do
   they bill to?" would require joining `commerce_order` against
   `webform_submission`, a fragile pattern. With this module, chargeback
   fields live directly on the order entity and are queryable in one hop.

2. **Smaller dependency footprint.** `commerce_webform_order` is a
   smaller contrib module with fewer maintainers. Adding it would
   create a long-term risk if the maintainer stops releasing updates,
   particularly across Drupal major versions. A custom module of ~250
   lines is fully under Lehigh's control.

3. **Configuration vs. code.** A Webform-based implementation spreads
   business logic across Webform config exports, commerce_webform_order
   configuration, and any custom glue PHP. The Drupal core/Commerce
   patterns used here are well-documented and immediately recognizable
   to any Commerce developer inheriting this codebase.

4. **Type safety and validation.** The validation logic (index format,
   @lehigh.edu email enforcement) lives in versioned PHP rather than
   Webform field settings that an editor could accidentally change.

5. **Maintenance burden.** Both approaches require maintenance. Option A
   only needs attention on Drupal/Commerce major bumps every few years.
   Option B requires watching Webform releases continuously and
   testing the commerce_webform_order integration after each.

## Files

- `lts_chargeback.info.yml` — module metadata
- `lts_chargeback.module` — defines order base fields via
  `hook_entity_base_field_info()`
- `src/Plugin/Commerce/CheckoutPane/ChargebackInformation.php` — the
  checkout pane plugin

## After enabling

1. Enable the module: `drush en lts_chargeback`
2. Apply pending entity schema updates: `drush updb`
3. Add the pane to the checkout flow at
   `/admin/commerce/config/checkout-flows`

## Future work

- Email notification to LTS on chargeback order placement (use a
  `commerce_order` event subscriber).
- Order admin view filter by department/index for fulfillment reporting.
- Export chargeback orders to CSV for Banner reconciliation.