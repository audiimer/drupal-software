<?php

namespace Drupal\lts_software\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\node\NodeInterface;

/**
 * Provides a Price block for Software detail pages.
 *
 * Shows the node's field_price for chargeback software (the price per
 * license that the Software request webform charges to an index). A zero
 * price displays as "Free". Other access types produce no output; the
 * surrounding layout accommodates that gracefully.
 *
 * @Block(
 *   id = "software_price_block",
 *   admin_label = @Translation("Software Price"),
 *   category = @Translation("Lehigh Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class SoftwarePriceBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->getContextValue('node');

    if (!$node || $node->bundle() !== 'software') {
      return [];
    }

    $price = static::formatPrice($node);
    if ($price === NULL) {
      return [];
    }

    $classes = ['software-price'];
    if ((float) $node->get('field_price')->value == 0.0) {
      $classes[] = 'software-price--free';
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => $classes,
      ],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $this->t('Price'),
        '#attributes' => ['class' => ['software-price__label']],
      ],
      'price' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $price,
        '#attributes' => ['class' => ['software-price__value']],
      ],
      '#attached' => [
        'library' => ['lts_software/software_price'],
      ],
      '#cache' => [
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

  /**
   * Formats a chargeback Software node's price for display.
   *
   * Shared with the catalog cards (CatalogViewsPreprocessHooks) so the
   * detail page and the cards always agree.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string|null
   *   "Free" for zero, "$1,234.50" otherwise, or NULL when the node is not
   *   chargeback software or has no price.
   */
  public static function formatPrice(NodeInterface $node) {
    if (!$node->hasField('field_access_type') || $node->get('field_access_type')->value !== 'chargeback') {
      return NULL;
    }
    if (!$node->hasField('field_price') || $node->get('field_price')->isEmpty()) {
      return NULL;
    }
    $amount = (float) $node->get('field_price')->value;
    return $amount == 0.0 ? t('Free') : '$' . number_format($amount, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    $node = $this->getContextValue('node');
    if ($node) {
      $tags = Cache::mergeTags($tags, $node->getCacheTags());
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url']);
  }

}
