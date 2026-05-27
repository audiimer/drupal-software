# LTS Software Module

## Purpose

This module provides custom display logic for the LTS software library
detail pages. It does NOT handle the Commerce checkout flow (that lives
in `lts_chargeback`); it only handles what an unauthenticated visitor
sees when they land on a software node.

## Provided blocks

### SoftwareCtaBlock

Renders the call-to-action button for a software node based on
`field_access_type`:

- `chargeback` → Commerce Add to Cart form (chargeback pane appears at checkout)
- `download` → Commerce Add to Cart form (no chargeback pane at checkout)
- `redirect` → External link button using `field_redirect_button` (Link field)

Placed once via Layout Builder; works on all software nodes.

### SoftwareTabsBlock

Renders Description / Terms of Use / System Specs as Bootstrap nav-tabs.
Reads from the current node's `body`, `field_terms_of_use`, and
`field_specs` fields.

Placed once via Layout Builder; works on all software nodes.

## Architectural decisions

### Why custom blocks instead of Field Group?

Custom blocks live in code (deployable via git); Field Group's Layout
Builder integration stores tab structure in entity view display config
(database). Since this project's config sync directory is currently
inside the gitignored `files/` folder, code-based blocks are the only
way to make this layout deployment-portable today.

Custom blocks are also independent of Layout Builder lifecycle — they
will continue to work if/when Layout Builder is succeeded by Experience
Builder or any other rendering system.

### Why a separate module instead of extending `lts_chargeback`?

`lts_chargeback` is focused: Commerce checkout pane + chargeback order
fields. The blocks here serve all three access types (only one of which
is chargeback), so they don't belong inside a chargeback-named module.
Keeping them separate means each module's name accurately describes its
contents, and the dev team can disable/enable them independently.

## Dependencies

- `drupal:node` — works with node entities
- `drupal:layout_builder` — blocks placed via Layout Builder
- `commerce:commerce_product` — CTA loads Commerce product variations

## Related modules

- `lts_chargeback` — Commerce checkout pane for chargeback orders