<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

/**
 * Orders cache tags so the most valuable ones survive CloudFront's limits.
 */
interface CacheTagPrioritizerInterface {

  /**
   * Prioritises cache tags for the response header.
   *
   * @param string[] $tags
   *   The (already filtered) cache tags.
   *
   * @return string[]
   *   The cache tags in priority order, re-indexed.
   */
  public function prioritize(array $tags): array;

}
