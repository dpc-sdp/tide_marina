<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit\Plugin\Purge\TagsHeader;

use Drupal\marina_cf_cachetags\CacheTagFilterInterface;
use Drupal\marina_cf_cachetags\CacheTagsHash;
use Drupal\marina_cf_cachetags\DefaultCacheTagPrioritizer;
use Drupal\marina_cf_cachetags\Plugin\Purge\TagsHeader\MarinaCfCachetagsHeader;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\Plugin\Purge\TagsHeader\MarinaCfCachetagsHeader
 * @group marina_cf_cachetags
 */
class MarinaCfCachetagsHeaderTest extends UnitTestCase {

  /**
   * The hash service shared with the plugin.
   */
  protected CacheTagsHash $hash;

  /**
   * The header plugin under test.
   */
  protected MarinaCfCachetagsHeader $header;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->hash = new CacheTagsHash();
    $filter = $this->createMock(CacheTagFilterInterface::class);
    $filter->method('filter')->willReturnCallback(static fn (array $tags): array => \array_values(\array_filter($tags, static fn (string $t): bool => $t !== 'http_response')));
    $this->header = new MarinaCfCachetagsHeader(
      [],
      'marina_cf_cachetags',
      ['id' => 'marina_cf_cachetags', 'header_name' => 'x-amz-meta-cache-tag'],
      $this->hash,
      $filter,
      new DefaultCacheTagPrioritizer(),
    );
  }

  /**
   * @covers ::getHeaderName
   */
  public function testHeaderName(): void {
    $this->assertSame('x-amz-meta-cache-tag', $this->header->getHeaderName());
  }

  /**
   * @covers ::getValue
   */
  public function testValueIsCommaSeparatedHashesWithEntityTagsFirst(): void {
    $value = $this->header->getValue([
      'config:block_list',
      'config:block.block.claro_page_title',
      'http_response',
      'node:203',
      'node:203',
      'taxonomy_term:9193',
    ]);
    $this->assertSame(\implode(',', [
      $this->hash->hashTag('node:203'),
      $this->hash->hashTag('taxonomy_term:9193'),
      $this->hash->hashTag('config:block_list'),
      $this->hash->hashTag('config:block.block.claro_page_title'),
    ]), $value);
    $this->assertStringNotContainsString(' ', $value);
    $this->assertSame('', $this->header->getValue([]));
    $this->assertSame('', $this->header->getValue(['', 'http_response']));
  }

  /**
   * @covers ::getValue
   */
  public function testValueIsCappedAtCloudFrontLimit(): void {
    $tags = \array_map(static fn (int $i): string => "config:thing$i", \range(1, 70));
    $tags[] = 'node:999';
    $hashes = \explode(',', $this->header->getValue($tags));
    $this->assertCount(MarinaCfCachetagsHeader::MAX_TAGS, $hashes);
    $this->assertSame($this->hash->hashTag('node:999'), $hashes[0]);
  }

}
