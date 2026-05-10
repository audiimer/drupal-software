<?php

namespace Drupal\Tests\quicktabs\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\quicktabs\Plugin\TabType\BlockContent as BlockContentPlugin;
use Drupal\user\Entity\User;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\user\UserInterface;

/**
 * Tests the BlockContent TabType rendering.
 *
 * @group quicktabs
 */
class BlockContentRenderTest extends KernelTestBase {

  use BlockCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'block_content',
    'quicktabs',
  ];

  /**
   * A test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected UserInterface $user;

  /**
   * The BlockContent plugin instance.
   *
   * @var \Drupal\quicktabs\Plugin\TabType\BlockContent
   */
  protected BlockContentPlugin $blockContentPlugin;

  /**
   * Setup before each test.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();

    // Install necessary schema.
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('user');

    // Create a test user.
    $this->user = User::create([
      'name' => 'test_user',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $this->user->save();

    $container = $this->container;
    // Create a BlockContent plugin instance.
    $this->blockContentPlugin = new BlockContentPlugin(
      [],
      'block_content',
      [],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.block'),
      $container->get('context.repository'),
      $container->get('current_user')
    );
  }

  /**
   * Tests rendering a custom block content.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testRenderBlockContent() {
    // Create a block content entity.
    $blockContent = BlockContent::create([
      'info' => 'Test Block',
      'type' => 'basic',
      'uuid' => 'test-uuid-123',
    ]);
    $blockContent->save();

    $tab = [
      'type' => 'block_content',
      'content' => [
        'block_content' => [
          'options' => [
            'bid' => 'block_content:test-uuid-123',
          ],
        ],
      ],
    ];

    $output = $this->blockContentPlugin->render($tab);

    // Ensure output is not empty.
    $this->assertNotEmpty($output, 'Block content was rendered correctly.');

    // Ensure cache tags are merged properly.
    $expected_cache_tags = Cache::mergeTags([], $blockContent->getCacheTags());
    $this->assertEqualsCanonicalizing(
      array_merge($expected_cache_tags, ['block_content_view']),
      $output['#cache']['tags'],
      'Cache tags are correctly merged.'
    );
  }

  /**
   * Tests rendering a plugin block.
   */
  public function testRenderPluginBlock() {
    $tab = [
      'type' => 'plugin',
      'content' => [
        'plugin' => [
          'options' => [
            'bid' => 'system_powered_by_block',
          ],
        ],
      ],
    ];

    $output = $this->blockContentPlugin->render($tab);

    // Check that output is not empty.
    $this->assertNotEmpty($output, 'Plugin block was rendered correctly.');
  }

  /**
   * Tests that access restrictions work.
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   * @throws \Exception
   */
  public function testRenderPluginBlockWithAccessDenied() {
    // Mock a block plugin.
    $mock_block = $this->getMockBuilder('Drupal\Core\Block\BlockBase')
      ->disableOriginalConstructor()
      ->getMock();

    // Force access check to return forbidden.
    $mock_block->method('access')
      ->willReturn(AccessResult::forbidden());

    // Mock the block manager to return the mocked block.
    $block_manager_mock = $this->createMock(BlockManagerInterface::class);
    $block_manager_mock->method('createInstance')->willReturn($mock_block);
    $container = $this->container;
    // Inject the mock block manager into the plugin.
    $this->blockContentPlugin = new BlockContentPlugin(
      [],
      'block_content',
      [],
      $container->get('entity_type.manager'),
      $container->get('entity.repository'),
      $block_manager_mock,
      $container->get('context.repository'),
      $container->get('current_user')
    );

    // Define a tab array for the test.
    $tab = [
      'type' => 'plugin',
      'content' => [
        'plugin' => [
          'options' => [
            'bid' => 'some_protected_block',
          ],
        ],
      ],
    ];

    // Call render() and check if access is denied.
    $output = $this->blockContentPlugin->render($tab);

    // The output should be an empty array if access is denied.
    $this->assertEmpty($output, 'Access denied block returned an empty render array.');
  }

}
