# Contributing

## Local development environment

The plugin is shipped with a `@wordpress/env` config so you can spin up a real WordPress site for manual smoke testing and (in PR C) Playwright e2e:

```sh
npm install
npm run env:start   # http://localhost:8888
```

If you prefer DDEV, Local, or another local stack, you can mount this directory as a plugin into your existing WordPress install — nothing in the plugin assumes wp-env at runtime.

## Running PHPUnit

PHPUnit runs as a regular PHP process against a MySQL the script can write to. The `bin/install-wp-tests.sh` helper downloads the WordPress test suite from `develop.svn.wordpress.org` into `/tmp/wordpress-tests-lib` and creates a fresh test database.

One-time setup:

```sh
composer install
bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
```

Then:

```sh
composer test          # or: vendor/bin/phpunit
npm run test:phpunit   # alias
```

Re-running `bin/install-wp-tests.sh` is idempotent — it skips re-downloading the suite if `/tmp/wordpress-tests-lib` already exists, but does drop and recreate the test database to keep runs deterministic.

Inside DDEV the same script works — pass DDEV's MySQL credentials (`db`, `db`, `db`, `db`) and run from inside `ddev exec`.

## Building block editor assets

```sh
npm run build      # one-shot production build
npm run start      # watch mode for development
```

The committed `admin/js/block-editor/build/` directory is verified in CI — re-run `npm run build` and commit the result whenever you change anything under `admin/js/block-editor/src/`.

## Branching

Open pull requests against `develop`. Releases merge `develop` into `master` and tag from there.
