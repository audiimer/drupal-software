<?php

namespace Drupal\webform_workflows_element\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Drupal\workflows\TransitionInterface;
use Drupal\workflows\WorkflowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form to transition a submission.
 */
class WebformWorkflowTransitionConfirmForm extends ConfirmFormBase {

  /**
   * Webform.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected WebformInterface $webform;

  /**
   * Webform submission.
   *
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  protected WebformSubmissionInterface $webformSubmission;

  /**
   * Workflow element.
   *
   * @var array|null
   */
  protected ?array $element;

  /**
   * Workflow.
   *
   * @var \Drupal\workflows\WorkflowInterface
   */
  protected WorkflowInterface $workflow;

  /**
   * Transition.
   *
   * @var \Drupal\workflows\TransitionInterface
   */
  protected TransitionInterface $transition;

  /**
   * The webform workflows manager service.
   *
   * @var \Drupal\webform_workflows_element\Service\WebformWorkflowsManager
   */
  protected $workflowsManager;

  /**
   * WebformWorkflowTransitionConfirmForm constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\webform_workflows_element\Service\WebformWorkflowsManager $workflows_manager
   *   The workflows manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    WebformWorkflowsManager $workflows_manager,
  ) {
    $this->messenger = $messenger;
    $this->workflowsManager = $workflows_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('webform_workflows_element.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?TranslatableMarkup {
    return $this->t('Do you want to perform "@transition" on submission @id?', [
      '@transition' => $this->transition->label(),
      '@id' => $this->webformSubmission->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Yes');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText(): TranslatableMarkup {
    return $this->t('No');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WebformInterface $webform = NULL, ?WebformSubmissionInterface $webform_submission = NULL, ?string $workflow_element = NULL, ?string $transition = NULL): array {
    // Set variables:
    $this->webformSubmission = $webform_submission;
    $this->webform = $webform;

    $this->element = $this->workflowsManager->getWorkflowElementsForWebform($this->webform)[$workflow_element] ?? NULL;
    if (!$this->element) {
      $this->throwFormError($form, $this->t('Cannot load element.', []));
      return $form;
    }

    $this->workflow = $this->workflowsManager->getWorkflow($this->element['#workflow']);
    if (!$this->workflow) {
      $this->throwFormError($form, $this->t('Cannot load workflow.', []));
      return $form;
    }

    $this->transition = $this->workflow->getTypePlugin()
      ->getTransition($transition);

    $form = parent::buildForm($form, $form_state);

    $form['log_public'] = [
      '#title' => $this->t('Log message for submitter'),
      '#type' => $this->element['#log_public_setting'] !== 'Disabled' ? 'textarea' : 'hidden',
      '#rows' => 2,
      '#required' => $this->element['#log_public_setting'] === 'Required',
    ];

    $form['log_admin'] = [
      '#title' => $this->t('Log message - admin only'),
      '#type' => $this->element['#log_admin_setting'] !== 'Disabled' ? 'textarea' : 'hidden',
      '#rows' => 2,
      '#required' => $this->element['#log_admin_setting'] === 'Required',
    ];

    return $form;
  }

  /**
   * Add cancel button and message to form.
   */
  public function throwFormError(&$form, $message) {
    $form['actions']['submit']['#access'] = FALSE;
    $form['actions']['cancel']['#title'] = $this->t('Cancel');
    $form['description']['#markup'] = $message;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submission = WebformSubmission::load($this->webformSubmission->id());

    foreach ($this->workflowsManager->getWorkflowElementsForWebform($this->webform) as $element_id => $element) {
      if ($element['#workflow'] === $this->workflow->id()) {
        $data = $this->webformSubmission->getElementData($element_id);
        $data['transition'] = $this->transition->id();
        if ($log_public = $form_state->getValue('log_public')) {
          $data['log_public'] = $log_public;
        }
        if ($log_admin = $form_state->getValue('log_admin')) {
          $data['log_admin'] = $log_admin;
        }
        $this->webformSubmission->setElementData($element_id, $data);
        $this->workflowsManager->runTransitionOnElementValue($this->webformSubmission, $element_id);

        $this->messenger()
          ->addMessage($this->t("%transition run on webform submission @submission_id.", [
            '%transition' => $this->transition->label(),
            '%transition_id' => $this->transition->id(),
            '@submission_id' => $this->webformSubmission->id(),
          ]), 'status');
      }
    }

    $submission->save();

    $url = $this->webformSubmission ? $this->webformSubmission->toUrl() : $this->getCancelUrl();

    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.webform.results_submissions', [
      'webform' => $this->webform->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "webform_workflow_transition_confirm_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Perform "@transition" on submission @id?', [
      '@transition' => $this->transition->label(),
      '@id' => $this->webformSubmission->id(),
    ]);
  }

  /**
   * Check access to workflow transition.
   *
   * @throws \Exception
   */
  public function checkAccess(?WebformInterface $webform = NULL, ?WebformSubmissionInterface $webform_submission = NULL, ?string $workflow_element = NULL, ?string $transition = NULL) {
    $element = $this->workflowsManager->getWorkflowElementsForWebform($webform)[$workflow_element] ?? NULL;
    $workflow = $this->workflowsManager->getWorkflow($element['#workflow']);
    $transition = $workflow->getTypePlugin()->getTransition($transition);

    // Check access to transition:
    $account = $this->currentUser();
    $access = $this->workflowsManager->checkAccessForSubmissionAndTransition($workflow, $account, $webform, $transition, NULL, $webform_submission);
    return AccessResult::allowedIf($access);
  }

}
