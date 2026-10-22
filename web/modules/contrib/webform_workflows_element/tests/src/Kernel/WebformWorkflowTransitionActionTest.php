<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform_workflows_element\Plugin\Action\WebformWorkflowTransition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the WebformWorkflowTransition action plugin.
 *
 * Covers defaultConfiguration(), access() checks for various
 * invalid and valid scenarios, and calculateDependencies().
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WebformWorkflowTransitionActionTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * The action plugin instance.
   *
   * @var \Drupal\webform_workflows_element\Plugin\Action\WebformWorkflowTransition
   */
  protected WebformWorkflowTransition $action;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an action plugin configured to run the 'submit'
    // transition on 'test_workflow'. This transition is only
    // valid from 'draft' state (moves to 'review'). Tests for
    // other transitions or wrong workflows create their own
    // plugin instances.
    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = $this->container->get('plugin.manager.action');
    $this->action = $action_manager->createInstance(
      'webform_workflow_transition',
      [
        'workflow' => 'test_workflow',
        'transition' => 'submit',
      ],
    );
  }

  /**
   * Tests defaultConfiguration() returns expected defaults.
   *
   * The default configuration should have workflow and transition
   * both set to NULL.
   */
  public function testDefaultConfiguration(): void {
    $defaults = $this->action->defaultConfiguration();

    $this->assertSame([
      'workflow' => NULL,
      'transition' => NULL,
      'element' => NULL,
    ], $defaults);
  }

  /**
   * Tests access() forbidden for non-WebformSubmission object.
   *
   * Passing a non-submission entity to the access check should
   * return a forbidden result because it is not a valid webform
   * submission.
   */
  public function testAccessForbiddenForNonSubmission(): void {
    $access = $this->action->access(
      $this->adminUser,
      $this->adminUser,
      TRUE,
    );
    $this->assertInstanceOf(AccessResultInterface::class, $access);
    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests access() forbidden when webform has no workflow elements.
   *
   * A submission from a webform without workflow elements should
   * produce a forbidden access result.
   */
  public function testAccessForbiddenForNoWorkflowElements(): void {
    $plain = Webform::create([
      'id' => 'plain_webform',
      'title' => 'Plain',
    ]);
    $plain->setElements([
      'name' => ['#type' => 'textfield', '#title' => 'Name'],
    ]);
    $plain->save();

    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('webform_submission');
    $submission = $storage->create([
      'webform_id' => 'plain_webform',
      'uid' => $this->adminUser->id(),
      'data' => [],
    ]);
    $submission->save();

    $access = $this->action->access(
      $submission,
      $this->adminUser,
      TRUE,
    );
    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests access() forbidden for wrong workflow ID.
   *
   * When the action is configured for a different workflow than
   * the one on the submission's element, access should be
   * forbidden.
   */
  public function testAccessForbiddenForWrongWorkflow(): void {
    $submission = $this->createSubmission();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = $this->container->get('plugin.manager.action');
    $wrong_wf_action = $action_manager->createInstance(
      'webform_workflow_transition',
      [
        'workflow' => 'nonexistent_workflow',
        'transition' => 'submit',
      ],
    );

    $access = $wrong_wf_action->access(
      $submission,
      $this->adminUser,
      TRUE,
    );
    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests access() forbidden for invalid transition from state.
   *
   * When the configured transition is not available from the
   * submission's current state, access should be forbidden.
   */
  public function testAccessForbiddenForInvalidTransition(): void {
    $submission = $this->createSubmission();

    /** @var \Drupal\Core\Action\ActionManager $action_manager */
    $action_manager = $this->container->get('plugin.manager.action');
    $approve_action = $action_manager->createInstance(
      'webform_workflow_transition',
      [
        'workflow' => 'test_workflow',
        'transition' => 'approve',
      ],
    );

    // 'approve' is not available from 'draft' state.
    $access = $approve_action->access(
      $submission,
      $this->adminUser,
      TRUE,
    );
    $this->assertTrue($access->isForbidden());
  }

  /**
   * Tests access() allowed for a valid transition.
   *
   * When the correct workflow and a valid transition from the
   * current state are configured, access should not be forbidden.
   */
  public function testAccessAllowedForValidTransition(): void {
    $submission = $this->createSubmission();

    $access = $this->action->access(
      $submission,
      $this->adminUser,
      TRUE,
    );
    // Admin with correct workflow + valid transition.
    $this->assertFalse($access->isForbidden());
  }

  /**
   * Tests calculateDependencies() includes workflow config.
   *
   * The plugin should declare a dependency on the workflow
   * configuration entity when a workflow is configured.
   */
  public function testCalculateDependencies(): void {
    $dependencies = $this->action->calculateDependencies();

    $this->assertArrayHasKey('config', $dependencies);
    $this->assertContains(
      'workflows.workflow.test_workflow',
      $dependencies['config'],
    );
  }

}
