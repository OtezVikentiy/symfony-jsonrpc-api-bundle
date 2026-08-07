[English](CONTRIBUTING.md) · [Русский](CONTRIBUTING.ru.md)

# Contributing

Thanks for your interest in `otezvikentiy/json-rpc-api`. This document is the
minimum set of rules that gets a pull request through CI on the first attempt,
without a round of corrections spent purely on formalities.

## Before you start

For small fixes — a typo, an obvious bug with an obvious patch — open a pull
request straight away. For new functionality or a change in behaviour, open an
issue first describing the problem and the solution you have in mind, so that
you do not spend time on an implementation that will not fit the bundle's
architecture.

## Environment

```bash
git clone https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle.git
cd symfony-jsonrpc-api-bundle
composer install
```

Supported versions: PHP 8.2–8.5, Symfony `^6.4 || ^7.0 || ^8.0` (see
`composer.json`). CI runs the full matrix, but running the suite once on the
lowest PHP version you have is a faster way to catch the obvious problems
before pushing.

## Tests

```bash
composer test     # phpunit --no-coverage
composer lint     # php -l across src/ and tests/
composer stan     # phpstan analyse
composer cs       # php-cs-fixer, dry run
composer ci       # all of the above
```

A full run with coverage:

```bash
./vendor/bin/phpunit --coverage-text
```

CI (`.github/workflows/ci.yml`) requires:

- A green suite on PHP 8.2/8.3/8.4/8.5 against Symfony 6.4/7.x/8.x — ten
  combinations, excluding Symfony 8 below PHP 8.4, where the combination
  cannot exist because Symfony 8 requires PHP >= 8.4.1.
- A green suite on the **lowest permitted** dependency versions
  (`composer update --prefer-lowest`) — new code must not rely on an API that
  appeared later than `composer.json` claims to support.
- **Line coverage at or above 90%.** New code without a covering test will
  most likely push that number down; add the test in the same pull request as
  the feature.
- PHPStan level 9 and PHP-CS-Fixer clean.
- `composer validate --strict` and `composer audit` without errors.

Any change to observable behaviour — a new error code, a different message
format, a different config default — needs a test that will notice the
regression. Prefer `tests/Security/` for security-relevant changes, or the
permanent JSON-RPC 2.0 conformance suite when the behaviour is one the
specification describes.

## Code style

- `declare(strict_types=1)` in every file under `src/`.
- PHP 8 attributes, not docblock annotations — `doctrine/annotations` is
  deliberately not used in this project.
- `final` on classes unless there is a stated reason to leave an extension
  point open through inheritance.
- Comments explain **why**, not **what**. The code should read on its own; a
  comment belongs where a non-obvious decision would otherwise invite an
  "improving" pull request that undoes a fix.
- Code and comments in `src/`, `tests/` and `config/` are in English.
  Documentation is bilingual: English is the primary version, Russian carries
  the `.ru.md` suffix. `CHANGELOG.md` is currently Russian only.

## Documentation

If a pull request changes observable behaviour — a new config key, a different
default, a different error format, a BC break — update the corresponding file
in `docs/` and add an entry to `CHANGELOG.md` following
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). A pull request that
changes behaviour without updating the documentation will not be accepted:
documentation that disagrees with the code is worse than no documentation,
because it misleads silently.

For BC-breaking changes destined for a major release, add an item to
`docs/upgrade-X.0.md` (create the file following `docs/upgrade-5.0.en.md` if
the release does not exist yet): what it was, what it is, what breaks for
consumers, what to do about it.

## Pull requests

- One pull request, one logical change. Do not mix refactoring with a change
  in behaviour — separate changes are easier to review and easier to
  `git revert` when something turns out to be wrong.
- Describe in the pull request what changed and *why* (not only what), how you
  verified it, and that the documentation is updated — or why it did not need
  to be.
- Write commit messages that give the reason for the change, not just the fact
  of it. "fix bug" says nothing; a message explaining which invariant was
  violated and why says everything. The project's commit history is the
  reference.

## Reporting a vulnerability

Do not open a public issue for a security vulnerability — see
[SECURITY.md](./SECURITY.md).

## Code of conduct

Participation in this project is governed by the
[Code of Conduct](./CODE_OF_CONDUCT.md).
