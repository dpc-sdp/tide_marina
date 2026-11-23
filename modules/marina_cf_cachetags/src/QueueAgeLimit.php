<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\purge\Plugin\Purge\Queue\DatabaseQueue;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface;

/**
 * Removes purge queue items that have been waiting longer than a maximum age.
 *
 * Purge puts back every item that does not succeed, including items that
 * never reach a purger: invalidation types no purger supports, and anything
 * queued while Purge's diagnostics are failing. Those are not covered by the
 * purger's attempt cap, so without this they would stay in the queue forever
 * and, because the oldest items are claimed first, could hold up everything
 * queued after them. Once an item is this old it has no value anyway.
 *
 * Only Purge's database queue is supported. Items currently claimed by a
 * processor are left alone.
 */
final class QueueAgeLimit {

  /**
   * The default maximum age, in seconds.
   */
  public const DEFAULT_MAX_AGE = 3600;

  /**
   * Constructs the age limit.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface $queue
   *   The purge queue service.
   * @param \Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface $stats
   *   The purge queue statistics tracker.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param int $maxAge
   *   The maximum age, in seconds. Zero or less disables the limit.
   */
  public function __construct(
    protected Connection $database,
    protected QueueServiceInterface $queue,
    protected StatsTrackerInterface $stats,
    protected TimeInterface $time,
    protected int $maxAge = self::DEFAULT_MAX_AGE,
  ) {}

  /**
   * Removes queue items older than the maximum age.
   *
   * @return int
   *   The number of items removed.
   */
  public function removeStaleItems(): int {
    if ($this->maxAge <= 0 || $this->queue->getPluginsEnabled() !== ['database']) {
      return 0;
    }

    $now = (int) $this->time->getCurrentTime();
    $removed = (int) $this->database->delete(DatabaseQueue::TABLE_NAME)
      ->condition('created', $now - $this->maxAge, '<')
      // Unclaimed items have an expiry of 0; skip leases still being worked.
      ->condition('expire', $now, '<')
      ->execute();

    if ($removed > 0) {
      $this->stats->numberOfItems()->set($this->queue->numberOfItems());
    }
    return $removed;
  }

}
