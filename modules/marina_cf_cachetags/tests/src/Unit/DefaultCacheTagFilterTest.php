<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\marina_cf_cachetags\DefaultCacheTagFilter;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\DefaultCacheTagFilter
 * @group marina_cf_cachetags
 */
class DefaultCacheTagFilterTest extends UnitTestCase {

  /**
   * @covers ::filter
   */
  public function testFilterUsesBlocklistPrefixes(): void {
    $filter = new DefaultCacheTagFilter($this->getConfigFactoryStub([
      'purge_queuer_coretags.settings' => [
        'blacklist' => ['config:filter.format', 'http_response', 'extensions', ''],
      ],
    ]));
    $this->assertSame(
      ['node:1', 'config:system.site', 'rendered'],
      $filter->filter([
        'node:1',
        'config:filter.format.rich_text',
        'http_response',
        'config:system.site',
        'extensions',
        'rendered',
      ])
    );
  }

  /**
   * @covers ::filter
   */
  public function testFilterWithoutBlocklistPassesThrough(): void {
    $tags = ['node:1', 'http_response'];
    foreach ([[], NULL, 'not-an-array'] as $blacklist) {
      $filter = new DefaultCacheTagFilter($this->getConfigFactoryStub([
        'purge_queuer_coretags.settings' => ['blacklist' => $blacklist],
      ]));
      $this->assertSame($tags, $filter->filter($tags));
    }
  }

}
