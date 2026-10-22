<?php

namespace Drupal\webform_workflows_element\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Utility\WebformYaml;
use Drupal\webform\WebformAccessRulesManagerInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_workflows_element\Element\WebformWorkflowsElement as ElementWebformWorkflowsElement;
use Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent;
use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\workflows\WorkflowTypeInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class WebformWorkflowsManager.
 *
 * $workflowsManager = \Drupal::service('webform_workflows_element.manager');
 */
class WebformWorkflowsManager {

  use StringTranslationTrait;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected WebformElementManagerInterface $elementManager;

  /**
   * The webform access rules manager.
   *
   * @var \Drupal\webform\WebformAccessRulesManagerInterface
   */
  protected WebformAccessRulesManagerInterface $accessRulesManager;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The submission log manager (optional, requires webform_submission_log).
   *
   * @var object|null
   */
  protected ?object $submissionLogManager;

  /**
   * The workflow storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $workflowStorage;

  /**
   * Constructs a new object.
   */
  public function __construct(
    LoggerChannelFactoryInterface $logger_factory,
    ModuleHandlerInterface $module_handler,
    WebformElementManagerInterface $element_manager,
    WebformAccessRulesManagerInterface $access_rules_manager,
    EventDispatcherInterface $event_dispatcher,
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    RouteMatchInterface $route_match,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer,
    ?object $submission_log_manager = NULL,
  ) {
    $this->loggerFactory = $logger_factory;
    $this->moduleHandler = $module_handler;
    $this->elementManager = $element_manager;
    $this->accessRulesManager = $access_rules_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->entityTypeManager = $entity_type_manager;
    $this->workflowStorage = $this->entityTypeManager->getStorage('workflow');
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
    $this->submissionLogManager = $submission_log_manager;
  }

  /**
   * Get states for an element's workflow.
   *
   * @param mixed $element
   *   Workflows element.
   *
   * @return array
   *   The states.
   */
  public function getStatesFromElement(array $element): array {
    $workflowType = $this->getWorkflowTypeFromElement($element);
    if (!$workflowType) {
      return [];
    }

    return $workflowType->getStates();
  }

  /**
   * Get the workflow type from the workflow for element.
   *
   * @param array $element
   *   Workflows element.
   *
   * @return \Drupal\workflows\WorkflowTypeInterface
   *   Workflow type.
   */
  public function getWorkflowTypeFromElement(array $element): ?WorkflowTypeInterface {
    return isset($element['#workflow']) ? $this->getWorkflowType($element['#workflow']) : NULL;
  }

  /**
   * Get workflow type for a workflow.
   *
   * @param string $workflowId
   *   String ID.
   *
   * @return \Drupal\workflows\WorkflowTypeInterface|null
   *   Workflow type.
   */
  public function getWorkflowType(string $workflowId): ?WorkflowTypeInterface {
    $workflow = $this->getWorkflow($workflowId);

    if (!$workflow) {
      return NULL;
    }

    $workflowType = $workflow->getTypePlugin();
    return $workflowType;
  }

  /**
   * Get workflow for a workflow.
   *
   * @param string $workflowId
   *   String ID.
   *
   * @return \Drupal\workflows\WorkflowInterface|null
   *   Workflow.
   */
  public function getWorkflow(string $workflowId): ?WorkflowInterface {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->workflowStorage->load($workflowId);
    if (!$workflow) {
      return NULL;
    }
    return $workflow;
  }

  /**
   * Get workflow from workflow for element.
   *
   * @param array $element
   *   Workflows element.
   *
   * @return \Drupal\workflows\WorkflowTypeInterface
   *   Workflow type.
   */
  public function getWorkflowFromElement(array $element): ?WorkflowTypeInterface {
    return isset($element['#workflow']) ? $this->getWorkflowType($element['#workflow']) : NULL;
  }

  /**
   * Get the initial workflow state for the workflow of the element.
   *
   * @param array $element
   *   Workflows element.
   *
   * @return \Drupal\workflows\StateInterface
   *   The workflow state.
   */
  public function getInitialStateForElement(array $element): ?StateInterface {
    $workflowType = $this->getWorkflowTypeFromElement($element);

    if (!$workflowType) {
      return NULL;
    }

    // Load the default state for the workflow.
    if ($state = $workflowType->getInitialState()) {
      return $state;
    }
    else {
      // If no default state set, use the first one.
      $allStates = $workflowType->getStates();
      return reset($allStates);
    }
  }

