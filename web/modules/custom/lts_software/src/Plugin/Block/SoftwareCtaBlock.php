<?php

namespace Drupal\lts_software\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a conditional CTA block for Software nodes.
 *
 * Renders one of three things depending on field_access_type:
 *   - chargeback / download: Commerce Add to Cart form for the matching
 *     product variation (looked up by field_software reference).
 *   - redirect: styled external link button built from field_redirect_button.
 *
 * Placed once in Layout Builder on the Software content type's default
 * display; renders correctly per-node based on the node's field_access_type.
 *
 * @Block(
 *   id = "software_cta_block",
 *   admin_label = @Translation("Software CTA"),
 *   category = @Translation("Lehigh Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class SoftwareCtaBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity form builder.
   */
  protected EntityFormBuilderInterface $entityFormBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFormBuilderInterface $entity_form_builder
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFormBuilder = $entity_form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder')
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

    if (!$node->hasField('field_access_type') || $node->get('field_access_type')->isEmpty()) {
      return [];
    }

    $access_type = $node->get('field_access_type')->value;

    switch ($access_type) {
      case 'redirect':
        return $this->buildRedirectButton($node);

      case 'chargeback':
        return $this->buildAddToCartForm($node);

      case 'download':
        return $this->buildInstallerModal($node);

      default:
        return [];
    }
  }

  /**
   * Builds a styled link button from field_redirect_button.
   */
  protected function buildRedirectButton($node): array {
    if (!$node->hasField('field_redirect_button') || $node->get('field_redirect_button')->isEmpty()) {
      return [];
    }

    $link_item = $node->get('field_redirect_button')->first();
    $uri = $link_item->uri;
    $title = $link_item->title ?: $this->t('Visit external site');

    try {
      $url = Url::fromUri($uri);
    }
    catch (\Exception $e) {
      return [];
    }

    $attributes = [
      'class' => ['btn', 'btn-primary', 'software-cta', 'software-cta--redirect'],
    ];
    if ($url->isExternal()) {
      $attributes['target'] = '_blank';
      $attributes['rel'] = 'noopener noreferrer';
    }
    $url->setOption('attributes', $attributes);

    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => $url,
      '#cache' => [
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

  /**
   * Renders the software's installer modal (direct download, no cart).
   *
   * Reads the flat field_installer_files paragraph field directly off the
   * Software node (each entry = OS + variant + file). Grouping by OS is
   * done at render time in the Twig template, not in the data model.
   *
   * NOTE: this deliberately does NOT use the old field_installer_modal
   * paragraph wrapper, which is being retired (see design doc). All
   * download installer data now lives in field_installer_files on the node.
   */
  protected function buildInstallerModal($node): array {
    // Guard against unsaved/sample entities (e.g. Layout Builder's
    // "manage default layout" preview screen, which renders blocks
    // against a generated sample entity with no real ID). Without a
    // real node ID we cannot build a valid download route, so bail
    // out cleanly rather than let Twig's path() throw.
    if (!$node->id()) {
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

  /**
   * Builds the Commerce Add to Cart form for the matching variation.
   *
   * Mirrors the lookup pattern used in playground_preprocess_views_view_fields()
   * so the catalog cards and detail page behave consistently.
   */
  protected function buildAddToCartForm($node): array {
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

    $product = $variation->getProduct();
    if (!$product) {
      return [];
    }

    $order_item = $this->entityTypeManager
      ->getStorage('commerce_order_item')
      ->create([
        'type' => 'default',
        'purchased_entity' => $variation,
        'quantity' => 1,
      ]);

    $form = $this->entityFormBuilder->getForm(
      $order_item,
      'add_to_cart',
      ['product' => $product]
    );

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['software-cta', 'software-cta--cart'],
      ],
      'form' => $form,
      '#cache' => [
        'tags' => Cache::mergeTags($node->getCacheTags(), $variation->getCacheTags()),
        'contexts' => ['user', 'session'],
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
    return Cache::mergeContexts(parent::getCacheContexts(), ['user', 'session', 'url']);
  }

}