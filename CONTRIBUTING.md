# Contributing

## Local development environment

The plugin is shipped with a `@wordpress/env` config so you can spin up a real WordPress site for manual smoke testing and (in PR C) Playwright e2e:

```sh
npm install
npm run env:start   # http://localhost:8888
```

If you prefer DDEV, Local, or another local stack, you can mount this directory as a plugin into your existing WordPress install — nothing in the plugin assumes wp-env at runtime.

## Running PHPUnit

PHPUnit runs as a regular PHP process against any MySQL/MariaDB the script can reach. The `bin/install-wp-tests.sh` helper downloads the WordPress test suite from `develop.svn.wordpress.org` into `/tmp/wordpress-tests-lib` and creates a fresh test database.

The script signature is:

```text
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
```

Pick the credentials that match your local DB. A few common cases:

```sh
# Local MySQL/MariaDB with root/root (Homebrew default, common dev setup):
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest

# DDEV (run from inside the web container; DB host inside DDEV is `db`):
ddev exec bash bin/install-wp-tests.sh wordpress_test db db db latest

# Ad-hoc Docker MySQL with MYSQL_ALLOW_EMPTY_PASSWORD=yes (what CI uses):
bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
```

Then run the suite:

```sh
composer install       # one-time
composer test          # or: vendor/bin/phpunit
npm run test:phpunit   # alias
```

Re-running `bin/install-wp-tests.sh` is idempotent — it skips re-downloading the suite if `/tmp/wordpress-tests-lib` already exists, but does drop and recreate the test database to keep runs deterministic.

## Building block editor assets

```sh
npm run build      # one-shot production build
npm run start      # watch mode for development
```

The committed `admin/js/block-editor/build/` directory is verified in CI — re-run `npm run build` and commit the result whenever you change anything under `admin/js/block-editor/src/`.

## Branching

Open pull requests against `develop`. Releases merge `develop` into `master` and tag from there.
