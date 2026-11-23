<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\purge\Plugin\Purge\Purger\Exception\CapacityException;
use Drupal\purge\Plugin\Purge\Purger\Exception\DiagnosticsException;
use Drupal\purge\Plugin\Purge\Purger\Exception\LockException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Processes the purge queue at the end of a request, at most once per interval.
 *
 * Purge's lateruntime processor sends the queue at the end of every request,
 * so each edit becomes its own invalidation and tags such as node_list are
 * repeated in every one. This sends at most one batch per interval across all
 * requests: the first request after a quiet period sends straight away, and
 * anything queued during the interval goes out together, de-duplicated, with
 * the next request after it. Sites with no further traffic are picked up by
 * the cron processor on the next drush cron.
 */
class ThrottledLateRuntimeProcessor implements EventSubscriberInterface {

  /**
   * The state key holding when a batch was last sent.
   */
  public const STATE_KEY = 'marina_cf_cachetags.last_processed';

  /**
   * The lock that stops concurrent requests sending at the same time.
   */
  public const LOCK_NAME = 'marina_cf_cachetags.lateruntime';

  /**
   * The processor plugin ID.
   */
  public const PLUGIN_ID = 'marina_cf_cachetags_lateruntime';

  /**
   * The default minimum number of seconds between batches.
   */
  public const DEFAULT_INTERVAL = 60;

  /**
   * The minimum number of seconds between batches.
   */
  protected int $interval;

  /**
   * Constructs the subscriber.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container, used to load Purge services only when needed.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Site\Settings $settings
   *   The site settings. `marina_cf_cachetags_processing_interval` overrides
   *   the minimum number of seconds between batches.
   */
  public function __construct(
    protected ContainerInterface $container,
    protected StateInterface $state,
    protected LockBackendInterface $lock,
    protected TimeInterface $time,
    Settings $settings,
  ) {
    $this->interval = (int) $settings->get('marina_cf_cachetags_processing_interval', self::DEFAULT_INTERVAL);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before services are destructed, as Purge's lateruntime processor
    // does, so processed items are removed from the queue.
    // @see \Drupal\purge_processor_lateruntime\EventSubscriber\LateRuntimeProcessor
    return [KernelEvents::TERMINATE => [['onKernelTerminate', 1000]]];
  }

  /**
   * Sends one batch of queued invalidations if the interval has passed.
   */
  public function onKernelTerminate(): void {
    $now = (int) $this->time->getCurrentTime();
    if ($now - (int) $this->state->get(self::STATE_KEY, 0) < $this->interval) {
      return;
    }

    $processor = $this->container->get('purge.processors')->get(self::PLUGIN_ID);
    if (!$processor) {
      return;
    }
    if (!$this->lock->acquire(self::LOCK_NAME, (float) \max(30, $this->interval))) {
      return;
    }

    try {
      $queue = $this->container->get('purge.queue');
      $claims = $queue->claim();
      if (!$claims) {
        return;
      }
      // Only start the interval once something has been sent, so the first
      // edit after a quiet period is not delayed.
      $this->state->set(self::STATE_KEY, $now);
      try {
        $this->container->get('purge.purgers')->invalidate($processor, $claims);
      }
      catch (DiagnosticsException | CapacityException | LockException) {
        // The items are released back to the queue for the next batch.
      }
      finally {
        $queue->handleResults($claims);
      }
    }
    finally {
      $this->lock->release(self::LOCK_NAME);
    }
  }

}
