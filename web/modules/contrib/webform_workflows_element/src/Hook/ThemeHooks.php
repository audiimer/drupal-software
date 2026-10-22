<?php

namespace Drupal\webform_workflows_element\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\Entity\User;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;

/**
 * Theme and preprocess hooks.
 */
class ThemeHooks {

  /**
   * Constructs ThemeHooks.
   */
  public function __construct(
    protected readonly WebformWorkflowsManager $workflowsManager,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'webform_workflows_element' => [
        'render element' => 'element',
      ],
      'webform_workflows_element_value' => [
        'variables' => [
          'element' => NULL,
          'values' => NULL,
          'color_class' => NULL,
        ],
        'initial preprocess' => static::class . ':preprocessWebformWorkflowsElementValue',
      ],
      'webform_handler_workflows_transition_email_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => NULL,
        ],
      ],
    ];
  }

  /**
   * Prepares variables for webform_workflows_element_value template.
   *
   * @param array $variables
   *   Variables from theme, by reference.
   *
   * @see webform-workflows-element-value.html.twig
   */
  public function preprocessWebformWorkflowsElementValue(array &$variables): void {
    if (isset($variables['values']['workflow_state'])) {
      // Provide colour class:
      $color_css = $this->workflowsManager->getColorClassForState($variables['element'], $variables['values']['workflow_state']);
      if ($color_css) {
        $variables['color_class'] = 'with-color ' . $color_css;
      }
    }

    $changed_user = isset($variables['values']['changed_user']) ? User::load($variables['values']['changed_user']) : NULL;
    $variables['values']['changed_user_name'] = $changed_user ? $changed_user->getDisplayName() : NULL;
  }

}
