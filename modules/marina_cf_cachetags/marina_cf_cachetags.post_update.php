<?php

/**
 * @file
 * Post update functions for the Marina CloudFront cache tags integration.
 */

/**
 * Cap purge attempts and allow the invalidation API 5 seconds to respond.
 */
function marina_cf_cachetags_post_update_cap_purge_retries() {
  \Drupal::moduleHandler()->loadInclude('marina_cf_cachetags', 'install');
  marina_cf_cachetags_configure_purge();

  $settings = \Drupal::configFactory()
    ->getEditable('purge_purger_http.settings.marina_cf_cachetags');
  if (!$settings->isNew() && (float) $settings->get('timeout') < 5.0) {
    $settings->set('timeout', 5.0)->save();
  }
}
