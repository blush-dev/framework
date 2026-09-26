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
