<?php

namespace Drupal\lts_software\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;

/**
 * Provides a conditional CTA block for Software nodes.
 *
 * Renders one of two things depending on field_access_type:
 *   - download: the installer modal (lts_download).
 *   - redirect: styled external link button built from field_redirect_button.
 * Chargeback software renders nothing here; its Request button is the
 * field_request_form field block placed next to this block.
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
class SoftwareCtaBlock extends BlockBase {

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
   * Renders the software's installer modal (direct download).
   *
   * Delegates to lts_download.installer_entries_builder, the single
   * shared source of truth also used by the catalog card
   * (CatalogViewsPreprocessHooks), so the two never drift apart.
   */
  protected function buildInstallerModal($node): array {
    return \Drupal::service('lts_download.installer_entries_builder')
      ->buildRenderArray($node);
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