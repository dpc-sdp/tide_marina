<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\marina_cf_cachetags\CacheTagsHash;
use Drupal\marina_cf_cachetags\CacheTagsHashInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\CacheTagsHash
 * @group marina_cf_cachetags
 */
class CacheTagsHashTest extends UnitTestCase {

  /**
   * The hash service under test.
   */
  protected CacheTagsHash $hash;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->hash = new CacheTagsHash();
  }

  /**
   * @covers ::hashTag
   */
  public function testHashTag(): void {
    $hash = $this->hash->hashTag('node:1');
    $this->assertSame(CacheTagsHashInterface::HASH_LENGTH, \strlen($hash));
    $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $hash);
    $this->assertSame($hash, $this->hash->hashTag('node:1'));
    $this->assertNotSame($hash, $this->hash->hashTag('node:2'));
    // Hashes only ever contain characters CloudFront accepts in a tag.
    $this->assertSame($hash, \trim($hash, ", \t"));
  }

  /**
   * @covers ::hashTags
   */
  public function testHashTags(): void {
    $this->assertSame([], $this->hash->hashTags([]));
    $hashes = $this->hash->hashTags(['node:1', 'user:1', 'config:system.site']);
    $this->assertSame([
      $this->hash->hashTag('node:1'),
      $this->hash->hashTag('user:1'),
      $this->hash->hashTag('config:system.site'),
    ], $hashes);
  }

}
