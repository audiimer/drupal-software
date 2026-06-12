<?php

namespace Drupal\lts_chargeback\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Renders the download reveal on completed download orders.
 *
 * POC NOTE: Uses field_instructions_url as the download/instructions link.
 * In production, a dedicated file field on the order item would be used.
 */
class DownloadRevealHooks {

  #[Hook('entity_view')]
  public function entityView(array &$build, $entity, $display, $view_mode): void {
    if ($entity->getEntityTypeId() !== 'commerce_order') {
      return;
    }
    if ($view_mode !== 'user') {
      return;
    }
    if ($entity->bundle() !== 'download') {
      return;
    }
    if ($entity->getState()->getId() !== 'completed') {
      return;
    }
    $node = $this->orderSoftwareNode($entity);
    if (!$node) {
      return;
    }
    $build['lts_download_reveal'] = $this->buildReveal($entity, $node);
    $build['lts_download_reveal']['#weight'] = -50;
  }

  protected function buildReveal($order, $node): array {
    $rows = [];
    if ($node->hasField('field_license_details') && !$node->get('field_license_details')->isEmpty()) {
      foreach ($node->get('field_license_details') as $item) {
        $paragraph = $item->entity;
        if (!$paragraph) {
          continue;
        }
        $label = $paragraph->hasField('field_label') ? $paragraph->get('field_label')->value : '';
        $value = $paragraph->hasField('field_value') ? $paragraph->get('field_value')->value : '';
        if ($label || $value) {
          $rows[] = ['label' => $label, 'value' => $value];
        }
      }
    }
    $instructions_link = NULL;
    if ($node->hasField('field_instructions_url') && !$node->get('field_instructions_url')->isEmpty()) {
      $item = $node->get('field_instructions_url')->first();
      try {
        $url = Url::fromUri($item->uri);
        $instructions_link = [
          'url' => $url->toString(),
          'title' => $item->title ?: 'Installation Guide',
        ];
      }
      catch (\Exception $e) {
        $instructions_link = NULL;
      }
    }
    return [
      '#theme' => 'lts_download_reveal',
      '#product_label' => $node->label(),
      '#rows' => $rows,
      '#installer_modal' => $this->renderInstallerModal($node),
      '#instructions_link' => $instructions_link,
      '#cache' => ['tags' => $order->getCacheTags()],
    ];
  }

  protected function orderSoftwareNode($order) {
    foreach ($order->getItems() as $order_item) {
      $variation = $order_item->getPurchasedEntity();
      if (!$variation || !$variation->hasField('field_software')) {
        continue;
      }
      $software = $variation->get('field_software')->entity;
      if ($software) {
        return $software;
      }
    }
    return NULL;
  }


  /**
   * Renders the installer_modal paragraph for the given software node.
   *
   * PORT ITEM: playground/installer_modal library lives in the playground
   * theme. WMS must port this library to their theme before production.
   */
  protected function renderInstallerModal($node): array {
    if (!$node->hasField('field_installer_modal') || $node->get('field_installer_modal')->isEmpty()) {
      return [];
    }
    $paragraph = $node->get('field_installer_modal')->entity;
    if (!$paragraph) {
      return [];
    }
    return \Drupal::entityTypeManager()
      ->getViewBuilder('paragraph')
      ->view($paragraph, 'default');
  }

}
