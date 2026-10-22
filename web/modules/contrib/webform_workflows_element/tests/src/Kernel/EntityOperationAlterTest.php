<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform_workflows_element\Hook\WebformWorkflowsElementHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the entityOperationAlter hook.
 *
 * Covers WebformWorkflowsElementHooks::entityOperationAlter()
 * which adds workflow transition operations to submission entity
 * operation lists. Verifies that enabled transitions are added,
 * disabled transitions are skipped, and non-submission entities
 * are ignored.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class EntityOperationAlterTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * The hooks service under test.
   *
   * @var \Drupal\webform_workflows_element\Hook\WebformWorkflowsElementHooks
   */
  protected WebformWorkflowsElementHooks $hooks;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The hooks service implements entityOperationAlter() which
    // adds workflow transition links (e.g. "Submit", "Approve")
    // to submission entity operation dropdowns. Disabled
    // transitions (like 'reject' in the test fixture) are
    // excluded.
    $this->hooks = $this->container->get(
      WebformWorkflowsElementHooks::class,
    );
  }

  /**
   * Tests that enabled transitions are added as entity operations.
   *
   * For a submission in 'draft' state, the 'submit' transition is
   * enabled and should be added as an operation. The operation key
   * should follow the pattern 'webform_workflow_{workflow}_{id}'.
   */
  public function testEntityOperationAlterAddsTransitions(): void {
    $submission = $this->createSubmission();
    $operations = [];

    $this->hooks->entityOperationAlter($operations, $submission);

    $expected_key = 'webform_workflow_test_workflow_submit';
    $this->assertArrayHasKey($expected_key, $operations);
    $this->assertStringContainsString(
      'Submit',
      (string) $operations[$expected_key]['title'],
    );
    $this->assertArrayHasKey('url', $operations[$expected_key]);
  }

  /**
   * Tests that disabled transitions are not added.
   *
   * The 'reject' transition is disabled on the test element. For
   * a submission in 'review' state, 'reject' should NOT appear
   * in the operations but 'approve' should.
   */
  public function testEntityOperationAlterSkipsDisabledTransition(): void {
    $submission = $this->createSubmission();

    // Move submission to 'review' state so reject becomes relevant.
    $submission->setElementData('workflow', [
      'workflow_state' => 'review',
      'workflow_state_label' => 'Review',
      'transition' => 'submit',
    ]);

    $operations = [];
    $this->hooks->entityOperationAlter($operations, $submission);

    // 'approve' should be present (enabled by default).
    $this->assertArrayHasKey(
      'webform_workflow_test_workflow_approve',
      $operations,
    );

    // 'reject' should be absent (disabled on the element).
    $this->assertArrayNotHasKey(
      'webform_workflow_test_workflow_reject',
      $operations,
    );
  }

  /**
   * Tests that non-submission entities are ignored.
   *
   * Passing a user entity (not a WebformSubmissionInterface)
   * should leave the operations array unchanged.
   */
  public function testEntityOperationAlterNonSubmission(): void {
    $operations = [];
    $this->hooks->entityOperationAlter($operations, $this->adminUser);
    $this->assertSame([], $operations);
  }

}
