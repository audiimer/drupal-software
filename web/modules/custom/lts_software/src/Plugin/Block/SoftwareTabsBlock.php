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
 * Renders Description / Terms of Use / System Specs / Installation
 * Instructions as Bootstrap nav-tabs sourced from the contextual node:
 *   - Description: node body (formatted long text)
 *   - Terms of Use: PDF file embedded via iframe (with download fallback)
 *   - System Specs: field_specs (formatted long text)
 *   - Installation Instructions: field_license_details (label/value
 *     paragraphs), field_installation_instructions (formatted long text),
 *     and field_instructions_url (optional external link)
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

    // Installation Instructions tab — license details table, inline
    // instructions text, and an optional external instructions link.
    // All three sources are independently optional; the tab is omitted
    // only if all three are empty.
    $has_license_details = $node->hasField('field_license_details') && !$node->get('field_license_details')->isEmpty();
    $has_inline_instructions = $node->hasField('field_installation_instructions') && !$node->get('field_installation_instructions')->isEmpty();
    $has_instructions_url = $node->hasField('field_instructions_url') && !$node->get('field_instructions_url')->isEmpty();

    if ($has_license_details || $has_inline_instructions || $has_instructions_url) {
      $license_rows = [];
      if ($has_license_details) {
        foreach ($node->get('field_license_details') as $item) {
          $paragraph = $item->entity;
          if (!$paragraph) {
            continue;
          }
          $label = $paragraph->hasField('field_label') ? $paragraph->get('field_label')->value : '';
          $value = $paragraph->hasField('field_value') ? $paragraph->get('field_value')->value : '';
          if ($label || $value) {
            $license_rows[] = ['label' => $label, 'value' => $value];
          }
        }
      }

      $inline_instructions = NULL;
      if ($has_inline_instructions) {
        $instructions_item = $node->get('field_installation_instructions')->first();
        $inline_instructions = [
          '#type' => 'processed_text',
          '#text' => $instructions_item->value,
          '#format' => $instructions_item->format,
        ];
      }

      $instructions_link = NULL;
      if ($has_instructions_url) {
        $item = $node->get('field_instructions_url')->first();
        try {
          $url = Url::fromUri($item->uri);
          $instructions_link = [
            'url' => $url->toString(),
            'title' => $item->title ?: $this->t('Installation Guide'),
          ];
        }
        catch (\Exception $e) {
          $instructions_link = NULL;
        }
      }

      $tabs['installation'] = [
        'id' => 'installation',
        'label' => $this->t('Installation Instructions'),
        'type' => 'installation',
        'license_rows' => $license_rows,
        'inline_instructions' => $inline_instructions,
        'instructions_link' => $instructions_link,
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