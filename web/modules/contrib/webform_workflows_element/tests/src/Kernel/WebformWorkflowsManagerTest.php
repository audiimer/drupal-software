<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform\Entity\Webform;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests WebformWorkflowsManager service methods.
 *
 * Covers the 10 new public methods added during the OOP migration:
 * isTransitionEnabled, getColorOptions, getColorOptionValues,
 * getColorsForStates, getColorClassForState,
 * getAccessibleWorkflowElements, getWebformAccessOperations,
 * shouldShowWorkflowEditForm, shouldShowWorkflowLog,
 * and getRenderedWorkflowLog.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WebformWorkflowsManagerTest extends WebformWorkflowsElementKernelTestBase {

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

    $this->manager = $this->container
      ->get('webform_workflows_element.manager');
  }

  /**
   * Tests isTransitionEnabled() pure logic.
   *
   * Verifies three scenarios: key absent returns TRUE (enabled by
   * default), key explicitly TRUE returns TRUE, and key explicitly
   * FALSE returns FALSE.
   */
  public function testIsTransitionEnabled(): void {
    $element = [
      '#workflow' => 'test_workflow',
      '#access_transition_submit_workflow_enabled' => TRUE,
      '#access_transition_reject_workflow_enabled' => FALSE,
    ];

    // Key absent: enabled by default.
    $this->assertTrue(
      $this->manager->isTransitionEnabled($element, 'approve'),
    );
    // Key TRUE: enabled.
    $this->assertTrue(
      $this->manager->isTransitionEnabled($element, 'submit'),
    );
    // Key FALSE: disabled.
    $this->assertFalse(
      $this->manager->isTransitionEnabled($element, 'reject'),
    );
  }

  /**
   * Tests getColorOptions() returns color names from config.
   *
   * Verifies that the installed configuration provides a color
   * options array with names keyed by name (e.g. 'Blue' => 'Blue').
   */
  public function testGetColorOptions(): void {
    $options = $this->manager->getColorOptions();

    $this->assertIsArray($options);
    $this->assertArrayHasKey('Blue', $options);
    $this->assertSame('Blue', $options['Blue']);
    $this->assertArrayHasKey('Red', $options);
  }

  /**
   * Tests getColorOptionValues() parses pipe-delimited config.
   *
   * Verifies that the method returns an array of CSS classes keyed
   * by color name, parsed from the pipe-delimited setting string.
   */
  public function testGetColorOptionValues(): void {
    $values = $this->manager->getColorOptionValues();

    $this->assertIsArray($values);
    $this->assertSame(
      'webform-workflow-color-blue',
      $values['Blue'],
    );
    $this->assertSame(
      'webform-workflow-color-red',
      $values['Red'],
    );
  }

  /**
   * Tests getColorsForStates() maps state IDs and labels to classes.
   *
   * Verifies that the method maps CSS classes to both the state ID
   * and the state label, using the element's color configuration.
   */
  public function testGetColorsForStates(): void {
    $workflow_type = $this->workflow->getTypePlugin();
    $states = $workflow_type->getStates();
    $element = $this->webform->getElementDecoded('workflow');

    $colors = $this->manager->getColorsForStates($states, $element);

    // State with color configured.
    $this->assertSame(
      'webform-workflow-color-blue',
      $colors['draft'],
    );
    $this->assertSame(
      'webform-workflow-color-blue',
      $colors['Draft'],
    );
    $this->assertSame(
      'webform-workflow-color-green',
      $colors['review'],
    );
    // State without color configured.
    $this->assertArrayNotHasKey('approved', $colors);
  }

  /**
   * Tests getColorClassForState() with and without color.
   *
   * Verifies that a configured color returns the CSS class and
   * an unconfigured state returns NULL.
   */
  public function testGetColorClassForState(): void {
    $element = $this->webform->getElementDecoded('workflow');

    $this->assertSame(
      'webform-workflow-color-blue',
      $this->manager->getColorClassForState($element, 'draft'),
    );
    $this->assertNull(
      $this->manager->getColorClassForState($element, 'approved'),
    );
  }

  /**
   * Tests getAccessibleWorkflowElements() with and without elements.
   *
   * Admin (uid 1) should get all workflow elements for a webform
   * that has them, and an empty array for a webform without any
   * workflow elements.
   */
  public function testGetAccessibleWorkflowElements(): void {
    // Admin user gets all elements from the test webform.
    $elements = $this->manager->getAccessibleWorkflowElements(
      $this->adminUser,
      $this->webform,
    );
    $this->assertCount(1, $elements);
    $this->assertArrayHasKey('workflow', $elements);

    // A webform without workflow elements returns empty.
    $empty_webform = Webform::create([
      'id' => 'empty_webform',
      'title' => 'Empty Webform',
    ]);
    $empty_webform->setElements([
      'name' => [
        '#type' => 'textfield',
        '#title' => 'Name',
      ],
    ]);
    $empty_webform->save();

    $elements = $this->manager->getAccessibleWorkflowElements(
      $this->adminUser,
      $empty_webform,
    );
    $this->assertCount(0, $elements);
  }

  /**
   * Tests getWebformAccessOperations() returns expected IDs.
   *
   * Verifies the method returns transition-based and state-based
   * access operation IDs for all workflow elements on the webform.
   */
  public function testGetWebformAccessOperations(): void {
    $operations = $this->manager->getWebformAccessOperations(
      $this->webform,
    );

    $this->assertContains('transition_submit', $operations);
    $this->assertContains('transition_approve', $operations);
    $this->assertContains('transition_reject', $operations);
    $this->assertContains('update_at_state_draft', $operations);
    $this->assertContains('update_at_state_review', $operations);
    $this->assertContains('update_at_state_approved', $operations);
  }

  /**
   * Tests shouldShowWorkflowEditForm() returns FALSE in kernel.
   *
   * In a kernel test there is no route context, so the method
   * should return FALSE because the current route is not a
   * submission view page.
   */
  public function testShouldShowWorkflowEditForm(): void {
    $submission = $this->createSubmission();

    $this->assertFalse(
      $this->manager->shouldShowWorkflowEditForm($submission),
    );
  }

  /**
   * Tests shouldShowWorkflowLog() returns FALSE without log module.
   *
   * The webform_submission_log module is not installed, so the
   * method should immediately return FALSE.
   */
  public function testShouldShowWorkflowLog(): void {
    $submission = $this->createSubmission();

    $this->assertFalse(
      $this->manager->shouldShowWorkflowLog('view', $submission),
    );
  }

  /**
   * Tests getRenderedWorkflowLog() returns NULL without log module.
   *
   * The webform_submission_log module is not installed, so the
   * method should immediately return NULL.
   */
  public function testGetRenderedWorkflowLog(): void {
    $submission = $this->createSubmission();

    $this->assertNull(
      $this->manager->getRenderedWorkflowLog($submission),
    );
  }

}