  /**
   * Get all transitions for a current state for a workflow.
   *
   * Optionally also filter by user access.
   *
   * @param string $workflowId
   *   The workflow id.
   * @param string $currentStateId
   *   The current state id.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   * @param \Drupal\webform\WebformInterface|null $webform
   *   The webform.
   * @param mixed $webform_submission
   *   The webform submission.
   *
   * @return array
   *   Array of WorkflowTransitions.
   *
   * @throws \Exception
   */
  public function getAvailableTransitionsForWorkflow(string $workflowId, ?string $currentStateId = NULL, ?AccountInterface $account = NULL, ?WebformInterface $webform = NULL, $webform_submission = NULL): array {
    $workflowType = $this->getWorkflowType($workflowId);

    if (!$workflowType) {
      return [];
    }

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->workflowStorage->load($workflowId);

    if ($currentStateId && $workflowType->hasState($currentStateId)) {
      $currentState = $workflowType->getState($currentStateId);
    }
    else {
      $currentState = $workflowType->getInitialState();
    }

    if (!$currentState) {
      return [];
    }

    // Get available transitions from current state:
    $availableTransitions = $currentState->getTransitions();

    if ($webform && $account) {
      foreach ($availableTransitions as $transition_id => $transition) {
        $access = $this->checkAccessForSubmissionAndTransition($workflow, $account, $webform, $transition, $currentState, $webform_submission);

        if (!$access) {
          unset($availableTransitions[$transition_id]);
        }
      }
    }

    return $availableTransitions;
  }

  /**
   * Check if user can do a transition for a workflow for a webform.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition.
   * @param mixed $currentState
   *   The current state.
   * @param mixed $webformSubmission
   *   The webform submission.
   *
   * @return bool
   *   Whether the user can transition the workflow.
   *
   * @throws \Exception
   */
  public function checkAccessForSubmissionAndTransition(WorkflowInterface $workflow, AccountInterface $account, WebformInterface $webform, TransitionInterface $transition, $currentState = NULL, $webformSubmission = NULL): bool {
    $pass = FALSE;
    $workflow_elements = $this->getWorkflowElementsForWebform($webform);

    foreach ($workflow_elements as $element) {
      if ($element['#workflow'] != $workflow->id()) {
        continue;
      }

      $transition_access_id = 'access_transition_' . $transition->id();

      $rule_id = 'transition_' . $transition->id();
      if (!$this->isTransitionEnabled($element, $transition->id())) {
        return FALSE;
      }

      $pass = $this->checkAccessForWorkflowAccessRules($element, $webform, $account, $transition_access_id, $rule_id);
    }

    // Allow hooks to determine whether access:
    // hook_webform_workflow_element_transition_access_alter.
    $context = [
      'workflow' => $workflow,
      'webform' => $webform,
      'state' => $currentState,
      'account' => $account,
      'webform_submission' => $webformSubmission,
      'transition' => $transition,
    ];
    $this->moduleHandler->alter(['webform_workflow_element_transition_access'], $pass, $context);
    return $pass;
  }

  /**
   * Get all workflow elements for a webform.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform.
   *
   * @return array
   *   Array of elements arrays.
   */
  public function getWorkflowElementsForWebform(WebformInterface $webform): array {
    $elements_raw = $webform->getElementsRaw();
    $elements = WebformYaml::decode($elements_raw);
    return is_array($elements) ? $this->filterWorkflowElements($elements) : [];
  }

  /**
   * Recursively filter webform elements to only keep webform_workflows_element.
   *
   * @param array $elements
   *   Webform elements returned by $webform->getElementsOriginalDecoded().
   *
   * @return array
   *   Filtered webform elements keyed by machine name.
   */
  protected function filterWorkflowElements(array $elements): array {
    $filtered = [];
    foreach (Element::children($elements) as $key) {
      $element = $elements[$key];
      if (isset($element['#type']) && $element['#type'] === 'webform_workflows_element') {
        $filtered[$key] = $element;
      }
      $filtered += $this->filterWorkflowElements($element);
    }
    return $filtered;
  }

