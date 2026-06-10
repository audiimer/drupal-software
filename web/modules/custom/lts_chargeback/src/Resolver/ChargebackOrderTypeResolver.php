<?php

namespace Drupal\lts_chargeback\Resolver;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\Resolver\OrderTypeResolverInterface;

/**
 * Resolves the 'chargeback' order type for chargeback-access software.
 *
 * When an order item references a Software node whose field_access_type is
 * 'chargeback', this resolver claims the order for the 'chargeback' order
 * type, which carries the lts_chargeback_approval workflow. Returning NULL
 * lets the next resolver in the chain (DefaultOrderTypeResolver) decide,
 * which is what happens for download/redirect items.
 *
 * POC NOTE: This resolves order type at add-to-cart time, based on the item
 * being added. It is sufficient for single-type carts (the demo path). The
 * seamless mixed-cart split (one checkout producing a chargeback order AND a
 * download order) is a separate, larger piece of work and is NOT handled here.
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
    if ($software->get('field_access_type')->value === 'chargeback') {
      return 'chargeback';
    }
    return NULL;
  }

}
