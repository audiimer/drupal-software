<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent;
use Drupal\webform_workflows_element\EventSubscriber\TransitionEventSubscriber;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the transition event class and event subscriber.
 *
 * Covers WebformSubmissionWorkflowTransitionEvent constructor,
 * public properties, getTransition() method, and the
 * TransitionEventSubscriber's getSubscribedEvents() and
 * integration with the manager's triggerTransitionEvent().
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class TransitionEventTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * Tests event constructor and getters with full data.
   *
   * Constructs a WebformSubmissionWorkflowTransitionEvent with a
   * real submission, element ID, original data, and a transition
   * object. Verifies all public properties and getTransition()
   * return the expected values.
   */
  public function testEventConstructorAndGetters(): void {
    $submission = $this->createSubmission();
    $workflow_type = $this->workflow->getTypePlugin();
    $transition = $workflow_type->getTransition('submit');
    $original_data = ['workflow_state' => 'draft'];

    $event = new WebformSubmissionWorkflowTransitionEvent(
      $submission,
      'workflow',
      $original_data,
      $transition,
    );

    $this->assertSame($submission, $event->submission);
    $this->assertSame('workflow', $event->elementId);
    $this->assertSame($original_data, $event->originalElementData);
    $this->assertSame($transition, $event->transition);
    $this->assertSame($transition, $event->getTransition());
  }

  /**
   * Tests event constructor with NULL transition and empty data.
   *
   * Constructs the event with NULL for the transition and an empty
   * array for original data. Verifies getTransition() returns NULL
   * and originalElementData is an empty array.
   */
  public function testEventConstructorWithNulls(): void {
    $submission = $this->createSubmission();

    $event = new WebformSubmissionWorkflowTransitionEvent(
      $submission,
      'workflow',
      [],
      NULL,
    );

    $this->assertNull($event->transition);
    $this->assertNull($event->getTransition());
    $this->assertSame([], $event->originalElementData);
    $this->assertSame('workflow', $event->elementId);
  }

  /**
   * Tests TransitionEventSubscriber::getSubscribedEvents().
   *
   * Verifies the subscriber maps the event name constant to the
   * 'onTransition' method.
   */
  public function testSubscriberGetSubscribedEvents(): void {
    $events = TransitionEventSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(
      WebformSubmissionWorkflowTransitionEvent::EVENT_NAME,
      $events,
    );
    $this->assertSame(
      'onTransition',
      $events[WebformSubmissionWorkflowTransitionEvent::EVENT_NAME],
    );
  }

  /**
   * Tests runTransition() dispatches a transition event.
   *
   * Registers a test listener on the transition event, then runs
   * a transition via the manager. Verifies the event is dispatched
   * with the correct submission, element ID, transition, and
   * original element data.
   */
  public function testRunTransitionDispatchesEvent(): void {
    $submission = $this->createSubmission();
    $captured_event = NULL;

    // Register a test listener to capture the dispatched event.
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(
      WebformSubmissionWorkflowTransitionEvent::EVENT_NAME,
      function (WebformSubmissionWorkflowTransitionEvent $event) use (&$captured_event) {
        $captured_event = $event;
      },
    );

    $manager = $this->container->get('webform_workflows_element.manager');
    $manager->runTransition(
      $submission,
      'workflow',
      'submit',
      NULL,
      FALSE,
    );

    $this->assertNotNull($captured_event, 'Event was dispatched.');
    $this->assertSame($submission, $captured_event->submission);
    $this->assertSame('workflow', $captured_event->elementId);
    $this->assertSame(
      'submit',
      $captured_event->getTransition()->id(),
    );
    $this->assertSame(
      'draft',
      $captured_event->originalElementData['workflow_state'],
    );
  }

}
