# Open questions

Move each item to `decisions.md` once it's answered.

## Needs the author's call

- **Empty-state page.** Proposal: a fresh site with no content renders a
  built-in welcome page from the framework default theme, instead of a 404.
  (M2's `WelcomeHandler` is only a placeholder until the router exists.)
- **Skeleton license** (D-070): confirm MIT for `blush-dev/blush` `2.x`.

## Later milestones
- **Subdirectory installs** (D-071): a site at `example.com/site/` needs a
  base path for routing and URL generation. Derive it from `AppConfig::$url`?
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
