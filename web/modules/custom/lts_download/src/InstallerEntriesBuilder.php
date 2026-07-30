<?php

namespace Drupal\lts_download;

use Drupal\node\NodeInterface;

/**
 * Builds the installer entry list for a Software node's download modal.
 *
 * Extracted as a shared service so the detail-page CTA
 * (SoftwareCtaBlock) and the catalog card (CatalogViewsPreprocessHooks)
 * both build the modal from one source of truth, rather than duplicating
 * the field-reading logic in two places.
 */
class InstallerEntriesBuilder {

  /**
   * Builds the flat list of installer entries for a node's modal.
   *
   * @return array
   *   List of ['id', 'os', 'variant', 'filename'], or an empty array if
   *   the node has no field, is empty, or is an unsaved/sample entity.
   */
  public function build(NodeInterface $node): array {
    if (!$node->id()) {
      // Unsaved/sample entity (e.g. Layout Builder preview) -- see
      // design doc §6.4.
      return [];
    }

    if (!$node->hasField('field_installer_files') || $node->get('field_installer_files')->isEmpty()) {
      return [];
    }

    $entries = [];
    foreach ($node->get('field_installer_files')->referencedEntities() as $installer) {
      if ($installer->get('field_installer_file')->isEmpty()) {
        continue;
      }
      $file = $installer->get('field_installer_file')->entity;
      if (!$file) {
        continue;
      }
      $variant = NULL;
      if (!$installer->get('field_installer_variant')->isEmpty()) {
        $term = $installer->get('field_installer_variant')->entity;
        $variant = $term ? $term->label() : NULL;
      }
      $entries[] = [
        'id' => $installer->id(),
        'os' => $installer->get('field_installer_os')->value,
        'variant' => $variant,
        'filename' => $file->getFilename(),
      ];
    }

    return $entries;
  }

  /**
   * Builds the full modal render array for a node, or [] if no entries.
   */
  public function buildRenderArray(NodeInterface $node): array {
    $entries = $this->build($node);
    if (empty($entries)) {
      return [];
    }

    return [
      '#theme' => 'lts_installer_modal',
      '#node_id' => $node->id(),
      '#entries' => $entries,
      '#attached' => [
        'library' => ['lts_download/installer_modal'],
      ],
      '#cache' => [
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

}