  /**
   * Checks access for workflow access rules.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Entity\EntityInterface $webform
   *   The webform.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $access_id
   *   The access id.
   * @param string $rule_id
   *   The rule id.
   *
   * @return bool
   *   The return value.
   *
   * @throws \Exception
   */
  public function checkAccessForWorkflowAccessRules(array $element, EntityInterface $webform, AccountInterface $account, string $access_id, string $rule_id): bool {
    $element_plugin = $this->elementManager->getElementInstance($element, $webform);

    $pass = $element_plugin->checkAccessRules($rule_id, $element, $account);

    $groupRoles = isset($element[$access_id . '_group_roles']) && count($element[$access_id . '_group_roles']) > 0;
    $groupPermissions = isset($element[$access_id . '_group_permissions']) && count($element[$access_id . '_group_permissions']) > 0;
    if ($groupRoles || $groupPermissions) {
      if ($this->moduleHandler->moduleExists('webform_group')) {
        $group_access = webform_group_webform_element_access($rule_id, $element, $account);
      }
      else {
        $group_access = AccessResult::neutral();
      }
      if (!$group_access->isAllowed()) {
        $pass = FALSE;
      }
    }

    return $pass;
  }

  /**
   * Load webforms which have workflow elements.
   *
   * @param string|null $workflow_id
   *   The workflow id.
   *
   * @return \Drupal\webform\WebformInterface[]
   *   The array of webforms.
   */
  public function getWebformsWithWorkflowElements(?string $workflow_id = NULL): array {
    $webforms = [];
    $webformsIDs = $this->getWebformsWithWorkflowElementsIds($workflow_id);
    foreach ($webformsIDs as $id) {
      $webforms[] = Webform::load($id);
    }
    return $webforms;
  }

  /**
   * Load webforms which have workflow elements.
   *
   * @param string|null $workflow_id
   *   The workflow id.
   *
   * @return \Drupal\webform\WebformInterface[]
   *   The array of webforms.
   */
  public function getWebformsWithWorkflowElementsIds(?string $workflow_id = NULL): array {
    $webforms = [];
    $connection = Database::getConnection();
    $query = $connection->select('config')
      ->fields('config', ['data'])
      ->condition('name', $connection->escapeLike('webform.webform.') . '%', 'LIKE')
      ->condition('data', '%' . $connection->escapeLike('webform_workflows_element') . '%', 'LIKE');

    if ($workflow_id) {
      $query->condition('data', '%' . $connection->escapeLike("'#workflow': " . $workflow_id) . '%', 'LIKE');
    }

    $webformsData = $query->execute();
    foreach ($webformsData as $data) {
      $data = unserialize($data->data, ['allowed_classes' => FALSE]);
      $webforms[] = $data['id'];
    }
    return $webforms;
  }

