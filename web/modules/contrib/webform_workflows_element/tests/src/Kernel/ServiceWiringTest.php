<?php

namespace Drupal\Tests\webform_workflows_element\Kernel;

use Drupal\webform_workflows_element\EventSubscriber\TransitionEventSubscriber;
use Drupal\webform_workflows_element\Hook\EntityTypeHooks;
use Drupal\webform_workflows_element\Hook\FormHooks;
use Drupal\webform_workflows_element\Hook\ResultsFilterHooks;
use Drupal\webform_workflows_element\Hook\ThemeHooks;
use Drupal\webform_workflows_element\Hook\TokenHooks;
use Drupal\webform_workflows_element\Hook\WebformWorkflowsElementHooks;
use Drupal\webform_workflows_element\Service\WebformWorkflowsManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that all services and hook classes instantiate correctly.
 *
 * Catches DI wiring errors such as wrong service IDs, missing
 * constructor arguments, or circular dependencies by retrieving
 * every service modified in the OOP migration commit from the
 * container and asserting the correct type.
 *
 * @group webform_workflows_element
 */
#[Group('webform_workflows_element')]
#[RunTestsInSeparateProcesses]
class ServiceWiringTest extends WebformWorkflowsElementKernelTestBase {

  /**
   * Tests that the main manager service is wired correctly.
   *
   * Retrieves the webform_workflows_element.manager service from
   * the container and asserts it is a WebformWorkflowsManager.
   */
  public function testWorkflowsManagerService(): void {
    $service = $this->container
      ->get('webform_workflows_element.manager');
    $this->assertInstanceOf(WebformWorkflowsManager::class, $service);
  }

  /**
   * Tests that the transition event subscriber is wired correctly.
   *
   * Retrieves the event subscriber service and asserts its type.
   */
  public function testTransitionEventSubscriber(): void {
    $service = $this->container
      ->get('webform_workflows_element.transition_events_subscriber');
    $this->assertInstanceOf(
      TransitionEventSubscriber::class,
      $service,
    );
  }

  /**
   * Tests that WebformWorkflowsElementHooks is wired correctly.
   *
   * Retrieves the hook class by its FQCN service ID and asserts
   * the instance type.
   */
  public function testWebformWorkflowsElementHooks(): void {
    $service = $this->container
      ->get(WebformWorkflowsElementHooks::class);
    $this->assertInstanceOf(
      WebformWorkflowsElementHooks::class,
      $service,
    );
  }

  /**
   * Tests that EntityTypeHooks is wired correctly.
   *
   * Retrieves the hook class by its FQCN service ID and asserts
   * the instance type.
   */
  public function testEntityTypeHooks(): void {
    $service = $this->container->get(EntityTypeHooks::class);
    $this->assertInstanceOf(EntityTypeHooks::class, $service);
  }

  /**
   * Tests that FormHooks is wired correctly.
   *
   * Retrieves the hook class by its FQCN service ID and asserts
   * the instance type.
   */
  public function testFormHooks(): void {
    $service = $this->container->get(FormHooks::class);
    $this->assertInstanceOf(FormHooks::class, $service);
  }

  /**
   * Tests that ThemeHooks is wired correctly.
   *
   * Retrieves the hook class by its FQCN service ID and asserts
   * the instance type.
   */
  public function testThemeHooks(): void {
    $service = $this->container->get(ThemeHooks::class);
    $this->assertInstanceOf(ThemeHooks::class, $service);
  }

  /**
   * Tests that TokenHooks is wired correctly.
   *
   * Retrieves the hook class by its FQCN service ID and asserts
   * the instance type.
   */
  public function testTokenHooks(): void {
    $service = $this->container->get(TokenHooks::class);
    $this->assertInstanceOf(TokenHooks::class, $service);
  }

  /**
   * Tests that ResultsFilterHooks is wired correctly.
   *
   * Retrieves the hook class by its FQCN service ID and asserts
   * the instance type.
   */
  public function testResultsFilterHooks(): void {
    $service = $this->container->get(ResultsFilterHooks::class);
    $this->assertInstanceOf(ResultsFilterHooks::class, $service);
  }

}
