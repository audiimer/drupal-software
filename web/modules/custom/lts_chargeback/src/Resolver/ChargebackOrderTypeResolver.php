<?php

namespace Drupal\lts_chargeback\Resolver;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Resolver\OrderTypeResolverInterface;

/**
 * Resolves order type based on the software node's field_access_type.
 *
 * - chargeback → 'chargeback' order type (approval workflow)
 * - download   → 'download' order type (instant completion)
 * - anything else → NULL (falls through to DefaultOrderTypeResolver)
 *
 * POC NOTE: Single-type carts only. Mixed-cart splitting (one checkout
 * producing both a chargeback and a download order) is deferred to production.
 */
class ChargebackOrderTypeResolver implements OrderTypeResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(OrderItemInterface $order_item) {
    $variation = $order_item->getPurchasedEntity();
    if (!$variation || !$variation->hasField('field_software')) {
      return NULL;
    }
    $software = $variation->get('field_software')->entity;
    if (!$software || !$software->hasField('field_access_type')) {
      return NULL;
    }
    $access_type = $software->get('field_access_type')->value;
    if ($access_type === 'chargeback') {
      return 'chargeback';
    }
    if ($access_type === 'download') {
      return 'download';
    }
    return NULL;
  }

}
