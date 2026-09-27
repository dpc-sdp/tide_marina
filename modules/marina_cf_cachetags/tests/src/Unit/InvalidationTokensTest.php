<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\marina_cf_cachetags\CacheTagsHash;
use Drupal\marina_cf_cachetags\InvalidationTokens;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\InvalidationTokens
 * @group marina_cf_cachetags
 */
class InvalidationTokensTest extends UnitTestCase {

  /**
   * Builds an invalidation double.
   */
  protected function invalidation(string $type, string $expression): InvalidationInterface {
    $invalidation = $this->createMock(InvalidationInterface::class);
    $invalidation->method('getType')->willReturn($type);
    $invalidation->method('getExpression')->willReturn($expression);
    return $invalidation;
  }

  /**
   * @covers ::hashedTagExpressions
   */
  public function testHashedTagExpressions(): void {
    $hash = new CacheTagsHash();
    $this->assertSame([], InvalidationTokens::hashedTagExpressions([], $hash));

    $expressions = InvalidationTokens::hashedTagExpressions([
      // Sparse keys, as handed over by PurgersService. Only tag invalidations
      // are hashed; url items, empty expressions and non-invalidations are
      // skipped, and duplicates are removed with order preserved.
      3 => $this->invalidation('tag', 'node:203'),
      4 => $this->invalidation('url', 'http://example.com/'),
      5 => $this->invalidation('tag', 'node:203'),
      6 => $this->invalidation('tag', ''),
      7 => 'not an invalidation',
      8 => $this->invalidation('tag', 'config:system.site'),
    ], $hash);

    $this->assertSame([
      $hash->hashTag('node:203'),
      $hash->hashTag('config:system.site'),
    ], $expressions);
  }

}
