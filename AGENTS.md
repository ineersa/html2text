# Repository Guidelines

Project goal is to migrate python library `html2text` to PHP while preserving feature parity with the upstream behaviour and exposing a modern PHP API.

## Architecture Overview
- `HTML2Markdown::convert()` trims input, short-circuits empty strings, wires up processors (`DataContainer`, `TextProcessor`, `TrProcessor`, `AnchorProcessor`, `ListProcessor`, `TagProcessor`, `WrapProcessor`), pre-processes character entities into synthetic placeholders, then loads a `Dom\HTMLDocument`.
- `HTML2Markdown::traverseDom()` streams the DOM using native node types: emitting text runs through `TextProcessor`/`TagProcessor`, dispatching element boundaries to `TagProcessor`, and ignoring comments/PI nodes.
- `HTML2Markdown::finish()` finalises buffered output, normalises non-breaking spaces, and hands the raw text to `WrapProcessor::process()` for post-formatting.

## Runtime State & Processors
- `DataContainer` holds the evolving output buffer, whitespace flags, blockquote/list stacks, anchor metadata, and CSS style cache used by Google Docs heuristics. All processors push formatted text back through this object.
- `TextProcessor` receives every text node plus attribute values. It decodes the temporary placeholders generated during preprocessing, resolves entity and char references based on `Config`, and applies minimal normalisation before handing off to `DataContainer`.
- `TagProcessor` centralises element handling; it mirrors the Python dispatcher, coordinates emphasis markers, block spacing, table handling, and invokes per-tag helpers. It collaborates with `TrProcessor`, `AnchorProcessor`, and `ListProcessor`, and honours user-provided `Config::tagCallback` hooks.
- `AnchorProcessor` is derived from the original HTML source (via `AnchorUtilities`) and keeps track of anchor depths so that implicit closings, automatic link detection, and pending closures match the Python behaviour.
- `ListProcessor` scans the HTML ahead of time to build a list nesting table (`getListStructure()`), ensuring ordered/unordered list levels (especially Google Docs exports) are restored as the DOM walker encounters each `<li>`.
- `TrProcessor` records whether `<tr>` elements were explicitly closed, allowing `TagProcessor` to decide when to emit table separators without inventing closing tags.
- `WrapProcessor` performs the final body-width wrapping, code block preservation, optional table padding, and respects `Config` toggles (`wrapLinks`, `wrapTables`, `padTables`, etc.).
- Supporting roles live under `src/Elements/` (lightweight state carriers like `ListElement` and `AnchorElement`) and `src/Utilities/` (parsing helpers, URL helpers, hyphen-aware `StringUtilities::wordwrap()` built on `symfony/string`).

## Configuration Surface
- `Config` is a readonly value object mirroring the Python defaults; constructor arguments map to behaviour toggles (unicode/escape snob, link handling, table strategy, emphasis markers, base URL, list bullets, Google Docs mode, etc.).
- When adding new behaviour, expand `Config` first, then thread the value through the relevant processors. Keep option names aligned with the Python port to ease parity reviews.

## Testing Strategy
- `tests/Html2MarkdownTest.php` runs the functional matrix. Use the provided `createConfig()` helper to translate fixture options into `Config` named parameters.
- Fixtures in `tests/files/` come in `.html`/`.md` pairs; the test suite trims trailing whitespace and compares Markdown verbatim. Some fixtures (e.g. `url_utilities_coverage_invalid_base.html`) assert exceptions instead of Markdown output.
- Add regression coverage here whenever behaviour changes (anchor depth handling, emphasis markers, Google Docs lists, custom callbacks, etc.). Keep Arrange/Act/Assert sections explicit and prefer data providers for permutations.

## Tooling & Commands
- Minimum runtime: PHP 8.4 with `ext-dom`, `ext-libxml`, and `symfony/string`.
- `composer install` sets up dependencies; run `composer dump-autoload` after introducing new classes/namespaces under `src/` or `tests/`.
- QA scripts:
  - `composer run tests` → PHPUnit 12 (`vendor/bin/phpunit --testdox`).
  - `composer run phpstan` → static analysis using `phpstan.dist.neon`.
  - `composer run cs-fix` → Symfony style fixer (`.php-cs-fixer.dist.php`).
  - `composer run coverage` / `composer run tests-xdebug` → optional coverage or debugging workflows.

## Coding Standards & Workflow
- All PHP files must start with `declare(strict_types=1);` and follow the configured PHP-CS-Fixer (PSR-12 + Symfony rules). Indentation is 4 spaces with LF line endings per `.editorconfig`.
- Maintain class/member ordering expected by the fixer (traits, constants, properties, constructor, public methods, protected, private).
- Namespace classes under `Ineersa\PhpHtml2text\…` mirroring the directory layout; tests live under the `Tests` namespace with filenames ending in `Test.php`.
- Before raising a PR, ensure CS, tests, and PHPStan pass locally. Document non-trivial manual verification in the PR description and avoid committing generated artefacts (e.g. `vendor/`, `coverage/`).

## Review & PR Expectations
- Use short imperative commit subjects; include ticket references with `Fixes #123` / `Refs #123` when applicable.
- PRs should summarise behaviour changes, list validation commands, and include screenshots when Markdown formatting changes materially.
- Resolve PHPStan findings instead of downgrading baselines; only adjust `phpstan.dist.neon` with accompanying justification.
