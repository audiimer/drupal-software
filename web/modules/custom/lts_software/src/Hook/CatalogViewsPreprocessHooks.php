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
 */
class CatalogViewsPreprocessHooks {

  /**
   * Attaches the catalog library and exposes user roles to the view template.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    if ($variables['view']->id() !== 'software_library_search') {
      return;
    }
    $variables['#attached']['library'][] = 'playground/software-library';
    $variables['user_roles'] = \Drupal::currentUser()->getRoles();
  }

  /**
   * Exposes formatted price and an add-to-cart form for each catalog row.
   *
   * Looks up the Commerce product variation matching the row's Software
   * node (via field_software), and builds:
   * - software_price: rendered price (Free if $0, via commerce_price_free).
   * - add_to_cart_form: the add-to-cart form for that variation.
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
    if (!\Drupal::moduleHandler()->moduleExists('commerce_product')) {
      return;
    }
    $row = $variables['row'];
    if (empty($row->_entity) || $row->_entity->bundle() !== 'software') {
      return;
    }
    $node = $row->_entity;

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

}
