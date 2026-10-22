<?php

namespace Drupal\webform_workflows_element_maestro\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform_workflows_element_maestro\Plugin\EngineTasks\MaestroTransitionWebformWorkflowTask;

/**
 * Hooks for the webform_workflows_element_maestro module.
 */
class MaestroHooks {

  use StringTranslationTrait;

  /**
   * Constructs MaestroHooks.
   */
  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_task_console_interactive_link_alter().
   */
  #[Hook('task_console_interactive_link_alter')]
  public function taskConsoleInteractiveLinkAlter(&$link, $taskTemplate, $queueRecord, $templateMachineName): void {
    if ($taskTemplate['tasktype'] === 'MaestroTransitionWebformWorkflowTask') {
      $link = $taskTemplate['data']['button_text'] ?? 'Review and change workflow';
    }
  }

  /**
   * Implements hook_execute_title().
   */
  #[Hook('execute_title')]
  public function executeTitle() {
    $queue_id = $this->routeMatch->getParameter('queueid_or_token');
    $submission = MaestroTransitionWebformWorkflowTask::getSubmission($queue_id);
    if ($submission) {
      return $this->t('Review submission: @webformTitle', [
        '@webformTitle' => $submission->getWebform()->label(),
      ]);
    }
    else {
      // Return default title from route configuration.
      $route = $this->routeMatch->getRouteObject();
      $title = $route->getDefault('_title');
      // @todo the following phpcs:ignore should be verified.
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      return $this->t($title);
    }
  }

}
