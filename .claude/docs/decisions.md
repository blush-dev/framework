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
- **Status:** Partially superseded by D-037 (no `x3p0-skills` dependency).
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

### D-020: Themes are presentation only
- **Date:** 2026-09-25
- **Decision:** Themes may provide templates, layouts, components, context
  providers, design tokens, a settings schema, image sizes, assets, menus and
  regions, and template hierarchy candidates. Themes may **not** register
  content types, routes, or CLI commands, or write content. Those belong to
  the site or to extensions. A theme declares what it expects from the site in
  its manifest (`requires`), and `theme:check` warns when the site doesn't
  provide it.
- **Why:** Switching themes must never break content or URLs.

### D-021: Theme building favors simplicity above all
- **Date:** 2026-09-25
- **Decision:** Simplicity for theme authors is the top priority of the
  theming system. The smallest valid theme is a manifest plus a stylesheet;
  every template and component falls back through the theme chain to the
  framework default theme. Complexity (inheritance, tokens, settings,
  components) is opt-in.

### D-022: Themes are data-first: manifest, tokens, and settings are data files
- **Date:** 2026-09-25
- **Status:** Partially superseded by D-160 (no token files or site token overrides).
- **Decision:** A theme's manifest, tokens, and settings schema are data files
  (`theme.json`), not PHP. Theme behavior (components, context providers)
  lives in an optional theme service provider.
- **Narrows D-017:** Developer-facing configuration stays typed PHP objects.
  **User-editable data** (theme setting values, token overrides, menus,
  anything the future admin writes) lives in data files under `user/data/`.

### D-023: Design tokens use the W3C Design Tokens (DTCG) format
- **Date:** 2026-09-25
- **Status:** Superseded by D-160 (no design token system for now).
- **Decision:** Tokens follow the W3C Design Tokens Community Group format.
  Blush adds support for modes (e.g. light/dark). Blush merges theme, parent,
  and user tokens and compiles them to CSS custom properties.

### D-024: Theme inheritance is a generic parent chain
- **Date:** 2026-09-25
- **Decision:** Build for layered themes. A theme may name a `parent`; the
  loader resolves a chain of any depth (with cycle detection). Views,
  components, assets, tokens, and settings all resolve through the same chain:
  site overrides → active theme → its ancestors → framework default theme.

### D-025: Layouts via inheritance, components via composition with slots
- **Date:** 2026-09-25
- **Decision:** Layouts (the page shell) use `layout()`, sections, and a
  default section. Reusable pieces are components with typed props and slots
  (a default slot plus named slots). Keep the template API small.

### D-026: Content components use generic directive syntax (for now)
- **Date:** 2026-09-25
- **Decision:** Markdown uses the "generic directives" syntax for components:
  container `:::name{attrs}`, leaf `::name{attrs}`, and inline
  `:name[text]{attrs}`. These map one-to-one onto the component registry. An
  unknown directive renders as plain content, never as an error.

### D-027: Presentation controls are first-class front matter
- **Date:** 2026-09-25
- **Status:** Partially superseded by D-160 (no `tokens` front matter).
- **Decision:** Entries can control their own presentation through built-in
  front matter fields: `layout`, `template`, `stylesheet`, `class` (body/entry
  classes), and `tokens` (per-entry token overrides). These are part of the
  core entry schema, not ad-hoc meta.

### D-028: CMS-wide translation system
- **Date:** 2026-09-25
- **Decision:** Blush has a proper, CMS-wide translator (not a theme feature).
  It uses ICU MessageFormat via `ext-intl` (plurals, select, number/date
  formatting) with message catalogs per domain (framework, extensions, theme,
  site) and locale fallback. Themes and components use it through the view
  API.

### D-029: Framework owns feed, sitemap, and error templates; themes may override
- **Date:** 2026-09-25
- **Decision:** The framework ships feed (RSS, Atom, JSON Feed), sitemap, and
  error templates. Themes and sites can override them through the normal
  lookup chain.

### D-030: Accessibility out of the box
- **Date:** 2026-09-25
- **Decision:** The default theme meets WCAG 2.2 AA. `theme:check` validates
  accessibility basics: landmarks and skip link in the base layout, a `lang`
  attribute, and palette contrast computed from tokens.

### D-031: Build-free themes are supported; build tooling is the theme's concern
- **Date:** 2026-09-25
- **Decision:** Blush reads a Vite-style `manifest.json` if a theme has one and
  otherwise versions assets by mtime. Blush does not prescribe a build tool.

### D-032: Data files may be JSON or YAML; JSON wins
- **Date:** 2026-09-25
- **Decision:** Data files (theme manifests, tokens, `user/data/*`, message
  catalogs) may be JSON or YAML, validated against the same schema. If both
  `name.json` and `name.yaml`/`name.yml` exist, **JSON wins** (`doctor` and
  `theme:check` warn about the shadowed file). NEON is not built in; an
  extension could add it through the parser registry.
- **Consequence:** the future admin writes JSON only. It can save next to a
  hand-written YAML file without destroying its comments, and its values take
  precedence.

### D-033: The framework ships core content components
- **Date:** 2026-09-25
- **Decision:** The framework's default theme provides a core set of content
  components (gallery, figure, callout, embed, at minimum) so content stays
  portable across themes. Themes can restyle or override them by key.

### D-034: Themes live in `user/themes`
- **Date:** 2026-09-25
- **Decision:** Local themes live in `user/themes/{slug}`. Themes installed
  through Composer may also be read from `vendor/`. Theme assets are published
  to `public/themes/{slug}`.

### D-035: Per-request theme switching: `?theme=` in dev only
- **Date:** 2026-09-25
- **Decision:** In the dev environment only, `?theme={slug}` renders a request
  with another theme. No other theme switching for now (admin preview comes
  later). Focus is on getting the basic architecture in.

### D-036: Multilingual content: architected for, not built yet
- **Date:** 2026-09-25
- **Status:** ID part refined by D-088 (IDs are source paths; lookup keys
  carry the locale).
- **Decision:** Design the content model so multilingual content can be added
  without rework, but don't implement it in early milestones. Concretely:
  - Every entry has a `locale` (defaulting to the site locale).
  - Entry IDs and index keys include the locale.
  - The router and URL generator accept an optional locale segment.
  - Entries can be linked as translations of each other.
  The file convention (for example `post.fr.md` vs. `user/content/fr/…`) is
  decided when the feature is built.

### D-037: Our own skills only
- **Date:** 2026-09-25
- **Decision:** Don't install `x3p0-skills`. Blush keeps its own skills in
  `.claude/skills/` (starting with `blush-code-style-php`). Supersedes the
  `x3p0-skills` part of D-015.

