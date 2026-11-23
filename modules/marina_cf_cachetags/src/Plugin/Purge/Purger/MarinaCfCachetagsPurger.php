<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags\Plugin\Purge\Purger;

use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge_purger_http\Plugin\Purge\Purger\HttpBundledPurger;

/**
 * Sends tag invalidations to the cache invalidation API, at most 3 times each.
 *
 * Purge keeps a failed invalidation in the queue and offers it again on every
 * processor run until it succeeds. The request can fail after the API has
 * already published the event, for example when the response is slower than
 * the timeout, so every retry can become another CloudFront invalidation.
 * Each invalidation is attempted at most MAX_ATTEMPTS times, then dropped and
 * logged.
 *
 * Settings are the purge_purger_http settings for the instance, so the HTTP
 * Bundled Purger configuration form still applies.
 *
 * @PurgePurger(
 *   id = "marina_cf_cachetags",
 *   label = @Translation("Marina CloudFront purger"),
 *   configform = "\Drupal\purge_purger_http\Form\HttpBundledPurgerForm",
 *   cooldown_time = 0.0,
 *   description = @Translation("Sends cache tag invalidations to the Marina cache invalidation API in a single HTTP request, and gives up on an invalidation after 3 failed attempts."),
 *   multi_instance = FALSE,
 *   types = {},
 * )
 */
class MarinaCfCachetagsPurger extends HttpBundledPurger {

  /**
   * The number of requests an invalidation is included in before it is dropped.
   */
  public const MAX_ATTEMPTS = 3;

  /**
   * The invalidation property that counts attempts.
   */
  protected const ATTEMPTS_PROPERTY = 'attempts';

  /**
   * {@inheritdoc}
   */
  public function invalidate(array $invalidations) {
    $pending = [];
    $dropped = [];
    foreach ($invalidations as $invalidation) {
      $attempts = (int) $invalidation->getProperty(self::ATTEMPTS_PROPERTY);
      if ($attempts >= self::MAX_ATTEMPTS) {
        // SUCCEEDED is the only state that removes an item from the queue.
        $invalidation->setState(InvalidationInterface::SUCCEEDED);
        $dropped[] = (string) $invalidation->getExpression();
        continue;
      }
      $invalidation->setProperty(self::ATTEMPTS_PROPERTY, $attempts + 1);
      $pending[] = $invalidation;
    }

    if ($dropped) {
      $this->logger()->error('dropped @count invalidations after @max failed attempts: @expressions', [
        '@count' => \count($dropped),
        '@max' => self::MAX_ATTEMPTS,
        '@expressions' => \implode(', ', $dropped),
      ]);
    }
    if ($pending) {
      parent::invalidate($pending);
    }
  }

}
