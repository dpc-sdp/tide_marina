<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Keeps only the cache tags that CloudFront invalidations can usefully target.
 *
 * Two rules apply, in order:
 *
 * - Tags matching the purge_queuer_coretags blacklist prefixes are dropped.
 *   The coretags queuer never queues invalidations for them, so keeping them
 *   out of the response header saves slots under CloudFront's 50-tag limit.
 * - When allowed tag patterns are configured, only tags matching at least one
 *   of them are kept. The defaults keep entity tags for content an editor
 *   changes directly, list tags for the entity types the content API lists,
 *   and config tags. Tags for child entities such as paragraphs, revision
 *   tags, and bookkeeping tags such as search_api_list or simple_sitemap are
 *   dropped: a change to a paragraph also saves its parent, and the others
 *   never describe a response a site visitor sees.
 *
 * The same filter is applied to the response header and to the queue (see
 * FilteringCacheTagsQueuer), so a tag is only ever invalidated if it can
 * appear on a cached object.
 *
 * Adapted from the cloudfront_purger_tags submodule of drupal/cloudfront_purger
 * (GPL-2.0-or-later).
 */
class DefaultCacheTagFilter implements CacheTagFilterInterface {

  /**
   * Constructs the filter.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param string[] $allowedTagPatterns
   *   PCRE patterns; a tag must match one of them to be kept. An empty list
   *   keeps every tag that is not blocklisted. Set by the
   *   `marina_cf_cachetags.allowed_tag_patterns` container parameter.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected array $allowedTagPatterns = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function filter(array $tags): array {
    // cspell:ignore-word blacklist -- Purge's config key.
    $blocklist = $this->configFactory
      ->get('purge_queuer_coretags.settings')
      ->get('blacklist');
    $blocklist = \is_array($blocklist)
      ? \array_filter($blocklist, static fn ($prefix): bool => \is_string($prefix) && $prefix !== '')
      : [];
    $patterns = \array_filter($this->allowedTagPatterns, static fn ($pattern): bool => \is_string($pattern) && $pattern !== '');

    return \array_values(\array_filter(
      $tags,
      static function (string $tag) use ($blocklist, $patterns): bool {
        foreach ($blocklist as $prefix) {
          if (\str_starts_with($tag, $prefix)) {
            return FALSE;
          }
        }
        if ($patterns === []) {
          return TRUE;
        }
        foreach ($patterns as $pattern) {
          if (\preg_match($pattern, $tag) === 1) {
            return TRUE;
          }
        }
        return FALSE;
      }
    ));
  }

}
