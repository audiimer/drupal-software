<?php

namespace Drupal\lts_software\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Preprocessing for the Software Library catalog view.
 *
 * Extracted from the playground theme (playground.theme) so this catalog
 * functionality survives a theme swap (playground -> Lehigh LTS). Only the
 * software_library_search View is affected; all other views are untouched.
 *
 * D11 note: procedural preprocess hooks did not reliably register for
 * this project's custom modules, so these are written as OOP #[Hook]
 * attribute methods, consistent with lts_chargeback's hook classes.
 *
 * This class also computes the role-based audience tab visibility (which
 * tabs a given user sees) and the card filter-attribute data (category/OS),
 * so the Twig templates only render pre-computed data rather than making
 * access-control decisions or looping over entity fields themselves.
 *
 * POC NOTE: the tab definitions (label/path/roles) are hardcoded here.
 * This was flagged early in the project as a "Field Group refactor of
 * hardcoded Twig tabs" deferred item — moving the hardcoding from Twig to
 * PHP is a real improvement (testable, no entity/access logic in templates)
 * but the tab list itself should eventually become configuration (e.g. a
 * taxonomy or a config entity) rather than a literal array in code, so WMS
 * can edit tabs without a code deploy.
 */
class CatalogViewsPreprocessHooks {

  /**
   * The audience tab definitions: label, path suffix, url-match string, and
   * which roles may see the tab. 'administrator' always sees every tab.
   */
  protected const TABS = [
    [
      'label' => 'Students',
      'path' => '/software-library-new/Students',
      'match' => 'Students',
      'roles' => ['student', 'lts'],
    ],
    [
      'label' => 'Faculty/Staff',
      'path' => '/software-library-new/Faculty%20Staff',
      'match' => 'Faculty',
      'roles' => ['faculty_staff', 'lts'],
    ],
    [
      'label' => 'LTS',
      'path' => '/software-library-new/LTS',
      'match' => 'LTS',
      'roles' => ['lts'],
    ],
    [
      'label' => 'Public Sites',
      'path' => '/software-library-new/Public%20Sites',
      'match' => 'Public',
      'roles' => ['student', 'faculty_staff', 'lts'],
    ],
    [
      'label' => 'HPC',
      'path' => '/software-library-new/HPC',
      'match' => 'HPC',
      'roles' => ['student', 'faculty_staff', 'lts'],
    ],
    [
      'label' => 'LUapps',
      'path' => '/software-library-new/LUapps',
      'match' => 'LUapps',
      'roles' => ['student', 'faculty_staff', 'lts'],
    ],
  ];

  /**
   * Attaches the catalog library and exposes the visible tabs to the
   * page template, already filtered by the current user's roles.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    if ($variables['view']->id() !== 'software_library_search') {
      return;
    }
    $variables['#attached']['library'][] = 'lts_software/software-library';

    $user_roles = \Drupal::currentUser()->getRoles();
    $variables['user_roles'] = $user_roles;
    $variables['visible_tabs'] = $this->buildVisibleTabs($user_roles);
  }

  /**
   * Builds the list of tabs the current user is allowed to see, with an
   * 'active' flag set for whichever tab matches the current URL.
   *
   * @param string[] $user_roles
   *   The current user's role machine names.
   *
   * @return array
   *   A list of ['label' => ..., 'path' => ..., 'active' => bool].
   */
  protected function buildVisibleTabs(array $user_roles): array {
    $current_path = \Drupal::request()->getRequestUri();
    $is_admin = in_array('administrator', $user_roles, TRUE);

    $tabs = [];
    foreach (self::TABS as $tab) {
      $has_access = $is_admin || !empty(array_intersect($tab['roles'], $user_roles));
      if (!$has_access) {
        continue;
      }
      $tabs[] = [
        'label' => $tab['label'],
        'path' => $tab['path'],
        'active' => str_contains($current_path, $tab['match']),
      ];
    }
    return $tabs;
  }

  /**
   * Exposes formatted price, add-to-cart form, and card filter-attribute
   * data for each catalog row.
   *
   * Looks up the Commerce product variation matching the row's Software
   * node (via field_software), and builds:
   * - software_price: rendered price (Free if $0, via commerce_price_free).
   * - add_to_cart_form: the add-to-cart form for that variation.
   * - card_data_category: comma-joined category labels, for the sidebar's
   *   client-side filter (data-category attribute on the card).
   * - card_data_os: comma-joined "available for" values, same purpose
   *   (data-os attribute).
   *
   * POC NOTE: assumes exactly one active variation per software node
   * (range(0,1)). If a node ever needs multiple purchasable variations,
   * this will need a variation picker instead of a single lookup.
   */
  #[Hook('preprocess_views_view_fields')]
  public function preprocessViewsViewFields(array &$variables): void {
    if ($variables['view']->id() !== 'software_library_search') {
      return;
    }

    $row = $variables['row'];
    $node = $row->_entity ?? NULL;
    if (!$node || $node->bundle() !== 'software') {
      return;
    }

    $variables['card_data_category'] = $this->extractTermLabels($node, 'field_category');
    $variables['card_data_os'] = $this->extractOsValues($node, 'field_available_for');
    $variables['card_access_type'] = $node->hasField('field_access_type')
      ? $node->get('field_access_type')->value
      : NULL;

    // Downloads never use the Commerce add-to-cart form. Instead the
    // card renders the same installer modal as the detail page, built
    // by the shared lts_download.installer_entries_builder service so
    // the two never drift apart.
    if ($variables['card_access_type'] === 'download') {
      $variables['installer_modal'] = \Drupal::service('lts_download.installer_entries_builder')
        ->buildRenderArray($node);
      return;
    }

    if (!\Drupal::moduleHandler()->moduleExists('commerce_product')) {
      return;
    }

    $variation_storage = \Drupal::entityTypeManager()
      ->getStorage('commerce_product_variation');
    $vids = $variation_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_software', $node->id())
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();
    if (empty($vids)) {
      return;
    }
    $variation = $variation_storage->load(reset($vids));
    if (!$variation) {
      return;
    }
    $product = $variation->getProduct();
    if (!$product) {
      return;
    }

    // Formatted price; commerce_price_free renders zero as "Free".
    $variables['software_price'] = $variation->get('price')->view([
      'label' => 'hidden',
      'type' => 'commerce_price_free',
      'settings' => [],
    ]);

    // Add-to-cart form only (no other product fields rendered here).
    $variables['add_to_cart_form'] = \Drupal::service('entity.form_builder')
      ->getForm(
        \Drupal::entityTypeManager()
          ->getStorage('commerce_order_item')
          ->create([
            'type' => 'default',
            'purchased_entity' => $variation,
            'quantity' => 1,
          ]),
        'add_to_cart',
        ['product' => $product]
      );
  }

  /**
   * Returns a comma-joined, lowercased list of referenced term labels.
   */
  protected function extractTermLabels($node, string $field_name): string {
    if (!$node->hasField($field_name)) {
      return '';
    }
    $values = [];
    foreach ($node->get($field_name) as $item) {
      if ($item->entity) {
        $values[] = strtolower($item->entity->label());
      }
    }
    return implode(',', $values);
  }

  /**
   * Returns a comma-joined, lowercased, space-normalized list of a list
   * field's raw values (e.g. field_available_for's machine-name options).
   */
  protected function extractOsValues($node, string $field_name): string {
    if (!$node->hasField($field_name)) {
      return '';
    }
    $values = [];
    foreach ($node->get($field_name) as $item) {
      $values[] = str_replace('_', ' ', strtolower($item->value));
    }
    return implode(',', $values);
  }

}
