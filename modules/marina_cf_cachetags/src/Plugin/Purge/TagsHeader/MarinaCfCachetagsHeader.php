<?php

declare(strict_types=1);

namespace Drupal\marina_cf_cachetags\Plugin\Purge\TagsHeader;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\marina_cf_cachetags\CacheTagFilterInterface;
use Drupal\marina_cf_cachetags\CacheTagPrioritizerInterface;
use Drupal\marina_cf_cachetags\CacheTagsHashInterface;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderBase;
use Drupal\purge\Plugin\Purge\TagsHeader\TagsHeaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exports hashed cache tags in the header CloudFront indexes for invalidation.
 *
 * The distribution must declare `CacheTagConfig.HeaderName` =
 * `x-amz-meta-cache-tag`; without it CloudFront ignores the header entirely.
 *
 * @PurgeTagsHeader(
 *   id = "marina_cf_cachetags",
 *   header_name = "x-amz-meta-cache-tag",
 * )
 *
 * @see https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/invalidation-by-tags.html
 */
final class MarinaCfCachetagsHeader extends TagsHeaderBase implements TagsHeaderInterface, ContainerFactoryPluginInterface {

  /**
   * CloudFront stores at most this many tags per cached object.
   */
  public const MAX_TAGS = 50;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected CacheTagsHashInterface $cacheTagsHash,
    protected CacheTagFilterInterface $cacheTagFilter,
    protected CacheTagPrioritizerInterface $cacheTagPrioritizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('marina_cf_cachetags.cache_tags_hash'),
      $container->get('marina_cf_cachetags.cache_tag_filter'),
      $container->get('marina_cf_cachetags.cache_tag_prioritizer'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * CloudFront requires a comma-separated value and rejects tags containing
   * spaces, so the base class' space-separated output can never be matched by
   * a `#tag` invalidation. Tags are filtered, ordered so entity tags come
   * first, hashed, de-duplicated and capped at the 50 CloudFront keeps.
   */
  public function getValue(array $tags): string {
    $tags = \array_values(\array_unique(\array_filter($tags, static fn ($tag): bool => \is_string($tag) && $tag !== '')));
    $tags = $this->cacheTagFilter->filter($tags);
    $tags = $this->cacheTagPrioritizer->prioritize($tags);
    $hashes = \array_values(\array_unique($this->cacheTagsHash->hashTags($tags)));

    return \implode(',', \array_slice($hashes, 0, self::MAX_TAGS));
  }

}
