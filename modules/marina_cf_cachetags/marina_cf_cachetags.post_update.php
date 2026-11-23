<?php

/**
 * @file
 * Post update functions for the Marina CloudFront cache tags integration.
 */

/**
 * Process invalidations on cron instead of at the end of every request.
 */
function marina_cf_cachetags_post_update_process_on_cron() {
  if (!\Drupal::moduleHandler()->moduleExists('purge_processor_cron')) {
    \Drupal::service('module_installer')->install(['purge_processor_cron']);
  }

  \Drupal::moduleHandler()->loadInclude('marina_cf_cachetags', 'install');
  marina_cf_cachetags_configure_purge();
}
