<?php

namespace Drupal\webform_workflows_element\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Drupal\workflows\Entity\Workflow;

/**
 * Core entity, access and lifecycle hooks.
 */
class WebformWorkflowsElementHooks {

  use StringTranslationTrait;

  /**
   * Constructs WebformWorkflowsElementHooks.
   */
  public function __construct(
    protected readonly WebformWorkflowsManager $workflowsManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly EntityFormBuilderInterface $entityFormBuilder,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for webform_submission.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  #[Hook('webform_submission_view')]
  public function webformSubmissionView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {
    $webform = $entity->getWebform();

    $workflow_elements = $this->workflowsManager->getAccessibleWorkflowElements($this->currentUser, $webform);

    if (count($workflow_elements) > 0 && $this->workflowsManager->shouldShowWorkflowEditForm($entity)) {
      // We need to open the webform submission edit form on the last page to
      // enable the 'Save' feature.
      $pages = $webform->getPages();
      unset($pages[WebformInterface::PAGE_CONFIRMATION]);
      $last_page = array_key_last($pages);

      $form = $this->entityFormBuilder->getForm($entity, 'edit', ['current_page' => $last_page]);

      // Add the submission workflow form to the view.
      $build = array_merge(
        $build,
        [
          'form' => $form,
        ],
      );
    }

    if ($this->workflowsManager->shouldShowWorkflowLog('view', $entity)) {
      $build = array_merge(
        $build,
        $this->workflowsManager->getRenderedWorkflowLog($entity),
      );
    }
  }

  /**
   * Implements hook_webform_element_access().
   */
  #[Hook('webform_element_access')]
  public function webformElementAccess($operation, array &$element, ?AccountInterface $account = NULL, array $context = []): AccessResultInterface {
    $access_property = '#access_' . $operation . '_workflow_enabled';

    if (isset($element['#workflow'])) {
      if (!isset($element[$access_property])) {
        if ($operation === 'create') {
          return AccessResult::forbidden();
        }
        return AccessResult::neutral();
      }

      if (!$element[$access_property]) {
        return AccessResult::forbidden();
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_webform_submission_presave().
   *
   * Makes the change to workflow state after checking access
   * and logs it in webform logs.
   */
  #[Hook('webform_submission_presave')]
  public function webformSubmissionPresave(WebformSubmissionInterface $webform_submission): void {
    $webform = $webform_submission->getWebform();
    $workflow_elements = $this->workflowsManager->getWorkflowElementsForWebform($webform);

    foreach ($workflow_elements as $element_id => $element) {
      $this->workflowsManager->runTransition($webform_submission, $element_id);
    }
  }

  /**
   * Implements hook_webform_submission_access().
   *
   * Allows edit access to submissions at certain workflow states.
   */
  #[Hook('webform_submission_access')]
  public function webformSubmissionAccess(WebformSubmissionInterface $webform_submission, $operation, AccountInterface $account): AccessResultInterface {
    // Allow access to edit submissions if at certain workflow states:
    // Set on access tab of workflow element.
    if ($operation === 'update') {
      $webform = $webform_submission->getWebform();
      $workflow_elements = $this->workflowsManager->getWorkflowElementsForWebform($webform);

      // Ignore if no workflow elements.
      if (count($workflow_elements) === 0) {
        return AccessResult::neutral();
      }

      // By default, we don't have an opinion.
      $new_access = AccessResult::neutral();

      // For each element, check if there's a status that allows this:
      foreach ($workflow_elements as $workflow_element_id => $workflow_element) {
        $state_id = NULL;

        // Get current state ID:
        $workflow_data = $webform_submission->getElementData($workflow_element_id);
        if (isset($workflow_data['workflow_state']) && $workflow_data['workflow_state']) {
          $state_id = $workflow_data['workflow_state'];
        }
        elseif ($initial_state = $this->workflowsManager->getInitialStateForElement($workflow_element)) {
          $state_id = $initial_state->id();
        }

        // Check if any access based on state:
        if ($state_id) {
          $new_access = $this->workflowsManager->checkAccessToUpdateBasedOnState($account, $webform_submission, $workflow_element, $state_id);

          // With multiple workflow elements, take the first clear opinion on
          // access.
          if (!$new_access->isNeutral()) {
            return $new_access;
          }
        }
      }
      return $new_access;
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_operation_alter().
   *
   * Adds each enabled transition as an entity operation on the submission.
   *
   * @throws \Exception
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity): void {
    if ($entity instanceof WebformSubmissionInterface) {
      $workflow_elements = $this->workflowsManager->getWorkflowElementsForWebform($entity->getWebform());
      foreach ($workflow_elements as $element_id => $element) {
        $workflow_id = $element['#workflow'];
        $workflow = Workflow::load($workflow_id);

        // Only load transitions available for the current state:
        $element_value = $entity->getElementData($element_id);
        $current_state = $element_value['workflow_state'] ?? NULL;
        $transitions = $this->workflowsManager->getAvailableTransitionsForWorkflow($workflow_id, $current_state, NULL, $entity->getWebform(), $entity);

        foreach ($transitions as $transition) {
          // Only show transitions enabled for this element/form:
          if (!$this->workflowsManager->isTransitionEnabled($element, $transition->id())) {
            continue;
          }

          $title = $this->t('@element_label: @transition', [
            '@element_label' => $element['#title'] ?: $workflow->label(),
            '@transition' => $transition->label(),
          ]);

          $operations['webform_workflow_' . $element['#workflow'] . '_' . $transition->id()] = [
            'title' => $title,
            'url' => Url::fromRoute('entity.webform.transition', [
              'webform' => $entity->getWebform()->id(),
              'webform_submission' => $entity->id(),
              'workflow_element' => $element_id,
              'transition' => $transition->id(),
            ]),
            'weight' => 100 + $transition->weight(),
          ];
        }
      }
    }
  }

}
