<?php

namespace Drupal\webform_workflows_element\EventSubscriber;

use Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Workflow transitions logging.
 *
 * @package Drupal\webform_workflows_element\EventSubscriber
 */
class TransitionEventSubscriber implements EventSubscriberInterface {

  /**
   * The logger channel factory service.
   *
   * @var \Drupal\webform_workflows_element\Service\WebformWorkflowsManager
   */
  protected WebformWorkflowsManager $workflowsManager;

  /**
   * Constructs a new YourEventSubscriber.
   *
   * @param \Drupal\webform_workflows_element\Service\WebformWorkflowsManager $workflows_manager
   *   The workflows manager.
   */
  public function __construct(WebformWorkflowsManager $workflows_manager) {
    $this->workflowsManager = $workflows_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      WebformSubmissionWorkflowTransitionEvent::EVENT_NAME => 'onTransition',
    ];
  }

  /**
   * Subscribe to the user login event dispatched.
   *
   * @param \Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent $event
   *   Event object.
   */
  public function onTransition(WebformSubmissionWorkflowTransitionEvent $event) {

    // Load key things:
    $webform_submission = $event->submission;
    $element = $webform_submission->getWebform()
      ->getElementDecoded($event->elementId);
    $elementData = $webform_submission->getElementData($event->elementId);

    // Log the transition if necessary/possible:
    $this->workflowsManager->logTransition(
      $element, $event->elementId, $webform_submission, $elementData, $event->originalElementData);

  }

}
