<?php

namespace Drupal\webform_workflows_element\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays workflow summary.
 */
class WorkflowsSummaryController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The webform workflows manager service.
   *
   * @var \Drupal\webform_workflows_element\Service\WebformWorkflowsManager
   */
  protected $workflowsManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->renderer = $container->get('renderer');
    $instance->workflowsManager = $container->get('webform_workflows_element.manager');
    return $instance;
  }

  /**
   * Return summaries of the transitions for each webform workflow.
   *
   * @param \Drupal\webform\WebformInterface|null $webform
   *   The webform object.
   *
   * @return array
   *   Render array.
   */
  public function renderSummary(?WebformInterface $webform = NULL): array {
    $build = [];

    $build['intro'] = [
      '#type' => 'markup',
      '#prefix' => '<p>',
      '#markup' => $this->t("This shows all available webform workflows. Note that you can always disable transitions via the element's access settings, so you can use a workflow that has more transitions/states than you actually need."),
      '#suffix' => '</p>',
    ];

    $buildWorkflows = [];
    $workflows = Workflow::loadMultipleByType('webform_workflows_element');
    foreach ($workflows as $workflow) {
      $buildWorkflows[$workflow->id()] = [
        '#type' => 'details',
        '#title' => $workflow->label(),
      ];

      $buildWorkflows[$workflow->id()][] = $this->renderWorkflowSummaryTable($workflow);
    }

    usort($buildWorkflows, function ($a, $b) {
      return strcmp($a['#title'], $b['#title']);
    });

    return array_merge($build, $buildWorkflows);
  }

  /**
   * Render a summary table for a workflow.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow object.
   * @param \Drupal\webform\WebformInterface|null $webform
   *   The webform object.
   * @param string|null $element_id
   *   The element id.
   *
   * @return array
   *   Render array of table.
   */
  public function renderWorkflowSummaryTable(WorkflowInterface $workflow, ?WebformInterface $webform = NULL, ?string $element_id = NULL): array {
    $rows_enabled = [];
    $rows_disabled = [];

    $transitions = $workflow->getTypePlugin()->getTransitions();

    $element = NULL;
    if ($element_id && $webform) {
      $element = $webform->getElementDecoded($element_id);
    }

    if (!$element) {
      return [];
    }

    foreach ($transitions as $transition) {
      $enabled = $this->workflowsManager->isTransitionEnabled($element, $transition->id());

      $fromStates = $transition->from();
      $fromStateNames = [];
      foreach ($fromStates as $state) {
        $fromStateNames[] = $this->renderState($state, $element);
      }

      $from = [
        '#theme' => 'item_list',
        '#items' => $fromStateNames,
        '#context' => ['list_style' => 'comma-list'],
      ];
      $from = $this->renderer->render($from);

      $to = $transition->to();
      $toMarkup = $this->renderState($to, $element);
      $toRendered = $this->renderer->render($toMarkup);

      $row = [
        'Transition' => $transition->label(),
        'From states' => $from,
        'To state' => $toRendered,
      ];

      if ($webform) {
        // Access:
        $access = $this->getTransitionAccessSummary($transition, $webform, $element_id);
        $element_access_list = [
          '#theme' => 'item_list',
          '#prefix' => $access['transition_access'] ? $this->t('<p><b>Element access</b></p>') : NULL,
          '#list_type' => 'ul',
          '#items' => $access['element_access'],
        ];
        $transition_access_list = $access['transition_access'] ? [
          '#theme' => 'item_list',
          '#prefix' => $this->t('<p><b>Transition access</b></p>'),
          '#list_type' => 'ul',
          '#items' => $access['transition_access'],
        ] : [];
        $access_lists = [
          $element_access_list,
          $transition_access_list,
        ];
        $row['Access'] = $this->renderer->render($access_lists);

        // Emails:
        $emails_list = [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $this->getTransitionEmailSummary($transition, $webform, $element_id),
        ];
        $row['Emails'] = $this->renderer->render($emails_list);
      }

      if ($enabled) {
        $rows_enabled[] = $row;
      }
      else {
        $rows_disabled[] = $row;
      }
    }

    $has_webform_ui = $this->moduleHandler()
      ->moduleExists('webform_ui');
    $editElementLink = '';
    if ($webform && $element_id && $has_webform_ui) {
      $editElementLink = Url::fromRoute(
          'entity.webform_ui.element.edit_form',
          [
            'webform' => $webform->id(),
            'key' => $element_id,
          ],
        )->toString() . '#webform-tab--access';
    }

    if ($has_webform_ui) {
      $empty_message = $this->t(
        'No enabled transitions for this workflow for this form. <a href=":href">Manage transition availability on the "Access" tab of the workflow element</a>.',
        [':href' => $editElementLink]
      );
    }
    else {
      $empty_message = $this->t(
        'No enabled transitions for this workflow for this form. Enable the Webform UI module to manage transition availability.'
      );
    }

    $enabled = [
      '#type' => 'table',
      '#header' => count($rows_enabled) > 0 ? array_keys($rows_enabled[0]) : [],
      '#rows' => $rows_enabled,
      '#empty' => $empty_message,
      '#attached' => [
        'library' => [
          'webform_workflows_element/default_colors',
        ],
      ],
    ];

    // Split into enabled and disabled.
    if ($webform) {
      $enabled['#prefix'] = '<h3>' . $this->t('Enabled transitions') . '</h3>';
      $enabled['#title'] = $this->t('Enabled transitions');

      if ($has_webform_ui) {
        $disabled_empty_message = $this->t(
          'No disabled transitions for this workflow for this form. <a href=":href">Manage transition availability on the "Access" tab of the workflow element</a>.',
          [':href' => $editElementLink]
        );
      }
      else {
        $disabled_empty_message = $this->t(
          'No disabled transitions for this workflow for this form. Enable the Webform UI module to manage transition availability.'
        );
      }

      $disabled = [
        '#prefix' => '<h3>' . $this->t('Disabled transitions') . '</h3>',
        '#title' => $this->t('Disabled transitions'),
        '#type' => 'table',
        '#header' => count($rows_disabled) > 0 ? array_keys($rows_disabled[0]) : [],
        '#rows' => $rows_disabled,
        '#empty' => $disabled_empty_message,
        '#attached' => [
          'library' => [
            'webform_workflows_element/default_colors',
          ],
        ],
      ];

      return [
        $enabled,
        $disabled,
      ];
    }
    else {
      return [$enabled];
    }
  }

  /**
   * Render a state with colours etc.
   *
   * @param \Drupal\workflows\StateInterface $state
   *   The state.
   * @param array|null $element
   *   The element.
   * @param string $suffix
   *   The suffix.
   *
   * @return array
   *   The state render array.
   */
  public function renderState(StateInterface $state, ?array $element = NULL, string $suffix = ''): array {
    $color_options = $this->workflowsManager->getColorOptionValues();

    if ($element && isset($element['#state_' . $state->id() . '_color']) && $color_name = $element['#state_' . $state->id() . '_color']) {
      $color = $color_options[$color_name];
      return [
        '#type' => 'markup',
        '#markup' => Markup::create('<span class="webform-workflow-state-label with-color ' . $color . '">' . $state->label() . '</span> '),
      ];
    }
    else {
      return [
        '#type' => 'markup',
        '#markup' => $state->label() . $suffix,
      ];
    }
  }

  /**
   * Get summary of access to the transition.
   *
   * Based on element update access AND transition custom access.
   *
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition object.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform object.
   * @param string $element_id
   *   The element id.
   *
   * @return array
   *   Array keyed by element_access and transition_access.
   */
  public function getTransitionAccessSummary(TransitionInterface $transition, WebformInterface $webform, string $element_id): array {
    $element_access = [];
    $transition_access = [];
    $element = $webform->getElementDecoded($element_id);

    // Process update access rules:
    foreach ($element as $key => $value) {
      // Overall element update access:
      $access_key = '#access_update_';
      if (strstr($key, '#access_update_')) {
        $access_type = str_replace($access_key, '', $key);
        $element_access = array_merge($element_access, $this->getTransitionAccessSummaryConvertToText($access_type, $value));
      }

      // Get transition-specific access:
      $access_key = '#access_transition_' . $transition->id() . '_';
      if (!strstr($key, $access_key)) {
        continue;
      }

      // Get access permission type, e.g. roles, group_roles, etc.
      $access_type = str_replace($access_key, '', $key);

      // Sometimes transition is just completely disabled:
      if ($access_type === 'workflow_enabled' && !$value) {
        return [
          'element_access' => [$this->t('Transition is disabled for this element.')],
          'transition_access' => [],
        ];
      }
      else {
        $transition_access = array_merge($transition_access, $this->getTransitionAccessSummaryConvertToText($access_type, $value));
      }
    }

    $element_access = array_unique($element_access);
    $transition_access = array_unique($transition_access);

    return [
      'element_access' => $element_access,
      'transition_access' => $transition_access,
    ];
  }

  /**
   * Get a user-friendly explanation for the access type.
   *
   * @param string $access_type
   *   The access type.
   * @param array|string $value
   *   The value.
   *
   * @return array
   *   Array of markups.
   */
  public function getTransitionAccessSummaryConvertToText(string $access_type, $value): array {
    $access = [];
    switch ($access_type) {
      case 'roles':
        foreach ($value as $role) {
          $access[$access_type . $role] = $this->t('User role "@label"', [
            '@label' => $this->getRole($role)->label(),
          ]);
        }
        break;

      case 'users':
        foreach ($value as $uid) {
          $access[$access_type . $uid] = $this->t('User "@label"', [
            '@label' => $this->getUser($uid)->getDisplayName(),
          ]);
        }
        break;

      case 'permissions':
        foreach ($value as $permission) {
          $access[$access_type . $permission] = $this->t('User permission "@label"', [
            '@label' => $permission,
          ]);
        }
        break;

      case 'group_roles':
        foreach ($value as $role) {
          $access[$access_type . $role] = $this->t('Group role "@label"', [
            '@label' => $this->getGroupRole($role)->label(),
          ]);
        }
        break;

      case 'group_permissions':
        foreach ($value as $permission) {
          $access[$access_type . $permission] = $this->t('Group permission "@label"', [
            '@label' => $permission,
          ]);
        }
        break;
    }

    return $access;
  }

  /**
   * Get an array of links to the email handlers for a transition.
   *
   * @param \Drupal\workflows\TransitionInterface $transition
   *   The transition object.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform object.
   * @param string $element_id
   *   The element id.
   *
   * @return array
   *   Array of markup links.
   */
  public function getTransitionEmailSummary(TransitionInterface $transition, WebformInterface $webform, string $element_id): array {
    $emails = [];
    $handlers = $webform->getHandlers('workflows_transition_email');
    foreach ($handlers as $handler) {
      $states = $handler->getConfiguration()['settings']['states'];
      $key = $element_id . ':' . $transition->id();
      if (!in_array($key, $states)) {
        continue;
      }

      $uri = Url::fromRoute('entity.webform.handler.edit_form', [
        'webform' => $webform->id(),
        'webform_handler' => $handler->getHandlerId(),
      ])->setAbsolute()->toString();

      $email = [
        '#type' => 'markup',
        '#markup' => $this->t('<a href=":url">"@label"</a> to <i>@to</i>', [
          '@label' => $handler->label(),
          '@to' => $handler->getConfiguration()['settings']['to_mail'],
          ':url' => $uri,
        ]),
      ];

      $emails[] = $email;
    }
    return $emails;
  }

  /**
   * Render summary for webform.
   *
   * @param \Drupal\webform\WebformInterface|null $webform
   *   The webform object.
   *
   * @return array
   *   The summary render array.
   */
  public function renderSummaryForWebform(?WebformInterface $webform = NULL): array {
    $workflow_elements = $this->workflowsManager->getWorkflowElementsForWebform($webform);
    if (!count($workflow_elements)) {
      if ($this->moduleHandler()->moduleExists('webform_ui')) {
        $url = Url::fromRoute('entity.webform_ui.element.add_form', [
          'webform' => $webform->id(),
          'type' => 'webform_workflows_element',
        ]);
        return [
          '#type' => 'markup',
          '#markup' => $this->t(
            'There are no "webform workflow element" elements in the form. <a href=":link">Add one now</a>.',
            [
              ':link' => $url->toString(),
            ]
          ),
        ];
      }
      return [
        '#type' => 'markup',
        '#markup' => $this->t(
          'There are no "webform workflow element" elements in the form. Enable the Webform UI module to add one.'
        ),
      ];
    }

    $render = [];
    foreach ($workflow_elements as $element_id => $element) {
      $render[$element['#title']] = $this->renderSummaryForWebformElement($element, $element_id, $webform);
    }

    if (count($workflow_elements) > 1) {
      $grouped_render = [];
      foreach ($render as $title => $rendered_workflow) {
        $grouped_render[] = [
          '#type' => 'fieldset',
          '#title' => $title,
          'rendered' => $rendered_workflow,
        ];
      }
      return $grouped_render;
    }

    return $render;
  }

  /**
   * Render summary for a webform element.
   *
   * @param array $element
   *   The element.
   * @param string $element_id
   *   The element id.
   * @param \Drupal\webform\WebformInterface|null $webform
   *   The webform object.
   *
   * @return array[]
   *   The element summary render array.
   */
  public function renderSummaryForWebformElement(array $element, string $element_id, ?WebformInterface $webform = NULL): array {
    if (!isset($element['#workflow'])) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Element does not have workflow set.'),
      ];
    }

    $workflow = $this->getWorkflow($element['#workflow']);
    if (!$workflow) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Cannot load workflow with id @id.', ['@id' => $element['#workflow']]),
      ];
    }

    $workflowType = $workflow->getTypePlugin();
    if (!$workflowType) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('No workflow type plugin for workflow @id.', ['@id' => $workflow->id()]),
      ];
    }

    $initialState = $workflowType->getInitialState();
    if (!$initialState) {
      // If no initial state is set, use the first state by default.
      // Initial states not being set when saving the workflow
      // seems to be a bug in the workflows module.
      $workflowStates = $workflowType->getStates();
      $initialState = count($workflowStates) > 0 ? reset($workflowStates) : NULL;
    }

    $render['overall_summary'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Overall summary'),
    ];

    $render['overall_summary']['workflow'] = [
      '#prefix' => $this->t('<b>Workflow: </b>'),
      '#type' => 'link',
      '#url' => Url::fromRoute('entity.workflow.edit_form', ['workflow' => $workflow->id()]),
      '#title' => $this->t('@label', [
        '@label' => $workflow->label(),
      ]),
    ];

    if ($initialState) {
      $render['overall_summary']['initial_state'] = [
        '#type' => 'markup',
        '#prefix' => '<div>',
        '#markup' => $this->t('<b>Initial state for new submissions: </b>') . '<span class="webform-workflow-state-label with-color ' . $this->workflowsManager->getColorClassForState($element, $initialState->id()) . '">' . $initialState->label() . '</span>',
        '#suffix' => '</div>',
      ];
    }
    else {
      $render['overall_summary']['initial_state'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Cannot identify an initial state for this workflow. Check the workflow configuration.'),
      ];
    }

    $render['transitions'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Transitions'),
    ];

    $render['transitions']['table'] = $this->renderWorkflowSummaryTable($workflow, $webform, $element_id);

    $render['transitions']['table_help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>"From" indicates what states can start the transition.</p><p>"To" indicates what the state will be after the transition.</p><p>"Access" outlines who can run the transition, e.g. they have to meet at least one of the user roles. If access is split into element and transition access, the user must meet both element access and transition access.</p>'),
    ];

    if ($this->moduleHandler()->moduleExists('workflows_diagram')) {
      $render[] = $this->renderSummaryAsDiagram($workflow, $webform, $element_id);
    }

    $render[] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#title' => $this->t('Understanding workflows'),
      'help' => [
        // phpcs:disable
        '#markup' => $this->t(
          '<p>Workflows are made up of "states" which a submission can be at - e.g. "submitted" or "approved".</p>'
          . '<p>A submission moves from one state to another by a "transition". e.g. the "approve" transition could move a submission from the "submitted" state to the "approved" state.</p>'
          . '<p>A transition could be set from multiple states to one, e.g. "submitted" and "queried" could both be set to go to the "rejected" state using a "reject" transition.</p>'
          . '<p>Not all states need to transition to all other states, so you could e.g. have a "rejected" state that can not transition to "approved".<p>'
          . '<p>More help on using webform workflows can be found <a target="_blank" href=":doc_link">on our documentation page</a>.</p>',
          [
            ':doc_link' => 'https://www.drupal.org/docs/contributed-modules/webform-workflows-element',
          ],
        ),
        // phpcs:enable
      ],
    ];

    return $render;
  }

  /**
   * Render the summary as a diagram.
   *
   * @param \Drupal\workflows\WorkflowInterface $workflow
   *   The workflow object.
   * @param \Drupal\webform\WebformInterface $webform
   *   The webform object.
   * @param string $element_id
   *   The element id.
   *
   * @return array
   *   The workflow diagram render array.
   */
  public function renderSummaryAsDiagram(WorkflowInterface $workflow, WebformInterface $webform, string $element_id): array {
    $workflowType = $workflow->getTypePlugin();

    $element = $webform->getElementDecoded($element_id);

    $states_in_use = [
      $workflowType->getInitialState()
        ->id() => $workflowType->getInitialState(),
    ];

    // Check if anything has been disabled as part of the workflow:
    $disabled = [
      'transitions' => [],
      'states' => [],
    ];

    foreach ($workflowType->getTransitions() as $transition) {
      $enabled_key = '#access_transition_' . $transition->id() . '_workflow_enabled';
      if (isset($element[$enabled_key]) && !$element[$enabled_key]) {
        $disabled['transitions'][] = $transition->id();
      }
      else {
        $states_in_use = $states_in_use + [
          $transition->to()
            ->id() => $transition->to(),
        ];
      }
    }

    // Loop through states:
    $classes = ['states' => []];

    foreach ($workflowType->getStates() as $state) {
      if (!in_array($state->id(), array_keys($states_in_use))) {
        $disabled['states'][] = $state->id();
        continue;
      }

      $color_class = $this->workflowsManager->getColorClassForState($element, $state->id());
      if ($color_class) {
        $classes['states'][$state->id()] = [
          'webform-workflow-state-label',
          'with-color',
          $color_class,
        ];
      }
    }

    return [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Diagram of workflow'),
      'diagram' => [
        '#theme' => 'workflows_diagram',
        '#workflow' => $workflow,
        '#classes' => $classes,
        '#disabled' => $disabled,
        '#attached' => [
          'library' => [
            'webform_workflows_element/default_colors',
          ],
        ],
      ],
    ];
  }

  /**
   * Get the workflow entity.
   *
   * @param mixed $id
   *   The workflow id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The workflow entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getWorkflow(mixed $id) {
    return $this->entityTypeManager()->getStorage('workflow')->load($id);
  }

  /**
   * Get the user.
   *
   * @param mixed $uid
   *   The user id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The user entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getUser(mixed $uid) {
    return $this->entityTypeManager()->getStorage('user')->load($uid);
  }

  /**
   * Get the role entity.
   *
   * @param mixed $role
   *   The role id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The role entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRole(mixed $role) {
    return $this->entityTypeManager()->getStorage('user_role')->load($role);
  }

  /**
   * Get the group role entity.
   *
   * @param mixed $role
   *   The role id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The group role entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getGroupRole(mixed $role) {
    return $this->entityTypeManager()->getStorage('group_role')->load($role);
  }

}
