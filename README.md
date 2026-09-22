**Cognitive-Load** - A dev-only Laravel package that statically analyses your codebase for cognitive complexity and architectural markers, then lets you gate CI/CD on a codebase getting measurably messier.

[![Build Status](https://github.com/GrafiteInc/Cognitive-Load/workflows/php-package-tests.yml/badge.svg?branch=main)](https://github.com/GrafiteInc/Cognitive-Load/actions?query=workflow%3A%22PHP+Package+Tests%22)
[![Maintainability](https://qlty.sh/gh/GrafiteInc/projects/Cognitive-Load/maintainability.svg)](https://qlty.sh/gh/GrafiteInc/projects/Cognitive-Load)
[![Packagist](https://img.shields.io/packagist/dt/grafite/cognitive-load.svg)](https://packagist.org/packages/grafite/cognitive-load)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

##### Author(s):
* [Matt Lantz](https://github.com/mlantz) ([@mattylantz](http://twitter.com/mattylantz), mattlantz at gmail dot com)

## Requirements

1. PHP 8.2+

## Compatibility and Support

| Laravel Version | Package Tag | Supported |
|-----------------|-------------|-----------|
| ^12.x - ^13.x | 1.x | yes |

## What it does

Cognitive-Load parses your app's PHP with its own AST-based engine (built on [nikic/php-parser](https://github.com/nikic/php-parser)) and scores every class on two 0-100 markers, in the spirit of [PHP Insights](https://github.com/nunomaduro/phpinsights)'s architecture/complexity overview:

* **Complexity** — derived from per-method metrics (line count, argument count, return count, variable count, property access count, if count, if-nesting depth, else count, cyclomatic complexity), inspired by the metric set in [Phauthentic/cognitive-code-analysis](https://github.com/Phauthentic/cognitive-code-analysis).
* **Architecture** — derived from class-level structure: LOC, method count, coupling (constructor dependencies + imports), and inheritance depth.

The two combine into a weighted overall score (100 = cleanest) with a human-readable band (`Good`/`Fair`/`Poor`/`Critical`).

On top of the scores, the package can snapshot a **baseline** and compare future runs against it, so a PR that makes a class meaningfully worse — either its score drops past a configurable margin, or it crosses into a worse risk tier — can fail your CI build.

### Installation

```php
composer require --dev "grafite/cognitive-load"
```

Then publish the config:
```php
php artisan vendor:publish --provider="Grafite\CognitiveLoad\CognitiveLoadServiceProvider"
```

### Commands

Analyse the codebase (defaults to `config('cognitive-load.paths')`, normally `app/`):

```php
php artisan cognitive-load:analyse
php artisan cognitive-load:analyse app/Services --format=json --detailed
```

Snapshot the current scores as the baseline for future comparisons:

```php
php artisan cognitive-load:baseline
```

Gate a build on regressions against the baseline:

```php
php artisan cognitive-load:analyse --compare-baseline --fail-on-regression
```

`cognitive-load:analyse` options: `--format=console|json`, `--detailed` (adds a per-method table per class), `--output=` (also write the full JSON report to a file), `--compare-baseline`, `--fail-on-regression` (implies `--compare-baseline`, exits non-zero only on a regression past the configured gate margins), `--baseline-path=`.

`cognitive-load:baseline` options: `--force` (skip the overwrite confirmation), `--baseline-path=`.

### Configuration

`config/cognitive-load.php` controls: which paths are scanned (`paths`) and excluded (`exclude`, `exclude_files`, `exclude_classes`, all regex), the per-metric thresholds/scales for both categories (`metrics`, `architecture_metrics`), how raw penalties decay into a 0-100 score (`category_scaling`) and combine into an overall score (`category_weights`), the risk/grade band thresholds and labels (`bands`), where the baseline file lives (`baseline.path`), and the CI gate margins (`gate.overall_margin`, `gate.class_margin`, `gate.fail_if_baseline_missing`).

### Example CI step

```yaml
- name: Cognitive Load gate
  run: php artisan cognitive-load:analyse --compare-baseline --fail-on-regression
```

Commit `cognitive-load-baseline.json` (or your configured `baseline.path`) to the repo, and update it deliberately with `php artisan cognitive-load:baseline --force` whenever a regression is intentional.

## Documentation

[https://documentation.grafite.ca/docs/utilities-cognitive-load](https://documentation.grafite.ca/docs/utilities-cognitive-load)

## License
Cognitive-Load is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Bug Reporting and Feature Requests
Please add as many details as possible regarding submission of issues and feature requests

### Disclaimer
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
