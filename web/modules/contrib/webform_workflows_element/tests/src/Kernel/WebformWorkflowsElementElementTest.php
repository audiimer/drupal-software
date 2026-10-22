<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform_workflows_element\Element\WebformWorkflowsElement;
use Drupal\webform_workflows_element\Plugin\WorkflowType\WebformWorkflowsElement as WorkflowTypePlugin;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the form element class and workflow type plugin.
 *
 * Covers WebformWorkflowsElement::getCompositeElements() (hidden
 * sub-elements), getWorkflowStates(), convertTransitionsToOptions(),
 * and the WorkflowType plugin's getInitialState() method.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WebformWorkflowsElementElementTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * Tests getCompositeElements() returns hidden sub-elements.
   *
   * When called without #value (i.e. not during rendering), the
   * method should return the composite sub-element definitions
   * including workflow_state, workflow_state_previous,
   * workflow_state_label, transition, changed_user, and
   * changed_timestamp — all of type 'hidden'.
   */
  public function testGetCompositeElements(): void {
    $element = ['#type' => 'webform_workflows_element'];

    $composites = WebformWorkflowsElement::getCompositeElements($element);

    $expected_keys = [
      'workflow_state',
      'workflow_state_previous',
      'workflow_state_label',
      'transition',
      'changed_user',
      'changed_timestamp',
    ];
    foreach ($expected_keys as $key) {
      $this->assertArrayHasKey($key, $composites, "Missing key: $key");
      $this->assertSame(
        'hidden',
        $composites[$key]['#type'],
        "Expected hidden type for: $key",
      );
    }
  }

  /**
   * Tests getWorkflowStates() returns states for an element.
   *
   * Uses the test element with a configured workflow. The method
   * should return 3 StateInterface objects (draft, review,
   * approved).
   */
  public function testGetWorkflowStates(): void {
    $element = $this->webform->getElementDecoded('workflow');

    $states = WebformWorkflowsElement::getWorkflowStates($element);

    $this->assertCount(3, $states);
    $state_ids = array_keys($states);
    $this->assertSame(['draft', 'review', 'approved'], $state_ids);
  }

  /**
   * Tests convertTransitionsToOptions() builds select options.
   *
   * Converts an array of TransitionInterface objects into an
   * associative array of transition_id => label suitable for
   * form select elements.
   */
  public function testConvertTransitionsToOptions(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $transitions = $workflow_type->getTransitions();

    $options = WebformWorkflowsElement::convertTransitionsToOptions(
      $transitions,
    );

    $this->assertSame('Submit', $options['submit']);
    $this->assertSame('Approve', $options['approve']);
    $this->assertSame('Reject', $options['reject']);
    $this->assertCount(3, $options);
  }

  /**
   * Tests WorkflowType plugin getInitialState() returns FALSE.
   *
   * The test workflow has no explicit initial_state configured in
   * its type_settings, so the plugin's getInitialState() should
   * return FALSE.
   */
  public function testWorkflowTypeGetInitialStateFalse(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $this->assertInstanceOf(WorkflowTypePlugin::class, $workflow_type);

    // No initial_state configured, so returns FALSE.
    $this->assertFalse($workflow_type->getInitialState());
  }

  /**
   * Tests WorkflowType plugin getInitialState() with configured state.
   *
   * When initial_state is set in the workflow type configuration,
   * getInitialState() should return the corresponding
   * StateInterface object.
   */
  public function testWorkflowTypeGetInitialStateConfigured(): void {
    // Reconfigure the workflow with an explicit initial_state.
    $workflow_type = $this->workflow->getTypePlugin();
    $config = $workflow_type->getConfiguration();
    $config['initial_state'] = 'draft';
    $workflow_type->setConfiguration($config);
    $this->workflow->save();

    // Reload to get fresh plugin.
    $reloaded = Workflow::load('test_workflow');
    $initial = $reloaded->getTypePlugin()->getInitialState();

    $this->assertNotFalse($initial);
    $this->assertSame('draft', $initial->id());
    $this->assertSame('Draft', $initial->label());
  }

}