  /**
   * Check if user can see workflow element.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   * @param array $workflow_element
   *   The workflow element.
   * @param mixed $webform_submission
   *   The webform submission.
   *
   * @return bool
   *   TRUE if access is allowed.
   *
   * @throws \Exception
   */
  public function checkUserCanAccessElement(AccountInterface $account, WebformInterface $webform, array $workflow_element, $webform_submission = NULL): bool {
    $element_plugin = $this->elementManager->getElementInstance($workflow_element, $webform);

    $access = NULL;

    // Allow hooks to determine whether access:
    $context = [
      'webform' => $webform,
      'element_plugin' => $element_plugin,
      'account' => $account,
      'workflow_element' => $workflow_element,
      'webform_submission' => $webform_submission,
    ];
    $this->moduleHandler->alter(['webform_workflow_element_access'], $access, $context);

    if (!is_null($access)) {
      return $access;
    }

    // Otherwise use default:
    if (!$element_plugin->checkAccessRules('update', $workflow_element, $account)) {
      return FALSE;
    }
    elseif (count(ElementWebformWorkflowsElement::getAvailableTransitions($workflow_element, $webform_submission)) === 0) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get all transitions for a given workflow.
   *
   * This is not limited by what's possible for a given submission.
   *
   * @param string $workflow_id
   *   Workflow ID.
   *
   * @return array
   *   Array of transitions or NULL;
   */
  public function getTransitionsForWorkflow(string $workflow_id): ?array {
    $workflowType = $this->getWorkflowType($workflow_id);

    if (!$workflowType) {
      return NULL;
    }

    return $workflowType->getTransitions();
  }

  /**
   * Check if user has access to update a workflow element to a certain state.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param array $element
   *   The element.
   * @param string $state_id
   *   The state id.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccessToUpdateBasedOnState(AccountInterface $account, WebformSubmissionInterface $webform_submission, array $element, string $state_id): AccessResultInterface {
    $state = $this->getStateFromElementAndId($element, $state_id);
    if (!$state) {
      return AccessResult::neutral();
    }

    $state_access_id = 'access_update_at_state_' . $state->id();

    if (!isset($element['#' . $state_access_id . '_override']) || !$element['#' . $state_access_id . '_override']) {
      return AccessResult::neutral();
    }

    if (isset($element['#' . $state_access_id . '_workflow_enabled']) && !$element['#' . $state_access_id . '_workflow_enabled']) {
      return AccessResult::forbidden();
    }

    // The module originally deferred to webform submission's access rules
    // manager as the final check. However, the webform access rules do not
    // contain any of the workflow state operations, nor the users, roles, or
    // permissions config. It was essentially doing nothing.
    //
    // Conveniently, Webforms has a public method that allows us to check
    // users, roles, and permissions against a list rules in one go.
    $operation = 'update_at_state_' . $state->id();
    $access_rules_result = $this->accessRulesManager->checkAccessRules($operation, $account, [
      $operation => [
        'users' => $element["#{$state_access_id}_users"] ?? [],
        'roles' => $element["#{$state_access_id}_roles"] ?? [],
        'permissions' => $element["#{$state_access_id}_permissions"] ?? [],
      ],
        // Default is needed because checkAccessRules has a hardcoded check
        // against the "administer" operation that we don't want to be in the
        // business of also hardcoding ourselves.
    ] + $this->accessRulesManager->getDefaultAccessRules());

    // Based on the wording of the override toggle, it expects to ONLY follow
    // the state's configuration. We cannot use allowedIf because a FALSE
    // becomes neutral(), which is subject to other checks or ultimately be
    // allowed (see \Drupal\Core\Entity\EntityAccessControlHandler::access()).
    // So we explicitly forbid when FALSE, neutral otherwise.
    return AccessResult::forbiddenIf($access_rules_result === FALSE);
  }

  /**
   * Get state for an element with the state ID, or return NULL.
   *
   * @param mixed $element
   *   Workflows element.
   * @param mixed $id
   *   State ID.
   *
   * @return \Drupal\workflows\StateInterface
   *   The workflow state.
   */
  public function getStateFromElementAndId($element, $id): ?StateInterface {
    $workflowType = $this->getWorkflowTypeFromElement($element);

    if (!$workflowType) {
      return NULL;
    }

    return $workflowType->hasState($id) ? $workflowType->getState($id) : NULL;
  }

  /**
   * Run transition on an element for submission.
   *
   * DOES NOT SAVE SUBMISSION, which means you need to do that for the
   * transition to run. However, transition events may run regardless.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param string $element_id
   *   The element id.
   * @param ?string $transition_id
   *   The transition id.
   * @param ?string $log_public
   *   The transition id.
   * @param bool $access_check
   *   Check access.
   *
   * @return bool
   *   True if transition was valid, false otherwise.
   *
   * @throws \Exception
   */
  public function runTransition(WebformSubmissionInterface &$webform_submission, string $element_id, ?string $transition_id = NULL, ?string $log_public = NULL, bool $access_check = TRUE): bool {
    $originalData = $webform_submission->getElementData($element_id);

    // Run the transition:
    $newData = $this->runTransitionOnElementValue($webform_submission, $element_id, $transition_id, $log_public, $access_check);

    // If data is returned, it's a valid transition to run:
    if ($newData) {
      // Save data to the element:
      $webform_submission->setElementData($element_id, $newData);

      // Trigger event.
      // @todo review where this is placed - this is risky if the submission is not actually saved after this function is called.
      $this->triggerTransitionEvent($webform_submission, $element_id, $originalData ?: []);

      Cache::invalidateTags($webform_submission->getCacheTags());

      return TRUE;
    }
    else {
      // If we came by form, reset the submission so the form isn't confused.
      if (!$transition_id) {
        $originalData['transition'] = '';
        $webform_submission->setElementData($element_id, $originalData);
      }
      return FALSE;
    }
  }

  /**
   * Modify element values to reflect changes due to transition.
   *
   * Or return null to indicate no transition happened.
   *
   * Note this does not run the transition - these values need to be saved to
   * the element value of the specific submission in order to trigger that.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param string $element_id
   *   The element id.
   * @param ?string $transition_id
   *   The transition id.
   * @param ?string $log_public
   *   The log public.
   * @param bool $access_check
   *   The access check.
   *
   * @return array|null
   *   Array of updated composite element values, or NULL.
   *
   * @throws \Exception
   */
  public function runTransitionOnElementValue(WebformSubmissionInterface $webform_submission, string $element_id, ?string $transition_id = NULL, ?string $log_public = NULL, bool $access_check = TRUE): ?array {
    $elementData = $webform_submission->getElementData($element_id);

    $element = $webform_submission->getWebform()
      ->getElementDecoded($element_id);

    $workflow = $this->workflowStorage->load($element['#workflow']);
    if (!($workflow instanceof WorkflowInterface)) {
      return NULL;
    }

    $workflowType = $this->getWorkflowType($element['#workflow']);

    if (!$workflowType) {
      return NULL;
    }

    // Set initial state if no workflow data set:
    if (!$elementData) {
      $initialState = $workflowType->getInitialState();
      if ($initialState) {
        $elementData['workflow_state'] = $initialState->id();
        $elementData['workflow_state_label'] = $initialState->label();
      }
      else {
        return NULL;
      }
    }

    // If not given a transition to run directly, use the one saved by the form:
    if (!$transition_id) {
      $transition_id = isset($elementData['transition']) && $elementData['transition'] ? $elementData['transition'] : NULL;
    }

    // Check if any transitions set to run on edit if no transition set to
    // change:
    if ($elementData && !$transition_id) {
      $current_state_id = $elementData['workflow_state'] ?? NULL;
      if (!$current_state_id || !$workflowType->hasState($current_state_id)) {
        return $elementData;
      }
      $transitions = $workflowType->getTransitionsForState($current_state_id);
      foreach ($transitions as $transition) {
        $runOnEditCondition = $element['#transition_' . $transition->id() . '_run_on_edit'] ?? FALSE;
        if ($runOnEditCondition) {
          $run = TRUE;

          // By default, anyone can access, unless it must be submission owner:
          if ($runOnEditCondition === 'owner') {
            $run = $this->currentUser->id() === $webform_submission->getOwnerId();
          }

          if ($run) {
            $transition_id = $transition->id();
          }
        }
      }
    }

    // Run transition if set:
    if ($elementData && $transition_id) {
      $transition = $workflowType->getTransition($transition_id);

      if (!$transition) {
        return NULL;
      }

      if ($access_check) {
        // Confirm access to submission and transition:
        $account = $this->currentUser;
        $access = $this->checkAccessForSubmissionAndTransition(
          $workflow, $account, $webform_submission->getWebform(), $transition, NULL, $webform_submission);
        if (!$access) {
          return NULL;
        }
      }

      // Set workflow state field value to the value of the new transition:
      $elementData['transition'] = $transition_id;
      $elementData['workflow_state'] = $transition->to()->id();
      $elementData['workflow_state_label'] = $transition->to()->label();

      if ($log_public) {
        $elementData['log_public'] = $log_public;
      }

      // Update who changed and when:
      $elementData['changed_user'] = $this->currentUser->id();
      $elementData['changed_timestamp'] = strtotime('now');
    }

    // Note: workflow_state_previous is set by default on the form.
    return $elementData;
  }

  /**
   * Triggers the transition event.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   * @param string $element_id
   *   The element id.
   * @param ?array $originalElementData
   *   The original element data.
   */
  public function triggerTransitionEvent(WebformSubmissionInterface $submission, string $element_id, ?array $originalElementData = NULL) {
    $transition = $this->getTransitionFromSubmission($submission, $element_id);
    $event = new WebformSubmissionWorkflowTransitionEvent($submission, $element_id, $originalElementData, $transition);
    $this->eventDispatcher->dispatch($event, WebformSubmissionWorkflowTransitionEvent::EVENT_NAME);
  }

  /**
   * Gets the workflow type from webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   * @param string $element_id
   *   The element id.
   *
   * @return ?WorkflowTypeInterface
   *   The workflow type.
   */
  public function getWorkflowTypeFromSubmission(WebformSubmissionInterface $submission, string $element_id): ?WorkflowTypeInterface {
    $workflow_id = $submission->getWebform()->getElementDecoded($element_id)['#workflow'];
    return $this->getWorkflowType($workflow_id);
  }

  /**
   * Gets the transition id from webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   * @param string $element_id
   *   The element id.
   *
   * @return ?string
   *   The transition id.
   */
  public function getTransitionIdFromSubmission(WebformSubmissionInterface $submission, string $element_id): ?string {
    $values = $submission->getElementData($element_id);
    return $values['transition'] ?? NULL;
  }

  /**
   * Gets a transition from webform submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   * @param string $element_id
   *   The element id.
   *
   * @return \Drupal\workflows\TransitionInterface|null
   *   The transition object.
   */
  public function getTransitionFromSubmission(WebformSubmissionInterface $submission, string $element_id): ?TransitionInterface {

    $workflow_type = $this->getWorkflowTypeFromSubmission($submission, $element_id);
    if (!$workflow_type) {
      return NULL;
    }

    $transition_id = $this->getTransitionIdFromSubmission($submission, $element_id);
    if (!$transition_id) {
      return NULL;
    }

    return $workflow_type->getTransition($transition_id);
  }

  /**
   * Log transition if logging is enabled on the webform.
   *
   * @param array $element
   *   The element.
   * @param string $element_id
   *   The element id.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   * @param array $elementData
   *   The element data.
   * @param ?array $originalElementData
   *   The original element data.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function logTransition(array $element, string $element_id, WebformSubmissionInterface $webform_submission, array $elementData, ?array $originalElementData = NULL) {
    if (!isset($elementData['transition']) || !$elementData['transition']) {
      return;
    }

    $webform = $webform_submission->getWebform();

    // Log webform submissions to the 'webform_submission' log.
    if (!$webform->hasSubmissionLog()) {
      return;
    }

    $workflowType = $this->getWorkflowType($element['#workflow']);

    if (!$workflowType) {
      return;
    }

    $transition = $workflowType->getTransition($elementData['transition']);

    $context = [
      'link' => ($webform_submission->id()) ? $webform_submission->toLink($this->t('Edit'), 'edit-form')
        ->toString() : NULL,
      'webform_submission' => $webform_submission,
      'operation' => 'workflow status changed',
      '@title' => $element['#title'],
      '@transition' => $transition->label(),
      '@state_old' => $originalElementData ? $originalElementData['workflow_state_label'] : '',
      '@state_new' => $transition->to()->label(),
      '@log_admin' => isset($elementData['log_admin']) && $elementData['log_admin'] ? $this->t('Admin log message: @log', ['@log' => $elementData['log_admin']]) : '',
      '@log_public' => isset($elementData['log_public']) && $elementData['log_public'] ? $this->t('Public log message: @log', ['@log' => $elementData['log_public']]) : '',
      '@user_on_behalf_of' => '',

      /*
          We append some technical information for reference / searching.
          Sadly logger logs in Drupal aren't more fieldable.
          We store state id and plugin_id because the element or workflow could
          be modified later, which would make them not derivable from
          transition or element.

          This can be used as a unique ID to find the relevant log message for
          a workflow transition being run.
          */
      '@transition_id' => $this->t('Technical reference: [workflow:@element_id:@workflow_plugin_id:@transition_id:@new_state_id:@old_state_id]', [
        '@element_id' => $element_id,
        '@workflow_plugin_id' => $workflowType->getPluginId(),
        '@transition_id' => $transition->id(),
        '@new_state_id' => $transition->to()->id(),
        '@old_state_id' => $elementData['workflow_state'],
      ]),
    ];

