<?php

namespace Drupal\webform_workflows_element_views\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\webform\Entity\Webform;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter based on the value of a composite of a webform submission.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("webform_workflows_element_state")
 */
class WebformWorkflowsElementState extends FilterPluginBase {

  /**
   * The webform workflows manager.
   *
   * @var \Drupal\webform_workflows_element\Service\WebformWorkflowsManager
   */
  protected WebformWorkflowsManager $workflowsManager;

  /**
   * The Views join handler manager.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected ViewsHandlerManager $joinManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->workflowsManager = $container->get('webform_workflows_element.manager');
    $instance->joinManager = $container->get('plugin.manager.views.join');
    return $instance;
  }

  /**
   * Applying query filter.
   *
   * If you turn on views query debugging you should see
   * these clauses applied. If the filter is optional, and nothing is selected,
   * this code will never be called.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function query() {
    $this->ensureMyTable();

    $stateIds = is_array($this->value) ? array_filter($this->value) : [];

    if (empty($stateIds)) {
      return;
    }

    $configuration = [
      'table' => 'webform_submission_data',
      'field' => 'sid',
      'left_table' => $this->relationship ?: 'webform_submission',
      'left_field' => 'sid',
      'adjusted' => TRUE,
      'type' => 'LEFT',
    ];

    $alias = 'wsd_state_' . $this->getFilterId();

    /** @var \Drupal\views\Plugin\views\join\JoinPluginBase $join */
    $join = $this->joinManager
      ->createInstance('standard', $configuration);
    $this->query->addRelationship($alias, $join, 'webform_submission_data');

    $this->query->addWhere('state', $alias . '.webform_id', $this->options['webform_id']);
    $this->query->addWhere('state', $alias . '.name', $this->options['workflow_element_id']);
    $this->query->addWhere('state', $alias . '.property', 'workflow_state');
    $this->query->addWhere('state', $alias . '.value', $stateIds, 'IN');

    // @todo this appears to do nothing
    $this->query->addGroupBy('webform_submission.sid');
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    if (!$this->options['webform_id'] || !$this->options['workflow_element_id']) {
      return '';
    }

    $webform = Webform::load($this->options['webform_id']);
    $webform_text = $this->t('webform: @webform', ['@webform' => $webform->label()]);
    $element_text = $this->t('workflow element: @element', ['@element' => $this->options['workflow_element_id']]);
    return $webform_text . ', ' . $element_text;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    if (!empty($this->options['exposed']) || !$this->options['webform_id'] || !$this->options['workflow_element_id']) {
      return;
    }

    $webform = Webform::load($this->options['webform_id']);
    $workflowElement = $webform->getElement($this->options['workflow_element_id']);
    $workflow = Workflow::load($workflowElement['#workflow']);
    $states = $workflow->getTypePlugin()->getStates();

    $options = [];
    foreach ($states as $state_id => $state) {
      $options[$state_id] = $state->label();
    }

    $form['value'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Workflow state(s)'),
      '#default_value' => $this->value,
      '#multiple' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['webform_id'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'webform',
      '#title' => $this->t('Webform'),
      '#default_value' => $this->options['webform_id'] ? Webform::load($this->options['webform_id']) : NULL,
      '#required' => TRUE,
    ];

    $form['workflow_element_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Workflow element ID'),
      '#default_value' => $this->options['workflow_element_id'] ?: 'workflow',
      '#required' => TRUE,
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    $form['value'] = !empty($form['value']) ? $form['value'] : [];
    parent::buildExposedForm($form, $form_state);

    if (!$this->options['webform_id'] || !$this->options['workflow_element_id']) {
      return;
    }

    $filter_id = $this->getFilterId();
    // Field which really filters.
    $form[$filter_id] = [
      '#type' => 'hidden',
      '#value' => '',
    ];

    $webform = Webform::load($this->options['webform_id']);
    $workflowElement = $webform->getElement($this->options['workflow_element_id']);
    $workflow = Workflow::load($workflowElement['#workflow']);
    $states = $workflow->getTypePlugin()->getStates();

    $options = [
      '' => $this->t('All'),
    ];
    foreach ($states as $state_id => $state) {
      $options[$state_id] = $state->label();
    }

    $form[$filter_id . ':states'] = [
      '#type' => 'select',
      '#title' => $this->options["expose"]["label"] ?? $this->t('Workflow state(s)'),
      '#description' => $this->options["expose"]["description"] ?? NULL,
      '#multiple' => $this->options['expose']['multiple'] ?? TRUE,
      '#tags' => TRUE,
      '#options' => $options,
      '#attributes' => [
        'class' => ['webform_workflows_element_filter_states'],
      ],
      '#default_value' => !empty($this->value['states']) ? $this->value['states'] : '',
    ];

    $colors = $this->workflowsManager->getColorsForStates($states, $workflowElement);

    if (count($colors) > 0) {
      $form['#attached']['library'][] = 'webform_workflows_element/default_colors';
      $form['#attached']['library'][] = 'webform_workflows_element/webform_workflows_element.filters';
      $form['#attached']['drupalSettings']['webform_workflows_element']['colors'] = $colors;
    }
  }

  /**
   * This method returns the ID of the fake field which contains this plugin.
   *
   * It is important to put this ID to the exposed field of this plugin for the
   * following reasons: (a) To avoid problems with
   * FilterPluginBase::acceptExposedInput function (b) To allow this filter to
   * be printed on twig templates with {{ form.date_range_picker_filter }}
   *
   * @return string
   *   ID of the field which contains this plugin.
   */
  private function getFilterId(): string {
    return $this->options['expose']['identifier'];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    if (empty($this->options['expose'])) {
      return TRUE;
    }

    $input[$this->options['expose']['identifier']] = $input[$this->getFilterId() . ':states'] ?? '';

    return parent::acceptExposedInput($input);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();

    $options['webform_id'] = ['default' => ''];
    $options['workflow_element_id'] = ['default' => ''];

    return $options;
  }

}
