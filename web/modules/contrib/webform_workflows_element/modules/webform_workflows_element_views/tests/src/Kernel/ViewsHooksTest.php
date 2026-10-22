<?php

namespace Drupal\Tests\webform_workflows_element_views\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Plugin\ViewsHandlerManager;
use Drupal\webform_workflows_element_views\Hook\ViewsHooks;
use Drupal\webform_workflows_element_views\Plugin\views\filter\WebformWorkflowsElementState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Views submodule hook class and service wiring.
 *
 * Covers ViewsHooks::viewsData() which registers the workflow
 * state filter for Views, and verifies the service resolves
 * correctly from the container.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class ViewsHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Extends KernelTestBase directly (not the shared base class)
   * because the Views submodule requires additional modules
   * (views, webform_views) that other tests do not need. No
   * workflow or webform fixtures are needed here — only service
   * wiring and hook output are tested.
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
    // Views integration modules required by the submodule.
    'views',
    'webform_views',
    'webform_workflows_element_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Minimal schema setup — no test fixtures needed, just enough
    // for the module to boot and register its Views hooks.
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('webform_submission');
    $this->installSchema('webform', ['webform']);
    $this->installConfig([
      'webform',
      'webform_workflows_element',
    ]);
  }

  /**
   * Tests viewsData() registers the workflow state filter.
   *
   * The hook should return a views data array for the
   * 'webform_submission' table containing a 'workflow_state'
   * filter with the correct plugin ID.
   */
  public function testViewsDataRegistersWorkflowStateFilter(): void {
    $hooks = $this->container->get(ViewsHooks::class);
    $data = $hooks->viewsData();

    $this->assertArrayHasKey('webform_submission', $data);
    $this->assertArrayHasKey(
      'workflow_state',
      $data['webform_submission'],
    );
    $this->assertSame(
      'webform_workflows_element_state',
      $data['webform_submission']['workflow_state']['filter']['id'],
    );
  }

  /**
   * Tests ViewsHooks resolves from the service container.
   *
   * Verifies that the service is registered and instantiates as
   * the correct class.
   */
  public function testServiceWiring(): void {
    $service = $this->container->get(ViewsHooks::class);
    $this->assertInstanceOf(ViewsHooks::class, $service);
  }

  /**
   * Tests the state filter instantiates with its join handler manager.
   *
   * The filter injects the plugin.manager.views.join service in create(); its
   * $joinManager property must be typed as the service's actual class,
   * ViewsHandlerManager, and not the sibling ViewsPluginManager (both extend
   * DefaultPluginManager, neither extends the other). With the wrong type,
   * building any View that uses this filter dies with a fatal TypeError before
   * it renders. This resolves the filter through the Views filter handler
   * manager the way Views itself does, so create() runs, and asserts it is
   * created and its join manager is the expected type.
   */
  public function testStateFilterInstantiatesWithJoinManager(): void {
    $filter = $this->container->get('plugin.manager.views.filter')
      ->createInstance('webform_workflows_element_state');
    $this->assertInstanceOf(WebformWorkflowsElementState::class, $filter);

    $join_manager = (new \ReflectionProperty($filter, 'joinManager'))->getValue($filter);
    $this->assertInstanceOf(ViewsHandlerManager::class, $join_manager);
  }

}
