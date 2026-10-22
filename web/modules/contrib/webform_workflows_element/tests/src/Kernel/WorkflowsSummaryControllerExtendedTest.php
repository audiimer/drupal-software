<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\Core\Render\RenderContext;
use Drupal\webform_workflows_element\Controller\WorkflowsSummaryController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests additional WorkflowsSummaryController methods.
 *
 * Extends the coverage beyond the existing
 * WorkflowsSummaryControllerTest which only tests the webform_ui
 * fallback paths. This file tests renderSummary(),
 * renderState() (with/without color),
 * getTransitionAccessSummary() (enabled/disabled), and
 * renderSummaryForWebformElement().
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WorkflowsSummaryControllerExtendedTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * The controller under test.
   *
   * @var \Drupal\webform_workflows_element\Controller\WorkflowsSummaryController
   */
  protected WorkflowsSummaryController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Instantiate via create() factory to inject all required
    // services (entity_type.manager, workflow manager, renderer).
    // The controller renders workflow summaries, state labels
    // (with/without color badges), and transition access info.
    $this->controller = WorkflowsSummaryController::create(
      $this->container,
    );
  }

  /**
   * Tests renderSummary() returns a build array with workflow.
   *
   * Verifies that the summary page includes an intro paragraph
   * and a details element for the test workflow.
   */
  public function testRenderSummary(): void {
    $build = $this->controller->renderSummary();

    $this->assertIsArray($build);
    $this->assertArrayHasKey('intro', $build);
    $this->assertSame('markup', $build['intro']['#type']);
  }

  /**
   * Tests renderState() with a configured color.
   *
   * When the element has a color configured for the 'draft' state,
   * renderState() should return markup containing the 'with-color'
   * CSS class and the specific color class.
   */
  public function testRenderStateWithColor(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $draft_state = $workflow_type->getState('draft');
    $element = $this->webform->getElementDecoded('workflow');

    $render = $this->controller->renderState($draft_state, $element);

    $this->assertSame('markup', $render['#type']);
    $markup = (string) $render['#markup'];
    $this->assertStringContainsString('with-color', $markup);
    $this->assertStringContainsString(
      'webform-workflow-color-blue',
      $markup,
    );
    $this->assertStringContainsString('Draft', $markup);
  }

  /**
   * Tests renderState() without a configured color.
   *
   * When the element has no color for the 'approved' state,
   * renderState() should return plain label markup without
   * the 'with-color' class.
   */
  public function testRenderStateWithoutColor(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $approved_state = $workflow_type->getState('approved');
    $element = $this->webform->getElementDecoded('workflow');

    $render = $this->controller->renderState($approved_state, $element);

    $this->assertSame('markup', $render['#type']);
    $markup = (string) $render['#markup'];
    $this->assertStringNotContainsString('with-color', $markup);
    $this->assertStringContainsString('Approved', $markup);
  }

  /**
   * Tests getTransitionAccessSummary() for a disabled transition.
   *
   * The 'reject' transition is disabled on the test element. The
   * summary should indicate that the transition is disabled.
   */
  public function testGetTransitionAccessSummaryDisabled(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $reject_transition = $workflow_type->getTransition('reject');

    $summary = $this->controller->getTransitionAccessSummary(
      $reject_transition,
      $this->webform,
      'workflow',
    );

    $this->assertArrayHasKey('element_access', $summary);
    $element_text = implode(' ', array_map(
      'strval',
      $summary['element_access'],
    ));
    $this->assertStringContainsString('disabled', strtolower($element_text));
  }

  /**
   * Tests getTransitionAccessSummary() for an enabled transition.
   *
   * The 'submit' transition is enabled. The summary should return
   * arrays for element_access and transition_access without the
   * 'disabled' text.
   */
  public function testGetTransitionAccessSummaryEnabled(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $submit_transition = $workflow_type->getTransition('submit');

    $summary = $this->controller->getTransitionAccessSummary(
      $submit_transition,
      $this->webform,
      'workflow',
    );

    $this->assertArrayHasKey('element_access', $summary);
    $this->assertArrayHasKey('transition_access', $summary);

    // The element_access should not say "disabled".
    $element_text = implode(' ', array_map(
      'strval',
      $summary['element_access'],
    ));
    $this->assertStringNotContainsString(
      'disabled',
      strtolower($element_text),
    );
  }

  /**
   * Tests renderSummaryForWebformElement() returns expected keys.
   *
   * The method should return a render array containing
   * 'overall_summary' and 'transitions' detail sections for the
   * test workflow element.
   */
  public function testRenderSummaryForWebformElement(): void {
    $element = $this->webform->getElementDecoded('workflow');
    $renderer = $this->container->get('renderer');

    $render = $renderer->executeInRenderContext(
      new RenderContext(),
      fn () => $this->controller->renderSummaryForWebformElement(
        $element,
        'workflow',
        $this->webform,
      ),
    );

    $this->assertIsArray($render);
    $this->assertArrayHasKey('overall_summary', $render);
    $this->assertArrayHasKey('transitions', $render);
    $this->assertSame('details', $render['overall_summary']['#type']);
    $this->assertSame('details', $render['transitions']['#type']);
  }

}
