<?php

namespace Drupal\lts_software\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Price block for Software detail pages.
 *
 * Looks up the matching Commerce product variation via field_software
 * (same pattern as SoftwareCtaBlock) and renders its price using the
 * commerce_price_free formatter so $0.00 displays as "Free".
 *
 * This block replaces the node-level field_price block previously placed
 * in the Layout Builder hero. The variation price is the source of truth
 * (it's what actually charges the user); the legacy field_price node
 * field should be deleted in a Phase F cleanup pass.
 *
 * Redirect-type Software nodes have no variation and produce no output;
 * the surrounding layout should accommodate that gracefully.
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
class SoftwarePriceBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface|null $node */
    $node = $this->getContextValue('node');

    if (!$node || $node->bundle() !== 'software') {
      return [];
    }

    if (!\Drupal::moduleHandler()->moduleExists('commerce_product')) {
      return [];
    }

    $variation_storage = $this->entityTypeManager->getStorage('commerce_product_variation');

    $vids = $variation_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_software', $node->id())
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();

    if (empty($vids)) {
      return [];
    }

    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface|null $variation */
    $variation = $variation_storage->load(reset($vids));
    if (!$variation) {
      return [];
    }

    // Render the variation's price field using the commerce_price_free
    // formatter so zero-priced items display as "Free".
    $price_render = $variation->get('price')->view([
      'label' => 'hidden',
      'type' => 'commerce_price_free',
      'settings' => [],
    ]);

    $price_value = $variation->getPrice();
    $is_free = $price_value && (float) $price_value->getNumber() == 0.0;

    $classes = ['software-price'];
    if ($is_free) {
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
      'price' => $price_render,
      '#attached' => [
        'library' => ['lts_software/software_price'],
      ],
      '#cache' => [
        'tags' => Cache::mergeTags($node->getCacheTags(), $variation->getCacheTags()),
      ],
    ];
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