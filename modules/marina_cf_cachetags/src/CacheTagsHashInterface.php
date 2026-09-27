<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

/**
 * Hashes Drupal cache tags into short strings for CloudFront.
 *
 * CloudFront stores at most 50 tags per cached object and caps the header at
 * 1,783 characters, while Drupal responses routinely carry dozens of long tags
 * such as `config:block.block.claro_page_title`. Hashing keeps the header well
 * inside those limits. The same hash must be used on both sides: in the
 * `x-amz-meta-cache-tag` response header and in the `#<hash>` invalidation
 * items sent to CloudFront.
 *
 * Adapted from the cloudfront_purger_tags submodule of drupal/cloudfront_purger
 * (GPL-2.0-or-later).
 */
interface CacheTagsHashInterface {

  /**
   * Length of the hash output.
   *
   * 6 hex characters = 16^6 = 16,777,216 values; collisions become likely only
   * around ~5,000 distinct tags (birthday bound).
   */
  public const HASH_LENGTH = 6;

  /**
   * Hashes a single cache tag.
   *
   * @param string $tag
   *   The cache tag.
   *
   * @return string
   *   The hashed tag.
   */
  public function hashTag(string $tag): string;

  /**
   * Hashes a list of cache tags, preserving order.
   *
   * @param string[] $tags
   *   The cache tags.
   *
   * @return string[]
   *   The hashed tags.
   */
  public function hashTags(array $tags): array;

}
