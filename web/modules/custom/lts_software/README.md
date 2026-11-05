# LTS Software Module

## Purpose

This module provides custom display logic for the LTS software library
detail pages and catalog cards. Paid (chargeback) software is requested
through the Software request webform (`field_request_form`), not here.

## Provided blocks

### SoftwareCtaBlock

Renders the call-to-action button for a software node based on
`field_access_type`:

- `download` → installer modal (built by `lts_download`)
- `redirect` → External link button using `field_redirect_button` (Link field)
- `chargeback` → nothing; the Request button is the `field_request_form`
  field block placed next to this block in Layout Builder

Placed once via Layout Builder; works on all software nodes.

### SoftwarePriceBlock

Shows `field_price` for chargeback software ("Free" when 0). Other
access types render nothing.

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

## Dependencies

- `drupal:node` — works with node entities
- `drupal:layout_builder` — blocks placed via Layout Builder
- `drupal:views` — catalog view templates and preprocessing

## Related modules

- `lts_download` — installer modal and download logging