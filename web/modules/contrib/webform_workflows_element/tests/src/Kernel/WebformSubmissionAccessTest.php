<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use Drupal\webform_workflows_element\Hook\WebformWorkflowsElementHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests submission access and presave hooks.
 *
 * Covers WebformWorkflowsElementHooks::webformSubmissionAccess()
 * and WebformWorkflowsElementHooks::webformSubmissionPresave().
 * The webformElementAccess() hook is already tested in
 * HookClassesTest and is NOT duplicated here.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WebformSubmissionAccessTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * The hooks service under test.
   *
   * @var \Drupal\webform_workflows_element\Hook\WebformWorkflowsElementHooks
   */
  protected WebformWorkflowsElementHooks $hooks;

  /**
   * A non-admin user for access testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The hooks service implements webformSubmissionAccess() and
    // webformSubmissionPresave() which control state-based edit
    // access and execute transitions on save, respectively.
    $this->hooks = $this->container->get(
      WebformWorkflowsElementHooks::class,
    );

    // uid=2 is a regular user with NO special permissions. Unlike
    // uid=1 (admin, set up in base class), this user does NOT
    // bypass access checks, so state-based access restrictions
    // (e.g. override + disabled) will actually be enforced.
    $this->regularUser = User::create([
      'uid' => 2,
      'name' => 'regular',
      'mail' => 'regular@example.com',
      'status' => 1,
    ]);
    $this->regularUser->save();
  }

  /**
   * Tests webformSubmissionAccess() returns neutral for non-update.
   *
   * The hook only handles the 'update' operation. For any other
   * operation (e.g. 'view'), it should return a neutral access
   * result regardless of workflow state.
   */
  public function testSubmissionAccessNeutralForNonUpdate(): void {
    $submission = $this->createSubmission();

    $access = $this->hooks->webformSubmissionAccess(
      $submission,
      'view',
      $this->adminUser,
    );
    $this->assertTrue($access->isNeutral());
  }

  /**
   * Tests webformSubmissionAccess() is neutral without workflow.
   *
   * When the webform has no workflow elements, the hook should
   * return neutral even for the 'update' operation.
   */
  public function testSubmissionAccessNeutralNoWorkflowElements(): void {
    $webform = Webform::create([
      'id' => 'no_workflow_form',
      'title' => 'No Workflow',
    ]);
    $webform->setElements([
      'name' => ['#type' => 'textfield', '#title' => 'Name'],
    ]);
    $webform->save();

    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');
    $submission = $storage->create([
      'webform_id' => 'no_workflow_form',
      'uid' => $this->adminUser->id(),
      'data' => [],
    ]);
    $submission->save();

    $access = $this->hooks->webformSubmissionAccess(
      $submission,
      'update',
      $this->adminUser,
    );
    $this->assertTrue($access->isNeutral());
  }

  /**
   * Tests webformSubmissionAccess() is neutral without override.
   *
   * When the workflow element has no state override configured,
   * the hook should return a neutral access result for the
   * 'update' operation.
   */
  public function testSubmissionAccessNeutralNoOverride(): void {
    $submission = $this->createSubmission();

    $access = $this->hooks->webformSubmissionAccess(
      $submission,
      'update',
      $this->regularUser,
    );
    $this->assertTrue($access->isNeutral());
  }

  /**
   * Tests webformSubmissionAccess() forbidden with disabled override.
   *
   * When the element has a state override configured and the
   * workflow_enabled flag is FALSE for the current state (draft),
   * the hook should return forbidden for the 'update' operation.
   */
  public function testSubmissionAccessForbiddenWhenOverrideDisables(): void {
    // Configure the override on the webform element.
    $this->webform->setElements([
      'workflow' => [
        '#type' => 'webform_workflows_element',
        '#title' => 'Workflow',
        '#workflow' => 'test_workflow',
        '#state_draft_color' => 'Blue',
        '#state_review_color' => 'Green',
        '#access_transition_reject_workflow_enabled' => FALSE,
        '#access_update_at_state_draft_override' => TRUE,
        '#access_update_at_state_draft_workflow_enabled' => FALSE,
      ],
    ]);
    $this->webform->save();

    $submission = $this->createSubmission();

    $access = $this->hooks->webformSubmissionAccess(
      $submission,
      'update',
      $this->regularUser,
    );
    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests webformSubmissionPresave() executes a transition.
   *
   * When the submission has a transition set in the element data,
   * the presave hook should execute it and change the workflow
   * state.
   */
  public function testPresaveExecutesTransition(): void {
    $submission = $this->createSubmission();

    // Set a transition in the element data.
    $submission->setElementData('workflow', [
      'workflow_state' => 'draft',
      'workflow_state_label' => 'Draft',
      'transition' => 'submit',
    ]);

    $this->hooks->webformSubmissionPresave($submission);

    $element_data = $submission->getElementData('workflow');
    $this->assertSame('review', $element_data['workflow_state']);
    $this->assertSame('Review', $element_data['workflow_state_label']);
  }

}