    $message = '@title: transition "@transition" - status changed from "@state_old" to "@state_new".';
    $message .= (isset($elementData['log_public']) && $elementData['log_public'] ? '<br><br>@log_public' : '');
    $message .= (isset($elementData['log_admin']) && $elementData['log_admin'] ? '<br><br>@log_admin' : '');
    $message .= '<br><br>@transition_id';

    $this->loggerFactory->get('webform_submission')->notice($message, $context);
  }

  /**
   * Get workflow elements accessible to the given account.
   *
   * Returns all workflow elements from the webform, filtering out
   * those the account cannot access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   *
   * @return array
   *   The accessible workflow elements.
   *
   * @throws \Exception
   */
  public function getAccessibleWorkflowElements(AccountInterface $account, WebformInterface $webform): array {
    $workflow_elements = $this->getWorkflowElementsForWebform($webform);

    foreach ($workflow_elements as $workflow_element_id => $workflow_element) {
      if (!$this->checkUserCanAccessElement($account, $webform, $workflow_element)) {
        unset($workflow_elements[$workflow_element_id]);
      }
    }

    return $workflow_elements;
  }

  /**
   * Whether to show a minimal workflow edit form on the current page.
   *
   * Checks the current route, element settings, user access and
   * available transitions to decide whether the reduced workflow
   * form should be displayed on the submission view page.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return bool
   *   TRUE if the workflow edit form should be shown.
   *
   * @throws \Exception
   */
  public function shouldShowWorkflowEditForm(WebformSubmissionInterface $webform_submission): bool {
    $current_route = $this->routeMatch->getRouteName();
    $on_submission_view_page = in_array($current_route, [
      'entity.webform_submission.canonical',
      'entity.node.webform_submission.canonical',
      'entity.webform.user.submission',
    ]);

    if (!$on_submission_view_page) {
      return FALSE;
    }

    $show_for_any_element = FALSE;
    $account = $this->currentUser;

    $webform = $webform_submission->getWebform();
    if (!$webform) {
      return FALSE;
    }

    $workflow_elements = $this->getWorkflowElementsForWebform($webform);

    foreach ($workflow_elements as $element_id => $element) {
      if (!isset($element['#show_workflow_form_on_view']) || $element['#show_workflow_form_on_view']) {
        $has_access = $this->checkUserCanAccessElement($account, $webform, $element);
        if ($has_access) {
          $submission_element = $webform_submission->getElementData($element_id);
          if ($submission_element) {
            $available_transitions = ElementWebformWorkflowsElement::getAvailableTransitions(array_merge($element, $submission_element), $webform_submission);
          }
          else {
            $available_transitions = ElementWebformWorkflowsElement::getAvailableTransitions($element, $webform_submission);
          }

          // If setting enabled, hide completely.
          if (count($available_transitions) === 0 && isset($element['#hide_if_no_transitions']) && $element['#hide_if_no_transitions']) {
            $show_for_any_element = FALSE;
          }
          else {
            $show_for_any_element = TRUE;
            break;
          }
        }
      }
    }

    return $show_for_any_element;
  }

  /**
   * Checks whether a transition is enabled on an element.
   *
   * This does not check access, just that the webform allows
   * the transition at all.
   *
   * @param array $element
   *   The element.
   * @param string $transition_id
   *   The transition id.
   *
   * @return bool
   *   TRUE if the transition is enabled.
   */
  public function isTransitionEnabled(array $element, string $transition_id): bool {
    $enabled_key = '#access_transition_' . $transition_id . '_workflow_enabled';
    // Either key is not set (enabled by default), or key is
    // set and TRUE.
    return $element && (!isset($element[$enabled_key])) || (isset($element[$enabled_key]) && $element[$enabled_key]);
  }

  /**
   * Whether to show the workflow log for the given operation.
   *
   * Checks that the webform_submission_log module is enabled,
   * that log display is configured on at least one element, and
   * that the current user has access.
   *
   * @param string $operation
   *   The operation ('view' or 'edit').
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return bool
   *   TRUE if the log should be shown.
   *
   * @throws \Exception
   */
  public function shouldShowWorkflowLog(string $operation, WebformSubmissionInterface $webform_submission): bool {
    if (!$this->moduleHandler->moduleExists('webform_submission_log')) {
      return FALSE;
    }

    $account = $this->currentUser;

    $webform = $webform_submission->getWebform();
    if ($webform) {
      $workflow_elements = $this->getWorkflowElementsForWebform($webform);

      foreach ($workflow_elements as $element) {
        if (!isset($element['#show_log_' . $operation]) || $element['#show_log_' . $operation]) {
          $has_access = $this->checkUserCanAccessElement($account, $webform, $element, $webform_submission);
          if ($has_access) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Builds a rendered workflow log table for a submission.
   *
   * Loads submission log entries filtered to workflow operations,
   * formats them into a table, and wraps the result in a
   * collapsible details element.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return array|null
   *   Log render array, or NULL if the log module is missing.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getRenderedWorkflowLog(WebformSubmissionInterface $webform_submission): ?array {
    if (!$this->moduleHandler->moduleExists('webform_submission_log')) {
      return NULL;
    }

    $source_entity = $webform_submission->getSourceEntity();

    // Header.
    $header = [];

    $header['timestamp'] = [
      'data' => $this->t('Date'),
      'field' => 'log.timestamp',
      'sort' => 'desc',
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['uid'] = [
      'data' => $this->t('User'),
      'field' => 'user.name',
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['message'] = [
      'data' => $this->t('Message'),
      'field' => 'log.message',
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];

    // Query.
    $options = ['header' => $header, 'limit' => 50];
    $logs = $this->submissionLogManager->loadByEntities($webform_submission, $source_entity, NULL, $options);

    // Rows.
    $rows = [];
    foreach ($logs as $log) {
      if (!in_array($log->operation, [
        'workflow status changed',
        'submission created',
      ])) {
        continue;
      }

      $row = [];

      $row['timestamp'] = $this->dateFormatter
        ->format($log->timestamp, 'short');
      $row['uid'] = [
        'data' => [
          '#theme' => 'username',
          '#account' => $this->entityTypeManager->getStorage('user')->load($log->uid),
        ],
      ];
      if (isset($log->variables['@transition_id'])) {
        // In the meantime just hide the technical reference.
        $log->variables['@transition_id'] = '';
      }

      // @todo the following phpcs:ignore should be verified.
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $message = new TranslatableMarkup($log->message, $log->variables);
      $row['message'] = [
        'data' => [
          '#markup' => $message,
        ],
      ];

      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => $this->t('No log messages available.'),
    ];

    return [
      'log_fieldset' => [
        '#type' => 'details',
        '#title' => $this->t('Workflow log'),
        '#weight' => -18,
        'log' => [
          '#type' => 'markup',
          '#group' => 'log_fieldset',
          '#markup' => $this->renderer->render($build),
        ],
      ],
    ];
  }

  /**
   * Gets color options for select dropdown.
   *
   * Returns an array of color names keyed by name, suitable
   * for use as #options in a form select element.
   *
   * @return array|null
   *   Array of names keyed by name, or NULL if none configured.
   */
  public function getColorOptions(): ?array {
    $options = [];
    $values = $this->getColorOptionValues();
    if ($values) {
      foreach ($values as $name => $class) {
        $options[$name] = $name;
      }
    }
    return $options;
  }

  /**
   * Loads available color classes from configuration.
   *
   * Parses the pipe-delimited color configuration into an
   * associative array of CSS classes keyed by color name.
   *
   * @return array|null
   *   Array of CSS classes keyed by name, or NULL.
   */
  public function getColorOptionValues(): ?array {
    $config = $this->configFactory->get('webform_workflows_element.settings');
    if ($colors = $config->get('ui.color_options')) {
      $color_options = [];
      $colors = str_replace(["\r", "\n", "\n\n"], "\n", $colors);
      $lines = explode("\n", $colors);
      foreach ($lines as $line) {
        $values = explode('|', $line);
        $color_options[$values[0]] = $values[1];
      }
      return $color_options;
    }
    return NULL;
  }

  /**
   * Gets CSS color classes for workflow states.
   *
   * Builds an array of CSS classes keyed by both state ID and
   * state label, based on the element's color configuration.
   *
   * @param array $states
   *   Array of workflow states.
   * @param mixed $workflow_element
   *   The workflow element.
   *
   * @return array
   *   Array of CSS classes keyed by state ID and label.
   */
  public function getColorsForStates(array $states, $workflow_element): array {
    $colors = [];

    foreach ($states as $state) {
      if (isset($workflow_element['#state_' . $state->id() . '_color'])) {
        $color_name = $workflow_element['#state_' . $state->id() . '_color'];
        $color_options = $this->getColorOptionValues();
        $class = $color_options[$color_name] ?? '';
        $colors[$state->id()] = $class;
        $colors[$state->label()] = $class;
      }
    }
    return $colors;
  }

  /**
   * Gets all workflow access operation IDs for a webform.
   *
   * Enumerates transition and state-based access operations for
   * every workflow element on the webform, returning IDs like
   * "transition_approve" and "update_at_state_draft".
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform.
   *
   * @return array
   *   Array of access operation ID strings.
   */
  public function getWebformAccessOperations(WebformInterface $webform): array {
    $operations = [];
    $workflow_elements = $this->getWorkflowElementsForWebform($webform);

    foreach ($workflow_elements as $element) {
      $workflow_type = $this->getWorkflowTypeFromElement($element);
      if ($workflow_type) {
        foreach ($workflow_type->getTransitions() as $transition) {
          $operations[] = 'transition_' . $transition->id();
        }
      }

      foreach ($this->getStatesFromElement($element) as $state) {
        $operations[] = 'update_at_state_' . $state->id();
      }
    }
    return $operations;
  }

  /**
   * Gets the CSS color class for a specific state in an element.
   *
   * @param mixed $element
   *   The element.
   * @param mixed $state_id
   *   The state id.
   *
   * @return string|null
   *   CSS class, or NULL if no color is configured.
   */
  public function getColorClassForState($element, $state_id): ?string {
    if (isset($element['#state_' . $state_id . '_color']) && $color_name = $element['#state_' . $state_id . '_color']) {
      $options = $this->getColorOptionValues();
      return $options[$color_name] ?? NULL;
    }
    return NULL;
  }

}
