<?php

namespace Drupal\lts_chargeback\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Renders the chargeback approval gate on the user-facing order view.
 *
 * Drupal 11 OOP hook. Keyed off order STATE (pending_review / approved /
 * denied) so a future "request" access type reuses it. Guarded so it only
 * appears on orders that contain a chargeback item.
 */
class OrderViewHooks {

  #[Hook('entity_view')]
  public function entityView(array &$build, $entity, $display, $view_mode): void {
    if ($entity->getEntityTypeId() !== 'commerce_order') {
      return;
    }
    if ($view_mode !== 'user') {
      return;
    }
    if (!$this->orderHasChargebackItem($entity)) {
      return;
    }
    $state = $entity->getState()->getId();
    if (!in_array($state, ['pending_review', 'approved', 'denied'], TRUE)) {
      return;
    }
    $build['lts_chargeback_gate'] = $this->gateRender($entity, $state);
    $build['lts_chargeback_gate']['#weight'] = -50;
  }

  protected function gateRender($order, $state): array {
    $base = ['#cache' => ['tags' => $order->getCacheTags()]];

    if ($state === 'pending_review') {
      return $base + [
        '#theme' => 'lts_chargeback_gate',
        '#gate_state' => 'pending_review',
        '#message' => t('Your request is pending review. Instructions will appear here once it has been approved. You will be notified by email.'),
      ];
    }

    if ($state === 'approved') {
      $link = NULL;
      $node = $this->orderSoftwareNode($order);
      if ($node && $node->hasField('field_instructions_url') && !$node->get('field_instructions_url')->isEmpty()) {
        $item = $node->get('field_instructions_url')->first();
        try {
          $url = Url::fromUri($item->uri);
          if ($url->isExternal()) {
            $url->setOption('attributes', [
              'target' => '_blank',
              'rel' => 'noopener noreferrer',
              'class' => ['btn', 'btn-primary', 'chargeback-gate__instructions'],
            ]);
          }
          $link = [
            '#type' => 'link',
            '#title' => $item->title ?: t('Instructions'),
            '#url' => $url,
          ];
        }
        catch (\Exception $e) {
          $link = NULL;
        }
      }
      return $base + [
        '#theme' => 'lts_chargeback_gate',
        '#gate_state' => 'approved',
        '#message' => t('Your request has been approved. Use the link below for installation and activation instructions.'),
        '#instructions_link' => $link,
      ];
    }

    $reason = '';
    if ($order->hasField('denial_reason') && !$order->get('denial_reason')->isEmpty()) {
      $reason = $order->get('denial_reason')->value;
    }
    return $base + [
      '#theme' => 'lts_chargeback_gate',
      '#gate_state' => 'denied',
      '#message' => t('Your request was denied.'),
      '#reason' => $reason,
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

  protected function orderHasChargebackItem($order): bool {
    foreach ($order->getItems() as $order_item) {
      $variation = $order_item->getPurchasedEntity();
      if (!$variation || !$variation->hasField('field_software')) {
        continue;
      }
      $software = $variation->get('field_software')->entity;
      if ($software && $software->hasField('field_access_type') && $software->get('field_access_type')->value === 'chargeback') {
        return TRUE;
      }
    }
    return FALSE;
  }

}
