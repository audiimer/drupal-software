<?php

namespace Drupal\webform_workflows_element\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\workflows\TransitionInterface;

/**
 * Event that is fired when a user logs in.
 */
class WebformSubmissionWorkflowTransitionEvent extends Event {

  const EVENT_NAME = 'webform_submission_workflow_transition';

  /**
   * The submission.
   *
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  public WebformSubmissionInterface $submission;

  /**
   * The transition.
   *
   * @var \Drupal\workflows\TransitionInterface|null
   */
  public ?TransitionInterface $transition;

  /**
   * The webform element ID.
   *
   * @var string
   */
  public string $elementId;

  /**
   * Workflow element values from before transition was run.
   */

  public array $originalElementData;

  /**
   * Constructs the object.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The submission.
   * @param string $element_id
   *   The element id.
   * @param ?array $originalElementData
   *   The original element data.
   * @param ?TransitionInterface $transition
   *   The original element data.
   */
  public function __construct(
    WebformSubmissionInterface $submission,
    string $element_id,
    ?array $originalElementData = NULL,
    ?TransitionInterface $transition = NULL,
  ) {
    $this->submission = $submission;
    $this->elementId = $element_id;
    $this->originalElementData = $originalElementData;
    $this->transition = $transition;
  }

  /**
   * Get the transition that was run.
   *
   * @return \Drupal\workflows\TransitionInterface|null
   *   The transition.
   */
  public function getTransition(): ?TransitionInterface {
    return $this->transition;
  }

}
