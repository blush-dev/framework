# Decision log

Append new decisions at the bottom with the next number. To change a past
decision, add a new entry that supersedes it and mark the old one
`Superseded by D-xxx`.

---

### D-001: Full rewrite on a `2.x` branch
- **Date:** 2026-09-25
- **Decision:** Blush 2 is a from-scratch rewrite on the `2.x` branch of
  `blush-framework`. 1.x code is not a reference; only flat-file CMS concepts
  carry over.
- **Why:** The 1.x architecture (static proxies, global helpers, homemade
  container) is too entangled to refactor incrementally.

### D-002: PHP 8.5 minimum
- **Date:** 2026-09-25
- **Decision:** Require PHP `>=8.5`. Use 8.4/8.5 features freely (property
  hooks, asymmetric visibility, lazy objects, `clone()` with properties, pipe
  operator, `Uri\` extension, `#[\NoDiscard]`, `array_first()`/`array_last()`,
  and so on).

### D-003: Flat-file by default, not limited to it
- **Date:** 2026-09-25
- **Decision:** Flat files are the default source of truth. All storage goes
  through interfaces (`ContentSource`, `ContentIndex`, `ContentRepository`,
  `ContentWriter`), so databases or other backends can plug in.

### D-004: Blush is a product; no stability guarantees yet
- **Date:** 2026-09-25
- **Decision:** Blush is intended for other users eventually. API stability and
  backward compatibility are not concerns during 2.x development.

### D-005: Custom, in-house HTTP layer
- **Date:** 2026-09-25
- **Decision:** Build our own request, response, URI, middleware, and kernel.
  Do not use Symfony HttpFoundation or third-party PSR-7 implementations. We
  implement the PSR interfaces (see D-006) ourselves.

### D-006: Dependency policy: in-house first, PSR interfaces allowed
- **Date:** 2026-09-25
- **Decision:** Prefer in-house code for everything. PSR interface packages are
  allowed (`psr/container`, `psr/event-dispatcher`, `psr/http-message`,
  `psr/http-factory`, `psr/http-server-handler`, `psr/http-server-middleware`,
  `psr/log`, `psr/simple-cache`, `psr/clock`). Any temporary third-party
  runtime library (for example Markdown or YAML parsing) must sit behind a
  Blush interface so it can be replaced by in-house code later. Long-term
  goal: control all runtime PHP code.
- **Dev-only tools** (PHPCS, PHPStan, PHPUnit, var-dumper) are fine.

### D-007: Copy the author's x3p0 packages into Blush
- **Date:** 2026-09-25
- **Decision:** Do not depend on x3p0-* Composer packages. Copy them into the
  Blush namespace more or less wholesale and adapt them. At minimum:
  - `x3p0-framework`: container, application, service provider, contracts.
    Drop the WordPress multi-phase `begin()` lifecycle.
  - `x3p0-event`: the whole event system, **including `BroadcastableEvent`**.
    Drop only the WordPress `BroadcastsToHooks` trait.
  - Borrow any other x3p0 repo as needed (`x3p0-class-registry`,
    `x3p0-attributes`, `x3p0-asset` concepts, and so on).
- **No origin records needed.** The x3p0 repos were themselves spun out of
  ideas for Blush, and they are the author's own projects.

### D-008: Naming: avoid Laravel-isms; use `Core`
- **Date:** 2026-09-25
- **Decision:** Avoid Laravel-specific terms in folders, classes, and commands
  (no "Foundation", "Facade", `make:*`, and so on). The application, container
  wiring, and bootstrapping area is `Core`.

### D-009: Plain PHP templates to start
- **Date:** 2026-09-25
- **Decision:** Views are plain PHP templates. A custom compiled template
  engine may be considered later, behind the same view-engine interface.

### D-010: Build a full theming system
- **Date:** 2026-09-25
- **Decision:** Themes are a first-class, fully explored subsystem, not just a
  views folder. See `theming.md`.

### D-011: Static export is supported
- **Date:** 2026-09-25
- **Decision:** Blush supports exporting a site to static files (CLI `build`
  command), using the render-anywhere kernel.

### D-012: Custom CLI
- **Date:** 2026-09-25
- **Decision:** Blush ships its own in-house console framework and CLI (no
  symfony/console). See `cli.md`.

### D-013: Publishing and admin come in stages
- **Date:** 2026-09-25
- **Decision:** Build cache busting and "push content live" first (signed
  webhook + CLI), then an operations dashboard, then a full content editor.
  Design the core now so the admin fits without rework (content schemas,
  `ContentWriter`, sessions and auth middleware, render-anywhere preview).

### D-014: MIT license
- **Date:** 2026-09-25
- **Decision:** Blush 2 and all code copied into it are MIT licensed. Fix
  `composer.json` (currently GPL-2.0-or-later) and use the MIT URL in file
  headers.

### D-015: Code style: x3p0 style via PHPCS + custom skill
- **Date:** 2026-09-25
- **Decision:** Drop WordPress-style spacing. Follow x3p0 PHP style, enforced
  by PHPCS with a ruleset modeled on x3p0-breadcrumbs' `.phpcs.xml`, minus the
  WordPress rules and with PHPCompatibility set for 8.5. Install `x3p0-skills`
  as a dev dependency, and maintain a project-specific
  `blush-code-style-php` skill that takes precedence over it.

### D-016: Site layout: `user/` holds all user content and data
- **Date:** 2026-09-25
- **Decision:** Sites keep all user-owned content under `user/`:
  `user/content`, `user/media`, and any other user data (for example
  `user/data`). The web root is `public/`.

### D-017: Configuration is immutable, typed objects
- **Date:** 2026-09-25
- **Decision:** Take the most modern, developer-friendly approach. Config
  files return typed, immutable config objects built with named arguments
  (IDE autocomplete, validation at construction, static analysis). Each config
  class also offers `fromArray()` for array, YAML, JSON, or env-sourced input.
  The merged config is compiled and cached in production.

### D-018: Project knowledge lives in `.claude/`
- **Date:** 2026-09-25
- **Decision:** Keep all decisions, paths, and design docs as Markdown in
  `.claude/docs/`, with a root `CLAUDE.md` that imports `AGENTS.md`.

### D-019: Architecture patterns from the x3p0 projects
- **Date:** 2026-09-25
- **Decision:** Adopt the patterns from x3p0-breadcrumbs' AGENTS.md:
  - Type enum + Registry + Factory + Registrar
  - Abstract base + final concrete types
  - Immutable config objects
  - Context objects for pipelines
