# Contributing

Bug reports, feature requests and pull requests are welcome. Support is provided on a best-effort basis.

## Before you start

Open an issue first for anything beyond a small fix, so the change can be discussed before work starts.

## Setup

```sh
git clone https://github.com/sayamaapps/kirby-emaki.git
cd kirby-emaki
composer install
```

To try the plugin on a site, link or copy the folder to `site/plugins/emaki/`.

## Checks

`composer ci` runs the checks that need no global tool, in the order of the CI workflow: Composer validation and normalization, dependency checks, platform requirements, Psalm, PHP Mess Detector and the tests. Run it before you open a pull request. CI runs these checks on PHP 8.2 to 8.5, plus the two global tools below.

```sh
composer ci
npm ci --prefix tests/browser && npm --prefix tests/browser run test:web   # browser tests (Chromium)
```

The two checks that need global tools are not part of `composer ci`. php-cs needs PHP 8.3 or newer, and phpcpd conflicts with PHPUnit 11 in this project:

```sh
composer global require getkirby/php-cs:0.2.1
composer global require sebastian/phpcpd:6.0.3
kirby-cs fix --dry-run --diff               # code style, Kirby rule set
phpcpd --fuzzy --exclude tests --exclude vendor .   # duplicated code
```

The global bin directory must be in `PATH`.

## Conventions

- Tabs for indentation, LF line endings. `.editorconfig` lists the rest.
- Classes live in `src/`, one class per file. The class map in `autoload.php` lists them, and `tests/AutoloadTest.php` checks it.
- Keep public names stable. A rename gets a deprecation wrapper first, see the README section Deprecated and planned for 2.0.
- Until 2.0 nothing reports a deprecation at runtime. Deprecations are `@deprecated` docblocks and README entries only: Kirby's debug mode turns a deprecation notice into an exception. Public constants keep their 1.x values, and no new option keys are read.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `style:`, `chore:`).

## Pull requests

Describe what changes and why. Add or update tests for a bug fix or a feature. Use the pull request template.
