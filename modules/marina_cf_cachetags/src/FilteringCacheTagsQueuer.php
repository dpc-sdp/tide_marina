<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

use Drupal\purge_queuer_coretags\CacheTagsQueuer;

/**
 * Queues only the invalidated cache tags that pass the cache tag filter.
 *
 * Replaces the class of the purge_queuer_coretags.queuer service (see
 * MarinaCfCachetagsServiceProvider). Tags the filter removes are never in the
 * x-amz-meta-cache-tag header, so invalidating them cannot match a cached
 * object. Queuing them anyway would only add CloudFront invalidations.
 */
class FilteringCacheTagsQueuer extends CacheTagsQueuer {

  /**
   * The cache tag filter shared with the response header.
   */
  protected ?CacheTagFilterInterface $cacheTagFilter = NULL;

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    $this->cacheTagFilter ??= $this->container->get('marina_cf_cachetags.cache_tag_filter');
    $tags = $this->cacheTagFilter->filter(\array_values(\array_filter($tags, 'is_string')));
    if ($tags !== []) {
      parent::invalidateTags($tags);
    }
  }

}
