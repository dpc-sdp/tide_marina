<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Filters the tags the coretags queuer adds to the purge queue.
 */
class MarinaCfCachetagsServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('purge_queuer_coretags.queuer')) {
      $container->getDefinition('purge_queuer_coretags.queuer')
        ->setClass(FilteringCacheTagsQueuer::class);
    }
  }

}
