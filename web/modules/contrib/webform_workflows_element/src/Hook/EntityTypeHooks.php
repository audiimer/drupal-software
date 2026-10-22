<?php

namespace Drupal\webform_workflows_element\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\webform_workflows_element\WebformSubmissionWorkflowListBuilder;

/**
 * Entity type hooks (isolated to avoid circular service dependencies).
 */
class EntityTypeHooks {

  /**
   * Constructs EntityTypeHooks.
   */
  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_entity_type_alter().
   *
   * Adds the workflow list builder if webform_better_results is absent.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    // If enabled, Webform Better Results will automatically pick up the hooks
    // we use. If not enabled, do it manually from within this module.
    if (!$this->moduleHandler->moduleExists('webform_better_results')) {
      $entity_types['webform_submission']->setHandlerClass('list_builder', WebformSubmissionWorkflowListBuilder::class);
    }
  }

}
