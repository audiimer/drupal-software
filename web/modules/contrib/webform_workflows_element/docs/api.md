# Developer API

## Manager service

The `webform_workflows_element.manager` service
(`WebformWorkflowsManager`) is the main entry point for programmatic
interaction with workflow elements.

```php
$manager = \Drupal::service('webform_workflows_element.manager');
```

### Key methods

#### Workflow and state retrieval

| Method | Description |
|--------|-------------|
| `getWorkflow(string $workflowId)` | Load a workflow entity by ID. |
| `getWorkflowType(string $workflowId)` | Get the workflow type plugin for a workflow. |
| `getWorkflowTypeFromElement(array $element)` | Get the workflow type from an element's `#workflow` property. |
| `getStatesFromElement(array $element)` | Get all states for an element's workflow. |
| `getInitialStateForElement(array $element)` | Get the initial state for an element's workflow. |
| `getStateFromElementAndId($element, $id)` | Get a specific state by ID, or `NULL`. |

#### Transitions

| Method | Description |
|--------|-------------|
| `getTransitionsForWorkflow(string $workflow_id)` | Get all transitions for a workflow (not filtered by state or access). |
| `getAvailableTransitionsForWorkflow(string $workflowId, ?string $currentStateId, ?AccountInterface $account, ?WebformInterface $webform, $webform_submission)` | Get transitions available from a given state, optionally filtered by user access. |
| `getTransitionFromSubmission(WebformSubmissionInterface $submission, string $element_id)` | Get the last transition object from a submission's element data. |
| `runTransition(WebformSubmissionInterface &$webform_submission, string $element_id, ?string $transition_id, ?string $log_public, bool $access_check)` | Execute a transition on a submission. Does **not** save the submission — you must call `$webform_submission->save()` afterwards. Returns `TRUE` if the transition was valid. |
| `runTransitionOnElementValue(WebformSubmissionInterface $webform_submission, string $element_id, ?string $transition_id, ?string $log_public, bool $access_check)` | Compute the new element data after a transition without modifying the submission. Returns the updated data array or `NULL`. |

#### Access control

| Method | Description |
|--------|-------------|
| `checkUserCanAccessElement(AccountInterface $account, WebformInterface $webform, array $workflow_element, $webform_submission)` | Check if a user can see and use a workflow element. |
| `checkAccessForSubmissionAndTransition(WorkflowInterface $workflow, AccountInterface $account, WebformInterface $webform, TransitionInterface $transition, $currentState, $webformSubmission)` | Check if a user can perform a specific transition. |
| `checkAccessToUpdateBasedOnState(AccountInterface $account, WebformSubmissionInterface $webform_submission, array $element, string $state_id)` | Check state-based update access override. |
| `checkAccessForWorkflowAccessRules(array $element, EntityInterface $webform, AccountInterface $account, string $access_id, string $rule_id)` | Low-level access check against webform access rules. |

#### Webform discovery

| Method | Description |
|--------|-------------|
| `getWebformsWithWorkflowElements(?string $workflow_id)` | Load all webforms that contain workflow elements, optionally filtered by workflow ID. |
| `getWorkflowElementsForWebform(WebformInterface $webform)` | Get all workflow elements for a given webform. |
| `getAccessibleWorkflowElements(AccountInterface $account, WebformInterface $webform)` | Get workflow elements the account can access (filters by `checkUserCanAccessElement`). |
| `getWebformAccessOperations(WebformInterface $webform)` | Enumerate all workflow access operation IDs (e.g. `transition_approve`, `update_at_state_draft`) for a webform. |

#### UI helpers

| Method | Description |
|--------|-------------|
| `shouldShowWorkflowEditForm(WebformSubmissionInterface $webform_submission)` | Whether the reduced workflow form should appear on the submission view page. |
| `shouldShowWorkflowLog(string $operation, WebformSubmissionInterface $webform_submission)` | Whether the workflow log should be shown for the given operation (`'view'` or `'edit'`). |
| `getRenderedWorkflowLog(WebformSubmissionInterface $webform_submission)` | Build a rendered workflow log table wrapped in a collapsible details element. |
| `isTransitionEnabled(array $element, string $transition_id)` | Check whether a transition is enabled on an element (not access — just the on/off toggle). |

#### Color helpers

| Method | Description |
|--------|-------------|
| `getColorOptions()` | Get color names as `#options` for a select element. |
| `getColorOptionValues()` | Get CSS classes keyed by color name from config. |
| `getColorsForStates(array $states, $workflow_element)` | Get CSS classes keyed by state ID and label for an element. |
| `getColorClassForState($element, $state_id)` | Get the CSS class for a specific state, or `NULL`. |

#### Events and logging

| Method | Description |
|--------|-------------|
| `triggerTransitionEvent(WebformSubmissionInterface $submission, string $element_id, ?array $originalElementData)` | Dispatch a `WebformSubmissionWorkflowTransitionEvent`. |
| `logTransition(array $element, string $element_id, WebformSubmissionInterface $webform_submission, array $elementData, ?array $originalElementData)` | Log a transition to the webform submission log (if logging is enabled). |

