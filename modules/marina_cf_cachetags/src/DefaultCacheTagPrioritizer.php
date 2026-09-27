<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

/**
 * Orders tags as entity tags, then list tags, then everything else.
 *
 * CloudFront only stores the first 50 tags of a cached object. Content edits
 * invalidate entity tags (node:123, media:45), so those go first; list tags
 * (node_list, node_list:article) next; config:* and other bookkeeping tags
 * last. Order within each group is preserved.
 *
 * Adapted from the cloudfront_purger_tags submodule of drupal/cloudfront_purger
 * (GPL-2.0-or-later).
 */
class DefaultCacheTagPrioritizer implements CacheTagPrioritizerInterface {

  /**
   * {@inheritdoc}
   */
  public function prioritize(array $tags): array {
    $entity = [];
    $list = [];
    $other = [];

    foreach ($tags as $tag) {
      if (\preg_match('/^[a-z_]+:\d+$/', $tag)) {
        $entity[] = $tag;
      }
      elseif (\str_contains($tag, '_list')) {
        $list[] = $tag;
      }
      else {
        $other[] = $tag;
      }
    }

    return [...$entity, ...$list, ...$other];
  }

}
