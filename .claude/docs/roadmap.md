# Roadmap

| # | Milestone | Done when |
|---|---|---|
| M0 | **Setup.** Clear 1.x `src/` on `2.x`. New `composer.json` (MIT, PHP 8.5, PSR interface packages, dev tools). `.phpcs.xml` (modeled on x3p0-breadcrumbs, no WordPress rules, 8.5). PHPStan (max). PHPUnit. CI. A local PHP 8.5 toolchain. | The empty project passes lint, analysis, and tests on 8.5 |
| M1 | **Core.** Copy and adapt the x3p0-framework container and application, x3p0-event, and x3p0-class-registry/attributes, with their tests. Add `Paths`, `Env`, config objects, errors, log, and clock. Add a cached container resolution plan (D-044) and extension discovery (D-041). | The copied tests pass under `Blush\` on 8.5 |
| M2 | **HTTP + Console.** PSR-7/17/15 implementations, `Kernel`, `Emitter`, the console framework, and `serve`. | "Hello" is served in the browser, through `Kernel::handle()` in tests, and via `bin/blush` |
| M3 | **Routing.** Compiler, matcher, URL generator, attribute discovery, redirects, and `routes:list`. | 404, 405, and redirects are tested; the route cache works |
| M4 | **Content.** Source, parsers (behind interfaces), schemas, indexer, repository, query, content types from PHP and data (D-042), the built-in `author` type (D-043), `content:*` commands, media, and the PHPBench baseline. | jtcom's ~1,200 entries index and lint cleanly; query benchmarks are recorded |
| M5 | **Views + theming.** Engine, hierarchy, components, `Head`, tokens, theme loader, default theme, built-in controllers, feeds, and sitemaps. | The default theme renders every route type |
| M6 | **Caching + publishing.** Cache layers, content version, `PageCache`, webhook, and `publish`. | One-command publish and cache clear |
| M7 | **Static export.** `build` plus incremental mode. | jtcom exports and serves from static files |
| M8 | **Port jtcom.** jtcom theme, config, `user/` layout, a URL-parity crawl against the live site, and a redirect map. | Every old URL returns 200 or 301; deployed |
| M9 | **Admin stage 2:** operations dashboard. | Publish, clear, reindex, and export from a browser |
| M10 | **Admin stage 3:** editor and media library. | Create and edit entries in a browser |
| Later | `SqliteIndex` + search; in-house YAML and Markdown parsers; theme distribution; custom template engine | — |

---

## M0 checklist (setup)

Work on the `2.x` branch. **Never commit** (the user reviews and commits).

1. **PHP 8.5 toolchain** (D-047). Herd's global PHP is 8.5.10, so plain `php`
   and `composer` work. Verify `php -v` before starting.
   **Don't switch jtcom's DDEV to 8.5: PHP 8.5 breaks the current (1.x)
   jtcom site.** jtcom stays on its current PHP until the M8 port. The framework is a
   library with no site of its own, so M0 and M1 only need the PHP 8.5 **CLI**
   plus Composer. A browser-facing dev site (on DDEV) comes in M2; see
   `open-questions.md`.
2. **Clear out 1.x.** Caution: jtcom's `vendor/blush-dev/framework` is a
   symlink to this repo's working tree, so jtcom always runs whatever branch
   is checked out here. Clearing 1.x on `2.x` breaks the local jtcom site until
   M8. If the user still needs jtcom running locally, set up a 1.x checkout
   first (e.g. `git worktree add ../blush-framework-1x master`), point jtcom's
   Composer path repository and `.ddev/docker-compose.blush.yaml` mount at it,
   and confirm with the user before deleting anything. Then delete `src/`, `composer.lock`, and `vendor/`, and rewrite
   `readme.md`. Keep `.claude/`, `AGENTS.md`, `CLAUDE.md`, and
   `.editorconfig`.
3. **License** (D-014): replace `license.md` with an MIT `LICENSE.md`
   (Copyright (c) 2026 Justin Tadlock).
4. **`composer.json`:**
   - Name `blush-dev/framework`. Description without "Foundation" (D-008).
   - License `MIT`, `"php": ">=8.5"`, `minimum-stability: stable`.
   - PSR-4: `Blush\` → `src/`, `Blush\Tests\` → `tests/`.
   - `require`: only what M0 needs. Add PSR interface packages as the
     subsystems that implement them land (D-006).
   - `require-dev`: `phpunit/phpunit` ^12, `phpstan/phpstan`,
     `squizlabs/php_codesniffer`, `phpcompatibility/php-compatibility`, and
     `dealerdirect/phpcodesniffer-composer-installer`.
   - Scripts: `lint`, `fix`, `analyse`, `test`, and `check` (all three).
5. **`.phpcs.xml`:** based on x3p0-breadcrumbs' ruleset (see `paths.md`)
   *without* the WordPress rules. Keep PSR-12 with its exclusions, tabs,
   `RequireStrictTypes`, and short arrays. Use `PHPCompatibility` with
   `testVersion` `8.5-`. Remove `/.phpcs.xml` and `/phpcs.xml` from
   `.gitignore`.
6. **Verify 8.5 syntax support** (see `open-questions.md`): write a scratch
   file using `|>`, `clone($x, [...])`, property hooks, and asymmetric
   visibility, then run PHPCS, PHPStan, and PHPUnit over it. Record the
   outcome in `decisions.md`, and put any restrictions in the
   `blush-code-style-php` skill.
7. **`phpstan.neon`:** level `max`, with paths `src` and `tests`.
8. **`phpunit.xml`** plus one smoke test under `tests/`.
9. **`.gitattributes`:** export-ignore dev files (`.claude`, `tests`, and the
   tool configs).
10. **CI:** `.github/workflows/ci.yml` on PHP 8.5 running
   `composer validate`, lint, analyse, and test.
11. **Docs:** fill in the `AGENTS.md` "Commands" section. Update the "Status"
    line to M1 when done.

**Done when:** `composer check` passes on PHP 8.5 locally (and in CI once
pushed).

