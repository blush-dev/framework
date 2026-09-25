# Open questions

Move each item to `decisions.md` once it's answered.

## Needs the author's call

- **Dev site from M2 onward.** Proposal: build the site skeleton
  (`blush-dev/site`, D-045) as its own repo next to this one, with its own
  DDEV at 8.5 (D-047), requiring the framework via a Composer path repository. It
  doubles as the browser playground. Automated tests keep using
  `tests/Fixtures/site`.
- **Empty-state page.** Proposal: a fresh site with no content renders a
  built-in welcome page from the framework default theme, instead of a 404.

## M2
- **Container plan warm-up** (D-052): `Bootstrap::compile()` plans what booting
  resolves. Request-time services (controllers, middleware) also need plans.
  Options: have the compile command dispatch a few representative requests
  through the kernel, or plan every class tagged or bound by providers.
- **Error handler timing:** should entry points register a minimal handler
  before config loads, so a broken config file renders cleanly?
- **Extension requirements** (D-058): what constraint syntax to support for
  `extension:check`, likely a Composer semver subset (`^`, `~`, comparison
  operators, `||`).

## Tooling
- **PHPCS property-hook support** (D-048): when PHPCS ships it, remove the
  `phpcs:disable` comments around hooked properties and update the style
  skill.
- **PHPCompatibility 10 stable** (D-049): drop the `@alpha` flag once it's
  released.

## Later
- **Product name** (D-038): the author will decide.
- **Repo strategy after 2.x stabilizes:** one package, or a split monorepo?
- **Multilingual file convention** (D-036): decided when the feature is built.
- **Theming:** see the open questions in `theming.md`.
