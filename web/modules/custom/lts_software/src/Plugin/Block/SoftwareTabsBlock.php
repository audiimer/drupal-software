<?php

namespace Drupal\lts_software\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a tabs block for Software detail pages.
 *
 * Renders Description / Terms of Use / System Specs as Bootstrap nav-tabs
 * sourced from the contextual node:
 *   - Description: node body (formatted long text)
 *   - Terms of Use: PDF file embedded via iframe (with download fallback)
 *   - System Specs: field_specs (formatted long text)
 *
 * Tabs are conditionally rendered: if a source field is empty, that tab
 * is omitted entirely. This replaces the legacy QuickTabs implementation
 * that previously occupied this slot in the Layout Builder display.
 *
 * @Block(
 *   id = "software_tabs_block",
 *   admin_label = @Translation("Software Tabs"),
 *   category = @Translation("Lehigh Custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class SoftwareTabsBlock extends BlockBase implements ContainerFactoryPluginInterface {

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

    $tabs = [];

    // Description tab — node body.
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $body_item = $node->get('body')->first();
      $tabs['description'] = [
        'id' => 'description',
        'label' => $this->t('Description'),
        'type' => 'html',
        'content' => [
          '#type' => 'processed_text',
          '#text' => $body_item->value,
          '#format' => $body_item->format,
        ],
      ];
    }

    // Terms of Use tab — PDF file embed.
    if ($node->hasField('field_terms_of_use') && !$node->get('field_terms_of_use')->isEmpty()) {
      $file_item = $node->get('field_terms_of_use')->first();
      $file = $file_item->entity;
      if ($file) {
        $file_url = $file->createFileUrl(FALSE);
        $tabs['terms'] = [
          'id' => 'terms',
          'label' => $this->t('Terms of Use'),
          'type' => 'pdf',
          'file_url' => $file_url,
          'file_name' => $file->getFilename(),
        ];
      }
    }

    // System Specs tab — formatted long text.
    if ($node->hasField('field_specs') && !$node->get('field_specs')->isEmpty()) {
      $specs_item = $node->get('field_specs')->first();
      $tabs['specs'] = [
        'id' => 'specs',
        'label' => $this->t('System Specs'),
        'type' => 'html',
        'content' => [
          '#type' => 'processed_text',
          '#text' => $specs_item->value,
          '#format' => $specs_item->format,
        ],
      ];
    }

    if (empty($tabs)) {
      return [];
    }

    // Mark the first tab as active for Bootstrap nav-tabs initial state.
    $keys = array_keys($tabs);
    $tabs[$keys[0]]['active'] = TRUE;

    return [
      '#theme' => 'software_tabs',
      '#tabs' => $tabs,
      '#attached' => [
        'library' => ['lts_software/software_tabs'],
      ],
      '#cache' => [
        'tags' => $node->getCacheTags(),
        'contexts' => ['url'],
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