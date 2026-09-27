# Tide Marina

Drupal integrations and dependency management for the Tide Marina hosting platform.

## Development

Develop and validate this module on the host. It requires PHP 8.3, Composer, and the PHP extensions declared in `composer.json`: `gd`, `igbinary`, `redis`, `pdo_mysql`, `zip`, and `zlib`.

### Getting started

Install the module dependencies and the pinned development tools:

```sh
composer install --prefer-dist --no-progress --no-interaction
composer --working-dir=tools install --prefer-dist --no-progress --no-interaction
```

### Validating a build

Run the same Composer validation and dependency installation performed by CI:

```sh
composer validate
composer install --prefer-dist --no-progress --no-interaction
```

Run coding-standard checks with:

```sh
tools/vendor/bin/phpcs --standard=phpcs.xml.dist
```

## Features

- Ensures dependencies required for the Tide Marina hosting platform are installed.
- Enforces runtime container requirements (PHP 8.3 and required PHP extensions).
- Provides the optional `marina_cf_cachetags` module. It sends cacheable
  responses' Drupal cache tags in the `x-amz-meta-cache-tag` header and
  invalidates those tags through the local CloudFront SigV4 sidecar.
- Automatically handles migration tasks from legacy Section.io purgers to the Marina CloudFront integration upon module installation (tide_marina_install()).

### CloudFront cache tags

Enable the submodule with:

```sh
drush en marina_cf_cachetags
```

On install it registers Purge's HTTP bundled purger (instance
`marina_cf_cachetags`), enables the `coretags` queuer and the `lateruntime`
processor, and empties the purge queue so nothing queued for a previous purger
lingers.

**Response header.** Cacheable responses carry `x-amz-meta-cache-tag`, a
comma-separated list of the response's Drupal cache tags hashed with xxHash3
and truncated to 6 hex characters (`node:203` → `#` + first 6 chars of
`hash('xxh3', 'node:203')`). Tags matching the `purge_queuer_coretags`
blacklist are dropped, entity tags are listed first, and the list is capped at
the 50 tags CloudFront stores per object. Hashing keeps the header far below
CloudFront's 1,783-character limit.

**Invalidation.** The purger sends a `POST` to
`http://localhost:8083/prod/cache-invalidation/{project}/{environment}` (the
platform's SigV4 sidecar) with a JSON body of the same hashes, each prefixed
with `tag:`:

```json
{"tagsCsv":"tag:b9917e,tag:4c1d2a"}
```

The cache invalidation API rewrites `tag:<hash>` to `#<hash>`, which is
CloudFront's tag-invalidation syntax. Both sides must use the same hash, so
they share the `marina_cf_cachetags.cache_tags_hash` service. Override
`marina_cf_cachetags.cache_tag_filter` or
`marina_cf_cachetags.cache_tag_prioritizer` in a site's `services.yml` to
change which tags are kept and in what order.

**Site configuration.** Override the purger's `path` with the deployment's
project and environment names (the placeholders are not substituted
anywhere else), e.g. in `settings.php`:

```php
$config['purge_purger_http.settings.marina_cf_cachetags']['path'] =
  sprintf('/prod/cache-invalidation/%s/%s', 'my-project', getenv('MARINA_ENVIRONMENT'));
```

**Platform requirements.** The CloudFront distribution must declare
`CacheTagConfig` with `HeaderName: x-amz-meta-cache-tag`; distributions
without it ignore the header and every tag invalidation is a no-op. Objects
cached before `CacheTagConfig` is enabled carry no tags — issue one `/*`
invalidation after enabling it.

To invalidate a tag by hand, hash it first:

```sh
aws cloudfront create-invalidation --distribution-id <id> \
  --paths "#$(php -r 'echo substr(hash("xxh3", "node:203"), 0, 6);')"
```

## Patches

### Redis

This module handles patching of the [Redis](https://www.drupal.org/project/redis) module with a few key features

1. Adds support for RedisCluster client.
1. Uses the current [RedisCluster upstream patch](https://www.drupal.org/project/redis/issues/2900947).
1. Preserves the configured TLS context during RedisCluster node discovery.
