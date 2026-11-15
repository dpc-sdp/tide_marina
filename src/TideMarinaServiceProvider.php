<?php

namespace Drupal\tide_marina;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Monolog\Handler\StreamHandler;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the Monolog output handlers when Monolog is enabled.
 *
 * The handlers depend on services provided by the Monolog module. Registering
 * them unconditionally would break the container rebuild on sites where
 * Monolog is not yet enabled, before tide_marina_update_10001() can enable it.
 */
class TideMarinaServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['monolog'])) {
      return;
    }

    $container->register('monolog.handler.tide_marina_stdout', StreamHandler::class)
      ->addArgument('php://stdout');
    $container->register('monolog.handler.tide_marina_stderr', StreamHandler::class)
      ->addArgument('php://stderr');
    // Drush writes to stderr so command output on stdout stays clean.
    $container->register('monolog.handler.tide_marina_output', 'Drupal\monolog\Logger\Handler\ConditionalHandler')
      ->setArguments([
        new Reference('monolog.handler.tide_marina_stderr'),
        new Reference('monolog.handler.tide_marina_stdout'),
        new Reference('monolog.condition_resolver.cli'),
        '%tide_marina.log_level%',
      ]);
  }

}