### D-038: Product name is "Blush Framework" for now
- **Date:** 2026-09-25
- **Decision:** Use "Blush Framework" (namespace `Blush\`) until the author
  decides on a new name. Keep name references centralized so a rename stays
  mechanical.

### D-039: Site config lives at the project root; nothing executable is written into `user/`
- **Date:** 2026-09-25
- **Decision:** Site configuration stays in `config/` at the project root. It is
  developer code (typed PHP, D-017) that ships with the site's code and varies
  per environment through `.env`. `user/` holds the site owner's content and
  data. It may be its own git repo, is pulled by the publish webhook, and is
  written by the admin.
- **Rule:** The admin, `ContentWriter`, and uploads never write executable
  files into `user/`. They write only content, media, and data files, and
  uploads go through an allowlist. (`user/themes` and `user/extensions` contain
  PHP, but only people with repo or filesystem access change them.)

### D-040: Shared hosting is a first-class target
- **Date:** 2026-09-25
- **Decision:** jtcom runs on GoDaddy, which supports PHP 8.5. Blush must run
  fully on shared Apache hosting:
  - It ships an `.htaccess`.
  - The public directory name is configurable (e.g. `public_html`).
  - Nothing requires a long-running process.
  - Features that need shell access (`git pull` on publish) are optional and
    have fallbacks: upload files by SFTP, then trigger reindex and cache bust
    by webhook or the admin.
  - nginx and VPS setups are supported too, with sample configs.

### D-041: Extensions: Composer packages first, local folders too
- **Date:** 2026-09-25
- **Decision:** The end-user term is **extensions**. "Apps" was rejected because
  it collides with the site's `App\` namespace and the idea of the site itself
  being the app.
  - **Composer packages** (type `blush-extension`) are discovered from
    `vendor/composer/installed.json` and cached.
  - **Local extensions** live in `user/extensions/{slug}/`. Each has an
    `extension.json` (or `.yaml`, D-032) manifest declaring its name, version,
    a PSR-4 namespace and path, a provider, and requirements. Blush registers
    their autoloaders itself.
  - Both kinds are the same thing: a manifest plus a service provider. An
    extension may register content types, routes, CLI commands, components,
    listeners, parsers, field types, and translations. (Unlike themes, D-020.)
  - Extensions are enabled or disabled in site config.

### D-042: Content types can be defined in PHP or in data, under developer control
- **Date:** 2026-09-25
- **Decision:** Content types and their schemas share one definition model
  (`ContentTypeDefinition`, with `fromArray()`), loadable from two sources:
  - **Developer (code):** typed PHP in `config/content.php` or extension
    providers. These are authoritative and **locked**: read-only in the admin.
  - **Site data:** `user/data/types/{type}.json|yaml`. Editable (later) in the
    admin.
  - Defining the same type name in both places is an error (at boot, and in
    `doctor`).
  - Site config controls the data source: allow data-defined types or not,
    and optionally restrict what data types may do (e.g. no custom routing).
- **Why:** "A strong system that's controllable either way."

### D-043: Authors are a built-in content type
- **Date:** 2026-09-25
- **Decision:** The framework registers an `author` content type by default
  (entries in `user/content/authors/{slug}.md`). Site config can rename,
  reconfigure, or disable it.
  - Entries reference authors through an `authors` Reference field.
  - Author archives and per-author feeds work like taxonomy term archives.
  - Author entries feed structured data (schema.org `Person`, OpenGraph).
  - Future admin user accounts are separate from author entries but can link
    to one.

### D-044: Performance: as fast as possible, measured continuously
- **Date:** 2026-09-25
- **Decision:** Performance is a primary design goal. Principles:
  - Anything that can be computed ahead of time is compiled to opcache-friendly
    PHP files: config, routes, provider and extension discovery, the content
    index, and container autowiring metadata.
  - Everything else is lazy.
  - Page cache and static export are built in.
  - A benchmark suite (PHPBench, dev only) runs in CI against the jtcom-sized
    fixture and fails on regressions beyond a threshold. Baselines are
    recorded in M4.
- **Follow-up:** add a compiled/cached resolution plan to the copied container
  (reflection per request is too slow for this goal).

### D-045: Adopted defaults
- **Date:** 2026-09-25
- **Decision:** Adopted without objection:
  - **Names:** namespace `Blush\`, package `blush-dev/framework`, site skeleton
    `blush-dev/site`. (Superseded by D-062: the skeleton already exists as
    `blush-dev/blush`.)
  - **Interfaces:** they live in their subsystem namespace. Only cross-cutting
    contracts (e.g. `Bootable`) go in `Blush\Core`. There is no global
    `Contracts\` namespace.
  - **Testing and CI:** PHPUnit 12, PHPStan at max level, GitHub Actions, and a
    fixture site in `tests/Fixtures/site`.
  - **Temporary runtime libraries** (D-006), each behind a Blush interface:
    `symfony/yaml` and `league/commonmark` (plus our own directive extension,
    D-026).
  - **Environments:** an `Environment` enum (`Development`, `Staging`,
    `Production`) from `APP_ENV`, plus a separate `APP_DEBUG` flag.
  - **Required PHP extensions:** `intl`, `dom`, `mbstring`, `fileinfo`, and
    `gd` or `imagick`. Recommended: `opcache`, `apcu`. Optional: `pdo_sqlite`.
  - **Built-in front matter fields:** `title`, `slug`, `published`, `updated`,
    `status`, `summary`, `authors`, `image`, `locale`, `template`, `layout`,
    `stylesheet`, `class`, `tokens`, `redirect_from`, plus one key per taxonomy.
    Dates are ISO 8601; a date without an offset uses the site timezone.
  - **Default theme:** plain modern CSS with no build step.
  - **User-facing docs:** Markdown in `docs/`.
  - **jtcom:** untouched until M8. Its separate `user/` repo stays.

### D-046: The web root is relocatable: only `index.php` must be where the site lands
- **Date:** 2026-09-25
- **Decision:** jtcom is on GoDaddy cPanel shared hosting. SSH is available but
  currently off; it can be enabled. The document root is probably a fixed
  `public_html`. Blush must not care where the web root is:
  - The project lives anywhere, for example one level above `public_html`.
  - The web root needs only `index.php` (plus `.htaccess` and published
    assets).
  - `index.php` holds one path to the project's bootstrap. Everything else
    (the public path and URL, and where assets and media are published)
    comes from config.
  - `publish`, the webhook, and the admin must all work without SSH. Git
    pull is an optional extra when SSH or git is enabled.

### D-047: Toolchain: Herd PHP 8.5 CLI for the framework, DDEV for sites
- **Date:** 2026-09-25
- **Decision:** The framework is a library with no site of its own. Framework
  tooling (Composer, PHPCS, PHPStan, PHPUnit) runs on Herd's PHP 8.5 CLI,
  `~/Library/Application Support/Herd/bin/php85`, which has 8.5.10 installed.
  Herd's global PHP is now 8.5 (updated the same day), so plain `php` and
  `composer` run on 8.5. All
  browser-facing local sites use **DDEV** (the author's standard). The dev
  site from M2 onward (see `open-questions.md`) gets its own DDEV project at
  8.5. jtcom's DDEV stays below 8.5 until M8, because 8.5 breaks the 1.x
  site.
- **Verified:** `php85` has `intl`, `dom`, `mbstring`, `fileinfo`, `gd`,
  `imagick`, `pdo_sqlite`, `uri`, and OPcache. The pipe operator and
  `Uri\Rfc3986\Uri` work.

### D-048: PHP 8.5 syntax under PHPCS: property hooks need a disable comment
- **Date:** 2026-09-25
- **Decision:** Verified in M0 against PHPCS 4.0.4, PHPCompatibility
  10.0.0-alpha2, PHPStan 2.2, and PHPUnit 12.5:
  - The pipe operator, `clone()` with properties, asymmetric visibility,
    `#[\NoDiscard]`, `new` without parentheses, `array_first()`/`array_find()`,
    and `Uri\Rfc3986\Uri` pass all three tools.
  - **Property hooks** pass PHPStan and PHPUnit, but PHPCS can't tokenize them
    yet (upstream: PHPCSStandards/PHP_CodeSniffer#731, #1443). It reports false
    errors from `PSR2.Classes.PropertyDeclaration` and
    `PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess`.
  - Hooks are **allowed**. Wrap each group of hooked properties in
    `// phpcs:disable PSR2.Classes.PropertyDeclaration, PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess -- PHPCS can't parse property hooks yet.`
    and `// phpcs:enable`. `phpcbf` leaves hooked code alone inside that block.
    Remove the comments once PHPCS supports hooks.
- **Resolves** the "PHPCS and 8.5 syntax" open question.

### D-049: Dev tool versions and repo housekeeping
- **Date:** 2026-09-25
- **Decision:**
  - `squizlabs/php_codesniffer` ^4.0.4 (the latest release; always track
    the newest PHPCS), `phpstan/phpstan` ^2.2, and
    `phpunit/phpunit` ^12.5 (PHPUnit 13 exists; D-045 chose 12, revisit
    later if needed).
  - `phpcompatibility/php-compatibility` `^10.0@alpha`. The last stable
    release (9.3.5) predates PHP 8 and doesn't support PHPCS 4. The root
    `@alpha` flag keeps `minimum-stability: stable` for everything else.
  - `composer.lock` is not committed (the framework is a library). It is
    listed in `.gitignore`.
  - Tool caches (`.phpcs.cache`, `.phpstan.cache`, `.phpunit.cache`) are
    ignored.
  - `Blush\Core\Framework` holds the product name and version as typed
    constants, so there's one place to read the name from (D-038).

### D-050: jtcom may break locally during the rewrite
- **Date:** 2026-09-25
- **Decision:** No 1.x worktree. M0 cleared 1.x from `2.x`, and jtcom's
  symlinked `vendor/blush-dev/framework` stays broken locally until the M8
  port. Resolves the "keep jtcom running" open question.

### D-051: Where the M1 core lives
- **Date:** 2026-09-25
- **Decision:**
  - `Blush\Container`: the container and its attributes. Resolution plans
    are in `Blush\Container\Plan`.
  - `Blush\Core`: `Application`, `ServiceProvider`, `Bootable`,
    `BlushException`, `Paths`, `Environment`, `AppConfig`, `Bootstrap`, and
    core events (`Core\Events\ApplicationBooted`).
  - `Blush\Event`: the x3p0-event system, implementing PSR-14.
  - `Blush\Support`: `Registry` (from x3p0-class-registry), `Filesystem`,
    `PhpArrayFile`, and `Support\Attributes` (from x3p0-attributes).
  - `Blush\Env`, `Blush\Config`, `Blush\Error`, `Blush\Log`, `Blush\Clock`,
    and `Blush\Extension`.
  - Each config class lives with its subsystem: `Core\AppConfig`,
    `Log\LogConfig`, `Extension\ExtensionConfig`.
- **Dropped from the x3p0 copies:** the WordPress `begin()` lifecycle,
  `BroadcastsToHooks`, `esc_html()` calls, the PHP-version guard on the
  `Until` listener attributes, and x3p0-attributes' PSR-16-shaped `Cache`
  mirror. The attribute reader caches in memory only, because attribute
  instances can hold closures in 8.5.

### D-052: Compiled container resolution plans
- **Date:** 2026-09-25
- **Decision:** Implements the D-044 follow-up. The container builds classes
  from `ClassPlan`/`ParameterPlan` data (types in DNF, defaults, and
  container attributes recorded as class + arguments) instead of reflecting
  on every build.
  - `ReflectionPlanner` builds plans on demand; development uses it.
  - `CompiledPlanner` reads `storage/cache/container.php` and falls back to
    reflection for anything missing. It's used everywhere except
    development.
  - A plan holding a closure (an 8.5 closure attribute argument) or an
    object default (`new` in an initializer) is never compiled. Object
    defaults are evaluated fresh per build, as PHP would.
  - `Bootstrap::compile()` gathers plans by booting a fresh application.
    Which request-time classes to plan ahead of time is decided with the
    M2 compile command (see `open-questions.md`).

### D-053: Container behavior changes from x3p0
- **Date:** 2026-09-25
- **Decision:**
  - `Container` extends PSR-11 `ContainerInterface`, and the exceptions
    implement the PSR-11 exception interfaces.
  - `make()` and `build()` verify that the result is an instance of the
    requested class and throw `ContainerException` otherwise. Resolve
    non-class identifiers with `get()`.
  - A closure factory must return an object.
  - `Application` binds itself plus the container under `Container`,
    `ServiceResolver`, and `Psr\Container\ContainerInterface`.

### D-054: Application lifecycle
- **Date:** 2026-09-25
- **Decision:** A single register-then-boot pass (`begin()` removed).
  - `boot()` is idempotent. A provider registered after booting boots once
    its batch is registered.
  - The framework providers (events, clock, log, errors) always register
    first, then a subclass's `PROVIDERS`, then those `Bootstrap` adds:
    extensions, then the site's `AppConfig::$providers`. The theme chain's
    providers slot in before the site's in M5.
  - After the first boot pass, `ApplicationBooted` is dispatched when a
    dispatcher is bound.
  - The provider constants are typed (`protected const array`), so
    subclasses must type theirs too.

### D-055: One exception marker
- **Date:** 2026-09-25
- **Decision:** Every framework exception implements `Blush\Core\BlushException`
  (a `Throwable` marker) and extends the SPL base that fits best. Subsystem
  markers such as `EventException` extend `BlushException`.

### D-056: `.env` format and behavior
- **Date:** 2026-09-25
- **Decision:** The in-house parser supports `NAME=value`, `export`,
  comments, single quotes (literal), double quotes (escapes and `${VAR}`),
  multi-line quoted values, and inline ` #` comments on unquoted values.
  The process environment wins over `.env`. Nothing calls `putenv()`.
  Error messages name variables but never include their values.

### D-057: Config files, defaults, and caching
- **Date:** 2026-09-25
- **Decision:**
  - Each `config/*.php` returns one `Config` object or a list of them,
    with `$env` and `$paths` in scope. Files declare those with
    `/** @var Env $env */` for IDEs and PHPStan.
  - `Config` requires `fromArray()` and `toArray()`. `toArray()` returns only
    exportable values, since it's also the compile format.
  - Missing configs get defaults: `AppConfig::fromEnv()` (from the `APP_*`
    variables), `LogConfig`, and `ExtensionConfig`.
  - Every config object is bound in the container under its class, so
    services type-hint `AppConfig` directly.
  - The compiled config (`storage/cache/config.php`, with env values baked
    in) is used whenever it exists, in any environment.
  - `AppConfig::$providers` lists the site's own providers.

### D-058: Extension discovery details
- **Date:** 2026-09-25
- **Status:** YAML manifests delivered by D-092.
- **Decision:** Implements D-041.
  - Composer extensions declare `extra.blush.provider` (and optional
    `requires`).
  - Local extensions declare an `extension.json` with `name`, `version`,
    `description`, `provider`, `autoload.psr-4`, and `requires`. YAML
    manifests (D-032) arrive with the data loader in M4.
  - Every discovered extension is enabled by default. `ExtensionConfig`
    narrows that with `enabled` (allow-list) and `disabled`.
  - Enabling an extension that isn't installed is an error, and so is two
    extensions sharing a name.
  - `requires` is recorded but not yet enforced. `extension:check` and
    `doctor` enforce it later.
  - Discovery is cached in `storage/cache/extensions.php`, which is used
    everywhere except development.

### D-059: Errors and logging defaults
- **Date:** 2026-09-25
- **Decision:**
  - `ErrorHandler` turns warnings and notices into `ErrorException`, logs
    deprecations, honors `@` and `error_reporting`, and at shutdown catches
    fatal errors along with PHP 8.5's fatal backtrace.
  - Rendering is HTML (generic, or detailed when `AppConfig::$debug`) or
    plain text on the CLI (to stderr). Entry points register the handler.
  - The logger is in-house PSR-3: one line per record, with placeholder
    interpolation, leftover context as JSON, and exception chains appended.
  - `LogDriver` is `file` (default: `storage/logs/blush.log`), `stderr`, or
    `null`. The default level is `warning`. A driver registry for custom
    writers can come later.
  - The clock is PSR-20 (`SystemClock` in the site timezone, or
    `FrozenClock`).

### D-060: Compiled cache files
- **Date:** 2026-09-25
- **Decision:** Everything compiled goes through `Support\PhpArrayFile`: a
  PHP file returning an array, written atomically and invalidated in
  opcache. M1 writes `storage/cache/config.php`, `extensions.php`, and
  `container.php`, using `Bootstrap::compile()` and `clearCompiled()`. The M2
  CLI wraps these as commands.

### D-061: PHP 8.5 closures in attributes are `static function`
- **Date:** 2026-09-25
- **Decision:** 8.5 allows closures in constant expressions (attribute
  arguments) only as `static function () { … }`, with no arrow functions
  and no `use`. Recorded in the style skill.

### D-062: The site skeleton is the existing `blush-dev/blush`
- **Date:** 2026-09-25
- **Decision:** The default install (the "forkable project package") already
  exists: `blush-dev/blush`, at `../blush`
  (https://github.com/blush-dev/blush). It is the 1.x skeleton today. Blush 2's
  skeleton and M2's browser dev site are that repo, not a new `blush-dev/site`.
  Supersedes the skeleton name in D-045.

### D-063: The M2 dev site is a `2.x` branch of `../blush` on DDEV
- **Date:** 2026-09-25
- **Decision:** `blush-dev/blush` (D-062) gets a `2.x` branch rewritten to
  the new site layout (`paths.md`). It requires the framework through a
  Composer path repository (`../blush-framework`), and runs on its own DDEV
  project at PHP 8.5, with the framework mounted into the web container at
  the same relative location (as jtcom does). It is the browser playground
  and the future skeleton. Automated tests keep using `tests/Fixtures/site`.
- **Resolves** the "dev site from M2 onward" open question.

### D-064: Entry points handle errors in two stages
- **Date:** 2026-09-25
- **Decision:** `public/index.php` and `bin/blush` first register a bare
  `ErrorHandler` (generic output, no logger; detailed only when `APP_DEBUG`
  is set in the process environment) before anything loads. Once the
  application is built, they swap it for the configured `ErrorHandler`. A
  broken `.env` or config file still renders cleanly.
- **Resolves** the "error handler timing" open question.

### D-065: Console commands: attribute plus `__invoke()` parameters
- **Date:** 2026-09-25
- **Decision:** A command is an invokable class marked
  `#[Command(name, description)]`. Its constructor takes services (autowired,
  as anywhere else). Its input is declared as `__invoke()` parameters:
  `#[Argument]` and `#[Option]` parameters are parsed from argv, with
  parsing, validation, and help text driven by their types and defaults. Any
  other parameter (such as `Output`) is resolved from the container.
  `__invoke()` returns an `ExitCode`. Refines the input part of `cli.md`.

### D-066: Container plan warm-up plans everything the container knows
- **Date:** 2026-09-25
- **Decision:** `Bootstrap::compile()` plans every class the booted
  container knows about (bound concretes, alias targets, and tagged
  abstracts, which include commands and middleware) plus their constructor
  dependencies, recursively. No sample requests are dispatched. Anything
  missed still falls back to reflection at runtime.
- **Resolves** the "container plan warm-up" open question (D-052).

### D-067: The M2 HTTP layer
- **Date:** 2026-09-25
- **Decision:** Implements D-005 in `Blush\Http`:
  - One request class: `Request` implements `ServerRequestInterface` (and so
    serves PSR-17's `createRequest()` too). `Response`, `Uri`, `Stream` (one
    resource-backed class with `fromString()`/`fromFile()`), and
    `UploadedFile`. Messages share the abstract `Message` base and are
    `readonly`, with `with*()` built on `clone()` and marked `#[\NoDiscard]`.
  - `Uri` parses with `Uri\Rfc3986\Uri` after percent-encoding characters
    browsers send raw (spaces, brackets in queries), and applies PSR-7's
    rules itself.
  - A `Status` enum names the registered codes and supplies reason phrases.
  - `HttpFactory` implements all six PSR-17 interfaces and is bound under
    each one.
  - `RequestFactory::fromGlobals()` is the only superglobal reader. It does
    not trust forwarded headers; `TrustProxies` will.
  - `Kernel` implements `RequestHandlerInterface`. It always runs
    `HandleErrors` outermost (HTML output whatever the SAPI, logged through
    `ErrorHandler::report()`), then `HttpConfig::$middleware`, then its
    handler. It dispatches `RequestReceived` and `ResponseReady` for
    listeners to observe; changing requests or responses is middleware's
    job.
  - Until the router (M3), the kernel's handler is `WelcomeHandler`, given
    by a contextual binding. It isn't the "empty-state page" (still open).
  - `Emitter` writes through a `Sapi` interface (`NativeSapi` by default),
    skips the body for `HEAD` and 1xx/204/304, and calls
    `fastcgi_finish_request()` when available.
  - `Response::file()` has no Range support yet; that comes with media in
    M4.
  - Only `HandleErrors` ships in M2. The other built-in middleware land with
    the features that need them.

### D-068: Entry points are runners
- **Date:** 2026-09-25
- **Decision:** `Core\Runner` (abstract) holds the D-064 two-stage error
  handling and builds and boots the application once. `Http\HttpRunner`
  (`run()`, plus `handle()` for tests) and `Console\ConsoleRunner`
  (`run($argv): int`) are the concrete entry points. `public/index.php` and
  `bin/blush` are each about three lines. If the application can't launch,
  `ConsoleRunner` prints the error and returns exit code 1 instead of
  leaving PHP to exit with 0.

### D-069: Console framework details
- **Date:** 2026-09-25
- **Decision:** Implements D-012 and D-065 in `Blush\Console`:
  - `Console::run($argv)` is the runner. It applies the global options
    (`-h`, `-q`, `-v…`, `--ansi`/`--no-ansi`, `-n`, `-V`), and binds the
    per-run `Output` and `Prompt` into the container. Input errors exit
    with `ExitCode::Invalid` (2). Other exceptions are logged, rendered to
    stderr, and exit with `Failure` (1).
  - `CommandRegistry` is its own class, not `Support\Registry`, because
    commands have no interface. Commands register declaratively by tagging
    them with `CommandRegistry::TAG` in a provider's `TAGS`. Tagged commands
    win; `CommandRegistrar` then seeds the `BuiltInCommand` enum's classes
    with `registerIf()`.
  - `Testing\CommandTester` runs commands with in-memory streams.
  - Progress bars are deferred to M4 (`content:index`).
  - The executable name lives in `Framework::BINARY` (D-038).
  - Built-in commands in M2: `list`, `help`, `serve`,
    `cache:clear [--config] [--extensions] [--container]`, and
    `cache:compile` (the M1 carry-over). `serve` runs `php -S` with the
    framework's `resources/server.php` router, which, as an entry point, may
    read superglobals.
  - Processes start through a `ProcessRunner` interface
    (`SystemProcessRunner` uses `proc_open` with an argument list, never a
    shell).

### D-070: The `2.x` skeleton is MIT licensed
- **Date:** 2026-09-25
- **Decision:** The `blush-dev/blush` `2.x` branch declares `MIT` in
  `composer.json`, matching D-014. It is a new project, requires
  `blush-dev/framework` `2.x-dev` from the `../blush-framework` path
  repository, and serves `public/` on DDEV at https://blush.ddev.site.
- **Needs confirmation:** the author should confirm the license change
  and add a `LICENSE.md` to the skeleton.

### D-071: A whole-project install in the web root works out of the box
- **Date:** 2026-09-25
- **Decision:** Most users will upload the entire project into
  `public_html` (or whatever the host calls it), and that must work with no
  setup. The site ships a root `.htaccess` that internally rewrites every
  request into `public/`. So:
  - `public/` stays the only servable directory (the security baseline
    holds). `config/`, `.env`, `user/`, `vendor/`, and `storage/` are never
    served; requests for them reach the front controller.
  - URLs don't include `public/`.
  - Without mod_rewrite, the root `.htaccess` denies every request (it fails
    closed rather than exposing the project).
  - Pointing the document root at `public/` (VPS, nginx, or a configurable
    host) still works; the root `.htaccess` is then unused.
  - The DDEV dev site runs this way (Apache, docroot = project root), so
    the common case is what gets exercised.
- **Refines** D-016 and D-046: `public/` is still the web root *inside*
  the project, but the project root may itself be the host's document
  root.
- **Follow-ups:** `/public/...` URLs also resolve (duplicate URLs) and
  should redirect to the canonical form (the `CanonicalUrl` middleware or
  M3). Installing in a subdirectory (`example.com/site/`) needs a base
  path, still to be designed. nginx is covered by D-072.

### D-072: nginx is supported with `root` at `public/`
- **Date:** 2026-09-25
- **Decision:** nginx doesn't read `.htaccess`, and PHP can't stop a web
  server from serving a static file such as `.env`. So on nginx the server
  block's `root` must be the project's `public/` directory. The skeleton
  ships `nginx.conf.example`: real files are served, everything else goes
  to `index.php`, only `index.php` runs as PHP (other `.php` files return
  404), and hidden files are denied.
  - Verified under DDEV's nginx with the sample, and under DDEV's Apache
    with the project root as the web root (D-071).
  - A whole-project install into an nginx web root that the owner can't
    configure is not supported. Such hosts are rare; cPanel hosts run
    Apache or LiteSpeed, which honor `.htaccess`.
- **Resolves** the nginx follow-up in D-071.

### D-073: The M3 router
- **Date:** 2026-09-25
- **Decision:** Implements the routing design in `Blush\Routing`:
  - **Definitions:** `Route` is an immutable value object
    (`Route::get('/archives/{year:\d{4}}', [Archive::class, 'year'])->named(…)`,
    plus `where()`, `defaults()`, `middleware()`, and `Route::group()`).
    Patterns use `{name}` or `{name:regex}`; constraints may nest braces
    but not capture. Paths are written without a trailing slash.
  - **Site routes** live in `config/routes.php` as a `RouteConfig` (routes,
    attribute-routed `controllers`, `redirects`, `trailingSlash`), so they
    compile with the rest of the config (D-057).
  - **Sources:** each `RouteSource` has a `RoutePriority` (System, Content,
    Controllers, Config, Fallback). Extensions tag sources with
    `RouteSource::TAG`, controllers with `ControllerRoutes::TAG`, and
    redirect sources with `RedirectSource::TAG`. Controllers are listed or
    tagged, never scanned for. Themes can't add routes (D-020), which
    corrects the architecture doc's "site, theme, or extensions".
  - **Precedence:** static paths match before patterns. Among patterns,
    priority then registration order decides. When two routes claim the
    same method and pattern, the higher priority wins and the other is
    recorded as shadowed (`routes:list` warns). Duplicate names are an
    error.
  - **Handlers:** an invokable class, a `[Class, 'method']` pair, or a
    PSR-15 handler, called through `Container::call()`. Parameters are
    filled by name from path parameters and defaults. Parameters typed as a
    request get the request, and the container autowires the rest.
    Parameters typed `int`, `float`, `bool`, or a backed enum are cast, and
    unconstrained ones get a matching constraint automatically. The
    compiler records all of this, so dispatch doesn't use reflection.
    Handlers must return a `ResponseInterface`.
  - **Attributes:** `#[Route]`, `#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`,
    `#[Delete]` (repeatable, on classes or public methods) and a class-level
    `#[Group(prefix, name, middleware)]`.
  - **Matching:** a hash map for static paths, then per-method combined
    regexes in chunks of 32 alternatives, using branch reset and
    `(*MARK)`. `HEAD` falls back to `GET`. `OPTIONS` without a route gets a
    204 with `Allow`.
  - **Request data:** the `RouteMatch` and each parameter become request
    attributes, and `RouteMatched` is dispatched before the route's
    middleware runs.
  - **`UrlGenerator::to(name, params, absolute)`:** extra values become
    the query string, and absolute URLs use `AppConfig::$url`'s origin.
  - `WelcomeHandler` is now a `Fallback` route for `/`, so any site route
    for `/` replaces it. The empty-state question stays open.
    (Superseded by D-093: the home page controller shows it instead.)

### D-074: Trailing slashes: one canonical form, configurable
- **Date:** 2026-09-25
- **Decision:** By default, canonical URLs have no trailing slash (jtcom's
  URLs don't). `RouteConfig::$trailingSlash = true` flips that. The
  non-canonical form redirects when the canonical one would match: 301 for
  `GET`/`HEAD`, and 308 otherwise so the method and body are kept. The
  query string carries over. `/` and paths whose last segment has a dot
  (`/feed.xml`) never get a slash. Generated URLs follow the setting.

### D-075: HTTP errors are exceptions with a status
- **Date:** 2026-09-25
- **Decision:** `Http\HttpError` (a `RuntimeException` with a `Status` and
  headers) can be thrown by anything, with `NotFound` and
  `MethodNotAllowed` (which sets `Allow`) as subclasses. `HandleErrors`
  turns them into responses with that status and doesn't log them.
  `HtmlRenderer` shows a short status page (with the message only in debug)
  until themed error pages arrive in M5.

### D-076: Redirects are the last resort before a 404
- **Date:** 2026-09-25
- **Decision:** Redirects (`Redirect(from, to, status = 301)`, from
  `RouteConfig::$redirects` and any tagged `RedirectSource`) are checked
  only when nothing answers: no route matches, or a handler throws
  `NotFound`. So a redirect can never shadow a live page. `from` is a route
  pattern whose parameters `to` can reuse (`/blog/{slug}` → `/archives/{slug}`).
  `to` may be an absolute URL, the query string carries over unless `to`
  has one, and the first redirect for a pattern wins. `redirect_from`
  front matter and data-file redirects arrive with content (M4) as more
  sources.
  - `/public/...` URLs from a whole-project install (D-071) redirect to the
    path without `public/`, when `public/` sits directly in the project
    root. **Resolves** that D-071 follow-up.

### D-077: The route table is a compiled cache
- **Date:** 2026-09-25
- **Decision:** The compiled table is `storage/cache/routes.php`
  (`CompiledCache::Routes`), used everywhere except development, like the
  other caches (D-060). Development compiles it on every request.
  `cache:compile` writes it and plans every route's controller (D-066);
  `cache:clear --routes` deletes it. Routes stay arrays in the table until
  one is matched. `routes:list` shows routes (method, path, name, handler,
  source), redirects, and shadowed routes.

### D-078: Blush 1.x content behavior carries over
- **Date:** 2026-09-25
- **Decision:** 2.x keeps every content convention 1.x supports, because
  jtcom's files and front matter won't change. The 2.x names are canonical,
  and the 1.x names are accepted as aliases. The inventory, taken from
  `master`:
  - **File names:** everything before the last `.` of a file name is
    organizational and not part of the slug (`01.about.md`,
    `2003-04-15.welcome.md`, `2008-04-05-3.bay-bay.md` → `about`,
    `welcome`, `bay-bay`). It isn't parsed as a date. The default order
    is by file name.
  - **`index.md`** is its directory's landing page: a type's collection
    page, or the page at the directory's URL. Collection listings skip it.
  - **Hidden:** a `_`-prefixed file name, or `visibility: hidden`. Hidden
    entries aren't routed or listed, but a query by name finds them.
  - **Front matter:** `published`, with `date` as the fallback (published
    wins); `updated` falls back to `published`, then the file's mtime;
    `author` (a scalar or a list) for authors; `excerpt` for the summary
    (Markdown), with an automatic 50-word excerpt as the fallback;
    `subtitle`; `image`; `template` and `view` (lists of view names);
    `collection` (query arguments for a page's own listing); and one key
    per taxonomy, named after the type, holding a scalar or a list of
    slugs. Keys no schema declares are kept (see D-081).
  - **Type options:** `path`, `public`, `collection`, `routing` (`false`, or
    `prefix` plus named `paths`), `date_archives`, `time_archives`,
    `taxonomy`, `collect`, `term_collect`, `term_collection`, `feed`
    (`true`, or `taxonomy` plus `collection`), and `sitemap`. Route names
    `{type}.single`, `{type}.collection`, `.paged`, `.feed`, `.feed.atom`,
    and the date-archive names. Single URLs can use `{name}`, `{year}` …
    `{second}`, `{author}`, and any taxonomy name.
  - **Home alias:** the home page can show a type's collection, with
    `/feed`, `/feed/atom`, and `/page/{page}`.
  - **Query arguments:** `type`/`path`, `names` (`slug`), `names_exclude`,
    `number` (≤ 0 means all), `offset`, `order`, `orderby` (`filename` by
    default; `published`, `updated`, `title`, `author`, or a meta key),
    `author`, `meta_key`/`meta_value`, `year` … `second`, and `noindex`.
  - **Page catch-all:** `path/index.md`, then `parent/name.md`; `_`-prefixed
    segments are private.
  - **Markdown:** configurable CommonMark options, extensions, and inline
    parsers. A lone image becomes a `<figure>` (its title is the
    `<figcaption>`, and width and height come from the media file) that
    isn't wrapped in `<p>`, and root-relative links become absolute.
  - **Media:** an allowlist of image, audio, and video MIME types.
- **Why:** the author asked that nothing 1.x supports be dropped.

### D-079: M4 ships in three slices
- **Date:** 2026-09-25
- **Decision:** M4a (data files, the YAML and Markdown adapters, content
  types, schemas, and field types), M4b (source, index, indexer,
  repository, query, and `content:*` commands; jtcom indexes and lints),
  and M4c (content routes, the page catch-all, `redirect_from`, data-file
  redirects, media with Range support, and PHPBench). Each slice ends with
  `composer check` passing, for review.

### D-080: YAML and Markdown adapters
- **Date:** 2026-09-25
- **Decision:** Implements D-045's temporary libraries: `symfony/yaml`
  ^8.1 and `league/commonmark` ^2.10, each behind a Blush interface.
  - YAML is parsed with `PARSE_DATETIME` and
    `PARSE_EXCEPTION_ON_INVALID_TYPE` (no objects). The adapter returns only
    plain data: timestamps come back as ISO 8601 strings, and a timestamp
    written without an offset comes back without one, so date fields read
    it in the site timezone (D-045). Symfony marks those with a `UTC` zone
    name, unlike an explicit `Z` or `+00:00`.

### D-081: Undeclared front matter is kept
- **Date:** 2026-09-25
- **Decision:** Schemas are open. Keys a schema doesn't declare are kept as
  untyped extra values on the entry, and `content:lint` reports them only
  with `--strict`. A type can opt into a closed schema, which makes them
  lint errors.

### D-082: Status and visibility are separate
- **Date:** 2026-09-25
- **Decision:** An entry has a `status` (`Published`, `Draft`, or
  `Scheduled`, which is derived from a future `published` date) and a
  `visibility` (`Public`; `Unlisted`, routed but not listed; or `Hidden`,
  1.x's `hidden`, neither routed nor listed but found by a query by name).
  Front matter may set `status: published|draft` and
  `visibility: public|unlisted|hidden`. This moves `Unlisted` out of the
  status enum that `architecture.md` listed.

### D-083: The content type model
- **Date:** 2026-09-25
- **Decision:** Implements D-042 and D-043 in `Blush\Content\Type`:
  - One class, `ContentType`, is the definition from every source (there's
    no separate `ContentTypeDefinition`). `fromArray()` takes the 2.x names
    or the 1.x ones (D-078); `routing` is `TypeRouting` (prefix plus
    per-key paths over 1.x's defaults) or `false`; `feed` is `TypeFeed` or
    `false`; `archives` is an `ArchiveGranularity`.
  - `ContentConfig` (`config/content.php`) holds the site's types, the
    home alias (`home`, 1.x's `app.home_alias`), the data-type policy
    (`dataTypes`, `dataTypeRouting`), and `disabled` built-ins (only
    `author`; `page` can't be disabled).
  - `ContentTypeLoader` merges, in order: built-ins, extension types
    (tagged `ContentTypeSource::TAG`; two extensions with one name is an
    error), config types (which replace either), then data types from
    `user/data/types`. A data type may redefine a built-in type, but not an
    extension or config type.
  - A taxonomy's term field is named after the type unless `field` says
    otherwise, and `fieldAliases` adds more keys. The built-in `author`
    uses `authors`, with `author` as an alias.
  - A file belongs to the type whose path is the nearest folder above it,
    falling back to `page`, so page bundles and nested taxonomies
    (`writing/forms`) resolve without extra rules.
  - A type's schema is the built-in entry fields, then every term field
    (which may not reuse a built-in name or alias), then the type's own
    fields (which may replace either).
  - Types load on first use. Caching them for production comes with the
    index in M4b.

### D-084: Schemas normalize for the index and hydrate for entries
- **Date:** 2026-09-25
- **Decision:** Implements the schema design in `Blush\Content\Schema`:
  - A `Field` normalizes a raw value into exportable data for the index,
    and hydrates that into the typed value entries expose. Dates are stored
    as Unix timestamps and hydrated as `DateTimeImmutable` in the site
    timezone. Relative dates (`tomorrow`) are refused.
  - The shared settings (aliases, required, default, label, description)
    are set with immutable fluent copies, such as
    `new DateField('published')->aliases('date')`.
  - `null`, `""`, and `[]` count as missing, as they did in 1.x
    (`image: ""`). A single value counts as a list of one.
  - Resolving never throws for bad content. It reports `Violation`s with a
    `Severity`: errors (values that don't fit, missing required fields,
    undeclared keys in a closed schema), warnings, and notices (undeclared
    keys and aliases in use, for `content:lint --strict`).
  - Reference values are turned into slugs with 1.x's rules
    (`Support\Slug`).
  - A field class that isn't the built-in for its type records its
    `class` in `toArray()`, so compiled caches rebuild extension field
    types without their registry.
- **Checked:** all 1,183 jtcom content files resolve with no errors or
  warnings.

### D-085: Data files and document parsers
- **Date:** 2026-09-25
- **Decision:**
  - `Data\DataLoader` reads files by name through the `DataParserRegistry`
    (JSON, YAML, YML; JSON wins, D-032), reports shadowed files, confines
    names to their directory, and parses whole directories (`loadAll()`).
  - `Content\Parser\DocumentParsers` picks a `DocumentParser` by file
    extension: Markdown (`.md`, `.markdown`) and HTML with YAML front
    matter, and data entries (`.json`, `.yaml`, `.yml`, or any data format
    an extension adds) whose optional `body` key holds Markdown. Files with
    other extensions aren't content.
  - Front matter follows 1.x: a block opened by `---` on the first line
    and closed by `---` (or `...`). An unclosed block isn't front matter.
  - Extensions add data formats, document formats, and field types by
    registering on the registry in a `resolving()` callback; each registry
    is seeded with the built-ins when it's built.

### D-086: Markdown config details
- **Date:** 2026-09-25
- **Decision:** `MarkdownConfig` (`config/markdown.php`) holds CommonMark
  `options`, `extensions`, and `inlineParsers` (1.x's `config` and
  `inline_parsers` keys are accepted). The defaults are CommonMark plus
  autolinks, strikethrough, tables, task lists, and footnotes, with raw
  HTML allowed. The classes are checked when the converter is first built,
  not in the constructor, so requests that don't render Markdown never load
  CommonMark.

### D-087: Source, index, and indexer
- **Date:** 2026-09-25
- **Decision:** Implements the storage half of D-003 in M4b:
  - `Content\Source\ContentSource` has `files()` (sorted `SourceFile`s:
    relative path, mtime, size), `stat()`, and `read()`.
    `FilesystemSource` reads `user/content`: any file a document parser
    handles, skipping dotfiles and dot-folders, with every path confined
    to the root.
  - `Content\Index\ContentIndex` has `exists()`, `snapshot()`, `save()`,
    `clear()`, and `select(Query, now)`. `PhpIndex` stores
    `storage/index/content.php` through `PhpArrayFile` and answers queries
    with array filters (`ArraySelector`). It's a data file, used in every
    environment (not a compiled cache).
  - An `IndexSnapshot` holds the records (arrays, keyed by ID, in ID
    order; only returned entries become objects), plus lookups derived at
    build time: keys by locale/type/key, reverse term relations, term
    labels, key conflicts, the earliest scheduled time, and a fingerprint.
  - `Indexer::index($full, $progress)`: a file whose mtime and size match
    its record isn't read; one whose hash (xxh128) matches keeps its
    record (a touch doesn't change `updated`); anything else is parsed.
    A different fingerprint (index format, content types, timezone,
    locale) forces a full run. Files that fail to parse are left out and
    reported. The index is written only when something changed, and then
    `ContentIndexed` is dispatched with the `IndexReport`.

### D-088: Entry identity and file conventions
- **Date:** 2026-09-25
- **Decision:** Implements D-078's file rules in `RecordBuilder`:
  - An entry's **ID is its source path** (`_posts/2003-04-15.welcome.md`).
    **Refines D-036:** IDs don't carry a locale, since each translation
    will be its own file; the lookup keys (locale/type/key) carry it.
  - **Slug:** the file name after its last `.`, the folder name for a
    bundle (`hello/index.md`), `index` for a landing page, or `slug:`.
  - **Landing page:** `index` directly in its type's folder (key `''`).
    Anywhere else, `name/index.md` is a bundle, listed in the folder
    above (so `about/index.md` is the page `about`).
  - **Key:** the slug prefixed by the folders between the type's folder
    and the entry (`about/biography`), which the page catch-all (M4c) can
    look up directly. When two files claim a key (`about.md` and
    `about/index.md`), the bundle wins (1.x's order) and `content:lint`
    warns.
  - **Hidden:** a `_`-prefixed file name, or a `_`-prefixed folder
    between the type's folder and the file (including a bundle's own
    folder), whatever front matter says. A `_drafts` folder makes entries
    drafts. (jtcom's `__drafts/` and `_error/` are hidden.)
  - **Status** is stored as declared (`published`/`draft`); `Scheduled` is
    decided against the clock when a record is read or queried.
  - Dates are stored as timestamps plus a `YmdHis` string in the site
    timezone, for date archives.
  - `Entry` is a readonly value (ID, type, slug, key, title, status,
    visibility, dates, locale, typed `fields`, raw `extra`, `terms`,
    `landing`, `source`) whose `Body` is a lazy ghost. `excerpt()` renders
    the summary, or takes the body's first 50 words without figure
    captions (1.x). Entry URLs come with content routes in M4c.

### D-089: Query semantics
- **Date:** 2026-09-25
- **Decision:** One immutable `Content\Query\Query` is both the criteria and
  the fluent builder. A query made by the repository's `query()` carries
  its `QueryRunner`, so `get()`, `first()`, `count()`, and `paginate()`
  end a chain; results are an `EntryCollection` (lazily hydrated) or a
  `Paginator`.
  - `fromArray()` takes 1.x's arguments (D-078) plus `status`,
    `visibility`, `terms`, and `locale`. Unknown arguments are an
    `InvalidQuery`. It defaults to 1.x's 10 entries; the builder defaults
    to all.
  - **Changes from 1.x:** without `type` or `path`, a query spans every
    type (1.x read the content root only); `year` through `second` all
    filter (1.x filtered only year, month, and day); title sorting is
    natural and case-insensitive.
  - Defaults: published, public, no landing pages, file-name order.
    Naming entries also finds unlisted and hidden ones; naming `index`
    finds landing pages.
  - `whereTerm(taxonomy, ...slugs)` matches any of the slugs (slugified)
    and each call must hold; `author` adds one condition per author (all
    must match, as in 1.x). A taxonomy that isn't a type falls back to a
    field of that name. `meta_value` is compared as a slug.
  - Sorting puts missing values lowest and keeps file-name order for
    ties.

### D-090: The repository and index freshness
- **Date:** 2026-09-25
- **Status:** Refined by D-098 (a stale index is rebuilt in any
  environment).
- **Decision:** `ContentRepository` (interface) extends `QueryRunner` and
  adds `query()`, `find(id)`, `named(type, key, locale)`, `term()`, and
  `termCounts()`. `IndexedRepository` is the default.
  - The index is built on first use if none exists, in any environment,
    so a new site works before `content:index` runs.
  - In development, the first use per request runs an incremental index
    (`ContentConfig::$autoIndex`, on by default). No throttle: a no-op
    run over jtcom's 1,183 files takes about 20 ms on the CLI without
    opcache. Elsewhere, reindexing is explicit (CLI, webhook, admin).
  - Reverse relations are kept for taxonomy terms only (authors
    included), keyed by taxonomy name. A referenced term without a file
    is a virtual entry titled with the term as first written
    (`Book Reviews`). Other reference fields are forward-only for now.
  - `termCounts()` counts listed entries (published and public).

### D-091: Lint severities and the `content:*` commands
- **Date:** 2026-09-25
- **Decision:**
  - `Content\Lint\Linter` reads files fresh (not the index). Errors:
    unparseable files, values that don't fit fields, and `collection`
    front matter that isn't a valid query. Warnings: two files claiming
    one entry. Notices (`--strict`): undeclared keys, 1.x aliases, and
    virtual terms. Any error fails `content:lint`.
  - `content:index [--full]` shows a progress bar, lists changes with
    `-v`, and fails when a file can't be indexed. `content:list [--type]
    [--status]` shows every entry whatever its status or visibility.
  - `content:new <type> <title> [--slug] [--draft]` writes
    `{type path}/{slug}.md`; types with date archives get jtcom's
    `Y-m-d.{slug}.md` name and a `published` time. It never overwrites,
    and refreshes the index.
  - `Console\ProgressBar` (from `Output::progress()`) draws only on an
    ANSI terminal at normal verbosity, redraws when the percentage
    changes, and clears itself when finished. Resolves the progress-bar
    deferral in D-069.

### D-092: Content types are a compiled cache; YAML extension manifests
- **Date:** 2026-09-25
- **Decision:** Resolves the two M4a carry-overs.
  - The resolved content types compile to
    `storage/cache/content-types.php` (`CompiledCache::ContentTypes`,
    via `ContentTypeCache`), read everywhere except development.
    `cache:compile` writes it; `cache:clear --types` deletes it.
  - Local extensions may use `extension.yaml` or `extension.yml`; JSON
    wins when a folder has several (D-032). Discovery runs before the
    container exists, so the finder uses `SymfonyYamlParser` directly.
    Resolves the YAML part of D-058.

### D-093: Content routes
- **Date:** 2026-09-25
- **Decision:** Implements the content half of the M3 router (D-078's
  route names and URL parameters):
  - `Content\Routing\ContentRoutes` (priority `Content`) registers every
    public, routed type: `{type}.collection(.paged)`, the date archives
    the type's granularity allows (`.collection.{level}(.paged)`), and
    `{type}.single` (plus `.single.paged` for a taxonomy). Types go
    deepest path first and routes in 1.x's order, so date archives match
    before singles. `{year}` is constrained to four digits, `{month}`
    through `{second}` to two, and `{page}` to digits.
  - The home type (`ContentConfig::$home`) has no collection routes of
    its own. `PageRoutes` (priority `Fallback`) holds `home` (`/`),
    `home.paged` (`/page/{page}`, with a home type), and the page
    catch-all `page.single` (`/{path:.+}`). Being fallbacks, a site's
    own `/` route in `config/routes.php` replaces the home page.
  - The routing layer's `FallbackRoutes` is gone. `HomeController`
    shows `WelcomeHandler` when a site has no `index.md` and no home
    type. **Supersedes** the welcome-route part of D-073.
  - Feed and sitemap routes (`.feed`, `.feed.atom`, `home.feed`) arrive
    with feeds and sitemaps in M5.

### D-094: Content controllers and the page renderer
- **Date:** 2026-09-25
- **Decision:**
  - The controllers (`Content\Http`: `HomeController`,
    `CollectionController`, `DateArchiveController`, `SingleController`,
    `TermController`, `PageController`, on an abstract
    `ContentController`) decide what a URL shows and build a
    `ContentPage` (kind, title, entry, type, `Paginator`, date parts, and
    a page-URL callback). A `PageRenderer` turns it into a response.
    `BasicPageRenderer` is a plain HTML stand-in until M5 binds a themed
    renderer.
  - Listings use 1.x's arguments: the type's `collection` (or the
    taxonomy's `termCollection`), then the landing page's or term's own
    `collection` front matter.
  - **Canonical URLs:** `/page/1` redirects to the first page, and a
    single reached by a URL that isn't its own (such as a wrong date)
    redirects to its URL. A page past the last is a 404.
  - **Changes from 1.x:** a collection doesn't need a landing page, and
    page 1 of an empty collection or term still renders (1.x 404'd);
    empty date archives are still 404s. Virtual terms have archives. A
    term lists its taxonomy's `termCollect` type, or every type when
    unset (D-083). The page catch-all serves only entries of types
    without routing, so a routed entry isn't also reachable at its
    folder path (1.x served both).
  - The router's `int` casts accept leading zeros (`/archives/2024/05`).

### D-095: Fallback routes are soft
- **Date:** 2026-09-25
- **Decision:** The page catch-all answers `GET` for every path, so the
  router treats `Fallback`-priority matches specially:
  - When a fallback finds nothing (throws `NotFound`) but other routes
    answer the path with other methods, the request is a 405.
  - `Allow` and `OPTIONS` list the methods non-fallback routes answer,
    when there are any.
  - A non-canonical path (trailing slash) that only a fallback matches
    redirects only if the fallback finds something at the canonical
    path, so unknown URLs stay 404s instead of redirecting first.
  - `RouteTable::methodsFor($path, $fallbacks)` can leave fallbacks out.

### D-096: Content URLs come from type routing
- **Date:** 2026-09-25
- **Decision:** `Content\Routing\ContentUrls` builds entry, collection,
  term, and date-archive URLs from the same `TypeRouting` that
  `ContentRoutes` registers (`ContentType::routePattern()`), not from the
  route table, so redirect sources can use it while the table compiles.
  - A routed entry fills `{name}` with its key, the date parts from its
    published date, and each taxonomy parameter (such as `{author}` or
    `{category}`) with its first term. An entry in a subfolder of a
    routed type's folder has no URL, since `{name}` is one segment.
  - Unrouted types' entries live at their folder path; landing pages
    are their type's collection; hidden entries have no URL.
  - `AppConfig::origin()` and `absoluteUrl()` make paths absolute, for
    the URL generator, content URLs, and Markdown.

### D-097: Redirect sources and order
- **Date:** 2026-09-25
- **Decision:** Redirect sources are consulted in tag order, and the
  first redirect for a path wins:
  1. `config/routes.php` (and extension sources tagged at register
     time).
  2. `user/data/redirects.json|yaml` (`DataRedirects`): a map of paths
     to targets (a target may be a map with `to` and `status`), or a list
     of `from`/`to`/`status` maps. Paths are route patterns.
  3. `redirect_from` front matter (`ContentRedirects`): paths or full
     URLs (their path is used) to the entry's URL. Values with `{` or `}`
     are skipped; entries without a URL redirect nowhere.
  - The content provider tags 2 and 3 in `boot()`, which is what puts
    them after the config's.
  - `RefreshRouteCache` rewrites an existing compiled route table (outside
    development) whenever the index changes, so `redirect_from` takes
    effect without `cache:compile`. Data-file redirects still need a
    recompile (or publish, M6).

### D-098: A stale index is rebuilt
- **Date:** 2026-09-25
- **Decision:** `IndexFingerprint` (the index format, content types,
  timezone, and locale) is shared by the indexer and the repository. The
  repository rebuilds the index on first use in any environment when the
  stored index's fingerprint doesn't match, so changing content types in
  production can't serve records of types that no longer exist.
  **Refines D-090.**

### D-099: Media
- **Date:** 2026-09-25
- **Decision:** `Blush\Media`, configured by `MediaConfig`
  (`config/media.php`):
  - `url` (default `/media`) is where `user/media` is served; jtcom sets
    `/user/media` to keep its 1.x URLs. `types` is the MIME allowlist,
    1.x's images, audio, and video by default.
  - `MediaResolver` turns a reference into a `MediaFile` (path, URL,
    MIME, size, and raster dimensions): a path under the media URL, or
    under `user/media`'s own site path (1.x's `/user/media/...`), is in
    `user/media`; a relative path is a page bundle file next to the
    entry, served at `{url}/_content/{content path}`. Files must exist,
    be allowed, not be hidden, and stay in their root. SVGs are
    recognized by extension when they sniff as XML or text.
  - `MediaController` (the `media` route, `{url}/{path:.+}`, at `System`
    priority) streams files with `X-Content-Type-Options: nosniff`, and
    SVGs with `Content-Security-Policy: sandbox`.
  - `Response::file()` takes the `Range` header: one range gives a 206
    over a `LimitedStream`; a range starting past the end gives a 416;
    several ranges or a malformed header give the whole file (RFC 9110
    allows it). Always `Accept-Ranges: bytes`.
  - `media:publish` links `{public}{url}` to `user/media` with a relative
    symlink; `--copy` copies allowed files that changed instead (for
    hosts without symlinks). Linking exposes the whole folder, so only
    `index.php` may run as PHP in `public/` (D-072). Bundle files aren't
    published; the controller (and static export) serves them.

### D-100: 1.x Markdown rendering
- **Date:** 2026-09-25
- **Decision:** The CommonMark adapter adds 1.x's renderers (D-078):
  - `ResolveLinks` (a `DocumentParsedEvent` listener, after the
    attributes extension): links and images to local media point at the
    media URL, and images get `width` and `height` from the file unless
    set. With `MarkdownConfig::$absoluteLinks` (default on), root-relative
    URLs become absolute.
  - `FigureRenderer`: a paragraph holding only an image (or a link
    around one) renders as a `<figure>`; the title is the escaped
    `<figcaption>`, and the image's (or else the link's) attributes move
    to the figure, except `<img>` ones such as `src` and `srcset`.
    `MarkdownConfig::$figures` (default on) turns it off. **Change from
    1.x:** inline images stay plain `<img>` elements (1.x wrapped every
    image in a `<figure>`, even inside a paragraph).
  - `MarkdownParser::toHtml($markdown, $base)` takes the entry's folder,
    for bundle media; entry bodies pass it.

### D-101: PHPBench baselines
- **Date:** 2026-09-25
- **Decision:** Implements D-044's benchmark suite:
  - `phpbench/phpbench` ^1.7 is a dev dependency; benchmarks live in
    `benchmarks/` (`Blush\Benchmarks\`, analysed and linted with the
    rest) and run with `composer bench`. `phpbench.json` enables opcache
    in the CLI with `file_update_protection` off, so the index file is
    measured as production serves it.
  - `JtcomSizedSite` generates, once per version and deterministically,
    a site in the system temp folder with jtcom's seven types and 1,183
    files (940 posts over 23 years).
  - Baselines are recorded in `roadmap.md`. Gating CI on regressions is
    still open, since CI machines differ from the author's.

### D-102: M5 ships in three slices
- **Date:** 2026-09-25
- **Decision:** Like M4 (D-079), each slice ends with `composer check`
  passing, for review:
  - **M5a:** the view engine (template API, layouts, sections,
    partials), the template hierarchy, `Head`, the escaping helpers, the
    translator, theme manifests and parent chains, the framework default
    theme, themed content, error, and welcome pages, the theme asset
    route, `layout`/`class` front matter, and `?theme=` in development.
  - **M5b:** components and slots, Markdown directives and the core
    content components, context providers, theme providers and
    autoloading, Composer-installed themes, settings, DTCG tokens,
    `stylesheet`/`tokens` front matter, asset manifests and
    `theme:publish`, and the `theme:*` commands.
  - **M5c:** feeds, sitemaps, and `robots.txt`.

### D-103: The view engine
- **Date:** 2026-09-25
- **Decision:** Implements D-009 and D-025 in `Blush\View`:
  - `Views` renders plain PHP templates for one theme chain. A template
    runs in a closure bound to its `Template` with **no class scope**, so
    `$this` exposes only `Template`'s public API; its data become
    variables (`extract`, skipping `this`, `__file`, and `__data`).
  - A template's `layout('base', ...$data)` (`layouts/{name}` unless the
    name has a folder) renders after it, with the template's output as
    the `content` section and the data plus the layout's own. Layouts
    may have layouts. `start()`/`stop()` capture sections; sections, the
    `Head`, the shared data (`$site`), and the `<body>` classes live in a
    per-render `ViewContext`.
  - `insert('parts/x', ...$data)` renders a partial with the shared data
    plus what it's given, not the caller's variables. Data is always
    passed by name.
  - Any exception in a template closes the output buffers it opened and
    is rethrown as a `ViewException` naming the file; so is a section
    left open. `ViewNotFound` is thrown when no candidate exists.
  - View names are `/`-separated segments of letters, digits, `_`, and
    `-`, so front matter can't name files outside the view folders.
  - `ViewFinder` searches, in order: `resources/views/themes/{active}`,
    `resources/views`, then each theme's `views/` in the chain.
  - The template API adds, beyond `theming.md`'s list: `hasSection()`,
    `permalink($entry)`, `route($name, $params)`, `terms($entry,
    $taxonomy)` (published term entries), `date($date, $format)`
    (`IntlDateFormatter`, site locale and timezone), and `bodyClass()`.
    `component()`, `setting()`, `token()`, and `image()` arrive in M5b.

### D-104: Template hierarchy uses 2.x names only
- **Date:** 2026-09-25
- **Decision:** `View\Hierarchy` builds the candidates in `theming.md`,
  with an entry's `template` front matter (1.x's `view`) always first.
  1.x's view names (`collection-datetime`, `collection-taxonomy`,
  `collection-home`, `single-home`, `index`) are **not** candidates;
  views are theme code, not content, so D-078 doesn't cover them, and
  jtcom's views are renamed in the M8 port.
  - The home page tries `home`, then the hierarchy of what it shows:
    `ContentPage::$base` is `Collection` (the home type) or `Page`
    (`index.md`).
  - A new `PageKind::Welcome` renders `welcome`.

### D-105: Themes (M5a)
- **Date:** 2026-09-25
- **Decision:** Implements the core of D-021, D-024, D-034, and D-035 in
  `Blush\Theme`:
  - A theme is a folder in `user/themes/{slug}` with `theme.json` (or
    `.yaml`/`.yml`, JSON wins, D-032). Only `name` is required;
    `parent`, `version`, `description`, `styles` (default
    `["style.css"]`), and `scripts` are read, and the whole manifest is
    kept for later keys. Slugs are `[a-z0-9][a-z0-9_-]*`.
  - The framework default theme is `resources/themes/default`, slug
    `default`. It's always installed, always last in a chain, and a site
    folder named `default` can't replace it.
  - `ThemeConfig` (`config/theme.php`) names the active theme (default
    `default`). `Themes::chain()` builds the chain, failing on a missing
    theme or ancestor and on loops. `ThemeResolver` picks the chain per
    request: `?theme={slug}` in development only, ignored when unknown.
  - **Stylesheets:** a page loads the *active* theme's `styles` and
    `scripts`, each resolved through the chain (so a child's missing
    `style.css` falls back to its parent's). Ancestors' own lists aren't
    loaded automatically; a child lists what it wants.
  - **Assets** are served by the `theme.asset` system route,
    `/themes/{slug}/{path}`, from any installed theme's folder: only
    allowed extensions (CSS, JS, source maps, fonts, images), never under
    `views/`, `lang/`, `src/`, `vendor/`, or `node_modules/`, and no
    dot-segments. SVGs are sandboxed. URLs are versioned by mtime
    (`?v=`). Publishing and `manifest.json` versioning come in M5b.
  - Manifests are read per request (a few small files). Compiling them,
    theme providers, and Composer-installed themes come in M5b.

### D-106: The escaping helpers are global functions
- **Date:** 2026-09-25
- **Decision:** `e()`, `attr()`, `url()`, `js()`, `css()`, and `raw()` are
  defined in `src/View/functions.php`, loaded by Composer's `files`
  autoload, and delegate to `View\Escaper`. They are the only global
  functions (architecture principles). They aren't wrapped in
  `function_exists()`: a conflicting definition fails loudly instead of
  silently changing how output is escaped.
  - `null` and `false` print as `''`. `url()` passes relative URLs and a
    scheme allowlist (`http`, `https`, `mailto`, `tel`, …), ignoring
    control characters and whitespace when reading the scheme, and
    returns `''` for anything else (`javascript:`, `data:`). `js()` is
    JSON with `<`, `>`, `&`, and quotes hex-escaped. `css()` hex-escapes
    everything but ASCII letters and digits.

### D-107: The translator (M5a)
- **Date:** 2026-09-25
- **Decision:** Implements D-028's core in `Blush\Translation\Translator`:
  - ICU MessageFormat through `MessageFormatter`, with named
    parameters. Messages without `{` skip ICU.
  - Catalogs are data files named by locale (`en_US.json`, `en.yaml`) in
    an ordered list of directories per domain; the first directory with
    a key wins, key by key. Nested objects flatten into dotted keys.
  - Locale fallback: the locale, its language, then the site locale
    and its language. A missing key returns the key, formatted.
  - Domains so far: `blush` (`resources/lang` in the framework) and
    `theme` (each chain theme's `lang/`, via `withDirectories()` in
    `ViewFactory`). `$this->t()` reads `theme`. Site and extension
    domains, and `DateFormatter`/`NumberFormatter` services, come when
    something needs them.

### D-108: Themed error pages and the welcome page
- **Date:** 2026-09-25
- **Decision:**
  - `Http\ErrorPages` is the seam: `HandleErrors` asks it for the
    response first, and falls back to the generic `ExceptionRenderer`
    page when it returns `null` or throws (the failure is reported).
    Error headers and `Cache-Control: no-store` are always applied.
  - `View\ThemedErrorPages` renders `error-{status}` → `error`, with
    `<meta name="robots" content="noindex">`. It uses the site's
    published entry `user/content/_errors/{status}.md`, falling back to
    1.x's `_error/{status}.md` (the author's call), for the title and
    body; without one, the theme's messages (`error.{status}.title`,
    `error.{status}.message`). With debug on, server errors aren't
    themed, so the detailed page shows; HTTP errors are, with the
    message available to the template.
  - **Empty state (resolves the open question):** a site with no
    `index.md` and no home type renders the theme's `welcome` view.
    `WelcomeHandler` and `BasicPageRenderer` are removed; the view layer
    binds `PageRenderer` to `ThemedPageRenderer`.

### D-109: Page head, body classes, and presentation front matter
- **Date:** 2026-09-25
- **Decision:**
  - `View\Head` keys every item (`meta:{name}`, `property:{name}`,
    `link:{rel}:{href}`, `link:canonical`, `style:{href}`,
    `script:{src}`), so each prints once, in first-added order, with the
    last value. Scripts are deferred unless given a `type`. The title is
    `{page} | {site}`, or the site name alone.
  - `ThemedPageRenderer` sets the title (none on the home and welcome
    pages), the canonical URL (the request path on the site origin),
    `og:site_name`, `og:title`, `og:type` (`article` for singles), and
    `og:url`, plus `rel=prev`/`next` links for paged listings.
  - `<body>` classes: the entry's `class` front matter, `is-{kind}`,
    `type-{type}`, `is-paged`, and on errors `is-error` and
    `is-error-{status}`. Invalid class names are dropped.
  - `layout` front matter replaces the layout the page's own template
    asks for, if that layout exists. `stylesheet` and `tokens` come in
    M5b (D-027).

### D-110: The framework default theme
- **Date:** 2026-09-25
- **Decision:** `resources/themes/default` (D-045: plain CSS, no build
  step; D-030: WCAG 2.2 AA): `layouts/base` (`lang`, skip link,
  `<header>`, `<main id="main">`, `<footer>` landmarks), `single`,
  `collection` (every listing kind), `error`, `welcome`, and the parts
  `header`, `footer`, `entries`, `entry-summary` (linked title, byline,
  excerpt), `entry-meta` (date and term links), and `pagination`. The
  stylesheet uses CSS custom properties with a `prefers-color-scheme`
  dark palette; they become compiled DTCG tokens in M5b. Strings are in
  `lang/en.json`. Templates are checked with `php -l`; PHPCS covers
  `src`, `tests`, and `benchmarks` only.

### D-111: Components
- **Date:** 2026-09-25
- **Decision:** Implements D-025 in `Blush\View\Component`:
  - A **template-only** component is `components/{key}.php` in the view
    chain. Its template gets the props as variables, all of them as
    `$props` (for names that aren't valid variables, such as `data-n`),
    the default slot as `$slot`, and named slots as `$slots` (`Slots`:
    `$slots->footer` is `''` when unfilled, `isset()` tells).
  - A **class-backed** component extends `Component` (typed props by
    constructor promotion). It's built through the container
    (`ComponentFactory`), so it can ask for services; string props are
    cast to `int`, `float`, or `bool` parameters, and props the
    constructor doesn't take stay in `$props`. `data()` (public
    properties by default) feeds the template, plus `$component`;
    `template()` can pick another view and `shouldRender()` can skip it.
  - Type enum + registry + factory + registrar (D-019):
    `ComponentType` (built-ins: `embed`), `ComponentRegistry`,
    `ComponentFactory`, `ComponentRegistrar`. Providers `register()` a
    class by key (overwriting); templates override by key through the
    chain, site views first.
  - `$this->component('card', title: 'Hi')` returns a
    `PendingComponent` that renders when printed, after `->content()`
    and `->slot($name, $html)`. Components share the page's
    `ViewContext`, so they can add to the `Head`.

### D-112: Markdown directives
- **Date:** 2026-09-25
- **Decision:** Implements D-026 as an in-house CommonMark extension
  (`Markdown\CommonMark\Directive`), on by default
  (`MarkdownConfig::$directives`):
  - Container `:::name[label]{attrs}` … `:::` (three or more colons;
    closed by a fence at least as long, or the end of the document;
    nest by giving the outer fence more colons). Leaf `::name[label]{attrs}`
    on its own line (exactly two colons). Inline `:name[text]{attrs}`
    (the `[text]` is required, and the colon can't follow a letter,
    digit, or colon, so URLs and times stay text).
  - Attributes: `key=value`, quoted values, `.class`, `#id`, and bare
    `key` (`"true"`). Labels are plain text (escaped).
  - `Markdown\DirectiveRenderer` is the seam; returning `null` means
    unknown, which renders as plain content (a container's blocks, a
    leaf's label as a paragraph, an inline directive's text).
  - `View\ComponentDirectives` renders a directive as the component of
    the same name with the request's theme chain
    (`ThemeResolver::current()`): attributes are props, the label is
    also the `label` prop, and the content is `$slot`. It gets a bare
    context (its `Head` additions don't reach the page), and resolves
    the view factory lazily (`#[Defer]`), since the factory depends on
    the Markdown parser through the content repository.
  - **Consequence for M6:** rendered bodies depend on the theme chain,
    so the rendered-body cache must key on the active theme too.

### D-113: The core content components
- **Date:** 2026-09-25
- **Decision:** Implements D-033 in the default theme: `callout`
  (`[title]`, `tone` = note, info, tip, warning, danger; an
  `<aside role="note">`), `gallery` (`columns`, 1–6, a CSS grid),
  `figure` (`src`, `alt`, caption from the label), and `embed` (the class
  `View\Component\Embed`: YouTube through `youtube-nocookie.com`, Vimeo
  with `dnt=1`, anything else as a link, so content never frames an
  unknown origin). `figure`'s `src` is used as written (a media URL);
  page-bundle-relative paths aren't resolved yet. Answers the "which
  core components ship first" question in `theming.md`.

### D-114: Context providers
- **Date:** 2026-09-25
- **Decision:** `View\ContextProvider::provide($view, $data)` supplies
  data for views matched by name or `fnmatch()` pattern (`*` stays in a
  folder), registered on `View\ContextProviders` (`add($pattern,
  $provider)`) in a theme or site provider. Providers apply to every
  view rendered by name (pages, layouts, partials, components). Their
  values are defaults: data given explicitly wins. Class names are
  built through the container on first match and kept.

### D-115: Theme discovery, Composer themes, and the theme cache
- **Date:** 2026-09-25
- **Decision:** Refines D-105.
  - `ThemeDiscovery` runs before the container (theme providers register
    at boot), reading manifests itself (`theme.json`, else `.yaml`/`.yml`).
    It finds the framework `default`, Composer packages of type
    `blush-theme` (slug from `extra.blush.slug`, or the package name
    after `/`), and `user/themes`. A local theme replaces a Composer one
    with the same slug; nothing replaces `default`.
  - A broken manifest is recorded in `Themes::invalid()` instead of
    failing discovery, so one bad folder can't take down the site or the
    CLI. Using such a theme throws its error.
  - Discovery compiles to `storage/cache/themes.php`
    (`CompiledCache::Themes`, `ThemeCache`), read everywhere but
    development; `cache:compile` writes it and `cache:clear --themes`
    deletes it.
  - `Support\ComposerPackages` reads `installed.json` for both
    extensions and themes.

### D-116: Theme providers and autoloading
- **Date:** 2026-09-25
- **Decision:** Implements the theme part of D-054:
  - A manifest may name a `provider` and, for a local theme, an
    `autoload.psr-4` map (folders inside the theme). Only the active
    chain's local themes are autoloaded (by `LocalAutoloader`; Composer
    themes are Composer's).
  - The chain's providers register ancestors first, after extensions'
    and before the site's. A chain that doesn't resolve registers none,
    and a provider that isn't a `ServiceProvider` is skipped; both are
    reported by `theme:check` (and a broken chain by rendering).
  - `?theme=` (D-035) doesn't register the other theme's providers.
  - D-020's limits (no routes, content types, or commands from themes)
    are a documented rule, not enforced.

### D-117: Theme settings
- **Date:** 2026-09-25
- **Decision:** Implements the settings part of D-022:
  - A manifest's `settings` map names to field definitions with the
    content field types (`type`, `default`, `label`, `options`, …).
    Definitions merge down the chain (a child's replaces its
    ancestor's). `SettingsResolver` resolves them per chain.
  - Values come from `user/data/theme.json` (`SiteThemeData`):
    `{"settings": {…}, "tokens": {…}}`, applying to whichever theme is
    active. Undeclared values are ignored; a value that doesn't fit
    falls back to the default and is reported (`theme:check`).
  - Templates read `$this->setting('name', $default)`. The default theme
    declares `excerpts` (default on), which turns listing excerpts off,
    and with them the per-entry Markdown rendering (D-110's benchmark
    cost).

### D-118: Design tokens
- **Date:** 2026-09-25
- **Status:** Superseded by D-160.
- **Decision:** Implements D-023 and the `tokens` part of D-027 in
  `Blush\Theme\Token`:
  - `TokenSet` reads DTCG: groups, inherited `$type`, `$value` tokens,
    and (a Blush shorthand for front matter and site data) plain scalar
    leaves. Aliases (`{color.accent}`, alone or inside a value) compile
    to `var(--color-accent)`, so modes flow through aliases. Values:
    strings, numbers, dimensions/durations (`{value, unit}`), font
    family lists, cubic Béziers, colors (`hex` or color-space
    components), shadows (and lists of them), and borders.
  - **Modes** are `$extensions.blush.modes` (or `blush.modes`): `dark`
    compiles to a `prefers-color-scheme: dark` block (unless
    `data-scheme="light"`) plus `:root[data-scheme="dark"]`; any other
    mode to `:root[data-scheme="{mode}"]`.
  - **Merge order:** default → ancestors → theme (`tokens.json|yaml`) →
    `user/data/theme.json` tokens → entry `tokens` front matter. An
    override replaces a token in every mode unless it gives its own
    modes. The entry's set is printed as a second block
    (`blush-entry-tokens`) with `over()`, which repeats its values in
    the base's modes so it wins there too.
  - Values that could escape a declaration (`;`, `{`, `}`, `<`, `>`,
    `\`, line breaks) are dropped and reported, since tokens also come
    from site data and front matter.
  - The CSS is inlined in the head (`<style id="blush-tokens">`), not
    served as a file, and built once per chain per process. Caching it
    per content version is M6's.
  - `$this->token('color.accent', $mode)` returns the concrete value
    (aliases followed). The default theme's colors, fonts, spacing,
    measure, and radius are tokens.

### D-119: Theme assets: build manifests, `stylesheet`, and publishing
- **Date:** 2026-09-25
- **Status:** Partially superseded by D-194 (versions are content
  hashes, not mtimes, and built files are versioned too).
- **Decision:** Implements D-031 and the rest of D-034:
  - `ThemeAssets` (per chain) resolves `asset()` and the manifest's
    `styles`/`scripts`: for each theme, nearest first, a Vite-style
    manifest (`dist/.vite/manifest.json` or `dist/manifest.json`) entry
    gives its hashed file (and its `css` list, added as stylesheets;
    built scripts load as `type="module"`); otherwise the file, versioned
    by mtime. Replaces M5a's `ThemeChain::assetUrl()`.
  - `stylesheet` front matter: an absolute or root-relative URL is used
    as is; anything else is a theme asset through the chain.
  - `theme:publish` copies servable files only (never links, since a
    link would expose `views/` and PHP) to `public/themes/{slug}`,
    recopies changed files, and removes published files whose source is
    gone. The active chain by default, `--all` for every theme.

### D-120: The `theme:*` commands
- **Date:** 2026-09-25
- **Decision:** `theme:list`, `theme:activate <slug>`, `theme:new <slug>
  [--parent] [--name]`, `theme:check [slug] [--strict]`, `theme:why
  <view> [--theme]`, and `theme:publish [--all]` (see `cli.md`).
  `theme:activate` writes `config/theme.php` (developer config, D-039):
  it creates the file, or edits a single plain `active` string literal,
  and otherwise refuses with instructions, so hand-written config is
  never mangled. It clears the compiled config and theme caches.

### D-121: `theme:check`
- **Date:** 2026-09-25
- **Status:** Partially superseded by D-160 (no token or contrast checks).
- **Decision:** Implements D-030's checks in `Theme\ThemeChecker`
  (`ThemeReport` of `Violation`s):
  - **Errors:** an unresolvable chain; a provider that isn't a service
    provider; invalid settings definitions or tokens; text contrast
    below WCAG AA (4.5:1) in any mode; a base layout (the rendered
    `welcome` page) without `lang` on `<html>`, exactly one `<main>`, or
    a first in-page link that skips to the main content.
  - **Warnings:** shadowed manifests; site setting values that don't
    fit; tokens that don't compile or resolve; other broken themes; no
    `<header>`/`<footer>` landmark (outside sectioning content); other
    than one `<h1>`.
  - **Notices** (`--strict`): colors whose contrast can't be measured
    (formats other than hex and `rgb()`), and `requires`, which isn't
    enforced yet.
  - Contrast pairs are `[foreground, background]` token paths from the
    nearest theme's `contrast` list; the default theme checks text,
    muted, and accent on the background, and text on the surface.

### D-122: Feeds
- **Date:** 2026-09-25
- **Decision:** Implements the feed part of D-029 in `Blush\Feed`:
  - **Formats:** RSS 2.0, Atom, and JSON Feed 1.1 (`FeedFormat`), each
    turned on or off by `FeedConfig` (`config/feed.php`: `formats`,
    `content` for full bodies or excerpts only, and `limit`, default
    10 as in 1.x).
  - **Routes** (`FeedRoutes`, `Content` priority), for every public,
    routed type with a `feed`, with 1.x's names and paths (D-078):
    `{type}.collection.feed` (`{prefix}/feed`), `.feed.atom`
    (`feed/atom`), and the new `.feed.json` (`feed/json`); the home
    type's are `home.feed`, `home.feed.atom`, and `home.feed.json` at the
    root. A taxonomy with a feed also gets a feed per term
    (`{type}.single.feed`, `{name}/feed`, and the Atom and JSON
    variants), which covers per-author feeds (D-043) when the author type
    has `feed`. `TypeRouting` gained those default paths, so a type can
    move them.
  - **Contents** (`FeedBuilder`): 1.x's feed arguments (the type, or its
    `collect` type; newest file first; then `feed.collection`); a term's
    feed lists what its archive lists. The home feed is titled with the
    site name, others `{title} | {site}`. Items carry absolute URLs,
    published and updated dates, the body (with `content` on), the
    excerpt, author term titles, and categories: the terms of
    `feed.taxonomy` (1.x), or of every taxonomy but authors. An empty
    feed is still a 200.
  - **Templates:** `feed-{format}-{type}` → `feed-{format}` in the default
    theme, rendered by `View\DocumentRenderer` (no layout). Content is
    escaped rather than wrapped in CDATA, so no extra global helper is
    needed.
  - **Discovery:** every page links the home feed, plus its own
    type's or term's feeds, with `<link rel="alternate">` (`FeedLinks`).

### D-123: Sitemaps and `robots.txt`
- **Date:** 2026-09-25
- **Decision:** Implements the rest of D-029 in `Blush\Sitemap`:
  - **Routes** (`System` priority): `sitemap` at `/sitemap` (1.x's
    path; `/sitemap.xml` answers too), `sitemap.type` at
    `/sitemap/{type}`, and `robots` at `/robots.txt`. `SitemapConfig`
    (`config/sitemap.php`: `enabled`, `disallow`, `robots`) replaces
    1.x's `app.sitemap`; each type's `sitemap` option still decides
    whether it's included. The route names differ from 1.x's
    (`sitemapindex`, `sitemap`); the URLs don't.
  - **Contents** (`SitemapBuilder`): one sitemap per public type with
    `sitemap` on, holding its collection page (only when it has a
    landing page or lists something), then its listed entries with URLs
    and `updated` as `lastmod`. A taxonomy's holds its terms that list
    entries, by slug. The root `index.md`'s type holds `/` when the home
    isn't a type's collection. The index lists each type's sitemap with
    its latest change, leaving out empty ones. Sitemaps aren't split at
    50,000 URLs yet.
  - **Templates:** `sitemap-index` and `sitemap-{type}` → `sitemap`.
  - **`robots.txt`:** `SitemapConfig::$robots` as written; otherwise, in
    production, allow everything but `disallow` and point to the
    sitemap; in any other environment, `Disallow: /`. A real
    `public/robots.txt` file wins, since the web server serves it.

### D-124: M5's exit criterion is a route-coverage test
- **Date:** 2026-09-25
- **Decision:** "The default theme renders every route type" is checked
  by `tests/View/DefaultThemeTest`: it builds a jtcom-shaped site (every
  date and time archive level, paged listings, terms, authors, feeds,
  pages, media, theme assets, sitemaps, and `robots.txt`), requires a
  sample URL for every named route in the compiled table (so a new
  route type fails the test until it's covered), and checks each
  response: HTML pages have `lang`, one `<main>`, one `<h1>`, and the
  skip link; feeds and sitemaps are well-formed XML; JSON feeds decode.
  Errors and the welcome page are checked too.

### D-125: No XSL stylesheets for feeds or sitemaps
- **Date:** 2026-09-25
- **Decision:** Blush won't rely on XSLT (`<?xml-stylesheet type="text/xsl"?>`)
  to make feeds or sitemaps readable in a browser, because major browsers
  are removing XSLT support. jtcom's 1.x feed stylesheet (`xsl/feed.xsl`)
  isn't carried over in the M8 port. A replacement is an open question
  (`open-questions.md`).

### D-126: M6 ships in two slices
- **Date:** 2026-09-25
- **Decision:** Like M4 and M5 (D-079, D-102), each slice ends with
  `composer check` passing, for review:
  - **M6a (caching):** the PSR-16 store and drivers, the content
    version, the page cache, conditional GETs, cached bodies, excerpts,
    and token CSS, and `cache:clear`/`cache:compile` clearing the store.
  - **M6b (publishing):** the publisher (optional `git pull`, reindex,
    route and content-type caches, version bump, store cleanup), the
    `publish` command, the signed webhook, `schedule:run`, and the
    `ContentPublished` event.

### D-127: The cache store
- **Date:** 2026-09-25
- **Decision:** Implements the architecture's store in `Blush\Cache`:
  - `Store` is an abstract, in-house PSR-16 base (`psr/simple-cache`
    ^3.0, D-006) for one **namespace**; drivers implement read, write,
    remove, and flush, and it adds key checks (PSR-16's reserved
    characters), TTLs as expiry times from the PSR-20 clock, the
    multiple-key methods, `remember()`, and `prune()`.
  - **Values are plain data** (scalars, `null`, arrays of them). Objects
    and resources are an `InvalidCacheValue`, so the file driver never
    unserializes an object (`allowed_classes: false`).
  - **Drivers** (enum + registry + factory + registrar, D-019):
    `file` (the default; one file per entry under
    `storage/cache/store/{namespace}`, sharded by hash, expiry on the
    first line), `php` (`PhpArrayFile` per entry, in opcache; for small
    hot values), `apcu` (prefixed by site and namespace; needs the
    extension), `array`, and `null`. The factory builds a driver through
    the container with its `$namespace`.
  - **Namespaces** (`CacheNamespace`): `pages`, `bodies`, `tokens`,
    `fragments`; extensions may use others.
  - **`CacheConfig`** (`config/cache.php`): `enabled` (`null` means on
    everywhere but development), `driver`, `stores` (a driver per
    namespace), `pages`, and `maxAge`.
  - **`Caches`** hands out stores (a `NullStore` when caching is off, so
    callers never check), `persistent()` stores that ignore `enabled`,
    and `clear()`/`prune()` over the framework's, the configured, and
    the used namespaces.
  - Tagged invalidation and the web-server-served page files stay
    later.

### D-128: The content version
- **Date:** 2026-09-25
- **Decision:** `Cache\ContentVersion` is one random value in
  `storage/cache/content-version.json` (JSON, not a PHP file, so a CLI
  write is seen by the web server without opcache invalidation), with
  the index's next scheduled time (`IndexSnapshot::nextScheduled()`).
  - It moves on (`bump()`) when the index is stored (`ContentIndexed`),
    on `cache:clear` and `cache:compile`, and (M6b) on publish.
  - When the scheduled time passes, the next read moves it to a hash of
    the old version and that time, so concurrent requests agree, and
    looks up the following scheduled time in the index. No cron needed.
  - A missing or damaged file gets a new version.
  - Every derived cache keys on it, so invalidation is one write; the
    stale entries are deleted by `cache:clear` (and publish).
  - **`cache:clear`** also clears the store and bumps the version;
    `--store` does only that, and any compiled-cache flag skips it.
    **`cache:compile`** clears the store and bumps the version too,
    since it's the deploy step and a deploy can change templates.
  - **Consequence:** in production, template, config, and site-data
    (`user/data`) changes reach cached pages only after `cache:clear`,
    `cache:compile`, or publish.

### D-129: The page cache and conditional GETs
- **Date:** 2026-09-25
- **Decision:**
  - The kernel runs `HandleErrors`, then middleware tagged
    `Kernel::MIDDLEWARE` in tag order, then `HttpConfig::$middleware`.
    The framework tags `Http\Middleware\ConditionalGet` (HTTP provider)
    and then `Cache\PageCache` (cache provider, registered after it).
  - **`PageCache`** (on when caching is and `CacheConfig::$pages`)
    stores whole responses in `pages`, keyed by the content version and
    the path. Only `GET`/`HEAD` without a query string or
    `Authorization`, answered 200, with no `Set-Cookie`, no
    `private`/`no-store`/`no-cache`, no `Vary: *`, no byte ranges
    (files), and at most 2 MB. Feeds, sitemaps, and `robots.txt` are
    cached too; errors and redirects aren't. Stored pages get
    `Cache-Control: public, max-age={maxAge}` (`0` adds
    `must-revalidate`) unless they set one. `X-Page-Cache: hit|miss`
    (no product name in the header).
  - **`ConditionalGet`** gives 200s a strong `ETag` (xxh128 of the body)
    unless they have one or are files (which have `Last-Modified`), and
    answers `If-None-Match` (weak comparison, `*`) or else
    `If-Modified-Since` with a 304 that keeps the caching headers.
    It's on in every environment.

### D-130: Rendered bodies, excerpts, and token CSS are cached
- **Date:** 2026-09-25
- **Status:** Partially superseded by D-160 (no token CSS).
- **Decision:**
  - `Content\Entry\BodyCache` is the seam; `Cache\RenderedBodies` (bound
    by the cache provider) keeps bodies, summaries, and 1.x word
    excerpts in `bodies`, through `Cache\ContentCache` (a value per
    content version).
  - Keys: the content version, the request's theme (D-112), a
    fingerprint of the rendering settings (framework version, site URL,
    Markdown and media config), and the body's own key (the record's
    content hash; a hash of the Markdown for summaries).
  - **The content version is in the key** (the architecture said only
    "content hash + renderer version"), because a directive's component
    may read other content or site data. A publish re-renders bodies
    lazily.
  - `Body` now holds a lazy `BodySource` ghost, so a cached body never
    reads its file; word excerpts moved from `Entry` into `Body`.
  - `ViewFactory` keeps each chain's compiled token CSS in `tokens`
    per content version (site tokens are site data).
  - Benchmarks (D-101): a themed home page drops from 8.5 ms to 1.7 ms
    with bodies cached, and a page cache hit takes 0.05 ms.

### D-131: The publisher and `publish`
- **Date:** 2026-09-25
- **Decision:** Implements stage 1 of D-013 in `Blush\Publish`:
  - `Publisher::publish(?bool $pull)` is what the CLI, the webhook, and
    later the admin all run: an optional pull of `user/` (a failed pull
    stops the publish with nothing changed); outside development, the
    compiled content types and route table rewritten if they exist (so
    `user/data/types` and data-file redirects take effect, D-097; a
    changed type set makes the next request rebuild the index, D-098);
    an incremental reindex; the cache store cleared and pruned; a new
    content version; then `ContentPublished` with the `PublishReport`.
  - One publish at a time: an `flock` on `storage/cache/publish.lock`
    (`PublishInProgress` otherwise).
  - **`Puller`** is the seam for bringing files up to date; `GitPuller`
    runs `git -C user pull --ff-only [remote [branch]]` as an argument
    list, with terminal prompts off and SSH in batch mode (so a webhook
    can't hang on a credential), its output captured, and a clear
    failure when `proc_open()` is disabled (shared hosts).
  - **`PublishConfig`** (`config/publish.php`, or `fromEnv()` from
    `PUBLISH_SECRET`, `PUBLISH_GIT`, `PUBLISH_REMOTE`, `PUBLISH_BRANCH`):
    `secret` (at least 32 characters), `git`, `remote`, `branch` (plain
    git names only, so they can't become options), `path` (default
    `/_blush/publish`, built from `Framework::BINARY`), `tolerance`
    (300 s), and `gitBinary`.
  - **`publish [--pull] [--no-pull]`** overrides `git` either way, and
    fails when the pull or any file's indexing does.

### D-132: The publish webhook
- **Date:** 2026-09-25
- **Decision:**
  - `POST {PublishConfig::$path}` (`publish.webhook`, a system route)
    exists only when a secret is set.
  - **Signature** (`WebhookSignature`): `X-Publish-Signature:
    sha256={HMAC-SHA256 of "{timestamp}.{raw body}"}` with
    `X-Publish-Timestamp` (Unix seconds), compared with `hash_equals()`.
    Header names are neutral (no product name).
  - **Replay protection:** a request outside `tolerance` seconds is
    refused, and each accepted signature is kept (hashed) in the
    `webhooks` namespace for twice that. `webhooks` is a persistent
    namespace: `Caches::clear()` (so `cache:clear` and every publish)
    never clears it, and it's kept even when caching is off.
  - **Body:** empty or a JSON object; `{"pull": bool}` overrides `git`.
  - **Answers** (JSON, `Cache-Control: no-store`): 200 with the report,
    500 when the pull failed, 401 for a bad, missing, or stale
    signature, 409 for a replay or a publish already running, 400 for
    another body.
  - Rate limiting waits for the admin's middleware (M9); the signature
    already gates any work.

### D-133: `schedule:run`
- **Date:** 2026-09-25
- **Decision:** `schedule:run` reads the content version (which moves it
  on if a go-live time has passed, D-128), reports the next go-live
  time, and prunes expired cache entries. Requests handle go-live by
  themselves; the command is the optional cron entry (D-040) for a go-live
  on time on a quiet site, and for pruning.

### D-134: M7 ships in two slices
- **Date:** 2026-09-26
- **Decision:** Like M4 to M6 (D-079, D-102, D-126), each slice ends
  with `composer check` passing, for review:
  - **M7a (export):** the export application, URL sources, the crawler,
    the output layout, assets, the manifest (unchanged files kept,
    stale ones removed), `build [--base-url] [--no-crawl]`, and
    `serve --static`.
  - **M7b (incremental and hosts):** `build --incremental` (skip
    rendering when the content version and the site's templates,
    config, and data are unchanged), redirects in the export, host
    files (`.htaccess`, `_redirects`, `_headers`), and the exit
    criterion checked against jtcom.

### D-135: Static export renders with a production export application
- **Date:** 2026-09-26
- **Decision:** `Export\ExportSite` boots a second application from the
  site's `Bootstrap` for every export, rather than rendering through the
  command's own application:
  - **Production, always.** `AppConfig` gets the production environment
    whatever the site runs in, so drafts, `?theme=`, development's
    auto-indexing, and development's `Disallow: /` `robots.txt` never
    reach the output.
  - **The export's origin.** `AppConfig::$url` becomes the export URL
    (`build --base-url`, then `ExportConfig::$url`, then the site's
    origin), so every absolute URL (canonical links, feeds, sitemaps,
    Markdown's absolute links) uses it. Only an origin works; a path
    would need subdirectory support (open question).
  - **Caching in memory.** `CacheConfig` is on with the `array` driver
    and no page cache: a body rendered for its page is reused by the
    listings and feeds that show it, and the site's own store isn't
    touched.
  - **Fresh compiled state.** Its cache path is `storage/cache/export`,
    which nothing compiles into, so config, routes, content types,
    themes, extensions, and container plans are read from the site's
    files, never a stale `cache:compile`.
  - It shares the site's clock, so both agree on what's scheduled.
  - `Bootstrap` gained `withConfig(Config ...)` (objects that replace
    the loaded or compiled config of their class) and `withPaths()`.

### D-136: Export URLs come from sources, paging, and crawling
- **Date:** 2026-09-26
- **Decision:** `Export\Crawler` (in the export application) renders
  every URL through `Kernel::handle()`:
  - **Sources:** `Export\UrlSource`s tagged `UrlSource::TAG`, like route
    sources. The framework's are `Content\Routing\ContentExportUrls`
    (the home page, collections, terms with files or listed entries,
    every date archive level of every period a listed entry was
    published in, and every published entry with a URL, unlisted ones
    included), `Feed\FeedExportUrls` (collection feeds, and per-term
    feeds of terms with listed entries, in every configured format),
    and `Sitemap\SitemapExportUrls` (`robots.txt`, `/sitemap`,
    `/sitemap.xml`, and each sitemap the index lists). Plus
    `ExportConfig::$paths`.
  - **Paging by asking:** a listing's `ExportUrl` carries a closure for
    its later pages, and page N+1 is requested only after page N
    answers 200, so the page count is always the controller's, without
    repeating its queries. The 404 that ends the paging isn't reported.
  - **Crawling** (`ExportConfig::$crawl`, on by default; `build
    --no-crawl`): links on exported HTML pages (`<a>`, `<area>`, and
    `<link>` with `rel` `alternate`, `canonical`, `next`, or `prev`) on
    the export's origin are queued, and so are internal redirect
    targets. It finds pages no source lists (an extension's, or a
    config route's) and reports broken links with the page linking to
    them.
  - Each path is rendered once. Paths under the media URL and
    `/themes/` are never requested (their files are copied), and
    `ExportConfig::$exclude` globs are skipped.
  - **Outcomes:** 200s are written; redirects are recorded (written in
    M7b); a 4xx is "skipped" when a source listed it (an empty date
    archive) or "broken" when a page linked to it; anything else is a
    failure, which fails `build`.
  - Checked on jtcom's content: 2,795 pages, 4,261 media files, no
    failures, in 12 s; the 114 broken links are real dead links in old
    posts (input for M8's redirect map). The generated benchmark site
    exports 2,920 pages in about 9 s at 34 MB peak.

### D-137: The export's layout, assets, and manifest
- **Date:** 2026-09-26
- **Decision:**
  - **Layout** (`Export\ExportLayout`): a URL whose last segment has an
    extension its content type uses is a file (`/robots.txt`,
    `/sitemap.xml`); any other URL is a folder with an index file named
    for its content type: `index.html`, `.rss`, `.atom`, `.json`,
    `.xml`, or `.txt` (`/feed` → `feed/index.rss`). URLs keep their
    exact form (no `.html`, no forced trailing slash), and a host
    learns the content type from the index's extension; the lookup
    order is `ExportLayout::INDEXES`. A content type with no known
    extension at an extensionless path is a failure. The 404 page is
    `404.html`.
  - **Assets** (`Export\ExportAssets`), at the URLs the live site serves
    them from: `public/`'s files first (except PHP, dotfiles, symlinks,
    and the published theme and media folders), so a real file such as
    `public/robots.txt` wins over a rendered URL, as it does live; the
    active chain's servable theme files at `/themes/{slug}/…`; and what
    the media route would serve, resolved by `MediaResolver`: allowed
    files in `user/media` at the media URL and page bundle files at
    `{media URL}/_content/…`.
  - **Writing** (`Export\ExportWriter`): the first write of a file in a
    run claims it. Unchanged files are left alone (rendered ones by
    xxh128, copied ones by size and mtime), so deploy tools that sync by
    mtime or checksum only move what changed. Rendered files are
    written atomically.
  - **Manifest** (`Export\ExportManifest`, `storage/cache/export/manifest.json`,
    outside the output so it's never deployed): the output folder,
    origin, and each file's fingerprint. The next export removes the
    files the last one wrote and this one didn't (and folders that
    leaves empty); files it never wrote (`.git`, `CNAME`) are never
    touched.
  - **Safety:** the output folder (`Paths::$export`, `storage/export` by
    default) can't be, hold, or sit inside the site's own folders
    (`storage/export` or a folder outside the project is fine). One
    export at a time (`storage/cache/export.lock`).
  - `ExportStarted` and `ExportFinished` are dispatched around it.

### D-138: `build` and `serve --static`
- **Date:** 2026-09-26
- **Decision:**
  - `build [--base-url=] [--no-crawl]` reindexes, exports with a
    progress bar, and prints a summary. Failures (a URL that errors, a
    file that can't be written, a content file that can't be indexed)
    are errors and fail the command; broken links are warnings. `-v`
    lists redirects, skipped URLs, and removed files. `--incremental`
    comes in M7b.
  - `serve --static` previews the export with PHP's built-in server and
    `resources/static-server.php`, which answers as a static host would:
    a file as is, a folder by its first `ExportLayout::INDEXES` file with
    the content type its extension gives, dotfiles never, and anything
    else as `404.html` with a 404. It runs without the framework, so the
    preview shows only what's in the export.
  - `Support\Filesystem::files()` lists a folder's files (skipping
    dotfiles, and optionally symlinks); `theme:publish` and
    `media:publish` use it too.

### D-139: Incremental export, and redirects in the export
- **Date:** 2026-09-26
- **Decision:**
  - **`build --incremental`** keeps the last export's rendered files, and
    renders nothing, when the manifest shows the same output folder,
    origin, content version (D-128, after the reindex), and
    `Export\ExportFingerprint`, and every file it rendered is still in
    place. Otherwise it renders everything, as a full build does. Theme
    assets, media, and `public/` are synced either way. Pages aren't
    re-rendered selectively: a listing, feed, or term count can depend
    on any entry, and a full render is about 10 s for jtcom.
  - **The fingerprint** covers what isn't content, by stat (path, size,
    mtime): the origin, crawl setting, `ExportConfig`, framework
    version, `.env`, `config/`, `user/data`, `user/media` (Markdown
    reads image dimensions), `user/themes`, `user/extensions`,
    `public/`, `resources/`, the site's `src/`, Composer's
    `installed.json`, and the framework's own `src/` and `resources/`.
  - **Redirects:** `Routing\RedirectExportUrls` lists the route table's
    redirects without parameters as export URLs, so rendering confirms
    each one (a redirect applies only before a 404, so a path a page
    answers stays a page). Every redirect the crawl meets (these, and
    canonical ones such as a misdated single) is exported as a literal
    `ExportRedirect`; redirects with parameters are exported as
    patterns, after them. `ExportConfig::$redirectPages` (default on)
    writes a page at each literal path that redirects in the browser
    (meta refresh, `noindex`, canonical link), unless a file has the
    path.
  - Measured on jtcom: an incremental build with nothing changed takes
    about 1.3 s (the media sync and fingerprint), against about 10 s.

### D-140: Host files
- **Date:** 2026-09-26
- **Decision:** An export tells its host what it needs through host
  files, an extensible subsystem (enum + registry + factory +
  registrar, D-019): `Export\Host\HostFormat`, `HostFilesRegistry`,
  `HostFilesFactory`, `HostFilesRegistrar`, and the abstract
  `HostFiles`, which turns a `HostContext` (redirects, non-HTML index
  folders, trailing-slash setting, 404 file) into a `HostOutput` (files
  and notices). `ExportConfig::$hosts` picks formats (default both
  built-ins); an unknown name fails the export. A `public/` file with
  the same name wins, with a notice.
  - **`apache`** (`.htaccess`, for jtcom's shared hosting):
    `DirectoryIndex` with `ExportLayout::INDEXES`; types for `.rss`,
    `.atom`, `.json`, and `.xml` (a host's MIME list may lack them);
    UTF-8; no listings or MultiViews; `ErrorDocument 404`; the host
    files denied. Literal redirects as unconditional anchored
    `RewriteRule`s, patterns (regexes with their constraints, `$n` in
    the target) only where no file or folder exists. Without trailing
    slashes (the default): `DirectorySlash Off`, a 301 from `/about/`
    to `/about`, and folders served by their index file, so URLs keep
    their exact form. Needs `mod_rewrite`.
  - **`netlify`** (`_redirects` and `_headers`, which Cloudflare Pages
    reads too): redirects as `from to status`, a whole-segment
    parameter as `:name` (its constraint dropped) and a trailing
    slash-spanning one as `*`/`:splat`; anything else gets a notice.
    These hosts apply a rule only where no file exists, so a literal
    redirect with a redirect page serves the page (which still
    redirects); turn `redirectPages` off there for true 301s. Folders
    whose index isn't `index.html` are rewritten to it (`/feed
    /feed/index.rss 200`) and get their content type in `_headers`.
    Untested against the real hosts.
  - `serve --static` applies `_redirects` (redirects and 200 rewrites,
    only where no file exists) and never serves the host files.
  - **Exit criterion checked on Apache** (XAMPP's 2.4.53, a private
    instance with `AllowOverride All`, the export at the root): all
    2,798 of jtcom's rendered URLs answer 200 (or 301 at redirected
    paths), redirects and trailing slashes 301, feeds and sitemaps
    carry their content types, unknown paths get the themed 404, and
    the host files are 403s.

### D-141: User documentation in `docs/`
- **Date:** 2026-09-26
- **Decision:** The framework ships user-facing documentation in `docs/`
  (Markdown, in the repo, included in Composer archives): an index
  (`README.md`), installation, writing content, media, content types,
  themes, configuration, going live (publishing, caching, static
  export), the command line, extending, and coming from 1.x. It's for
  people installing and running a site, so it's task-first and plain,
  and it documents only what's implemented (planned features such as
  `image()`, menus, the admin, and `extension:*`/`doctor` are left out
  until they land). `.claude/docs/` stays the design record for
  contributors.
  - Every example was checked against a scratch copy of the dev site
    (types, taxonomies, the home alias, data types and fields,
    redirects, theme settings and tokens, components, overrides, a
    site provider with a controller, command, and class-backed
    component, production publishing, and `build`).
  - **Keep it current:** a change to user-facing behavior (config
    options, front matter, CLI commands, theming API) updates `docs/`
    in the same session, like the `.claude/docs/` files.
- **Why:** the design docs had grown too heavy for someone who just
  wants to install Blush and write; the author asked for a simplified
  guide before M8.

### D-142: M8 approach: a dynamic jtcom, trialed on the skeleton first
- **Date:** 2026-09-26
- **Decision:**
  - **jtcom runs dynamically** (PHP on the host). Static deployment of
    jtcom is tabled; `build` stays as built in M7.
  - **jtcom's theme keeps SCSS.** How a theme's SCSS build fits the
    theming system (D-110 onward) is explored when the theme is ported.
  - **jtcom's port lives on a `2.x` branch** of the jtcom repo.
  - **Trial run first:** before touching jtcom, port as much of what
    jtcom does as possible (its types, archives, controllers, head
    meta, Markdown additions, and views) to a test branch of
    `blush-dev/blush`, running against jtcom's real content. That
    shakes out framework gaps with real data while jtcom stays as it is.
- **Why:** the author wants the port tested separately with real data
  before jtcom itself changes.

### D-143: Skeleton fixes before M8
- **Date:** 2026-09-26
- **Decision:**
  - The framework requires `ext-intl` (`MessageFormatter`,
    `IntlDateFormatter`) and `ext-mbstring` in `composer.json`; the
    installation docs list both.
  - `blush-dev/blush`'s `config/app.php` passes
    `locale: $env->string('APP_LOCALE', 'en_US')`, and `.env.example`
    lists `APP_LOCALE`.
  - `blush-dev/blush` ships `config/markdown.php` adding
    `AttributesExtension` to the defaults, since its sample post uses
    `{.alignwide}`. The framework's `DEFAULT_EXTENSIONS` stay as they
    are; attribute syntax is a site's choice.
- **Why:** closes the skeleton gaps found while writing `docs/` (D-141),
  before jtcom's trial starts from the skeleton.

### D-144: Site themes in `resources/themes`
- **Date:** 2026-09-26
- **Decision:** `ThemeDiscovery` also finds themes in the site's
  `resources/themes/{slug}` (the new `Paths::$siteThemes`), with the new
  `ThemeSource::Site`. Precedence for a shared slug: `user/themes`, then
  `resources/themes`, then Composer; nothing replaces `default`. Site
  themes' `autoload` maps are loaded like local themes'. `theme:new`
  still writes to `user/themes`.
- **Why:** jtcom's `user/` is its content repo, and its theme is code
  that belongs in the site repo. **Provisional:** the author wants to
  revisit where themes (and site code generally) live, relative to
  `user/`, later; see `open-questions.md`.

### D-145: Themes can serve web app manifests
- **Date:** 2026-09-26
- **Decision:** `ThemeChain::ASSET_TYPES` adds `webmanifest`
  (`application/manifest+json`), so a theme can ship
  `manifest.webmanifest` and link it with `$this->asset()`. `.json`
  stays private (theme and token manifests live in theme folders).
- **Why:** jtcom links a web app manifest; found in the M8 trial.

### D-146: Page data is shared with every template
- **Date:** 2026-09-26
- **Decision:** `ViewContext::share()` adds data every template sees.
  `ThemedPageRenderer` shares `$page`, `$entry`, `$entries`, `$type`, and
  `$title`, and `ThemedErrorPages` its error data, instead of passing
  them to the top template only. Layouts, partials, and components see
  them; data a template passes (`insert('part', entry: $item)`) wins.
  This narrows D-009's scope isolation: partials still don't see their
  caller's local variables.
- **Why:** in the M8 trial, every part needed `page:`, `entry:`, and
  `title:` passed by hand, the port's most repeated friction.

### D-147: `collection-taxonomy` in the hierarchy
- **Date:** 2026-09-26
- **Decision:** a taxonomy type's listing (its terms) tries
  `collection-{type}` → `collection-taxonomy` → `collection`.
- **Why:** jtcom lists five taxonomies the same way and needed five
  identical templates.

### D-148: `inheritTokens` in `theme.json`
- **Date:** 2026-09-26
- **Status:** Superseded by D-160.
- **Decision:** `"inheritTokens": false` (default `true`) makes a theme
  the start of its token chain: the default theme's and its ancestors'
  tokens are left out, and a theme without its own tokens prints no
  `blush-tokens` block. Site and entry overrides still apply on top. A
  theme that opts out declares its own `$type`s (they were inherited
  from the default's groups).
- **Why:** jtcom's CSS doesn't use tokens, but every page carried about
  50 lines of the default theme's. Inferring it (from whether the
  default's styles load) would break themes that copy the default's
  stylesheet, so it's explicit.

### D-149: Entry description and image in the head
- **Date:** 2026-09-26
- **Decision:** on a page showing a (non-virtual) entry,
  `ThemedPageRenderer` adds `description` and `og:description` (the
  excerpt, 30 words, as text: the summary when there is one) and, when
  the entry has an `image` field, `og:image` (made absolute unless it's
  already an `http(s)` URL) and `twitter:card` =
  `summary_large_image`. Themes replace or extend them through `Head`
  (a later value for the same key wins in place).
- **Why:** generic sharing metadata; jtcom's 1.x plugins did it in site
  code.

### D-150: `excerpt()`'s `$more` is HTML
- **Date:** 2026-09-26
- **Decision:** `Entry::excerpt($words, $more)` appends `$more` as HTML,
  inside the paragraph, only when the body is cut short (the excerpt
  text is still escaped). A summary is returned without it.
- **Why:** 1.x themes (jtcom) pass a "Continue reading" link; as plain
  text it was escaped.

### D-151: Reading time and inline theme assets
- **Date:** 2026-09-26
- **Decision:**
  - `Entry::wordCount()` (cached per body hash, figure captions left
    out) and `Entry::readingTime(int $wordsPerMinute = 200)` (whole
    minutes, at least 1).
  - `Template::inline($path)` returns a theme asset's contents through
    the chain, for inline SVG icons. Only servable assets
    (`ThemeChain::isServable()`) can be read, so views, PHP, and
    manifests stay out of reach.
- **Why:** jtcom's writing shows reading time, and its header and social
  menu inline SVGs; both needed workarounds in the trial.

### D-152: Template fragment cache
- **Date:** 2026-09-26
- **Decision:** `Template::cache(string $key, Closure $render): string`
  keeps rendered HTML in the `fragments` namespace through
  `ContentCache`, keyed by the active theme's slug and the key, so a
  publish (a new content version) or a theme switch renders it again.
  With caching off (development) it always renders. Only the returned
  HTML is kept, so a fragment mustn't add to the head or `<body>`
  classes. `ViewServices` gets the optional `ContentCache`. `cache:clear`
  already clears fragments.
- **Why:** the M6 carried-forward item; jtcom's archive pages list every
  post on each request.

### D-153: `widont()` for titles
- **Date:** 2026-09-26
- **Decision:** `Template::widont(string $text): string` escapes text
  and, when it has four or more words, joins the last two with
  `&nbsp;`, so a title can't end with one word alone on its line (a
  "runt"). It's 1.x's `runt()` under the name web typography tools use
  for the fix. It's a `Template` method, not a global function (global
  functions stay limited to escaping, D-106).
- **Why:** jtcom's titles used `e|runt` in 1.x.

### D-154: `Head::remove()`
- **Date:** 2026-09-26
- **Decision:** `Head::remove(string $key)` drops an item by the key
  `has()` takes (`style:{href}`, `meta:{name}`, and so on).
- **Why:** jtcom's standalone React page renders without the theme's
  stylesheets, which the head adds to every page.

### D-155: Themes build from `resources/` into `public/`
- **Date:** 2026-09-26
- **Status:** Partially superseded by D-194 (no `resources/static/`, no
  hashed file names).
- **Decision:**
  - The theme build convention is sources in the theme's `resources/`
    and built files in its `public/`: the author's folder names.
    `ThemeAssets` reads `public/.vite/manifest.json` (or
    `public/manifest.json`) first, then the `dist/` locations, which stay
    supported.
  - `resources/` joins the private theme folders, so build sources
    (SCSS, unbundled JavaScript) are never served, published, or
    exported.
  - jtcom's theme builds with **Vite** (8.x, `sass-embedded`): one tool
    for SCSS, JavaScript, fonts, and images, with hashed file names and a
    manifest Blush already read (D-119). `resources/static/` is Vite's
    `publicDir`, copied to `public/` as is for files templates reach by
    name (favicons, inline SVG icons, the web app manifest). Built files
    are committed; hosts don't need Node.
  - jtcom's SCSS moved from `@import` to `@use`/`@forward` with
    `sass-migrator`, checked byte for byte against the old output.
  - Vite's development server isn't integrated; `vite build --watch`
    works as is.
- **Why:** the author wants one simple, modern build tool for CSS,
  JavaScript, and media, with `resources/` for sources and `public/` for
  builds.

### D-156: M8 on hold; setup DX/UX next
- **Date:** 2026-09-26
- **Decision:**
  - The `jtcom-trial` branch of `../blush` is the author's test bed, not
    work to commit. Its site files (`config/`, `src/`, the jtcom theme,
    `package.json`, `vite.config.js`) and its copied `user/` content stay
    uncommitted.
  - jtcom's `2.x` branch waits until the author says jtcom can change.
    URL parity, the redirect map, production settings, and deployment
    wait until the author is ready to go live.
  - The next focus is the developer and user experience of setting up a
    Blush site (installing, first run, checking an install, the first
    look, local development), to be scoped with the author. See
    `roadmap.md`.
- **Why:** the trial did its job (D-144 to D-155 came from it); the
  author wants setup polished before the jtcom port resumes.

### D-157: Content type kinds and option names
- **Date:** 2026-09-26
- **Decision:** The first setup DX/UX slice (D-156) is how content types
  are defined. Supersedes D-083's single `ContentType` class and its
  option names.
  - `ContentType` is an abstract base. The kinds are final classes:
    `Collection` (listed entries, such as posts), `Taxonomy` (entries that
    are terms grouping other entries), and `Pages` (the built-in `page`
    type that claims the content root). Code checks
    `$type instanceof Taxonomy` instead of a `taxonomy` flag, and each
    kind takes only the options that mean something for it. Data types
    pick one with `kind:` (`collection` by default).
  - Renames: `path` is `folder`; `routing` (`TypeRouting`) is `urls`
    (`TypeUrls`, with `single:` and `collection:` shortcuts); `collect`
    and the `collection` array are `listing` (a typed `Listing`: `type`,
    `orderBy`, `order`, `perPage`, and other 1.x `query` arguments);
    `termCollect` is a taxonomy's `types` (a list); `termCollection` is
    `termListing`; `fieldAliases` is `aliases`; `feed`'s `taxonomy` and
    `collection` are `categories` and `listing`; `archives`
    (`ArchiveGranularity`) is `dateArchives` (`DateArchives`); `schema`
    is `fields` plus `closed`, as in data. `ContentConfig`'s
    `dataTypeRouting` is `dataTypeUrls`.
  - `fromArray()` still reads every 1.x option name (D-078), and
    `taxonomy: true` makes a `Taxonomy`. 1.x's `collect: false` had no
    effect beyond listing the type itself, so it's read and dropped.
  - A taxonomy's `types` only chooses what its term pages and feeds list;
    every taxonomy's term field is still in every type's schema, so no
    content changes.
  - "Blueprint" is reserved for field definitions (the admin editor's
    forms, M10), as in Kirby, Grav, and Statamic, not for kinds.
- **Why:** the `taxonomy` flag switched behavior in a dozen places and
  gated four options, and the 1.x names collided (`collect`/`collection`,
  `path`/routing paths). The author asked for a clearer structure for
  setting up types.

### D-158: Templates use `$template`, not `$this`
- **Date:** 2026-09-27
- **Decision:** Supersedes the `$this` binding in D-103 (the template
  runs in a closure bound to `Template`). A template file runs in a
  static closure with its `Template` in scope as `$template`, and no
  object or class scope. Using `$this` in a view fails with a
  `ViewException` saying views use `$template`. A data key or component
  prop named `template` is ignored (like `__data` and `__file`). Arrow
  functions in views capture `$template` on their own; a `function ()`
  closure needs `use ($template)`. The default theme, the test views,
  the docs, and the `jtcom-trial` theme use `$template`.
- **Why:** the author doesn't want `$this` in theme views: it reads as
  if a view file were a class method. `$template` is explicit, matches
  the class name, and can be typed with `@var` for editors.

### D-159: 1.x's include helpers return
- **Date:** 2026-09-27
- **Decision:** Refines D-103's template API with 1.x's names (its
  `Engine`):
  - `insert()` is renamed `include()`. It takes a view name or a list,
    rendering the first that exists (a single invalid name is an error; a
    list skips invalid names, like hierarchies).
  - `includeIf($views, ...$data)` renders `''` when no view exists.
  - `includeWhen($when, $views, ...$data)` and
    `includeUnless($unless, $views, ...$data)` render on a truthy or
    falsy condition.
  - `each($views, $items, as: 'item', empty: null, ...$data)` renders a
    partial per item, passing the item as `$as` and its position as
    `$index`, plus the other data; with no items it renders `empty`, when
    given.
  - Every helper returns a string (printed with `<?= ?>`), unlike 1.x's,
    which echoed. 1.x's template tags (`__call`, `tag()`) stay out:
    components, context providers, and `Head` cover them, and magic
    methods would lose `$template`'s typing.
- **Why:** the author relied on these in 1.x; D-103 kept the API small
  after Plates' shape and dropped them without a decision.

### D-160: No design token system (for now)
- **Date:** 2026-09-27
- **Decision:** Supersedes D-023, D-118, and D-148, and the token parts
  of D-022 (token files and site token overrides), D-027 (the `tokens`
  front matter), D-121 (contrast checks), and D-130 (cached token CSS).
  Blush sets no design rules for themes: a theme's styles are plain CSS
  and nothing is compiled into the head.
  - Removed: `Blush\Theme\Token` (`TokenSet`, `TokenResolver`,
    `Contrast`), `tokens.json|yaml` in themes, `inheritTokens` and
    `contrast` in `theme.json`, `tokens` in `user/data/theme.json`, the
    `tokens` front matter field, `$template->token()`, the
    `blush-tokens`/`blush-entry-tokens` style blocks, the `tokens`
    cache namespace, and `theme:check`'s token and contrast checks.
    Old keys in manifests and data files are ignored; a `tokens` key in
    front matter is now an ordinary custom field.
  - The default theme's palette and scale are custom properties in
    `style.css`; dark mode uses `light-dark()`, driven by the
    `color-scheme` the stylesheet already sets (system preference, or
    `data-scheme` on `<html>`). The palette's values are unchanged.
  - `Head::inlineStyle()` stays as a general API.
  - A token system may return later as an add-on (open question);
    `theming.md` → Design keeps notes on the old design.
- **Why:** the author wants themes to design however they like without
  framework rules about design for now. jtcom didn't use tokens, and
  their main payoff (site-owner overrides) waits on the admin.

### D-161: Numbered pagination returns (1.x's `Pagination`)
- **Date:** 2026-09-27
- **Decision:** `Paginator::links(?Closure $url, int $endSize = 1,
  int $midSize = 1, bool $adjacent = true)` returns a `list<PageLink>`
  for numbered pagination, 1.x's layout: the first and last `$endSize`
  pages, `$midSize` on each side of the current page, and dots between.
  `ContentPage::pageLinks()` calls it with the page's URL builder.
  - `PageLink` is a readonly value: `kind` (`PageLinkKind`), `?number`,
    `?url` (none for the current page or the dots), `isCurrent()`, and
    `padded($width)` for 1.x's leading zeros.
  - `PageLinkKind` is `Previous`, `Number`, `Current`, `Dots`, `Next`,
    backed by 1.x's class suffixes (`prev`, `number`, `current`, `dots`,
    `next`) so they work as class names.
  - Unlike 1.x, a gap of one page shows that page instead of dots.
  - Themes own the markup and the labels; the framework has no
    pagination renderer (1.x's HTML options array is gone). A listing
    with one page, or a page past the last, has no links.
  - The default theme's `parts/pagination.php` uses it (numbered links
    instead of "Page X of Y"), and so does the `jtcom-trial` theme.
- **Why:** 1.x drew these links for themes; 2.x left every theme to
  write the windowing loop itself (about 40 lines in the jtcom trial).
  Theme authors shouldn't need that logic.

### D-162: Later pages of a listing get the page number in the title
- **Date:** 2026-09-27
- **Decision:** `ThemedPageRenderer` sets the head title of page 2 and
  later to the `blush` catalog's `document_title.paged`
  (`"{title}: Page {page}"`), or `document_title.page` (`"Page {page}"`)
  when the page has no title of its own (the front page). A theme's
  catalog overrides either key. `og:title` stays unnumbered. This adds
  the framework's first catalog, `resources/lang/en.json`.
- **Why:** 1.x's `DocumentTitle` did this; without it every page of a
  listing shares one `<title>`, which hurts search results and browser
  history.

### D-163: `dump()` and `dd()` through Symfony VarDumper; stray output is kept
- **Date:** 2026-09-27
- **Decision:** Debug dumps are Symfony VarDumper's `dump()` and `dd()`,
  a development dependency of sites (and of the framework), per D-006.
  Blush doesn't wrap or restyle them. The skeleton's `composer.json`
  should list `symfony/var-dumper` in `require-dev` (not done yet: the
  skeleton's `2.x` branch isn't checked out).
  - `HttpRunner::run()` buffers output printed while the kernel runs and
    `StrayOutput::insert()` adds it just inside `<body>` (or before
    another body), dropping `Content-Length`. Without it, a dump outside
    a view sent headers early and the `Emitter` threw.
  - The calls are the stable surface. A nicer in-house dumper or debug
    toolbar can come later by replacing VarDumper's handler
    (`VarDumper::setHandler()`) or taking over from `StrayOutput`,
    without changing `dump()` calls.
- **Why:** 1.x shipped a styled dumper; 2.x had none. The author wants a
  dumper now and may build a nicer one later.

### D-164: Core components are declared; components are discoverable
- **Date:** 2026-09-27
- **Decision:** Components stay registered by file: a template-only
  component is just `views/components/{key}.php`, and only classes go in
  the `ComponentRegistry`. Discovery comes from three pieces instead:
  - `ComponentType` declares all four core content components
    (`callout`, `embed`, `figure`, `gallery`), the contract content can
    rely on in any theme (D-033). `className()` returns `null` for the
    template-only ones, and the registrar seeds only classes (still just
    `embed`).
  - `Views::components()` lists every component a chain can render, as
    `ComponentListing`s: the core keys, the registered classes, and every
    `components/**.php` in the view directories (nested keys such as
    `cards/post` included), each with its class, its files (winner
    first), and whether it's core. `isMissingTemplate()` is true when
    there's no file and no class that picks another view by overriding
    `template()`.
  - A `component:list [--theme]` command prints them and warns about
    components that can't render; `theme:check` warns about the same.
- **Why:** the author wants developers to know which components exist.
  Requiring registration for template-only components would add a step
  that drifts from the files and would loosen the registry's class
  contract; listing from the chain shows what actually renders.

### D-165: A global installer is planned
- **Date:** 2026-09-27
- **Decision:** Blush will get a global installer: a small package
  installed once per machine (`composer global require`, like
  `laravel/installer`) that provides a `blush` command. It creates new
  sites (`blush new mysite`, wrapping `composer create-project` and the
  first-run steps) and, inside a site, hands off to that site's
  `bin/blush`, found by walking up from the current folder. It's part of
  the setup DX work (D-156) and not scheduled yet. Until it exists, a
  launcher script that finds the nearest `bin/blush` does the hand-off.
- **Why:** `bin/blush` belongs to each site, so a global command has to
  find the site it's run in; the author wants that, plus site creation,
  as a real product feature rather than a script users copy.


### D-166: `user/` is everything the site owner owns or installs
- **Date:** 2026-09-27
- **Decision:** `user/` is the site's `wp-content`: the content, media,
  and data the owner writes, plus the themes and extensions they
  install. The defaults stay `user/themes/{slug}` (D-034) and
  `user/extensions/{slug}` (D-041). Each theme or extension may be its
  own git repository nested there. When `user/` is itself a repository
  (jtcom's content repo holds `content/` and `media/` at its root), it
  ignores `themes/` and `extensions/`, so the nested repositories are
  independent of it. D-039's rule stands: the admin and uploads never
  write executable files into `user/`, and only people with repo or
  filesystem access change themes and extensions.
- **Why:** the author wants content in one repo and each theme and
  extension in its own, all under `user/`. Publishing's `git pull`
  (D-131) runs in `user/` and so updates only the content repository;
  git doesn't descend into ignored nested repos, so a content push never
  deploys code. Clarifies D-016 and D-039's "content and data", and
  answers the open question D-144 raised about where themes live.

### D-167: `resources/themes` is removed; a theme carries its own build
- **Date:** 2026-09-27
- **Decision:** Supersedes D-144. `Paths::$siteThemes` and
  `ThemeSource::Site` are gone; local themes come only from
  `user/themes` (D-166), and a same-slug `user/themes` theme beats a
  Composer one. A built theme keeps its build config (`package.json`,
  `vite.config.js`) in its own folder rather than at the site root, so
  it travels with the theme's repository. The jtcom trial's theme moved
  to `user/themes/jtcom`, with its Vite build.
- **Why:** `resources/themes` was a workaround for keeping jtcom's theme
  out of its content repo; D-166 makes each theme its own repo under
  `user/themes` instead. One local location keeps discovery and
  precedence simple.

### D-168: Build tool config files in a theme are private
- **Date:** 2026-09-27
- **Decision:** `ThemeChain::isServable()` refuses any `*.config.js`,
  `*.config.mjs`, or `*.config.cjs` file, at any depth, alongside the
  private folders (D-155). So a theme's `vite.config.js` (D-167) is never
  served, published, or exported. `package.json` was already private
  (`.json` isn't an asset type), and so was `node_modules/`.
- **Why:** moving the build into the theme put a `.js` file at its root,
  which the asset route would otherwise serve. A built asset is never
  named `*.config.js`, so the rule costs nothing.

### D-169: Site config stays at the root, not under `user/`
- **Date:** 2026-09-28
- **Decision:** Reaffirms D-039 after considering moving config under
  `user/` (D-166). `config/*.php` stays at the project root, and
  environment values and secrets stay in `.env`. No settings data layer
  in `user/` for now. Where jtcom's content types live (data types or an
  extension) is still being weighed; extension-defined types (D-083) are
  now documented for users.
- **Why:** config is PHP loaded with `$env` in scope, and `user/` is
  what the publish webhook pulls and the admin writes (D-039); boot reads
  config before any extension loads, so extensions can't hold it either.

### D-170: Components get their own user doc
- **Date:** 2026-09-28
- **Decision:** `docs/components.md` is the one place users learn about
  components: the directive syntax, the core components, using them in
  templates, template-only and class-backed components, and where they
  live (theme or site). It says plainly that every component the active
  theme can draw works in Markdown with no extra registration, that
  content using a theme's own component falls back to plain text under
  another theme, that Markdown components can't add to the head, and
  that they're cached with the rendered body (D-130). `content.md`,
  `themes.md`, and `extending.md` keep short sections that link to it.
- **Why:** the component-to-directive connection (D-026, D-112) was only
  implied across three docs. The jtcom trial's archive pages moved from
  `single-page-*` views to `::post-archives{by=…}` in their content,
  which relies on exactly these rules.

### D-171: Component names are namespaced; short names are core only
- **Date:** 2026-09-28
- **Decision:** Every component has a namespaced name, `{namespace}/{name}`
  (`blush/callout`, `acme/tabs`, `jtcom/post-archives`), and the directive
  syntax allows the `/` (`::acme/tabs{…}`).
  - **Short names are only for core components.** `::callout` is
    `blush/callout`. Third-party components (extensions and themes) are
    always written with their namespace; a short name that isn't a core
    component renders as plain text, like any unknown directive.
  - **Template files use the namespace with a hyphen:**
    `components/acme-gallery.php` draws `acme/gallery`. A core
    component's template may be `components/gallery.php` or
    `components/blush-gallery.php`, so today's overrides (such as jtcom's
    own `callout.php`) keep working.
  - The inserter (M10) always writes the full name.
- **Why:** components are meant to be the main extension point for
  developers, so extension and theme names mustn't collide. Only one
  theme chain is active at a time, and the site and themes already win
  over core by precedence, so the real risk is between extensions and
  between an extension and a theme. Reserving short names for core keeps
  everyday writing (`::callout`, `:::gallery`) easy.
- **Namespaces:** core is `blush`, a theme's is its slug
  (`jtcom/post-archives`), and the site's own components (registered
  from its `src/`) use `app`, after the `App\` PHP namespace. An
  extension uses its vendor. Whether theme slugs and extension vendors
  may clash is decided later (see `open-questions.md`).

### D-172: Component metadata lives with the registration, and is translatable
- **Date:** 2026-09-28
- **Decision:** What the inserter shows about a component (its label,
  description, keywords, what it wraps, and its props with their labels
  and choices) comes from where the component is registered, not from a
  file beside its template in `views/`. Every piece of user-facing text
  in it is translatable. Only registered components appear in the
  inserter; unregistered template-only components still render but are
  hidden.
  - **No metadata file.** Structure comes from the component: a class
    component's props from its constructor (types and defaults; a
    backed-enum prop gives its choices), and a template-only
    component's props and what it wraps (`none`, `text`, or `blocks`)
    from a small typed definition given at registration. Both reuse
    the content schema's field types.
  - **Text comes from the translation catalogs by convention.** The
    namespace is the domain (`blush`, the theme's, an extension's, the
    site's), and keys follow the name: `components.tabs.label`,
    `.description`, `.keywords`, `.props.tone.label`, and
    `.props.tone.choices.warning`. A missing key falls back to a label
    made from the name, and `theme:check` and `extension:check` warn
    about it. This needs the extension and site translation domains
    (carried forward from M5).
- **Why:** the registration is the component's source of truth (the
  view chain only decides how it looks, and a theme may override the
  template), and the admin and its inserter must work in the site's
  language.

### D-173: Component names and metadata, implemented
- **Date:** 2026-09-28
- **Decision:** Implements D-171 and D-172 in `Blush\View\Component`:
  - `ComponentName` parses `{namespace}/{name}` or a core short name
    (anything else is `null`), gives the template view names
    (`components/{namespace}-{name}`, plus `components/{name}` for core),
    and maps a file name back to a name using the known namespaces
    (longest prefix wins). Directive names allow one `/`
    (`DirectiveAttributes::NAME`).
  - `ViewFinder::nearest()` and `allOf()` resolve a view with several
    allowed names by directory precedence, so a theme's
    `blush-callout.php` beats the default theme's `callout.php`.
  - `ComponentRegistry` no longer extends `Support\Registry`: it stores
    `ComponentDefinition`s (name, optional class, `ComponentContent`,
    props as schema `Field`s) by full name, since template-only
    components can be registered. It refuses short third-party names and
    new `blush/*` names. The registrar seeds all four core components
    with their definitions (`ComponentType::content()`/`props()`).
  - A class component's props come from its constructor: `string`,
    `int`, `float`, `bool`, and backed-enum parameters that are public
    or unpromoted (services and private state are skipped), with
    defaults, or `required()` when there's none. `Component::CONTENT`
    says what it wraps. `ComponentFactory` casts string props to backed
    enums; an unknown value falls back to the parameter's default.
  - Text: `Views::componentText()` reads `components.{name}.{key}` from
    the namespace's domain. The translator gains the `app` domain
    (`resources/lang`) and one per extension vendor (each enabled
    extension's `lang/`); a theme namespace maps to the `theme` domain.
    The framework catalog has the core components' text.
  - `component:list` shows the full name, label, and whether it's
    registered, and reports stray files; `theme:check` warns about a
    theme's stray component files and notes (with `--strict`) its
    registered components without a label.
  - Subfolders of `components/` are no longer components.
- **Why:** the naming and metadata rules of D-171 and D-172. The jtcom
  trial moved to `jtcom/post-archives` and `jtcom/entry-terms` and
  builds the same 421 pages.
- **Not done:** extensions can't ship component templates (they have no
  view directory in the chain); see `open-questions.md`. The inserter
  itself is M10.

### D-174: Extensions will get views in the chain (later)
- **Date:** 2026-09-28
- **Decision:** Each enabled extension's `views/` will join the view
  chain, so an extension can ship its components' templates (and other
  views). Not scheduled yet; the likely place is below the site and
  themes and above the default theme, so themes can still override.
  Until then, the theme or site supplies an extension component's
  template (D-173).
- **Why:** components are meant to be the main extension point
  (D-171), and an extension that registers one should be able to ship
  how it looks.

### D-175: The planned core component set
- **Date:** 2026-09-28
- **Decision:** Core components exist only where Markdown (with its
  CommonMark extensions) can't do the job well. Core short names are
  reserved for good (D-171), so the set grows deliberately. Nothing
  below is built yet.
  - **Layout:** `group` (a `<div>`/`<section>` wrapper for a class, id,
    or alignment), `grid` (CSS grid: `columns=`, or auto-fit with a
    minimum width), and `row` (flexbox, wrapping). `gallery` stays (it
    carries image meaning: captions, a later lightbox) and may be built
    on `grid`. No `columns`/`column` for now, and no `flex` or `stack`.
  - **Media:** `audio`, `video` (poster, caption track, dimensions from
    the media folder), and `file` (a download link with its size and
    type), alongside `figure`, `gallery`, and `embed`.
  - **Inline:** `abbr` (`title`), `kbd`, and `time` (`datetime`).
  - **Planned, later:** `toc` (a table of contents; weigh CommonMark's
    `TableOfContents` extension first), `icon` (SVG icons from a
    registry), and `progress` and `meter`.
  - **Markdown extensions:** support definition lists
    (`DescriptionList`) and highlighting (`Highlight`, `==text==` →
    `<mark>`).
  - **Not now:** quotes with a source and credit, and table captions.
    Blockquotes already work; the direction may be improving the
    existing quote, or a general figure wrapper that captions a quote,
    table, or code block. Buttons: undecided; a link with a class may be
    enough.
- **Why:** the author reviewed a proposed inventory (layout, blocks,
  media, actions, inline, logic) against what Markdown already covers.

### D-176: Definition lists and highlighting are on by default
- **Date:** 2026-09-28
- **Decision:** Implements D-175's Markdown part: `DescriptionListExtension`
  and `HighlightExtension` join `MarkdownConfig::DEFAULT_EXTENSIONS`.
  `CommonMarkParser` adds each configured extension once
  (`array_unique`), so a config that spreads the defaults and lists one
  again keeps working.
- **Why:** the author wants both. Without the dedupe, CommonMark throws
  on a duplicate (a second `==` delimiter processor), which jtcom's
  config (it already added `DescriptionListExtension`) would have hit.

### D-177: Layout components: `group`, `grid`, and `row`
- **Date:** 2026-09-28
- **Decision:** Implements D-175's layout set as core components, class
  backed in `Blush\View\Component\Layout`, with templates in the default
  theme:
  - `group` (`Group`): a `<div>`, or a `<section>` (`tag=section`) whose
    label becomes its `aria-label`.
  - `grid` (`Grid`): `columns` (most columns, 1 to 12, default 2),
    `min` (narrowest column before it drops one, default `12rem`; `0`
    keeps them all), `gap`. One `grid-template-columns` does it:
    `repeat(auto-fill, minmax(max(min(MIN, 100%), calc((100% - (N - 1)
    * GAP) / N)), 1fr))`, so it's responsive without media queries.
  - `row` (`Row`): flexbox with `justify` (`RowJustify`), `align`
    (`RowAlign`), `wrap`, and `gap`.
  - **Layout is inline styles.** Unlike the other core components, whose
    look comes from theme CSS, these set their structural CSS inline, so
    they work under any theme (jtcom doesn't load the default theme's
    stylesheet). Themes style the `group`, `grid`, and `row` classes, and
    set spacing with the `--layout-gap` custom property, which the inline
    styles read (a component's own `gap` sets it on the element).
  - **Lengths are checked** (`CssLength`): a prop that goes into `style`
    must be `0` or a number with a length unit or `%`; anything else
    falls back to the default, so a prop can't carry other CSS.
  - `class` and `id` come from the directive's `.class`/`#id` (read from
    `$props` in the templates), not constructor props, so they aren't
    listed as inserter fields.
- **Why:** the author picked these three (D-175). Inline structure keeps
  core layout working in every theme, which is the promise of core
  components.

### D-178: Checking a theme leaves out other themes' components
- **Date:** 2026-09-28
- **Decision:** `theme:check` and `component:list` skip components in the
  namespace of an installed theme outside the chain being checked
  (`Themes::isOutside()`). The active theme's provider registers its
  components on every boot, so checking another theme (`theme:check
  default` with jtcom active) wrongly warned that `jtcom/post-archives`
  had no template.
- **Why:** such a component belongs to its own theme (D-171) and can't
  render in another chain; it isn't that chain's problem.

### D-179: Media components: `audio`, `video`, and `file`
- **Date:** 2026-09-28
- **Decision:** Implements D-175's media set as core components, class
  backed in `Blush\View\Component\Media`, with templates in the default
  theme:
  - `audio` (`Audio`): `src`, `preload` (`MediaPreload`, `metadata` by
    default), `loop`; the label is the caption.
  - `video` (`Video`): `src`, `poster`, `captions` (a WebVTT track whose
    `srclang` is the site locale's language), `width`/`height` (the
    poster's when neither is given, so the page doesn't shift),
    `preload`, `loop`, `muted`; always `playsinline`.
  - `file` (`File`): a `download` link; the label or the file's name;
    `$format` (the extension) and, for local media, `$size` (1,024 to a
    unit, in the site's number format). Its joining text is the default
    theme's `media.file_details`.
  - **Media props resolve like images.** A directive now carries its
    entry's base folder (`Directive::$base`, from `MarkdownContext`), and
    `ComponentDirectives` resolves every `media` prop of a registered
    component through `MediaResolver` against it, so `src=clip.mp4` in a
    page bundle becomes `/media/_content/…/clip.mp4`. Unresolved values
    pass through as written. A class marks a string parameter as media
    with `#[MediaProp]` (a `MediaField` in its definition). This also
    fixes `figure`'s `src` in bundles.
  - **`text/vtt` is allowed by default**, so caption tracks are served; a
    `.vtt` file that sniffs as `text/plain` (no cue yet) counts as
    `text/vtt`, as SVGs are read by extension.
  - Video dimensions come from the poster only; reading them from the
    video file would need a media probe.
- **Why:** the author picked these (D-175). Resolving by field type keeps
  bundle media working for any component, not just these.
- **Open:** documents (such as PDFs) aren't allowed media types, so a
  local `file` of that type has no size and isn't served unless a site
  adds the type.

### D-180: Inline components: `abbr`, `kbd`, and `time`
- **Date:** 2026-09-28
- **Decision:** Implements D-175's inline set as core components, with
  templates in the default theme:
  - `abbr`: template-only; `title` is the expansion.
  - `kbd` (`Inline\Kbd`): a label joined with `+` is a combination, each
    key in its own `<kbd>` inside the outer one (HTML's recommendation);
    a `+` with nothing on one side doesn't split (`Ctrl++`).
  - `time` (`Inline\Time`): `datetime` must be one of HTML's forms (year,
    month, date, local or global date and time, time, or ISO duration)
    and a real date, or the element gets no `datetime`. Without a label
    it's shown with `IntlDatePatternGenerator` skeletons in the site's
    locale and time zone; a duration or invalid value shows as written.
  - `ComponentDirectives` trims an inline directive's HTML, so a
    template's line breaks don't turn into spaces in the sentence.
- **Why:** the author picked these (D-175).

### D-181: Embeds keep their start time and name their frame
- **Date:** 2026-09-28
- **Decision:** Refines D-113's `embed`:
  - A start time carries over: YouTube's `t` (`90`, `90s`, `1m30s`,
    `1h2m3s`) or `start` becomes `?start={seconds}` on the no-cookie
    frame, and Vimeo's `#t=` becomes `#t={seconds}s`. Other query
    parameters (such as YouTube's `si` share ID) are still dropped.
  - The iframe's `title` (its accessible name) is the `title` prop, else
    the label (the caption), else the theme's `embed.{provider}` text
    ("YouTube video"), never the URL. A link to a non-embeddable URL
    still shows the URL as its text.
  - No-cookie mode stays the only mode; a setting to use `youtube.com`
    was considered and skipped.
- **Why:** the author compared an `::embed` with jtcom's hand-written
  1.x iframes on a trial post; start times were being lost and the
  frame was named by its URL.

### D-182: Component classes start with `component-`
- **Date:** 2026-09-28
- **Decision:** A component's CSS classes are BEM-style and named after
  it with a `component-` prefix: `component-callout`,
  `component-callout--warning`, `component-callout__title`. The default
  theme's core components use it (supersedes the bare `callout`, `grid`,
  `embed`, … of D-113, D-177, and D-179; `abbr` and `time` gain
  `component-abbr` and `component-time`), and the docs recommend it for
  every component. Classes that aren't components keep their names
  (jtcom's `block-heading__permalink`, `block-table-of-contents`, and the
  byline's `block-row`).
  - The jtcom trial's theme follows it: `component-callout`,
    `component-embed` (was `block-embed`), `component-figure`,
    `component-gallery`, `component-entry-terms`, and
    `component-post-archives` with `--post`, `--month`, and `--year`
    modifiers (was three blocks, `archives-site`, `archives-monthly`,
    `archives-yearly`, plus the unstyled `block-month-archives__year`,
    now `__year`).
  - jtcom's SCSS keeps its 1.x names as aliases for HTML written in
    posts, which don't change (D-078): `.block-embed`,
    `.block-embed__wrapper`, and `.embed-wrap`, `.gallery` (with
    `--flex`, `--grid`, and stacked galleries via `& + &`), and `.box`.
- **Why:** the author's convention (jtcom used 1.x's `block-` prefix);
  one prefix marks component markup in any theme.

### D-183: The `toc` component
- **Date:** 2026-09-28
- **Decision:** A core `toc` component (`View\Component\Toc`) rather than
  relying on CommonMark's `TableOfContents` extension (D-175 said weigh
  it first): a component can be placed anywhere with `::toc`, styled and
  overridden like any other, and works without heading permalinks.
  - **Outline at parse time.** A directive renders in document order, so
    a `::toc` at the top can't see later headings. `CollectOutline` (a
    `DocumentParsedEvent` listener at -200, after permalinks and
    CommonMark's table of contents, before the slug history resets at
    -1000) runs only when a document has a `toc` or `blush/toc`
    directive. It gives every heading a link target (its `id`, its
    permalink's `fragment_prefix`-slug, or a new `id` from the
    environment's slug normalizer, which keeps ids unique alongside
    permalinks) and sets the outline (`level`, plain `text`, `id`) on the
    directive node. `Directive::$outline` carries it, and
    `ComponentDirectives` passes it as the `headings` prop.
  - `Toc` takes `min`/`max` (2 and 3 by default, clamped and swapped if
    reversed) and nests the headings in range: a deeper heading goes
    under the item before it, a skipped level nests with the level below,
    and a deeper heading with nothing before it joins the top level.
    Nothing renders without headings in range. The label is the title
    and the `<nav>`'s name (else the theme's `toc.label`).
  - Excerpts and word counts leave out `<nav>` (with figure captions),
    so a table of contents never becomes a post's excerpt or its reading
    time.
  - Pages without a table of contents are unchanged: no heading ids are
    added.
- **Why:** the author asked for it (D-175). Checked against jtcom's
  config: the component's links match its permalinks and CommonMark's
  table of contents exactly.

### D-184: oEmbed providers for embeds
- **Date:** 2026-09-28
- **Decision:** Embeds use oEmbed, through a new `Blush\Embed` subsystem,
  so each embed gets its real size and title and sites can add any
  number of providers. Supersedes D-113's hard-coded YouTube/Vimeo
  parsing (kept as those providers' URL-only fallback).
  - **Providers are registered, never discovered** (D-113's rule stands:
    content never frames an unknown site). `EmbedProvider` (abstract):
    name, label, oembed.com-style schemes (`*` wildcards; `http://`
    matches as `https://`), and an HTTPS endpoint; `request()` (the
    oEmbed URL to ask), `frame()` (the URL to frame), and
    `allowsScripts()`. Built in (enum + registry + factory + registrar):
    `YouTube` (always `youtube-nocookie.com` with the start time, asking
    oEmbed about the plain watch or short URL) and `Vimeo` (`dnt=1`, the
    start time, and a private link's hash, now also taken from oEmbed's
    frame). Sites add plain providers in `config/embed.php`
    (`OEmbedProvider`, or arrays) and ones that need code as classes in
    `ProviderRegistry`. Configured providers come first and replace a
    registered one of the same name.
  - **Blush builds the markup.** `EmbedData` keeps the checked response
    (type, title, provider name, positive integer sizes, HTTPS
    thumbnail); `frame()` reads the HTTPS iframe URL from the provider's
    HTML with `Dom\HTMLDocument`, and the theme renders its own iframe.
  - **Fetching** happens on first render (`Embeds::lookup()`), through the
    `Fetcher` interface (`StreamFetcher`: HTTPS only, a timeout, three
    redirects, 1 MB, a 200 status). Answers are kept for 30 days and
    failures for an hour (`EmbedConfig` `ttl`/`failureTtl`) in the new
    `embeds` cache namespace, which isn't derived (publishing and
    `cache:clear` leave it) and is used through `persistent()`, so it
    works with caching off. `fetch: false` never asks.
  - **The component** gets `$width`, `$height`, `$ratio` (for
    `--embed-ratio`, which the default theme's frame uses as its
    `aspect-ratio`), `$embedTitle`, `$thumbnail`, and `$providerLabel`.
    The frame's name is `title`, else the provider's title, else the
    label, else the theme's `embed.title` ("Embedded content from
    {provider}"); a link's text prefers the provider's title to the URL.
  - **Rich embeds** that need the provider's script (X, Instagram,
    TikTok, Mastodon) render as links for now; the design for them is in
    `open-questions.md`.
- **Why:** the author wants oEmbed as the general mechanism, with many
  providers. Checked live on the jtcom trial: YouTube answers set each
  frame's ratio and title.

### D-185: Embed frames use `aspect-ratio` on the iframe
- **Date:** 2026-09-28
- **Decision:** The default theme (and the jtcom trial's theme) size an
  embed's iframe directly: `display: block; width: 100%; height: auto;
  aspect-ratio: var(--embed-ratio, 16 / 9)`, replacing an absolutely
  positioned iframe in a sized wrapper (and jtcom's 1.x
  `padding-bottom: 56.25%` technique, whose extra `padding-top: 30px`
  made every frame taller than 16:9). `height: auto` also covers 1.x's
  hand-written iframes with `width`/`height` attributes. A tall embed
  (a 9:16 short) is capped at 80% of the small viewport height with
  `max-width: calc(80svh * (var(--embed-ratio)))` and centered. jtcom
  drops the unused `.embed-wrap` alias (no post in its full content uses
  it) and the wrapper's `margin-bottom`, which `o-flow` and the caption's
  padding cover.
- **Why:** the author asked for a modern review of the old wrapper;
  `aspect-ratio` is supported in every current browser, and with real
  ratios from oEmbed (D-184) vertical videos need a height cap.

### D-186: Only portrait embeds get the height cap
- **Date:** 2026-09-28
- **Decision:** Corrects D-185. Its `max-width: calc(80svh * ratio)` cap
  applied to every embed, so on a short window a 16:9 video in jtcom's
  `stretch-wide` figure (1024px) stopped filling it. The cap now applies
  only to portrait embeds: `Embed::$portrait` (the provider says it's
  taller than wide) adds `component-embed--portrait`, and only that
  class gets the cap and centering. Checked in headless Chrome at
  1440×800, 1440×1200, and narrow widths: landscape iframes match their
  wrapper's width at 16:9 (1024×579).
- **Why:** the author saw the old-men post's videos stop stretching.

### D-187: Icons: a Lucide subset, named like components
- **Date:** 2026-09-28
- **Decision:** Blush ships icons and a core `icon` component.
  - **The core set** is a front-end subset of Lucide 1.48.0 (ISC): 131
    icons (arrows and navigation, people, files and writing, messages and
    sharing, media, alerts, actions, devices, and a few objects), in
    `resources/icons/blush/` with Lucide's license, its search tags
    (`tags.json`, for the future inserter), and a README on updating.
    Each SVG keeps Lucide's name, drops its license comment and `class`,
    and is one line. All of Lucide may be bundled later. Brand logos
    aren't in core; a future social menu will need some basics (jtcom
    uses social brands), likely from Simple Icons.
  - **Names** follow D-171: `{namespace}/{name}` (`IconName`), with short
    names only for core (`house` is `blush/house`); the name part is
    lowercase letters, digits, and hyphens.
  - **Lookup** (`Blush\Icon\Icons`, per theme chain), first found wins:
    the site's `resources/icons/{ns}/{name}.svg` (its own `app` icons at
    `resources/icons/{name}.svg`); each theme, active first,
    `icons/{name}.svg` for its own namespace or `icons/{ns}/{name}.svg`
    to restyle another's (`icons/blush/house.svg`); folders extensions
    add to `IconRegistry` for their vendor; then core.
  - **Output** is inline SVG (`View\Component\Icon`, `:icon[Label]{name=…}`
    or `:icon[]{name=…}`; `$template->icon($name, $label)`): `1em`
    square, `class="component-icon"` plus any directive classes,
    `focusable="false"`, and `aria-hidden="true"` without a label or
    `role="img"` with `aria-label` from it. The SVG is edited with
    `Dom\XMLDocument`, so labels and classes are escaped; a file that
    isn't well-formed SVG renders nothing, as does an unknown icon.
  - **Labels** are translatable: `icons.{name}.label` in the namespace's
    domain (`Views::iconText()`, sharing the component text's domain
    rules); the `blush` catalog has English labels for the core set,
    plainer than some Lucide names (`triangle-alert` is "Warning").
  - `icon:list [--theme]` lists every icon a chain can show, with its
    label and file.
  - `Template::component()`'s first parameter is now `$component` (it
    was `$name`, D-173), so a component can take a `name` prop from PHP.
- **Why:** the author chose a Lucide subset for the front end, names
  like components, inline SVG, and translatable labels for the future
  admin editor.

### D-188: `progress` and `meter` components
- **Date:** 2026-09-28
- **Decision:** Core `progress` (`View\Component\Progress`) and `meter`
  (`View\Component\Meter`), the last of D-175's planned set.
  - `progress`: `value` and `max`; without `value` the bar is
    indeterminate. `meter`: `value`, `min`, `max`, and optional `low`,
    `high`, and `optimum`.
  - **`max` is 100 by default** for both (HTML's default is 1), so a
    plain `value` reads as a percentage when written by hand. Values are
    clamped to the range; an invalid `max` (progress: not above 0; meter:
    not above `min`) falls back to 100 (and 0–100); `low` and `high` are
    swapped if reversed.
  - **Accessible names without ids:** both elements are labelable, so the
    template wraps them in a `<label>` with the label text; without a
    label they get `aria-label` from the theme's `progress.label` or
    `meter.label`. The value is also shown as text beside the bar (and as
    the element's fallback content): the percentage when the range is
    0–100, otherwise the theme's `measure.value` ("12 of 50").
  - `MeasureNumbers` writes attribute values as plain decimals and text
    in the site's locale (`NumberFormatter`).
- **Why:** the author asked for them (D-175). The planned core set is now
  built.

### D-189: The `button` component
- **Date:** 2026-09-28
- **Decision:** Answers D-175's buttons question with a core `button`
  component (`View\Component\Button`), not a link with a class: an icon
  inside a class-styled link would need a directive nested in link text
  and the Attributes extension, which is hard to write and to insert.
  - **It's an `<a>`**, since a button in content goes somewhere. Props:
    `url` (required, and must pass the `url()` escaper's scheme rules, or
    nothing renders), `variant` (`ButtonVariant`: `primary`, the default,
    or `secondary`), `icon` (any icon name), `iconPosition`
    (`IconPosition`: `start` or `end`), and `iconOnly`. The label is its
    text and is required.
  - **The icon** renders through the `icon` component and is decorative.
    An **icon-only** button is named by its label with `aria-label` (and
    `title` for a tooltip) rather than visually hidden text, so it's
    named in any theme, even one without the component's CSS. If the
    icon doesn't exist, `iconOnly` is ignored and the text shows.
  - Several buttons go in a `row`, one `::button` per line; no separate
    group component.
  - Classes: `component-button`, `--primary`/`--secondary`,
    `--icon-only`, and `__text`.
- **Why:** the author wants buttons with icons, a fixed primary and
  secondary for now, and icon-only buttons. Planned: a variant system
  any component can register, and a real `<button>` for scripts (see
  `open-questions.md`).

### D-190: Component media and links become full URLs
- **Date:** 2026-09-28
- **Decision:** A component's media and link props render as full URLs,
  like Markdown's own links and images (`ResolveLinks`), so they work in
  feeds.
  - `ComponentDirectives` makes a resolved `media` prop's URL (and any
    other `media` or `#[LinkProp]` value starting with `/`, not `//`)
    absolute with `AppConfig::absoluteUrl()`, following
    `MarkdownConfig::$absoluteLinks`. The new `#[LinkProp]` attribute
    (read by `ComponentDefinition::links()`) marks link props; `Button`'s
    `url` is one.
  - `MediaResolver` tries a relative path that isn't a bundle file from
    the site root, so `user/media/a.mp3` resolves like
    `/user/media/a.mp3` (in Markdown images too), and treats a full URL on
    the site's own origin as its path, so an absolute URL resolves again
    (for `File`'s size and `Video`'s poster size). It takes an optional
    `AppConfig` for that.
- **Why:** the author's `::audio{src=user/media/audio/…}` rendered as a
  relative path, which breaks in feeds and on other pages.

### D-191: Component variants (planned)
- **Date:** 2026-09-28
- **Decision:** A variant system for all components, planned and not yet
  built. A variant is a named visual style of a component.
  - **One variant per use** (`variant=ghost`), like WordPress's block
    styles. Extra looks come from classes (`.class`).
  - **The class is a BEM modifier:** `component-{name}--{variant}`
    (`component-button--ghost`), per D-182.
  - **Planned shape** (not settled in detail): a `VariantRegistry` maps a
    component's full name to its variants, each with its registrant (core,
    a theme's slug, `app`, or a vendor), whose catalog holds its label
    (`components.{name}.variants.{variant}.label`). Components declare
    built-in variants and a default (`button`: `primary`, the default, and
    `secondary`, replacing `ButtonVariant`). A theme's variants apply only
    while it (or a child) is active. The framework handles `variant` for
    every component: it checks the value against what the current chain
    offers, falls back to the default for an unknown one (so content
    written for another theme stays plain rather than broken), and gives
    the template `$variant` and the modifier class. `component:list` and
    the future inserter list the variants; `theme:check` warns about
    variants declared for unknown components.
  - **Variants are for looks only.** Props with meaning stay props:
    callout `tone`, gallery `columns`.
- **Open:** where themes declare their variants. The working assumption
  is `theme.json` (no PHP needed) plus PHP registration for extensions
  and the site; the author isn't sure yet (see `open-questions.md`).
- **Why:** the author wants variants any component can have, registered
  by core, themes, and extensions, rather than per-component enums
  (D-189).

### D-192: Components are a top-level subsystem
- **Date:** 2026-09-28
- **Decision:** Components move from `View\Component` to their own
  top-level namespace, `Blush\Component` (`src/Component/`), with their
  own `ComponentServiceProvider` (the registry, factory, and the default
  `DirectiveRenderer`). `ComponentDirectives` moves with them. `Views`
  and `Template` still render components; the dependency runs both ways
  (components render through `Views`). Tests and fixtures move to
  `tests/Component/` and `tests/Fixtures/Component/`. Earlier entries
  that name `View\Component` refer to the old location.
- **Why:** the component system has grown into its own subsystem (names,
  namespaces, definitions, built-ins, directives, and planned variants,
  D-191) that views use, and it will keep growing.

### D-193: The head prints full URLs
- **Date:** 2026-09-28
- **Decision:** `Head` takes the site's origin (`AppConfig::origin()`,
  passed by `ViewFactory`) and prints every root-relative `href` and
  `src` (`/feed`, `/page/2`, theme assets) as a full URL on it. Full,
  protocol-relative, and other URLs print as given. It happens at render
  time, so an item's key keeps the URL as added and
  `remove('style:' . $template->asset(...))` still works. Meta `content`
  (such as `og:image`) is left alone; whoever adds it passes a full URL.
- **Why:** the author wants no relative URLs in `<head>`. Doing it in
  `Head` covers the framework's tags, theme assets, and theme-added tags
  in one place.

### D-194: Flat `resources/`, plain built names, `?v={hash}` versions
- **Date:** 2026-09-28
- **Decision:** Supersedes parts of D-119 and D-155.
  - `ThemeAssets` versions every theme asset URL, manifest-built or not,
    with `?v=` and a CRC32 of the file's contents (`hash_file('crc32b')`,
    eight hex characters), memoized per instance. A file the manifest
    names that doesn't exist gets no version. mtimes are no longer used:
    they change on checkout and deploy when contents don't.
  - The recommended Vite build (docs, and jtcom's theme) keeps
    `resources/` flat: `scss/` and `js/` are built, and every other entry
    in `resources/` is copied to `public/` by a small inline plugin
    (`publicDir: false`). Built files keep plain names (`css/[name].css`,
    `js/[name].js`), and assets the CSS pulls in keep their
    `resources/`-relative path, so they land on their copies.
  - The same plugin adds `?v=` to the `url()`s in built CSS, with
    Node's `zlib.crc32` (Node 22.2+), which matches PHP's `crc32b`. So a
    preloaded font's URL is the same as the CSS's, and it downloads once.
- **Why:** the author wants a flat `resources/` and no hashed names in
  `public/`, with query-string cache busting instead.

### D-195: Component templates get one `$component` object
- **Date:** 2026-09-28
- **Decision:** A component's template gets `$component`, `$slot`,
  `$slots`, and `$template`, and nothing else. Props are no longer spread
  into variables, and `$props` and `Component::data()` are gone.
  Supersedes those parts of D-025, D-111, and the template-variable
  lists of D-179 to D-190.
  - **Props are the class's public properties** (`$component->tone`),
    typed by the constructor as before. Computed values are methods
    (`Embed::frameTitle()`, `File::details()`, `Meter::text()`), so the
    template does only markup. `@var Blush\Component\Callout $component`
    gives editors autocomplete.
  - **Every core component has a class.** The template-only four gained
    one: `Callout` (with `CalloutTone`), `Inline\Abbr`, `Media\Figure`,
    and `Media\Gallery`. `ComponentType::className()` is never `null`,
    and its `content()` and `props()` are gone. `Toc::$items` is a tree
    of `TocItem`s, drawn by the partial `components/toc/list.php`.
  - **The base `Component` handles the root element.** `Views` calls
    `attach()` with the name, every prop, and the chain's translator.
    Every component has the `class` and `id` props.
    `attributes(array $extra = [])` prints the escaped `class` (the
    `component-{name}` block, `modifiers()` as BEM modifiers, and the
    `class` prop), `id`, and `rootAttributes()` (such as a callout's
    `role`), plus any given (a given `class` is added to the rest;
    `null`, `false`, and `''` are left out; `true` prints the name).
    `classes()` and `block()` return the parts, `prop()` returns any
    prop as given, and `t()` translates from the theme's catalog as
    `$template->t()` does. `Component::html()` renders an attribute
    array; `Meter::gaugeAttributes()` and `Progress::barAttributes()`
    use it for their inner elements.
  - **Template-only components stay** for now. Their `$component` is a
    `TemplateComponent` (no props of its own), read with
    `$component->prop('tone', 'note')`. Whether every component should
    need a class is open (see `open-questions.md`).
  - Variants (D-191) will follow the same path: the base class will
    hold the checked `variant` and add its modifier in `classes()`.
  - The default theme's templates and the jtcom trial's use it. The
    trial's `jtcom/entry-terms` became a class (`Jtcom\View\EntryTerms`),
    and `PostArchives` has `groups()` and `count()` in place of
    `data()`. It builds the same 421 pages; the only change in the markup
    is the order of a gallery's classes.
- **Why:** the author wants editor autocomplete in component templates,
  and templates that are about markup, not logic. Loose variables,
  `$props` lookups with type checks, and fallback chains had crept into
  every template.

### D-196: Content and slots are on `$component`, named for their role
- **Date:** 2026-09-28
- **Decision:** Extends D-195. A component's template gets only
  `$component` and `$template`; `$slot` and `$slots` are gone.
  - **`content()`** returns the main content's HTML, matching how
    `PendingComponent` fills it (`->content()`). Named slots are
    **`$component->slots`**, a get-only property hook returning the
    `Slots` (`->footer`, `->has('footer')`), or an empty one before
    `attach()`. `attach()` takes them, as `(name, props, content,
    slots, translator)`.
  - **The language sets the rules, not the tools.** PHPCS 4.0.4 can't
    tokenize property hooks, so the hook sits between `phpcs:disable`
    and `phpcs:enable` with the reason (as `ListenerPriority` does for
    an enum). PHPStan reads `Slots` as an object crate
    (`universalObjectCratesClasses`), since slot names are dynamic.
  - **Content with a role gets a method named for the role**, not the
    element: `caption()` (`figure`, `embed`, `audio`, `video`), `text()`
    (`button`, `file`, `abbr`, `kbd`, `time`), `heading()` (`callout`,
    `toc`), and `Embed::linkText()`. Block components use `content()`.
    Themes choose the markup (`<figcaption>` or not).
  - **The label and the content are interchangeable for text.** A
    directive's label is plain text, and a `::`/`:` directive's content
    is that label escaped, so they always matched in Markdown; from a
    template, some components read `label:` and others `->content()`.
    Now each role method returns the content, else the `label` prop
    escaped (`Component::contentOr()`). `figure`, `audio`, `video`,
    `file`, `abbr`, and `time` gained a `label` prop for it. `Time`'s
    `text` property became `formatted`, and `text()` is the method.
  - **Rule for templates:** props are plain (print with `e()` or
    `attr()`); `content()`, slots, and role methods return HTML (print
    with `raw()`). Methods for attributes (`Embed::frameTitle()`,
    `Toc::navLabel()`) return plain text.
  - The jtcom trial builds byte-for-byte the same as with D-195.
- **Why:** the author asked why `$slot` wasn't on the component (it was
  the last loose variable, and nothing tied it to the component), and
  whether content should be named for its purpose, such as a caption.

### D-197: Sub-element attribute methods; `html()` escapes URLs
- **Date:** 2026-09-28
- **Decision:** Extends D-195.
  - **A component's inner elements can have attribute methods**, like
    the root's `attributes()`: `Embed::frameAttributes()` (the
    `component-embed__frame` class and `--embed-ratio`) and
    `Embed::iframeAttributes()` (`src`, size, `title` from
    `frameTitle()`, `loading`, `allow`, `allowfullscreen`,
    `referrerpolicy`), `Audio::playerAttributes()`,
    `Video::playerAttributes()` and `Video::captionsAttributes()` (the
    `<track>`), plus `Meter::gaugeAttributes()` and
    `Progress::barAttributes()`. Methods are named for the element's
    role (`frame`, `player`, `captions`), matching its BEM element. Each includes its BEM element class
    (`{block}__{element}`), so a template writes
    `<div <?= $component->wrapperAttributes() ?>>` with no stray space
    when the rest is empty. (As first written, the wrapper was
    `frameAttributes()`/`__frame` and the iframe `iframeAttributes()`
    with no class; D-198 renamed them.)
  - **`Component::html()` escapes URL attributes as URLs** (`action`,
    `cite`, `data`, `formaction`, `href`, `poster`, `src`, through
    `Escaper::url()`), and leaves out one with an unsafe scheme, so
    attribute methods can carry links safely.
  - **The jtcom trial uses the default `callout`, `embed`, `figure`, and
    `gallery` templates**; its copies are gone. Its SCSS follows:
    `.component-embed__wrapper` became `__frame`, and
    `.component-gallery` is a flex gallery sized by `--gallery-columns`
    (up to 2, 3, then all per row at its breakpoints), while 1.x's
    hand-written `.gallery` keeps its `--flex`/`--grid` and
    `.columns-{n}` rules. Its gallery's `layout` prop is dropped (no
    content used `layout=grid`). The build changes only the one gallery
    (a `<div>` with `--gallery-columns: 3`) and the embed frame's class.
- **Why:** the author asked for the embed template to be as simple as
  progress's, and for the trial theme to rely on the core templates
  rather than copies.

### D-198: Embed wrapper and frame, gallery layouts, the video's `track`
- **Date:** 2026-09-28
- **Decision:** Supersedes parts of D-197.
  - **Embed:** the `<div>` around the iframe is the wrapper,
    `wrapperAttributes()` with `component-embed__wrapper` (the name
    jtcom and 1.x's `.block-embed__wrapper` used), and the `<iframe>` is
    the frame, `frameAttributes()` with `component-embed__frame` (it had
    no class before). The default theme's and jtcom's CSS style
    `.component-embed__frame` itself.
  - **Gallery:** a `layout` prop, `GalleryLayout` (`flex`, the default,
    or `grid`), as the `component-gallery--flex`/`--grid` modifier.
    `flex` rows grow to fill the width; `grid` keeps even columns. Both
    read `--gallery-columns` through `--gallery-per-row`, which the
    default theme caps at 2 on narrow screens (jtcom: 2, 3, then all, at
    its breakpoints). Flex is the default because jtcom's template made
    it so and its one gallery relies on it; the default theme's plain
    galleries change from grid to flex.
  - **Video:** the WebVTT file prop is `track` (was `captions`, which
    read too much like the `caption()` method), with `trackLang` and
    `trackAttributes()` (`component-video__track`). The track stays
    `kind="captions"`, so `subtitles` would have named it wrongly.
- **Why:** the author's calls: "wrapper" for the wrapper, the gallery's
  layout prop is needed, and a clearer name for the captions file.


### D-199: Menus (planned)
- **Date:** 2026-09-28
- **Status:** Implemented in D-204, which refines it.
- **Decision:** A menu system, designed and not yet built.
  - **Storage:** one data file per menu, `user/data/menus/{name}.yaml`
    (or `.json`; JSON wins, D-032), read through `DataLoader`. This
    replaces the single `user/data/menus.*` file that `theming.md` and
    `paths.md` planned.
  - **Locations:** a theme declares its menu locations in `theme.json`
    `menus`. A location shows the site menu of the same name. An
    optional map in `user/data/theme.json` (`"menus": {"main":
    "primary"}`) points a location at another menu, so switching to a
    theme with different names doesn't mean renaming files. There is no
    WordPress-style assignment step.
  - **Items:** a list under `items`, each with at most one link source:
    `entry: {type}/{key}` (label and URL from the entry, so menus follow
    slug and permalink changes, and drafts and scheduled entries stay
    out until they go live), `term: {taxonomy}/{slug}`,
    `collection: {type}`, `route: {name}` (with `params`), or `url`. An
    item with only a `label` is a heading or group. Items may have
    `label` (overrides the derived one), `children`, `icon` (a
    namespaced icon name, D-187), `class`, and `rel`; there is no
    `target`. The menu may have its own `label` (the `<nav>`'s
    accessible name; else the theme's location label).
  - **Link kinds** follow D-019: a `MenuLinkType` enum, registry,
    factory, and registrar, with an abstract `MenuLink` and final kinds,
    so extensions can add their own.
  - **Runtime:** `Blush\Menu`, a top-level subsystem (like `Component`,
    D-192). A loader validates files into immutable `Menu` and
    `MenuItem` objects; `Menus` resolves links and caches the resolved
    tree (URLs and labels, not HTML) per content version in a `menus`
    namespace. The current item is marked at render time from the
    request path (`aria-current="page"` on the match, an ancestor
    modifier on its parents), so the view context needs the current
    path, which templates can't see today. A link that doesn't resolve
    is left out and logged.
  - **Template API:** a core `menu` component
    (`$template->component('menu', name: 'primary')`, and `::menu{name=…}`
    in content) with a `<nav>` and nested `<ul>` default template and
    `component-menu__*` classes (D-182); plus `$template->menu($name)`,
    which returns the `Menu` (or `null` when the location has none) for
    themes that write their own markup. Looks come from variants
    (D-191).
  - **Tooling:** `menu:list` (menus, locations, item counts, broken
    links), `menu:show {name}` (the resolved tree), and `theme:check`
    warnings for unfilled locations and menus no location uses.
  - **Later:** entries adding themselves from front matter (Hugo-style
    `menu:` and `weight:`).
- **Why:** the author's calls, walking through the design. jtcom's
  primary and social menus are hard-coded in its theme today.

### D-200: Mega menus: rich items now, panels later (planned)
- **Date:** 2026-09-28
- **Status:** Implemented in D-204, which refines it.
- **Decision:** Part of D-199.
  - **Rich items:** built-in optional `description`, `image`, `icon`,
    and `badge`.
  - **Theme-declared locations:** a `theme.json` `menus` entry is a label
    string or an object with `label`, `depth` (the deepest nesting the
    location shows), and `fields`: extra per-item fields in the content
    schema field types (as theme settings, D-117), validated by the
    loader and later used by the admin's item form. Layout values (such
    as panel columns) live there, not in core's item model.
  - **Markup:** items with children use the disclosure pattern (a
    `<button aria-expanded>` next to the parent's real link), never ARIA
    `role="menu"`. The framework ships markup and state hooks only;
    opening, closing, and focus behavior are the theme's.
  - **Later:** `panel: {entry}`, a Markdown entry (such as
    `_menus/tutorials.md`) rendered with components inside the
    dropdown, once a site needs it. Its links wouldn't get the current
    state, since the body is cached for every page.
- **Why:** rich items cover most mega menus for little cost; jtcom
  doesn't need panels.

### D-201: Regions, designed with menus (planned)
- **Date:** 2026-09-28
- **Status:** Implemented in D-204, which refines it.
- **Decision:** Replaces the regions plan in `theming.md` (contents in
  site config).
  - **Storage:** one data file per region, `user/data/regions/{name}.*`,
    matched to the theme's `regions` locations by name, with the same
    optional map in `user/data/theme.json` (`"regions": {…}`).
  - **Items:** a list under `items` (not "blocks"), each one of:
    `component: {name}` with its props as sibling keys; `markdown:`
    inline text; `entry: {type}/{key}`, an entry's rendered body (such
    as `_regions/about.md`); or `view: {name}`, a site or theme partial,
    with sibling keys as its data. Kinds follow D-019 (an enum,
    registry, factory, and registrar).
  - **Theme defaults:** a `theme.json` `regions` entry is a label string
    or an object with `label` and default `items`, shown when the site
    has no file for that region (a site file replaces them; no merge).
    Menus get no theme defaults: an unfilled menu renders nothing.
  - **Template API:** `$template->region($name)` returns the HTML
    (`''` when empty) and `$template->hasRegion($name)` guards
    wrappers. Markdown items render through the body cache per content
    version and theme; components render per request, so a menu in a
    region still marks its current item.
  - **Later:** per-page conditions (a sidebar only on posts). Until
    then, a theme's templates choose which regions they print.
- **Why:** the author wanted regions designed together with menus so
  both share one site-data and theme-declaration shape.

### D-202: Text in user data may be a locale map (planned)
- **Date:** 2026-09-28
- **Status:** Implemented in D-204, which refines it.
- **Decision:** In menus and regions (and any later user data), a text
  value is a string or a `{locale: text}` map (`label: {en: About,
  fr_CA: À propos}`). It's picked by the page's locale (the entry's,
  else the site's), falling back like message catalogs: the locale, its
  language, the site locale, then the first value. `entry:` references
  resolve to that locale's translation when one exists
  (`ContentRepository::named()` already takes a locale). Blush has no
  per-request locale yet; this rule applies once it does.
- **Why:** the author chose locale maps from the start, for every text
  value, so data files don't change shape when multilingual sites
  arrive.

### D-203: Brand icons come from themes
- **Date:** 2026-09-28
- **Decision:** Refines D-187's open item. Neither core nor the default
  theme ships brand logos. A theme that needs a social menu includes its
  brand SVGs in its own icon namespace (`jtcom/github`), which menu
  items reference by `icon`.
- **Why:** the author's call; brand logos carry usage rules and go
  stale, and they belong with the design that uses them.

### D-204: Menus and regions, implemented
- **Date:** 2026-09-28
- **Decision:** D-199 to D-203 are built, with these refinements:
  - **`Blush\Menu`:** `MenuLoader` (`user/data/menus/`; a file is
    `{label, items}` or a bare list of items), `MenuLocation`,
    `MenuFile`, `Menus` (resolves a location for a chain and locale,
    `locations()`, `menuName()`, `check()`), and the resolved `Menu` and
    `MenuItem` (`label`, `url`, `icon`, `description`, `image`, `badge`,
    `class`, `rel`, `fields`, `children`, `current`, `ancestor`;
    `Menu::forPath()` marks the current item). Link kinds:
    `Link\MenuLinkType` (`entry`, `term`, `collection`, `route`, `url`),
    the abstract `MenuLink` (`validate()`, `resolve()` → `LinkTarget`,
    throwing `UnresolvedLink`; `keys()` for extra keys such as a route's
    `params`), registry, factory, and registrar.
  - **`Blush\Region`:** `RegionLoader`, `RegionLocation`, `RegionFile`,
    `Regions` (`render()`, `has()`, `items()`, `check()`), and
    `Item\RegionItemType` (`component`, `entry`, `markdown`, `view`),
    the abstract `RegionItem`, registry, factory, and registrar. Items
    render with a `RegionRender` (the views, the page's context, and
    the locale).
  - **Caching:** resolved menus are kept per theme, location, and
    locale for the process only, not in a `menus` cache namespace as
    D-199 planned; resolving is a few index lookups, and the page cache
    keeps whole pages. Region `markdown` items use the body cache.
  - **Problems don't break pages:** an item that doesn't resolve or
    render is left out and logged (warning), and `menu:list`,
    `menu:show`, and `theme:check` report the same problems. Invalid
    location declarations are errors (`MenuException`,
    `RegionException`, and `ThemeManifest` checks that `menus` and
    `regions` map names to labels or objects).
  - **`theme:check`** reports menu and region problems and site menus
    and regions no location shows (notices), but not unfilled locations
    (an empty location is normal); `menu:list` shows those.
  - **Item keys:** a key that isn't a built-in option, the link, the
    link's own keys, or a field the location declares is a problem.
    Declared fields resolve through a content `Schema`; a value that
    doesn't fit gets the default.
  - **Entry references** are `{type}/{key}`; `{type}/` (or `{type}`) is
    the landing page. Region text for longer content lives in a hidden
    `_regions/` folder (`entry: page/_regions/about`).
  - **The page's path and locale** are on `ViewContext` (`path`,
    `locale`: the entry's, else the site's), set by the page and error
    renderers; `ViewFactory::context()` takes the path. Components get
    the render's context through `attach()` and read it with the
    protected `Component::context()`.
  - **Template API:** `$template->menu($location)` (a `Menu` marked for
    the page, or `null`), `$template->region($location)`, and
    `$template->hasRegion($location)`. `ViewServices` carries `Menus`
    and `Regions`.
  - **The `menu` component** is core (`ComponentType::Menu`, class
    `Component\Menu`): props `name` (the location), `label`, and `menu`
    (a `Menu` object, from templates). Its root is
    `component-menu component-menu--{location}` with `aria-label`;
    attribute methods `listAttributes()`, `itemAttributes()`
    (`--current`, `--ancestor`, `--parent`), `linkAttributes()`
    (`aria-current="page"`; a heading `<span>` for items without a
    link), and `toggleAttributes()` (a `hidden` disclosure button with
    `aria-expanded` and `aria-controls`, and the theme's `menu.toggle`
    text). In Markdown it renders once for every page, so nothing is
    marked current.
  - **Location labels name the `<nav>`**, so they should be short and
    leave out "navigation" ("Primary", not "Primary navigation").
  - **Locale maps (`Translation\LocaleMap`):** a map counts as one when
    every key looks like a locale and every value is a string; keys are
    normalized (`fr-CA` → `fr_CA`); `resolve()` replaces maps at any
    depth in props and view data.
  - **The default theme** declares a `primary` menu (in its header) and
    a `footer` region (above the credit), with plain CSS for menus
    (submenus open, no script).
  - **CLI:** `menu:list [--theme]` and `menu:show <location> [--theme]
    [--locale]`. No region command yet; `theme:check` covers regions.
  - **The jtcom trial** uses `primary` and `social` menus in
    `user/data/menus/`, printed with the core `menu` component and its
    default markup (the primary menu gets `class: 'hidden'` for its
    toggle script); its SCSS targets `component-menu--primary` and
    `component-menu--social` and their `__list`, `__item`, `__link`,
    and `component-icon` elements, with each item's `class`
    (`is-github`) setting its brand color. Its brand SVGs are in
    `resources/svg/icon/`, added to the `jtcom` icon namespace by its
    `ThemeProvider` through `IconRegistry` (`jtcom/github`, D-203); the
    `menu-social` partial is gone. Its build still makes 421 pages.
- **Why:** the author asked to build the D-199 to D-203 design.

### D-205: Directive attributes stay `key=value`, not JSON
- **Date:** 2026-09-28
- **Decision:** Directive attributes keep the D-112 syntax
  (`{columns=3 .stretch-wide}`) rather than JSON
  (`{"columns": 3, "class": "stretch-wide"}`). Types come from the
  component's props (`ComponentFactory` casts strings to scalars and
  enums), not from the author's punctuation. Richer props (arrays and
  maps) will come through additive changes to this syntax, most likely
  dotted keys and multi-line attributes (see `open-questions.md`).
- **Why:** the author prefers the current syntax. It's the common
  generic-directives and attributes form (remark-directive, Pandoc,
  MyST), easy to write by hand, and a sketch of a complex component (a
  breadcrumbs block with `icons` and `taxonomies` maps) fit it with
  dotted keys, so nothing forces a switch.

### D-206: Editor JSON Schemas for `theme.json` and `extension.json`
- **Date:** 2026-09-28
- **Decision:** The framework ships JSON Schemas so editors autocomplete
  and check manifests, starting with `theme.json` and `extension.json`.
  - **Generated, not hand-written:** `Blush\JsonSchema\JsonSchemas`
    builds them, `composer schemas` (`scripts/build-schemas.php`) writes
    them to `resources/schemas/`, and they're committed. A test fails
    when a committed file is stale, and another when a built-in field
    type has an option its schema doesn't describe.
  - **Field definitions** (theme `settings`, menu location `fields`) come
    from the field types: `Field::definitionSchema()` (static, default
    `[]`) returns a type's own keys, applied with `if`/`then` on `type`;
    `FieldType::description()` describes each type. `type` also accepts
    any string, for extension field types, whose options aren't
    described.
  - **Open, like the manifests:** keys a schema doesn't describe are
    allowed (both manifests ignore or keep them). Draft 7, for the widest
    editor support. `$schema` is a described key; the manifests already
    accept it, so no loader change was needed.
  - **How sites find them:** through `vendor/` (`Framework::PACKAGE`,
    `blush-dev/framework`), so they work offline and match the installed
    version. `theme:new` writes a relative `$schema` key; the default
    theme's points at `../../schemas/`. YAML manifests use a
    `# yaml-language-server: $schema=…` comment. The skeleton's
    `.vscode/settings.json` maps `user/themes/*/theme.*` and
    `user/extensions/*/extension.*` by glob.
  - **Later:** schemas for other data files (menus, regions, content
    types, redirects, site theme settings), site-generated schemas that
    know the active theme's settings and an extension's field types, and
    public URLs (and SchemaStore) once the product name is final (D-038).
- **Why:** the author asked for editor autocomplete. Generating from the
  PHP definitions keeps the schemas from drifting from the field-type
  system.

### D-207: Editor JSON Schemas for menu and region files
- **Date:** 2026-09-28
- **Decision:** Extends D-206 to the site's `user/data/menus/*` and
  `user/data/regions/*` files (`menu.schema.json`,
  `region.schema.json`).
  - **The generator is `Blush\JsonSchema\JsonSchemas`**, renamed from
    `ManifestSchemas` now that it covers more than manifests.
  - **Kinds describe themselves**, as field types do: static
    `MenuLink::itemSchema($key, $text)` and
    `RegionItem::itemSchema($key, $text)` return schemas for the item
    keys a kind reads (its own key, plus `keys()` for links, such as a
    route's `params`), keyed by the name it's registered under. The
    default is a non-empty string. `$text` is the schema for text or a
    locale map (a `localeText` definition, from the now-public
    `LocaleMap::LOCALE`). Tests check each built-in kind's schema keys
    against the keys it reads.
  - **Shape:** a file is an object with `label` (menus) and `items`, or
    the list on its own. The object is closed, like the loaders, which
    now also allow a `$schema` key. Items are open: a menu item's other
    keys are the theme's fields, and a region item's are a component's
    props or a view's data. "One link per item" and "one kind per region
    item" are left to `menu:list` and `theme:check`, since extension kinds
    can't be listed.
  - `theme.schema.json`'s region items (`regions.*.items`) now use the
    same generated definition.
  - The skeleton's `.vscode/settings.json` maps both folders. The jtcom
    trial references the schemas in its `theme.json` and menu files.
- **Why:** the author asked for menus and regions next.


### D-208: Schema patterns write a backslash as `\x5C`
- **Date:** 2026-09-28
- **Status:** Superseded by D-209.
- **Decision:** A JSON Schema `pattern` that matches a literal backslash
  (class names, PSR-4 prefixes) writes it as `\x5C`, never `\\`. A test
  checks every pattern in the generated schemas.
- **Why:** PhpStorm's `adaptSchemaPattern()` replaces every `\\` in a
  pattern with `\` before compiling it, so `^\\?[A-Za-z_]…` became
  `^\?[A-Za-z_]…` and rejected jtcom's valid `"provider":
  "Jtcom\\ThemeProvider"`. `\x5C` means a backslash in both ECMAScript
  and Java regexes, and PhpStorm leaves it alone (checked against its
  bundled JSON plugin).

### D-209: Schema patterns match one backslash or two
- **Date:** 2026-09-28
- **Status:** Superseded by D-210.
- **Decision:** Supersedes D-208. A pattern that matches a literal
  backslash writes it as `\x5C{1,2}` (`JsonSchemas::BACKSLASH`): one
  backslash, as standard validators see the decoded value, or two, as
  PhpStorm sees it. The test that no pattern contains `\\` stays.
- **Why:** D-208's `\x5C` still failed in PhpStorm. Its string check
  (`StringValidation`) reads a value's raw JSON text with
  `unquoteString`, which strips the quotes but not the escapes, so
  `"Jtcom\\ThemeProvider"` is checked as `Jtcom\\ThemeProvider`.
  Checked by running PhpStorm's own `compilePattern()` and
  `matchPattern()` on the raw values: the D-208 pattern fails there, and
  this one matches while still rejecting names that aren't classes. The
  cost is that a doubled backslash (`Acme\\\\Gallery` in JSON) also passes
  in other editors; the manifest classes still reject it.

### D-210: No patterns for class names or namespace prefixes
- **Date:** 2026-09-28
- **Decision:** Supersedes D-208 and D-209. The schemas describe
  `provider` and `autoload.psr-4` (theme and extension) as plain strings
  and objects, with descriptions and examples, but no `pattern` or
  `propertyNames`, as Composer's own schema does for `autoload`. The
  manifest classes (and so `theme:check` and discovery) still reject
  bad class names and prefixes. The test that no pattern contains `\\`
  stays, so a backslash pattern doesn't come back by accident.
- **Why:** the author pointed out that Composer's schema has no trouble
  with backslashes in PhpStorm. It avoids backslash patterns entirely,
  and PhpStorm reads both patterns (`\\` becomes `\`) and values (raw
  JSON text, escapes kept) differently from other validators. Working
  around both was fragile, and it loosened the check anyway.

### D-211: A front matter schema for the built-in entry fields (trial)
- **Date:** 2026-09-28
- **Decision:** `resources/schemas/entry.schema.json` describes the
  built-in entry fields (`EntryFields`), to find out whether editors can
  check front matter. It's open, since types and taxonomies add fields.
  - **Fields describe their values:** `Field::valueSchema()` (the
    field's label, description, and default over the protected
    `valueType()`, which defaults to anything). A value schema may be
    looser than `normalize()`, never stricter: text takes numbers, a list
    or reference takes one value or a list, a date is text starting
    `YYYY-MM-DD` or a timestamp, a bool takes the words Blush accepts, and
    media and slugs are plain strings (Blush treats `''` as missing, and
    slugs may be Unicode). `Schema::jsonSchema()` lists each field by
    name and alias, and is closed when the schema is.
  - `EntryFields` now describes each field (the `docs/content.md`
    wording), for the schema and the future admin.
  - Checked against all 300 jtcom trial entries: none are flagged.
  - **Result: not reliable, so on hold.** On the trial's test page,
    PhpStorm seemed to apply it (it flagged `status: pending` and
    `published: yesterday`, after D-212). Added to every trial entry, it
    didn't appear to apply, and a `category` error came from another
    schema (most likely WordPress's `block.json`, which PhpStorm had
    cached). The comments were removed from the trial's content, and
    there are no user docs. `entry.schema.json`, `Field::valueSchema()`,
    and `Schema::jsonSchema()` stay, for data entries and per-site
    schemas later. Not tried: a PhpStorm path mapping
    (`user/content/**/*.md`).
  - **Was open:** whether PhpStorm applies a `# yaml-language-server:
    $schema=…` comment inside Markdown front matter (it reads that
    comment in YAML files, and its Markdown plugin injects YAML into
    front matter). The trial has `user/content/_scratch/schema-test.md`
    to check. User docs wait until that's known. Per-site schemas (a
    type's fields and term fields) and content type definitions come
    after.
- **Why:** the author asked to see whether front matter can be handled.


### D-212: Type-specific keywords never sit beside a type list
- **Date:** 2026-09-28
- **Decision:** A schema that pairs a keyword for one type (`pattern`,
  `minLength`, `minimum`, `items`, `properties`, and the like) with a
  type list writes an `anyOf` with one branch per type instead. A date
  is `anyOf: [{type: string, pattern}, {type: integer}]`, not `type:
  [string, integer]` plus `pattern`. A test walks every generated schema
  for type lists with such keywords.
- **Why:** in the D-211 trial, PhpStorm applied the entry schema in
  Markdown front matter (it flagged `status: pending` and autocompleted
  keys) but didn't flag `published: yesterday`. PhpStorm's own regex
  code rejects `yesterday` with that pattern, so the check never ran:
  its validators are picked by a single type
  (`getTypeValidations(JsonSchemaType)`), and a type list has none.

### D-213: Menus and regions stay separate
- **Date:** 2026-09-29
- **Decision:** Closes the open question from D-204. Menus and regions
  are two distinct things, as D-199 and D-201 designed them: a menu is
  link data shown in a menu location, and a region is an ordered list of
  items shown in a region location. Menu locations and region locations
  stay separate, and the admin will have a menu editor and a region
  editor. A region can still show a menu through the `menu` component.
- **Why:** the author's call.

### D-214: Only components with a class are offered in the admin
- **Date:** 2026-09-29
- **Decision:** Answers D-195's open question for the admin. A component
  must have a class to be offered by the admin's component inserter
  (the inserter reads its props from the constructor, D-173).
  Template-only components (`TemplateComponent`) still render wherever
  they're written by hand, but the admin doesn't list them.
- **Why:** the author's call; the admin needs typed props to build its
  forms.

### D-215: The admin is a JavaScript single-page application
- **Date:** 2026-09-29
- **Decision:** The admin (M9 and M10) is a single-page application
  built in JavaScript, not server-rendered PHP pages. The front-end
  library, and how the SPA talks to the server, are still open (see
  `open-questions.md`). Component variants (D-191) are deferred until
  after the admin's groundwork.
- **Why:** the author's call.

### D-216: Admin accounts, linked to authors, with roles
- **Date:** 2026-09-29
- **Decision:** Refines D-013's stage 2 auth ("password hashes in env or
  config") and D-043's note on accounts. Details are still being
  designed.
  - **Several accounts.** A person who signs in to the admin is an
    **account** (the name avoids clashing with `user/` and the `author`
    type). Accounts live outside `user/` and git, created and managed
    from the CLI (`account:*` commands); the working plan is one file per
    account in `storage/`.
  - **Accounts are tied to author entries.** An account links to an
    entry of the built-in `author` type (D-043), which gives it its
    public name and profile.
  - **Roles and capabilities.** An account has one or more roles; a role
    holds capabilities; an account can do what any of its roles allows.
- **Why:** the author's calls.

### D-217: Account, role, and first-account details (planned)
- **Date:** 2026-09-29
- **Decision:** Settles D-216's open details.
  - **Storage:** `storage/accounts/{username}.json` (password hash,
    optional author, roles, created and last-login times; passkeys
    later). Kept out of git and `user/`; `cache:clear` leaves it.
  - **The author link is optional.** An account without one (a
    developer's, or any account when the site disables the `author`
    type) owns no entries. With one, an entry is the account's own when
    its `authors` field includes that author, and new entries default to
    it.
  - **Capabilities** are dotted names (`content.edit`,
    `content.edit.others`, `content.publish`, `media.upload`,
    `menus.edit`, `regions.edit`, `site.publish`, `cache.clear`,
    `accounts.manage`, `site.settings`), registered with translatable
    labels so extensions can add their own. One set covers all content
    types for now; per-type capabilities later if a site needs them.
  - **Roles:** built-in `administrator` (every capability, including
    ones added later), `editor`, `author` (own entries, media), and
    `contributor` (own drafts, never publishes). Custom roles, and
    changes to the built-ins, go in `config/auth.php`, not `user/`: they
    control security, so the webhook's pull must not reach them.
  - **Checks:** `$account->can($capability, $entry)` (the entry turns
    on ownership), a route middleware, and a 403 JSON answer from the
    API; the SPA reads the account's capabilities from its `me`
    endpoint.
  - **The first account:** created with `init` (or `account:add`) on a
    machine with a shell, and uploaded with `storage/accounts/` on hosts
    without SSH (D-040). A one-time web setup screen, shown only while
    no accounts exist and guarded by a setup token from `.env`, comes
    with the admin.
- **Why:** the author's calls.

### D-218: First-run setup: `init`, `doctor`, and the setup page
- **Date:** 2026-09-29
- **Decision:** The first part of the setup work (D-156), ahead of auth.
  - **`init`** (`SetUpSite`) is safe to run again. Without a `.env` it
    writes one from `.env.example` (or built-in defaults), asking in a
    terminal for the name, URL, timezone, and environment (development
    also sets `APP_DEBUG=true`). An existing `.env` is never changed,
    except to add a secret it lacks. It creates the storage folders
    (`SetupChecks::STORAGE`) and fails when one isn't writable.
  - **The webhook stays opt-in.** `init` adds a random 64-hex-character
    `PUBLISH_SECRET` only with `--webhook` or a yes when asked (default
    no), since a secret turns on a public endpoint. No `APP_SECRET` yet:
    nothing uses one until signed previews or sessions need it.
  - **`Env\EnvFile`** edits `.env` as text: it rewrites the last line
    that sets a variable (keeping `export` and double quotes) or appends
    one, quoting so `EnvParser` reads the value back, and refuses to
    rewrite a value that spans lines.
  - **`doctor`** (`CheckSite`) runs `Setup\SetupChecks::all()`: PHP 8.5,
    `dom`, `intl`, and `mbstring`; `.env` (a warning when missing, since
    a host may set the environment itself); in production, `APP_DEBUG`
    on (failure) and a local `APP_URL` (warning); `public/index.php`
    (failure) and `public/.htaccess` (warning); and each storage path
    (writable, or creatable under a writable parent). Hints say what to
    do; any failure fails the command. There's no opcache check: the
    CLI's PHP isn't the web server's.
  - **The setup page:** `HttpRunner::handle()` runs only the storage
    checks, before the application loads (a few `stat` calls), and
    answers any failure with `SetupPage`: a self-contained 503
    (`no-store`, `Retry-After`) listing each problem and its fix, with
    paths relative to the root. A missing `.env` isn't a setup problem.
  - `composer.json` now requires `ext-dom` (Blush already used `Dom\`).
  - **The skeleton** (its `2.x` branch, not changed here) should run
    `@php bin/blush init` from `post-create-project-cmd`, and its
    `.env.example` may mention `PUBLISH_SECRET`.
- **Open:** a read-only deployment (a container with an unwritable
  filesystem) would get the setup page, though it can run without
  writing; if one is ever needed, a way to skip the check.
- **Why:** the author picked first-run setup and auth as the next work
  (D-156, D-215 to D-217).

### D-219: Auth groundwork: sessions, accounts, roles, and the admin's sign-in API
- **Date:** 2026-09-29
- **Decision:** Builds D-215 to D-217 without a UI. Refinements:
  - **Sessions (`Blush\Session`)** are Blush's own, not PHP's
    `session_*()` (they'd need superglobals and global state). `Session`
    is mutable, like PHP's, so a login changes what `StartSession` saves.
    Ids are 32 random bytes; files in `storage/sessions` are named by the
    id's SHA-256 and written `0660`. A session is dropped after `idle`
    (2 hours) or `lifetime` (12 hours). **A new session is saved only
    once something is stored**, so anonymous admin requests leave no
    file and no cookie. The cookie is a browser-session cookie,
    `HttpOnly`, `SameSite=Strict`, and over HTTPS `Secure` with the
    `__Host-` prefix (plain `blush_session` over HTTP, for local work).
    Pruned by a 1-in-100 lottery and by `schedule:run`.
  - **CSRF (`VerifyCsrf`)** for anything but `GET`, `HEAD`, and
    `OPTIONS`: `Sec-Fetch-Site` must be `same-origin` or `none` and
    `Origin` the site's or the request's own, when sent; once signed in,
    `X-CSRF-Token` must match the session's token. **Signing in has no
    token** (there's no anonymous session to hold one), so it relies on
    the origin checks and the `SameSite=Strict` cookie. Clients that send
    neither header (curl) pass the origin layers.
  - **Accounts** live in `storage/accounts/{username}.json` (`0660`;
    `Paths::$accounts`, a new path). `Account` holds the hash, roles,
    optional author, and times. `Accounts` creates and changes them with
    checks (a free username, `minPasswordLength` 12, roles that exist).
    `Passwords` uses Argon2id where available, rehashes old hashes at
    sign-in, and verifies against a dummy hash for unknown usernames.
  - **Permissions** are a service, `Permissions::can($account,
    $capability, ?$entry)`, not a method on `Account` (which stays plain
    data). With an entry: an entry not the account's own also needs the
    capability's `.others` form, and editing or deleting a non-draft also
    needs `content.publish` (keeping contributors to drafts). Ownership
    is the entry crediting the account's author in
    `AuthConfig::$authorTaxonomy`. An unknown role grants nothing.
    `capabilities()` lists what an account has, for the admin.
  - **Capabilities** (`Capabilities`, a registry of names and English
    labels; built-ins in the `Capability` enum, 15 of them) and **roles**
    (`BuiltInRole`: `administrator` is `*`; `editor`; `author`;
    `contributor`) as in D-217. Labels aren't translated yet.
  - **Sign-in (`Authenticator`)**: usernames are lowercased. A login
    regenerates the session id and stores the username, a SHA-256
    fingerprint of the password hash (so a password change signs out
    every session), and a CSRF token. `LoginThrottle` counts failures in
    a new persistent `logins` cache namespace (never cleared): 5 per
    address and username, 20 per address, locked for `lockout` (900 s)
    from the first counted failure. No lock, so parallel requests can
    add a few guesses. The address is `REMOTE_ADDR` (`ClientIp`), so
    behind a proxy it's the proxy's until trusted proxies exist.
  - **The admin API (`Blush\Admin`)**, only while `AdminConfig::$enabled`
    (off by default; `path` `/admin`): `GET {path}/api/session` (the
    account, its capabilities, and the CSRF token, or `account: null`),
    `POST {path}/api/login` (JSON `username` and `password`; 400, 401,
    429 with `Retry-After`), and `POST {path}/api/logout` (204). All
    `no-store`.
  - **CLI:** `account:add` (administrator by default; `--role` repeats;
    `--author`), `account:list`, `account:password`, `account:roles`,
    `account:author`, and `account:remove`. Passwords are asked twice,
    hidden (`Prompt::newSecret()`), so they need a terminal. `init`
    offers an administrator while there are no accounts. A missing
    author only warns; an author counts when entries credit it, even
    without an entry file (jtcom's `justintadlock` is virtual).
  - **Other:** `Cookie`, `SameSite`, and `Response::withCookie()`;
    `Filesystem::writeAtomic()` takes a mode; `SetupChecks` covers
    `storage/accounts`.
  - **The jtcom trial** has the admin on (`config/admin.php`) and a
    `justintadlock` administrator (linked to the `justintadlock` author); signing in and out
    over `bin/blush serve` was checked with curl. Its `composer.json`
    runs `init` after `create-project` and has a `doctor` script (not
    `init`, which would shadow Composer's own command), and its
    `.env.example` lists an empty `PUBLISH_SECRET`.
- **Open:** a route middleware that checks a capability (route
  middleware are class names without arguments today); rate limiting
  for the rest of the API; trusted proxies; passkeys; the web setup
  screen for the first account (D-217); translated capability labels.
- **Why:** the author asked to start on auth after first-run setup.

### D-220: The admin SPA talks to a private JSON API
- **Date:** 2026-09-29
- **Decision:** Answers half of D-215's open question. The admin SPA uses
  a private JSON API under `{path}/api` (session cookie, `X-CSRF-Token`
  header), grown from D-219's sign-in endpoints, not an Inertia-style
  protocol. Each endpoint group checks `Permissions`. The front-end
  library is still open.
- **Why:** the author's call. The admin will be app-like (a Markdown
  editor with live preview, a component inserter, uploads, reordering),
  the API is testable through `Kernel::handle()`, and other clients (the
  CLI, other tools) can use it later. Inertia would add a server adapter
  and tie the protocol to one front end.

### D-221: The admin is built with Vue
- **Date:** 2026-09-29
- **Decision:** Answers the rest of D-215's open question. The bundled
  admin is Vue 3 (`<script setup>`, TypeScript), built with Vite, with
  Vue Router; Reka UI for accessible widgets when they're needed.
  Sources live in the framework's `resources/admin/`, and the build goes
  to its `public/admin/`, committed, so sites never need Node.
- **Why:** the author's criteria, in order: what the wider PHP community
  uses (Kirby's Panel and Statamic's control panel are Vue; Laravel has
  long paired with Vue), then modern, documented, and easy to use
  (official router and docs; HTML-like templates suit PHP developers).
  The author's own familiarity with React wasn't a factor.

### D-222: Replaceable admin; extensions and themes describe their pieces in PHP
- **Date:** 2026-09-29
- **Decision:** Two rules for everything the admin grows:
  - **Others can build their own admin.** The JSON API (D-220) is the
    contract, documented for that. `AdminConfig::$app` points the admin
    at another built front end (a folder with a Vite manifest) in place
    of the bundled one, and a front end can live anywhere that can reach
    the API.
  - **Extensions and themes never need JavaScript to appear in the
    admin.** They describe their pieces in PHP, and the admin renders
    them generically from the API's descriptions: actions
    (`AdminAction` classes), capabilities (D-219), component props from
    constructors (D-214), and later screens and forms from content
    schema fields (as theme settings and menu fields already are). A JS
    extension point may come later as an option, never a requirement.
- **Why:** the author wants custom admins possible and extension and
  theme developers to stay in PHP.

### D-223: The admin app's first slice: shell, sign-in, dashboard, actions
- **Date:** 2026-09-29
- **Status:** Partially superseded by D-224 (plain file names), D-231 (design tokens, not `light-dark()`; the rail layout), and D-235 (the shell reads the session, without starting one).
- **Decision:** Builds on D-219 to D-222.
  - **The shell (`ShellController`)** answers `GET {path}` and
    `{path}/{screen}` (any path but `api/` and `assets/`, so unknown API
    paths stay 404s) with one page: the Vite entry's script and styles
    from `.vite/manifest.json`, and a JSON block
    (`#blush-admin-config`: `base`, `api`, `site`). Headers: `no-store`,
    a CSP of `'self'` only (Vue's runtime build needs no `eval`),
    `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, and
    `X-Robots-Tag: noindex`. No session. Without a build it's a 503.
  - **Assets (`AssetController`, `AdminApp`)** come from the build's
    `assets/` only (js, css, svg, png, webp, woff2), cached a year as
    immutable, since Vite hashes their names. `AdminApp::BUNDLED` is the
    framework's `public/admin`; `AdminConfig::$app` (absolute) replaces
    it.
  - **API additions:** `GET dashboard` (site name, URL, environment,
    content version; entry counts by status over every entry; the
    account's actions) and `POST actions/{name}` (404 unknown, 403
    without the capability, else the `ActionResult`, 200 even when the
    action failed).
  - **Actions (`Blush\Admin\Action`)**, D-019's pattern: the abstract
    `AdminAction`, `AdminActionType` (built-ins), `AdminActionRegistry`
    (seeded in the provider with `registerIf`), and `AdminActions`
    (builds through the container). Built-ins: `publish` (the
    `Publisher`; `site.publish`), `reindex` (the `Indexer`, bumping the
    content version when the index changed; `site.publish`), and
    `clear-caches` (the store and the content version, not compiled
    files, which are deploy artifacts; `cache.clear`). Export isn't an
    action yet: a full build can outlast a request.
  - **The Vue app** (Vue 3.5, Vue Router 5, Vite 8, TypeScript 6 with
    `vue-tsc`; TypeScript 7's native compiler isn't used yet, since
    `vue-tsc` builds on the TypeScript API): `config.ts` (the start-up
    block), `api.ts` (fetch with the cookie and `X-CSRF-Token`,
    `ApiError`), `session.ts` (shared sign-in state), `router.ts` (history
    routing under `base`; a guard sends signed-out visitors to sign-in
    with `next`, which only follows paths inside the admin), the layout
    (skip link, navigation, sign-out), and the sign-in, dashboard, and
    not-found screens. Accessibility: labeled fields with autocomplete,
    errors in `role="alert"`, action results in polite live regions,
    focus on the new screen's `h1` after each navigation, visible focus,
    and light and dark colors through `light-dark()`. Confirmations use
    `window.confirm()` for now; a Reka UI dialog when one is needed.
  - `package.json` at the framework root (`admin:build`, `admin:watch`,
    `admin:check`); `resources/admin`, `package.json`, and
    `package-lock.json` are left out of Composer archives; the build is
    committed (94 KB of JS, 36 KB gzipped).
  - **Checked** on the jtcom trial in headless Chrome (sign-in with a
    wrong and a right password, the dashboard, clearing caches, dark
    mode, sign-out) and with curl.
- **Open:** a capability middleware; JS tests for the app (none yet);
  how a site's own admin screens and forms get described in PHP (next,
  from content schema fields, D-222).
- **Why:** the author asked to move forward with Vue (D-221) under
  D-222's rules.

### D-224: The admin build uses plain names and `?v=` versions
- **Date:** 2026-09-29
- **Decision:** Supersedes D-223's hashed file names. Every Vite build
  follows D-194 (the jtcom theme's build), the admin's included:
  - **Sources** are flat folders in `resources/admin`: `js/` (the entry
    `admin.ts`, the Vue files) and `css/admin.css`. No `src/`.
  - **Output** keeps plain names: `js/[name].js` (entries and chunks),
    `css/[name].css`, and any other file (fonts, images) at its
    `resources/admin`-relative path; the rest of `resources/admin` is
    copied as it is. A small plugin adds `?v={crc32}` (Node's
    `zlib.crc32`, matching PHP's `crc32b`) to the `url()`s in built CSS.
  - **PHP versions the URLs:** `AdminApp::url()` prints
    `{path}/assets/{file}?v=` and a CRC32 of the file's contents, so
    `ShellController` needs no hashes in names.
  - **Serving:** `AdminApp::asset()` serves any file of an allowed type
    anywhere in the build folder, except under dot folders (`.vite/`);
    `AssetController` caches a `?v=` URL for a year as immutable, and an
    unversioned one with `no-cache`.
  - Custom admins (`AdminConfig::$app`) get the same versioning; their
    docs recommend plain names.
- **Why:** the author's rule: scripts and assets built with Vite always
  use `?v={hash}`, never hashed file names.

### D-225: Admin screens for drafts and content health
- **Date:** 2026-09-29
- **Status:** Partially superseded by D-236 (no Drafts screen; drafts and scheduled entries are tabs on each list).
- **Decision:** The next M9 pieces (D-013's "content health (lint),
  drafts and scheduled lists").
  - **`GET {path}/api/entries?status=draft|scheduled`**
    (`EntriesController`): entries with that status that
    `Permissions::can($account, 'content.edit', $entry)` allows, so an
    author or contributor sees their own drafts and an editor everyone's;
    a contributor sees no scheduled entries, since they aren't drafts
    (D-219's live-entry rule). Drafts sort by `updated` descending,
    scheduled entries by `published` ascending. Each entry: id, title,
    type name, status, `published` and `updated` (ISO 8601), source
    path, authors (`AuthConfig::$authorTaxonomy` terms), and `own`. Any
    other status is a 400 (published entries wait for the editor).
  - **`GET {path}/api/health`** (`HealthController`): the `Linter`'s
    report, warnings and errors by default and notices with
    `?strict=1`, as `{checked, strict, counts, files: [{path,
    violations: [{field, message, severity}]}]}`. It needs
    `content.edit.others`, since it lists every file. It lints on each
    request (about 0.2 s for the trial's 300 files), so the dashboard
    doesn't run it.
  - **The app:** "Drafts" (two tables, drafts and scheduled, with a
    "Yours" tag, `<time>` dates in the browser's locale) and "Content
    health" (runs on opening, "Check again", an "Include notices"
    checkbox, the summary in a polite live region, severity written as
    text as well as color). Navigation and routes show only what the
    account's capabilities allow (`meta.capability`; a screen it can't
    use sends it to the dashboard); the API checks every request anyway.
    The dashboard's draft and scheduled counts link to the Drafts screen.
  - Checked on the jtcom trial in headless Chrome with three temporary
    entries (removed afterward).
- **Open:** links from a draft to its preview (signed preview URLs, next)
  and to its editor (M10).
- **Why:** the author asked for content health and drafts next.

### D-226: Signed preview links
- **Date:** 2026-09-29
- **Decision:** D-013's "signed preview URLs", in `Blush\Preview`.
  - **The secret is `APP_SECRET`**, read by `PreviewConfig::fromEnv()`
    (`config/preview.php` overrides, as `PublishConfig` does), not an
    `AppConfig` option: sites build `AppConfig` in their own
    `config/app.php`, which wouldn't pass a new option. At least 32
    characters; without it, preview links are off (no route, the admin
    endpoint answers 503, the command fails). `init` always adds one
    when it's missing (unlike `PUBLISH_SECRET`, it opens nothing by
    itself). Changing it ends every link.
  - **Links** (`PreviewLinks::make()`): `{path}?entry={id}&expires={unix}
    &signature={hmac}`, absolute on the site's origin; the signature is
    HMAC-SHA256 over `"preview\n{id}\n{expires}"`, checked with
    `hash_equals`. `path` defaults to `/_blush/preview`, `lifetime` to a
    week. Anyone with a link sees the entry until it expires; no account
    is needed, so drafts can be shared with reviewers.
  - **The page** (`PreviewController`) renders the entry through
    `PageRenderer` as its own page would (a routed type's entry as a
    single, others as a page), whatever its status, with
    `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`, and
    `Referrer-Policy: no-referrer` (so the link doesn't leak through
    outbound links). A bad, changed, or expired link is a plain 403;
    a genuine link to an entry that's gone is a 404.
  - **Admin:** `POST {path}/api/previews` with `{"entry": id}` (201 `{url,
    expires}`; 400, 403 unless the account may edit the entry, 404, 503).
    The Drafts screen has a "Get link" control per entry: then "Open"
    (new tab) and "Copy", the expiry, and a polite announcement.
  - **CLI:** `content:preview <type> <name> [--hours=]` prints a link and
    when it expires.
  - **The jtcom trial** has `APP_SECRET` in `.env` (from `init`) and an
    empty line in `.env.example`. Checked in headless Chrome: a link
    made in the admin opened signed out and rendered the draft with the
    jtcom theme; a changed signature was refused.
- **Open:** a visible "preview" marker on the page (themes decide their
  markup, so it may need a template hook); revoking one link without
  changing the secret.
- **Why:** the author asked for signed preview links next.

### D-227: jtcom's drafts become `_posts` drafts
- **Date:** 2026-09-29
- **Decision:** jtcom's `__drafts/` folder was 1.x's stand-in for a
  drafts system: to Blush it's a hidden folder (D-088), so its files
  were indexed as published, hidden pages, and the admin's Drafts screen
  (D-225) showed none of them. The author chose to make them real
  drafts rather than teach Blush the old folder: each dated file moves
  to `_posts/` (same file name) with `status: draft` added under its
  title, and becomes a draft post (the post template when previewed).
  `__drafts/template.md`, a starter for new posts rather than a draft,
  stays where it is. The Drafts screen shows "(Untitled)" for drafts
  without a title.
  - Done in the jtcom trial (8 files; `content:lint` clean). For jtcom's
    real `2.x` port (M8), the same move is a content change to make then.
- **Noticed:** several of these drafts use placeholder dates such as
  `2019-00-00`, which PHP rolls back to a real date (`2018-11-30`), and
  `content:lint` doesn't flag. A lint warning for a zero month or day
  may be worth adding.
- **Why:** the author's call: the old folder only existed because 1.x
  had no true drafts.

### D-228: Writing content back to files
- **Date:** 2026-09-29
- **Status:** Partially superseded by D-237 (one trash folder per entry, with a manifest; restore and purge).
- **Decision:** The first piece of M10 (the editor): `ContentWriter`
  (`Blush\Content\Writer`), bound to `FilesystemWriter`.
  - **Only the edit changes the file.** Markdown and HTML front matter,
    and YAML entries, are edited key by key (`YamlMap`), not re-dumped:
    comments, key order, blank lines, aligned colons (`title     :`),
    and other keys' quoting stay. A key's entry is its line plus the
    indented or `- ` lines under it. A field is written under whichever
    of its name and aliases the file already uses (a jtcom `date` stays
    `date`); new keys go at the end under the field's name. Values are
    dumped by Symfony's YAML dumper, with dates (YAML timestamps)
    unquoted and text that needs quotes in double quotes (JSON strings),
    as people write them; multi-line text is a literal block. JSON
    entries are decoded and re-encoded pretty-printed. The body is
    replaced exactly as given; a file that ends at its closing `---`
    keeps doing so.
  - **Every edit proves itself** (`DocumentEditor`): the result is parsed
    with the same parsers the indexer uses; each set key must read back
    as given (dates as the same moment), removed keys must be gone,
    every other key must read exactly as before, and the body must be
    the given one. Otherwise it's a `WriteException` and nothing is
    written. Checked against all 300 jtcom trial files in memory
    (setting a title and a date): no refusals, and only those lines
    changed.
  - **Safety:** ids (paths) are confined to `user/content` and content
    formats (never PHP, D-039); writes are atomic and serialized by
    `storage/cache/content-write.lock`; a `revision` (SHA-256 of the
    file, from `load()`) guards against lost edits (`WriteConflict`).
    Values must be plain data (`EntryChanges`).
  - **Operations:** `load`, `create` (`{folder}/{slug}.{format}`, dated
    types `{Y-m-d}.{slug}`, never overwriting), `update`, `rename` (a new
    slug; a dated file keeps its date; a bundle renames its folder with
    its media; landing pages can't), and `delete` (moves the file, or a
    bundle's folder, to `storage/trash/{Ymd-His}/`). Each write
    reindexes incrementally and bumps the content version.
  - `content:new` now creates through the writer: a title that needs no
    quotes is written bare (`title: Colophon`), others double-quoted.
- **Open:** git-backed revisions (D-013); restoring from the trash (by
  hand for now); YAML entries' multi-line flow values can't be edited
  in place (the self-check refuses them).
- **Why:** the author asked to start the editor with the write path.

### D-229: The admin's editing API
- **Date:** 2026-09-29
- **Decision:** M10's second piece, over `ContentWriter` (D-228), in
  `Admin\EntryController`:
  - `GET entries/{id}` (the id is the source path, `{id:.+}`): `values`
    by field name (read from the first of a field's name and aliases in
    the file, as parsed, so dates are ISO 8601), `extra` (undeclared
    keys), `body`, `revision`, `title`, `status`, `own`, `url` (when
    published), `type` (name, kind, dated, and each field's
    `toArray()` without `class`, for forms), `can` (edit, publish,
    delete), and `violations` from the new `Linter::lintFile()` (one
    file's schema and `collection` checks; checks across files stay
    `lint()`'s).
  - `POST entries` (`type`, `title`, optional `slug` (else from the
    title), `set`, `body`, `status`): drafts by default; the account's
    author in the author taxonomy's term field unless given (not for
    the author type itself); dated types get `published` now. 201.
  - `PATCH entries/{id}`: `revision` required (428 without; 409 when
    stale), then `set`, `remove`, `body`, the `status` shortcut, and
    `slug` (a rename after the update). The answer is the reloaded
    entry.
  - `DELETE entries/{id}?revision=…`: to the trash (428, 409 as above).
  - **The status shortcut:** `draft` sets `status: draft`; `published`
    removes `status` and sets `published` to now when the entry has no
    date or a future one; `scheduled` removes `status` and sets
    `published` to the given future date (400 otherwise). Dates are
    read in the site's timezone and written `Y-m-d H:i:s P`.
  - **Permissions from the change:** `content.edit` for the entry
    (D-219's ownership and live-entry rules), `content.publish` when the
    result isn't a draft, `content.edit.others` to change the authors
    so the account's own author is gone (adding co-authors is fine),
    `content.create` to create, `content.delete` for the entry to
    delete.
  - Errors: 400 for a malformed request (`InvalidEdit`), 403, 404, 409,
    422 for a `WriteException`, 428.
  - Checked on the jtcom trial over HTTP: a no-op save of a real draft
    left it byte-identical; a post was created (a draft credited to
    `justintadlock`), renamed, and deleted to the trash.
- **Open:** notices for 1.x names show on nearly every jtcom file, so the
  editor should hide notices by default; validating `set` values
  against field types before writing (the file's violations report
  them after).
- **Why:** the author asked for the editing API after the writer.

### D-230: The admin's entry list
- **Date:** 2026-09-29
- **Decision:** `GET entries` (`Admin\EntriesController`) lists every
  entry the account may edit (`content.edit` on the entry, D-219), not
  only drafts and scheduled ones (D-225), for the editor's list
  screens. Query parameters: `status` (`draft`, `scheduled`,
  `published`, or `any`, the default), `type` (400 when unknown),
  `search` (a case-insensitive match on the title or source path),
  `page` (from 1), and `per` (20 by default, 1 to 100). The answer adds
  `type`, `search`, `total`, `page`, `pages`, and `per` to `status` and
  `entries`; each entry is described as before. Order: drafts and `any`
  by `updated` descending, scheduled by `published` ascending,
  published by `published` descending. Virtual entries (no source file)
  never appear, since the index holds only files.
  - **Everything runs in the index as one query**, so only the page's
    entries are built. Three additions make that possible:
    - `Query::search()`: a case-insensitive match on the title or
      source path.
    - `Query::either(...)`: groups of alternatives (closures that add
      conditions to `Query::condition()`, a query matching everything);
      an entry must match one alternative in each group, and no
      alternatives match nothing. Alternatives only narrow the query.
    - `Permissions::restrict($account, $capability, $query)`: the
      permission rules as query conditions. The rules are now stated
      once, as the statuses an account may act on for its own entries
      and for others' (`statuses()`); `can()` checks an entry against
      them and `restrict()` becomes `either(others' statuses, own
      statuses + the author's term)`. An account that may act on every
      entry gets the query unchanged. Ownership needs the author
      taxonomy to be a taxonomy type, as `owns()` does. A test checks
      that `restrict()` finds exactly what `can()` allows for every
      built-in role, two custom roles (one that edits others' drafts but
      only publishes its own), and accounts with and without an author.
  - `ArraySelector`'s filters moved to `RecordMatcher` (one per query
    and per alternative, with its comparisons worked out once,
    including term slugs, which were slugged per record before).
  - **Measured** (`benchmarks/AdminBench.php`, and a copy of the bench
    site with ten times the posts): the first version built and
    checked every entry, 9.4 ms at 1,183 entries and 94 ms at 9,643,
    growing with the site at four times a plain query's cost. Now, at
    1,183 entries: the whole list 2.4 ms (page 1 or 40), an author's
    3.2 ms, a contributor's 1.0 ms, a search 0.9 ms, against 2.4 ms for
    a plain index query. At 9,643 entries: an editor's list 26 ms, an author's (about 9,400 of
    their own, sorted) 33 ms, a contributor's 10 ms, a search 8 ms,
    against 26 ms for a plain index query. What's left is `PhpIndex`
    scanning every record for any query, public ones included;
    `SqliteIndex` (later) is the answer for sites that size.
  - The Drafts screen asks for `per=100`.
- **Why:** the author asked for the quickest admin win while the admin
  UI design is in progress; every design needs an entry list. Paging in
  the index came after benchmarking, because the author plans sites
  several times jtcom's size.

### D-231: The admin's design tokens and shell
- **Date:** 2026-09-29
- **Decision:** The admin follows the design direction in
  `.claude/docs/admin-design/` (`admin.md`, a prototype-stage document,
  and `tokens.css`, the prototype's tokens, kept as the author supplied
  them). Its firm parts are adopted as rules: the theming cascade (every
  token on bare `:root`, dark values for a dark system and again for
  `[data-color-scheme="dark"]`), token names as a contract for admin
  themes, no literal colors, fonts, type sizes, or radii outside the
  tokens, and status never shown by color alone. Supersedes D-223's
  `light-dark()` colors. Built so far:
  - **CSS layers** in `resources/admin/css/`: `tokens.css` (the neutral
    theme, light and dark; the only file with literal values),
    `fonts.css`, `base.css` (resets, element defaults), and `admin.css`
    (the entry: imports the others, then the pieces several screens
    share: page headers, buttons, panels, status pills, stat tiles,
    tables, empty states, fields, notices). A component's own layout is
    in its scoped styles.
  - **Fonts:** IBM Plex Sans (variable, latin and latin-ext) and IBM
    Plex Mono (400, 500, latin) in `resources/admin/fonts/` with their
    OFL license, self-hosted because the admin's CSP allows only
    `'self'`. About 106 KB, fetched only as text needs them.
  - **Icons:** Lucide 1.48.0 markup in `js/icons.ts`, drawn by
    `AdminIcon`; the admin's set is separate from the core front-end
    icons (D-187), which carry tags and translated labels it doesn't
    need.
  - **The shell (`AdminLayout`):** a rail (the site's name, the
    navigation, the account with sign-out), a top bar (a rail toggle,
    the site / screen trail, "View site"), and the work area, the only
    scroll container, capped at `--work-max`. The rail collapses to
    icons, remembered per browser in `localStorage` (wrapped in
    `try`/`catch`). At 860px and below it's a drawer: a menu button
    with `aria-expanded`, a scrim, Escape to close, focus into the
    drawer on open and back to the button on close, and `inert` on
    whichever side is hidden.
  - **Screens restyled:** the dashboard's counts are stat tiles (with a
    share of all entries as context) and its actions are panel rows;
    the entry table shows the file under the title and a status pill
    (`StatusPill`: published, scheduled, draft); drafts and health use
    panels with empty states; health severities are pills (error,
    warning, notice).
  - **Departures from the prototype**, recorded in `admin.md`: three
    tokens added (`--text-sm`, `--text-xs`, `--h2`) so type sizes stay
    tokenized; shared pieces are global classes in `admin.css`, not yet
    `Base*` components; table headers aren't sticky (a table that
    scrolls sideways is its own scroll container, so a sticky header
    has nothing to stick to); the `editorial` theme isn't shipped,
    since nothing chooses a theme yet.
  - **Checked** on the jtcom trial in headless Chrome: light and dark,
    the collapsed rail, 390px with the drawer (Escape returns focus),
    no page-wide horizontal scroll at phone width, and the fonts
    loading under the CSP.
- **Open:** choosing an admin theme and color scheme per account (the
  attributes exist; nothing sets them yet); the prototype's list screen
  (status tabs, filters, bulk selection, row menus, tree view), command
  palette, and toasts, which come with the editor screens.
- **Why:** the author supplied the prototype tokens and direction and
  asked for the admin to be built from them.

### D-232: A light/dark choice in the admin, per browser
- **Date:** 2026-09-29
- **Status:** Superseded by D-235 (the preference belongs to the account, set on Your profile).
- **Decision:** Settles the color-scheme half of D-231's open question;
  the admin theme choice stays open. The top bar has an Appearance
  control (`ColorSchemeControl`): three radio buttons drawn as icons
  (System, Light, Dark), named for screen readers and on hover.
  `js/color-scheme.ts` sets `data-color-scheme` on `<html>` (removed
  for System) before the app mounts, so the sign-in screen follows it
  too, and keeps the choice in `localStorage`
  (`blush-admin-color-scheme`, wrapped in `try`/`catch`; System removes
  it). Nothing is stored on the account.
  - The page's background can show the system's scheme for a moment
    before the app's script runs: the CSP allows no inline script to set
    the attribute earlier. An account setting served in the shell's
    HTML would fix that, and belongs with the theme choice.
  - **Checked** in headless Chrome: choosing Dark with a light system,
    a reload keeping it, arrow keys moving between options, and System
    following the system's setting again.
- **Why:** the author asked for the toggle now and theme choice later;
  a per-browser setting needs no API or account changes.

### D-233: The editor's first screens: entries, new entry, and the editor
- **Date:** 2026-09-29
- **Status:** Partially superseded by D-234 (a list per content type) and D-236 (no Drafts screen or preview column).
- **Decision:** M10's third piece, over the editing API (D-229) and
  the entry list (D-230). The Markdown body is a plain text area for
  now; live preview, a Markdown editor component, the component
  inserter, and media come next.
  - **API:** `GET types` (`TypesController`): each content type's
    `name`, `kind`, and `dated`, taxonomies last, for the type filter
    and "New …". The shell's screen paths now allow `.`, so editor URLs
    (which end in a file name) load directly.
  - **Entries** (`/entries`): status tabs (All, Published, Drafts,
    Scheduled) with counts (one `per=1` query each, with the same type
    and search), a search box (300 ms after typing stops), a type menu,
    Clear filters, and Previous / Next pages. The filters live in the
    URL query. Titles link to the editor (`EntryTable`, now also on
    Drafts, where preview links stay).
  - **New entry** (`/entries/new?type=`): a type and a title; the
    server writes a draft (D-229) and the editor opens it with a
    one-time "Created" notice.
  - **The editor** (`/entries/{id…}`, the id's segments as a repeatable
    route param):
    - The title and body in the main column; a sidebar with
      Publishing (the `published` date, a preview link or View, Move to
      trash), Fields (the schema's fields but `title`, `status`, and
      `published`), Other front matter (read-only), and Problems
      (`lintFile()`'s, notices behind a checkbox, per D-229).
    - **Forms from schemas** (`fields.ts`, `FieldControl`): text, slug,
      media, and single references are text inputs; markdown a text
      area; bool a checkbox; number a number input (min, max,
      integer); enum a select with an empty choice; date a
      `datetime-local` input; multiple references a comma-separated
      list; lists of scalars one per line. Objects and lists of
      objects are read-only ("Edit this one in the file for now").
      Labels are a field's `label` or its name made readable.
    - **Saves send only what changed** (compared as form state), so
      untouched keys keep their exact text and shape (a single
      `author: jane` isn't rewritten as a list). A cleared field is
      removed. A date keeps the offset its old value had; a new one is
      written without, so the site's timezone applies.
    - **Buttons from status and date** via D-229's status shortcut:
      a draft has Save draft and Publish (Schedule with a future
      date); a published or scheduled entry has Update (Schedule when
      the date moves to the future; Publish when a scheduled date is
      past) and Switch to draft. Without `content.publish`, drafts get
      Save draft only.
    - Ctrl+S / ⌘S saves without a status change. Quiet save state
      beside the buttons ("Unsaved changes", "Saving…", "Saved 12:27
      PM"). A 409 shows the server's message and "Load the saved
      version". Leaving with unsaved changes asks (route guard and
      `beforeunload`). Trash asks first, then returns to Entries.
  - The rail gains Entries; `meta.section` keeps it current on the new
    and editor screens.
  - **Departures from the design direction** (in `admin-design/admin.md`):
    no autosave and no pending changes on published entries (the writer
    has no pending-draft store; saves are explicit); status tabs are
    links (`aria-current`), since each is a URL.
  - **Checked** on the jtcom trial in headless Chrome: the list's tabs,
    type filter, and search; opening a real draft (not dirty on load;
    reload works); creating a post, saving a body, subtitle, and
    categories (only those lines written); a conflicting save made
    through the API refused with 409, then reloaded; scheduling for
    2099 (the offset kept) and switching back to draft; the leave
    prompt; trash; no page-wide horizontal scroll at 390px. (Editing
    files on the host doesn't test conflicts under ddev's Mutagen sync:
    the container hadn't seen the change yet.)
- **Open:** live preview (a render endpoint for a body and front
  matter); a Markdown editor component; the component inserter; media;
  pickers for references (terms) and media; renaming (the API's `slug`)
  from the editor; objects and lists of objects in forms; autosave and
  pending changes; whether Drafts folds into Entries.
- **Why:** the author asked to start on the editor screens.

### D-234: The admin lists entries by content type
- **Date:** 2026-09-29
- **Decision:** Refines D-233's single Entries screen, following the
  admin design direction (one list component, varied by the type).
  - **Types name themselves** (`ContentType::$label`, `$singular`):
    optional settings on every kind (`label:`, `singular:` in YAML;
    named arguments in PHP). `singular` defaults to the name made
    readable (`literary_form` → "Literary form"), `label` to the
    singular made plural by simple English rules (`-y` after a
    consonant → `-ies`; `-s`, `-x`, `-z`, `-ch`, `-sh` → `-es`; else
    `-s`). `toArray()` leaves defaults out. `GET types` adds both and
    sorts by label (taxonomies still last). Types from other languages,
    or names English doesn't pluralize this way, set `label`. The jtcom
    trial's `literature` type sets `label: 'Literature'`.
  - **Navigation:** the admin's screens (Dashboard, Drafts, Content
    health); a **Content** group with each collection and pages type
    by label, then **All entries**; and a **Taxonomies** group (the
    author's choice) with each taxonomy. Headings label their lists
    (`aria-labelledby`); the collapsed rail shows them as dividers.
    Icons by kind (collection `file-text`, pages `files`, taxonomy
    `tag`, all entries `library`). Types load once (`types.ts`) and the
    menu works without them.
  - **Screens:** `/content/{type}` is the type's list (the same
    `EntriesView`): its label as the heading and title, "New
    {singular}", "Search {label}", counts in its words, and no type
    column or menu. `/entries` stays as All entries, with the type menu.
    New entry and the editor use `singular` ("New literary genre",
    "Edit post"), and the editor's crumb goes back to the type's list.
    The navigation marks the type being listed, created, or edited
    (`currentType`); screens can name themselves more precisely than
    their route (`screenTitle`, for the top bar and the document title).
  - **Checked** on the jtcom trial in headless Chrome: the groups and
    their order, each type's screen and "New" button, the current item
    on list, new, and editor screens (and after changing the new
    entry's type), the crumb, All entries, the collapsed rail, and a
    direct load of `/admin/content/post`.
- **Open:** icons per type (a setting that names an admin icon);
  translated labels; hierarchical pages as a tree; whether Drafts and
  All entries stay once each type has its own screen.
- **Why:** the author asked why everything was under one Entries screen
  and chose per-type screens, with taxonomies grouped under a heading.

### D-235: Admin preferences belong to the account; Your profile
- **Date:** 2026-09-29
- **Decision:** Supersedes D-232's per-browser storage, following the
  updated design direction (§3): the color scheme (and later the admin
  theme) is a per-account preference, kept on the server and followed on
  any device, edited on **Your profile**, never with the site's
  Appearance.
  - **Stored with the account:** `Blush\Auth\Preferences` (immutable;
    `colorScheme`, a `ColorScheme` enum: `system`, `light`, `dark`),
    `Account::$preferences` and `withPreferences()`, and
    `Accounts::setPreferences()`. The account file gets `preferences`
    only for settings off their defaults; unknown or damaged values fall
    back to the defaults. The session fingerprint is the password hash,
    so saving a preference signs no one out.
  - **API:** `GET session` adds `account.preferences`; `PATCH
    preferences` (`PreferencesController`, any signed-in account, CSRF
    checked) changes the account's own and answers `{"preferences"}`;
    400 for a bad value or body.
  - **The shell reads the session without starting it**
    (`Blush\Session\SessionReader`: the cookie name, `Secure`, a live
    record, and the expiry rules, shared with `StartSession`; `read()`
    writes nothing and doesn't keep the session alive). `ShellController`
    prints `data-color-scheme` on `<html>` for a light or dark account
    and `colorScheme` in the start-up block (`null` when signed out), so
    the first frame is right and D-232's flash is gone. The page still
    sets no cookie.
  - **The app:** `color-scheme.ts` starts from the start-up block, else
    the browser's cache (`localStorage`, for the sign-in screen), follows
    the account whenever the session loads (a new device switches right
    after signing in), and `saveColorScheme()` shows a choice at once,
    saves it, and puts the old one back if saving fails.
  - **Your profile** (`/profile`, linked from the account at the foot of
    the rail, which now reads "Your profile"): the account's username,
    roles, author, and last sign-in, and the color scheme as three
    labeled choices (System, Light, Dark) with a polite "Saved" message.
    The top bar's control (D-232) is gone.
  - **Checked** in headless Chrome with two browser contexts as two
    devices: choosing Dark on one; the shell's HTML carrying it; the
    other device switching to dark on sign-in and keeping it on reload;
    System clearing the attribute and the cache; arrow keys between
    choices. Tests cover the API, storage (defaults left out), CSRF, the
    shell's attribute signed in and out without `Set-Cookie`, and the
    reader having no side effects.
- **Open:** the admin theme choice (a second preference; bundled themes
  and addon themes); changing one's own password on the profile.
- **Why:** the author updated the design direction to make these
  per-account preferences and asked to build that first.

### D-236: Taxonomy lists count uses; Drafts folds into the lists
- **Date:** 2026-09-29
- **Decision:** Two changes from the updated design direction and the
  author's follow-up.
  - **Terms count their uses.** `GET entries` adds `uses` to each
    entry: for a term, how many published, listed entries reference it
    (`ContentRepository::termCounts()`, one pass per taxonomy on the
    page, matching what the site's term pages list); `null` for other
    entries. A taxonomy's list (`/content/{taxonomy}`) shows an
    **Entries** column (right-aligned) in place of Authors, says so in
    the panel header ("Entries counts the published entries using
    each"), and speaks of terms ("Terms that group other entries",
    empty states). Drafts that use a term aren't counted, so a 0 doesn't
    promise that deleting the term breaks nothing. Checked against the
    jtcom trial's files (`era: current` on every post, `life` in 21
    posts with one a draft). The design direction's reparenting on
    delete doesn't apply: terms aren't hierarchical.
  - **No Drafts screen** (the author's call): each list's Drafts and
    Scheduled tabs replace it, and All entries covers every type.
    `/drafts` redirects to All entries → Drafts; the dashboard's Drafts
    and Scheduled figures link to those tabs. `DraftsView` is removed,
    and with it the entry table's preview column; preview links live in
    the editor (D-233).
- **Why:** the author asked for the design direction's taxonomy list
  and said Drafts no longer needs its own screen.

### D-237: The trash, in the admin
- **Date:** 2026-09-29
- **Status:** Partially superseded by D-254 (a trashed entry's actions are in its row menu, and its file isn't shown).
- **Decision:** Answers D-228's open "restoring from the trash (by hand
  for now)", following the design direction's Trash pattern: trash is a
  tab on each list, not a separate screen.
  - **Storage:** each deleted entry gets its own folder,
    `storage/trash/{Ymd-His}-{6 hex}/`, holding the moved file (or a
    bundle's folder, with its media) at its `user/content/…` path and a
    `trash.json` manifest (`entry`, `bundle`, `trashed`). Trash from
    before manifests is listed file by file (as single files, its folder
    time as when).
  - **`ContentWriter` gains** `trashed()` (`TrashedEntry`: the trash's
    id `{folder}/{entry id}`, the entry id, bundle, when, and the file's
    front matter), `restore($trashId, $changes)`, and `purge($trashId)`,
    all under the write lock. `restore()` makes its changes to the file
    **while it's still in the trash**, then moves it back, so a restored
    entry is never live without them; it refuses (and leaves the trash
    alone) when something now has the entry's place. Emptied trash
    folders are removed.
  - **API** (`TrashController`): `GET trash?type=`, `POST trash/restore`
    (always as a draft: `status: draft`), `POST trash/delete`, and `POST
    trash/empty` (`type` optional; everything the account may handle).
    Trashed entries aren't in the index, so ownership comes from the
    file's author field (the author taxonomy's field and aliases): an
    account handles its own with `content.delete` and everyone's with
    `content.delete.others`. POST with a JSON `id`, since trash ids hold
    slashes.
  - **Admin:** a Trash tab (with a count) on every list for accounts
    with `content.delete`, filtered by the list's type and search; rows
    show the title and where it lived, the type (on All entries), and
    when it was trashed, with **Restore as a draft** and, after a
    divider, **Delete permanently**; **Empty trash** in the panel header.
    Both deletions confirm first; results show in a status notice
    ("Restored “…” as a draft." with a link to open it). Trashing from
    the editor returns to the entry's type list.
  - **Checked** in headless Chrome on the jtcom trial: a published post
    trashed from the editor, restored as a draft, trashed again, deleted
    permanently; two pages trashed and the Pages tab emptied, leaving an
    older trashed post alone. Tests cover the writer (manifests, legacy
    trash, restore with changes, bundles with media, a refused restore,
    purge, and ids that try to leave the trash) and the API (types,
    restore as draft, delete, empty, and an author seeing only their
    own).
- **Open:** a trash "status" in the index (so trashed entries could sit
  in "All"); automatic emptying after some days.
- **Why:** the author asked for the design direction's Trash next.

### D-238: Media metadata (planned)
- **Date:** 2026-09-29
- **Decision:** Media files can carry fields (alt text, caption, and so
  on) without becoming a content type. They borrow the parts of content
  types that fit a file and leave out the rest.
  - **Not a content type.** A media file's identity is its path, not a
    Markdown document: no body, slug, status, taxonomies, or routes.
    Media gets no pages of its own (no attachment pages); it's served as
    a file, as now (D-099).
  - **Fields** are defined with the same model as content type schemas
    (D-042), in code or in data, using the existing field types.
    Built-ins: `alt`, `caption`, `credit`, and `description`. Sites and
    extensions add their own. The admin's forms, validation, and editor
    JSON Schemas come from the definition, as for entries.
  - **Storage:** never next to the media file (`media:publish` would
    expose it, D-099, and the author doesn't want it there). A tree under
    `user/data/media/` mirrors the media paths, one file per media file,
    written only when it has something in it:
    `user/media/2024/sunset.jpg` →
    `user/data/media/2024/sunset.jpg.yml`; bundle media under
    `_content/`, as it's served (`user/content/posts/hello/photo.jpg` →
    `user/data/media/_content/posts/hello/photo.jpg.yml`). JSON or YAML,
    like the rest of `user/data`.
  - **Moves:** the admin moves or trashes a media file and its metadata
    file together. A metadata file with no media file (a rename by
    hand) is reported by `content:lint` and `doctor`.
  - **Embedded metadata** is read from the file where possible: EXIF,
    IPTC, and XMP for images; ID3 (and the like) for audio and video:
    title, artist, album, date, duration, dimensions, camera details,
    copyright, and embedded artwork. It's derived data, so it's
    **cached, not stored**: kept with the media index, reread only when
    the file's size or modified time changes, and never written into
    `user/data`. The admin can copy embedded values into the editable
    fields ("use the file's title") when an author wants to keep or
    change them.
  - **Readers** follow the Type enum + Registry + Factory + Registrar
    pattern, one per format, so extensions can add formats. Any
    third-party library (for example getID3) sits behind the Blush
    interface (D-006). Readers that need an optional PHP extension
    (`exif`) are skipped when it's missing.
  - **The index** holds media records beside entries: every allowed file
    in `user/media` and in page bundles, with or without a metadata
    file (path, URL, MIME, size, dimensions, embedded metadata, fields).
    The media library lists, searches, and filters it (for example
    images missing alt text). Queries go through the same query layer as
    entries.
  - **Rendering:** a value set where the media is used (Markdown alt or
    title, a component prop) wins; then the metadata file; then embedded
    metadata. Alt text and captions depend on context, so metadata only
    fills gaps. Alt text is never made up from a file name. Content
    written without metadata renders exactly as it does now (D-078).
  - **Privacy:** location data (EXIF GPS) is read but never rendered or
    exported by default.
- **Open:** see `open-questions.md` → Media metadata.
- **Why:** the author's call: fields and descriptions for media,
  metadata kept away from the files, and embedded metadata looked up and
  cached.

### D-239: Image sizes: legacy WordPress copies are variants; the original is the source of truth (planned)
- **Date:** 2026-09-29
- **Decision:** Refines D-238 and the planned image derivatives
  (architecture → Media).
  - **Variants.** Media imported from WordPress holds resized copies of
    each image (`photo-300x200.jpg`, `photo-1024x683.jpg`) and others
    WordPress makes (`photo-scaled.jpg`, `photo-rotated.jpg`, edited
    `photo-e1234567890.jpg`, and their sizes). These are **variants** of
    the original, not media of their own:
    - The media library and the media index show one item, the
      original, with its variants listed under it. Metadata (D-238)
      belongs to the original; variants have no metadata files.
    - Variant files stay where they are and are still served, so old
      content that links to them keeps working (D-078). A reference to a
      variant resolves to the original for its metadata (alt, caption).
    - Nothing is deleted automatically.
  - **Detection is by rule, never by name alone.** A file is a WordPress
    size only when its name ends in `-{w}x{h}`, a file without that
    suffix exists beside it, and the file really is `w`×`h` pixels (no
    larger than the original). This keeps names such as
    `daisy-3x4.jpg` or `warrior-16x9.jpg` (aspect ratios, in the jtcom
    trial's media) as images of their own. Rules follow the Type enum +
    Registry + Factory + Registrar pattern, with WordPress's as the
    built-in, so other importers' conventions can be added; `MediaConfig`
    can turn rules off.
  - **Image sizes made by Blush** (the planned derivatives) are always
    generated from the original, never from a variant, and are cached
    output (rebuildable, in `public/_media/` or storage), never written
    into `user/media`, so they're never mistaken for originals. Sizes
    are named and declared by the theme, and sites can add or change
    them (in config first, the admin later). A media field for a
    **focal point** (D-238's fields) guides crops.
- **Open:** see `open-questions.md` → Media metadata.
- **Why:** the author has many old WordPress media files with several
  sizes of the same image, and wants to create image sizes in Blush
  later.

### D-240: The admin follows the design direction's state patterns; no "All entries"
- **Date:** 2026-09-29
- **Decision:** From the updated design direction
  (`admin-design/admin.md` §8: Loading, Offline and failed saves, Edit
  conflicts, Validation, Empty and first-run) and the author's call to
  drop the list of every type.
  - **No "All entries".** Each content type has its own list; `/entries`
    and `/drafts` redirect to the dashboard. The lists lose their Type
    column and type menu. The dashboard's Drafts and Scheduled figures
    are plain numbers (they linked to All entries).
  - **Loading:** skeletons in the shape of the content
    (`SkeletonTable`, dashboard tiles and actions, the editor's title,
    body, and sidebar), with the chrome (header, tabs, search) shown at
    once. A list's skeleton guesses its rows from the tab's last count
    (up to a page), else six. The shimmer runs one way and stops under
    reduced motion. Content health keeps its quiet text.
  - **Offline:** a warn bar under the top bar (`connection.ts` follows
    the browser's `online`/`offline` events); typing is never blocked.
  - **Unsaved changes are kept in the browser** (`kept.ts`, one
    `localStorage` item per entry with its revision), since there's no
    autosave (D-233): written a moment after typing stops, cleared once
    saved, thrown away, or left on purpose. Opening the entry again
    offers **Restore them** or **Throw them away**; changes kept from an
    older revision come back as a conflict.
  - **Saves:** offline, a save waits (**Waiting for a connection**) and
    goes ahead when the browser is back online. Any other failure shows
    **Not saved** in the save state (red) and a notice that leads with
    what's safe, with **Try again**.
  - **Conflicts** (a 409 on an old revision): saving stops; the editor
    loads the file as it is now and says when it was written
    (`GET entries/{id}` gains `modified`, from `EditableEntry::$modified`,
    the file's modified time), "from the admin or by editing the file
    itself" (who isn't known: files change through git and text editors
    too). **Keep theirs** loads their version; **Compare** lists the
    fields that differ and a line diff of the body (`diff.ts`, common
    ends trimmed, then a longest common subsequence; unchanged runs
    folded to two lines of context); **Keep mine** saves this editor's
    version of every field it shows over theirs, with their revision.
    Nothing is resolved without a choice.
  - **Validation:** the schema's `required` fields (the title and
    publish date too, when required) gate **Publish**, **Schedule**, and
    **Update** of a live entry, never a draft save. A blocked publish
    names the missing fields in a notice, marks each (`aria-invalid`,
    the reason under it), and focuses the first; errors clear as fields
    are filled. The API doesn't enforce it yet.
  - **Empty and first-run:** a type with no entries in any status or
    the trash drops its tabs and search, says what the type is for (from
    its kind; types have no description yet), and offers its first
    entry. A site with no content replaces the dashboard's figures with
    numbered steps: the first entry of each page type, then of each
    collection. Steps for content types, media, and inviting people wait
    for their screens.
  - `admin.md` gets back this repo's file paths, departures (with these
    added), and settled questions, which the updated copy had dropped.
- **Checked:** `vue-tsc` and the build; `composer check`; the diff under
  Node. Not driven in a browser: making a throwaway admin account on the
  dev site wasn't permitted this session.
- **Open:** enforcing required fields in the API; a type `description`
  for the empty state; who saved, if the writer ever records it.
- **Why:** the author asked for the admin to match the updated design
  direction, and for no "All entries" screen.

### D-241: The admin's full navigation, planned screens, and a Markdown source editor
- **Date:** 2026-09-29
- **Decision:** From the author's new clickable prototype
  (`admin-design/blush-admin.html`), with its screens that don't exist
  yet stubbed, and the editor's next step.
  - **Navigation in the prototype's groups:** the admin's own screens
    (Dashboard, Content health); **Content**, each collection and pages
    type, with a taxonomy nested under it when the taxonomy's `types`
    names only that type, then Media; **Structure**, Content types and
    the taxonomies shared by several types or every type, each with a
    second line naming them ("Every type", "Posts, Pages", "3 types");
    and **Site**: Appearance, Extensions, Accounts, Roles, Settings.
    Replaces D-234's Taxonomies group. Links the account can't use are
    hidden (Media `media.upload`; Accounts and Roles `accounts.manage`;
    Content types, Appearance, Extensions, and Settings
    `site.settings`). The prototype's "Addons" are Extensions, and its
    nav counts are left out (no API gives them cheaply yet).
  - **`GET types`** adds `types` to each taxonomy (`Taxonomy::$types`,
    the types a term's page lists; empty for every type), which is what
    places it.
  - **Planned screens** (`PlannedView`, routes with `meta.planned`):
    `/media`, `/types`, `/appearance`, `/extensions`, `/accounts`,
    `/roles`, `/settings`. Each has its heading, what it's for, "This
    screen comes next" with what it will do, and where that's done
    until then (a command or file), so the whole admin can be judged
    before every screen exists. The first-run setup doesn't link to
    them: its steps must do something (D-240).
  - **The Markdown source editor** (`MarkdownEditor`, `markdown.ts`):
    the body stays a plain text area (typing, undo, spelling, and screen
    readers work as in any field) laid over a highlighted copy in the
    same grid cell, hidden from assistive tech. It picks out headings,
    fenced and inline code, links, strong text, and component
    directives, following the server's rules (D-026: containers closed
    by at least as many colons, closing the outermost they're long
    enough for; leaves; inline directives not after a word character or
    colon; nothing inside fenced code), and marks the directive the
    caret is in. A bar above shows the component count, the current
    component's name, and the line and column; one below, word and
    character counts (grouped digits) and "Components render on the
    site". Highlights change color and background only, never width, so
    the copy lines up (checked: identical sizes at 1360px and 390px).
    The field grows with its text; the work area stays the only scroll
    container. Departs from the prototype: no bottom padding for
    scrolling past the end, spelling on, and no horizontal padding on
    inline code (it shifted the text in the prototype).
  - **Checked** on the jtcom trial in headless Chrome with a throwaway
    administrator (deleted afterwards with its sessions): the groups and
    nesting (Literature's three taxonomies, Posts' Categories and Eras,
    Authors under Structure), each planned screen, the collapsed rail,
    dark mode, a post with a container, a leaf, and inline directives,
    typing a wrapped paragraph, no page-wide scroll at 390px, and no
    console errors.
- **Open:** the component inserter and the sidebar swapping to a
  component's options (the current directive is the hook); live
  preview (a render endpoint); nav counts; each planned screen's real
  version.
- **Why:** the author supplied the prototype, asked for stubs of the
  screens that don't exist yet, and to keep moving the admin forward.

### D-242: Relationships between entries; taxonomies become a preset (planned)
- **Date:** 2026-09-29
- **Decision:** Taxonomies aren't split further from content types.
  Instead, references between entries become the general mechanism, and
  a taxonomy becomes shorthand for settings any type can have. Today's
  taxonomy is a content type plus: its term field added to the types it
  groups; a reverse index (`IndexSnapshot::referencing()`); term pages
  and feeds listing the entries that use a term; virtual terms for
  referenced slugs with no file; and no dates. A plain `reference` field
  (`to: actor`) is forward-only. Authors (D-043) already show the
  pattern: an ordinary type that behaves like a taxonomy.
  - **A reference is stored on one side** (a movie's `actors:
    [tom-hanks]`); the index works out the other. Storing both sides in
    flat files would let them disagree. The admin shows the reverse side
    read-only ("Appears in", with links); it's edited from the side that
    holds it.
  - **Reverse lookups are by field, not by type**, so two fields that
    point at one type (`director`, `actors` → person) give separate
    lists ("Directed", "Acted in"). The index keys reverse references by
    field and slug, for every reference field.
  - **Whether a missing target is allowed** is a setting on the
    reference: tags create terms as they're typed (virtual terms); an
    unknown actor is a problem `content:lint` reports.
  - **Any type's entry page can list what references it**, with a
    term page's paging and feeds; a term page is that, on an entry that
    can also have its own body and fields.
  - **`kind: taxonomy` (and 1.x's `taxonomy: true`, D-078) becomes a
    preset:** the reference field added to its `types`, missing targets
    allowed, its page listing what references it (with feeds), and no
    dates. Any collection can take each of those separately.
  - **The admin** gets one picker in two modes: create-as-you-type for
    taxonomies, search existing entries for other references. "Used by"
    counts generalize the taxonomy list's Entries column (D-236), and the
    rail's nesting (D-241) generalizes to a type under the one type that
    references it.
  - **In stages:** (1) the reverse index for every reference field, keyed
    by field, with a template API (something like
    `$entry->referencedBy('movie.actors')`) and "Used by" in the admin;
    no config changes; (2) "lists what references it" as a setting for
    any type, with taxonomies re-expressed as the preset (the taxonomy
    kind is checked in about 20 files: routing, feeds, sitemaps, the
    view hierarchy, permissions, lint, menus, the admin); (3) one picker
    in the admin, after the component inserter settles how pickers look.
- **Open:** see `open-questions.md` → Relationships.
- **Why:** the author designed taxonomies as their own thing for a
  simpler 1.x, thinks they can work much like other types, and wants
  entry-to-entry relationships (a Movie type with an Actor type that
  behaves like a taxonomy); agreed after discussion.

### D-243: The admin's component inserter
- **Date:** 2026-09-29
- **Decision:** From the updated design direction (`admin.md` §8, The
  component inserter) and prototype, the editor's inserter, built for a
  catalog that grows.
  - **`GET components`** (`ComponentsController`) lists what the
    inserter offers: registered components with a class (D-214) that the
    active theme's chain can render (as `component:list` filters them).
    Each has its full `name` (the inserter writes full names, D-171), its
    translated `label` and `description` (D-172), `content` (`none`,
    `text`, `blocks`), `kind` (`container`, `leaf`, or `inline`),
    `category`, `source`, and `props` as schema fields with translated
    `label`s and, for choices, `choices` labels by value.
  - **Groups:** the core components have a category
    (`ComponentType::category()`, a `ComponentCategory`: Text, Media,
    Layout, Navigation, Data). The rest are grouped by where they come
    from (`source`: a theme in the chain by its name, the site's `app`
    namespace as "This site", or an extension by its vendor), which is
    the provenance the design names as the lasting axis. No registration
    API changes: a category for third-party components can come later.
  - **Kind:** `blocks` is a container; the core components meant for
    inside a sentence (`ComponentType::isInline()`: abbr, icon, kbd,
    time) are inline; the rest are leaves.
  - **The inserter** (`ComponentInserter`): search first (label, name,
    description, group; every word must match; starts-with ranks first),
    groups down the side with counts, recently used on top (five, per
    account, in `localStorage`), keyboard throughout, kind always shown,
    and a badge naming a theme or extension source.
  - **Typing `/`** at the start of an otherwise empty line (up to three
    spaces before it, not in fenced code) opens it at the caret; what
    follows filters it and stays in the text until a component replaces
    it. Enter or Tab inserts; Escape, or clicking elsewhere in the text,
    leaves the slash as text, and it stays closed until that slash is
    gone.
  - **What's written:** a component that takes a line of text is
    written inline (`:blush/kbd[…]`) when the caret is in a sentence,
    else on lines of its own with a blank line either side (a block can't
    start mid-line, so it goes after the caret's line). Selected text
    becomes its label or body. Required props are written empty
    (`{src=""}`) so the author sees what's needed; defaults aren't
    written. The caret goes to the first empty value, else the empty
    label or body. Inserting goes through the browser's own editing
    (`execCommand('insertText')`, with `setRangeText` as a fallback), so
    one undo takes it back, slash and all.
- **Checked:** `composer check`; `vue-tsc` and the build; in headless
  Chrome, the editor in a scratch page with a stubbed component list:
  opening from the button, search, groups, recents, the keyboard,
  slash insertion, Escape, fenced code, inline insertion around a
  selection, undo and redo, and no page-wide scroll at 390px. Not on
  the dev site: making a throwaway admin account wasn't permitted this
  session.
- **Open:** the sidebar swapping to a component's options (the
  directive under the caret is the hook); keywords in search (D-172
  names `components.{name}.keywords`, which isn't read anywhere yet);
  a category for third-party components; component icons from the
  registration rather than the admin's own map.
- **Why:** the author updated the design direction with the inserter's
  design and asked to keep building the admin from it.

### D-244: The admin's navigation is a section rail and a panel
- **Date:** 2026-09-29
- **Decision:** From the updated design direction (`admin.md` §6,
  Layout: "Two levels, on purpose"), replacing D-241's single sidebar.
  - **A labeled section rail** (`--railbar`, 66px): the site's mark (a
    link to the site), then **Home**, **Content**, and **Config**. The
    panel beside it (`--rail`) shows only the active section: Home has
    the Dashboard and Content health, then shortcuts (Your profile,
    Settings); Content has each collection and pages type with the
    taxonomies that group only it nested under it (one level), "Shared
    taxonomies" with what they group on a second line, and "Library"
    (Media); Config has Structure (Content types), Site (Settings,
    Appearance, Extensions), and People (Accounts, Roles, Your profile).
    D-241's rules for what shows and what nests are unchanged; a section
    with nothing the account can use is left out of the rail.
  - **Each screen belongs to a section** (`meta.area` on its route), and
    the panel follows the screen. Choosing Content or Config changes the
    panel without navigating (and shows the panel if it was hidden); Home
    is one screen, so it goes to the dashboard.
  - **The panel collapses to nothing**, leaving the rail (remembered in
    this browser, as before); the old icon-only collapse and `--rail-min`
    are gone. Below 860px the rail and panel slide in together as one
    drawer.
  - **The account's menu moves to the top bar** (`MenuButton`, a
    disclosure rather than an ARIA menu): the username and roles, Your
    profile, and Sign out.
  - **Focus mode** (`focusMode` in `screen.ts`, set by the editor)
    drops the rail, the panel, and the top bar; any navigation turns it
    off.
  - Tokens added: `--railbar`, `--text-2xs` (the rail's labels),
    `--drawer`, `--measure`, `--doc`, `--doc-title` (D-245).
- **Departs from the prototype:** no nav counts (as in D-241), no state
  dot on Content (there are no unpublished changes to live entries
  without autosave, D-233), no ⌘K palette yet, no theme button in the top
  bar (the color scheme is an account preference, D-235), no site
  switcher, and no toast when a taxonomy moves (it moves when a config
  file changes, not in the admin).
- **Checked:** in headless Chrome against a stubbed API (scratch
  harness): the three panels, choosing a section without navigating,
  hiding and remembering the panel, the account menu (Escape closes it),
  the drawer at 390px, and no page-wide scroll. Not on the dev site (a
  throwaway admin account wasn't permitted this session).
- **Why:** the author's updated design direction.

### D-245: The editor is a writing surface, with component options
- **Date:** 2026-09-29
- **Decision:** From the updated design direction (`admin.md` §8, The
  editor is a writing surface; The component inserter) and prototype.
  Settles the open question of whether the settings swap between
  Document fields and Component options: two tabs, both always shown.
  - **Layout:** the editor fills the work area (`meta.bleed`) and
    scrolls itself. One centered column (`--measure`, 68ch) holds the
    title and body; the title is part of the document (a wrapping text
    area in the display face at `--doc-title`, no box; Enter moves to the
    body) with the entry's file under it. The body is `--doc` (14px) and
    the Markdown editor is bare in the column (its bars are gone).
  - **The header:** back to the type's list, the type, the save state
    (with a dot), the status, **+** (the inserter, at the caret), the
    settings button, a **⋯** menu (Save draft or Switch to draft, View,
    Focus mode, Move to trash), and the primary action. Notices (kept
    changes, failed saves, missing required fields with **Show me**, a
    conflict with its comparison) are bars under it.
  - **The footer is the status line:** words and reading time (220 words
    a minute), the chip naming the component the caret is in ("Callout
    options", when the settings aren't showing it), and shortcut hints.
  - **Settings are a drawer** (`--drawer`, 340px) that pushes the column
    aside, closed at first (⌘/ toggles it; Escape closes it); below
    980px it lies over the column. **Document** has Publishing, the
    type's fields, "Components in this entry" (each opens its options and
    moves the caret to it), other front matter, and problems.
    **Component** follows the caret, not focus, so it stays while the
    settings are used; it's disabled, and says why, with none.
  - **Component options** (`ComponentOptions`): the props from `GET
    components` as a form (`FieldControl`, now with choice labels and an
    id prefix), a **Text** field for the `[label]`, the attributes the
    component doesn't declare (read-only), its source, and **Remove
    component**. `markdown.ts` reads a directive's head and makes minimal
    edits: an attribute that's there is rewritten in place, a new one is
    added at the end, empty braces are removed, repeats of the same key
    go, and values are quoted only when needed. A value set back to its
    default is removed; a required one left empty stays as `key=""`.
    Removing keeps a container's body and an inline directive's text.
  - **While keys move** (in the title or body), the header and footer
    fade to a third and the file path to 40%; any pointer movement
    brings them back (2.6 s otherwise). No fade under reduced motion.
  - **Focus mode** (⌘⇧F, or the ⋯ menu) leaves the editor alone
    (D-244); Escape, the footer's chip, or leaving the editor ends it.
  - **The inserter opens at the caret**, from **+** too, kept inside the
    writing column when the column is wide enough and flipped above the
    caret when there's no room below.
- **Departs from the prototype:** no autosave (D-233), so the save state
  reads "Unsaved changes" or "Saved 3:46 PM"; the file path, not a URL
  slug, under the title; no Copy link or Duplicate in the menu; Tab in
  the body moves focus as in any field (the prototype indented); the
  drawer isn't remembered; removing a container keeps its body (the
  prototype removed it). Option changes from the settings are applied to
  the text directly, so the settings keep focus while typing; they
  aren't in the text field's own undo (inserting and removing are).
- **Checked:** the directive edits under Node (setting, adding, and
  removing attributes, quoting, repeated keys, labels, removing each
  kind, an unclosed container); in headless Chrome against a stubbed
  API: the column and the drawer pushing it, the chip and tabs following
  the caret, option changes (select, text, checkbox, back to default,
  a required one emptied) with focus kept in the settings, "Components in
  this entry", remove and undo, the inserter at the caret, slash
  insertion, Enter from the title, the fade and its return, focus mode
  and Escape, ⌘/, ⌘S, and 390px (no page-wide scroll; the drawer lies
  over the text). Not on the dev site (see D-244).
- **Open:** a media picker for `media` options; reordering components;
  undo for option changes; the line and column readout the prototype's
  footer has.
- **Why:** the author's updated design direction and prototype.

### D-246: The admin lists icons and media for the editor's pickers
- **Date:** 2026-09-29
- **Decision:** Two read-only endpoints for the editor's inserters
  (D-247), both needing an account.
  - **`GET icons`** (`IconsController`): the icons the active theme can
    show (`Icons::all()`, as `icon:list` lists them), each with its
    `name` as the icon component's `name` prop takes it (a core icon's
    short name, `house`; the rest in full), its translated `label`, the
    core icons' `keywords` (Lucide's tags, `resources/icons/blush/tags.json`),
    and its `svg` (files over 16 KB are sent without it). The admin draws
    an icon as a CSS mask from its SVG, filled with the text color, so no
    markup from the file runs in the admin.
  - **`GET media`** (`MediaListController`, `content.edit`): the library
    (`user/media`), newest first by modified time, a page at a time
    (`page`, `per`: 48 by default, at most 100), narrowed by `search` (in
    the path, any case) and `kind` (`image`, `video`, `audio`, `any`).
    Files are narrowed by extension first (the site's allowed types, less
    caption tracks), so a 4,000-file library isn't read, then each file on
    the page is checked by `MediaResolver` (its real type, size, and an
    image's dimensions). With `entry` (an id the account may edit) in a
    page bundle (`name/index.md`), `beside` lists the media files in its
    folder (at most 200), which it refers to by name; otherwise `null`.
    Each file has its `reference` (what to write: the library's URL path,
    such as `/media/2026/photo.jpg`, or a bundle file's name), `name`,
    `folder`, `url`, `mime`, `kind`, `size`, `width`, `height`, and
    `modified`.
- **Open:** uploading (`media.upload`), media metadata (D-238), and
  thumbnails (the picker shows the file itself, lazily).
- **Why:** the updated design's icon popover and media modal need them.

### D-247: The editor's three inserters, and a header in two halves
- **Date:** 2026-09-29
- **Decision:** From the updated design direction (`admin.md` §8, The
  editor is a writing surface; The inserters) and prototype.
  - **The header has two halves.** Left: back, where you are, a hairline,
    then the three insert tools (components, media, icons), things done
    *to* the document; the component button sits over the panel it
    opens. Right: the save state, the status, settings, the ⋯ menu, and
    the primary action, what the document *is* and what happens to it.
    Below 480px the save state keeps its dot and its words are read out
    only.
  - **Components are a panel from the left** (`ComponentPanel`, replacing
    D-243's popover `ComponentInserter`, `--inserter`, 322px) that pushes
    the column aside (below 980px it lies over the text) and stays open
    after a pick when opened from its button. Inside: a search field,
    category pills (All, the core categories, then sources), a
    two-column grid of tiles (an icon in a tinted square, the name, the
    kind, or "theme"/"extension" for where it comes from), and a strip at
    the foot describing the highlighted one. Up and down move a row, left
    and right a tile (in the search field only while it's empty, so they
    still edit text), Enter inserts, Escape closes (`grid.ts`). Typing
    `/` opens the same panel with the query from the text; it closes once
    a component replaces the slash. The recently used list (D-243) is
    gone, as in the design.
  - **Icons are a popover** (`IconPicker`) under their button, with the
    shapes glyph: a search (names, labels, keywords), a six-column grid,
    and a foot showing the directive the highlighted icon writes
    (`:blush/icon[]{name=house}`, or the selection as its label).
  - **Media is a modal** (`MediaPicker`, a native `<dialog>`): search,
    kind filters, "Beside this entry" for a page bundle, then the library
    with **Show more**; a click selects, **Insert** or a double click
    uses it. An image becomes a figure, a video a video, a sound audio,
    anything else a file download, written on its own lines with
    `src`, and the settings' Component tab switches to it. The same
    picker is **Choose** beside every `media` field and option
    (`FieldControl`'s `pickable`), which settles the open question of how
    the picker is invoked from a component option.
  - **What a component's kind decides** (replacing D-243's rule): an
    inline component is written at the caret; a leaf or container on
    lines of its own, whatever it takes. (D-243 put any component that
    takes a line of text inside a sentence, which wrote figures and
    buttons inline.)
  - `directiveText()` takes attribute values, written first, before any
    required props still empty.
- **Departs from the prototype:** no `Changes` pill (no pending changes
  without autosave, D-233); the prototype's inserter closes after every
  pick, the design's doesn't, and this follows the design.
- **Checked:** `composer check`; in headless Chrome against a stubbed API:
  the panel pushing the column, pills, grid keys, the preview strip,
  inserting with the panel staying open, search, Escape, the slash path
  (open, filter, insert, close), the icon popover under its button with
  keyword search, the media modal (filters, select, insert as a block,
  the Component tab following), **Choose** for an option, Escape in the
  dialog, and 390px with no page-wide scroll. Not on the dev site (a
  throwaway admin account wasn't permitted).
- **Why:** the author's updated design direction.

### D-248: Toasts and the command palette
- **Date:** 2026-09-29
- **Decision:** From the design direction (`admin.md` §7, Menus, command
  palette, toasts; §6, where ⌘K is the answer to the section rail's one
  extra click).
  - **Toasts** (`toast.ts`, `ToastHost` in the layout): one at a time,
    bottom right, in a polite live region, gone after 2.6 s, in the past
    tense. The editor says what it inserted or removed ("Inserted a
    callout", "Inserted the house icon", "Inserted beach.png", "Removed
    the figure"), a change of status ("Published", "Scheduled",
    "Switched to draft"), and "Moved … to the trash". A plain save shows
    in the save state, never as a toast; problems stay notices where they
    happened.
  - **The command palette** (`CommandPalette`, a native `<dialog>`), from
    ⌘K (Ctrl+K) anywhere or the top bar's "Search or jump to…" button (an
    icon below 640px). Commands come first: the screen's own
    (`useCommands` in `commands.ts`; the editor adds focus mode, the
    settings, the three inserters, Save, its primary and secondary
    actions, and Move to trash, with their shortcuts), then going to each
    screen and type the account may use, "New {singular}" for each type,
    Your profile, and switching the color scheme (saved to the account,
    D-235). Then entries (`GET entries`, any type the account may edit):
    the latest changed with nothing typed, else those matching, a moment
    after typing. Up and down, Enter, and Escape (which closes only the
    palette, not the focus mode or panel under it).
- **Checked:** in headless Chrome against a stubbed API: the toasts and
  their timing; the palette's lists, search, navigation, the editor's
  commands first, focus mode from it, and Escape.
- **Why:** the design direction; the section rail made the palette the
  quick way across sections.

### D-249: Roles and accounts, read-only, as list and detail screens
- **Date:** 2026-09-29
- **Decision:** The first stubbed screens (D-241) built for real, in the
  design direction's shape (`admin.md` §8, List, then detail).
  - **`GET roles`** and **`GET accounts`** (`PeopleController`,
    `accounts.manage`): every capability (`name`, `label`); every role
    with its capabilities (`*` for all), whether it's built in, and the
    accounts holding it; every account's username, roles, author, and
    created and last sign-in times. Password hashes and preferences stay
    on the server.
  - **Roles** (`/roles`): a table (Role with its key, Capabilities as
    "Everything" or "3 of 14", Accounts), no search or tabs for a handful
    of fixed rows. **A role** (`/roles/{name}`): its label as the title,
    "3 of 14 capabilities · held by 1 account", **All roles**, then About
    (key, capabilities, built in or `config/auth.php`), Held by (links to
    the accounts, or an empty state naming `account:roles`), and every
    capability in groups by its first word, granted or not.
  - **Accounts** (`/accounts`): Account, Roles, Author, Last signed in.
    **An account** (`/accounts/{username}`): the facts, the commands
    that change it, and every role with whether it's held (each a link).
  - Read-only, with a notice naming where each is changed (`config/auth.php`,
    `account:*`). A detail screen marks its list in the panel
    (`meta.parent`).
- **Departs from the prototype:** no Invite, role checkboxes, or danger
  zone yet (accounts are changed with commands); no role descriptions (a
  role has none); no entry counts or "last active" (the last sign-in
  stands in).
- **Checked:** `composer check` (`AdminPeopleTest`); in headless Chrome
  against a stubbed API: both lists, a role, an account, the panel
  marking the list, and 390px.
- **Why:** the author asked to keep building the design's screens.

### D-250: Content types, read-only, as list and detail screens
- **Date:** 2026-09-29
- **Decision:** The Content types stub (D-241) built as the design's
  list and detail screens (`admin.md` §8), read-only.
  - **`GET types`** adds each type's `origin` (`TypeOrigin`: built in,
    an extension, `config/content.php`, or `user/data/types`), `folder`,
    URL `prefix` (`null` without URLs), and how many `fields` it defines.
    **`GET types/{name}`** adds its own fields (`Field::toArray()`), the
    taxonomies that group it, whether it's public, has a feed, is in the
    sitemap, and is `editable` (only `user/data/types` types, D-042).
  - **Content types** (`/types`): tabs by kind (All, Content, Taxonomies)
    with counts, a search of names and keys, and a table of Name (with
    its key and address), Kind, Source, Fields, and Entries (each type's
    `GET entries` total, as the account may edit them); **Clear filters**
    when nothing matches.
  - **A type** (`/types/{name}`): its label, "Collection · post · from
    config/content.php", **All types** and **View posts**; General (names,
    key, folder, address, dated, public, feed, sitemap), Taxonomies (or,
    for a taxonomy, the types it groups, and where that puts it in the
    navigation), and Fields (label, key, type, required), noting the
    fields every entry has. Each type's list has **Type settings** linking
    here, for `site.settings`.
- **Departs from the prototype:** nothing is editable yet (no field
  editor, no new-type wizard, no delete); "Show in the sidebar" and a
  hierarchy switch aren't type settings in Blush.
- **Checked:** `composer check`; in headless Chrome against a stubbed
  API: the tabs, search and Clear filters, a type's screen, the panel
  marking Content types, and the links both ways.
- **Why:** the author asked to keep building the design's screens.

### D-251: The media library, as list and detail screens
- **Date:** 2026-09-29
- **Decision:** The Media stub (D-241) built over `GET media` (D-246), in
  the design's shape (`admin.md` §8, List, then detail), without upload.
  - **Media** (`/media`): the library newest first as a grid of cards (a
    lazily loaded image, or a video, audio, or file glyph; the name; the
    folder, dimensions, and size), filters by kind, a search of file
    names, **Show more**, and **Clear filters** when nothing matches.
  - **A file** (`/media/{path}`, its path under `user/media`; `GET
    media/{path}`, confined to the library and its allowed types): a
    preview (the image, or a player), its details, and **Use it**: its
    address and the component that shows it
    (`::blush/figure[]{src=/media/…}`), each with **Copy** (a toast says
    so).
- **Departs from the prototype:** no upload, alt text, captions, or
  "used in" yet (metadata is D-238); bundle files aren't listed here
  (they're in the editor's picker, beside their entry).
- **Checked:** `composer check` (`AdminPickersTest`); in headless Chrome
  against a stubbed API: the grid, filters, a file's screen, copying, and
  the panel marking Media.
- **Why:** the author asked to keep building the design's screens.

### D-252: Live preview waits; the Markdown experience comes first
- **Date:** 2026-09-30
- **Decision:** Live preview (rendering the editor's unsaved body and
  front matter through the theme; D-233, D-241) is deferred. When it
  comes, it starts small: inline previews of images and embeds in the
  editor. The goal after that is a full rendered preview, above all of
  components. The current focus is making the Markdown editing
  experience work well.
- **Open:** what the Markdown experience needs; the shape of image and
  embed previews; the render endpoint for the full preview.
- **Why:** the author's call.

### D-253: Markdown that reads as it looks, and editor addresses by handle
- **Date:** 2026-09-30
- **Status:** Partially superseded by D-254 (Fira Code instead of Plex Mono's italic and semibold faces; no slug under the title).
- **Decision:** The first Markdown-experience work (D-252), and the
  editor's address, both asked for by the author.
  - **Markdown styling** (`markdown.ts`, `MarkdownEditor`): emphasis
    (`*`, `_`) is italic, strong text (`**`, `__`) and headings semibold,
    struck text (`~~`) struck through, quotes muted and italic, list and
    task markers in the accent, and every mark (`*`, `#`, `>`, `\`
    escapes, a link's brackets and address) muted; a link's label is in
    the accent. Marks nest (`**bold *and italic***`), underscores inside
    words don't count, and nothing in code does. Still not a Markdown
    parser; the site's rendering has the last word.
  - **Widths never change**, so the text area still lines up with its
    highlighted copy: the admin now serves IBM Plex Mono's own 400
    italic, 600, and 600 italic faces (from Fontsource, the same files as
    the 400 already there), rather than synthesized ones, which can
    widen text in some browsers. Headings can't be larger for the same
    reason.
  - **Long words and addresses wrap** inside the writing column: its
    grid track is `minmax(0, 1fr)`, so a long link no longer widens the
    column and pushes it off center.
  - **Editor addresses by handle:** an entry is edited at
    `{path}/content/{type}/{key}`, its type's name and its index key
    (the slug, with a page's folders; a landing page's is `index`),
    under the type's list at `content/{type}`, rather than
    `entries/{source path}`. Keys are already unique per type and
    locale (entries' own URLs need them), so no date suffix is needed.
    `EntryHandles` makes and resolves handles; `GET content/{type}/{key}`
    answers as `GET entries/{id}` does, and entry descriptions (the
    editor's and `GET entries`') carry `handle`. An entry without one
    (not in the site's locale, a key another file claims, or a page
    keyed `index` beside a landing page) is edited at `entries/{path}`,
    as before; that address still works for every entry, and the
    editor moves to the handle once loaded, and again after a rename.
    The API keeps ids (paths) for everything else.
  - The editor shows the slug under the title (the path is its
    tooltip), not the file's path.
- **Checked:** `composer check` (`AdminEditingTest`); on the jtcom trial
  in Chrome: the post from the report centered (the column 283px from
  each side), its old address moving to
  `content/post/register-custom-icons-wordpress-7-0`, list links by
  handle (pages with folders), and the text area's own text drawn over
  the highlighted copy, lined up through bold, italic, and code.
- **Why:** the author asked for italics, bold, and headings to look
  like themselves, for entries not to be named by their `.md` file, and
  for addresses to use the type's name rather than its folder.

### D-254: A 640px Fira Code editor, site addresses in tables, and row menus
- **Date:** 2026-09-30
- **Status:** Partially superseded by D-255 (Fira Code is the admin's only mono; no `--font-editor`).
- **Decision:** The author's requests, after D-253:
  - **The writing column is 640px** (`--measure`, was `68ch`, about
    530px).
  - **The Markdown body is in Fira Code** (`--font-editor`; Fontsource's
    Latin and Latin Extended 400 and 600, OFL, noted in `fonts/LICENSE`).
    The rest of the admin's mono stays IBM Plex Mono, and D-253's Plex
    Mono italic and 600 faces are gone. Fira Code has no italic, so
    emphasis is a synthesized slant, which keeps the advance (a
    synthesized bold might not, hence the real 600). Its ligatures are
    left on; they keep widths too.
  - **Nothing under the editor's title:** D-253's slug line is gone.
  - **No files or folders in the UI** unless an info box asks for them
    later: entry tables show the entry's address on the site (`url` in
    `GET entries`, from `ContentUrls::entry()`, where a draft will be
    once published; nothing when it has none), the trash shows no path,
    and an untitled entry is "Untitled" rather than its path (the
    palette, messages, the preview link's label). Content health still
    names files: it's about files.
  - **Row menus**, as in the prototype: a narrow last column of **⋯**
    buttons (`.row-more`: shown on the row's hover or focus where
    there's hover, always where there isn't), each opening a
    `MenuButton` with `floating` (placed `fixed` beside the button,
    above it when there's no room, so the table's scrolling wrapper
    can't clip it; scrolling closes it). Entries: **Edit**; **View**
    (**View archive** for a term) and **Copy link** (the full address)
    once live; and **Move to trash** when `can.delete` (new in `GET
    entries`), which loads the entry's revision and deletes at it, as
    the editor does. The trash: **Restore as a draft** and, after a
    divider, **Delete permanently**, replacing D-237's inline buttons.
  - Not yet: **Duplicate** (no API) and a trashed entry's **Preview**.
- **Checked:** `composer check` (`AdminEditingTest`); on the jtcom trial
  in Chrome: addresses under titles, the last row's menu opening upward,
  the editor 640px wide in Fira Code with no slug line and its copy
  still lined up, and a throwaway post created, moved to the trash from
  its row menu, and deleted permanently from the Trash tab's.
- **Why:** the author asked for all of it.

### D-255: Fira Code everywhere, and the index page pinned in its list
- **Date:** 2026-09-30
- **Decision:**
  - **Fira Code is the admin's mono** (`--font-mono`), replacing IBM
    Plex Mono everywhere; D-254's `--font-editor` is gone and the
    Markdown editor uses `--font-mono` again. The admin serves Fira
    Code 400, 500, and 600 (Latin and Latin Extended); Plex Mono's files
    and license line are removed. IBM Plex Sans stays the UI font.
  - **The index page is an entry, pinned** (the design doc's new
    section, from the author): a collection's or taxonomy's landing page
    (`Entry::$landing`, the `index` file in its folder) is its type's
    index page. `GET entries?type=` leaves it out of `entries`, `total`,
    and `pages` (`Query::withLanding(false)`) and answers it apart as
    `index`, on every page, when the same filters (status, search, the
    account's permissions) find it, in the site's locale; entries
    describe `index: true`, and an index page's `can.delete` is false.
    The list pins it in a `tbody` of its own above the rest, tinted
    (`--surface-2`, `--surface-3` on hover, a `--border-strong` rule
    under it), with a pin before the title and an **Index** mark after
    it; its row menu has no Move to trash. Tab counts and the type's
    total leave it out, so "Posts 63" means 63 posts (Content types'
    Entries counts too). A type whose only entry is its index page shows
    it above the first-run state ("The index page above is already
    live" when it's published).
  - **Pages have no index page:** a page tree's root is the site, so
    the home page (the `page` type's landing) is a page like the others.
- **Departs from the design:** there's no checkbox column (no bulk
  actions yet), so the pin sits before the title rather than in the
  checkbox's place. Not yet: the editor's side of the pattern (no
  custom fields, taxonomy picker, or Scheduled for an index page, the
  Document tab's line saying what it is, the **Index** mark beside the
  type in the editor's header, and refusing to trash it: the editor's
  menu still offers Move to trash), the type screen's "Has an index
  page" switch, and a new type being born with one.
- **Checked:** `composer check` (`AdminEditingTest`: left out and
  pinned, on every page, hidden by a tab or search it doesn't match,
  and none for pages); on the jtcom trial in Chrome: Posts pins "I am
  Justin Tadlock." on page 1 and 2 but not on Drafts or a search for
  "twinkle", its menu is Edit, View, Copy link, Categories pins its
  index page and Pages pins nothing, and paths render in Fira Code.
- **Why:** the author asked for Fira Code throughout and for the
  design's index-page pattern in the content tables.