## Transition event

When a transition is executed, a
`WebformSubmissionWorkflowTransitionEvent` is dispatched with the event name
`webform_submission_workflow_transition`.

```php
use Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent;
```

### Event properties

| Property | Type | Description |
|----------|------|-------------|
| `$submission` | `WebformSubmissionInterface` | The webform submission being transitioned. |
| `$transition` | `?TransitionInterface` | The transition that was executed, or `NULL`. |
| `$elementId` | `string` | The machine name of the workflow element. |
| `$originalElementData` | `array` | The element's composite values before the transition. |

### Subscribing to the event

```php
use Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MySubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      WebformSubmissionWorkflowTransitionEvent::EVENT_NAME => 'onTransition',
    ];
  }

  public function onTransition(WebformSubmissionWorkflowTransitionEvent $event): void {
    $submission = $event->submission;
    $transition = $event->getTransition();
    $element_id = $event->elementId;
    // React to the transition...
  }

}
```

## Alter hooks

The module provides three alter hooks for overriding access and adding
transition notes. These are defined in `webform_workflows_element.api.php`.

### hook_webform_workflow_element_access_alter

Override access to a workflow element.

```php
function hook_webform_workflow_element_access_alter(
  ?bool &$access,
  array $context,
): void {
  // $context contains:
  //   'webform', 'element_plugin', 'account',
  //   'workflow_element', 'webform_submission'.
  if ($context['account']->hasPermission('bypass workflow access')) {
    $access = TRUE;
  }
}
```

Set `$access` to `TRUE` to grant access, `FALSE` to deny, or leave it `NULL`
to fall through to the default access checks.

### hook_webform_workflow_element_transition_access_alter

Override access to a specific transition.

```php
function hook_webform_workflow_element_transition_access_alter(
  ?bool &$access,
  array $context,
): void {
  // $context contains:
  //   'workflow', 'webform', 'state', 'account',
  //   'webform_submission', 'transition'.
}
```

### hook_webform_workflow_element_transition_note_alter

Add a note displayed alongside transitions on the workflow widget.

```php
function hook_webform_workflow_element_transition_note_alter(
  ?string &$transitionMessage,
  array $context,
): void {
  // $context contains:
  //   'webform_submission', 'form', 'form_state'.
}
```

## Deprecated functions (2.1.0 → 3.0.0)

The following procedural functions in `webform_workflows_element.module` were
deprecated in 2.1.0 and will be removed in 3.0.0. Use the manager service
methods instead.

| Deprecated function | Replacement |
|---------------------|-------------|
| `webform_workflows_element_get_available_elements()` | `WebformWorkflowsManager::getAccessibleWorkflowElements()` |
| `webform_workflows_element_show_workflow_edit_form()` | `WebformWorkflowsManager::shouldShowWorkflowEditForm()` |
| `webform_workflows_element_check_transition_enabled()` | `WebformWorkflowsManager::isTransitionEnabled()` |
| `webform_workflows_element_get_webform_access_operations()` | `WebformWorkflowsManager::getWebformAccessOperations()` |
| `webform_workflows_element_check_show_log()` | `WebformWorkflowsManager::shouldShowWorkflowLog()` |
| `webform_workflows_element_get_rendered_log()` | `WebformWorkflowsManager::getRenderedWorkflowLog()` |
| `webform_workflows_element_get_color_options()` | `WebformWorkflowsManager::getColorOptions()` |
| `webform_workflows_element_get_color_options_values()` | `WebformWorkflowsManager::getColorOptionValues()` |
| `webform_workflows_element_get_colors_for_states()` | `WebformWorkflowsManager::getColorsForStates()` |
| `webform_workflows_element_get_color_class_for_state_from_element()` | `WebformWorkflowsManager::getColorClassForState()` |
| `webform_workflows_element_element_form_after_build()` | `FormHooks::elementFormAfterBuild()` |
| `webform_workflows_element_add_reassign_submission_states_on_delete_submit()` | `FormHooks::reassignSubmissionStatesOnDeleteSubmit()` |
| `webform_workflows_element_webform_submission_filter_form_submit()` | `ResultsFilterHooks::filterFormSubmit()` |

## Element data structure

Each workflow element stores a composite value on the submission:

```php
[
  'workflow_state'          => 'approved',        // Current state ID.
  'workflow_state_label'    => 'Approved',         // Current state label.
  'workflow_state_previous' => 'in_review',        // Previous state ID.
  'transition'              => 'approve',          // Last transition ID.
  'changed_user'            => 42,                 // UID of last changer.
  'changed_timestamp'       => 1700000000,         // Unix timestamp.
  'log_public'              => 'Looks good.',      // Public log message.
  'log_admin'               => 'Fast-tracked.',    // Admin log message.
]
```

Access this data via:

```php
$data = $submission->getElementData('my_workflow_element');
$current_state = $data['workflow_state'];
```
