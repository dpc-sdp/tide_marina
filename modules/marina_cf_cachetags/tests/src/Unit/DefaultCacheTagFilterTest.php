<?php

declare(strict_types=1);

namespace Drupal\Tests\marina_cf_cachetags\Unit;

use Drupal\marina_cf_cachetags\DefaultCacheTagFilter;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Drupal\marina_cf_cachetags\DefaultCacheTagFilter
 * @group marina_cf_cachetags
 */
class DefaultCacheTagFilterTest extends UnitTestCase {

  /**
   * @covers ::filter
   */
  public function testFilterUsesBlocklistPrefixes(): void {
    $filter = new DefaultCacheTagFilter($this->getConfigFactoryStub([
      'purge_queuer_coretags.settings' => [
        'blacklist' => ['config:filter.format', 'http_response', 'extensions', ''],
      ],
    ]));
    $this->assertSame(
      ['node:1', 'config:system.site', 'rendered'],
      $filter->filter([
        'node:1',
        'config:filter.format.rich_text',
        'http_response',
        'config:system.site',
        'extensions',
        'rendered',
      ])
    );
  }

  /**
   * @covers ::filter
   */
  public function testFilterWithoutBlocklistPassesThrough(): void {
    $tags = ['node:1', 'http_response'];
    foreach ([[], NULL, 'not-an-array'] as $blacklist) {
      $filter = new DefaultCacheTagFilter($this->getConfigFactoryStub([
        'purge_queuer_coretags.settings' => ['blacklist' => $blacklist],
      ]));
      $this->assertSame($tags, $filter->filter($tags));
    }
  }

  /**
   * @covers ::filter
   */
  public function testFilterKeepsOnlyAllowedTags(): void {
    $filter = new DefaultCacheTagFilter(
      $this->getConfigFactoryStub([
        'purge_queuer_coretags.settings' => ['blacklist' => ['config:filter.format']],
      ]),
      ['/^node:\d+$/', '/^config:/', ''],
    );
    $this->assertSame(
      ['node:1', 'config:system.site'],
      $filter->filter([
        'node:1',
        'node:1:revisions',
        'paragraph:2',
        'config:filter.format.rich_text',
        'config:system.site',
      ])
    );
  }

  /**
   * @covers ::filter
   */
  public function testDefaultAllowedTagPatterns(): void {
    $services = Yaml::parseFile(__DIR__ . '/../../../marina_cf_cachetags.services.yml');
    $filter = new DefaultCacheTagFilter(
      $this->getConfigFactoryStub(['purge_queuer_coretags.settings' => ['blacklist' => []]]),
      $services['parameters']['marina_cf_cachetags.allowed_tag_patterns'],
    );
    $allowed = [
      'node:185',
      'media:92',
      'taxonomy_term:8888',
      'menu_link_content:12',
      'paragraphs_library_item:3',
      'redirect:300',
      'node_list',
      'node_list:landing_page',
      'taxonomy_term_list',
      'media_list:embedded_video',
      'config:system.site',
      'config:shield.settings',
    ];
    $dropped = [
      'node:185:revisions',
      'paragraph:1532',
      'paragraph_list',
      'paragraph_list:hero_banner_with_cta',
      'content_moderation_state:185',
      'path_alias:1339:revisions',
      'redirect_list',
      'crop:68:revisions',
      'file_list',
      'search_api_list:node',
      'simple_sitemap:default',
      'simple_sitemap:site-8888',
      'config:views.view.admin_audit_trail',
    ];
    $this->assertSame($allowed, $filter->filter([...$allowed, ...$dropped]));
  }

}
