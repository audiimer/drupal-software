<?php

namespace Drupal\webform_workflows_element\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform_workflows_element\Element\WebformWorkflowsElement;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form alter hooks and form callbacks.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Constructs FormHooks.
   */
  public function __construct(
    protected readonly WebformWorkflowsManager $workflowsManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * Implements hook_form_alter().
   *
   * Adds JS for forms, modifies workflow form, shows editable workflow
   * on submission view if enabled.
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, $form_id): void {
    // Editing the workflow element itself:
    if ($form_id === 'webform_ui_element_form') {
      $form['#attached']['library'][] = 'webform_workflows_element/webform_workflows_element.edit_element';
      return;
    }

    // For the workflow edit form, show which workflows are used:
    if ($form_id === 'workflow_edit_form') {
      $this->addWebformsToWorkflowEdit($form, $form_state);
      return;
    }

    // For workflow state deletion, give option to transfer any submissions to a
    // different state:
    if ($form_id === 'workflow_state_delete_form') {
      $this->addReassignSubmissionStatesOnDelete($form, $form_state);
      return;
    }

    // Get form object.
    $form_object = $form_state->getFormObject();
    if (!$form_object) {
      return;
    }

    if (str_starts_with($form_id, 'webform_submission')
      && $form_object instanceof WebformSubmissionForm) {

      $webform = Webform::load($form['#webform_id']);
      $workflow_elements = $this->workflowsManager->getAccessibleWorkflowElements($this->currentUser, $webform);

      // Show the webform workflow as editable on the submission view:
      if (!method_exists($form_object, 'getEntity')) {
        return;
      }

      $webform_submission = $form_object->getEntity();

      if (count($workflow_elements) > 0) {
        // Attach JS.
        $form['#attached']['library'][] = 'webform_workflows_element/webform_workflows_element.states';
        $form['#attached']['library'][] = 'webform_workflows_element/webform_workflows_element.ui';
      }

      $form['#after_build'][] = [self::class, 'elementFormAfterBuild'];

      $reduce_form_to_workflow_only = $this->workflowsManager->shouldShowWorkflowEditForm($webform_submission);

      if ($reduce_form_to_workflow_only) {
        // If there are workflows this user can change:
        if (count($workflow_elements) > 0) {
          $this->reduceFormToWorkflow($form, $form_state, $workflow_elements);
        }
        else {
          $form['#access'] = FALSE;
        }
      }
    }
  }

  /**
   * Implements hook_webform_element_ELEMENT_TYPE_alter().
   */
  #[Hook('webform_element_webform_workflows_element_alter')]
  public function webformElementAlter(array &$element, FormStateInterface $form_state, array $context): void {
  }

  /**
   * Implements hook_webform_submission_form_alter().
   */
  #[Hook('webform_submission_form_alter')]
  public function webformSubmissionFormAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();

    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_object->getEntity();

    // Workflow log:
    $edit_form = !str_contains($form_id, '_add_form');
    if ($edit_form) {
      $not_canonical = $this->routeMatch
        ->getRouteName() !== 'entity.webform_submission.canonical';
      $enabled_for_form = $this->workflowsManager->shouldShowWorkflowLog('edit', $webform_submission);
      if ($not_canonical && $enabled_for_form) {
        $form = array_merge(
          $this->workflowsManager->getRenderedWorkflowLog($webform_submission),
          $form
        );
      }
    }
  }

  /**
   * After-build callback for workflow element form.
   *
   * Ensures the transition options are correct after the form is built.
   */
  public static function elementFormAfterBuild($form, &$form_state) {
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_object->getEntity();

    if (!isset($form['elements'])) {
      return $form;
    }

    $available_transitions_total = 0;

    foreach (Element::children($form['elements']) as $element_id) {
      $element = $form['elements'][$element_id];
      if (!isset($element['#type']) || $element['#type'] !== 'webform_workflows_element') {
        continue;
      }

      if (!isset($form['elements'][$element_id]['workflow_fieldset'])) {
        continue;
      }

      $blank_option = '';
      $options = isset($form['elements'][$element_id]['workflow_fieldset']['transition']) ? $form['elements'][$element_id]['workflow_fieldset']['transition']['#options'] : ['' => $blank_option];

      // Limit to available transitions.
      // We can't do this in src/Element/WebformWorkflowsElement.php
      // getCompositeElements because it gets in a loop as it tries to load the
      // submission while building the submission.
      $available_transitions = WebformWorkflowsElement::getAvailableTransitions($element, $webform_submission);

      $available_transitions_total += count($available_transitions);

      $new_options = [];
      $new_options[''] = $options[''] ?? $blank_option;
      foreach ($available_transitions as $key => $transition) {
        $new_options[$key] = $transition->label();
      }

      $form['elements'][$element_id]['workflow_fieldset']['transition']['#options'] = $new_options;

      // Allow showing a transition note if, e.g., access checks have made
      // transitions not available for some reason:
      $context = [
        'element' => $element,
        'available_transitions' => $available_transitions,
        'webform_submission' => $webform_submission,
        'form' => $form,
        'form_state' => $form_state,
      ];
      $transition_message = NULL;
      \Drupal::moduleHandler()
        ->alter(['webform_workflow_element_transition_note'], $transition_message, $context);

      if ($transition_message) {
        $form['elements'][$element_id]['workflow_fieldset'][] = [
          'transition_note' => [
            '#type' => 'webform_message',
            '#message_message' => $transition_message,
            '#message_type' => 'info',
            '#message_close' => TRUE,
            '#message_storage' => WebformMessage::STORAGE_SESSION,
          ],
        ];
      }
    }

    // phpcs:disable
    // @todo only show button if transition value changed:
    // $form['actions']['submit']['#states'] = [
    //   'visible' => [
    //     ':input[name$="transition]"]' => ['!value' => ''],
    //   ],
    // ];
    // phpcs:enable
    // Rename button as it's only a workflow form
    // Or hide if no transitions:
    if ($form['#workflow_form_reduced'] ?? FALSE) {
      if ($available_transitions_total) {
        $form['actions']['submit']['#value'] = t('Save workflow');
      }
      else {
        unset($form['actions']['submit']);
      }

      // If we are specifying a workflow element in the query string,
      // rename action button:
      $workflow_element_id = \Drupal::request()->query->get('workflow_element');
      if ($workflow_element_id) {
        // Set button text to the transition name:
        // Setting the default transition from the query string is handled in:
        // src/Element/WebformWorkflowsElement.php.
        $workflow_id = $form['elements'][$workflow_element_id]['#workflow'];
        $workflows_manager = \Drupal::service('webform_workflows_element.manager');
        $workflow_type = $workflows_manager->getWorkflowType($workflow_id);
        $preset_transition_id = \Drupal::request()->query->get('transition');
        if ($preset_transition_id && $workflow_type && $transition = $workflow_type->getTransition($preset_transition_id)) {
          $form['actions']['submit']['#value'] = $transition->label();
        }
      }
    }

    return $form;
  }

  /**
   * Submit handler: reassign submissions on workflow state deletion.
   *
   * Reassigns any workflow element value to a different state
   * when a state is deleted and the user selects to.
   */
  public static function reassignSubmissionStatesOnDeleteSubmit($form, $form_state): void {
    $reassign = $form_state->getValue('reassign');
    if (!$reassign) {
      return;
    }

    $workflow = \Drupal::routeMatch()->getParameter('workflow');

    // Work out state values:
    $states = $workflow->getTypePlugin()->getStates();
    $new_state_id = $form_state->getValue('reassign_state');
    if (!$new_state_id) {
      return;
    }
    $new_state_label = $states[$new_state_id] ? $states[$new_state_id]->label() : $new_state_id;

    $previous_state_id = \Drupal::routeMatch()->getParameter('workflow_state');
    if (!$previous_state_id) {
      return;
    }
    $previous_state_label = $states[$previous_state_id] ? $states[$previous_state_id]->label() : $previous_state_id;

    // Load all webforms which use this workflow:
    $workflows_manager = \Drupal::service('webform_workflows_element.manager');
    $webform_ids = $workflows_manager->getWebformsWithWorkflowElementsIds($workflow->id());
    foreach ($webform_ids as $webform_id) {
      // Load any elements using this workflow:
      $workflow_elements = $workflows_manager->getWorkflowElementsForWebform(Webform::load($webform_id));

      foreach ($workflow_elements as $workflow_element_id => $workflow_element) {
        if ($workflow_element['#workflow'] !== $workflow->id()) {
          continue;
        }

        // Update submission with new state:
        $query = Database::getConnection()->update('webform_submission_data')
          ->fields([
            'value' => $new_state_id,
          ])
          ->condition('name', $workflow_element_id)
          ->condition('property', 'workflow_state')
          ->condition('value', $previous_state_id)
          ->condition('webform_id', $webform_id);
        $query->execute();

        // Update label of state:
        $query = Database::getConnection()->update('webform_submission_data')
          ->fields([
            'value' => $new_state_label,
          ])
          ->condition('name', $workflow_element_id)
          ->condition('property', 'workflow_state_label')
          ->condition('value', $previous_state_label)
          ->condition('webform_id', $webform_id);
        $query->execute();
      }

      // Clear cache for webform:
      Cache::invalidateTags(['config:webform.webform.' . $webform_id]);
    }

    // Clear cache for all submissions:
    Cache::invalidateTags(['webform_submission_list']);

    \Drupal::messenger()
      ->addStatus(t('Moved any submissions using "@workflow_label" workflow, at state "@previous_state_label", to "@new_state_label".', [
        '@workflow_label' => $workflow->label(),
        '@previous_state_label' => $previous_state_label,
        '@new_state_label' => $new_state_label,
      ]));

  }

  /**
   * Renders which webforms use a specific workflow on the edit form.
   */
  private function addWebformsToWorkflowEdit(array &$form, FormStateInterface $form_state): void {

    $workflow = $form_state->getFormObject()->getEntity();
    $workflow_type = $workflow->getTypePlugin();
    if ($workflow_type->getPluginId() !== 'webform_workflows_element') {
      return;
    }

    $webforms_with_workflows = $this->workflowsManager->getWebformsWithWorkflowElements($workflow->id());
    $links = [];
    foreach ($webforms_with_workflows as $webform) {
      $links[] = $webform->toLink()->toString();
    }

    $form['workflows_in_use'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => 'Webforms using this workflow',
    ];

    if (count($links) > 0) {
      $form['workflows_in_use'][] = [
        '#type' => 'markup',
        '#markup' => '<ul><li>' . implode('</li><li>', $links) . '</li></ul>',
      ];
    }
    else {
      $form['workflows_in_use'][] = [
        '#type' => 'markup',
        '#markup' => $this->t('No webforms use this workflow.'),
      ];
    }
  }

  /**
   * Adds reassign-state UI to the workflow state delete form.
   */
  private function addReassignSubmissionStatesOnDelete(array &$form, FormStateInterface $form_state): void {
    $workflow = $this->routeMatch->getParameter('workflow');
    $workflow_type = $workflow->getTypePlugin();

    if ($workflow_type->getPluginId() !== 'webform_workflows_element') {
      return;
    }

    $state_id = $this->routeMatch->getParameter('workflow_state');

    $states = $workflow_type->getStates();
    $options = [];
    foreach ($states as $state) {
      if ($state->id() !== $state_id) {
        $options[$state->id()] = $state->label();
      }
    }
    $form['reassign_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => 'Reassign submissions on delete',
      'reassign' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Reassign any submission workflow elements at this current state to another workflow state?'),
      ],
      'reassign_state' => [
        '#type' => 'select',
        '#title' => $this->t('Select state'),
        '#options' => $options,
        '#states' => [
          'visible' => [
            ':input[name="reassign"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ];

    $form['#submit'] = array_merge([[self::class, 'reassignSubmissionStatesOnDeleteSubmit']], $form['#submit']);
  }

  /**
   * Reduces a submission form to show only workflow elements.
   */
  private function reduceFormToWorkflow(array &$form, FormStateInterface $form_state, array $workflow_elements): void {
    // Get all the accessible workflow elements in the form hierarchy.
    $workflow_elements_keys = array_keys($workflow_elements);
    $form_workflow_elements = self::findFormElements(
      $form['elements'],
      function ($key, $element) use ($workflow_elements_keys) {
        return in_array($key, $workflow_elements_keys);
      }
    );
    // Remove all existing elements from the form.
    foreach (Element::children($form['elements']) as $key) {
      unset($form['elements'][$key]);
    }
    // Add back the accessible workflow elements only.
    foreach ($form_workflow_elements as $element_id => $workflow_element) {
      // This is necessary since the workflow element is not guaranteed to be
      // present on the last page of the webform which has the Save button.
      $workflow_element['#access'] = TRUE;
      $form['elements'][$element_id] = $workflow_element;
    }

    // Cleanup the remaining unnecessary form items.
    unset($form['navigation']);
    unset($form['information']);
    unset($form['progress']);
    unset($form['actions']['cards_prev']);
    unset($form['actions']['cards_next']);
    unset($form['actions']['wizard_prev']);
    unset($form['actions']['wizard_next']);
    unset($form['webform_message']);
    unset($form['log_fieldset']);
    unset($form['author']);
    unset($form['required']);

    // If we are specifying a workflow element in the query string,
    // hide other workflow elements.
    $request = $this->requestStack->getCurrentRequest();
    $workflow_element_id = $request->query->get('workflow_element');
    if ($workflow_element_id) {
      foreach ($workflow_elements as $element_id => $workflow_element) {
        // If an element is specified in the query string, hide any other
        // workflow elements.
        if ($workflow_element_id !== $element_id) {
          unset($form['elements'][$element_id]);
        }
      }
    }

    $form['#workflow_form_reduced'] = TRUE;
  }

  /**
   * Recursively collects form elements matching a condition.
   *
   * @param array $elements
   *   The form or form subtree.
   * @param callable $matcher
   *   Callback ($key, $element): bool — return TRUE to collect.
   *
   * @return array
   *   Matching elements keyed by element key.
   */
  private static function findFormElements(array &$elements, callable $matcher): array {
    $matches = [];
    foreach (Element::children($elements) as $key) {
      $element = &$elements[$key];
      if ($matcher($key, $element)) {
        $matches[$key] = $element;
      }
      $child_matches = self::findFormElements($element, $matcher);
      $matches = array_merge($matches, $child_matches);
    }
    return $matches;
  }

}
