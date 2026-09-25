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
- **Keep jtcom (1.x) running locally during the rewrite?** If yes, set up a 1.x
  worktree before M0 clears `src/` (see the roadmap, M0 step 2).

## Tooling
- **PHPCS and 8.5 syntax:** PHPCS/PHPCompatibility may not tokenize the pipe
  operator (`|>`) or `clone()` with properties yet. Verify in M0. If they
  can't, either hold off on those features in affected files or add a
  PHP-CS-Fixer check alongside.

## Later
- **Product name** (D-038): the author will decide.
- **Repo strategy after 2.x stabilizes:** one package, or a split monorepo?
- **Multilingual file convention** (D-036): decided when the feature is built.
- **Theming:** see the open questions in `theming.md`.
