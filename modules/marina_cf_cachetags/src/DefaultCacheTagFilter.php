<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Drops tags matching the purge_queuer_coretags blacklist prefixes.
 *
 * The coretags queuer never queues invalidations for blacklisted tags, so
 * keeping them out of the response header makes the header describe exactly
 * what can be invalidated and saves slots under CloudFront's 50-tag limit.
 *
 * Adapted from the cloudfront_purger_tags submodule of drupal/cloudfront_purger
 * (GPL-2.0-or-later).
 */
class DefaultCacheTagFilter implements CacheTagFilterInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function filter(array $tags): array {
    // cspell:ignore-word blacklist -- Purge's config key.
    $blocklist = $this->configFactory
      ->get('purge_queuer_coretags.settings')
      ->get('blacklist');

    if (!\is_array($blocklist) || $blocklist === []) {
      return \array_values($tags);
    }

    return \array_values(\array_filter(
      $tags,
      static function (string $tag) use ($blocklist): bool {
        foreach ($blocklist as $prefix) {
          if (\is_string($prefix) && $prefix !== '' && \str_starts_with($tag, $prefix)) {
            return FALSE;
          }
        }
        return TRUE;
      }
    ));
  }

}
