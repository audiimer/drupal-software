<?php

namespace Drupal\webform_workflows_element\Hook;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformEntityReferenceManagerInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Results filter and webform_better_results hooks.
 */
class ResultsFilterHooks {

  /**
   * Constructs ResultsFilterHooks.
   */
  public function __construct(
    protected readonly WebformWorkflowsManager $workflowsManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly RequestStack $requestStack,
    protected readonly WebformEntityReferenceManagerInterface $entityReferenceManager,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Adds a workflow state filter to the submission results form
   * when webform_better_results is not installed.
   */
  #[Hook('form_webform_submission_filter_form_alter')]
  public function formWebformSubmissionFilterFormAlter(&$form, FormStateInterface $form_state, $form_id): void {
    // If enabled, Webform Better Results will automatically pick up the hooks
    // we use. If not enabled, do it manually from within this module.
    if ($this->moduleHandler->moduleExists('webform_better_results')) {
      return;
    }

    $webform = NULL;

    $webform_id = $this->routeMatch
      // There must be a better way to get the webform...
      ->getRawParameter('webform');
    if ($webform_id) {
      $webform = Webform::load($webform_id);
    }
    else {
      if ($nid = $this->routeMatch->getRawParameter('node')) {
        if ($node = Node::load($nid)) {
          // Check that the node has a webform field that has been populated.
          $webform = $this->entityReferenceManager->getWebform($node);
        }
      }
    }

    if (!$webform) {
      return;
    }

    $form['#submit'][] = [self::class, 'filterFormSubmit'];

    $data = [
      'form' => $form,
      'form_state' => $form_state,
      'webform' => $webform,
    ];
    $this->webformBetterResultsFormAlter($data);
    $form = $data['form'];

    // Move buttons to end.
    $buttons = ['submit', 'reset'];
    foreach ($buttons as $button_id) {
      if (isset($form['filter'][$button_id])) {
        $button = $form['filter'][$button_id];
        unset($form['filter'][$button_id]);
        $form['filter'][$button_id] = $button;
      }
    }
  }

  /**
   * Implements hook_webform_better_results_query_alter().
   *
   * Filters submissions by workflow state.
   */
  #[Hook('webform_better_results_query_alter')]
  public function webformBetterResultsQueryAlter(&$data, $query_strings, $webform): void {
    static::applyWorkflowQueryFilters($data, $query_strings, $webform);
  }

  /**
   * Filters a submission query by workflow state.
   *
   * Extracts workflow element IDs from the query string parameters
   * and adds sub-query conditions to restrict results to
   * submissions in the specified workflow states.
   *
   * @param array $data
   *   The query data array with 'query' and 'submission_storage'.
   * @param array $query_strings
   *   The query string parameters.
   * @param \Drupal\webform\WebformInterface|null $webform
   *   The webform entity, or NULL when the list builder is rendering the
   *   global webform submission entity list (no webform context — e.g.
   *   /admin/structure/webform/submissions/manage).
   */
  public static function applyWorkflowQueryFilters(array &$data, array $query_strings, ?WebformInterface $webform): void {
    /** @var \Drupal\Core\Entity\Query\QueryInterface $query */
    $query = $data['query'];
    /** @var \Drupal\webform\WebformSubmissionStorageInterface $submission_storage */
    $submission_storage = $data['submission_storage'];

    // Filter by workflow fields:
    $query_workflow_elements = [];
    foreach ($query_strings as $key => $value) {
      if (str_starts_with($key, 'workflow-')) {
        $query_workflow_elements[str_replace('workflow-', '', $key)] = $value;
      }
    }
    foreach ($query_workflow_elements as $element_id => $value) {
      $sub_query = Database::getConnection()
        ->select('webform_submission_data', 'sd')
        ->fields('sd', ['sid'])
        ->condition('name', $element_id)
        ->condition('property', 'workflow_state')
        ->condition('value', $value);
      $submission_storage->addQueryConditions($sub_query, $webform);
      $query->condition('sid', $sub_query, 'IN');
    }

    $data['query'] = $query;
    $data['submission_storage'] = $submission_storage;
  }

  /**
   * Implements hook_webform_better_results_form_alter().
   *
   * Adds workflow state filter select elements to the filter form.
   */
  #[Hook('webform_better_results_form_alter')]
  public function webformBetterResultsFormAlter(array &$data): void {
    $webform = $data['webform'];

    $workflow_elements = $this->workflowsManager->getWorkflowElementsForWebform($webform);

    $form = $data['form'];
    $form_state = $data['form_state'];
    $form_state->set('workflow_elements', $workflow_elements);

    $colors = [];

    foreach ($workflow_elements as $element_id => $element) {
      $workflow_type = $this->workflowsManager->getWorkflowType($element['#workflow']);
      $states = $workflow_type ? $workflow_type->getStates() : [];
      $options = ['' => ' - Any - '];
      foreach ($states as $state) {
        $options[$state->id()] = $state->label();
      }
      $colors = array_merge($colors, $this->workflowsManager->getColorsForStates($states, $element));

      $form['filter']['workflow-' . $element_id] = [
        '#type' => 'select',
        // phpcs:ignore
        '#title' => t($element['#title']),
        '#title_display' => 'before',
        '#options' => $options,
        '#attributes' => [
          'class' => ['webform_workflows_element_filter_states'],
        ],
        '#default_value' => $this->requestStack->getCurrentRequest()->query->get('workflow-' . $element_id),
      ];

      if (count($colors) > 0) {
        $form['#attached']['library'][] = 'webform_workflows_element/default_colors';
        $form['#attached']['library'][] = 'webform_workflows_element/webform_workflows_element.filters';
        $form['#attached']['drupalSettings']['webform_workflows_element']['colors'] = $colors;
      }
    }

    $data = [
      'form' => $form,
      'form_state' => $form_state,
      'webform' => $webform,
    ];
  }

  /**
   * Implements hook_webform_better_results_submit_alter().
   *
   * Adds workflow filter values to the redirect query string.
   */
  #[Hook('webform_better_results_submit_alter')]
  public function webformBetterResultsSubmitAlter(array &$data): void {
    $form_state = $data['form_state'];
    $query_strings = $data['query'];
    foreach ($form_state->get('workflow_elements') as $element_id => $element) {
      if ($form_state->getValue('workflow-' . $element_id)) {
        $query_strings['workflow-' . $element_id] = $form_state->getValue('workflow-' . $element_id);
      }
    }
    $data['query'] = $query_strings;
  }

  /**
   * Submit handler for the submission filter form.
   *
   * Adds workflow state filter values to the redirect query string
   * when webform_better_results is not installed.
   */
  public static function filterFormSubmit(array $form, FormStateInterface $form_state): void {
    // If enabled, Webform Better Results will automatically pick up the hooks
    // we use. If not enabled, do it manually from within this module.
    if (\Drupal::moduleHandler()->moduleExists('webform_better_results')) {
      return;
    }

    $redirect = $form_state->getRedirect();
    $query = $redirect->getOptions()['query'];

    $data = [
      'form' => $form,
      'form_state' => $form_state,
      'query' => $query,
    ];

    \Drupal::service(self::class)->webformBetterResultsSubmitAlter($data);

    $query = $data['query'];
    $form_state = $data['form_state'];

    $query = array_filter($query);
    $form_state->setRedirect($redirect->getRouteName(), $redirect->getRouteParameters(), [
      'query' => $query,
    ]);
  }

}
