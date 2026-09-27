<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;

/**
 * Builds the invalidation token values sent to the cache invalidation API.
 */
final class InvalidationTokens {

  /**
   * Separators offered by purge_tokens for the `invalidations` token group.
   */
  public const SEPARATORS = [
    'separated_comma' => ',',
    'separated_pipe' => '|',
    'separated_tab' => "\t",
  ];

  /**
   * Returns the `tag:<hash>` expressions for the tag invalidations in a batch.
   *
   * Only tag invalidations are transformed; URL and path invalidations are
   * left to purge_tokens so other purgers keep working. Expressions that
   * already carry a `tag:` or `#` marker are assumed to be pre-hashed and are
   * passed through unchanged. Duplicates are removed and order is preserved.
   *
   * @param iterable<mixed> $invalidations
   *   The invalidations offered to the purger, possibly with sparse keys.
   * @param \Drupal\marina_cf_cachetags\CacheTagsHashInterface $hash
   *   The hash shared with the response header.
   *
   * @return string[]
   *   The expressions, re-indexed.
   */
  public static function hashedTagExpressions(iterable $invalidations, CacheTagsHashInterface $hash): array {
    $expressions = [];
    foreach ($invalidations as $invalidation) {
      if (!$invalidation instanceof InvalidationInterface || $invalidation->getType() !== 'tag') {
        continue;
      }
      $expression = $invalidation->getExpression();
      if (!\is_string($expression) || $expression === '') {
        continue;
      }
      // The cache invalidation API hashes/normalises the raw tag itself, so
      // send the bare xxHash3 hash — the same value emitted in the
      // x-amz-meta-cache-tag response header.
      $expressions[] = $hash->hashTag($expression);
    }
    return \array_values(\array_unique($expressions));
  }

}
