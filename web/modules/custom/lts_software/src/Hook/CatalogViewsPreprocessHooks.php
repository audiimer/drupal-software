<?php

namespace Drupal\lts_software\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\lts_software\Plugin\Block\SoftwarePriceBlock;

/**
 * Preprocessing for the Software Library catalog view.
 *
 * Extracted from the playground theme (playground.theme) so this catalog
 * functionality survives a theme swap (playground -> Lehigh LTS). Only the
 * software_library_search View is affected; all other views are untouched.
 *
 * D11 note: procedural preprocess hooks did not reliably register for
 * this project's custom modules, so these are written as OOP #[Hook]
 * attribute methods.
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
   * Exposes price, call-to-action, and card filter-attribute data for each
   * catalog row.
   *
   * Builds:
   * - card_data_category: comma-joined category labels, for the sidebar's
   *   client-side filter (data-category attribute on the card).
   * - card_data_os: comma-joined "available for" values, same purpose
   *   (data-os attribute).
   * - installer_modal: download software only (lts_download).
   * - software_price: chargeback software only; the node's field_price
   *   ("Free" if 0), formatted the same way as SoftwarePriceBlock.
   * - request_link: chargeback software only; the node's field_request_form
   *   rendered as a link to the Software request webform.
   * - request_unavailable: set instead of request_link when the title has a
   *   Request form but the current user cannot create submissions (e.g.
   *   anonymous). Holds only cache metadata, so the card shows no button.
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

    // Downloads render the same installer modal as the detail page, built
    // by the shared lts_download.installer_entries_builder service so the
    // two never drift apart.
    if ($variables['card_access_type'] === 'download') {
      $variables['installer_modal'] = \Drupal::service('lts_download.installer_entries_builder')
        ->buildRenderArray($node);
      return;
    }

    if ($variables['card_access_type'] !== 'chargeback') {
      return;
    }

    $variables['software_price'] = SoftwarePriceBlock::formatPrice($node);

    if ($node->hasField('field_request_form') && !$node->get('field_request_form')->isEmpty()) {
      $build = $node->get('field_request_form')->view([
        'label' => 'hidden',
        'type' => 'webform_entity_reference_link',
        'settings' => [
          'label' => 'Request',
          'attributes' => [
            'class' => ['btn', 'btn-primary', 'software-cta'],
          ],
        ],
      ]);
      // The formatter skips the link when the user cannot create
      // submissions, but the build still carries its cache metadata.
      if (Element::children($build)) {
        $variables['request_link'] = $build;
      }
      else {
        $variables['request_unavailable'] = ['#cache' => $build['#cache'] ?? []];
      }
    }
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
