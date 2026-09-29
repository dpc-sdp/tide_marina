<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\marina_cf_cachetags\CacheTagsHash;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests marina_cf_cachetags_tokens_alter().
 *
 * @group marina_cf_cachetags
 */
class TokensAlterTest extends UnitTestCase {

  /**
   * The hash service registered in the test container.
   */
  protected CacheTagsHash $hash;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once __DIR__ . '/../../../marina_cf_cachetags.module';
    $this->hash = new CacheTagsHash();
    $container = new ContainerBuilder();
    $container->set('marina_cf_cachetags.cache_tags_hash', $this->hash);
    \Drupal::setContainer($container);
  }

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
   * Tag expressions are hashed and prefixed; other types are left alone.
   */
  public function testTagsAreHashedAndPrefixed(): void {
    $context = [
      'type' => 'invalidations',
      'tokens' => ['separated_comma' => '[invalidations:separated_comma]'],
      // Keys deliberately do not start at 0: this is how PurgersService hands
      // a batch to the purger when an earlier item was unsupported.
      'data' => [
        'invalidations' => [
          3 => $this->invalidation('tag', 'node:203'),
          4 => $this->invalidation('url', 'http://example.com/'),
          5 => $this->invalidation('tag', 'node:203'),
          6 => $this->invalidation('tag', '#already'),
          7 => $this->invalidation('tag', 'config:system.site'),
        ],
      ],
    ];
    // purge_tokens produced nothing for this batch (sparse keys).
    $replacements = [];
    marina_cf_cachetags_tokens_alter($replacements, $context, new BubbleableMetadata());
    $this->assertSame(
      'tag:' . $this->hash->hashTag('node:203') . ',#already,tag:' . $this->hash->hashTag('config:system.site'),
      $replacements['[invalidations:separated_comma]']
    );
  }

  /**
   * Other token types and unknown token names are untouched.
   */
  public function testIgnoresUnrelatedTokens(): void {
    $bubbleable = new BubbleableMetadata();
    $replacements = ['[invalidation:expression]' => 'node:1'];
    marina_cf_cachetags_tokens_alter($replacements, [
      'type' => 'invalidation',
      'tokens' => ['expression' => '[invalidation:expression]'],
      'data' => ['invalidation' => $this->invalidation('tag', 'node:1')],
    ], $bubbleable);
    $this->assertSame(['[invalidation:expression]' => 'node:1'], $replacements);

    $replacements = ['[invalidations:other]' => 'x'];
    marina_cf_cachetags_tokens_alter($replacements, [
      'type' => 'invalidations',
      'tokens' => ['other' => '[invalidations:other]'],
      'data' => ['invalidations' => [$this->invalidation('tag', 'node:1')]],
    ], $bubbleable);
    $this->assertSame(['[invalidations:other]' => 'x'], $replacements);
  }

}
