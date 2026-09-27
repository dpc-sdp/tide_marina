<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

/**
 * Removes cache tags that should not be sent to CloudFront.
 */
interface CacheTagFilterInterface {

  /**
   * Filters cache tags before they are prioritised and hashed.
   *
   * @param string[] $tags
   *   The cache tags of the response.
   *
   * @return string[]
   *   The remaining cache tags, re-indexed.
   */
  public function filter(array $tags): array;

}
