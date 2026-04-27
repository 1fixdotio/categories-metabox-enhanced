# Contributing

## Local development environment

```sh
npm install
composer install
npm run env:start
```

`npm run env:start` boots two WordPress sites via `@wordpress/env`: `dev` (port 8888) and `tests` (port 8889). The `tests` site is what PHPUnit talks to.

## Running tests

PHPUnit runs inside the `tests-cli` container so it shares the test database that wp-env provisions:

```sh
npm run test:phpunit
```

To target a single test file:

```sh
npm run test:phpunit -- tests/phpunit/test-plugin-loaded.php
```

## Building block editor assets

```sh
npm run build      # one-shot production build
npm run start      # watch mode for development
```

The committed `admin/js/block-editor/build/` directory is verified in CI — re-run `npm run build` and commit the result whenever you change anything under `admin/js/block-editor/src/`.

## Branching

Open pull requests against `develop`. Releases merge `develop` into `master` and tag from there.
