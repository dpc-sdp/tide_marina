<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\marina_cf_cachetags\DefaultCacheTagPrioritizer;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\DefaultCacheTagPrioritizer
 * @group marina_cf_cachetags
 */
class DefaultCacheTagPrioritizerTest extends UnitTestCase {

  /**
   * @covers ::prioritize
   */
  public function testPrioritize(): void {
    $prioritizer = new DefaultCacheTagPrioritizer();
    $this->assertSame([], $prioritizer->prioritize([]));

    $tags = [
      'config:block_list',
      'config:block.block.claro_page_title',
      'node_view',
      'node:203',
      'node_list:landing_page',
      'taxonomy_term:9193',
      'rendered',
      'scheduled_transitions_for:node:203',
      'user:34',
    ];
    $this->assertSame([
      // Entity tags first, original order kept.
      'node:203',
      'taxonomy_term:9193',
      'user:34',
      // Then list tags.
      'config:block_list',
      'node_list:landing_page',
      // Then everything else.
      'config:block.block.claro_page_title',
      'node_view',
      'rendered',
      'scheduled_transitions_for:node:203',
    ], $prioritizer->prioritize($tags));
  }

}
