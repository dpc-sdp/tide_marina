<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

/**
 * Hashes cache tags with xxHash3, truncated to 6 hex characters.
 *
 * Adapted from the cloudfront_purger_tags submodule of drupal/cloudfront_purger
 * (GPL-2.0-or-later).
 */
class CacheTagsHash implements CacheTagsHashInterface {

  /**
   * {@inheritdoc}
   */
  public function hashTag(string $tag): string {
    return \substr(\hash('xxh3', $tag), 0, self::HASH_LENGTH);
  }

  /**
   * {@inheritdoc}
   */
  public function hashTags(array $tags): array {
    return \array_map([$this, 'hashTag'], $tags);
  }

}
