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
- **Decision:** A theme's manifest, tokens, and settings schema are data files
  (`theme.json`), not PHP. Theme behavior (components, context providers)
  lives in an optional theme service provider.
- **Narrows D-017:** Developer-facing configuration stays typed PHP objects.
  **User-editable data** (theme setting values, token overrides, menus,
  anything the future admin writes) lives in data files under `user/data/`.

### D-023: Design tokens use the W3C Design Tokens (DTCG) format
- **Date:** 2026-09-25
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
