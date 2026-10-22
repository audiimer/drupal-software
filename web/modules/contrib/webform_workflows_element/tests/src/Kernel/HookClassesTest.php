<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform_workflows_element\Hook\EntityTypeHooks;
use Drupal\webform_workflows_element\Hook\ThemeHooks;
use Drupal\webform_workflows_element\Hook\TokenHooks;
use Drupal\webform_workflows_element\Hook\WebformWorkflowsElementHooks;
use Drupal\webform_workflows_element\WebformSubmissionWorkflowListBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Hook class methods that can run in a kernel environment.
 *
 * Covers EntityTypeHooks, ThemeHooks, WebformWorkflowsElementHooks
 * access logic, TokenHooks info, and the deprecated wrapper
 * functions in webform_workflows_element.module.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class HookClassesTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * Tests entityTypeAlter() sets the workflow list builder.
   *
   * Without the webform_better_results module installed, the
   * entity type alter hook should override the webform_submission
   * list builder with WebformSubmissionWorkflowListBuilder.
   */
  public function testEntityTypeAlterSetsListBuilder(): void {
    $entity_type_hooks = $this->container->get(EntityTypeHooks::class);

    $entity_types = $this->container
      ->get('entity_type.manager')
      ->getDefinitions();

    $entity_type_hooks->entityTypeAlter($entity_types);

    $handler_classes = $entity_types['webform_submission']
      ->getHandlerClasses();
    $this->assertSame(
      WebformSubmissionWorkflowListBuilder::class,
      $handler_classes['list_builder'],
    );
  }

  /**
   * Tests theme() returns expected hook definitions.
   *
   * Verifies the ThemeHooks::theme() method returns an array
   * containing the three expected theme hook keys.
   */
  public function testThemeHookDefinitions(): void {
    $theme_hooks = $this->container->get(ThemeHooks::class);
    $definitions = $theme_hooks->theme();

    $this->assertArrayHasKey(
      'webform_workflows_element',
      $definitions,
    );
    $this->assertArrayHasKey(
      'webform_workflows_element_value',
      $definitions,
    );
    $this->assertArrayHasKey(
      'webform_handler_workflows_transition_email_summary',
      $definitions,
    );
  }

  /**
   * Tests preprocess sets color_class and changed_user_name.
   *
   * Verifies that preprocessWebformWorkflowsElementValue() sets
   * the color_class variable from the element's color config and
   * the changed_user_name from the loaded user entity.
   */
  public function testPreprocessWithColor(): void {
    $theme_hooks = $this->container->get(ThemeHooks::class);
    $element = $this->webform->getElementDecoded('workflow');

    $variables = [
      'element' => $element,
      'values' => [
        'workflow_state' => 'draft',
        'changed_user' => $this->adminUser->id(),
      ],
      'color_class' => NULL,
    ];

    $theme_hooks->preprocessWebformWorkflowsElementValue($variables);

    $this->assertSame(
      'with-color webform-workflow-color-blue',
      $variables['color_class'],
    );
    $this->assertSame(
      'admin',
      $variables['values']['changed_user_name'],
    );
  }

  /**
   * Tests preprocess without color leaves color_class NULL.
   *
   * Verifies that when a state has no color configured, the
   * color_class variable remains NULL after preprocessing.
   */
  public function testPreprocessWithoutColor(): void {
    $theme_hooks = $this->container->get(ThemeHooks::class);
    $element = $this->webform->getElementDecoded('workflow');

    $variables = [
      'element' => $element,
      'values' => [
        'workflow_state' => 'approved',
      ],
      'color_class' => NULL,
    ];

    $theme_hooks->preprocessWebformWorkflowsElementValue($variables);

    $this->assertNull($variables['color_class']);
  }

  /**
   * Tests webformElementAccess() returns Forbidden for create.
   *
   * When the element has #workflow but no
   * #access_create_workflow_enabled property, the create operation
   * should return AccessResult::forbidden().
   */
  public function testWebformElementAccessCreate(): void {
    $hooks = $this->container
      ->get(WebformWorkflowsElementHooks::class);

    $element = [
      '#workflow' => 'test_workflow',
    ];

    $result = $hooks->webformElementAccess(
      'create',
      $element,
    );

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Tests webformElementAccess() returns Neutral for update.
   *
   * When the element has #workflow but no
   * #access_update_workflow_enabled property, the update operation
   * should return AccessResult::neutral() (not forbidden like
   * create).
   */
  public function testWebformElementAccessUpdate(): void {
    $hooks = $this->container
      ->get(WebformWorkflowsElementHooks::class);

    $element = [
      '#workflow' => 'test_workflow',
    ];

    $result = $hooks->webformElementAccess(
      'update',
      $element,
    );

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests webformElementAccess() returns Neutral when enabled.
   *
   * When #access_update_workflow_enabled is explicitly TRUE, the
   * method should return AccessResult::neutral() (allowing other
   * access checks to proceed).
   */
  public function testWebformElementAccessEnabled(): void {
    $hooks = $this->container
      ->get(WebformWorkflowsElementHooks::class);

    $element = [
      '#workflow' => 'test_workflow',
      '#access_update_workflow_enabled' => TRUE,
    ];

    $result = $hooks->webformElementAccess(
      'update',
      $element,
    );

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests webformElementAccess() returns Forbidden when disabled.
   *
   * When #access_update_workflow_enabled is explicitly FALSE, the
   * method should return AccessResult::forbidden().
   */
  public function testWebformElementAccessDisabled(): void {
    $hooks = $this->container
      ->get(WebformWorkflowsElementHooks::class);

    $element = [
      '#workflow' => 'test_workflow',
      '#access_update_workflow_enabled' => FALSE,
    ];

    $result = $hooks->webformElementAccess(
      'update',
      $element,
    );

    $this->assertTrue($result->isForbidden());
  }

  /**
   * Tests webformElementAccess() for a non-workflow element.
   *
   * When the element does not have #workflow set, the method
   * should return AccessResult::neutral() regardless of operation.
   */
  public function testWebformElementAccessNonWorkflow(): void {
    $hooks = $this->container
      ->get(WebformWorkflowsElementHooks::class);

    $element = [
      '#type' => 'textfield',
    ];

    $result = $hooks->webformElementAccess(
      'create',
      $element,
    );

    $this->assertTrue($result->isNeutral());
  }

  /**
   * Tests tokenInfo() returns correct type and token structure.
   *
   * Verifies that the method returns a 'webform_workflow' type
   * and four dynamic token definitions under 'webform_workflow'.
   */
  public function testTokenInfo(): void {
    $token_hooks = $this->container->get(TokenHooks::class);
    $info = $token_hooks->tokenInfo();

    // Verify type.
    $this->assertArrayHasKey('types', $info);
    $this->assertArrayHasKey('webform_workflow', $info['types']);

    // Verify tokens.
    $this->assertArrayHasKey('tokens', $info);
    $this->assertArrayHasKey('webform_workflow', $info['tokens']);
    $tokens = $info['tokens']['webform_workflow'];
    $this->assertArrayHasKey('transition-url', $tokens);
    $this->assertArrayHasKey('transition-link', $tokens);
    $this->assertArrayHasKey(
      'transition-url-secure-token',
      $tokens,
    );
    $this->assertArrayHasKey(
      'transition-link-secure-token',
      $tokens,
    );
    $this->assertCount(4, $tokens);
  }

  /**
   * Tests deprecated getColorOptions() wrapper function.
   *
   * Verifies the procedural wrapper returns the same result as
   * the service method it delegates to.
   */
  public function testDeprecatedGetColorOptions(): void {
    $this->loadModuleFile();

    $manager = $this->container
      ->get('webform_workflows_element.manager');

    $this->assertSame(
      $manager->getColorOptions(),
      // @phpstan-ignore function.deprecated
      webform_workflows_element_get_color_options(),
    );
  }

  /**
   * Tests deprecated getColorOptionValues() wrapper function.
   *
   * Verifies the procedural wrapper returns the same result as
   * the service method it delegates to.
   */
  public function testDeprecatedGetColorOptionValues(): void {
    $this->loadModuleFile();

    $manager = $this->container
      ->get('webform_workflows_element.manager');

    $this->assertSame(
      $manager->getColorOptionValues(),
      // @phpstan-ignore function.deprecated
      webform_workflows_element_get_color_options_values(),
    );
  }

  /**
   * Tests deprecated isTransitionEnabled() wrapper function.
   *
   * Verifies the procedural wrapper returns the same result as
   * the service method it delegates to.
   */
  public function testDeprecatedIsTransitionEnabled(): void {
    $this->loadModuleFile();

    $element = [
      '#workflow' => 'test_workflow',
      '#access_transition_reject_workflow_enabled' => FALSE,
    ];

    $manager = $this->container
      ->get('webform_workflows_element.manager');

    $this->assertSame(
      $manager->isTransitionEnabled($element, 'submit'),
      // @phpstan-ignore function.deprecated
      webform_workflows_element_check_transition_enabled(
        $element,
        'submit',
      ),
    );
    $this->assertSame(
      $manager->isTransitionEnabled($element, 'reject'),
      // @phpstan-ignore function.deprecated
      webform_workflows_element_check_transition_enabled(
        $element,
        'reject',
      ),
    );
  }

  /**
   * Ensures the .module file is loaded.
   *
   * The deprecated wrapper functions live in the .module file
   * which may not be loaded in kernel tests.
   */
  protected function loadModuleFile(): void {
    $module_handler = $this->container->get('module_handler');
    $module_handler->loadInclude(
      'webform_workflows_element',
      'module',
    );
  }

}
