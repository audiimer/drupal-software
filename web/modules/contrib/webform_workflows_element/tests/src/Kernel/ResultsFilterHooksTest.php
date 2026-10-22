<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform_workflows_element\Hook\ResultsFilterHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ResultsFilterHooks query filtering methods.
 *
 * Covers the static applyWorkflowQueryFilters() method which
 * adds sub-query conditions based on workflow query parameters.
 * This method is used by both the webform_better_results hook
 * and the standalone submission filter form.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class ResultsFilterHooksTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * Tests applyWorkflowQueryFilters() with no workflow params.
   *
   * When no query parameters start with 'workflow-', the query
   * should remain unchanged (no additional conditions added).
   */
  public function testApplyWorkflowQueryFiltersNoParams(): void {
    $submission_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');

    $query = $submission_storage->getQuery()->accessCheck(FALSE);
    $data = [
      'query' => $query,
      'submission_storage' => $submission_storage,
    ];

    ResultsFilterHooks::applyWorkflowQueryFilters(
      $data,
      ['search' => 'test'],
      $this->webform,
    );

    // The query object should still be present.
    $this->assertSame($query, $data['query']);
    // Executing the query should not error.
    $result = $data['query']->execute();
    $this->assertIsArray($result);
  }

  /**
   * Tests applyWorkflowQueryFilters() with a workflow param.
   *
   * When the query string contains 'workflow-workflow=draft', the
   * method should add a sub-query condition filtering by that
   * workflow state. The query should execute without error and
   * return the submission in draft state.
   */
  public function testApplyWorkflowQueryFiltersWithParam(): void {
    // Create a submission in draft state so there's data to find.
    $submission = $this->createSubmission();
    $submission->save();

    $submission_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');

    $query = $submission_storage->getQuery()->accessCheck(FALSE);
    $data = [
      'query' => $query,
      'submission_storage' => $submission_storage,
    ];

    ResultsFilterHooks::applyWorkflowQueryFilters(
      $data,
      ['workflow-workflow' => 'draft'],
      $this->webform,
    );

    // The query should still execute without errors.
    $result = $data['query']->execute();
    $this->assertIsArray($result);
    $this->assertContains(
      (string) $submission->id(),
      array_map('strval', $result),
    );
  }

  /**
   * Tests applyWorkflowQueryFilters() excludes non-matching state.
   *
   * When filtering by 'review' state, a submission in 'draft'
   * should not be returned.
   */
  public function testApplyWorkflowQueryFiltersExcludes(): void {
    // Create a submission in draft state.
    $submission = $this->createSubmission();
    $submission->save();

    $submission_storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');

    $query = $submission_storage->getQuery()->accessCheck(FALSE);
    $data = [
      'query' => $query,
      'submission_storage' => $submission_storage,
    ];

    ResultsFilterHooks::applyWorkflowQueryFilters(
      $data,
      ['workflow-workflow' => 'review'],
      $this->webform,
    );

    $result = $data['query']->execute();
    $this->assertNotContains(
      (string) $submission->id(),
      array_map('strval', $result),
    );
  }

}
