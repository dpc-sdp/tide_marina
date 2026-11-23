<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit\Plugin\Purge\Purger;

use Drupal\Core\Utility\Token;
use Drupal\marina_cf_cachetags\Plugin\Purge\Purger\MarinaCfCachetagsPurger;
use Drupal\purge\Plugin\Purge\Invalidation\InvalidationInterface;
use Drupal\purge\Plugin\Purge\Invalidation\TagInvalidation;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\Plugin\Purge\Purger\MarinaCfCachetagsPurger
 * @group marina_cf_cachetags
 */
class MarinaCfCachetagsPurgerTest extends UnitTestCase {

  /**
   * The number of HTTP requests sent.
   */
  protected int $requests = 0;

  /**
   * Whether the HTTP client should fail.
   */
  protected bool $fail = TRUE;

  /**
   * The messages logged at error level.
   *
   * @var string[]
   */
  protected array $errors = [];

  /**
   * The purger under test.
   */
  protected MarinaCfCachetagsPurger $purger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')->willReturnCallback(function () {
      $this->requests++;
      if ($this->fail) {
        throw new ConnectException('timed out', new Request('POST', 'http://localhost'));
      }
      return new Response(200);
    });
    $token = $this->createMock(Token::class);
    $token->method('replace')->willReturnArgument(0);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->method('error')->willReturnCallback(function (string $message, array $context = []): void {
      $this->errors[] = strtr($message, $context);
    });

    // The constructor is final and loads settings from config, so set the
    // dependencies directly.
    $reflection = new \ReflectionClass(MarinaCfCachetagsPurger::class);
    $this->purger = $reflection->newInstanceWithoutConstructor();
    $settings = (object) [
      'request_method' => 'POST',
      'http_errors' => TRUE,
      'connect_timeout' => 1.0,
      'timeout' => 5.0,
      'body' => '{"tagsCsv":"[invalidations:separated_comma]"}',
      'body_content_type' => 'application/json',
      'headers' => [],
      'scheme' => 'http',
      'hostname' => 'localhost',
      'port' => 8083,
      'path' => '/prod/cache-invalidation/project/environment',
    ];
    foreach (['settings' => $settings, 'client' => $client, 'token' => $token] as $property => $value) {
      $reflection->getProperty($property)->setValue($this->purger, $value);
    }
    $this->purger->setLogger($logger);
  }

  /**
   * Builds a tag invalidation in the purger's context.
   */
  protected function invalidation(string $tag): TagInvalidation {
    $invalidation = new TagInvalidation([], 'tag', [
      'id' => 'tag',
      'label' => 'Tag',
      'expression_required' => TRUE,
      'expression_can_be_empty' => FALSE,
      'expression_must_be_string' => TRUE,
    ], 1, $tag);
    $invalidation->setStateContext('marina_cf_cachetags');
    return $invalidation;
  }

  /**
   * @covers ::invalidate
   */
  public function testFailingInvalidationIsDroppedAfterMaxAttempts(): void {
    $invalidation = $this->invalidation('node:1');
    for ($run = 1; $run <= MarinaCfCachetagsPurger::MAX_ATTEMPTS; $run++) {
      $this->purger->invalidate([$invalidation]);
      $this->assertSame(InvalidationInterface::FAILED, $invalidation->getState());
    }
    $this->assertSame(MarinaCfCachetagsPurger::MAX_ATTEMPTS, $this->requests);
    $this->assertSame([], $this->errors);

    // The next run drops it without sending another request.
    $this->purger->invalidate([$invalidation]);
    $this->assertSame(InvalidationInterface::SUCCEEDED, $invalidation->getState());
    $this->assertSame(MarinaCfCachetagsPurger::MAX_ATTEMPTS, $this->requests);
    $this->assertSame(['dropped 1 invalidations after 3 failed attempts: node:1'], $this->errors);
  }

  /**
   * @covers ::invalidate
   */
  public function testFreshInvalidationsAreStillSentWithExhaustedOnes(): void {
    $exhausted = $this->invalidation('node:1');
    $exhausted->setProperty('attempts', MarinaCfCachetagsPurger::MAX_ATTEMPTS);
    $fresh = $this->invalidation('node:2');
    $this->fail = FALSE;

    $this->purger->invalidate([$exhausted, $fresh]);
    $this->assertSame(1, $this->requests);
    $this->assertSame(InvalidationInterface::SUCCEEDED, $exhausted->getState());
    $this->assertSame(InvalidationInterface::SUCCEEDED, $fresh->getState());
    $this->assertSame(1, $fresh->getProperty('attempts'));
  }

}
