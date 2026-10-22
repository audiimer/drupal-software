<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\Core\Render\RenderContext;
use Drupal\webform\Entity\Webform;
use Drupal\webform_workflows_element\Controller\WorkflowsSummaryController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests WorkflowsSummaryController graceful handling of webform_ui absence.
 *
 * The controller generates links to webform_ui routes for editing
 * workflow elements and adding new ones. When the webform_ui module
 * is not installed, those routes do not exist and would cause a
 * RouteNotFoundException. These tests verify that the controller
 * falls back to plain-text messages advising the user to enable the
 * Webform UI module.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class WorkflowsSummaryControllerTest extends WebformWorkflowsElementKernelTestBase {

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

    $this->controller = WorkflowsSummaryController::create(
      $this->container,
    );
  }

  /**
   * Tests renderSummaryForWebform() without webform_ui for empty form.
   *
   * When a webform has no workflow elements and the webform_ui module
   * is not installed, the controller cannot link to the "add element"
   * route. It should render a plain-text message advising the user to
   * enable the Webform UI module instead of crashing with a
   * RouteNotFoundException.
   */
  public function testRenderSummaryForWebformWithoutWebformUi(): void {
    $this->assertFalse(
      $this->container->get('module_handler')
        ->moduleExists('webform_ui'),
      'webform_ui must not be installed for this test.',
    );

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

    $build = $this->controller->renderSummaryForWebform($empty_webform);

    $this->assertIsArray($build);
    $markup = (string) $build['#markup'];
    $this->assertStringContainsString(
      'Enable the Webform UI module to add one',
      $markup,
    );
    $this->assertStringNotContainsString(
      'entity.webform_ui',
      $markup,
    );
  }

  /**
   * Tests renderWorkflowSummaryTable() without webform_ui.
   *
   * When the webform_ui module is not installed, the enabled and
   * disabled transitions tables should show a fallback message
   * telling the user to enable Webform UI, rather than generating
   * links to the nonexistent webform_ui routes.
   */
  public function testRenderWorkflowSummaryTableWithoutWebformUi(): void {
    $this->assertFalse(
      $this->container->get('module_handler')
        ->moduleExists('webform_ui'),
      'webform_ui must not be installed for this test.',
    );

    $renderer = $this->container->get('renderer');
    $build = $renderer->executeInRenderContext(
      new RenderContext(),
      fn () => $this->controller->renderWorkflowSummaryTable(
        $this->workflow,
        $this->webform,
        'workflow',
      ),
    );

    // The result is an array with two tables: enabled [0]
    // and disabled [1].
    $this->assertCount(2, $build);

    $enabled_empty = (string) $build[0]['#empty'];
    $this->assertStringContainsString(
      'Enable the Webform UI module',
      $enabled_empty,
    );
    $this->assertStringNotContainsString(
      '<a href=',
      $enabled_empty,
    );

    $disabled_empty = (string) $build[1]['#empty'];
    $this->assertStringContainsString(
      'Enable the Webform UI module',
      $disabled_empty,
    );
    $this->assertStringNotContainsString(
      '<a href=',
      $disabled_empty,
    );
  }

}
