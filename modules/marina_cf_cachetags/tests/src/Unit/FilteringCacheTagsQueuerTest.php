<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\marina_cf_cachetags\DefaultCacheTagFilter;
use Drupal\marina_cf_cachetags\FilteringCacheTagsQueuer;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationsServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queuer\QueuerBase;
use Drupal\purge\Plugin\Purge\Queuer\QueuersServiceInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\FilteringCacheTagsQueuer
 * @group marina_cf_cachetags
 */
class FilteringCacheTagsQueuerTest extends UnitTestCase {

  /**
   * The expressions passed to the invalidation factory.
   *
   * @var string[]
   */
  protected array $queued = [];

  /**
   * The mocked queue service.
   */
  protected QueueServiceInterface $purgeQueue;

  /**
   * The queuer under test.
   */
  protected FilteringCacheTagsQueuer $queuer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $config_factory = $this->getConfigFactoryStub([
      'purge_queuer_coretags.settings' => [
        'blacklist' => ['4xx-response', 'route_match', 'http_response'],
      ],
    ]);
    $services = Yaml::parseFile(__DIR__ . '/../../../marina_cf_cachetags.services.yml');
    $patterns = $services['parameters']['marina_cf_cachetags.allowed_tag_patterns'];

    $queuers = $this->createMock(QueuersServiceInterface::class);
    $queuers->method('get')->with('coretags')->willReturn($this->createStub(QueuerBase::class));
    $factory = $this->createMock(InvalidationsServiceInterface::class);
    $factory->method('get')->willReturnCallback(function (string $type, string $expression): InvalidationInterface {
      $this->queued[] = $expression;
      return $this->createStub(InvalidationInterface::class);
    });
    $this->purgeQueue = $this->createMock(QueueServiceInterface::class);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('purge.queuers', $queuers);
    $container->set('purge.invalidation.factory', $factory);
    $container->set('purge.queue', $this->purgeQueue);
    $container->set('marina_cf_cachetags.cache_tag_filter', new DefaultCacheTagFilter($config_factory, $patterns));
    $this->queuer = new FilteringCacheTagsQueuer($container);
  }

  /**
   * @covers ::invalidateTags
   */
  public function testLandingPageSaveQueuesOnlyTagsSeenInResponses(): void {
    $this->purgeQueue->expects($this->once())->method('add');
    // The tags Drupal invalidates when a Tide landing page is saved.
    $this->queuer->invalidateTags([
      '4xx-response',
      'config:views.view.admin_audit_trail',
      'content_moderation_state:185',
      'content_moderation_state:185:revisions',
      'content_moderation_state_list',
      'node:185',
      'node:185:revisions',
      'node_list',
      'node_list:landing_page',
      'paragraph:1532',
      'paragraph:1532:revisions',
      'paragraph_list',
      'paragraph_list:hero_banner_with_cta',
      'path_alias:1339:revisions',
      'redirect:300',
      'redirect_list',
      'redirect_list:redirect',
      'route_match',
    ]);
    $this->assertSame(['node:185', 'node_list', 'node_list:landing_page', 'redirect:300'], $this->queued);
  }

  /**
   * @covers ::invalidateTags
   */
  public function testCronBookkeepingTagsAreNotQueued(): void {
    $this->purgeQueue->expects($this->never())->method('add');
    $this->queuer->invalidateTags([
      'search_api_list:node',
      'simple_sitemap:default',
      'simple_sitemap:index',
      'simple_sitemap:site-8888',
      'crop:68:revisions',
      'crop_list',
      'file_list',
    ]);
    $this->assertSame([], $this->queued);
  }

}
