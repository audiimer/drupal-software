<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform\Entity\Webform;
use Drupal\webform_workflows_element\Event\WebformSubmissionWorkflowTransitionEvent;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use Drupal\workflows\StateInterface;
use Drupal\workflows\TransitionInterface;
use Drupal\workflows\WorkflowInterface;
use Drupal\workflows\WorkflowTypeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests untested public methods on WebformWorkflowsManager.
 *
 * Covers approximately 25 public methods that were not covered by
 * the existing WebformWorkflowsManagerTest, including workflow and
 * state retrieval, transition execution, submission helpers, event
 * triggering, and access checking.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WebformWorkflowsManagerExtendedTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * The workflows manager service under test.
   *
   * @var \Drupal\webform_workflows_element\Service\WebformWorkflowsManager
   */
  protected WebformWorkflowsManager $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The manager service is the central API for workflow
    // operations: retrieving workflows/states/transitions,
    // running transitions on submissions, dispatching events,
    // and checking state-based access.
    $this->manager = $this->container
      ->get('webform_workflows_element.manager');
  }

  /**
   * Tests getWorkflow() returns a workflow entity or NULL.
   *
   * Verifies that loading an existing workflow returns a
   * WorkflowInterface object, and loading a nonexistent workflow
   * returns NULL.
   */
  public function testGetWorkflow(): void {
    $workflow = $this->manager->getWorkflow('test_workflow');
    $this->assertInstanceOf(WorkflowInterface::class, $workflow);
    $this->assertSame('test_workflow', $workflow->id());

    $this->assertNull($this->manager->getWorkflow('nonexistent'));
  }

  /**
   * Tests getWorkflowType() returns the type plugin or NULL.
   *
   * Verifies that an existing workflow returns a WorkflowTypeInterface
   * plugin with the correct plugin ID, and a nonexistent workflow
   * returns NULL.
   */
  public function testGetWorkflowType(): void {
    $workflow_type = $this->manager->getWorkflowType('test_workflow');
    $this->assertInstanceOf(WorkflowTypeInterface::class, $workflow_type);
    $this->assertSame(
      'webform_workflows_element',
      $workflow_type->getPluginId(),
    );

    $this->assertNull($this->manager->getWorkflowType('nonexistent'));
  }

  /**
   * Tests getWorkflowTypeFromElement() with and without workflow key.
   *
   * Verifies that an element with a valid #workflow key returns a
   * WorkflowTypeInterface, and an element without #workflow returns
   * NULL.
   */
  public function testGetWorkflowTypeFromElement(): void {
    $element = $this->webform->getElementDecoded('workflow');
    $workflow_type = $this->manager->getWorkflowTypeFromElement($element);
    $this->assertInstanceOf(WorkflowTypeInterface::class, $workflow_type);

    $this->assertNull(
      $this->manager->getWorkflowTypeFromElement(['#type' => 'textfield']),
    );
  }

  /**
   * Tests getWorkflowFromElement() behaves like getWorkflowTypeFromElement().
   *
   * This is an alias method; it should return the same
   * WorkflowTypeInterface for a valid element and NULL for an
   * element without a workflow.
   */
  public function testGetWorkflowFromElement(): void {
    $element = $this->webform->getElementDecoded('workflow');
    $workflow_type = $this->manager->getWorkflowFromElement($element);
    $this->assertInstanceOf(WorkflowTypeInterface::class, $workflow_type);

    $this->assertNull(
      $this->manager->getWorkflowFromElement(['#type' => 'textfield']),
    );
  }

  /**
   * Tests getStatesFromElement() returns states or empty array.
   *
   * Verifies that the test element returns 3 states (draft, review,
   * approved), and an element without a workflow returns an empty
   * array.
   */
  public function testGetStatesFromElement(): void {
    $element = $this->webform->getElementDecoded('workflow');
    $states = $this->manager->getStatesFromElement($element);
    $this->assertCount(3, $states);
    $this->assertArrayHasKey('draft', $states);
    $this->assertArrayHasKey('review', $states);
    $this->assertArrayHasKey('approved', $states);

    $empty_states = $this->manager->getStatesFromElement(
      ['#type' => 'textfield'],
    );
    $this->assertSame([], $empty_states);
  }

  /**
   * Tests getInitialStateForElement() returns the first state.
   *
   * The test workflow has no explicit initial_state configured, so
   * the method should fall back to the first state (draft). An
   * element without a workflow should return NULL.
   */
  public function testGetInitialStateForElement(): void {
    $element = $this->webform->getElementDecoded('workflow');
    $initial_state = $this->manager->getInitialStateForElement($element);
    $this->assertInstanceOf(StateInterface::class, $initial_state);
    $this->assertSame('draft', $initial_state->id());

    $this->assertNull(
      $this->manager->getInitialStateForElement(['#type' => 'textfield']),
    );
  }

  /**
   * Tests available transitions from the 'draft' state.
   *
   * From draft, only the 'submit' transition should be available
   * (draft -> review). Without access filtering, even the admin
   * user should see exactly one transition.
   */
  public function testGetAvailableTransitionsFromDraft(): void {
    $transitions = $this->manager->getAvailableTransitionsForWorkflow(
      'test_workflow',
      'draft',
    );
    $this->assertCount(1, $transitions);
    $this->assertArrayHasKey('submit', $transitions);
    $this->assertInstanceOf(
      TransitionInterface::class,
      $transitions['submit'],
    );
  }

  /**
   * Tests available transitions from the 'review' state.
   *
   * From review, two transitions are available: 'approve'
   * (review -> approved) and 'reject' (review -> draft). Without
   * access filtering, both should be returned.
   */
  public function testGetAvailableTransitionsFromReview(): void {
    $transitions = $this->manager->getAvailableTransitionsForWorkflow(
      'test_workflow',
      'review',
    );
    $this->assertCount(2, $transitions);
    $this->assertArrayHasKey('approve', $transitions);
    $this->assertArrayHasKey('reject', $transitions);
  }

  /**
   * Tests available transitions from the 'approved' state.
   *
   * Approved is a terminal state with no outgoing transitions, so
   * the method should return an empty array.
   */
  public function testGetAvailableTransitionsFromApproved(): void {
    $transitions = $this->manager->getAvailableTransitionsForWorkflow(
      'test_workflow',
      'approved',
    );
    $this->assertCount(0, $transitions);
  }

  /**
   * Tests available transitions with NULL state and no initial_state.
   *
   * When currentStateId is NULL and the workflow type plugin has no
   * explicit initial_state configured, getInitialState() returns
   * FALSE and the method returns an empty array.
   */
  public function testGetAvailableTransitionsNullState(): void {
    $transitions = $this->manager->getAvailableTransitionsForWorkflow(
      'test_workflow',
      NULL,
    );
    // No initial_state configured, so getInitialState() returns
    // FALSE and no transitions are returned.
    $this->assertCount(0, $transitions);
  }

  /**
   * Tests available transitions for a nonexistent workflow.
   *
   * A workflow ID that does not exist should return an empty array
   * without throwing an exception.
   */
  public function testGetAvailableTransitionsInvalidWorkflow(): void {
    $transitions = $this->manager->getAvailableTransitionsForWorkflow(
      'nonexistent_workflow',
      'draft',
    );
    $this->assertSame([], $transitions);
  }

  /**
   * Tests getWorkflowElementsForWebform() finds workflow elements.
   *
   * Verifies that the test webform returns one workflow element
   * keyed by 'workflow', and a plain webform without workflow
   * elements returns an empty array.
   */
  public function testGetWorkflowElementsForWebform(): void {
    $elements = $this->manager->getWorkflowElementsForWebform(
      $this->webform,
    );
    $this->assertCount(1, $elements);
    $this->assertArrayHasKey('workflow', $elements);
    $this->assertSame(
      'webform_workflows_element',
      $elements['workflow']['#type'],
    );

    // Plain webform without workflow elements.
    $plain = Webform::create([
      'id' => 'plain_webform',
      'title' => 'Plain',
    ]);
    $plain->setElements([
      'name' => ['#type' => 'textfield', '#title' => 'Name'],
    ]);
    $plain->save();

    $this->assertSame(
      [],
      $this->manager->getWorkflowElementsForWebform($plain),
    );
  }

  /**
   * Tests getWorkflowElementsForWebform() finds nested elements.
   *
   * A workflow element inside a container/fieldset should still be
   * found by the recursive element search.
   */
  public function testGetWorkflowElementsForWebformNested(): void {
    $nested = Webform::create([
      'id' => 'nested_webform',
      'title' => 'Nested',
    ]);
    $nested->setElements([
      'container' => [
        '#type' => 'container',
        'inner_workflow' => [
          '#type' => 'webform_workflows_element',
          '#title' => 'Inner Workflow',
          '#workflow' => 'test_workflow',
        ],
      ],
    ]);
    $nested->save();

    $elements = $this->manager->getWorkflowElementsForWebform($nested);
    $this->assertCount(1, $elements);
    $this->assertArrayHasKey('inner_workflow', $elements);
  }

  /**
   * Tests getWebformsWithWorkflowElements() returns matching webforms.
   *
   * Without a filter, the test webform should be included. Filtering
   * by the test workflow ID should also return it. Filtering by a
   * nonexistent workflow ID should return nothing.
   */
  public function testGetWebformsWithWorkflowElements(): void {
    $webforms = $this->manager->getWebformsWithWorkflowElements();
    $ids = array_map(fn($wf) => $wf->id(), $webforms);
    $this->assertContains('test_webform', $ids);

    // Filter by workflow ID.
    $filtered = $this->manager->getWebformsWithWorkflowElements(
      'test_workflow',
    );
    $filtered_ids = array_map(fn($wf) => $wf->id(), $filtered);
    $this->assertContains('test_webform', $filtered_ids);

    // Nonexistent workflow filter.
    $none = $this->manager->getWebformsWithWorkflowElements('fake_wf');
    $this->assertSame([], $none);
  }

  /**
   * Tests getWebformsWithWorkflowElementsIds() returns webform IDs.
   *
   * Verifies the method returns an array containing 'test_webform'
   * when no filter is applied.
   */
  public function testGetWebformsWithWorkflowElementsIds(): void {
    $ids = $this->manager->getWebformsWithWorkflowElementsIds();
    $this->assertContains('test_webform', $ids);
  }

  /**
   * Tests getTransitionsForWorkflow() returns all transitions.
   *
   * The test workflow has 3 transitions (submit, approve, reject).
   * A nonexistent workflow should return NULL.
   */
  public function testGetTransitionsForWorkflow(): void {
    $transitions = $this->manager->getTransitionsForWorkflow(
      'test_workflow',
    );
    $this->assertCount(3, $transitions);
    $this->assertArrayHasKey('submit', $transitions);
    $this->assertArrayHasKey('approve', $transitions);
    $this->assertArrayHasKey('reject', $transitions);

    $this->assertNull(
      $this->manager->getTransitionsForWorkflow('nonexistent'),
    );
  }

  /**
   * Tests getStateFromElementAndId() returns state or NULL.
   *
   * Verifies that a valid state ID returns a StateInterface, and
   * an invalid ID returns NULL. An element without a workflow also
   * returns NULL.
   */
  public function testGetStateFromElementAndId(): void {
    $element = $this->webform->getElementDecoded('workflow');

    $state = $this->manager->getStateFromElementAndId($element, 'draft');
    $this->assertInstanceOf(StateInterface::class, $state);
    $this->assertSame('draft', $state->id());

    $this->assertNull(
      $this->manager->getStateFromElementAndId($element, 'nonexistent'),
    );
    $this->assertNull(
      $this->manager->getStateFromElementAndId(
        ['#type' => 'textfield'],
        'draft',
      ),
    );
  }

  /**
   * Tests runTransitionOnElementValue() executes a valid transition.
   *
   * Runs the 'submit' transition on a draft submission. The returned
   * data should have workflow_state='review', a changed_user set,
   * and a changed_timestamp set.
   */
  public function testRunTransitionOnElementValue(): void {
    $submission = $this->createSubmission();

    $new_data = $this->manager->runTransitionOnElementValue(
      $submission,
      'workflow',
      'submit',
      NULL,
      FALSE,
    );

    $this->assertIsArray($new_data);
    $this->assertSame('review', $new_data['workflow_state']);
    $this->assertSame('Review', $new_data['workflow_state_label']);
    $this->assertSame('submit', $new_data['transition']);
    $this->assertArrayHasKey('changed_user', $new_data);
    $this->assertArrayHasKey('changed_timestamp', $new_data);
  }

  /**
   * Tests runTransitionOnElementValue() with a nonexistent transition.
   *
   * Attempting to run a transition that does not exist in the
   * workflow should throw an InvalidArgumentException from the
   * WorkflowTypeBase::getTransition() method.
   */
  public function testRunTransitionOnElementValueInvalid(): void {
    $submission = $this->createSubmission();

    $this->expectException(\InvalidArgumentException::class);
    $this->manager->runTransitionOnElementValue(
      $submission,
      'workflow',
      'nonexistent_transition',
      NULL,
      FALSE,
    );
  }

  /**
   * Tests runTransitionOnElementValue() with no workflow state set.
   *
   * When the submission has empty element data (e.g. default
   * composite values with transition=''), the data is non-empty
   * so the initial-state branch is skipped. With no transition_id
   * and no run-on-edit config, the method returns the data
   * unchanged.
   */
  public function testRunTransitionOnElementValueNoData(): void {
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');
    $submission = $storage->create([
      'webform_id' => 'test_webform',
      'uid' => $this->adminUser->id(),
      'data' => [],
    ]);
    $submission->save();

    $result = $this->manager->runTransitionOnElementValue(
      $submission,
      'workflow',
      NULL,
      NULL,
      FALSE,
    );

    // Element data has defaults (e.g. 'transition' => ''), so the
    // no-data branch is not entered. With no transition to run,
    // the data is returned as-is.
    $this->assertIsArray($result);
    $this->assertSame('', $result['transition']);
  }

  /**
   * Tests runTransitionOnElementValue() with no transition selected.
   *
   * A normal submission in 'draft' state with no transition chosen
   * (e.g. user saves without selecting a transition). The method
   * should return the element data unchanged, preserving the
   * current workflow_state.
   */
  public function testRunTransitionOnElementValueNoTransition(): void {
    $submission = $this->createSubmission();

    $result = $this->manager->runTransitionOnElementValue(
      $submission,
      'workflow',
      NULL,
      NULL,
      FALSE,
    );

    $this->assertIsArray($result);
    $this->assertSame('draft', $result['workflow_state']);
  }

  /**
   * Tests runTransition() executes and updates submission data.
   *
   * Runs the 'submit' transition via runTransition() and verifies
   * it returns TRUE and the submission's element data is updated
   * to the 'review' state.
   */
  public function testRunTransition(): void {
    $submission = $this->createSubmission();

    $result = $this->manager->runTransition(
      $submission,
      'workflow',
      'submit',
      NULL,
      FALSE,
    );

    $this->assertTrue($result);

    $element_data = $submission->getElementData('workflow');
    $this->assertSame('review', $element_data['workflow_state']);
    $this->assertSame('Review', $element_data['workflow_state_label']);
  }

  /**
   * Tests runTransition() with a nonexistent transition.
   *
   * Attempting to run a transition that does not exist in the
   * workflow should throw an InvalidArgumentException from the
   * WorkflowTypeBase::getTransition() method.
   */
  public function testRunTransitionInvalid(): void {
    $submission = $this->createSubmission();

    $this->expectException(\InvalidArgumentException::class);
    $this->manager->runTransition(
      $submission,
      'workflow',
      'nonexistent_transition',
      NULL,
      FALSE,
    );
  }

  /**
   * Tests getTransitionFromSubmission() returns transition or NULL.
   *
   * When a submission has transition data set, the method should
   * return a TransitionInterface. When no transition is set, it
   * should return NULL.
   */
  public function testGetTransitionFromSubmission(): void {
    $submission = $this->createSubmission();

    // No transition set yet.
    $this->assertNull(
      $this->manager->getTransitionFromSubmission($submission, 'workflow'),
    );

    // Run a transition to set it.
    $this->manager->runTransition(
      $submission,
      'workflow',
      'submit',
      NULL,
      FALSE,
    );

    $transition = $this->manager->getTransitionFromSubmission(
      $submission,
      'workflow',
    );
    $this->assertInstanceOf(TransitionInterface::class, $transition);
    $this->assertSame('submit', $transition->id());
  }

  /**
   * Tests getTransitionIdFromSubmission() returns ID or NULL.
   *
   * Verifies the method extracts the transition ID string from
   * submission element data, or returns NULL when absent.
   */
  public function testGetTransitionIdFromSubmission(): void {
    $submission = $this->createSubmission();

    $this->assertNull(
      $this->manager->getTransitionIdFromSubmission(
        $submission,
        'workflow',
      ),
    );

    // Run a transition to set data.
    $this->manager->runTransition(
      $submission,
      'workflow',
      'submit',
      NULL,
      FALSE,
    );

    $this->assertSame(
      'submit',
      $this->manager->getTransitionIdFromSubmission(
        $submission,
        'workflow',
      ),
    );
  }

  /**
   * Tests checkAccessToUpdateBasedOnState() returns neutral.
   *
   * When no override is configured for the state, the method should
   * return a neutral access result.
   */
  public function testCheckAccessToUpdateBasedOnStateNeutral(): void {
    $submission = $this->createSubmission();
    $element = $this->webform->getElementDecoded('workflow');

    $access = $this->manager->checkAccessToUpdateBasedOnState(
      $this->adminUser,
      $submission,
      $element,
      'draft',
    );
    $this->assertTrue($access->isNeutral());
  }

  /**
   * Tests checkAccessToUpdateBasedOnState() returns forbidden.
   *
   * When the override is enabled and the workflow_enabled flag is
   * FALSE for a state, the method should return forbidden.
   */
  public function testCheckAccessToUpdateBasedOnStateForbidden(): void {
    $submission = $this->createSubmission();
    $element = $this->webform->getElementDecoded('workflow');

    // Add override + disabled for draft state.
    $element['#access_update_at_state_draft_override'] = TRUE;
    $element['#access_update_at_state_draft_workflow_enabled'] = FALSE;

    $access = $this->manager->checkAccessToUpdateBasedOnState(
      $this->adminUser,
      $submission,
      $element,
      'draft',
    );
    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests getWorkflowTypeFromSubmission() returns type or NULL.
   *
   * Verifies that the method correctly extracts the workflow type
   * from a submission's element configuration.
   */
  public function testGetWorkflowTypeFromSubmission(): void {
    $submission = $this->createSubmission();

    $workflow_type = $this->manager->getWorkflowTypeFromSubmission(
      $submission,
      'workflow',
    );
    $this->assertInstanceOf(WorkflowTypeInterface::class, $workflow_type);
    $this->assertSame(
      'webform_workflows_element',
      $workflow_type->getPluginId(),
    );
  }

  /**
   * Tests triggerTransitionEvent() dispatches a transition event.
   *
   * Registers a test listener on the transition event, runs a
   * transition, then calls triggerTransitionEvent() manually.
   * Verifies the event is dispatched with the correct submission,
   * element ID, and original element data.
   */
  public function testTriggerTransitionEvent(): void {
    $submission = $this->createSubmission();
    $this->manager->runTransition(
      $submission,
      'workflow',
      'submit',
      NULL,
      FALSE,
    );

    $captured_event = NULL;
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(
      WebformSubmissionWorkflowTransitionEvent::EVENT_NAME,
      function (WebformSubmissionWorkflowTransitionEvent $event) use (&$captured_event) {
        $captured_event = $event;
      },
    );

    $original_data = ['workflow_state' => 'draft'];
    $this->manager->triggerTransitionEvent(
      $submission,
      'workflow',
      $original_data,
    );

    $this->assertNotNull($captured_event, 'Event was dispatched.');
    $this->assertSame($submission, $captured_event->submission);
    $this->assertSame('workflow', $captured_event->elementId);
    $this->assertSame(
      'draft',
      $captured_event->originalElementData['workflow_state'],
    );
  }

}
