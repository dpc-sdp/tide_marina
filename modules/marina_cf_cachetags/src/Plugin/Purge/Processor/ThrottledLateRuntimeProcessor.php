<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags\Plugin\Purge\Processor;

use Drupal\purge\Plugin\Purge\Processor\ProcessorBase;
use Drupal\purge\Plugin\Purge\Processor\ProcessorInterface;

/**
 * Processes the queue at the end of a request, at most once per interval.
 *
 * The work is done by ThrottledLateRuntimeProcessor; this plugin only lets
 * Purge enable and disable it.
 *
 * @PurgeProcessor(
 *   id = "marina_cf_cachetags_lateruntime",
 *   label = @Translation("Throttled late runtime processor"),
 *   description = @Translation("Processes the queue at the end of a request, at most once per interval, so a burst of edits is sent as one invalidation."),
 *   enable_by_default = FALSE,
 *   configform = "",
 * )
 */
class ThrottledLateRuntimeProcessor extends ProcessorBase implements ProcessorInterface {

}
