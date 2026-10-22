<?php

namespace Drupal\webform_workflows_element_views\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hooks for webform_workflows_element_views.
 */
class ViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data = [];

    // Filters.
    $data['webform_submission']['workflow_state'] = [
      'title' => $this->t('Workflow state filter'),
      'filter' => [
        'id' => 'webform_workflows_element_state',
      ],
    ];

    return $data;
  }

}
