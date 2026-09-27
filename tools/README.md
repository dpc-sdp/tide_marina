# Development tools

This directory contains Composer dependencies used only for local development
and continuous integration. It is separate from the module's `composer.json`
so code-quality tools do not become production dependencies for downstream
Drupal projects.

Install the pinned tools:

```sh
composer --working-dir=tools install --no-interaction \
  --prefer-dist --no-progress
```

Run the coding-standard checks from the repository root:

```sh
tools/vendor/bin/phpcs --standard=phpcs.xml.dist
```

`composer.lock` is committed to keep the toolchain reproducible. Generated
files under `tools/vendor/` are ignored.
