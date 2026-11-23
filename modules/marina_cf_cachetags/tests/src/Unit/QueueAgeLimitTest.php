<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Delete;
use Drupal\marina_cf_cachetags\QueueAgeLimit;
use Drupal\purge\Counter\CounterInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\StatsTrackerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\QueueAgeLimit
 * @group marina_cf_cachetags
 */
class QueueAgeLimitTest extends UnitTestCase {

  /**
   * The current time.
   */
  protected const NOW = 1_000_000;

  /**
   * The conditions added to the delete query.
   *
   * @var array<int, array{string, int, string}>
   */
  protected array $conditions = [];

  /**
   * The value the number of items statistic was set to, if any.
   */
  protected ?int $statSetTo = NULL;

  /**
   * Builds the age limit with a delete query that removes $removed rows.
   */
  protected function limit(int $removed, array $queuePlugins = ['database'], int $maxAge = QueueAgeLimit::DEFAULT_MAX_AGE): QueueAgeLimit {
    $delete = $this->createMock(Delete::class);
    $delete->method('condition')->willReturnCallback(function (string $field, $value, string $operator) use ($delete): Delete {
      $this->conditions[] = [$field, $value, $operator];
      return $delete;
    });
    $delete->method('execute')->willReturn($removed);
    $database = $this->createMock(Connection::class);
    $database->method('delete')->with('purge_queue')->willReturn($delete);

    $queue = $this->createMock(QueueServiceInterface::class);
    $queue->method('getPluginsEnabled')->willReturn($queuePlugins);
    $queue->method('numberOfItems')->willReturn(7);
    $counter = $this->createMock(CounterInterface::class);
    $counter->method('set')->willReturnCallback(function ($value): void {
      $this->statSetTo = $value;
    });
    $stats = $this->createMock(StatsTrackerInterface::class);
    $stats->method('numberOfItems')->willReturn($counter);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(self::NOW);

    return new QueueAgeLimit($database, $queue, $stats, $time, $maxAge);
  }

  /**
   * @covers ::removeStaleItems
   */
  public function testRemovesUnclaimedItemsOlderThanMaxAge(): void {
    $this->assertSame(3, $this->limit(3)->removeStaleItems());
    $this->assertSame([
      ['created', self::NOW - QueueAgeLimit::DEFAULT_MAX_AGE, '<'],
      ['expire', self::NOW, '<'],
    ], $this->conditions);
    // The queue size statistic is resynced after removing items.
    $this->assertSame(7, $this->statSetTo);
  }

  /**
   * @covers ::removeStaleItems
   */
  public function testNothingRemovedLeavesTheStatisticAlone(): void {
    $this->assertSame(0, $this->limit(0)->removeStaleItems());
    $this->assertNull($this->statSetTo);
  }

  /**
   * @covers ::removeStaleItems
   */
  public function testOtherQueuesAndDisabledLimitAreSkipped(): void {
    $this->assertSame(0, $this->limit(3, ['memory'])->removeStaleItems());
    $this->assertSame(0, $this->limit(3, ['database'], 0)->removeStaleItems());
    $this->assertSame([], $this->conditions);
  }

}
