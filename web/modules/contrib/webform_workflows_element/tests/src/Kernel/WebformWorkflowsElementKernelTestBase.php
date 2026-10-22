<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use Drupal\workflows\Entity\Workflow;

/**
 * Base class for webform_workflows_element kernel tests.
 *
 * Provides shared module list, schema installation, and test
 * fixtures (workflow with 3 states/transitions, webform with one
 * workflow element, admin user).
 */
abstract class WebformWorkflowsElementKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'field',
    'workflows',
    'webform',
    'webform_workflows_element',
  ];

  /**
   * The test workflow entity.
   *
   * @var \Drupal\workflows\WorkflowInterface
   */
  protected $workflow;

  /**
   * The test webform with a workflow element.
   *
   * @var \Drupal\webform\WebformInterface
   */
  protected $webform;

  /**
   * A test user with admin permissions (uid 1).
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas required by webform submissions.
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('webform_submission');
    // The 'webform' table stores webform definitions (not an entity).
    $this->installSchema('webform', ['webform']);
    // Module config provides default settings and permissions.
    $this->installConfig(['webform', 'webform_workflows_element']);

    $this->createTestFixtures();
  }

  /**
   * Creates shared test fixtures.
   *
   * Builds a workflow with 3 states (draft, review, approved) and
   * 3 transitions (submit, approve, reject), a webform with one
   * workflow element (with colors and one disabled transition), and
   * an admin user (uid 1).
   */
  protected function createTestFixtures(): void {
    // Workflow state graph:
    // phpcs:disable Drupal.Commenting.InlineComment
    //   draft --(submit)--> review --(approve)--> approved
    //                         \----(reject)----> draft
    // phpcs:enable Drupal.Commenting.InlineComment
    // No initial_state is configured, so getInitialState() returns
    // FALSE (not a StateInterface fallback).
    // 'approved' is a terminal state with no outgoing transitions.
    $this->workflow = Workflow::create([
      'id' => 'test_workflow',
      'label' => 'Test Workflow',
      'type' => 'webform_workflows_element',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0],
          'review' => ['label' => 'Review', 'weight' => 1],
          'approved' => ['label' => 'Approved', 'weight' => 2],
        ],
        'transitions' => [
          'submit' => [
            'label' => 'Submit',
            'from' => ['draft'],
            'to' => 'review',
            'weight' => 0,
          ],
          'approve' => [
            'label' => 'Approve',
            'from' => ['review'],
            'to' => 'approved',
            'weight' => 1,
          ],
          'reject' => [
            'label' => 'Reject',
            'from' => ['review'],
            'to' => 'draft',
            'weight' => 2,
          ],
        ],
      ],
    ]);
    $this->workflow->save();

    // Webform with a single workflow element keyed 'workflow'.
    // - Colors: draft=Blue, review=Green, approved=none.
    // - The 'reject' transition is explicitly disabled; 'submit'
    //   and 'approve' are enabled by default.
    $this->webform = Webform::create([
      'id' => 'test_webform',
      'title' => 'Test Webform',
    ]);
    $this->webform->setElements([
      'workflow' => [
        '#type' => 'webform_workflows_element',
        '#title' => 'Workflow',
        '#workflow' => 'test_workflow',
        '#state_draft_color' => 'Blue',
        '#state_review_color' => 'Green',
        '#access_transition_reject_workflow_enabled' => FALSE,
      ],
    ]);
    $this->webform->save();

    // uid=1 is Drupal's super-admin and bypasses all access
    // checks. Tests needing non-privileged access must create a
    // separate user with uid >= 2.
    $this->adminUser = User::create([
      'uid' => 1,
      'name' => 'admin',
      'mail' => 'admin@example.com',
      'status' => 1,
    ]);
    $this->adminUser->save();
  }

  /**
   * Creates a minimal webform submission for testing.
   *
   * @return \Drupal\webform\WebformSubmissionInterface
   *   The saved submission entity.
   */
  protected function createSubmission() {
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');
    // Creates a submission in 'draft' state (the first state in
    // the workflow graph). Only workflow_state is set; transition,
    // changed_user, and changed_timestamp are left empty.
    $submission = $storage->create([
      'webform_id' => 'test_webform',
      'uid' => $this->adminUser->id(),
      'data' => [
        'workflow' => [
          'workflow_state' => 'draft',
        ],
      ],
    ]);
    $submission->save();
    return $submission;
  }

}
