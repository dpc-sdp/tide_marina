<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\marina_cf_cachetags\EventSubscriber\ThrottledLateRuntimeProcessor;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;
use Drupal\purge\Plugin\Purge\Processor\ProcessorsServiceInterface;
use Drupal\purge\Plugin\Purge\Purger\PurgersServiceInterface;
use Drupal\purge\Plugin\Purge\Queue\QueueServiceInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\EventSubscriber\ThrottledLateRuntimeProcessor
 * @group marina_cf_cachetags
 */
class ThrottledLateRuntimeProcessorTest extends UnitTestCase {

  /**
   * The current time.
   */
  protected int $now = 1_000_000;

  /**
   * The state values.
   *
   * @var array<string, mixed>
   */
  protected array $stateValues = [];

  /**
   * Whether the lock can be acquired.
   */
  protected bool $lockAvailable = TRUE;

  /**
   * The items the queue returns from claim().
   *
   * @var \Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface[]
   */
  protected array $claims = [];

  /**
   * The number of batches passed to the purgers service.
   */
  protected int $batches = 0;

  /**
   * The number of claim() calls.
   */
  protected int $claimCalls = 0;

  /**
   * Builds the subscriber under test.
   */
  protected function subscriber(array $settings = []): ThrottledLateRuntimeProcessor {
    $processors = $this->createMock(ProcessorsServiceInterface::class);
    $processors->method('get')->with(ThrottledLateRuntimeProcessor::PLUGIN_ID)->willReturn($this->createStub(ProcessorInterface::class));
    $queue = $this->createMock(QueueServiceInterface::class);
    $queue->method('claim')->willReturnCallback(function (): array {
      $this->claimCalls++;
      return $this->claims;
    });
    $purgers = $this->createMock(PurgersServiceInterface::class);
    $purgers->method('invalidate')->willReturnCallback(function (): void {
      $this->batches++;
    });
    $container = new ContainerBuilder();
    $container->set('purge.processors', $processors);
    $container->set('purge.queue', $queue);
    $container->set('purge.purgers', $purgers);

    $state = $this->createMock(StateInterface::class);
    $state->method('get')->willReturnCallback(fn (string $key, $default = NULL) => $this->stateValues[$key] ?? $default);
    $state->method('set')->willReturnCallback(function (string $key, $value): void {
      $this->stateValues[$key] = $value;
    });
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(fn (): bool => $this->lockAvailable);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn (): int => $this->now);

    return new ThrottledLateRuntimeProcessor($container, $state, $lock, $time, new Settings($settings));
  }

  /**
   * @covers ::onKernelTerminate
   */
  public function testSendsAtMostOneBatchPerInterval(): void {
    $subscriber = $this->subscriber();
    $this->claims = [$this->createStub(InvalidationInterface::class)];

    // The first request after a quiet period sends straight away.
    $subscriber->onKernelTerminate();
    $this->assertSame(1, $this->batches);

    // Requests within the interval leave the queue alone.
    $this->now += ThrottledLateRuntimeProcessor::DEFAULT_INTERVAL - 1;
    $subscriber->onKernelTerminate();
    $this->assertSame(1, $this->batches);
    $this->assertSame(1, $this->claimCalls);

    // The first request after the interval sends the next batch.
    $this->now += 1;
    $subscriber->onKernelTerminate();
    $this->assertSame(2, $this->batches);
  }

  /**
   * @covers ::onKernelTerminate
   */
  public function testEmptyQueueDoesNotStartTheInterval(): void {
    $subscriber = $this->subscriber();
    $subscriber->onKernelTerminate();
    $this->assertSame(0, $this->batches);
    $this->assertArrayNotHasKey(ThrottledLateRuntimeProcessor::STATE_KEY, $this->stateValues);

    // An edit a moment later is still sent straight away.
    $this->now += 1;
    $this->claims = [$this->createStub(InvalidationInterface::class)];
    $subscriber->onKernelTerminate();
    $this->assertSame(1, $this->batches);
  }

  /**
   * @covers ::onKernelTerminate
   */
  public function testSkipsWhenAnotherRequestHoldsTheLock(): void {
    $this->lockAvailable = FALSE;
    $this->claims = [$this->createStub(InvalidationInterface::class)];
    $this->subscriber()->onKernelTerminate();
    $this->assertSame(0, $this->claimCalls);
  }

  /**
   * @covers ::__construct
   * @covers ::onKernelTerminate
   */
  public function testIntervalCanBeOverridden(): void {
    $subscriber = $this->subscriber(['marina_cf_cachetags_processing_interval' => 10]);
    $this->claims = [$this->createStub(InvalidationInterface::class)];
    $subscriber->onKernelTerminate();
    $this->now += 10;
    $subscriber->onKernelTerminate();
    $this->assertSame(2, $this->batches);
  }

}
