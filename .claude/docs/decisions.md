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
- **Status:** Themes are known by name, not folder, and publish to `public/themes/{vendor}/{name}` since D-379.
- **Decision:** Local themes live in `user/themes/{slug}`. Themes installed
  through Composer may also be read from `vendor/`. Theme assets are published
  to `public/themes/{slug}`.

### D-035: Per-request theme switching: `?theme=` in dev only
- **Date:** 2026-09-25
- **Status:** `?theme=` takes the theme's name since D-379.
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
- **Status:** Amended by D-388: the admin installs extensions into
  `user/`. Content, media, data, and uploads still never carry code.
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
- **Status:** Naming superseded by D-378 (planned): these are **plugins**, one kind of extension.
  "Enabled or disabled in site config" is superseded by D-390: config
  names only what's on.
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
- **Status:** The admin edits data types since D-311. A data file named
  for a code collection or taxonomy changes it rather than being an
  error since D-349.
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
- **Status:** YAML manifests delivered by D-092. Applies to plugins
  (`user/plugins`, `plugin.json`, `PluginConfig`) since D-379. "Every
  discovered extension is enabled by default" and `disabled` are
  superseded by D-390: only Composer plugins are on by default.
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
- **Status:** Partially superseded by D-320 (a closing fence closes the innermost open container it's long enough for, so nesting doesn't need longer fences).
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
- **Status:** Themes are keyed by name and a Composer theme's name is its package's (no `extra.blush.slug`) since D-379.
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
- **Status:** The default theme's `excerpts` setting is removed (D-307).
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
  hashes, not mtimes, and built files are versioned too). Assets are
  published under the theme's name, `public/themes/{vendor}/{name}`, since
  D-379.
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
- **Status:** The `Pages` kind superseded by D-386: it's the `Tree` kind.
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
- **Status:** "Only people with repo or filesystem access change
  themes and extensions" is amended by D-388: the admin installs them
  too.
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
- **Status:** Where a theme's or extension's namespace comes from is superseded by D-378 (planned): every extension declares it in its manifest, checked for clashes.
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
- **Status:** Icon packs (D-378, D-379) add their folders to `IconRegistry`; a theme's own icons use its namespace.
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
- **Status:** The planned-screen page is removed (D-309).
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
- **Status:** `user/data/types` types are editable since D-311, and
  code collections and taxonomies since D-349.
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

### D-256: Content types have a description and an icon
- **Date:** 2026-09-30
- **Decision:** Types already had `label` and `singular` (D-234); every
  kind now also takes:
  - **`description`**: what the type is for, in a sentence (`''` for
    none). The admin shows it under the type screen's title and as an
    empty list's text, in place of D-240's text by kind.
  - **`icon`**: the name of a site icon (as the `icon` component takes
    it, `film`, `jtcom/github`), or `null` for its kind's admin icon.
    The admin loads `GET icons` only when some type names one, and draws
    it as a mask in the text color (`TypeIcon.vue`), falling back to the
    kind's icon while loading or for an unknown name.
  - `GET types` adds both. Two labels stay the whole set for now; more
    (such as "Add new …" or "Search …" strings) and translated labels
    come when a screen needs them.
- **Why:** the author asked for labels for the admin; those existed, so
  the missing pieces were a description and an icon (D-234's and
  D-240's open items). The author expects more labels in time.

### D-257: Pages nest by folder, taxonomies by `parent`; collections don't nest
- **Date:** 2026-09-30
- **Decision:**
  - **Pages** nest by folder, as their URLs already do: `about/team`'s
    parent is the page keyed `about` (`about.md` or `about/index.md`).
    Top-level pages have none; the home page isn't every page's parent.
  - **Hierarchical taxonomies:** `hierarchical: true` (`Taxonomy`'s
    option, in data too) adds a single `parent` reference to the
    taxonomy's own terms (`Taxonomy::parentField()`, added by
    `ContentTypes::schema()` after the term fields, so a type's own
    `parent` field may replace it). A term names its parent by slug;
    term files stay flat and keep their URLs when they move, and slugs
    stay unique across the taxonomy. Chosen over folders (the author's
    call): moving a term would move its file and change its URL.
  - **Collections don't nest.** A type whose entries nest (a manual with
    chapters) would be a kind of its own, if one's ever needed; a
    collection's subfolders stay only a way to organize files.
  - **One model:** `ContentType::parentKey($key, $values)` (`null` in
    the base, the folder for `Pages`, `parent` for a hierarchical
    `Taxonomy`) gives each record's `parent` key (`IndexRecord::$parent`),
    and the snapshot keeps the reverse (`children`, by locale, type, and
    parent key; index format version 2). `ContentRepository::parent()`
    and `children()` (by title, any status) read them; templates get
    `$template->parent()`, `ancestors()` (top down, stopping at a
    missing parent or a loop), and `children()`, published and routable
    only.
  - **Checks:** `content:lint` errors on a term that's its own parent or
    in a loop, and warns of a parent with no file (the term is shown at
    the top level). A folder needn't have a page, so pages aren't
    checked.
  - **Admin:** `GET entries` adds each entry's `ancestors` (titles, top
    down), shown before its title in the lists; `GET types` adds a
    taxonomy's `hierarchical`. The editor shows `parent` as a text field
    from the schema until the reference picker (D-242's third stage).
  - **Not yet:** a term page listing its child terms' entries too
    (WordPress does); a tree-ordered admin list; reparenting a deleted
    term's children.
  - **jtcom trial:** `category` (topics) is hierarchical, with parents on
    31 terms (Web › Web design › CSS, WordPress › bbPress › bbPress
    tutorials, and others); the theme nests the topics listing
    (`parts/term-tree.php`) and puts a term's parents above its title.
- **Checked:** `composer check`; on the jtcom trial: `content:lint`
  clean, `/topics` nested and `/topics/css` under "Web › Web Design";
  in headless Chrome, Categories rows such as "WordPress › bbPress ›
  bbPress Tutorials" and Pages rows "Archives › Months", and the type
  screen's Hierarchical row.
- **Why:** the author asked whether types besides pages nest, wants
  hierarchical taxonomies (with one in the trial to test), and thinks
  nesting collections would be a different kind.

### D-258: Type folders default to `_` and the name
- **Date:** 2026-09-30
- **Decision:** Supersedes the folder default in D-083 and D-157.
  - A collection's or taxonomy's `folder` defaults to `_{name}`
    (`_recipe`), so type folders stand apart from page folders in the
    content root, as jtcom's `_posts` does. Pages keep the root; the
    built-in `author` keeps `authors` (existing content).
  - A type's URL `prefix` without `urls.prefix` is its folder with the
    leading `_` of each folder name dropped (`_recipe` → `/recipe`,
    `_blog/_tags` → `/blog/tags`), so URLs are as before.
  - 1.x definitions without `path` now get the new folder; the author
    isn't worried about older definitions. `coming-from-1x.md` says to
    add `path` to keep one.
  - **A clash check in `content:lint`, not `doctor`:** a page whose
    address another route answers (a type's single, listing, or archive
    route; matched against the route table, not by prefix) is a
    warning. `doctor`'s checks run before the application loads and need
    only `Paths` (D-218), so content checks belong to lint. Matching real
    routes rather than prefixes keeps jtcom's `/archives/months` page
    (under the post type's `archives` prefix, but no post route answers
    it) quiet.
- **Why:** the author agreed types shouldn't share the root's namespace
  with pages by default, and isn't worried about older definitions.

### D-259: Authors are the public side of accounts
- **Date:** 2026-09-30
- **Decision:** Refines D-043 and D-216/D-217.
  - **Author entries stay the public identity:** a name for bylines, a
    bio, an archive. Accounts stay private and out of git, so content
    never points at them, and authors who aren't accounts (guests,
    co-authors, people from jtcom's past) keep working.
  - **An account's author entry is its own** (`Permissions::owns()` and
    `restrict()`), so an author edits their own bio without
    `content.edit.others`; the usual status rules still apply.
  - **Your profile** has an **Author page** panel: the name bylines
    show, **Edit your author page** (the editor), or **Create your
    author page** when there's no file (a draft, with the slug as its
    title), or a note when the account has no author.
  - **`account:add` and `account:author`** offer to create the author's
    entry when it has none (`Accounts::hasAuthorPage()` and
    `createAuthorPage()`), asking for the public name; declined, or
    without a terminal, bylines show the slug until someone creates it.
  - **The admin lists authors under People** (Config), beside accounts,
    not in Content's shared taxonomies; `GET types` names the author
    type as `authors`, and its list and editor screens open the Config
    section.
  - **Planned:** once D-242's second stage lands, `author` stops being a
    `Taxonomy` and becomes an ordinary type that other entries
    reference.
- **Checked:** `composer check`; in headless Chrome on the jtcom trial
  with a throwaway account linked to a missing author: Authors under
  People (marked current on its list, Content without it), Your
  profile's "Create your author page" opening the new entry's editor and
  then "Edit your author page"; the account, its sessions, and the page
  were deleted afterwards.
- **Why:** the author asked whether the author taxonomy is needed at
  all, agreed to keep author entries as the public identity, and is
  fine with it not being a taxonomy; these are the suggestions they
  accepted for now.

### D-260: Hierarchical terms have nested URLs
- **Date:** 2026-09-30
- **Decision:** Refines D-257, where terms kept flat URLs.
  - A hierarchical taxonomy's `{name}` is the term's path: its parents'
    slugs, then its own (`/topics/web/web-design/css`), in its single,
    paged, and feed routes. `ContentUrls::termPath()` walks
    `ContentRepository::parentKey()` (a deferred dependency; a parent
    without a file ends the walk, so virtual terms and orphans sit at
    the top) and every term URL (sitemaps, export, menus, feeds) goes
    through `ContentUrls::term()` or `feed()`.
  - The route constraint is `ContentUrls::TERM_PATH`: slugs joined by
    `/`, where only a lone first segment may be `page` or `feed`, so
    `{name}/page/{page}`, `{name}/feed`, and the taxonomy's own
    `/topics/page/2` still match. `ContentUrls::constraints()` gives it
    to `ContentRoutes::route()` (and `FeedRoutes`) and to URL building
    for hierarchical taxonomies only. A child term slugged `page` or
    `feed` has no URL.
  - `TermController` finds the term by the path's last slug and
    redirects (301) any other path to the term's own, so flat 1.x URLs
    and a moved term's old address keep working. `FeedController` finds
    the term the same way without redirecting.
  - Term files stay flat and name their `parent` (D-257): the URL
    follows the tree, the file doesn't.
  - **jtcom trial:** the term pages no longer show their parents above
    the title (the author's call); the nested `/topics` list stays.
- **Checked:** `composer check` (`ContentRoutingTest`: nested pages,
  paged and feed URLs, redirects from flat and partial paths, a child
  slugged `page`); the jtcom trial's `/topics/web/web-design/css`.
- **Why:** the author asked for nested URLs for hierarchical terms.

### D-261: Nesting types list as a tree in the admin
- **Date:** 2026-09-30
- **Decision:** Resolves D-257's "tree-ordered admin list" item.
  - `GET entries` with a `type` whose entries nest (pages, or a
    hierarchical taxonomy), no `status`, and no `search` returns entries
    in tree order: each followed by its children, siblings by title
    (natural, any case), then any caught in a loop of parents. An entry
    whose parent isn't in the list (missing, or one the account can't
    edit) is at the top. Every entry is built to order them, then the
    page is sliced; tabs and searches keep D-230's orders.
  - The rows still show their parents' titles before their own; no
    indentation.
- **Checked:** `composer check` (`AdminContentTest`: order, paging, a
  search keeping the usual order); the jtcom trial's Categories and
  Pages lists in headless Chrome.
- **Why:** the author asked for hierarchical terms, and any nesting
  type, to be sorted together in their default admin listing.

### D-262: Child rows are indented in a tree-ordered list
- **Date:** 2026-09-30
- **Decision:** Refines D-257's and D-261's display.
  - In tree order (D-261), `GET entries` gives each entry its `depth`
    (0 at the top; an entry whose parent isn't in the list, or in a loop,
    is 0). Other lists' entries have `depth: null`.
  - The table indents a row with a depth: `.entry-title--nested`, 20px a
    level past the first, with a 1px `--border` guide line, from a
    `--depth` custom property set through a Vue style binding (CSSOM,
    which the admin's CSP allows). Those rows don't show their parents'
    titles.
  - Rows without a depth (status tabs, searches) keep the parents'
    titles before their own, since their order isn't a tree.
  - A page of the list can start with a child whose parent is on the
    page before; it's indented all the same.
- **Checked:** `composer check` (`AdminContentTest`: depths in the tree,
  an orphan at 0, none in a search); the jtcom trial's Categories in
  headless Chrome (children indented, a search still "WordPress ›
  bbPress › bbPress Tutorials").
- **Why:** the author asked for child terms to be indented instead of
  the breadcrumb.

### D-263: Tree lists follow the design's Hierarchy; later pages repeat their parents
- **Date:** 2026-09-30
- **Decision:** Supersedes D-262's look (20px steps and a guide line).
  - **As the design direction's Hierarchy section says:** 18px indent a
    level, a disclosure triangle (`chevron-right`, turned 90° when open)
    before each entry with children and its space before each leaf, and
    expansion held client-side (a `Set` of collapsed IDs in
    `EntryTable`, kept while the admin is open). Collapsing hides the
    rows under it on the page. `GET entries` adds each tree entry's
    `children` count.
  - **The flattened-tree bar:** on a nesting type, a status tab or a
    search shows `.notebar` above the table: "Searching, so the tree is
    flattened. Clear the search to see the hierarchy." or "Filtered by
    status, so the tree is flattened. Choose All to see the hierarchy."
  - **Later pages repeat their parents:** a tree page that starts inside
    a branch begins with the entries above its first row, top down,
    marked `continued: true` in the API and **Continued** in the table.
    They aren't counted again in `total` or `pages`.
- **Departs from the design:** it suppresses paging in tree mode ("Showing
  all 14 pages as a tree"); the author asked for parents at the top of
  later pages, so trees stay paged. No tree/flat switch or column
  sorting yet.
- **Checked:** `composer check` (`AdminContentTest`: a page inside a
  branch starting with its parent, uncounted; child counts; none in a
  search); on the jtcom trial in headless Chrome: Categories page 2
  opens with "Books (Continued)" then "Books In Bed", collapsing Art
  hides its two children, and a search shows the note bar with no
  triangles.
- **Why:** the author asked for parents at the top of later pages and
  for the indent to follow the design docs, without the border.

### D-264: The index page is pinned on the first page only
- **Date:** 2026-09-30
- **Decision:** Supersedes D-255's "on every page".
  - `GET entries` answers the type's index page as `index` on page 1
    only; later pages have `index: null`. It's still left out of
    `entries`, `total`, and `pages`, and still follows the tabs and
    search.
  - In a tree list (D-261, D-263), the pinned row's title leaves the
    triangle's space, so it lines up with the top-level rows.
- **Departs from the design:** the design direction pins it through
  paging ("survives sorting, paging and the status tabs").
- **Checked:** `composer check` (`AdminEditingTest`: pinned on page 1,
  not page 2); the jtcom trial's Categories in headless Chrome.
- **Why:** the author's call.

### D-265: The updated design: space, flat surfaces, four inserters, icon categories, and a marked source
- **Date:** 2026-09-30
- **Decision:** From the author's updated design direction (`admin.md`
  §2, §4 Icons, §5 Elevation, §6 Space, §8 The editor, Marking the
  source, The inserters, Variants, The media library) and prototype.
  Supersedes D-247's icon popover, category pills, and two-column tiles,
  D-245's disabled Component tab and its list on the Document tab, and
  D-253's styling of the source.
  - **Space and surfaces.** The tokens take the design's values: a space
    scale (`--s-1` 4px to `--s-7` 52px), `--ctl` (34px) and `--ctl-sm`
    (30px) for every control's height, flat resting surfaces
    (`--shadow-1: none`, with small shadows kept for menus, toasts, and
    modals), larger radii (6/10/16px), `--h1` 26px, `--h2` 15px, looser
    density (`--pad-row` 14px, `--pad-panel` 17px, `--pad-x` 22px, shared
    by panels, cells, and rows), `--railbar` 74px, `--rail` 262px,
    `--bar` 62px, `--work-max` 1400px, `--drawer` 372px, `--inserter`
    376px, and `--doc-title` 34px. The shared classes, the shell, menus,
    the palette, toasts, tabs, and empty states follow (empty states get
    the most room). Body text is 1.5 high; Fira Code's contextual
    ligatures are off, so a slug reads as its characters. Icons are
    drawn with a 1.6 stroke.
  - **A compact toggle, not a compact default:** each type's list has a
    two-button control beside the search for roomy or compact rows
    (compact drops the address line), kept in this browser
    (`density.ts`).
  - **Four inserters** in the editor's header, at 36px with 18px icons:
    **+** for block components (the panel, now without inline ones),
    media, icons, and an **A** menu of inline components, each with its
    description (the icon component is left out: it has its own picker).
    The panel loses its category pills; tiles are an icon and a name,
    three across, and the strip at the foot names a theme's or
    extension's source. Arrow keys step by the grid's real column count.
  - **Icons are a library in a modal**, sharing one shell with the media
    picker (`.modal` in `admin.css`): a search, the groups down the left
    (a scrolling row below 760px), a grid of icons with names, and a
    footer naming the selection and its directive, with **Insert**
    disabled until one is chosen. A search selects its best match, so a
    name and Enter inserts it; a double click inserts too.
  - **Icon categories:** `GET icons` gives each core icon its `category`
    (`IconCategory`: arrows, interface, status, communication, people,
    security, writing, media, development, design, time, places, nature,
    things), read from `resources/icons/blush/categories.json` beside
    `tags.json` (a test checks every core icon has one). Other icons
    have `category: null` and a `source` (theme, site, or extension), as
    components do; `Provenance` names it for both.
  - **The media picker** is wide (`min(1180px, 100vw - 64px)`, up to 90vh):
    kinds as a segmented control (All, Images, Video, Audio, Files, with
    `GET media`'s new `kind=file` for anything not an image, video, or
    sound), fixed-height cards with 4:3 cropped thumbnails, a ring and a
    tick for the selection, a kind label only on files that aren't
    images, and a footer naming the file with its size. The Media
    screen's cards follow the same rules (its thumbnails had grown to
    their images' heights).
  - **The settings drawer:** the tabs sit left (the first flush with the
    fields) and a close button right. The Component tab is never
    disabled: named for the component the caret is in, else
    "Components", with the entry's count; it shows the options, then
    every component in the entry with the current one marked (moved from
    the Document tab), and with nothing selected says so over that list.
    No kind label and no "Go to it in the text".
  - **Marking the source** (supersedes D-253's styling): every syntax
    character in `--fg-3` and the words at full ink; headings by weight
    (600 for `#` and `##`, 500 below); struck text dimmed; inline code's
    backticks muted on its chip; a link's label in the accent and its
    address muted; an image's alternative text in `--fg-2`; quotes in
    `--fg-2`, no longer italic; list markers in `--fg-2` at 600 (not the
    accent); a task's box muted, `[x]` in `--good`; rules at `--fg-2`;
    tables' pipes and delimiter row muted; fences on a slab with the
    language named; footnotes and autolinks in the accent; directives
    unboxed with the name in the accent and the label at full ink; and
    attribute blocks (`{.class #id key=value}`, on a directive or after
    any Markdown) as a gray chip with class and id names at full ink.
    Only valid syntax lights up (a brace in prose stays text). The box is
    kept for the directive the caret is in, and a container's body is
    tinted. The editor's line height is 2.
  - **Picker dialogs close before they hand over a choice**, so the
    editor can focus its text and insert at the caret (a modal dialog
    kept focus, so choosing an icon inserted nothing).
- **Departs from the design:** the writing column stays 640px (D-254),
  not 72ch; no Variant selector, since variants aren't built (D-191);
  admin icons stay per-component inline SVGs (`AdminIcon`), not a
  `<symbol>` sprite; the density choice is kept in the browser, not the
  account; the media kinds keep Audio; icon categories are Blush's own
  for its 131 core icons.
- **Checked:** `composer check` (`AdminPickersTest`: categories, a site
  icon's source, every core icon categorized, `kind=file`); the
  highlighter under Node (every character kept, with a directive
  current or not); in headless Chrome against the real API on a scratch
  site: the dashboard, a list roomy and compact, the editor (marks, the
  boxed container, the drawer's tabs and close button, the Component
  tab and its list), the panel's three columns and arrow keys, the icon
  modal (groups, search, Enter inserting), the inline menu, the media
  modal (selection, insert, the Component tab following), the Media
  screen, the palette, dark mode, and 390px.
- **Open:** variants (D-191); a way for a theme or extension to put its
  icons in the core categories; the density choice as an account
  preference.
- **Why:** the author's updated design direction.

### D-266: Component variants
- **Date:** 2026-09-30
- **Decision:** Supersedes D-191's planned shape and settles its open
  question (where themes declare variants). Built in this session.
  - **A variant is a name and its registrant**, the namespace whose
    catalog has its text: core (`blush`), a theme's slug, the site
    (`app`), or an extension's vendor. Its label and description are
    translated: `components.{name}.variants.{variant}.label` and
    `.description` in the registrant's domain.
  - **Default is always the default.** Every component has it; it isn't
    registered and can't be renamed, replaced, or removed. It writes no
    attribute (`variant=default` means the same as none) and adds no
    modifier class. No registered variant may be called `default`.
  - **Declaring them:** a component declares its own when it registers:
    a class with its `VARIANTS` constant, a template-only one with
    `register(…, variants: […])`. Anything else adds (or removes) them
    when the component's variants are first collected, from the
    `ComponentVariantsCollecting` event, which fires once per component
    and whatever order providers booted in. A theme may also list them in
    `theme.json` (`variants`, by component), with the text in its own
    catalog.
  - **A theme's variants apply only while it (or a child) is active.** A
    variant a component doesn't have (unknown, or from a theme that isn't
    active) renders as Default.
  - **Templates get it separately:** `$component->variant` (`'default'`
    when none), and the root element's classes get the BEM modifier
    `component-{name}--{variant}` unless the variant names another
    modifier. `variant` is a prop every component has, like `class` and
    `id`, so no component takes its own `variant` parameter.
  - **A variant may have its own template:** `components/{name}-{variant}`
    (such as `callout-bordered.php`) wins over the component's own, so a
    variant can change more than its looks. Variants are meant mostly for
    style, but nothing limits them to it.
  - **Core ships variants where they fit:** the button's is `secondary`
    (its Default is the primary look), and the callout's tones become
    variants (`info`, `tip`, `warning`, `danger`; Default is the note).
    `ButtonVariant`, `CalloutTone`, and the callout's `tone` prop go, with
    no fallback: `variant=primary` is flagged by `content:lint` (a
    variant the button doesn't have); `tone=…` is now just an attribute
    the callout ignores (the jtcom trial's content had none; its theme's
    callout styles moved the note colors to the Default callout).
  - **Checks:** `content:lint` warns about a directive's variant its
    component doesn't have under the active theme; `theme:check` warns
    about variants the theme declares for components that don't exist and
    about a variant template that's also a component's own template name.
    `component:list` lists each component's variants. `GET components`
    gives them to the admin, whose Component tab has a Variant select
    above Options (Default writes nothing).
- **Direction, not built:** components will have to be registered to
  render (PHP, or JSON with translations); template-only components found
  only by their file go away (see `open-questions.md`).
- **Checked:** `composer check` (`VariantsTest`: names, Default, the
  event adding and removing once per component, `theme.json` variants
  only while the theme is active, modifiers, a variant's template;
  `LinterTest`, `ThemeCommandsTest`, `AdminContentTest`); the jtcom
  trial's `content:lint`, `theme:check`, and `component:list`; the
  Variant select in headless Chrome against the real API on a scratch
  site.
- **Why:** the author's call, and the design direction's Variants
  section.

### D-267: `:::figure` is a container; the media picker inserts plain images
- **Date:** 2026-09-30
- **Decision:** Supersedes the figure part of D-113 and D-247's "an
  image becomes a figure".
  - **`blush/figure` is a container** (`ComponentContent::Blocks`) that
    wraps anything to be set apart with a caption (an image, a table, a
    code block, a quote): `:::figure[Caption]` … `:::`. The label is the
    `<figcaption>`, after the content; without content it doesn't render.
    Its `src` and `alt` props are gone, with no fallback (the jtcom
    trial's content doesn't use `::figure`). It moves to Layout: the
    `Component\Layout\Figure` class, the Layout category in the
    inserter, and the admin's `panel-bottom` icon (content over its
    caption).
  - **An image alone in a paragraph inside a figure is the image**
    (`FigureRenderer`): no `<p>` and no `<figure>` of its own, and its
    title stays a `title`. Outside a figure, a lone image is still a
    figure with its quoted title as the caption (D-078).
  - **The media picker inserts an image as plain Markdown**,
    `![alt](src)`, on lines of its own like a block component, with the
    caret in the alternative text (selected text becomes it). The address
    is wrapped in `<…>` when it has spaces or parentheses. Video, sound,
    and other files still insert their components.
  - **The editor reads an image's quoted title as its caption:** the
    address stays muted, and the title is set in full ink between muted
    quotes.
- **Checked:** `composer check` (a figure around an image and around a
  table, one in a page bundle); `imageText()` and the highlighter under
  Node; inserting an image from the picker in headless Chrome.
- **Why:** the author's call: a figure is for anything captioned, and
  the lone-image convention already makes a captioned image.

### D-268: Every block is an object; images are edited as Markdown; uploads
- **Date:** 2026-09-30
- **Decision:** From the author's updated design direction (`admin.md`
  §8: Marking the source, The inserters, Every block is an object, An
  image is Markdown, The media library; §10: Copy) and prototype.
  Supersedes D-265's `--fg-2` alternative text and tinted container
  body, D-247's single media button, and D-251's "no upload".
  - **Attributes are on by default:** `AttributesExtension` joins
    `MarkdownConfig::DEFAULT_EXTENSIONS`, since the editor writes
    `{.class #id}` for any block (a site listing it again is fine;
    extensions are added once). Checked against the parser, attributes
    go at the end of a heading's, paragraph's, or list item's last line,
    and on a line of their own directly above a quote, code block,
    table, or rule (the end of those lines doesn't reach the block, and
    a fence's info string doesn't take them).
  - **The Component tab follows the caret over three kinds of object:**
    the innermost directive or image the caret is in, else the Markdown
    block it's in (`blocks()` in `markdown.ts`), named on the tab
    (Heading, Paragraph, List Item, Quote, Code Block, Table, Divider).
    A blank line belongs to the block, or block directive, that ends
    nearest above it. Inside a container, the container wins. The
    selection drives the box in the source too, so the two always agree.
  - **Every object has Classes and ID** (`AttributeFields`): blocks,
    images, and components alike. A block's panel adds a heading's
    Level (an underlined heading becomes one with hashes), a code
    block's Language (the fence's first word), and a list item's task
    and done switches, then its first line as Source.
  - **An image is edited as Markdown** (`ImageOptions`): Variant, the
    image itself (the library's 4:3 crop, Replace and Remove over a veil
    on hover or focus, and always shown without hover; a file that's
    missing says so; a bare file name is looked up beside the entry),
    Alt text, Caption (the quoted title; empty writes nothing), and
    Classes and ID. Images are in "Components in this entry"; blocks
    aren't. Remove takes an image alone on its line with the blank line
    after it; in a sentence, one space around it.
  - **Image variants are the theme's classes:** `theme.json`'s
    `variants.image` lists them, with text at
    `images.variants.{name}.label` and `.description` in the theme's
    catalog; `ComponentVariants::forImages()` collects them, and
    `GET components` answers them as `image.variants`. Choosing one
    swaps that class in the image's attributes and leaves the rest. The
    framework default theme offers `stretch-wide` (Wide),
    `stretch-full` (Full Bleed), `inline-left` (Float Left), and
    `inline-right` (Float Right) and styles them, but only while it's
    the active theme, since only the active theme's stylesheet loads.
    `theme:check` checks them. The jtcom trial's theme lists
    `stretch-wide` and `stretch-full`.
  - **The list is a state:** each panel ends with one row, "Components
    in this {entry}" with the count, which swaps the panel for the list;
    choosing from it, moving the caret, or changing tabs brings the
    panel back.
  - **Marking:** an image is marked as a link (alternative text in the
    accent) with its caption at full ink and 500; a container is boxed
    on its opening and closing lines only; the selected image is boxed
    with its attributes. An alternative text with escaped brackets is an
    image now (the link pattern takes escapes).
  - **Media is a menu:** **Media Library** and **Upload a File**, both
    opening the picker on that tab (a plain button without
    `media.upload`). **Image** leads the panel's Media group and opens
    the picker on images. The Media screen gets **Upload** (the same
    picker, "Upload to the Library", **Open** going to the file).
  - **Uploads:** `POST media` (`MediaUploadController`, `media.upload`)
    takes one file as the multipart field `file` into
    `user/media/{Y}/{m}/`, with a name made safe for a URL (spaces to
    hyphens, other characters dropped, extension lowercased) and `-2`,
    `-3`, … when it's taken; nothing is replaced. The extension must be
    one the library lists and the site allows; the file is written
    hidden first, checked by its contents (`MediaResolver::mimeOf()`),
    and only then named, so a file that isn't what its name says never
    appears. 413 when PHP refused its size, 422 for a type. `GET media`
    answers `upload` (`limit` from `upload_max_filesize` and
    `post_max_size`, and `extensions`) for accounts that may. The
    picker's Upload tab is a drop zone and a button with those limits;
    a drop anywhere on the modal uploads; each upload lands at the top
    of the library, selected, with a receipt and **Show in library**.
  - **Title Case names things; sentence case says things** (§10): page
    and document titles, headings, empty-state headings, modal titles,
    nav and breadcrumb entries, palette rows naming a screen, and core
    component labels ("Keyboard Key", "Progress Bar", "Table of
    Contents"); buttons, labels, hints, toasts, and menu items stay in
    sentence case. `titleCase()` (`format.ts`) for built names.
- **Departs from the design:** attributes for quotes, code, tables, and
  rules go on a line above (the design's provisional end-of-block rule
  doesn't reach them); image variants come from the theme, not a fixed
  list; the list state has a **Back to the {object}** row too; a blank
  line after a leaf or container selects it; images from the library
  get no alt text or caption filled in (the library has none yet); the
  upload types are the library's (images, sound, video), as the site
  allows; below 480px the menus' carets go, so the header fits; the
  writing column stays 640px (D-254).
- **Checked:** `composer check` (`MarkdownRenderingTest`: attributes in
  each place by default; `VariantsTest`: image variants by theme;
  `ThemeCommandsTest`; `AdminContentTest`: `image.variants`;
  `AdminPickersTest`: uploads, names, refusals, nothing left behind,
  who may); `blocks()`, the image and block edits, and the highlighter
  under Node (every character kept); in headless Chrome against the real
  API on a scratch site: each block's panel and edits, the blank-line
  rule, image variants, caption, alt text, classes, the list row and
  back, the media menu, an upload through the picker and its insertion,
  **Image** in the panel, dark mode, and 390px; the jtcom trial's
  `theme:check`.
- **Open:** media metadata (alt text and captions in the library, to
  fill in on insert); a Markdown image's own attributes on a linked
  image; a site or extension adding image variants.
- **Why:** the author's updated design direction.

### D-269: Alt text and captions in the media library
- **Date:** 2026-09-30
- **Decision:** Builds the first part of D-238 (media metadata) and
  settles D-268's open item on filling images in from the library.
  - **Two fields, alt and caption** (`Media\MediaMetadata`), each one
    line of text, trimmed, `''` for none. D-238's field definitions
    (credit, description, a site's own, schemas) wait; a metadata file
    may hold other keys, which are kept.
  - **Storage as D-238 planned** (`MediaMetadataStore`):
    `user/data/media/{path under user/media}.yml`, or `_content/{path
    under user/content}` for bundle files, read through the data loader
    (`.json`, `.yaml`, `.yml`, in its order). Saving edits `alt` and
    `caption` only: YAML key by key (`YamlMap`, so comments and other
    keys stay), JSON as JSON; a file left empty is removed, and none is
    written for a file with nothing to say. An unreadable metadata file
    counts as none, so it can't break the library.
  - **API:** every file `GET media` and `GET media/{path}` describe (and
    `POST media` answers) has `alt` and `caption`; `PATCH
    media/{path}` (`media.upload`) changes either or both for a library
    file. An upload doesn't take a name that has leftover metadata.
  - **The admin:** a file's screen has a Details panel with Alt text
    (warned when an image has none) and Caption, saved with **Save**,
    asking before leaving unsaved changes; its "Use It" snippet for an
    image is now Markdown with them (it still showed D-267's retired
    `::figure{src}`). Inserting an image writes the library's alt text
    (selected text wins) and caption; **Replace** brings them only
    where the image has none. What an entry writes always wins.
- **Not yet (D-238):** rendering fallbacks (an image without alt text
  or a title taking the library's when the page renders, which needs
  the page cache to know about metadata files), the media index,
  embedded metadata, moving metadata with a file, `content:lint` for
  orphaned or unreadable metadata files, and editing a bundle file's
  metadata in the admin.
- **Checked:** `composer check` (`AdminPickersTest`: saved outside the
  media folder, listed, one field changed with the rest kept, other
  keys and comments kept, JSON kept JSON, an empty file removed, a
  bundle file's under `_content/`, bad input, a broken file, who may,
  and uploads skipping leftover metadata); in headless Chrome against
  the real API on a scratch site: the file screen empty and saved, the
  data file written, and an image inserted with its alt text and
  caption.
- **Why:** the author asked for alt text and captions in the media
  library.

### D-270: Pages use the library's alt text where theirs is empty
- **Date:** 2026-09-30
- **Decision:** Builds D-238's rendering rule for alt text, which D-269
  left open.
  - **Where it's used wins:** a Markdown image of local media (a library
    file, or one beside the entry) written without alt text, or with
    only spaces, renders with the library's (`ResolveLinks`, given the
    `MediaMetadataStore`). Alt text in the content always wins. Other
    addresses are left alone.
  - **Captions don't fall back:** a caption is visible text, and pages
    written without one (all of the jtcom trial's older images) would
    change; the library's caption is filled in on insert only.
  - **Separate, both ways (the author's note):** an image's alt text and
    caption in content are its own there. Editing them in the editor
    never writes to the library, and saving the library's never
    rewrites content, though content with empty alt text shows the new
    one. The image panel shows the library's alt text as the empty
    field's placeholder, with a hint saying the page uses it, and says
    "For this image here; the library's own doesn't change" once it has
    its own; the Media file screen says the reverse.
  - **Caches:** `PATCH media/{path}` moves the content version on, so
    cached bodies and pages re-render. A metadata file edited by hand
    needs a publish or `cache:clear`, as other site data does.
  - **The admin finds an image's library file** from its address
    (`mediaFile()` in `media.ts`: the media URL, which the shell config
    now carries as `media.url`, then `GET media/{path}`; a bare name
    beside the entry through `beside`), cached per file.
  - `ResolveLinks` collects the links and images, then rewrites them:
    changing an image's children during the walk lost its place.
- **Open:** marking an image decorative (empty alt on purpose) when the
  library has alt text for its file; components' own image props.
- **Checked:** `composer check` (`AdminPickersTest`: library alt text
  used for block and inline images, a bundle file's, content's own
  winning, captions not falling back, other addresses untouched, the
  content version moving on); on a scratch site, a page's image with
  and without its own alt text over HTTP, and the image panel's
  placeholder and hint in headless Chrome.
- **Why:** the author's request, and D-238's rendering rule.

### D-271: Decorative images are `{alt=""}`; no count on the Component tab
- **Date:** 2026-09-30
- **Decision:** Settles D-270's open item on decorative images, and
  supersedes the Component tab's count (D-265, D-268).
  - **The marker is `alt=""` in the image's attributes:**
    `![](/media/rule.png){alt=""}`. It's the HTML meaning of empty alt
    text said out loud, and the attributes parser already reads it (a
    bare `{decorative}` isn't an attribute to it). `ResolveLinks` gives
    library alt text only to an image without an `alt` attribute, so a
    decorative image keeps `alt=""` on the page. Alt text written in the
    brackets still wins over the attribute, as CommonMark renders it.
  - **The editor:** the image panel has a **Decorative** checkbox under
    Alt text. Checking it writes `alt=""` at the end of the attributes
    and clears the alt text (which it stands in for); the field turns
    off, saying screen readers skip it and the library's isn't used.
    Unchecking removes it. Classes, ID, and Variant keep it
    (`isDecorative()`, `withDecorative()` in `markdown.ts`). Empty alt
    text on an image that isn't decorative now says a screen reader may
    read its file name, rather than calling it decorative.
  - **No count on the Component tab:** it's named for what's selected,
    or "Components", with no number; the count stays on the "Components
    in this entry" row.
  - Fixed along the way: the image panel's size line cleared after any
    edit (its watcher fired on every change).
- **Checked:** `composer check` (a decorative image keeps `alt=""`
  beside library alt text); the checkbox on and off, the Markdown
  written, the field's state, and the tab without a count, in headless
  Chrome.
- **Why:** the author's request.

### D-272: Empty brackets are empty alt text; removing a container removes its body
- **Date:** 2026-09-30
- **Decision:** The author's call. Supersedes D-270 (pages taking the
  library's alt text) and D-271's `{alt=""}` marker, and D-245's
  "removing a container keeps its body".
  - **An image without alt text in its brackets renders `alt=""`**, as
    CommonMark does: no library fallback and no extra syntax. The
    library's alt text and caption are filled in when an image is
    inserted (D-269) and are the entry's own copy from then on; pages
    never read `user/data/media`. `ResolveLinks` no longer takes the
    metadata store, and `PATCH media/{path}` no longer moves the content
    version on. An image already written with `{alt=""}` still renders
    `alt=""`.
  - **Decorative is empty alt text.** The image panel's **Decorative**
    toggle is on when the brackets are empty, and the Alt text field is
    hidden then. Turning it off shows the field (focused) until there's
    something in it; turning it on clears the alt text. A decorative
    image whose file has library alt text offers **Use the library's**.
    The toggle comes first in the Text group, then Alt text, then
    Caption.
  - **Removing a container removes everything in it**, with a blank
    line after it, as a leaf goes with its line; an inline component
    still leaves its label in the sentence. Undo in the text brings it
    back.
- **Checked:** `composer check` (`MarkdownRenderingTest`: empty
  brackets are `alt=""` beside library alt text); in headless Chrome on
  a scratch site: the toggle on (field hidden, alt text cleared, the
  library's offered) and off (field shown and focused, typing writes
  it), and removing a callout with its body.
- **Why:** the author's call: no extra syntax, and a removed component
  shouldn't leave its contents behind.

### D-273: Changing your own password on Your profile
- **Date:** 2026-09-30
- **Decision:** Any account may change its own password in the admin
  (one of D-235's open items).
  - **API:** `POST {path}/api/password` with `{"current", "password"}`
    (`PasswordController`). The current password is checked by
    `Authenticator::confirm()`, throttled per address and username like
    a sign-in (a `429` when locked out). A wrong current password or a
    new one that's too short (`Accounts::passwordProblem()`) is a `422`
    whose `field` is `current` or `password`; the admin's `ApiError`
    carries it. Success is a `204`.
  - **Sessions:** the account's other sessions are signed out (the
    fingerprint changes, as with `account:password`). This one stays
    signed in: `Authenticator::refresh()` gives it a new id and the new
    fingerprint, and keeps its CSRF token so the open admin needn't
    fetch a new one.
  - **Fixed along the way:** a session signed out by a password change
    elsewhere kept its CSRF token, so that browser's next sign-in was
    refused (`403`) until the session expired. `Authenticator::account()`
    now forgets a stale sign-in and its token, and `VerifyCsrf` refuses
    a wrong token only while the session is still signed in: a stale
    session has nothing left to protect, and `Authenticate` still
    answers `401`.
  - **UI:** **Change password** in the Account panel, as in the
    prototype, opens an inline form (current and new password, with
    password-manager `autocomplete` and a hidden username); errors mark
    the field and focus it; Escape or **Cancel** closes it; success
    closes it with a toast. The Lucide `key-round` icon is added.
  - **Not done:** the prototype's email confirmation (Blush has no
    email) and **Sign out everywhere else** on its own; an administrator
    setting another account's password in the admin waits for editing
    accounts. There's no "confirm new password" field: browsers and
    password managers fill and save new passwords, and a mistyped one
    can be reset with `account:password`.
- **Checked:** `composer check` (`AdminApiTest`: wrong, short, missing,
  and CSRF-less requests; this session kept with a new id and working
  token; another session signed out and able to sign in again; the old
  password refused; throttling); on the jtcom trial in Chrome with a
  throwaway account and two browsers: both errors marked and focused,
  the change kept this browser signed in and signed the other out, and
  the new password signed in; 390px in dark mode.
- **Why:** the author asked for the quick wins, this one first.

### D-274: The editor's side of the index page
- **Date:** 2026-09-30
- **Decision:** Finishes D-255's editor items, from the design's "The
  index page is an entry, pinned".
  - **`IndexPage::is()`** (`Blush\Admin`) is the one test for an index
    page (a collection's or taxonomy's landing entry), used by the list
    and the editing API.
  - **`GET entries/{id}`** answers `index`. An index page's `type.fields`
    are only `title` and `status`: the type's other fields describe its
    entries, not its archive, so taxonomy fields, custom fields, and
    `published` are left out, and anything the file has for them is in
    `extra` ("kept as it is"), never removed. `can.delete` is false.
  - **Guarded on the server too:** `DELETE` of an index page is a 422
    ("… is the index page for Posts, so it can't be moved to the
    trash"); `status: scheduled` is a 400; `status: published` doesn't
    date it.
  - **The editor** marks it **Index** (the list's `index-mark`) beside
    the type in the header (hidden at 760px and under, with the type),
    calls it an "index page" (Edit Index Page, toasts), and adds a line
    at the top of the Document tab's Publishing group: "The index page
    for **Posts**, where readers find all of them. There's only one, so
    it can't be moved to the trash." With no date field there's no
    Schedule; with no `can.delete`, no Move to trash in the menu or the
    command palette.
- **Departs from the design:** the prototype's line also says "its slug
  is the type's URL base"; Blush's index page is the `index` file in
  the type's folder and has no slug of its own to edit, so the line
  leaves that out. The type screen's "Has an index page" switch and a
  new type being born with one still wait for editing types.
- **Checked:** `composer check` (`AdminEditingTest`: fields, `extra`,
  `can`, refused trash and schedule, publishing without a date, and the
  home page still a page); on the jtcom trial in Chrome: the Posts index
  page shows the mark, the line, no date or type fields, and a menu
  without Move to trash; an ordinary post has none of it; the Categories
  index page is marked too.
- **Why:** the author asked for the quick wins in turn; this was second.

### D-275: Duplicate
- **Date:** 2026-09-30
- **Decision:** Settles D-254's "Not yet: Duplicate (no API)".
  - **`ContentWriter::duplicate($id, $slug, $changes, $date)`** writes a
    copy beside the entry with the changes applied, under `$slug` or
    the first free `{slug}-2`, `{slug}-3`, …, never over anything. A
    dated name (file or bundle folder) takes the new date's prefix. A
    bundle's whole folder is copied (media too; links skipped), then its
    index file is written. A landing page is refused, as `rename()`
    refuses it.
  - **`POST entries/{id}/duplicate`** (`EntryController::duplicate`)
    needs `content.create` and `content.edit` for the entry. The copy is
    a draft titled "{title} (Copy)" ("Untitled (Copy)" for none), slugged
    `{slug}-copy` from the original's slug (not the title, so a
    hand-picked slug carries over), with the same authors and every other
    key as it was, and dated now when its type is dated. An index page
    is a 422 ("… and there's only one"). Answers `201` with the copy as
    `GET entries/{id}` describes it.
  - **`GET entries`** gives each entry `can.duplicate`: not for landing
    pages, and only with `content.create`.
  - **The row menu** has **Duplicate** (Lucide `copy`) after Copy link
    and before the divider, as in the prototype, for entries but not
    terms (the prototype's term menus have none). The list shows the
    result in its notice, "Duplicated as a draft: “… (Copy)”." with
    **Open it**, as Restore does, since the copy is a draft that the
    current tab may not show.
- **Departs from the design:** the prototype confirms with a toast;
  the list's other actions already use the notice with a link, and the
  link matters here.
- **Not done:** Duplicate in the editor's ⋯ menu (the design's editor
  menu has none) and duplicating terms.
- **Checked:** `composer check` (`FilesystemWriterTest`: dated copy,
  numbered second copy, file byte-for-byte but for the changes,
  original untouched, bundle media copied, landing refused;
  `AdminEditingTest`: `can`, the copy's id, title, status, extra,
  authors and body, `-copy-2`, index page 422, 404, an author's own vs
  someone else's); on the jtcom trial in Chrome: the index page's menu
  has no Duplicate, a post's does; duplicating shows the notice and
  the draft copy at the top of All (64 posts), Open it opens
  `register-custom-icons-wordpress-7-0-copy` as a draft; the copy was
  then trashed and deleted permanently from the Trash tab, leaving the
  trial as it was.
- **Why:** the author asked for the quick wins in turn; this was third.

### D-276: Previewing a trashed entry
- **Date:** 2026-09-30
- **Decision:** Settles D-254's "Not yet: a trashed entry's Preview".
  - **In the admin, not through the theme.** The prototype's trash
    Preview "opened the editor"; a trashed file isn't in the content
    index, so the signed preview page (D-226), which renders indexed
    entries, can't show it without building an entry outside the index.
    A read-only screen shows what's in it, which is what deciding
    between restoring and deleting needs.
  - **`ContentWriter::loadTrashed($trashId)`** reads a trashed entry's
    file (an `EditableEntry` with the id it had, its front matter and
    body, and the trash time as `modified`).
  - **`GET trash/{id}`** (`TrashController::show`) answers what `GET
    trash` lists for it plus `frontMatter` and `body`, for the accounts
    that may handle it (404 otherwise, as restore and delete answer).
  - **The screen** (`TrashedView`, `/trash/{trash id}`): the title,
    "Post · Moved to the trash {date}", **Trash** (back to the type's
    Trash tab), **Delete permanently**, and **Restore as a draft**
    (primary, then the editor opens on it); a warning notice that it
    isn't on the site and can't be edited until restored; the body in
    `MarkdownEditor` with a new `readonly` prop (the same highlighting,
    no spellcheck), leading blank lines trimmed; and the front matter
    but the title. The navigation marks its type.
  - **The Trash tab**: titles link to it, and each menu has **Preview**
    (Lucide `eye`) between Restore as a draft and the divider, as in the
    prototype.
- **Checked:** `composer check` (`FilesystemWriterTest`: reading one,
  and a missing one; `AdminEditingTest`: the answer, a 404, and an
  author kept from someone else's); on the jtcom trial in Chrome with a
  throwaway account: a throwaway duplicate trashed, its menu, the
  screen (read-only body, front matter, the type marked), Restore
  opening the editor on a draft, trashed again from the editor, opened
  from its title, and Delete permanently returning to the Trash tab with
  it gone.
- **Why:** the author asked for the quick wins in turn; this was fourth.

### D-277: Renaming from the editor, with redirects
- **Date:** 2026-09-30
- **Decision:** One of D-233's open items. Amends D-275 (what a copy
  keeps).
  - **The Slug field** is on the Document tab, in Publishing under the
    publish date (as the prototype has it), for every entry but a
    landing page (`can.rename`, false when `landing`; its slug is its
    folder's). `GET entries/{id}` answers `slug`. The type's `slug`
    field (`EntryFields`: "The URL name, instead of the file name") no
    longer shows under the type's fields (`PLACED`), since this is it.
    The slug is part of the editor's state (kept changes too; older kept
    changes without one still restore) and saves with everything else.
  - **What a rename changes:** the file's `slug` key when it has one
    (that's what names the entry; renaming the file wouldn't move it),
    else the file (`ContentWriter::rename()`). The file is renamed
    **before** the other changes are written, so a refused name leaves
    the file untouched; before, the changes were written and then the
    rename could fail.
  - **Checked up front**, as a 422 with `field: "slug"`, so the editor
    marks the field (it opens the drawer on the Document tab and focuses
    it; nothing is saved and the rest of the changes stay unsaved): not
    a slug ("Slugs are lowercase letters, numbers, and hyphens; try
    …"), another entry of the type with that key (`ContentRepository::
    named()`, so a dated post can't take another post's slug on another
    day either, which would make its handle ambiguous), or a landing
    page.
  - **Redirects:** `redirect: true` adds a published entry's current
    address to its `redirect_from` (after any there, its own set ones
    first; a lone string becomes a list; no duplicates), which
    `ContentRedirects` answers with a 301. The editor offers **Redirect
    the old address here**, on by default, once a live entry's slug
    changes, and the help shows the new address ("Saving moves it to
    /archives/…/new-slug."), or warns that links will stop working when
    it's off.
  - **Duplicate (amends D-275):** a copy drops the original's `slug` and
    `redirect_from` keys, so it's named by its file and doesn't claim the
    original's addresses.
- **Checked:** `composer check` (`AdminEditingTest`: a rename with and
  without other changes, taken and invalid slugs changing nothing, a
  landing page refused, `can.rename`, a slug key changed in place, old
  addresses kept and joined, a copy without `slug` or `redirect_from`);
  on the jtcom trial in Chrome with a throwaway duplicate of "Forty-two":
  a taken slug refused in the field (focused, still unsaved), a rename
  moving the editor's address and surviving a reload; published, the
  new address shown, the redirect on, and the old address answering
  301 to the new one; the throwaway then trashed and deleted.
- **Why:** the author asked for the quick wins in turn; this was fifth.

### D-278: Content type labels
- **Date:** 2026-09-30
- **Decision:** Supersedes D-234's `label` and `singular` (and settles
  D-256's open item). A type's names for people are one setting,
  **`labels`** (`TypeLabels`, `ContentType::$labels`), on every kind:
  - **Eight keys**, the ones the admin shows: `singular` ("Recipe"),
    `plural` ("Recipes"), `menu` (the navigation's name, defaulting to
    `plural`, so "Literary forms" can be "Forms" under Literature;
    added the same session at the author's request), `item` and
    `items` (the names mid-sentence: "the first recipe", "3 recipes"),
    and the phrases `newItem` ("New recipe"), `editItem` ("Edit
    recipe"), and `searchItems` ("Search recipes"). Unknown keys are an
    error. No "all items" or "not found" label: the description (D-256)
    is the empty state.
  - **Each defaults from the ones before it:** `singular` from the name
    (`literary_form` → "Literary form"), `plural` by D-234's English
    rules, `item`/`items` by lowercasing the first letter unless the
    first word has another capital ("FAQ", "HTML snippet", "McGuffin"
    stay; `mb_lcfirst`), and the phrases from those. A blank label is
    its default. `toArray()` leaves out labels equal to what the ones
    before them make.
  - **PHP:** `new TypeLabels('Person', plural: 'People')`; `singular` is
    the only required argument, and a type without `labels` gets
    `TypeLabels::named($name)`; `menu` is named, like every key after
    `singular` (`new TypeLabels('Literary form', menu: 'Forms')`).
    **Data:** a `labels` map, any keys (`labels: {plural: People}`);
    `singular` comes from the name when it's missing.
  - **The top-level `label` and `singular` options are gone**, not
    aliased: 2.x is unreleased, 1.x never had them, and one way to set
    names is simpler. They're unknown options now, so an old definition
    fails loudly. The jtcom trial's config moved to `labels`.
  - **The server makes every label; the admin never lowercases a type
    name.** `GET types` sends `labels` (all eight, defaults filled) in
    place of `label` and `singular`, sorted by `plural`. The admin's
    `labelsOf($name)` (`types.ts`) gives a type's labels, or plain ones
    from its name while types load. The list, New entry, the editor,
    Trash, the dashboard, and the command palette use them; the Content
    type screen shows them all. Server messages use `item`/`items` in
    place of `lcfirst()`/`strtolower()` (which made "fAQ" and "faq").
    Admin headings still title-case them (`titleCase`). The navigation
    uses `menu` (and names a shared taxonomy's types by it) and sorts
    by it; everything else uses `plural`.
  - **Also:** the admin's `inSentence()` (field and icon names) keeps a
    first word with another capital as it is, the same rule.
- **Open:** translating labels, which waits for the admin's own
  translation (open-questions.md).
- **Checked:** `composer check` (defaults chained from `literary_form`,
  overrides and what `toArray()` keeps, acronyms and multibyte names,
  unknown keys and the old options rejected, `GET types`); `npm run
  admin:build`; the jtcom trial's config loads with every type's
  labels as expected.
- **Why:** the author asked for a proper labels system for content
  types, kept to the uses that matter for the CMS.

### D-279: The editor's chrome doesn't fade while typing
- **Date:** 2026-09-30
- **Decision:** Supersedes D-245's fade. While keys move, the editor's
  header (the toolbar with the inserters, save state, and Update button)
  and footer stay at full opacity. The `is-writing` state, its
  pointer listeners, and `MarkdownEditor`'s `typed` event are gone.
- **Why:** the author found the darkened toolbar, then the footer,
  distracting while editing.

### D-280: The editor's elements, outline, breadcrumb, and Enter; drawn selects and dates
- **Date:** 2026-09-30
- **Decision:** From the author's updated design direction
  (`admin-design/admin.md` and `blush-admin.html`, uploaded as is). Its
  project-specific parts (files, departures, settled questions) move to
  a separate doc, `admin-design/departures.md`, so the direction can be
  replaced wholesale. Supersedes D-245's footer chip and shortcut hints,
  D-244's Home navigating, D-265's and D-268's Component tab name and
  "Components in this entry" list, and D-268's list item attributes
  above a list.
  - **Every element is an object** (`elements.ts`): one resolver over
    directives, images, and blocks takes the smallest span holding the
    caret (a list item over its list, an image over its paragraph), so
    inside a callout the caret is in a paragraph. On a blank line it's
    the element above at the caret's own level: one inside a container
    that closed above the caret is passed over. An element picked from
    the outline, a Content group, or the breadcrumb stays picked while
    the caret stays put (a list and its first item start on one line).
  - **New blocks** (`markdown.ts`): **lists**, rebuilt from their items'
    indents (items separated only by blank lines are one list), each
    item reaching over what's nested under it; a list's attributes on a
    line of their own above it (the parser reads them there; the item
    no longer claims that line), written when needed and removed when
    both fields are emptied; a **List Type** (Bulleted, Numbered, Task)
    that rewrites the markers at the list's indent. **Definition lists**
    (`:` with one colon; groups a blank line apart are one list), with
    each term and definition an element. The site's parser gives a
    definition list, a term, and a definition no attributes, so their
    panels have none (a departure).
  - **The outline**: every container and leaf directive, image (a line
    that's only an image isn't also a paragraph), and block, in order,
    with depth by containment (container, list, list item, definition
    list), a name column (quiet mono; a component's in the accent) and a
    line of what's in it. It's a drilldown at the foot of the entry's
    tab ("← Post / Outline 14"), also in the ⋮ menu and the palette. A
    holder's panel has a **Content** group, one level deep.
  - **The drawer's tabs** are the type's singular label, title-cased
    (the index page's is "Index page"), and the element's name
    ("Heading 2", "List"), else "Elements".
  - **The footer** is the breadcrumb (every element holding the
    caret, outermost first; each crumb selects it; the root opens the
    entry's tab; middle crumbs shrink first) and the words and reading
    time. No shortcut hints, and no chip.
  - **The ⋮ menu** (vertical, after the primary button) has named
    sections: View (Settings panel ⌘/, Outline, Focus mode ⌘⇧F, View
    when live) and Entry (Save draft or Switch to draft, Copy link when
    live, Duplicate unless a term or index page, then Move to trash
    after a divider). No Preview or Revisions item: an unpublished
    entry's preview link stays on the entry's tab, and there are no
    revisions. `GET entries/{id}` answers `can.duplicate`.
  - **Enter carries the marker** (`continuation()`): a list item's
    marker (the next number, renumbering the run from its first number;
    an open box for a task), a quote's `>`, a table's row (writing the
    delimiter row first when there's none); Enter on an empty one ends
    it, leaving a blank line above the caret. One edit, so one undo.
  - **Marking the source:** a table's header cells at 600, a delimiter
    row's colons at `--fg-2` 600, a term at 600, a definition's `:`
    dimmed; the scan tells the highlighter which lines those are. The
    text area's selection is a 26% accent tint and the copy's is
    transparent.
  - **The rail never navigates**, Home included. The editor collapses
    the section panel on the way in and puts it back on the way out,
    without changing the remembered setting.
  - **Selects are drawn** (`AdminSelect`): a Vue component, not a
    `MutationObserver` enhancing every `<select>` (Vue owns the DOM, and
    there are five). The real `<select>` stays, visually hidden, holding
    the value; the button is what a label names.
  - **The Publish group** is label → value rows: **Status**, a menu of
    Draft and Published (or Scheduled, with a future date), each with
    what it does, which saves with that status; **Date** ("Goes live"
    when in the future), which opens a Monday-first month with a
    12-hour time (`DatePicker`, also used for a type's date fields in
    place of `datetime-local`) and a line saying what it means; and
    **Slug**.
- **Kept as they were:** the chrome doesn't fade while typing (D-279;
  the direction brought the fade back); a type's words come from
  `labels` (D-278), not `singular`; no visibility (Blush has none); and
  the direction's featured image, authors as people, taxonomy tree and
  token field, and parent tree wait for the reference picker.
- **Checked:** `composer check`; `npm run admin:build`; a scratch run
  of the scanner (blocks, outline, resolver, Enter, list types, marks);
  on the jtcom trial in headless Chrome with a throwaway account (since
  removed), nothing saved: the rail, the panel collapsed and restored,
  the tabs, breadcrumb and crumbs, the Outline and Content groups, the
  List Type select (Escape closes only it), Enter in lists and tables
  and its undo, the blank line after a closed container, the ⋮ menu,
  the Status menu, and the calendar.
- **Why:** the author uploaded the updated design direction and asked
  for its changes, mostly the editor's, to be implemented.

### D-281: The reference picker, and the document panel's groups
- **Date:** 2026-09-30
- **Decision:** D-242's third stage, and the rest of the direction's
  document panel (admin.md §8). Corrects D-280, which said Blush has no
  visibility: every entry has `visibility` (D-082), which the editor
  showed as a plain select among the type's fields.
  - **`GET references/{type}`** (`ReferencesController`): what a
    reference field to a type can point at, for anyone who edits content
    (not only entries they may edit: an author files under a category
    they can't edit). Items are `slug`, `title`, `status`, `parent`,
    `uses` (terms), `depth` (in a tree), `virtual` (a slug in use with no
    file), and `missing` (a held slug nothing answers to). A
    hierarchical taxonomy answers every term in tree order; anything
    else answers a `search` of titles and slugs, by title, up to `limit`
    (20, at most 100). `slugs` are always answered, found or not.
    `create` is true for a taxonomy (a slug with no term becomes a
    virtual one, D-242), and `tree` for a hierarchical one. The index
    page is left out.
  - **`ReferencePicker`**, one picker shaped by the field: a
    hierarchical taxonomy is one box of search (keeping a match's
    parents), the tree as checkboxes with use counts, and **New
    {term}** (name and parent, the parent a tree select), which writes
    the term with `POST entries` (published when the account can
    publish) and ticks it; another multi-value reference is a token
    field (Enter takes the first suggestion, or writes what's typed when
    `create`, keeping the typed words, which a virtual term shows;
    Backspace removes the last chip); the authors field (`to` the
    author type) is people, the first marked Lead, with no × on the last
    one (the handler refuses too); a single value (a term's `parent`) is
    an `AdminSelect` in tree order without the term and its descendants.
    The form value is still the slugs separated by commas, so saving is
    unchanged, and `FieldControl` uses the picker for any reference with
    a `to`.
  - **The Document tab's groups**, in the direction's order: Publish
    (Status, Date, Slug, **Visibility** as a menu of Public, Unlisted,
    and Hidden with what each does, where Public writes nothing unless
    the file said `public`; and **Parent** for a term), **Featured
    Image** (the `image` field, 16:9, the image panel's preview, now
    `ImagePreview`), **Authors** (with "2 people"), each other reference
    (with "3 selected"), **Summary** (with "84 / 160"), then the type's
    other fields as a form. A term shows Featured Image and Authors only
    when its file has one.
- **Departures:** the people rows show an author's slug, not a role
  (an author isn't always an account); a term keeps its Visibility row
  and Date, as Blush terms have both; no avatar images.
- **Open:** the author says the site's parser takes classes and ids on
  definition lists. Checked against `CommonMarkParser` with the default
  and the jtcom trial's config (above, below, trailing on a term or
  definition, `{: …}`), none reached the HTML, so D-280's panels without
  attributes stay until the syntax that works is known.
- **Checked:** `composer check` (`AdminReferencesTest`: a tree in order
  with depths, parents, and uses; virtual terms with their words;
  search; held slugs found and missing; `limit`; input errors; an
  author seeing terms they can't edit); `npm run admin:build`; on the
  jtcom trial in headless Chrome with a throwaway account (since
  removed): the groups on a post, the category tree searched and
  ticked, a new literary form typed, the authors search, a topic's
  parent select without itself, and **New topic** under Art (the file
  had `parent: art`; since deleted and reindexed).
- **Why:** the author asked to start on the reference picker, and
  pointed out visibility.

### D-282: Definition lists take attributes
- **Date:** 2026-09-30
- **Decision:** Supersedes D-280's panels without attributes and settles
  D-281's open item. league/commonmark's attributes extension already
  attaches `{…}` to a description list and its terms, but its
  description list renderers pass no attributes, so they never reached
  the HTML. Blush now renders `<dl>`, `<dt>`, and `<dd>` with them
  (`DescriptionListRenderer`, registered over league's when
  `DescriptionListExtension` is configured), and moves attributes at the
  end of a tight definition from its paragraph (which a tight `<dd>`
  doesn't print) to the `<dd>` (`DescriptionAttributes`, after the
  attributes listener), as league does for a tight list's items. A
  loose definition keeps them on its `<p>`, as a loose list item does.
  - **Where they go:** `{.glossary}` on a line of its own above (or
    below) the list, `{#one}` at the end of a term, `{.note}` at the
    end of a definition.
  - **The editor** gives the Definitions, Term, and Definition panels
    Classes and ID again: the list's on a line of its own above it (the
    list claims that line, as a list does), a term's and a
    definition's at the end of its line.
- **Checked:** `composer check` (`CommonMarkParserTest`: above and
  below, terms, tight and loose definitions, and a list without any
  unchanged); `npm run admin:build`; a scratch run of the editor's
  scan and edits (reading each place, writing and removing the list's
  line, a definition's and a term's).
- **Why:** the author asked whether the PHP side could support them.

### D-283: The document panel as the prototype draws it; only the type's taxonomies
- **Date:** 2026-09-30
- **Decision:** From a check against `blush-admin.html`'s document
  panel, at the author's request.
  - **Publish** is the prototype's rows: Status, Visibility, Date,
    Slug (and a term's Parent), each value filling its row with its
    caret at the end; Status in its state's ink (green published, gray
    draft, accent scheduled) with its icon and word; the slug a
    borderless value that becomes a field on focus; a term's Parent a
    plain `AdminSelect` (`plain`). One quiet line under the rows says
    what the date means and when the file was last edited ("Published
    152 days ago · Last edited 1 day ago."); the slug's help and the
    redirect choice appear only once the slug changes. The **View**
    button and the preview link leave the group: **Preview** is in the
    ⋮ menu's View section (a signed link, D-226, opened in a new tab,
    to the entry as last saved), with **View** there once it's live.
  - **The pickers and fields** follow the prototype's rules: filled
    search fields and tag boxes (`--bg`), custom checkboxes (a hidden
    real input and a drawn box), gray chips, gray avatars, a quiet
    **New {term}** at the tree's foot; the drawer's typed-into fields
    are filled wells too. A taxonomy group is headed by the taxonomy's
    plural label ("Topics"), not its field.
  - **The Outline** no longer overflows: the list is a one-column grid
    whose rows can shrink, the name column truncates (at most half the
    row), and the excerpt truncates, as the prototype's rows do.
  - **Only the type's taxonomies** (`EntryController::describe()`):
    the schema keeps every taxonomy's term field, so a file may use any
    of them, but `GET entries/{id}` sends only those of taxonomies that
    group the type (naming it in `types`, or with no `types`, unless the
    entry is itself a term), and any the file already uses, so it stays
    editable.
- **Checked:** `composer check` (`AdminEditingTest`: a post offered its
  own and every-type taxonomies but not a page's, a page offered its
  own, one a post's file uses kept, and a term not offered the
  every-type taxonomy); `npm run admin:build`; the prototype and the
  jtcom trial side by side in headless Chrome (a post shows Publish,
  Featured Image, Authors, Topics, Eras, Summary, and Post Fields, no
  literary taxonomies, and no sideways overflow in the Outline).
- **Why:** the author found the panel didn't match the mockups, the
  outline overflowing, and non-post taxonomies on a post.

### D-284: The Markdown editing experience, first set
- **Date:** 2026-09-30
- **Decision:** Settles part of D-252's open "what the Markdown
  experience needs", with the affordances Markdown editors share, all
  editing the source as one undoable step (`markdown.ts`,
  `MarkdownEditor`):
  - **Formatting keys:** ⌘B strong (`**`), ⌘I emphasis (`*`), ⌘E code
    (`` ` ``), ⌘⇧X struck (`~~`), each toggling (`toggleMark()`): marks
    around the selection or at its ends are removed, else added; a
    selection's end spaces stay outside the marks; three stars count as
    both strong and emphasis; nothing selected puts in a pair with the
    caret between.
  - **Links:** ⌘K with text selected (`linked()`): words become
    `[words]()` with the caret where the address goes, an address
    becomes `[](address)` with the caret in the label. With nothing
    selected, ⌘K stays the command palette (D-248). An address pasted
    over selected words (one line, not itself an address) links them.
  - **Nesting lists:** Tab and Shift+Tab in a list item (`nested()`)
    move it under the item above at its level (lined up with that
    item's text) or back to the item it's under, with the lines nested
    under it; a numbered item starting a level starts at one, and the
    runs it left and joined are renumbered. Elsewhere, and on a first
    item, Tab leaves the field as before (D-245).
  - **Files:** dropping or pasting files on the text uploads each
    (`POST media`, needs `media.upload`) and inserts it at the caret as
    the media picker does (`insertFile()`, now shared: an image as
    Markdown with the library's alt text and caption, else a video,
    audio, or file component). The text is outlined while a file is
    held over it.
  - The formatting commands are in the command palette, with their
    keys.
- **Open (D-252):** what comes next: candidates are heading levels from
  the keyboard, moving lines, and smart paste of HTML as Markdown;
  image and embed previews still wait.
- **Checked:** `npm run admin:build`; a scratch run of `toggleMark()`,
  `linked()`, and `nested()` (on and off, spaces, `***`, links from
  words and addresses, nesting, un-nesting, numbering, nested lines,
  nowhere to go); on the jtcom trial in headless Chrome with a
  throwaway account (since removed): each key and its undo, ⌘K with and
  without a selection, Tab and Shift+Tab, an address pasted over words,
  and a dropped PNG uploaded and inserted (the file since deleted).
- **Why:** the author asked to start on the Markdown editing
  experience.

### D-285: Heading levels and moving lines from the keyboard
- **Date:** 2026-09-30
- **Decision:** Two of D-284's open items, at the author's request.
  - **⌘⌥1 to ⌘⌥6** make the line, or every line in the selection, a
    heading of that level (`withHeading()`), with the hashes after any
    quote or list marks and in place of any there; if every line is
    already that level, they go back to paragraphs. **⌘⌥0** makes them
    paragraphs. Code, directives' own lines, rules, and blank lines are
    left alone. The keys are read by `event.code` (`Digit2`), since
    Option changes the character a digit types on a Mac.
  - **⌥↑ and ⌥↓** move the line, or the selected lines, past the line
    above or below (`movedLines()`), keeping the selection on them; a
    selection ending at the start of a line doesn't take it. A numbered
    list is renumbered where the lines left and where they landed, from
    the number its run started at before the move (`runStart()`;
    `renumber()` takes that start). Lines, not blocks: the same keys
    work in any Markdown, as in code editors.
  - Both are one undoable edit, keep the caret at its distance from its
    line's end, and are in the command palette (Heading 1 to 6,
    Paragraph, Move line up, Move line down).
- **Checked:** `npm run admin:build`; a scratch run (levels on and
  off, paragraphs, quotes and list items, several lines, code and blank
  lines left alone; moving up and down, at the edges, several lines, a
  selection ending at a line's start, numbered runs starting at 1 and at
  3, a numbered list under a bulleted one); on the jtcom trial in
  headless Chrome with a throwaway account (since removed): each key,
  and undo.
- **Why:** the author asked for the heading shortcuts and moving lines
  next.

### D-286: Pasting HTML as Markdown is on hold
- **Date:** 2026-09-30
- **Decision:** The last of D-284's open items, converting pasted HTML
  (from a web page or a word processor) to Markdown, waits. Pasting
  keeps the browser's plain text, with D-284's link paste and files.
- **Why:** the author's call.

### D-287: Media metadata fields, by kind
- **Date:** 2026-09-30
- **Status:** The extension, data file, and config layers are superseded
  by D-341: a site's and extensions' media fields are field sets aimed
  at `media:{kind}`, and can't replace a built-in field.
- **Decision:** Builds D-238's fields and settles three of its open
  questions, at the author's call:
  - **Order:** fields first; then the media index; then embedded
    metadata.
  - **Field sets by kind:** fields every file has, plus fields for one
    kind (`MediaKind`: `image`, `video`, `audio`, and `file` for
    anything else, from the MIME type), not one set for all or sets by
    folder.
  - **The media index will be separate** from the content index,
    rebuilt incrementally, so reindexing content stays fast.
  - **Embedded metadata will be read in-house**, images first
    (`exif_read_data()`, `iptcparse()`, XMP through DOM, behind Blush's
    reader interface); audio and video durations wait.
- **What's built:**
  - **Built-in fields** (`MediaSchemas::builtIn()`): `caption`,
    `credit`, and `description` (Markdown) for every kind, and `alt`
    ("Alt text") for images. A kind's schema is its own fields first,
    then every kind's, a kind's field replacing one of every kind's
    with its name.
  - **More fields from**, in order, each replacing a field of the same
    name before it: extensions (`MediaFieldSource`, tagged
    `media.fields`, like `ContentTypeSource`); the site's
    `user/data/media-fields.{json,yaml,yml}` (`all` and each kind
    mapped to lists of field definitions, as a data type's `fields`);
    and `config/media.php` (`MediaConfig::$fields`, a list of
    `MediaFieldSet`s, or the same map in array form). They're the
    content types' field types (D-042), with their validation and
    admin controls.
  - **`MediaMetadata`** holds a metadata file's values by key; `alt`
    and `caption` stay one-line text for Markdown. The store
    (`MediaMetadataStore::save()`) sets and removes only the keys
    asked for, under whichever of a field's name and aliases the file
    uses, keeping other keys and a YAML file's comments.
  - **API:** `GET media/{path}` adds the file's `fields`, their
    `values`, the metadata file's other keys (`extra`), and
    `violations`; `PATCH media/{path}` takes `set` and `remove`, each
    value checked by its field (a 422 naming the `field`), and `alt`
    and `caption` on their own as before. A file's `kind` in every
    answer is its `MediaKind` (a caption track is `file`, not `text`).
  - **The admin's Details panel** is built from the fields
    (`FieldControl`, as an entry's form is), sends only what changed,
    lists the other keys as they are, shows what doesn't fit under its
    field, and keeps the missing-alt-text warning for images.
  - **`media.schema.json`** (`composer schemas`): the built-in fields,
    for editors checking metadata files.
- **Not yet (D-238):** the media index, embedded metadata, moving
  metadata with a file, `content:lint` for orphaned or unreadable
  metadata files, and editing a bundle file's metadata in the admin.
- **Checked:** `composer check` (`AdminMediaFieldsTest`: kinds from MIME
  types, the built-in fields by kind, a site's config and data file and
  an extension's fields in order, a built-in replaced and required,
  saving with an alias and other keys and comments kept, and values
  checked by their fields; `AdminPickersTest` and `MediaTest` updated);
  `npm run admin:build`; the trial site's media screen in headless
  Chrome with a throwaway account (since removed).
- **Why:** the author asked to start on media metadata, and answered
  its open questions.

### D-288: The media index
- **Date:** 2026-09-30
- **Decision:** D-238's media index, separate from the content index
  (D-287).
  - **What's in it** (`Media\Index`): every file of the types the
    library lists (`MediaResolver::EXTENSIONS`, moved there from the
    admin's controller; caption tracks aren't library files) that the
    site allows, never hidden ones, in `user/media` and in page bundles
    in `user/content`, keyed as the metadata tree is (`2026/09/lake.jpg`,
    `_content/trip/beach.jpg`): its URL, MIME type (read from the file
    by the resolver), size, modified time, dimensions, and metadata
    file's values and modified time (`MediaRecord`). Metadata files
    whose media file is gone are listed as `orphans`.
  - **Stored** in `storage/index/media.php` (`MediaIndex`, a PHP array
    file, as the content index is), with a fingerprint of the media URL
    and allowed types; another fingerprint rebuilds it. A concrete
    class for now, not an interface: nothing else stores media records
    yet.
  - **Built incrementally** (`MediaIndexer`): a file is read only when
    its size, modified time, or metadata file's modified time changed;
    metadata files are found in one walk of `user/data/media`, taking
    the data loader's first format when a file has two. Files are in
    key order, and the index is written only when something changed.
    The trial site's 290 files: 272 ms to build, 5 ms unchanged.
  - **Kept fresh** (`MediaLibrary`) as content is: built on first use
    when missing or built with other settings; in development
    (`MediaConfig::$autoIndex`, default on) refreshed incrementally on
    the first use in each request; elsewhere by `media:index [--full]`
    (new), `publish` (after content; `PublishReport::$media`), the
    admin's **Reindex content** action (now both indexes), an upload,
    and a saved Details form.
  - **Queried** (`MediaQuery`): search in the path and the metadata's
    text values (any case), kind, library or bundle files, and images
    without alt text, newest first, then by path, a page at a time.
    `GET media` now lists from it (no longer walking the folder and
    reading each file's metadata per request), with `missing=alt`.
  - **The admin's Media screen** has **Missing alt text** beside the
    kinds, searches names and details, and marks an image without alt
    text on its thumbnail (an icon, read out as "No alt text").
- **Not yet:** `content:lint` and `doctor` reporting orphans (the index
  lists them, and `media:index` warns); bundle files in the library
  screen (the index has them; the editor's picker still reads an
  entry's folder); embedded metadata, which will be cached in these
  records.
- **Checked:** `composer check` (`MediaIndexTest`: library and bundle
  files, skipped hidden and wrong files, metadata values, orphans, only
  what changed refreshed, removals, queries by search, kind, missing alt
  text, bundles, and pages, a rebuild for other types, and the command;
  `AdminPickersTest`: the list's `missing=alt`, search by alt text, and
  a save showing at once); `npm run admin:build`; the jtcom trial:
  `media:index`, and the Media screen's filter and search in headless
  Chrome with a throwaway account (since removed).
- **Why:** the author asked to start on the media index.

### D-289: Embedded image metadata, read in-house
- **Date:** 2026-09-30
- **Decision:** D-238's embedded metadata, images first, read in-house
  (D-287).
  - **One set of keys** (`Media\Embedded\EmbeddedMetadata`): `title`,
    `description`, `creator`, `copyright`, `credit`, `keywords`,
    `created` (`YYYY-MM-DD HH:MM:SS`, with the offset when given),
    `camera`, `lens`, `focalLength`, `aperture`, `exposure`, `iso`,
    `orientation`, and `software`; text made UTF-8 (older files write
    Latin-1), controls and padding removed. The location (GPS, decimal
    degrees) is kept apart.
  - **Three readers**, the Type enum + Registry + Factory + Registrar
    pattern (`EmbeddedReaderType`, `EmbeddedReaderRegistry` seeded in
    `MediaServiceProvider`, `EmbeddedReaderFactory`,
    `EmbeddedReaderRegistrar`), merged in the registry's order, the
    first value for a key winning (`EmbeddedMetadataReader`): **XMP**
    (`XmpReader`: the packet found by its markers in the first 8 MB, so
    any format, SVG's bare `rdf:RDF` too; properties as attributes or
    elements, language alternatives by the default, lists and bags;
    no network or external entities), **IPTC** (`IptcReader`: JPEG
    APP13 through `getimagesize()` and `iptcparse()`), then **EXIF**
    (`ExifReader`: `exif_read_data()`, skipped without the extension;
    a model that names its maker isn't named twice). A reader that
    fails on a file is skipped.
  - **Cached, not stored:** each image's record in the media index
    (D-288) has it (`MediaRecord::$embedded`, `null` when the file says
    nothing), read only when the file changes. The index's fingerprint
    includes the readers (`EmbeddedMetadataReader::fingerprint()`), so a
    new reader rereads every file; the snapshot format is version 2.
  - **Privacy:** `GET media/{path}` answers `embedded` as `values` and
    `location` (whether there is one), never coordinates. The admin
    warns that a file carries its location without saying where.
  - **The admin's From the File panel** lists the values, each with
    **Use** to copy it into the field it fits (title → caption,
    description → description, creator or credit → credit), as an edit
    the form still asks to save.
- **Also:** panels (`.panel`) are one column that can shrink
  (`minmax(0, 1fr)`), and the file screen's side column too, so the Use
  It box's long snippet scrolls in its box instead of widening the
  panel (the author's report).
- **Not yet:** rendering fallbacks (a page's image without a caption
  using the embedded title, and so on); audio and video metadata;
  stripping the location on upload; embedded artwork; D-270's alt text
  never falls back to embedded values, which aren't written as alt
  text.
- **Checked:** `composer check` (`EmbeddedMetadataTest`, with a JPEG
  built at run time carrying EXIF, IPTC, and XMP (`TaggedJpeg`): each
  reader, SVG's RDF, quiet failure on a broken file, the merge order,
  the index's cache, and text and date normalizing;
  `AdminMediaFieldsTest`: `embedded` answered with `location: true`
  and no coordinates); `npm run admin:build`; the jtcom trial (its 290
  files index in 6 ms unchanged; 61 carry GIMP's XMP, none EXIF or
  IPTC), and a temporary tagged JPEG's screen in headless Chrome (Use
  filled the caption, nothing overflowed), since removed.
- **Why:** the author asked to start on embedded image metadata, and
  reported the Use It box overflowing.

### D-290: Captions come from the library only; every media file has a title
- **Date:** 2026-09-30
- **Decision:** The author's calls, settling D-289's open question.
  - **Captions come from the library's details, never embedded
    values.** A caption is still filled in from the library on insert
    (D-269, D-270); a file's embedded title or description never
    becomes one, on insert or when a page renders.
  - **`title` is a built-in field of every media file**
    (`MediaSchemas::builtIn()`, after an image's own `alt`), one line
    (`MediaMetadata::$title`), answered with every file (`title`, `''`
    for none). The admin calls a file by it, falling back to its file
    name (`mediaName()` in `media.ts`): the library's cards (the file
    name on hover), the file's screen (its heading, with the file name
    in the line under it), the media picker's cards and footer (its
    search matches titles too), and the editor's toasts. The From the
    File panel's embedded title now fills `title`, not the caption
    (amends D-289). The index already searches it with the rest of the
    metadata.
- **Checked:** `composer check` (`AdminMediaFieldsTest`: the built-in
  field order with `title`, and a title saved as one line); `npm run
  admin:build`; on the jtcom trial in headless Chrome with a temporary
  file and a throwaway account (both since removed): Use filled the
  title, and the file screen and the library showed it.
- **Why:** the author's request.

### D-291: Sound and video metadata, read in-house
- **Date:** 2026-09-30
- **Decision:** The author's calls on D-238's open questions: sound and
  video are read in-house (no getID3), and embedded artwork is noted,
  not extracted.
  - **More keys** (`EmbeddedMetadata`): `album`, `track` ("3/12"),
    `genre`, `duration` (seconds, a float to the millisecond),
    `artwork` ("image/jpeg, 42 KB"), and a video's `width` and
    `height`; a date may be a year or a year and month.
  - **Five readers**, registered after the image readers, each reading
    only its own format (`BinaryFile` seeks and unpacks, so no file is
    loaded whole):
    - **ID3** (`Id3Reader`, MP3): ID3v2.2 to 2.4 (the four text
      encodings, unsynchronization, an extended header skipped, a
      genre's number dropped, `COMM`, `APIC`), then ID3v1 for what
      those lack; the duration from the first Layer III frame's Xing or
      VBRI frame count, else its bit rate and the audio's size.
    - **MP4** (`Mp4Reader`, MP4, M4A, M4V): boxes walked with `mdat`
      skipped wherever it is; `mvhd` for the duration, a video
      `tkhd`'s size, and `ilst` tags (`©nam`, `©ART`, `©alb`, `©day`,
      `©gen`, `©cmt`, `desc`, `cprt`, `©too`, `trkn`, `covr`), under
      `udta/meta` or `meta`, with or without its full-box header.
    - **Ogg** (`OggReader`, Vorbis and Opus): the comment packet put
      back together from its pages (with a `METADATA_BLOCK_PICTURE`
      noted); the duration from the last page's granule over the
      stream's rate (Opus: 48 kHz, less its pre-skip). Theora says
      nothing.
    - **RIFF** (`RiffReader`, WAV): the `data` size over `fmt `'s byte
      rate, and `LIST`/`INFO` tags.
    - **Matroska** (`MatroskaReader`, WebM): EBML to the first cluster:
      `Info` (timecode scale, duration, title, writing app, date) and a
      video track's pixel size; a segment of unknown size runs to the
      end. Tags after the media aren't read.
  - **The index** reads images, sound, and video; a video's width and
    height come from what it says. `EmbeddedMetadataReader::VERSION`
    is 2, so every file is read again once. Every file answer has
    `duration` (`MediaRecord::duration()`), which the library's cards,
    the picker, and the file screen show as `m:ss` (`mediaFacts()` and
    `formatDuration()` in `media.ts`, replacing two copies of the
    cards' facts); From the File lists album, track, genre, and
    artwork.
  - **A WAV is `audio/wav`** (`MediaResolver::mimeOf()`): the system's
    magic database calls it `audio/x-wav`, which the allowed types
    don't list, so WAVs weren't served or listed before.
- **Checked:** `composer check` (`AudioVideoMetadataTest`, with files
  built at run time (`TaggedAudioVideo`): each format's tags, duration,
  and size, Opus's pre-skip, a constant-bit-rate MP3, readers keeping
  to their formats and reading nothing from junk, and the index
  keeping durations, a video's size, and a WAV as `audio/wav`);
  `npm run admin:build`; the jtcom trial: its real MP3 read as 2:44
  (164.04 s, matching its size at its bit rate) with its title, artist,
  and encoder, and temporary MP3 and WebM files' cards and screen in
  headless Chrome with a throwaway account (all since removed).
- **Why:** the author asked to start on audio and video metadata, and
  answered its open questions.

### D-292: Page bundles' files in the media library
- **Date:** 2026-09-30
- **Decision:** The files beside entries in page bundles (D-099),
  which the media index already held (D-288), are in the admin's
  library.
  - **Where:** `GET media` takes `source`: `library` (the default,
    `user/media`), `bundles`, or `all`, and answers `bundles`, how many
    bundle files there are. The Media screen's **Where** control
    (Library, Beside entries, Everywhere) shows only when there are
    some, and a bundle file's card says **Beside** its entry.
  - **A bundle file's address** in `GET` and `PATCH media/{path}` is
    its index key, `_content/` and its path under `user/content`,
    confined there; its `reference` is the URL it's served at.
  - **Its entry** is the nearest folder above it with an `index.md`
    entry. Every bundle file answers `bundle`: the `name` that entry
    writes it by (its path under the entry's folder) and the `entry`
    (`id`, `handle`, `title`, or `null` when none holds it); a library
    file's is `null`.
  - **Changing its details** takes `media.upload` and editing its entry
    (`content.edit` for that entry), so an author can't describe
    someone else's photos. Its metadata goes to
    `user/data/media/_content/…` (D-269's layout).
  - **The file screen** says **Beside** with a link to the entry and
    its real folder, and Use It gives both what its entry writes
    (`![](shore.jpg)`) and the address for anywhere else.
- **Not yet:** uploading into a bundle from the admin (uploads go to
  `user/media`).
- **Checked:** `composer check` (`AdminPickersTest`: the library with
  the bundle count, bundle files with their entry and name, `all`, a
  bundle file's screen and saved details, a non-media file refused, a
  bad `source`, and an author refused another's entry's file but not
  their own); `npm run admin:build`; on the jtcom trial in headless
  Chrome with a temporary bundle and a throwaway account (both since
  removed and reindexed): the Where control, the card, the file
  screen's Beside and Folder, and Use It.
- **Why:** the author asked to start on bundle files after they were
  explained.

### D-293: Media metadata in `content:lint`
- **Date:** 2026-09-30
- **Decision:** `content:lint` (and the admin's Content health, which
  runs the same `Linter`) checks the metadata files under
  `user/data/media` (D-238, D-269) as well as content.
  `Media\MediaMetadataCheck` reports each by its path from the site
  root (`user/data/media/2019/gone.png.yml`), beside the content files'
  paths:
  - **Errors:** a file that can't be read (its parser's message, without
    the path it repeats) or isn't a map of fields, and a value that
    doesn't fit its field in the kind's schema (`MediaSchemas`, D-287).
    The library reads such a file as none, so this is where a site hears
    about it.
  - **Warnings:** a file whose media file is gone (renamed or deleted
    by hand): "describes user/media/2019/gone.png, which isn't there;
    move this file with its media file, or delete it"; one whose media
    file is there but isn't a type the site allows; and one hidden by
    a file in another format (`sunset.png.yml` beside the
    `sunset.png.json` that's read).
  - **Notices** (`--strict`): keys that aren't fields, and aliases, as
    for front matter.
  - **Counts:** `LintReport::$metadata` is how many metadata files were
    checked, hidden ones included; the summary reads "Checked 302 files
    and 2 media metadata files: …" (the clause is left out when there
    are none), in the command and in Content health (`metadata` in
    `GET health`).
  - **One walk:** `MediaMetadataStore::files()` finds every metadata
    file by its media key, with the file the data loader reads, its
    modified time, and the files it hides; the media indexer's orphans
    (D-288) use it too. `MediaResolver::fromKey()` resolves a media key
    (`2026/lake.png`, `_content/trip/beach.png`) for both.
- **Refines D-238:** it planned orphans in `content:lint` and `doctor`.
  `doctor` checks the install, not content, so it doesn't; `media:index`
  still warns of orphans as it indexes.
- **Checked:** `composer check` (`MediaMetadataCheckTest`: the walk
  with a hidden file, every kind of problem, files that pass, and the
  linter and command together); `npm run admin:build`; `content:lint`
  on the jtcom trial (clean, then with a temporary unreadable file and
  an orphan, since removed).
- **Why:** the author asked to start on orphaned metadata in
  `content:lint`.

### D-294: No media beside entries
- **Date:** 2026-09-30
- **Decision:** Media is only ever in `user/media`. Page bundle media,
  files kept in `user/content` beside an entry (`trip/index.md` with
  `trip/beach.jpg`) and written by name, is removed. **Supersedes** the
  bundle parts of D-099 (relative references into the entry's folder,
  served at `{url}/_content/…`), D-179 (media props resolved against the
  entry's bundle), D-246 (the picker's files beside the entry), and
  D-292 (bundle files in the library), and the `_content/` metadata
  layout of D-238 and D-269.
  - **Folder entries stay:** `slug/index.md` is still the entry `slug`
    (a 1.x convention, D-078), and renaming, duplicating, or trashing
    one still moves or copies its folder.
  - **Resolving:** `MediaResolver::resolve()` takes no base; a relative
    path is read from the site root (D-190's `user/media/a.mp3`), and
    `/media/_content/…` is just a path under `user/media`.
    `MarkdownParser::toHtml()` takes no base, `Directive` has no `base`,
    `Body` no folder, and `MarkdownContext` (which only carried the
    base) is gone.
  - **Serving and export:** the media route serves nothing from
    `user/content`, and a static export copies only `user/media`.
  - **The media index** lists only `user/media`; `MediaRecord` loses
    `isBundle()` and `relative()`, `MediaQuery` loses `bundles`, and
    the snapshot's version is 3, so an index holding bundle files is
    rebuilt.
  - **The admin:** `GET media` loses `entry`, `beside`, `source`, and
    `bundles`, and files lose `bundle`; `PATCH media/{path}` no longer
    checks an owning entry. The picker loses Beside This Entry, the
    Media screen its **Where** control and card line, and a file's
    screen its Beside fact and by-name snippet.
  - **Leftovers:** a metadata file under `user/data/media/_content/`
    is now an orphan, which `content:lint` reports (D-293).
- **Why:** the author: "Media should always be stored under the /media
  folder and not bundled with the content." 1.x resolved media from the
  site root, never beside entries, so no 1.x content relies on it, and
  the jtcom trial has none.
- **Checked:** `composer check` (bundle tests removed or rewritten:
  resolving, serving, export, components, Markdown, the index, the
  metadata check, and the admin API, each now asserting files beside
  an entry aren't media); `npm run admin:build`; on the jtcom trial,
  `media:index` rebuilt (290 files), `content:lint` clean, and the home
  page and admin answer.

### D-295: Extracting artwork waits
- **Date:** 2026-09-30
- **Decision:** Extracting embedded artwork from sound and video files
  is on hold. D-291's behavior stays: the media index notes that a file
  has artwork (its type and size) and From the File shows it, but the
  picture isn't extracted, cached, or shown. The open question stays
  open for when it's picked up.
- **Why:** the author asked to save it for later.

### D-296: One helper for encoding URL paths
- **Date:** 2026-09-30
- **Decision:** `Blush\Support\UrlPath::encode()` percent-encodes a
  decoded path one segment at a time, keeping its slashes. It replaces
  the seven inline copies of
  `implode('/', array_map(rawurlencode(...), explode('/', $path)))`:
  `MediaResolver`, `RoutePattern::build()`, `Exporter`'s redirect pages,
  `ExportAssets` and `NetlifyFiles` (each lose a private `encode()`),
  and the admin's `MediaListController` and `MediaUploadController`.
  `resources/static-server.php` keeps its own copy, since it runs
  without the framework. A static method beside `Slug`, since it's a
  pure function with nothing to inject.
- **Why:** the open question noted 2026-09-27 (five copies then, seven
  by now); a quick win the author picked.
- **Checked:** `composer check` (`UrlPathTest`: spaces, reserved
  characters, `%`, Unicode, and slashes kept as they are).

### D-297: Blush doesn't strip a photo's location
- **Date:** 2026-09-30
- **Decision:** Blush won't remove location (GPS) data from uploaded
  photos, on upload or by a setting. D-289's behavior stays: the
  location is read, kept apart, never shown or answered, and a file's
  screen warns that it's there. Removing it is up to the person
  uploading, with their own photo software (as `docs/media.md` already
  says). This closes the open question and the roadmap's "next for
  media" item.
- **Why:** the author: "That's outside our responsibility."

### D-298: Layout components take a `tag`
- **Date:** 2026-09-30
- **Decision:** Supersedes D-177's `group`-only `tag`. Which element a
  layout component renders as is an option, apart from how it lays out
  its blocks, rather than a component per element (a `division`,
  `section`, and `aside` that each also do flex and grid).
  - **`LayoutTag`** (was `GroupTag`): `div` (the default), `section`,
    and `aside`. `main` is left out because the theme owns it (a second
    one is invalid), and `header` and `footer` because inside an entry
    they'd belong to the theme's `<article>`, which authors won't
    expect; either can be added if a need shows up.
  - **`group`, `grid`, and `row` all take `tag` and `label`**, through an
    abstract `Component\Layout\Layout` base (abstract `tag` and `label`
    properties, which each class promotes, and `CONTENT` as blocks). On
    `grid` and `row` they come after the existing props, so positional
    arguments and the admin's field order keep their props first.
  - **A landmark (`section` or `aside`) is named by its label**
    (`aria-label`), and only when there is one; a `div`'s label is
    ignored. The label's admin field is "Name for screen readers" (was
    "Section name").
  - **The gallery stays separate:** its columns are a theme-styled
    custom property capped at the theme's breakpoints (D-198), which
    jtcom relies on, while `grid` is inline styles that need no media
    queries. They share no code.
  - The names `group`, `grid`, and `row` stay, since they read well in
    Markdown and the jtcom trial uses them.
- **Why:** the author asked about block-level HTML elements as
  components; one option keeps the element and the layout independent,
  makes changing the element a select on the Component tab instead of a
  different block, and keeps the inserter short.
- **Checked:** `composer check` (an `aside` grid named by its label, an
  unnamed `section` row, and the props' order); the jtcom trial's About
  page renders the same markup.

### D-299: The editor remembers the settings drawer
- **Date:** 2026-09-30
- **Decision:** Whether the editor's settings drawer is open is kept in
  the browser (`localStorage`, `blush-admin-drawer-open`, through
  `resources/admin/js/drawer.ts`), so a refresh or the next entry opens
  it as it was left. Any change counts: the toggle, ⌘/, the close button,
  Escape, and the editor opening it itself (a crumb, a field with a
  problem). Only on screens wider than 980px, where the drawer pushes the
  column aside: narrower, it lies over the column, so it starts shut and
  changes there aren't remembered. Storage that's off or fails leaves it
  shut. A departure from `admin.md` §8 ("the settings drawer starts
  shut"), recorded in `departures.md`; the section panel's courtesy
  collapse is unchanged.
- **Why:** the author: a refresh or opening another entry should keep it
  open.
- **Checked:** `npm run admin:build` (type-checked). Not driven in a
  browser.

### D-300: The entry list's filters, sorting, and page size
- **Date:** 2026-09-30
- **Decision:** From the design direction's §7 (Tables; Tabs, toolbar,
  filters) and §8 (Hierarchy; Type-driven variation), and the
  prototype's list screen:
  - **`GET entries`** takes `author` (a slug of the author taxonomy,
    `AuthConfig::$authorTaxonomy`), `terms` (`taxonomy:slug` pairs,
    comma separated, each required), `days` (updated in the last so many,
    1 to 36500), and `sort` (`title`, `status`, `author`, `updated`) with
    `dir` (`asc`/`desc`; by default `updated` is newest first and the
    rest A to Z). It answers them back, with `tree`. Any of them, like a
    status or search, flattens a nesting type's tree.
  - **The query:** `Query::updatedSince()` (a Unix time, matched against
    the record's `updated`), sorting by `status` as it is now (so a
    future-dated entry sorts as scheduled), and sorting by a taxonomy's
    first term when no field has the name (so the author sort follows a
    renamed author taxonomy).
  - **The filter row:** search (with an icon and a `/` key, ignored while
    typing elsewhere), then **Author** (not on a taxonomy's list, nor on
    the authors' own), one select per taxonomy the type uses (excluding
    authors), and **Updated** (any time, or the last 7, 30, or 90 days),
    each a drawn select (`AdminSelect`) sized to its content; **Clear
    filters** clears them all. Options come from `GET references/{type}`,
    at most 100 each; a filter whose options don't load, or that has
    none, isn't shown. The Trash tab keeps only the search.
  - **Sorting:** the Title, Status, Authors, and Updated headers are
    buttons (`aria-sort`, an arrow on hover, focus, and the sorted
    column); clicking again turns the order around. A term's Entries
    column doesn't sort. A flattened tree's note bar names the sort and
    has **Clear the sort**.
  - **Page size:** the pager offers 10, 20, 50, or 100 a page (20 by
    default), shown once a list has more than 10 entries.
  - The filters, sort, and page size are in the URL (`author`, `terms`,
    `days`, `sort`, `dir`, `per`), like the status and search, and the
    tabs' counts follow the filters.
- **Not now:** bulk selection and the bulk bar, toasts for the list's
  actions, and **Unpublished changes** (no autosave).
- **Why:** the author asked to refine the content lists from the design
  doc, and picked the filters row and sorting with page size.
- **Checked:** `composer check` (filtering by author, terms, and days,
  sorting each column both ways, and filters and sorts flattening a
  tree; malformed values refused); `npm run admin:build` (type-checked).
  Not driven in a browser.

### D-301: Bulk selection and the bulk bar
- **Date:** 2026-09-30
- **Decision:** From the design direction's §7 (Tables, Bulk bar):
  - **`POST entries/bulk`** (`EntryController::bulk()`): `action`
    (`publish`, `draft`, or `trash`) and `ids` (1 to 100). Each entry is
    changed at its current revision, read on the server: a status change
    edits only the status (and an undated entry's date), and a trashed
    entry can be restored, so nothing anyone wrote is lost (the row
    menu's Move to trash already loads the revision just before
    deleting). Each is checked alone, with the single-entry rules
    (`content.edit`, `content.publish` for anything not a draft,
    `content.delete` and never an index page for the trash), and
    **publishing needs the type's required fields** (empty, a blank
    string, or an empty list), the editor's rule now on the server for
    bulk changes; the publish date isn't checked, since publishing sets
    it, and an index page has no type fields. An entry that can't be
    changed is answered in `skipped` with its title and why, and the rest
    go ahead; `done` lists the ids changed.
  - **The checkbox column:** `EntryTable`'s `selectable` and
    `v-model:selected`, drawn checkboxes (`role="checkbox"`,
    `aria-checked`), a header that selects every row shown on the page
    (mixed when some are, with a minus), and selected rows tinted
    `--accent-soft`. The index page's pin moves into the column (it was
    before the title); Continued rows have no checkbox.
  - **The bulk bar:** a pill fixed to the bottom center, clear of the
    safe area, with the count, **Publish** (with `content.publish`),
    **Move to draft**, a divider, **Move to trash** (with
    `content.delete`, after a confirmation), and **Clear**. Not on the
    Trash tab. The selection clears when the type, tab, filters, sort,
    or page change, and after a change.
  - **The result** is the list's notice: "Published 3 posts." and, when
    some were skipped, a warning naming each with its reason.
- **Not now:** Discard changes (no autosave), bulk actions in the trash,
  and selecting across pages.
- **Why:** the author asked for bulk selection next (after D-300).
- **Checked:** `composer check` (moving to draft, publishing with and
  without a date, trashing, a missing entry, a required field left
  empty, an author trashing another's entry, malformed requests, and the
  CSRF token); `npm run admin:build` (type-checked). Not driven in a
  browser.

### D-302: The list's actions are toasts
- **Date:** 2026-09-30
- **Decision:** Supersedes the list's success notices (D-254, D-275,
  D-237, and D-301's result notice). On a content type's list, what an
  action did is a toast in the past tense, as admin.md §7 says: Moved
  “…” to the trash, Duplicated as a draft: “…”, Restored “…” as a draft,
  Deleted “…” permanently, Deleted 3 posts permanently, and the bulk
  bar's Published 3 posts, Moved 3 posts to draft, Moved 3 posts to the
  trash (or Nothing changed). No trailing period, like the editor's
  toasts. What an action couldn't do stays a notice above the list, as
  `toast.ts` says problems do: an error, and the entries a bulk change
  skipped ("2 posts couldn't be published:", each with why).
  - **No Open it link:** a toast is gone in 2.6 seconds, too soon for a
    link, so a duplicate or restored entry is found in the list (both
    are drafts).
- **Why:** the author asked to switch the list's actions to toasts.
- **Checked:** `npm run admin:build` (type-checked). Not driven in a
  browser.

### D-303: The list's filters offer only terms in use
- **Date:** 2026-09-30
- **Decision:** The entry list's Author and taxonomy selects (D-300)
  offer only the authors and terms the type's entries use, so a choice
  never finds nothing; a select with nothing to offer isn't shown.
  - **`GET references/{type}?for={type}`:** a taxonomy answers only the
    terms that type's entries use, in any status (a draft's terms count),
    among the entries the account may edit (`Permissions::restrict()`),
    with virtual terms that only drafts use too. A hierarchical taxonomy
    keeps each used term's parents (stopping at a loop), so its tree
    holds together. An unknown `for` is a 400.
  - **`ContentRepository::termCounts()`** takes an optional query and
    counts among the entries it finds (limit and offset aside); without
    one it counts listed entries as before.
- **Why:** the author asked to hide unused terms, after jtcom's era
  filter offered seven eras when only Current is used.
- **Checked:** `composer check` (unused terms left out, a parent kept,
  a draft's virtual term kept, an unrelated type answering none, bad
  `for` refused); on the jtcom trial, `era?for=post` answers only
  `current` and `category?for=post` 24 topics. `npm run admin:build`.
  Not driven in a browser.

### D-304: Terms are listed alphabetically
- **Date:** 2026-09-30
- **Decision:** Wherever the admin lists a taxonomy's terms (the entry
  list's filters, the reference picker, a hierarchical taxonomy's tree,
  where siblings sort by title), they're alphabetical by title, never in
  file order. A term file's numeric prefix (`07.current.md`) orders
  nothing in the admin. This is how it already works; don't propose file
  order as an option.
- **Why:** the author: "Terms should be alphabetical, not file order."

### D-305: More inline components, and `[text]{.class}` spans
- **Date:** 2026-09-30
- **Decision:** Extends D-175's inline set and settles how content
  writes a plain span. Built in this session.
  - **Spans are Markdown, not a component.** `[text]{.class #id}`
    (Pandoc's bracketed spans) renders `<span class="class"
    id="id">text</span>`. Until now the attributes extension silently
    dropped the `{…}` and left `[text]`. `BracketedSpanParser` runs
    just before league/commonmark's closing bracket parser and only
    takes a `]` that's followed straight away by a valid attribute list,
    which it leaves for the attributes extension to put on the span (so
    its filtering of `on*` and unsafe links applies). Links win: an
    image, an inactive opener, and a label with a link reference
    definition are left alone. It's on whenever `AttributesExtension`
    is configured.
  - **`<mark>` needs nothing:** `==text==` is on by default (D-176) and
    takes attributes (`==text=={.x}`).
  - **New core inline components**, each a class in
    `Component\Inline` with a template in the default theme, category
    Text, and inline in the admin's inserter: `badge` (`<span>`, with
    the callout's tones as variants: `info`, `tip`, `warning`, `danger`;
    Default is neutral), `cite`, `dfn` (`title`, the term when the text
    says it differently), `ins` (`datetime`, a real date or date and
    time as HTML requires of `<ins>`, else left out; `cite`, a URL),
    `samp`, `small`, and `var` (class `Variable`, since `var` is
    reserved in PHP).
  - **Not added:** `sub` and `sup`. The author didn't pick them; note
    that `~text~` is already strikethrough (`<del>`), so Pandoc's
    subscript syntax isn't available.
  - The default theme styles the badge; the browser's defaults serve
    the rest. The jtcom trial's theme got badge, `samp`, and `var`
    styles (it already styled `ins`, `cite`, `dfn`, and `small`).
- **Checked:** `composer check` (`CommonMarkParserTest`: spans, nesting,
  inside links and emphasis, links winning, bad attribute lists, without
  the extension; `InlineComponentsTest`: rendering and `ins` dates;
  `ComponentsTest`); `npm run admin:build`; the jtcom trial's
  `content:lint`, `theme:check`, `component:list`, and theme build.
- **Why:** the author asked for a badge and a custom span, and picked
  `ins`, `cite`/`dfn`, and `small`/`var`/`samp` from the HTML inlines
  Markdown can't write.

### D-306: The Appearance screen
- **Date:** 2026-09-30
- **Status:** The screen's read-only rows are replaced by D-381's cards,
  which activate a theme (saved in `user/data/settings.json`) and
  delete theme folders.
- **Decision:** The first of the three stubbed Config screens (D-241)
  is built. The author chose **read-only first** for all three
  (Appearance, Extensions, Settings): they show configuration and where
  it's set, and writing `config/` from the admin is a later, separate
  decision (D-039 stands). Appearance came first.
  - **`GET appearance`** (`AppearanceController`, `site.settings`):
    the active theme, its chain, whether `config/theme.php` exists,
    whether `?theme=` previews work (development), every installed
    theme (slug, name, version, description, parent, source, active),
    and the broken ones with the reason.
  - **The screen:** each theme as a row: **Active**, **In use** for the
    themes the active one builds on, else **Preview** in development and
    **Copy command** for `theme:activate`. Then broken themes, if any.
    Departures are in `admin-design/departures.md`.
  - A Theme Settings form (editing `user/data/theme.json`) was built
    and taken out in the same session; see D-307.
- **Checked:** `composer check` (`AdminAppearanceTest`); `npm run
  admin:build`; the jtcom trial in Chrome with a throwaway
  administrator (removed after).

### D-307: No theme settings in the admin, and no `excerpts`
- **Date:** 2026-09-30
- **Decision:** The default theme's `excerpts` setting is removed:
  listings always show the excerpt (supersedes that part of D-117).
  The Appearance screen has no settings form and the API no `PATCH
  appearance/settings`. Custom theme settings will come later with a
  proper API. The manifest `settings` mechanism
  (`SettingsResolver`, `user/data/theme.json`, `$template->setting()`)
  is unchanged for now.
- **Why:** the author asked to drop it and do theme settings properly
  later.

### D-308: The Extensions screen
- **Date:** 2026-09-30
- **Status:** It's the Plugins screen (`/plugins`, `GET plugins`), attributing by namespace, since D-379; drawn as the extensions sketch, with a switch and no list of what each plugin adds, since D-385.
- **Decision:** The second stubbed Config screen (D-241), read-only as
  D-306 settled.
  - **`GET extensions`** (`ExtensionsController`, `site.settings`):
    every installed extension, found by running discovery
    (`ExtensionDiscovery::forPaths()`), since the compiled extension
    cache holds only enabled ones (`extension:list`, planned in D-041,
    still isn't built). Each has its name, version, description, source,
    path, requirements, whether `ExtensionConfig` enables it, and what it
    `adds`; the ones that are on come first, then by name.
  - **What an extension adds** is what the admin can attribute without
    changing any registry or cache: content types from each tagged
    `ContentTypeSource` (asked for its types, each marked `overridden`
    when the site's `config/content.php` redefines it), admin actions
    (`AdminActionRegistry`), and console commands (tagged classes'
    `#[Command]`, hidden ones left out), each owned by the extension
    whose PSR-4 prefix or provider namespace is the longest match for
    the class; and components and icon namespaces by namespace (the
    extension named it, or every extension under that vendor, as
    `Provenance` does). An extension that's off adds nothing. Routes,
    media fields, embed providers, and events aren't listed.
  - **The screen:** a notice on where extensions are installed and
    turned off, then one row per extension (sharing the global
    `.package` classes, promoted from Appearance), with an On or Off
    pill, what it adds as grouped chips, and for one that's off, how to
    turn it on. Departures are in `admin-design/departures.md`.
- **Checked:** `composer check` (`AdminExtensionsTest`, with a fixture
  extension adding one of everything and one turned off); `npm run
  admin:build`; the jtcom trial in Chrome with two temporary local
  extensions and a throwaway administrator (all removed after).

### D-309: The Settings screen, and no more planned screens
- **Date:** 2026-09-30
- **Status:** The trailing-slash finding is corrected and fixed by D-310.
- **Decision:** The last stubbed Config screen (D-241), read-only as
  D-306 settled, with only the settings Blush has (the author's call;
  the prototype's other fields wait until they exist).
  - **`GET settings`** (`SettingsController`, `site.settings`): groups
    (General: name, address, locale, environment, detailed errors; Dates
    and Time: time zone, with the time there now; Content: home page,
    `user/data/types`, built-in types turned off; Addresses: trailing
    slash, media URL; Feeds: formats, full content, limit; Search
    Engines: sitemap, asking not to be indexed outside production,
    disallowed paths; Caching: on, driver, pages, browser max age;
    Publishing and Previews: webhook, git pull, preview links), each
    naming the file it's set in by convention (`ConfigRepository`
    doesn't track files), with items carrying a display value, a kind,
    whether it's still the default (against a default-constructed
    config; `null` for one that follows from others), help, and a
    warning (detailed errors in production). Secrets are never sent,
    only whether one is set.
  - **The screen:** a notice (`config/`, `.env`, compile again), then
    the groups as two columns of panels, each a list of label and value
    with a Default mark, ending with its file.
  - **The planned-screen mechanism is removed** (`PlannedView`,
    `PlannedScreen`, the router's `planned` map; supersedes that part of
    D-241): every screen in the navigation exists.
  - **Found, not fixed:** with `trailingSlash` on, the router redirects
    the admin's API paths to their slash form, which would break the
    admin's `POST` and `PATCH` requests (noted in the roadmap).
- **Checked:** `composer check` (`AdminSettingsTest`); `npm run
  admin:build`; the jtcom trial in Chrome with a throwaway administrator
  (removed after).

### D-310: Exact routes skip the trailing-slash redirect
- **Date:** 2026-09-30
- **Context:** D-309 reported that with `RouteConfig::$trailingSlash` on,
  the admin's `POST` and `PATCH` requests would be redirected as `GET`s.
  That was wrong: the router already answers unsafe methods with a 308,
  which keeps the method and body, and the admin worked in Chrome on the
  jtcom trial with the setting on. The real costs: every admin request
  was two round trips (301 or 308, then the slash form), the admin's
  addresses gained slashes (`/admin/sign-in/`), and webhook senders that
  don't follow redirects (GitHub's) would fail on the publish webhook.
- **Decision:** A route can be **exact**: it answers its path as written,
  with or without the slash, is never redirected to the canonical form,
  and `UrlGenerator` makes its URLs without `canonicalPath()`.
  - `Route::$exact` (`->exact()`, `toArray()`/`fromArray()`),
    `Route::group(..., exact: true)`, `CompiledRoute::$exact` (old route
    caches read as not exact), the `#[Route]`/`#[Get]`/... attributes'
    and `#[Group]`'s `exact` argument.
  - `Router::handle()` skips the redirect when the route the request
    reaches (by method, `HEAD` falling back to `GET`) is exact.
  - Exact by default: every admin route (both groups), the publish
    webhook, and preview links. Media and theme assets end in file
    extensions, which `canonicalPath()` already leaves alone.
- **Checked:** `composer check` (`RouterTest`: exact routes in both
  forms, `HEAD`, `POST`, generated URLs, an attribute route, other
  routes still redirecting; `RouteCompilerTest`: round trips and groups;
  `AdminApiTest`: signing in, a `PATCH`, and screens on a trailing-slash
  site, all 200); the jtcom trial in Chrome with `trailingSlash` on
  (every admin request a single 200; `/about` still 301s), the setting
  and a throwaway account removed after.

### D-311: Editing content types in the admin
- **Date:** 2026-09-30
- **Status:** Code collections and taxonomies are editable too since
  D-349, and every URL path since D-350.
- **Decision:** Implements D-042's "editable (later) in the admin" and
  the design's type builder, for types in `user/data/types` only. The
  author chose edit, create, and delete in one pass; the prototype's
  switches mapped to what exists; and every field type but `object`.
  - **`DataTypeWriter`** (`Content\Type`): `create()`, `update()`,
    `delete()`, and `path()`. Changes come by canonical option name
    (`labels`, `description`, `icon`, `prefix`, `public`, `sitemap`,
    `feed`, `dateArchives`, `hierarchical`, `types`, `fields`; `null`
    removes), are applied to the file's own data, built with
    `ContentType::fromArray()` (so checked as the loader checks), and
    each changed option is written as `ContentType::toArray()` writes it,
    leaving out defaults (the singular and plural a name gives, `public`,
    a prefix the folder gives, `kind: collection`) and replacing its 1.x
    name (`routing`, `date_archives`, `term_collect`). Untouched keys stay
    as written: JSON keeps its other keys; YAML is edited with `YamlMap`
    (its `with()` regains an inline depth, with Symfony's compact nested
    mappings, so `fields` is a block list of `- name:` items). New types
    are YAML. Field classes are never written (the registry knows them by
    type). Every change is checked against all the types: the file is
    written, `ContentTypeLoader` loads everything again, and when that
    throws (two types in one folder, a taxonomy naming a missing type),
    the file is put back. Writes take `storage/cache/types.lock`. Delete
    first refuses while a taxonomy groups the type, naming it.
  - **The API** (`TypeEditController`, `site.settings`): `POST types`,
    `PATCH` and `DELETE types/{name}`, and `POST types/refresh`; `GET
    types` adds `create` and `urls`, and `GET types/{name}` adds
    `dateArchives`, `folderPrefix`, `file`, and `index` (found on disk,
    so a type just created has it). `TypesController::detail()` describes
    a type among any set of types, since the request that made a change
    still holds the old ones.
  - **Applying a change:** the save writes the compiled types again when
    the site keeps them compiled; the index notices its types changed
    (its fingerprint) and rebuilds on the next request; and the admin
    then calls `types/refresh`, which runs with the new types, to compile
    the routes (when compiled) and reindex. Each change moves the content
    version on.
  - **Index pages** (D-255): `index: true` writes `{folder}/index.md`,
    titled with the plural name, when the folder has no `index` file.
    It's never removed by the admin.
  - **The screens:** the type's screen edits a data type (`TypeEditor`:
    General, Behavior, Fields, Save and Revert, a Danger Zone), built from
    `TypeBasicsFields`, `TypeBehaviorFields`, and `FieldListEditor` with
    `FieldDefinitionEditor`; **New Content Type** (`/types/new`) is the
    three-step wizard with What Gets Created; the types list has the
    button when data types are read. Departures are in
    `admin-design/departures.md`.
- **Checked:** `composer check` (`AdminTypeEditTest`: creating with
  fields and an index page, defaults left out, editing only what changed
  with comments and other keys kept, a 1.x name replaced, a folder's
  prefix left out, a JSON type, compiled types rewritten, refusals that
  write nothing, deleting and the taxonomy guard, `dataTypes` off, the
  capability); `npm run admin:build`; the jtcom trial in Chrome with a
  throwaway administrator: Recipes created with the wizard (a number
  field, the featured image, the index page; `/recipes` served),
  edited (a field's maximum, the feed), Cuisines created as a
  hierarchical taxonomy grouping Recipes and nested under it in the
  navigation, Recipes' delete refused while Cuisines grouped it, then
  both deleted; the files, folders, account, and sessions removed after.
  Two bugs found that way and fixed: `structuredClone()` refusing Vue's
  proxies, and a number input's model being a number.


### D-312: Editing accounts and roles in the admin
- **Date:** 2026-09-30
- **Decision:** The Accounts and Roles screens (D-249) edit what they
  show. Refines D-217's roles: custom roles and changes to the built-ins
  may also be made in the admin, kept outside git; `config/auth.php`
  still wins. The author's calls: roles in `storage/roles.json`; one-time
  password links instead of email; the built-ins' capabilities editable
  (not the administrator's); and suspension as well as removal.
  - **Roles (`Blush\Auth`):** `Role` gains an optional `description`
    (the built-ins have one) and `withCapabilities()`. `RoleStore`
    (`FileRoleStore`, `storage/roles.json`: `{"roles": [...]}`, `0660`)
    holds roles made in the admin and the capabilities of a changed
    built-in (its name and description stay built in; an administrator
    entry is ignored). `Roles` merges the built-ins, the store, then
    config, records each role's `RoleOrigin` (`built-in`, `changed`,
    `custom`, `config`; config and the administrator aren't editable),
    and is no longer readonly: `reload()` reads them again. A damaged
    file throws (fail closed). `RoleEditor` creates, updates, and
    deletes (a custom role, refused while an account holds it) or resets
    (a changed built-in) roles; capabilities must be registered (`*`
    never), except ones a role already has. Each change reloads the
    shared `Roles`, so `Accounts` and `Permissions` see it in the same
    request.
  - **Accounts:** `Account` gains `suspended` and a `PasswordLink`
    (`passwordLink`: the token's SHA-256 and when it expires; both
    written only when set) and `status()` (`AccountStatus`: `active`,
    `invited` (never signed in, with a link), `suspended`).
    `Accounts::invite()` makes an account with a random password and a
    link; `issuePasswordLink()` replaces any link (the password keeps
    working until it's used); `usePasswordLink()` checks it (and that the
    account isn't suspended), sets the password, and ends the link;
    `setPassword()` ends it too; `setSuspended()`. `AuthConfig::
    $passwordLinkLifetime` is a week. A suspended account's sessions end
    (`Authenticator::account()`), and signing in with its right password
    throws `AccountSuspended` (a `403`).
  - **Rules (`PeopleRules`)**, for the admin only (the CLI's operator has
    the site anyway): you give a role, or make or change one, only when
    you have every registered capability it grants; you change an account
    only when it can't do anything you can't; never your own account
    (its password is on Your profile); and after any change an account
    that isn't suspended still has `accounts.manage` (a role change is
    put back otherwise). All of it is under `accounts.manage`; no separate
    capability for roles.
  - **The API:** `GET roles` adds `description`, `origin`, `grantable`,
    `editable`, and a changed built-in's `defaults`; `GET accounts` adds
    `name` (the author page's title), `status`, `link` (`expires`,
    `expired`), and `manages` (`PeopleJson`). `AccountEditController`:
    `POST accounts` (`201` with the `link`'s `url` and `expires`, shown
    once), `PATCH accounts/{username}` (`roles`, `author`, `suspended`),
    `POST accounts/{username}/link`, `DELETE accounts/{username}`.
    `RoleEditController`: `POST roles`, `PATCH` and `DELETE
    roles/{name}`. `SetPasswordController`: `POST set-password` with
    `{"account", "token", "password"}`, no account needed; a short
    password is a `422`, a dead link a `410` that counts as a failed
    sign-in in `LoginThrottle`; success signs in (`204`). The admin makes
    no account or role named `new` (its screens are `accounts/new` and
    `roles/new`).
  - **Links:** `{admin}/set-password#account={username}&token={token}`.
    The token is in the fragment, so it never reaches a server log or a
    `Referer`, and the screen takes it out of the address bar on load.
  - **The screens:** Accounts has **New Account** and a Status column;
    **New Account** (`/accounts/new`) is a username, roles
    (`RoleChecks`: each with its description; one you can't give is
    locked, and so is the last one held), and an author (`AuthorField`, a
    datalist of the site's authors), then opens the account with the
    link to copy. An account's screen saves roles as they're ticked (a
    toast names them), saves the author, makes password links, and has a
    Danger Zone (Suspend or Reinstate, Remove account); your own account,
    and one you can't manage, show a notice and no controls. Roles has
    **New Role** and each role's source; **New Role** (`/roles/new`, also
    **Duplicate**'s `?from=`) takes a name, a key following it, a
    description, and capabilities (`CapabilityChecks`, in groups; one you
    lack is locked). A role's screen edits it with Save and Revert (a
    built-in's capabilities only) and a Danger Zone (Delete this role, or
    Reset to built-in); others are read-only with the reason. **Set
    Password** (`/set-password`) is public.
  - **CLI:** `account:suspend` and `account:reinstate`; `account:list`
    has a Status column.
- **Departures** are in `admin-design/departures.md`: links to copy
  instead of email invites and resets, no email or entry counts.
- **Checked:** `composer check` (`AdminPeopleEditTest`: a link that sets
  the password and signs in, once; expired and wrong links throttled; a
  new link replacing the old; new accounts' checks; roles, author, and
  suspension changed; suspension ending sessions and refusing sign-in;
  removal; never your own account; never more than you have; custom
  roles made, changed, refused while held, and deleted; a built-in
  changed and reset; the administrator and config roles left alone;
  someone always able to manage accounts; `accounts.manage`), plus the
  commands; `npm run admin:build`; the jtcom trial in Chrome with a
  throwaway administrator: an account created, its link opened in a
  second browser (390px, dark) and used, refused a second time, the
  account suspended (its session ended, sign-in refused) and
  reinstated, a new link copied, a custom role made, saved, given,
  refused deletion while held, then deleted after the account was
  removed; Editor changed and reset; Duplicate; the administrator
  read-only; 390px with no sideways scroll. Fixed along the way: the
  shared `Roles` not seeing a role saved in the same container (now
  `reload()`), and deleting a role asking to leave without saving. The
  accounts, sessions, and `storage/roles.json` were removed after.
- **Open:** passkeys; a separate capability for roles if a site needs
  one; showing entry counts per account.
- **Why:** the author asked to work on editing accounts and roles.

### D-313: The editor's toolbar, bleed, element moves, and the code block, from the split direction
- **Date:** 2026-10-01
- **Decision:** The author split the admin's design direction into
  numbered documents (`admin-design/00-project-brief.md` to
  `90-conventions.md`, with `meridian-admin.html`; the single `admin.md`
  and `blush-admin.html` moved to `admin-design/old/`). "Meridian" is the
  design project's codename only; the product is Blush. The direction
  is design; code directions are this project's. The author asked for
  its editor changes first. Supersedes D-284's ⌘I writing `*` and ⌘K
  linking only a selection, and D-285's ⌥↑ and ⌥↓ moving lines.
  - **The toolbar** (`30-editor.md`, The toolbar): no back button and
    no type name in the header. The top bar's trail is the way out:
    `Site / Posts / Edit Post`, the type a link (`screenTrail` in
    `screen.ts`). The left half, in order: components and media (always
    there); a stacked ▴▾ to move the element (while the caret is in the
    text); bold, italic, the link form, the icon picker, and the inline
    menu (only where emphasis is emphasis, `inProse()`: not code, a
    directive's own line, a rule, a delimiter row, or an attribute
    line); and bleed (a top-level element). Contextual groups are
    hidden, not disabled, after the fixed ones. One thing is open at a
    time (`closeOverlays()`, and `MenuButton`'s new `open` event). The
    toolbar doesn't toast: inserting a component, Markdown element,
    icon, or file no longer says "Inserted …" (upload progress still
    does). The Index mark left the header with the type name; the
    title says **Edit Index Page**.
  - **Bold and italic** (`toggleEmphasis()`, `emphasisAt()`): read with
    the highlighter's patterns, so either mark counts (`*`/`_`,
    `**`/`__`) and the buttons are pressed (`aria-pressed`) when the
    caret's text is. Bold writes `**`, italic `_`, but `*` inside a word.
    Nothing selected means the word at the caret (`wordAt()`); no word,
    an empty pair. Strikethrough uses the same path; inline code keeps
    `toggleMark()`.
  - **Links, as the author asked (the way a familiar block editor does
    it, not ⌘⇧K as the direction has it):** in the text, ⌘K opens the
    link form (Text and Address) with or without a selection, filled
    from the selection, the word at the caret, or the link the caret is
    in (`linkAt()`, with **Remove**, keeping a link's title); focus goes
    to the first empty field. ⌘⇧K removes the link at the caret
    (`withoutLink()`). Outside the text, ⌘K stays the command palette.
  - **Moving elements** (Reordering): ⌥↑, ⌥↓, the toolbar's ▴▾, and the
    palette's Move element up and down move the top-level element the
    caret is in past its neighbor (`topLevel()`, `movedElement()`,
    `swapped()`): whole lines, elements sharing a line are one run, and
    the gaps stay where they were. One undoable edit. `movedLines()` is
    gone. Not done: the element panel's Position row and outline
    handles (the direction's decisions log moved reordering to the
    toolbar).
  - **Bleed** (Bleed): Base (no class), Wide, and Full, in a menu whose
    button's glyph is the width in force (`bleed-base`, `bleed-wide`,
    `bleed-full` icons), tinted while widened; for a top-level element,
    written where its attributes go (`withDirectiveParts()`,
    `withImage()`, `withBlockParts()`), keeping other classes and the
    id. **The theme names the classes:** `theme.json`'s `bleed`
    (`{"wide", "full"}`, class names; `ThemeManifest::bleed()`), the
    nearest theme in the chain naming each winning
    (`ThemeChain::bleedClasses()`), else `bleed-wide` and `bleed-full`.
    `GET components` answers `bleed`. The author's call, since jtcom's
    1.x content uses `{.stretch-wide}` (D-078): the jtcom trial's theme
    names `stretch-wide` and `stretch-full`. Widths are no longer image
    variants: the default theme's are `inline-left` and `inline-right`,
    and it styles `bleed-*` and 1.x's `stretch-*` alike.
  - **The code block is one box** (The fenced block is a box): the
    highlight wraps the fences and lines in one block element, drops the
    break after a closing fence, and ends with the space a final empty
    line needs only when the last line isn't a closed block. Horizontal
    negative margin and padding; no vertical box metrics; the hairline an
    inset shadow. Checked against a reference `<pre>`: no line out of
    place in eight cases (middle, end, end with a newline, first, twice,
    unclosed, unclosed with a newline, a wrapping line).
  - **The third backtick** (`closingFence()`): three backticks alone on
    a line, the caret at their end, and an odd number of fence lines
    write an empty line and the closing fence, as an edit of its own
    (undo takes back the closer first); the caret stays for the
    language. Enter at the end of an opening fence over the block's
    empty first line steps into it (`intoFence()`).
  - **The drawer opens on what the caret is in**: the element tab when
    something's selected, else the entry's, decided on each open.
  - **Leaving the text drops the selection:** the selection, the
    breadcrumb, the sentence group, move, and bleed follow whether the
    caret is in the text. A press on the editor's chrome (header,
    footer, inserter, drawer but its entry tab, menus, dialogs) isn't
    leaving: the press is recorded on `pointerdown` and the blur asks
    where it landed; a Tab away is judged by where focus went.
  - **The Markdown elements are tiles** in the component panel
    (`MARKDOWN_ELEMENTS`, `markdown/*` names): Heading, Quote, List,
    Definitions, and Code Block under Text, Table under Data, Divider
    under Layout, first in each group, named and drawn as `BLOCK_KINDS`
    does, found by aliases ("hr", "dl"), inserted on lines of their own
    with the placeholder selected.
  - **Menus show the choice in force by filling its row** (Status,
    Visibility, Bleed), with the option's own icon, not a tick.
  - Smaller: element panels are keyed by where the element starts, not
    its index; a floating menu closes only when its button moves (a
    panel closing as it opens no longer shuts it); narrow, the toolbar
    wraps to a second row, and the trail keeps its separators.
- **Kept as they were** (departures): Tab leaves the text outside a
  list (D-245, D-284), the chrome doesn't fade (D-279), the drawer is
  remembered (D-299), and `spellcheck` stays on.
- **Not yet, from the direction:** Backspace taking a marker off; the
  "machinery" guards (an insertion or keystroke moved out of a
  directive's head or attribute block); a component's `only` (the
  gallery as a container of images, pickers locked to a kind); renaming
  the table component Data Table and dropping the divider component;
  pasting HTML as Markdown (on hold, D-286); the node list and its
  round-trip check (waits for a visual editor); caching the highlight
  per line; a placeholder for an empty entry; the shell's trail from
  the rail section rather than the site name.
- **Checked:** `composer check` (`ThemesTest`: bleed classes through
  the chain and refused manifests; `AdminContentTest`: `GET components`'
  `bleed`; the image variant tests moved to `inline-left`);
  `npm run admin:build`; a scratch run of the model (emphasis on and
  off with either mark, `*` inside a word, the word at the caret,
  links found, written with escapes and `<…>`, and removed, fence
  completion and stepping in, prose detection, element moves keeping
  gaps and carrying the caret, the code box's HTML); on the jtcom trial
  in headless Chrome with a throwaway administrator (since removed),
  nothing saved: the header's groups appearing and going with the
  caret, Bold pressed after bold, ⌘K's form filled from the word and
  Escape back to the text, bleed Wide writing `{.stretch-wide}` and Base
  removing it, no bleed in a list item, ⌥↑ and its undo, typing three
  backticks then Enter, the drawer opening on the paragraph, `hr`
  finding Divider and Heading inserted selected, the code box's
  alignment probe, light and dark, and 390px with no sideways scroll.
- **Why:** the author uploaded the split design direction and asked for
  its editor changes, deciding the three conflicts with earlier
  decisions (element moves, theme-named bleed classes, and links).

### D-314: Moving the element the caret is in, Backspace, guarded syntax, media kinds, and `only`
- **Date:** 2026-10-01
- **Decision:** The rest of the split direction's editor changes
  (D-313), at the author's request. Supersedes D-313's moving the
  top-level element, which the author found wrong in use.
  - **Moving elements** (the author's call, departing from the
    direction's top level only): ▴▾, ⌥↑, and ⌥↓ move the element the
    breadcrumb names last among its siblings, inside whatever holds it
    (`siblingRuns()`, `runIndex()`, `movedElement()`): a list item
    within its list, a paragraph within its callout, a callout among the
    top-level elements. A term or definition moves its whole definition
    list. An arrow is disabled with nothing to swap with. A moved
    numbered item renumbers its list from the number it started at
    (`renumberedAt()`). The view follows the move once the highlight has
    caught up (`change()` waits a tick before scrolling).
  - **Backspace takes the marker off** (`unmarked()`): just after a list
    item's marker (with its box), a quote's last `>`, or a heading's
    hashes, the marker goes and the words stay; an indented item comes
    out a level first, as Shift+Tab does. Anywhere else it's
    Backspace.
  - **You can't write into the machinery** (`safeSpot()`,
    `typedSpot()`): a typed character right after a trailing attribute
    block at the end of its line goes before the block; one right after
    a directive's tag ending in `]` or `}` goes on a new line (inside
    braces or a name, typing still edits). An inline insertion (a
    component, an icon) or a paste into a directive's own line goes on a
    line after it, and one inside an image's attributes after them.
    Nothing is refused.
  - **Media kinds:** `MediaField` takes an optional `kind`
    (`MediaKind`: `image`, `video`, `audio`, `file`; an unknown one is an
    `InvalidSchema`), written by `toArray()` and offered in the type
    builder's field editor (**Takes**). `#[MediaProp(MediaKind::…)]`
    names a component prop's: the video's `src` and the audio's take
    their kind, a video's `poster` images; the track and a download take
    any file. The admin's picker is **locked** to a kind for those, for
    the featured image (`kind: image` from the type builder, or by name
    for older types), an image's Replace, and the Image tile: no kind
    filter, the file chooser's `accept` narrowed, and an upload of
    another kind refused with why, by its extension, before it's sent.
    Saving doesn't check kinds (a URL's can't be known).
  - **`only`:** a component's `HOLDS` (list of inserter keys, `image` or
    full names) is `GET components`' `only`; the gallery holds
    `['image']`. Inside one (the innermost the caret is in, itself
    included), the component panel offers only those, with a note
    ("Gallery holds only images."); the sentence group goes; the media
    picker is locked to images. Its panel counts the images and names
    any line that isn't one, line by line, since images one to a line
    are one Markdown paragraph. The outline lists such a paragraph as
    its images.
  - Smaller: the Quote block has its own glyph (`text-quote`), apart
    from the inline Cite's; the link and icon picker glyphs are the
    direction's, rescaled to the toolbar's ink height; an empty entry's
    placeholder names `/` and **+**.
- **Doesn't apply:** Blush has no table, divider, or code component, so
  the direction's Data Table rename, dropped divider, and Code Sample
  question have nothing to change.
- **Not done:** the highlight cached per line (a keystroke costs about
  15 ms at 35,000 characters, frame wait included, so it isn't needed
  yet); Tab moving quote lines or a multi-line selection (Tab still
  leaves the text outside lists, D-245); the node list and its
  round-trip check, which wait for a visual editor; pasting HTML as
  Markdown (D-286).
- **Checked:** `composer check` (`SchemaTest`: a media field's kind
  read, written, and refused; `MediaComponentsTest`: the video's and
  audio's prop kinds; `AdminContentTest`: the gallery's `only`);
  `npm run admin:build`; a scratch run of `unmarked()`, `safeSpot()`, and
  `typedSpot()`; on the jtcom trial in headless Chrome with a throwaway
  administrator (since removed), nothing saved: moves of list items,
  numbered items (from 1 and from 3), nested items, an item with
  children, a paragraph in a callout, an only child (both arrows off), a
  callout, a term (its definition list), and a top-level paragraph, with
  real clicks; a move in a long post keeping the moved paragraph in
  view; every chunk of a real post moved down and checked line for line;
  Backspace in a nested item, a quote, and a heading, and undo; typing
  after attributes, after a tag, and inside a name; a paste into a tag;
  a gallery's tiles, note, locked picker, and its panel's count and
  stray line; and D-313's probes again, all with no console errors.
- **Why:** the author asked to keep going with the editor changes, and
  reported the move buttons weren't working as expected.

### D-315: Tab in quotes and over several lines
- **Date:** 2026-10-01
- **Decision:** The rest of the direction's "Tab moves a block"
  (`30-editor.md`'s decisions log), at the author's request, by
  structure rather than the prototype's two spaces per line, which do
  nothing to a quote and turn prose into a code block after two
  presses.
  - **In a quote** (`quoted()`): Tab adds a `>` level to every quote line,
    and Shift+Tab takes one off; the last level off leaves paragraphs.
    With a lone caret it's the whole quote the caret is in; with a
    selection, its lines (one it ends at the very start of isn't
    counted). Lazy lines (no `>`) are left as they are.
  - **Over several lines of code** (`indentedCode()`): with a selection
    spanning lines that starts in a fenced block's code, Tab puts two
    spaces in front of each code line and Shift+Tab takes up to two off;
    blank lines and fences are left alone.
  - **In a list**, as D-284 has it (`nested()`), which a selection
    starting in an item already used.
  - **Elsewhere** (prose, several lines of it, or a lone caret in code),
    Tab still leaves the text (D-245), so it never traps the keyboard.
  - Each is one undoable edit, and the selection stays on the same words.
- **Checked:** `composer check`; `npm run admin:build`; a scratch run
  (a caret and a selection in a quote, nesting and un-nesting, the last
  level, a selection ending at a line's start, a non-quote line, code
  lines indented and outdented, a single code line and prose left
  alone); on the jtcom trial in headless Chrome with a throwaway
  administrator (since removed), nothing saved: each key, undo, a list
  item, and Tab over prose leaving the text, with no console errors.
- **Why:** the author asked for Tab's behavior in quotes and over
  multi-line selections.

### D-316: The highlight is cached per line, and the body is read once per keystroke
- **Date:** 2026-10-01
- **Decision:** The direction's per-line highlight cache (its decisions
  log, "The highlight layer is rendered once per line"), at the
  author's request, after D-314 and D-315 left it as not needed yet.
  - **`highlight()` keeps each line's HTML** between calls in a
    module-level map, keyed on everything the line's HTML depends on:
    its kind and text; what the block scan says it is (a table's header,
    a term, a definition); for a directive's own line, whether it's the
    selected one; and for prose, where in it the selected inline
    directive or image starts, if anywhere. The keys a call uses become
    the next cache, so lines that went away stop being kept. A fence's
    HTML is cached by its text; code lines are only escaped, so they
    aren't cached.
  - **The body is read once per keystroke:** `MarkdownEditor` takes the
    editor screen's outline and blocks (`parsed`, `blocks`) rather than
    reading the body again (a trashed entry's read-only view still reads
    its own), and `emphasisAt()` takes the outline rather than a source.
  - Measured on the jtcom trial in headless Chrome (a keystroke's script
    work through Vue's update, median of 25): 3.1, 7.5, and 13.5 ms at
    11,600, 40,500, and 75,100 characters before; 2.3, 4.8, and 8.1 ms
    after. In Node at 75,000 characters, reading the body is 1.5 ms, its
    blocks 0.2, the outline 1.0, and a warm highlight 0.35. What's left
    is Vue's update and the browser taking the copy's new HTML; patching
    the copy line by line in the DOM would be the next step, if long
    entries need it.
- **Checked:** `composer check`; `npm run admin:build`; on the jtcom
  trial in headless Chrome with a throwaway administrator (since
  removed), nothing saved: the selection's box following the caret
  across two identical lines with inline components, an image, a
  container's opener and closer, and the text inside and outside it;
  typed text appearing and the copy matching the text; the code box's
  alignment probe; and D-315's Tab probe, with no console errors.
- **Why:** the author asked for the per-line cache.

### D-317: The shell's rail toggle and section trail, and the Editorial admin theme
- **Date:** 2026-10-01
- **Decision:** From the split direction's decisions log (*The shell*,
  *Theming*) and its requests to foundations, at the author's request to
  keep going with the design's changes. Supersedes D-231's "only the
  neutral theme ships" departure, D-235's single preference, and D-313's
  trail starting at the site's name.
  - **A rail button toggles its panel:** pressing the section already
    shown closes the panel (remembered in this browser, as the collapse
    was), pressing it again or another section opens it; on a narrow
    screen it closes the drawer. Its `aria-expanded` says which. The top
    bar's collapse button is gone; the burger stays on narrow screens.
  - **The trail is the section, the screens above, and the screen:**
    `Content / Posts / Editing`, `Config / Content Types / Posts`,
    `Config / Accounts / jane`, `Home / Dashboard`. The section crumb is
    a button that opens that section's panel (never closes it); the
    middle crumbs are links back (`screenTrail`, else the route's
    `meta.parent` and its title); the last is the title, or
    `screenCrumb` when it says what you're doing (the editor's
    "Editing"; the document title stays "Edit Post"). The site's name is
    no longer in it. On a narrow screen the section crumb goes first.
  - **Editorial ships** as a per-account choice: `AdminTheme`
    (`neutral`, `editorial`) in `Preferences` (stored only when not
    neutral, like the color scheme), `PATCH preferences`' `adminTheme`,
    the shell page's `data-admin-theme` and config `adminTheme`,
    `admin-theme.ts` (cached in this browser for the sign-in screen),
    a Theme choice on Your profile beside the color scheme ("Theme and
    Color Scheme"), and a palette command. Its tokens are the
    direction's, in `tokens.css`'s three Editorial blocks (light, system
    dark, chosen dark), changing values only. Its fonts, Karla and
    Newsreader (variable, latin and latin-ext; OFL, from Fontsource
    5.3.0), are served with the admin like IBM Plex Sans and Fira Code;
    the editor's mono stays Fira Code, so its alignment holds.
- **Checked:** `composer check` (`AdminApiTest`: the theme saved, one
  preference leaving the other, a bad theme refused;
  `AdminAppTest`: the shell page's attribute and config);
  `npm run admin:build`; on the jtcom trial in headless Chrome with a
  throwaway administrator (since removed): the trail on the list, a
  type, an account, the dashboard, Settings, and the editor, with
  Posts going back to the list; the rail toggling, a second section
  opening, and the section crumb opening without closing; Editorial
  chosen on Your profile, saved, its fonts loaded, the list and editor
  in light and dark, and the editor's alignment probe under it; no
  console errors.
- **Why:** the author asked to keep moving with the admin design's
  changes; these were the shell and theming changes the split direction
  made outside the editor.

### D-318: A Stack component, and layout icons that match
- **Date:** 2026-10-01
- **Decision:** Asked for by the author.
  - **Icons:** the admin's inserter draws `group` with Lucide's `group`
    (dashed corners around two blocks) rather than `folder`, which read
    as a file folder, and `row` with `columns-3`, since a row's items
    sit side by side. `rows-3`, the stacked bars `row` had, goes to the
    new `stack`.
  - **`stack`** (`Component\Layout\Stack`, a core layout component like
    `row`; D-175, D-177): blocks one above another with an even gap
    between them, `:::stack{gap=2rem}` … `:::`. Inline styles
    (`display: flex; flex-direction: column`), so it works in any theme.
    Props: `align` (`StackAlign`: `stretch` by default, `start`,
    `center`, `end`), `gap` (a `CssLength`; otherwise the theme's
    `--layout-gap`, or `1rem`, as in `row`), and `tag` and `label`
    (D-298). The default theme takes its items' margins off, as for
    `grid` and `row`, so the gap is the only space between them. It
    differs from `group`, which only wraps blocks and leaves their
    spacing alone. No `justify`: a stack is as tall as its items.
- **Checked:** `composer check` (`LayoutComponentsTest`: the styles, a
  bad gap dropped, props from the class, rendering in a bare theme);
  `npm run admin:build`.
- **Why:** the author: "Group is a folder doesn't make sense. Row is
  really a stack (we should have a Stack component)."

### D-319: Inserted containers lengthen the containers around them
- **Date:** 2026-10-01
- **Status:** Superseded by D-320 (nested containers all use `:::`; the inserter writes `:::` again, and `withNested()` and `longestFence()` are gone).
- **Decision:** The editor's inserter always wrote a container with
  `:::`. Inside another container written with `:::`, the new one's
  closing line closed the outer one too, since a closing line closes the
  outermost container it's long enough for (D-026; the editor's
  `outline()` follows the server's `ContainerDirectiveParser`). So
  nested stacks, rows, and groups lost their structure, and the
  highlighting (correctly) showed it. Now:
  - `directiveText()` gives a container one more colon than the longest
    container in the text it wraps (`longestFence()`), else three, and
    returns its `fence`.
  - `withNested()` (`markdown.ts`) writes the insert and lengthens each
    container around it, innermost first, to one more colon than the
    one inside it, opening and closing lines both; ones already long
    enough stay. `MarkdownEditor`'s `write()` applies it as one edit, so
    undo takes it all back, with the caret moved by the colons added
    before it.
  - Highlighting is unchanged: Markdown typed by hand with equal fences
    still shows what the site will render.
- **Open:** moving an element (D-313) into or out of a container, and
  pasting, don't adjust fences yet.
- **Checked:** `npm run admin:build`; `withNested()` and
  `longestFence()` in Node: a stack, then a row in it, then a group in
  that (five, four, and three colons, each closed by its own line);
  wrapping a selected group (four colons outside); a container already
  long enough left alone.
- **Why:** the author: with nested containers, the editor "doesn't seem
  to properly pick up where one component ends."

### D-320: Nested containers all use `:::`
- **Date:** 2026-10-01
- **Decision:** Supersedes D-319 and D-112's nesting rule. A closing
  fence closes the **innermost** open container it's long enough for
  (and anything still open inside that), rather than the outermost, so
  containers nest with `:::` throughout, as Pandoc's fenced divs do:
  ```markdown
  :::stack
  :::row
  :::group
  :::
  :::
  :::
  ```
  - **Server:** `ContainerDirectiveParser::tryContinue()` doesn't finish
    on a closing fence when a container still open inside it is short
    enough to take it (`closesInside()`, walking from the innermost
    active block's parents up to its own).
  - **Editor:** `outline()`'s `closes()` picks the last open container
    long enough, matching the server, so highlighting follows the same
    rule. The inserter writes `:::` everywhere again; D-319's fence
    lengthening is removed.
  - **Longer fences still work** (D-078): the two rules differ only
    when more than one open container is short enough for a closing
    line. Markdown that nests by giving the outer fence more colons,
    and closes each container with its own line, parses as before. What
    changes: `:::` inside `:::outer` and `:::inner` now closes only the
    inner one (it closed both, which is what broke nesting), and a
    `::::` meant to close a `::::outer` and a `:::inner` at once now
    closes only the inner one, leaving the outer open.
  - User docs teach only `:::`.
- **Checked:** `composer check` (`DirectiveTest`: three containers
  nested with `:::`; a `:::` skipping an inner `::::` container too long
  for it; the existing longer-outer-fence test unchanged);
  `npm run admin:build`; `outline()` in Node on the author's
  stack/row/group snippet (each container ends at its own line).
- **Why:** the author: "consistent syntax is more important" than
  counting colons, which is more to learn when typing by hand.
- **Background** (researched 2026-10-01, at the author's request):
  - **The generic directives proposal** (mb21, CommonMark forum, 2014;
    never part of the spec) counts colons: "an arbitrary number of
    colons greater or equal three could be used as long as the closing
    line is longer than the opening line. That way, you can even nest
    blocks … by using successively fewer colons for each containing
    block," by analogy with fenced code. In the thread, Tab Atkins
    (post 115) offered the alternative, a container line always has
    *something* so "a bare `:::` line is always a closer," and the
    proposal's author answered (post 116): "both approaches are
    viable."
  - **Most tools count colons** (outer fence longer): remark-directive
    (micromark-extension-directive) and what's built on it (Docusaurus,
    Starlight), VitePress (markdown-it-container), MyST, and djot; each
    run on equal fences closes everything at the first `:::`. MDC (Nuxt
    Content) varies colon counts and indents (equal fences held at two
    levels, not three). Markdoc and Hugo close tags by name. **Pandoc's
    fenced divs** work as Blush now does: an opening fence must have
    attributes, a bare fence closes, and lengths are for readability.
  - **Why this one:** the outer-longer rule exists for code, which is
    literal, so an inner fence can't be seen as nesting; containers hold
    Markdown, read as it goes, and every Blush container's opening line
    has a name, so a bare `:::` can only close. Markdown's other nesting
    (quotes, lists) marks the inner thing and leaves the outer alone,
    and writers already know fences as "open with three, close with
    three."
  - **Trade-off:** Blush reads remark-style Markdown (longer outer
    fences, each closed by its own line) unchanged, but Blush Markdown
    with nested `:::` breaks in remark-based tools. jtcom's content has
    no such nesting.

### D-321: Pasted containers are balanced; moving already nests
- **Date:** 2026-10-01
- **Decision:** With D-320, nesting needs no fence counting, so what was
  left was text that isn't balanced.
  - **Pasting** (`pasted()` in `markdown.ts`, used by `MarkdownEditor`'s
    paste handler): a closing line that closes nothing in the pasted
    text (left over from copying part of a container) is dropped,
    leaving a blank line when there are blocks on both sides, so it
    can't close the container it lands in; a container left open is
    closed at the end, so it can't take in what follows (and push its
    parent's closer down until the parent runs to the end of the body);
    every fence is written `:::`, one closing line per container, so
    remark-style Markdown (longer outer fences) comes in as Blush
    writes it. Code is left alone. Text holding a container or leaf
    directive goes on lines of its own, where the inserter puts a
    component (`place(false)`), rather than mid-paragraph.
  - **Moving** (D-313, D-314) needs no change: it swaps whole sibling
    runs, so a container moves from its opening line to its closing
    one and never crosses another's fence, and with `:::` everywhere
    the result nests as it reads. Checked in Node with nested stacks,
    rows, and groups. Moving into or out of a container stays out, as
    the direction has it (30-editor.md, Reordering).
- **Open:** cutting or deleting a selection that holds only one of a
  container's fences still unbalances the body.
- **Checked:** `npm run admin:build`; `pasted()` in Node (plain text
  unchanged; balanced, remark-style, unclosed, and stray-closer pastes;
  a leaf; `:::` inside code), and each pasted into a stack, its
  structure read back with `outline()`. Not checked in a browser:
  creating a temporary administrator account for it wasn't allowed.
- **Why:** the author asked for pasting and moving to support nesting.

### D-322: Accounts have a name, used across the admin
- **Date:** 2026-10-01
- **Status:** D-369 took the name away; D-370 restores it, first in
  `displayName` (the name, else the profile's title, else the
  username).
- **Decision:** An account may have a **name**: what the admin calls the
  person. The author asked for "a display name or first/last name";
  it's one free-text field, not first and last, because names don't
  split that way everywhere (one name, family name first, several
  surnames) and the admin only ever shows the whole name.
  - **`Account::$name`** (`?string`, stored as `name` only when set):
    one line of up to 100 characters (`Account::NAME_LENGTH`), no
    control characters. `Account::tidyName()` turns runs of spaces and
    line breaks into one space and trims (an empty name is `null`);
    `isValidName()` checks the rest, and the constructor throws an
    `AuthException` for an invalid one.
  - **What the admin shows** is `Accounts::displayName()`: the name,
    else the linked author page's title (what D-312 showed), else the
    username. The author page's title stays the public name in bylines;
    the account's name is private to the admin.
  - **The API:** `GET session` and `PeopleJson` send `name` (its own,
    or `null`) and `displayName`; a role's `accounts` are now each
    holder's `{"username", "displayName"}`, not usernames. `POST
    accounts` and `PATCH accounts/{username}` take `name` (`null` or
    empty removes it; a bad one is a `422` with `field: name`). `PATCH
    profile` (`ProfileController`) sets your own, for any account,
    answering `{"name", "displayName"}`.
  - **The screens:** the account menu's avatar shows up to two initials
    of the display name (`initials()` in `people.ts`, shared with the
    reference picker), and the menu shows the name, then the username
    when they differ; Accounts, an account's screen (its title,
    heading, avatar, toasts, and confirmations), a role's Held By list,
    and Set Password's "signed in as" use the display name. New Account
    has an optional Name field; an account's screen edits it (Save
    appears once it changes); Your profile has a Name field at the top
    of Account.
  - **CLI:** `account:add --name=`, `account:name <username> [name]`
    (no name removes it), and a Name column in `account:list`.
- **Checked:** `composer check` (`AccountsTest::testNamesAccounts`:
  tidying, the 100-character limit in characters, control characters
  and line separators refused, the fallbacks; `AdminApiTest::
  testAccountsNameThemselves`: `PATCH profile`, the session, CSRF, bad
  input, and no name not stored; `AdminPeopleEditTest::
  testNamesAccounts`; `AccountCommandsTest::testNamesAnAccount`; the
  new shapes in `AdminPeopleTest`); `npm run admin:build`. Not checked
  in a browser.
- **Why:** the author asked for a name on accounts, used throughout the
  admin.

### D-323: The dashboard greets by name; roles show their labels
- **Date:** 2026-10-01
- **Decision:** Follows D-322.
  - **The dashboard's heading** is the prototype's greeting: "Good
    morning", "afternoon" (from noon), or "evening" (from 6 p.m. to 5
    a.m.), by the browser's clock, then the account's `displayName`
    (the whole name; the prototype's "Justin" is a first name, which a
    single name field can't pick out reliably).
  - **Roles are shown by their saved label**, never their key, outside
    the Roles screens (which show the key on purpose). `GET session`'s
    `roles` are now each `{"name", "label"}` (a role that no longer
    exists shows its key), since an account without `accounts.manage`
    can't read `GET roles`; the account menu and Your profile use the
    labels, where they showed lowercase keys.
- **Checked:** `composer check` (`AdminApiTest`: the session's role
  labels); `npm run admin:build`. Not checked in a browser.
- **Why:** the author asked for the name in the greeting, and saw
  lowercase role keys in several places.


### D-324: Editable settings live in `user/data/settings.json`, on one page
- **Date:** 2026-10-01
- **Status:** The single page with anchors, and the file's flat keys,
  are superseded by D-325 (four screens; sections in the file).
- **Decision:** The Settings screen (D-309) becomes editable, for the
  settings a site owner changes. This is the "later, separate decision"
  D-306 left open about writing config from the admin.
  - **Storage:** the admin writes owner settings to
    `user/data/settings.json`, a data file over the values from
    `config/`. It's data, not code, so D-039 holds: the admin still
    never writes `config/` or `.env`. The file travels with `user/`
    (its own repo, pulled by the publish webhook). Settings tied to the
    developer or the environment (site address, environment, detailed
    errors, caching, secrets, and so on) stay read-only and keep naming
    the file they're set in.
  - **One page, not tabs or sub-pages:** the groups stay panels on a
    single scrolling page, with one save bar for the whole screen.
    Tabs under the page header filter lists by state in the direction,
    so they'd mean something else here, and they'd hide unsaved
    changes. Nested sidebar pages would hold one to three fields each.
  - **Anchors:** each group has an address, `/settings#<group>`, which
    scrolls to its panel. A sticky list of the groups beside the panels
    on wide screens and the command palette use the same anchors.
  - **Editable** (`Settings\Setting`, the file's keys): `name`,
    `locale`, `timezone` (AppConfig), `home` (ContentConfig; a
    collection with addresses, or `null` for `user/content/index.md`),
    `trailingSlash` (RouteConfig), `feedFormats`, `feedContent`,
    `feedLimit` (1 to 100; FeedConfig), `sitemap` and `sitemapDisallow`
    (paths starting with `/`, at most 50; SitemapConfig). Everything
    else is read-only under **Set in code**: the site address,
    environment, detailed errors, and media address (per environment);
    `dataTypes` and built-in types turned off (structure); caching;
    publishing and previews (secrets); and indexing, which follows the
    environment.
  - **The saved value always wins** (the author's option 1): `config/`
    gives the values, `settings.json` lays the saved ones over them.
    `Settings::apply()` rebuilds each config object with
    `fromArray([...toArray(), ...saved])`, so it's checked as a config
    file is. A way for developers to lock a setting waits until someone
    needs one.
  - **The file** (`SettingsFile`): JSON only (the bootstrap reads it
    before the data parsers exist), pretty-printed in the enum's order,
    written atomically under `storage/cache/settings.lock`, and removed
    when nothing is saved. A missing file is no settings; a broken one
    stops the site with an `InvalidSetting` naming it, as a broken
    config file does.
  - **Boot and compiling:** `Bootstrap` lays the settings over the
    config (compiled or not) on every build, before `withConfig()`
    overrides; `compile()` writes the config without them, so a save
    needs no compiling.
  - **The API** (`SettingsEditController`, `site.settings`): `PATCH
    settings` with `{"set", "unset"}`, all checked before anything is
    written (a refusal is a `422` with the reason); answers `{"saved",
    "refresh"}`. A change to the home page, time zone, trailing slash,
    feed formats, or sitemap deletes the compiled content types and
    routes (they're built from source until compiled again) and asks
    for a refresh; `POST settings/refresh`, running with the new
    settings, compiles them again when the site is compiled and
    reindexes. Every save moves the content version on. `GET settings`
    groups gain `editable`; editable items gain `setting`, `control`,
    `input`, `options`, `saved`, and the config `file` they fall back to.
  - **The screen:** editable panels as a form (text, selects, checkboxes,
    a number, lines), each setting saying whether it's saved here or
    from its config file, with **Use `config/…`'s value** for a saved
    one (a change until saved); live help for the time zone's time and
    the trailing slash's example, and warnings when the trailing slash
    changes or the sitemap goes off; the prototype's floating save bar;
    leaving with changes asks first. A link to a group on the same
    screen doesn't move focus to the heading (`App.vue`). Departures
    are in `admin-design/departures.md`.
- **Checked:** `composer check` (`AdminSettingsTest`: groups and the
  editable shape, saving over `.env`, normalizing, unsetting and the
  file removed, every refusal writing nothing, the capability,
  compiling leaving the settings out, a save clearing the compiled
  routes and types and the refresh compiling them; `SettingsTest`:
  apply, order, the file, broken files); `npm run admin:build`; on the
  jtcom trial with a throwaway administrator through the API: a saved
  name in the page title, the trailing slash redirecting `/about`, a
  taxonomy refused as the home page, then both unset (account and file
  gone after). The screen itself wasn't checked in a browser.
- **Why:** the author picked `user/data/settings.json` over rewriting
  `config/*.php` (fragile, and it would break hand-written config), one
  page with anchors over tabs or nested pages, the editable list as
  proposed, and the saved value winning.

### D-325: Settings is four screens, the Config panel four groups, and the file has sections
- **Date:** 2026-10-01
- **Decision:** Supersedes D-324's single page and flat file. The author
  asked for separate screens as settings grow (the one page was already
  long), with the Config panel split into **Structure** (Content Types),
  **Settings**, **Customize** (Appearance, Extensions), and **People**.
  - **Four screens, by task, not by config file:** General
    (`/settings/general`: site name, language, time zone; shown: the
    site address, environment, detailed errors), Reading (`reading`:
    the home page; feeds), Addresses and Search (`search`: trailing
    slash, sitemap, skipped paths; shown: the media address, asking not
    to be indexed), and System (`system`, all set in code: where types
    come from, caching, publishing and previews). `/settings` goes to
    General. A setting set in code sits beside the editable ones it
    relates to and names its file; System says it's all code. Each
    screen has its own save bar, and moving to another screen with
    changes unsaved asks first (`onBeforeRouteUpdate`). The anchors,
    the list of groups, and the "Set in code" divider are gone; the
    command palette goes to each screen.
  - **The API:** `GET settings/{screen}` (a `404` for any other), each
    item with the `file` it's set in (`null` when it follows from
    others); groups lose `editable` and `file`.
  - **The file has sections** named for the config files by convention
    (`app`, `content`, `routes`, `feed`, `sitemap`), each holding the
    keys that config object takes, so the names match `config/`:
    `{"app": {"name": "…"}, "feed": {"limit": 20}}`. A `Setting`'s
    value is `{section}.{key}` (`feed.limit`), which is also what
    `PATCH settings` takes in `set` and `unset`. One file, not one per
    config: a save that touches several configs stays one atomic write,
    and a request reads one file. Screens and sections needn't match
    (Reading spans `content` and `feed`).
  - **"Customize"** over "Extend": plain, familiar for themes and
    add-ons, and it fits Appearance.
- **Checked:** `composer check` (`AdminSettingsTest`: the four screens
  and an unknown one, the item shape, dotted keys, the nested file;
  `SettingsTest`: sections, `find()`, files, broken files including a
  section that isn't an object and a key outside the list); `npm run
  admin:build`; on the jtcom trial in headless Chrome with a throwaway
  administrator: each screen rendered, a save writing the nested file,
  the unsaved-changes prompt when moving to another screen, and **Use
  `config/feed.php`'s value** on both settings removing the file (the
  account removed after).
- **Why:** the author's call, for room to grow and names that match
  `config/`.

### D-326: People is its own rail section
- **Date:** 2026-10-01
- **Status:** The panel's order is amended by D-327 (Your Profile first).
- **Decision:** The section rail has four sections: Home, Content,
  **People** (`users`), and Config. People's panel holds Accounts and
  Roles (with `accounts.manage`), the author type's entries, and Your
  Profile, in one group without a heading; Config keeps Structure,
  Settings, and Customize (D-325). Account, role, and profile screens
  are `meta.area: 'people'`, and an author's entries open People's
  panel, as they opened Config's. Your Profile gets its own icon
  (`circle-user-round`, Lucide 1.48.0), since `users` is now the
  section's and Accounts'. This departs from the foundations' "three
  sections, not more" (recorded in `admin-design/departures.md`).
- **Checked:** `npm run admin:build`; the jtcom trial in headless Chrome
  with a throwaway administrator: Accounts under People, the rail's four
  sections, and Config's three groups (the account removed after).
- **Why:** the author asked for People in its own sidebar, above
  Config: it's looked for by name, every account uses it (Your
  Profile), and Config was long once Settings had four screens.

### D-327: Your Profile first in People, and Appearance is Themes
- **Date:** 2026-10-01
- **Status:** Customize is Themes, Plugins, and Icon Packs since D-379, and is headed Extensions since D-380.
- **Decision:** People's panel puts Your Profile first, then Accounts,
  Roles, and Authors (amends D-326). The Appearance screen (D-306) is
  named **Themes**: the navigation, its heading, the trail, and the
  command palette ("Go to Themes", still found by "appearance"); its
  address is `/themes`, with `/appearance` redirecting; the view is
  `ThemesView`. The API keeps `GET appearance`. Customize is Themes and
  Extensions.
- **Checked:** `npm run admin:build`.
- **Why:** the author asked for both; the screen only lists themes.

### D-328: The work area contains what's absolutely positioned
- **Date:** 2026-10-01
- **Decision:** `.main`, the work area's scroller, is `position:
  relative`. Without a positioned ancestor, an absolutely positioned
  element (`.visually-hidden`, such as the pager's "Rows per page"
  label) was placed against the body at its spot far down a long
  list, so the whole document scrolled past the rail and the work
  area onto the bare background. The screen's end padding stays the
  direction's 96px (80px on a phone).
- **Checked:** `npm run admin:build`; the jtcom trial in headless Chrome
  with a throwaway administrator (removed after): the document doesn't
  scroll on any screen in the navigation, the lists, or the editor, at
  1360px and 390px wide.
- **Why:** the author could scroll below the sidebar and content on
  content lists.

### D-329: Authors are people, with archives under each type (planned)
- **Date:** 2026-10-01
- **Status:** Superseded by D-351 (profiles, people fields, and a canonical profile URL), except where D-351 keeps it: accounts and profiles as two records linked one way, guest profiles, slugs apart from usernames, and per-type archives.
- **Decision:** Refines D-043, D-216, D-217, D-242, D-259, and D-322.
  Replaces D-043's "author archives work like taxonomy term archives"
  and D-259's planned step.
  - **Two records, one person.** An account (`storage/accounts/`:
    password hash, roles, sessions, preferences) stays private and out
    of git (D-217). Its author entry (`user/content/authors/`: the public
    name, a Markdown bio, fields) stays content and stays in git. The
    link goes one way, from the account to the author, so content never
    points at accounts and a clone or export without `storage/` still
    has every byline. Guest authors (entries with no account) are
    allowed.
  - **The author slug isn't the username.** Usernames stay out of
    public URLs because they're half of a login. A new author's slug is
    suggested from the public name, never from the username.
  - **`author` becomes an ordinary type, not a `Taxonomy`** (D-242's
    second stage, for authors). It has **no routes of its own**: no
    `/authors/{slug}` across the whole site, and no listing, feed, or
    sitemap of its own. Its pages exist only under the types that
    support authors.
  - **A type supports authors** with an `authors` reference field to
    the `author` type. It's on by default for collections and off for
    pages and taxonomies. A type can turn it on or off.
  - **Each type that supports authors gets two routes** under its
    prefix, with the base word set per type (`urls.authors`, default
    `authors`; `author`, `users`, `profile`, or anything else, or
    `null` for no author routes):
    - `{prefix}/{base}` (`/blog/authors`) lists the authors with at
      least one published entry of that type, alphabetically by title
      (D-304).
    - `{prefix}/{base}/{slug}` (`/blog/authors/jane`) is that author's
      archive for that type: the author entry's title, body, and fields,
      then that type's entries crediting them, paged, with a feed.
    These routes are built on the reverse index for reference fields
    (D-242), so other reference fields can get them later.
  - **Per-type settings:** "supports authors" (the field) and "author
    archives" (`urls.authors`) are separate, so a type can credit
    authors without archives (bylines are plain text then). The index
    and the single archives switch on and off together for now; they can
    be split later if a site wants one without the other. Data types
    write `authors: true` and `urls: { authors: cooks }` (`false` for
    no routes); PHP types use `TypeUrls(authors: …)`. The admin's type
    editor (D-311) gets an **Authors** switch in Behavior and, when
    it's on, **Author archives** with a URL word field and a preview
    (`/recipes/cooks`, `/recipes/cooks/jane`), saved by
    `DataTypeWriter`; types from PHP show them read-only.
  - **Each type has its own author index page:**
    `{type folder}/_authors.md` (`_posts/_authors.md`,
    `_portfolio/_authors.md`). It's named after the field, not the URL
    word, so changing the word doesn't rename the file (and later
    reference archives follow the pattern, such as `_movies/_actors.md`).
    It's an entry of that type, flagged like a landing page: its title
    and body show above the list of authors, and it's left out of the
    type's listings, feeds, and counts (as D-255's index page is).
    Without the file, the page uses the type's labels and no intro. The
    admin pins it in the type's list below the index page, marked
    **Authors**, and turning on author archives offers to create it.
  - **Templates vary per type:** `authors-{type}` → `authors` →
    `collection` for the index, and `author-{type}-{slug}` →
    `author-{type}` → `author` → `collection` for one author's archive.
    The author's bio is one entry, shared by every type; a per-type bio
    can come later if a site needs it.
  - **Byline links and structured data** point to the author's archive
    for the entry's own type. That's also the schema.org `Person` URL.
  - **A credited slug with no author entry** still renders, with the
    slug as its name, and `content:lint` warns about it.
  - **The admin shows one person.** People becomes one list of people
    (with or without an account). Your Profile (and an account's screen,
    for administrators) is the editor for the author entry: the bio is
    the writing surface, and the account's private settings (password,
    preferences, roles) sit beside it. When an account has an author,
    there's one name: the author entry's title. The account's private
    name (D-322) is used only for accounts without an author. The type
    keeps the name `author`; the admin calls the public side the
    person's profile.
  - **jtcom:** posts use the `archives` prefix, so the author archive
    moves from `/authors/justintadlock` to
    `/archives/authors/justintadlock` (see `open-questions.md`). Older
    posts' `author` front matter may need updating.
- **Build order:** `author` as an ordinary type with the `authors`
  field and per-type opt-in; the per-type author routes, listing, feed,
  and theme helpers (byline links, schema.org); `content:lint`; then
  the admin (one People list, Your Profile as the editor, one name).
- **Why:** the author wants any type to have authors, with author
  archives such as `/blog/authors/jane` and `/recipes/authors/jane`
  (and their indexes, `/blog/authors`) under configurable words, tied
  to real accounts that have a Markdown editor like other content. The
  author agreed to keep the split (accounts hold secrets; author entries
  are public content), to allow guest authors, and to keep slugs apart
  from usernames, and asked that author pages exist only under types
  that support authors, with an author index page per type (a portfolio
  shows something different from a blog). Naming in the admin was left
  to Claude.

### D-330: The `Authors` kind (D-329's first step)
- **Date:** 2026-10-01
- **Status:** Superseded by D-351: the kind becomes `profiles`, with a URL of its own.
- **Decision:** Implements D-329's first step. "Ordinary type" there
  meant "not a taxonomy"; it's built as a fourth kind, since the
  framework has to know which type holds people.
  - **`Authors`** (`Content\Type`, `TypeKind::Authors`, `kind:
    authors`): `field` and `aliases` (the built-in is `authors`, reading
    `author`), `public`, fields, labels, description, and icon; no
    `urls`, `listing`, `feed`, or `sitemap`. A site has at most one
    (`ContentTypeLoader` refuses a second); `ContentTypes::authors()`
    finds it. The admin can't create one (`DataTypeWriter::create()`
    refuses it, as it does pages).
  - **Not pages either:** `ContentType::servedAsPages()` says whether
    the page catch-all serves a type without routes at its folder path
    (1.x's `urls: false`); `Authors` says no, so `/authors/jane` is a
    404 and `ContentUrls::entry()` gives an author no URL (no sitemap,
    no export).
  - **The `authors` option** on collections (default `true`),
    taxonomies, and pages (default `false`); `toArray()` writes it only
    when it differs. `ContentTypes::schema()` adds the authors field
    only to those types, so elsewhere an `authors` key is undeclared
    front matter.
  - **Terms beyond taxonomies:** `ContentType::hasTerms()` (a term
    field) and `ContentTypes::termTypes()` (taxonomies and the authors
    type) replace `instanceof Taxonomy` where the index's terms are
    meant: `RecordBuilder` (forward and reverse), `term()` and virtual
    entries, the admin's use counts and reference picker. Routes,
    sitemaps, feeds, and menus keep `Taxonomy`.
  - **`AuthConfig::$authorTaxonomy` is gone** (a site with it set gets
    the unknown-key error); `Permissions`, `Accounts`, and the admin
    controllers use the authors type. A site that redefines `author` as
    a 1.x taxonomy has no authors type, so accounts own nothing by
    author and feeds name no authors.
  - **Lint:** a credited author with no entry is a warning (D-329), not
    a virtual-term notice.
  - **Feeds** name authors from the authors type and take categories
    from taxonomies only.
  - **The admin:** types describe `authors` (whether they credit
    authors), and the authors type lists the `types` that do. The
    navigation leaves the authors kind out of Content; an entry list's
    author filter shows only on types that credit authors; the authors
    list counts uses like a taxonomy's; the type screen shows "Credits
    authors" and the authors type's "Credited by". The kind's icon is
    Lucide's `user-round`, added to the admin's set.
  - **Between steps:** until the per-type archives (D-329's second
    step), authors have no public pages at all.
- **Checked:** `composer check` (the fixture site has author entries
  now; `ContentTypeTest::testCollectionsCreditAuthorsByDefault`,
  the authors type's round trip and checks in `ContentTypeLoaderTest`,
  `LinterTest::testWarnsAboutCreditedAuthorsWithoutEntries`, and
  `/authors/…` as 404s in `ContentRoutingTest` and `SitemapTest`);
  `npm run admin:build`; on the jtcom trial, `content:lint` clean,
  `/authors/justintadlock` a 404, and the JSON feed naming "Justin
  Tadlock". Not checked in a browser.
- **Why:** the author asked for D-329's first step.

### D-331: Author archives under each type (D-329's second step)
- **Date:** 2026-10-01
- **Status:** Superseded by D-351: archives are per people field, not per type, and can be overridden per profile.
- **Decision:** Implements D-329's per-type author routes.
  - **`TypeUrls::$authors`** (`urls.authors`, default `authors`,
    `false` for none) sets the route keys `authors.collection`
    (`{word}`), `authors.single` (`{word}/{author}`),
    `authors.single.paged`, and the three `authors.single.feed` keys;
    `paths` can still move any of them, and `toArray()` writes the word
    only when it isn't the default. The parameter is `{author}`, not
    `{name}`, so a hierarchical taxonomy's term-path constraint never
    applies to it. An empty word is an error.
  - **Who has archives:** `ContentType::hasAuthorArchives()` (public,
    credits authors, routed, with a word) and, for the site,
    `ContentUrls::hasAuthorArchives()` (also an authors type). Pages
    can credit authors but have no routes, so no archives. The home type
    keeps its archives under its own prefix (jtcom:
    `/archives/authors/justintadlock`).
  - **`{type}.authors.collection`** (`AuthorsController`, `PageKind::
    Authors`): every published, routable author, real or virtual, that a
    listed entry of the type credits (`Content\AuthorArchives`, shared
    with the sitemap and export), by name, on one page (no paged route;
    an authors list rarely needs one). The title is the type's
    `_authors` page's (`{folder}/_authors.md`, `AuthorsController::
    PAGE`), else the authors type's plural label. The leading `_`
    already hides the page (1.x's private files), so it's in no listing,
    feed, or sitemap and has no URL; the controller reads it when it's
    published.
  - **`{type}.authors.single`** (`AuthorController`, `PageKind::
    Author`): the author, with the type's entries crediting them, listed
    by the type's `listing` (its own `collection` front matter isn't
    applied, since one bio serves every type), paged and canonical like
    a term. An author no listed entry of the type credits is a 404
    there.
  - **Feeds:** `{type}.authors.single.feed` (and `.atom`, `.json`) when
    the type has a feed (`FeedBuilder::author()`, titled "{author} |
    {type plural}"), advertised on the archive (`FeedLinks`), and
    exported.
  - **Templates:** `authors-{type}` → `authors` → `collection`, and
    `author-{type}-{slug}` → `author-{type}` → `author` →
    `collection`. The default theme adds `authors.php` (each author
    linking to their archive, with the start of their bio) and a byline
    ("By" and the authors, linked when the type has archives) in
    `parts/entry-meta.php`.
  - **Template API:** `authors($entry)`, `authorUrl($author,
    $entryOrType)`, and `authorsUrl($type)`; `terms()` now answers only
    for taxonomies (so bylines don't list authors as terms).
    `ViewServices` gains `ContentTypes`.
  - **Head:** an author archive is `og:type` `profile`; a single entry
    gets one `article:author` per credited author, their archive in the
    entry's type (`Head::addProperty()`, for properties that repeat).
    There's no JSON-LD in Blush yet, so schema.org `Person` waits for
    structured data in general.
  - **Sitemap and export:** a type's sitemap adds its authors list and
    each archive after its entries; static export adds them (paged)
    before the entries, and each author's feeds.
- **Checked:** `composer check` (`AuthorArchivesTest`: the list by name
  with and without an `_authors` page, the archive with paging,
  redirects, 404s, the profile type and feed links, the feed, bylines
  and `article:author`, another word and none, templates, export; plus
  `TypeUrls` and `Head` unit tests and the post sitemap); on the jtcom
  trial, `/archives/authors`, `/archives/authors/justintadlock` (paged,
  with a feed), and `/writing/authors/justintadlock` answer, `routes:list`
  shows the routes, and `content:lint` is clean. jtcom's theme doesn't
  style the default theme's authors list or show bylines; that waits
  for the port.
- **Why:** the author asked for D-329's second step, and is fine with
  jtcom's author archive moving to `/archives/authors/justintadlock`.

### D-332: The admin's side of authors (D-329's last step)
- **Date:** 2026-10-01
- **Status:** Superseded by D-351: Accounts and Profiles are two lists again, and the type editor gets a People panel.
- **Decision:** Implements D-329's admin step.
  - **The type editor** (D-311): `DataTypeWriter` takes `authors` and
    `authorsWord` (written into `urls.authors`; `null` or `authors` is
    the default and is left out, `false` turns archives off).
    `TypesController::detail()` adds `authorsWord` (`null` without
    URLs) and `authorsPage`. `POST`/`PATCH types` take `authorsPage:
    true`, which writes `{folder}/_authors.md` titled with the authors
    type's plural name (a `422` without author archives). The Behavior
    panel (`TypeBehaviorFields`) gets an Authors group, shown when the
    site has an authors type: "Entries credit authors", "Each one has
    an archive here", the word with a hint showing both addresses, and
    the authors page (a link once it exists, else a checkbox). The
    new-type wizard sets credits on for content and off for taxonomies,
    and its summary says where archives will be. Types from code show
    "Author archives" read-only. A YAML data type emptied to `{}` can
    take keys again (`DataTypeWriter::yaml()` starts it fresh).
  - **The authors page in its list** (`AuthorsPage::is()`): set apart
    from `entries` and the totals like the index page and answered as
    `authorsPage` on the first page; pinned under the index page
    (`EntryTable`'s `pinned` is now a list) with an **Authors** tag; not
    duplicable. In the editor it's described like an index page (title
    and status only), with `can.rename` false (its slug is what it is)
    and `authorsPage: true`; the editor names it "Authors page" and
    says what it introduces. It can be trashed.
  - **People** (`GET people`, `PeopleView`, D-249's Accounts screen
    folded in): one list by name of author entries the viewer may edit
    (`Permissions::restrict()`), authors credited without a page (with
    `content.edit.others`), and accounts (with `accounts.manage`) not
    already listed with their author, including a second account linked
    to one author. Each row has the name, slug, entry, account, and how
    many published entries credit them. It needs `accounts.manage` or
    `content.edit` (`meta.anyCapability` in the router); `/accounts`
    redirects to `/people`, and account screens name People as their
    list. The panel is Your Profile, People, and Roles; the author type's
    list and editor still mark People.
  - **Your Profile** (`ProfileView`): with an author page, it renders
    `EditorView` with `profile` (the page's handle) and an `account`
    slot, a third drawer tab, **Account** (`AccountSettings`, the old
    profile panels as a component). In profile mode the address stays
    `/profile`, the top bar and heading say Your Profile, and the slug
    row is hidden. Without a page it's `AccountSettings` as panels plus
    the author page panel, whose **Create your author page** now titles
    the draft with the account's name (else the slug) and opens it here.
    `screenBleed` lets a screen choose edge-to-edge itself; the layout
    keeps one wrapper around the slot (`display: contents` when
    bleeding), since swapping wrappers remounted the screen in a loop.
  - **One name** (D-322 revised): `Accounts::displayName()` is the
    author page's title, else the account's name, else the username.
    `PeopleJson::account()` adds `authorPage` (`{"id", "handle"}`). Your
    Profile and an account's screen show the Name field only without an
    author page; with one, the account's screen says so and links to
    the page. The account's stored name stays as it was.
  - Departures are in `admin-design/departures.md`.
- **Checked:** `composer check` (`AdminTypeEditTest::
  testSetsWhetherATypeCreditsAuthorsAndWhere`, `AdminContentTest::
  testPinsATypesAuthorsPage`, `AdminPeopleTest::
  testListsAccountsAndAuthorsAsOne` and `testAuthorsSeeOnlyThemselves`,
  and the one-name expectations in `AccountsTest` and `AdminApiTest`);
  `npm run admin:build`; on the jtcom trial in headless Chrome with a
  throwaway administrator linked to `justintadlock` (removed after, with
  its sessions): People listing both accounts linked to Justin Tadlock,
  Your Profile as the editor with Author, Elements, and Account tabs,
  Posts' read-only "Author archives /archives/authors", and a throwaway
  data type (created through the API with `authorsWord: cooks` and an
  authors page, then deleted with its folder) showing the Authors group
  and its pinned authors page. No console errors.
- **Why:** the author asked for D-329's admin step.

### D-333: The authors work is kept, but on hold
- **Date:** 2026-10-01
- **Status:** Superseded by D-351: the author picked the subject up again.
- **Decision:** D-329 to D-332 stay on the `2.x` branch as built, and
  the author commits them, but the design isn't settled: the author
  wants to think it over, and other work comes first. Treat those
  decisions as provisional, build nothing further on them until the
  author picks the subject up again, and expect them to be revised or
  superseded then. The open question is in `open-questions.md`.
- **Why:** the author called the work a good exploration and wants
  time to think before going further.

### D-334: The editor's body starts at its first line
- **Date:** 2026-10-01
- **Decision:** The admin's entry and trash APIs send a body without
  the blank lines between the front matter and its first line, so the
  editor doesn't show them as empty lines. `DocumentEditor` keeps the
  file's blank lines on save: a Markdown or HTML body that doesn't
  start with a blank line gets the ones the file had (one, for a file
  without front matter), and a body that does start with one is
  written as given. `EditableEntry` stays the raw body.
- **Why:** files conventionally have a blank line after the closing
  `---` (jtcom's all do), and the editor showed it as an empty first
  line. Leading blank lines mean nothing in Markdown, and keeping them
  on save leaves files as they were.

### D-335: Enter on the title starts a paragraph at the top of the body
- **Date:** 2026-10-01
- **Decision:** In the editor, Enter on the title adds an empty
  paragraph (a blank line) above the body's content and puts the caret
  in it, as an undoable edit. With no content, or an empty line at the
  top already, it only puts the caret at the start of the body.
- **Why:** the author asked for it; it works like Enter at the end of
  a paragraph, so writing can start above what's there.

### D-336: New entries open in the editor
- **Date:** 2026-10-01
- **Supersedes:** D-233's New entry screen (a type and a title first).
- **Decision:** As in the design direction's prototype, **New post**
  (and every other way to start an entry) opens the editor itself, with
  no step in front of it. `/entries/new?type=…` is the editor on a new
  entry; with no type or an unknown one, it's the first collection's,
  as before.
  - **The first save creates it.** `GET entries/new?type=…`
    (`EntryController::blank()`) describes a new entry the way
    `GET entries/{id}` describes one, without writing anything: no
    `id`, `handle`, or `revision`, a draft crediting the account's
    author, with the type's fields (the taxonomies that group it,
    D-283) and what the account may do (no trash or duplicate). The
    editor's first save sends `POST entries` (D-229) with the title,
    the slug if one's typed (else from the title; the slug field shows
    that as its placeholder), the fields set, the body, and the status:
    Save draft, Publish, and Schedule all work. The address then moves
    to the entry's handle, in place: the screen's title and trail
    update, and focus stays where it was (`sameScreen()` in
    `router.ts`). The first draft save says **Saved as a draft**.
  - **A title is needed to save**, since the file is named for it:
    saving without one says so where the save state is and puts the
    caret in the title. The title has the caret when the editor opens.
  - **Leaving before the first save writes nothing**, and without
    asking when nothing's typed. Unsaved writing in a new entry is kept
    in this browser by type (`new:{type}`, D-240) and offered back the
    next time one of that type is started.
  - `POST entries` now puts a blank line before a body that doesn't
    start with one, so the editor's body (which starts at its first
    line, D-334) is written the way files are.
  - `NewEntryView` and the one-time "Created" notice are gone.
- **Departure:** the prototype writes an "Untitled …" draft the moment
  New is pressed; here nothing is written until the first save, so
  abandoned starts don't leave untitled files in `user/content`, and the
  file is named for the title it's given rather than `untitled-…`.
- **Checked:** `composer check` (`AdminEditingTest::
  testDescribesANewEntryWithoutWritingIt` and
  `testANewEntrysBodyFollowsABlankLine`); `npm run admin:build`; on the
  jtcom trial in headless Chrome with a throwaway administrator (removed
  after, with the entry it made): New on Posts opens the editor at
  `/entries/new?type=post` titled New Post with the caret in the title;
  leaving untouched asks nothing and writes nothing; `/entries/new`
  picks the first collection; ⌘S without a title doesn't save; with
  one, the draft is written (credited, a blank line before the body),
  the address moves to its handle with the caret still in the body, and
  a second save updates it. No console errors.
- **Why:** the author asked for it, as in the design mockups.

### D-337: The Fields API: field sets, controls, and Structure → Fields (planned)
- **Date:** 2026-10-01
- **Decision:** Fields become an API of their own, so extensions and
  sites can add fields to screens easily, with a Structure screen for
  creating and managing them. The value layer stays as it is (`Field`,
  `FieldType`, the registry and factory, `Schema`; D-019, D-084). Three
  things are added above it. **Content types are the only consumer
  until the API is right**; media metadata (D-287), theme settings
  (D-307), site settings (D-324), accounts, and component props come
  later. The author's calls: no custom admin controls for now; content
  types first; the name `FieldSet`. The author left the reuse model to
  this design (below).
  - **Field types are reusable everywhere; contexts decide what fits.**
    A field type knows nothing about where it's used. A **target** (a
    place fields attach to, such as `type:recipe`) says which fields it
    accepts (`FieldTarget::accepts(Field)`, all of them by default). A
    set with a field its target won't take is a problem. Content type
    targets take every field type, so nothing is refused today; the
    check exists for later targets (a settings screen with no index for
    a `reference` to read, for example).
  - **`FieldSet`:** a named, labeled, ordered list of fields with
    `targets`, attached from the set's side (as ACF's field groups are,
    not as Drupal's per-type fields are), so an extension can add
    fields to a type it doesn't own without editing it:

    ```yaml
    # user/data/fields/seo.yaml
    label: SEO
    description: How the entry appears in search results.
    targets: [type:post, type:page]
    fields:
      - name: meta_title
        type: text
      - name: noindex
        type: bool
        label: Hide from search engines
    ```

    Sets come from extensions (`FieldSetSource`, tagged, like
    `ContentTypeSource`), `config/fields.php`, and
    `user/data/fields/*.{json,yaml,yml}` (editable in the admin), a
    later set replacing an earlier one with its name. Config and
    extension sets are read-only in the admin, as their types are. A
    target that doesn't exist (a disabled type, an extension that's off)
    is a notice, not an error. A type's own inline `fields` stay, as the
    type's own set (D-311 and every existing site are unchanged).
  - **A type's schema** is the entry fields (`EntryFields`), its own
    fields, then its sets' fields in set name order. A name used twice
    across them is an error when types are loaded
    (`ContentTypeLoader`, "every type's fields must fit together"),
    never a silent override. Sets are compiled with the types, and a
    change to one moves the index's fingerprint, so the index rebuilds
    as it does for a type change.
  - **Controls, a fixed vocabulary:** a `Control` enum (`text`,
    `textarea`, `mono`, `checkbox`, `number`, `select`, `radios`,
    `checks`, `date`, `lines`, `tokens`, `reference`, `media`,
    `readonly`). Each field type lists the controls it can be edited
    with, the first being its default; a definition may name another
    with `control`, checked against that list. An extension's field
    type picks from the vocabulary; one that names none is shown
    read-only. `fields.ts`'s type-to-control mapping goes; the admin
    renders the control the server names.
  - **Field types describe themselves.** Each field class gives its
    label ("Formatted text"), description, controls, and definition
    schema (`definitionSchema()`, D-206), so `FieldType::description()`
    moves to the classes. `GET fields/types` is the catalog the admin's
    field definition editor is built from, which ends its hard-coded
    list of ten types and offers extension types.
  - **Namespace:** fields aren't only content's, so `Content\Schema`
    moves to `Blush\Field` (`Field`, `Fields\*`, `Schema`, the registry,
    factory, and enum), with `FieldSet`, `FieldTarget`, and `Control`
    beside them. Content types' target lives in `Content\Type`.
  - **Groundwork fixed on the way:** `ContentConfig::fromArray()` (and
    `MediaConfig`'s) build a registry of only the built-in field types,
    so extension field types fail in `config/`; they'll use the
    container's registry. Definitions are accepted as a list of
    `{name, …}` or a map of name to definition everywhere (types and
    media fields use lists; theme settings and menu fields, maps);
    files are written as lists.
  - **The admin:**
    - **Structure → Fields** (`/fields`), beside Content Types: every
      set with its label, targets, field count, and origin; a set's
      screen (`/fields/{name}`) edits a data set with
      `FieldListEditor` and `FieldDefinitionEditor`, plus a target
      picker; New Field Set and delete, as types have (D-311). The API
      is `GET`/`POST fields/sets`, `PATCH`/`DELETE fields/sets/{name}`,
      and `GET fields/types`, under `site.settings`, written by a
      `DataFieldSetWriter` that checks every change by loading all the
      types again, putting the file back when that fails.
    - **A type's screen** lists the sets attached to it, linking to
      each; its own Fields section stays.
    - **The editor** shows each set as a document panel group (D-281)
      under its label, after the type's own fields.
  - **Phases:**
    1. The namespace move; field types describing themselves;
       `Control` and the `control` key; both definition shapes; the
       registry fix; `GET fields/types`; the admin's definition editor
       and `FieldControl` built from the catalog and the named control.
    2. `FieldSet`, its sources, `FieldTarget` and the content type
       target, sets merged into type schemas (load checks, the
       compiled cache, the index fingerprint, `content:lint`), the
       editor's set groups, the editor JSON Schema for set files, and
       `docs/`.
    3. Structure → Fields: the API, the writer, and the screens.
    4. Later consumers, one at a time: media metadata (its
       `MediaFieldSet`s becoming sets targeting `media:{kind}`), theme
       settings, site settings, accounts.
- **Why:** the value layer is already shared by four systems, but
  about a dozen define, label, and render fields their own way, fields
  can't be reused across types, and the admin hard-codes which types
  exist and how each is edited. Attaching from the set's side is what
  makes "an extension adds fields to these screens" one file. Letting
  targets, not field types, decide what fits keeps a new field type
  from having to know every place it might be used. The open parts
  (placement, broader targets, conditions) are in `open-questions.md`.

### D-338: The Fields API, phase 1: controls, the catalog, and the groundwork
- **Date:** 2026-10-01
- **Decision:** Builds D-337's first phase.
  - **`Blush\Field`:** `Content\Schema` moved there whole (`Field`,
    `Fields\*`, `Schema`, the enum, registry, factory, and registrar,
    `Definition`, `Violation`, and the rest), tests to `tests/Field`.
  - **Field types describe themselves:** static `typeLabel()`,
    `typeDescription()`, and `controls()` on `Field` (an empty label, no
    description, and only `readonly` by default, so an extension's type
    needs nothing new). `FieldType::description()` is gone; the JSON
    Schemas read the classes.
  - **`Control`** (`Blush\Field\Control`, each with a `label()`):
    `text`, `textarea`, `mono`, `checkbox`, `number`, `select`, `radios`,
    `checks`, `date`, `lines`, `reference`, `media`, `readonly`. D-337's
    `tokens` is left out: nothing draws it yet, and lists are `lines`.
    The built-ins: text `text`/`textarea`/`mono`; markdown `textarea`;
    date `date`; bool `checkbox`; number `number`; enum
    `select`/`radios`; list `lines`/`checks`/`readonly`; reference
    `reference`/`mono`; media `media`/`mono`; slug `mono`; object
    `readonly`.
  - **A field's control:** `Field::$control` (`null` for the type's
    default), set with `control()` or a definition's `control`, and
    refused (`InvalidSchema`, naming what it can use) when it isn't one
    the field can use. `canUse()` narrows a type's controls by its
    settings: a list is `lines` when its items are edited on one line
    (and aren't lists), `checks` when they're `enum`; a reference is
    `reference` only with `to`. `editedWith()` is the control used: the
    field's own, or the first its type lists that it can use.
    `toArray()` writes `control` only when it's set.
  - **Forms and definitions:** `Field::toForm()` is a field as the
    admin's forms take it: its definition without `class`, and `control`
    always the one it's edited with. Entries (`EntryController`), media
    (`MediaListController`), and component props use it; a type's
    fields (`TypesController`) stay definitions, so the type editor
    never writes a default control into the file.
  - **The catalog:** `GET fields/types` (`FieldTypesController`, any
    signed-in account): each registered type's `type`, `label` (its key
    when the class has none), `description`, `controls` (`value` and
    `label`), and `options` (`definitionSchema()` without `default`),
    plus every control.
  - **Both definition shapes:** `FieldFactory::definitions()` takes a
    list of named definitions or a map of names to them (the key is the
    name), and `schema()` uses it, so types' `fields`, objects'
    `fields`, and each media kind's fields take either; files are still
    written as lists. Theme settings and menu fields stay maps only (their
    manifests check that) until they're consumers.
  - **Config builds with the site's field types:** config files run at
    bootstrap, before extensions register field types, and the config
    cache rebuilds configs with `fromArray()` too. So
    `ContentConfig::fromArray()` keeps its types as `definitions`
    (arrays, each with a `name`; a name used twice across `types` and
    `definitions` is refused), built by `ContentTypeLoader` with the
    container's factory; an invalid one now fails when types load,
    prefixed `config/content.php:`, instead of when config loads.
    `MediaConfig::fromArray()` keeps `fields` as `definitions`, read by
    `MediaSchemas`. Objects passed to the constructors work as before.
  - **JSON Schemas:** the shared field definition has `control`, and
    each type's `if`/`then` limits it to the type's controls
    (`composer schemas`).
  - **The admin:** `fields.ts` has no type-to-control mapping; it draws
    the control a form's field names (`readonly` for none).
    `FieldControl` adds `radios` (with **None** for an optional field)
    and `checks` as a fieldset under the label; `mono` is the
    monospaced input; a `media` field without the picker is a typed
    path. The field definition editor's types, names, and item types
    come from the catalog (`field-types.ts`), with **Edited with** when
    the field can use more than one control (the type's default first,
    and a control its settings rule out dropped on **Done**), options
    for a list of choices, and an extension type's options drawn from
    its JSON Schemas (text, numbers, true or false, a set of values, or
    a list of text). The fields list names types from the catalog.
    `.field input` styles skip radio buttons as they skip checkboxes.
  - **`docs/`:** Custom fields has the map shape, the `control` key, and
    each type's controls; Extending has field types from an extension.
- **Checked:** `composer check` (`ControlTest`, `AdminFieldTypesTest`,
  and `ContentTypeLoaderTest`'s extension field type in
  `config/content.php` and late error); `npm run admin:build`; on the
  jtcom trial in headless Chrome with a throwaway administrator and a
  throwaway data type (both removed after, with the account's
  sessions): the catalog lists every built-in type with its controls,
  the type's fields list names them ("Choice", "List of choice",
  "Reference to posts"), a list of choices' field editor shows
  **Edited with: Checkboxes** and its options, and a new entry's panel
  draws radio buttons with None, checkboxes, and a slug field with its
  comma help. No console errors but a 404 that no page request made.
- **Why:** D-337's phase 1, as planned.

### D-339: The Fields API, phase 2: field sets on content types
- **Date:** 2026-10-01
- **Decision:** Builds D-337's second phase.
  - **`FieldSet`** (`Blush\Field`): a `name` (lowercase letters,
    digits, `_`, `-`), its fields (a `Schema`, so names and aliases are
    checked), `targets` (`kind:name`, such as `type:post`), a `label`
    (its name made readable by default), and a `description`.
    `fromArray()` takes `targets` as one or a list, `fields` as a list or
    a map, refuses unknown keys, and ignores a JSON file's `$schema`;
    `toArray()` leaves out a made label.
  - **`FieldTarget`** (`key()`, `label()`, `accepts()`), and
    **`ContentTypeTarget`** (`Content\Type`, kind `type`), which takes
    every field.
  - **Sources, in order, each replacing a set of the same name:**
    extensions (`FieldSetSource`, tagged `field.sets`; two extensions
    can't define one set), `config/fields.php` (`FieldConfig`: `sets`,
    and `dataSets`, on by default; array sets are kept as `definitions`
    and built at load, as D-338 does for types), and
    `user/data/fields/*.{json,yaml,yml}`, named after the file
    (`FieldSetLoader`). Unlike types, a data set may replace a config
    or extension set (D-337's order); the origin is kept
    (`FieldSetOrigin`) for the admin's locking in phase 3.
  - **`FieldSets`:** the resolved sets in name order, with origins;
    `for($target)` gives a target's sets.
  - **Type schemas:** `ContentTypes` holds the sets; `schema()` adds each
    attached set's fields after the type's own, in set name order,
    refusing a field the target won't take and any name or alias used
    before (`InvalidContentType`, naming the type and set), so the
    loader's check of every type catches it at load. `setsFor()` gives a
    type's sets. The sets are in `ContentTypes::toArray()`, so they're
    compiled with the types (`content-types.php`) and change the index
    fingerprint, which rebuilds the index when a set changes.
  - **Targets that attach to nothing** are left alone and noted by
    `content:lint` (`FieldSetCheck`): a `type:` naming no type, or a
    kind Blush doesn't have. Notes are keyed by the data set's file,
    `config/fields.php`, or the extension set's name.
  - **The editor:** an entry's `type.sets` lists the attached sets
    (`name`, `label`, `description`, field names); the document panel
    shows a group per set after the type's other fields, and set
    fields stay out of the panel's special groups (a set's reference
    field is a form field there, not a taxonomy picker).
  - **`field-set.schema.json`** for set files (`composer schemas`).
  - **`docs/`:** Field sets in Content types, Field sets from an
    extension in Extending, and `config/fields.php` in Configuration.
- **Found on the way:** spreading two name-keyed field arrays merges
  equal keys, so a set's field would silently replace the type's;
  the merge uses lists, so `Schema` sees the clash.
- **Checked:** `composer check` (`FieldSetTest`, `FieldSetsTest`, a
  `LinterTest` and an `AdminEditingTest` case); `npm run admin:build`;
  on the jtcom trial in headless Chrome with a throwaway administrator
  and a throwaway set on posts (both removed after, with the account's
  sessions): a new post's panel shows the set's group (label, help, a
  text field, a checkbox, radio buttons) after the post's fields; the
  site's home page served and `content:lint` found no errors.
- **Why:** D-337's phase 2, as planned.

### D-340: The Fields API, phase 3: Structure → Fields
- **Date:** 2026-10-01
- **Decision:** Builds D-337's third phase.
  - **`DataFieldSetWriter`** (`Content\Type`): `create()`, `update()`,
    `delete()`, and `path()` for `user/data/fields` sets. Changes come
    by key (`label`, `description`, `targets`, `fields`; `null`
    removes), are applied to the file's data, built with
    `FieldSet::fromArray()`, and each changed key is written as
    `FieldSet::toArray()` writes it (no label the name gives, no field
    classes); untouched keys and comments stay. New sets are YAML.
    Every change reloads all the types and puts the file back when that
    throws (a field a target already has). Writes take the types' lock.
    Refused while `FieldConfig::$dataSets` is off. A set's name is its
    file's and isn't renamed.
  - **`DataFileKeys`** (`Content\Writer`): the JSON and YAML key editing
    `DataTypeWriter` had, shared by both writers (`InvalidData` for a
    file that isn't a JSON object).
  - **The API**, under `site.settings` for writes: `GET fields/sets`
    (`FieldSetsController`: each set's `name`, `label`, `description`,
    `origin`, `editable`, `file`, `targets` as `{key, label, found}`, and
    a field count; `create`; and every target, each content type by
    plural label), `GET fields/sets/{name}` (with `fields` as
    definitions and `options`), and `POST`, `PATCH`, `DELETE`
    (`FieldSetEditController`, `422` with the reason). A change rewrites
    compiled types and bumps the content version; the admin then calls
    `POST types/refresh` to reindex. `GET types/{name}` adds `sets`.
  - **The screens:** **Fields** under Config → Structure (and the
    command palette), with the set list; a set's screen, edited
    (`FieldSetEditor`: General with label, fixed key, and help; Added
    To, checkboxes of content types; Fields, `FieldListEditor`; Save,
    Revert, and a Danger Zone) or shown read-only for config and
    extension sets; **New Field Set**, one screen with the same editor,
    the key following the label until typed; and a Field Sets panel on
    every type's screen (`TypeFieldSets`). Departures are in
    `admin-design/departures.md`.
  - **`docs/`:** Fields in `admin.md` (and Edited with, from D-338,
    under Editing a type), with the API rows; Content types points to
    the screen.
- **Checked:** `composer check` (`AdminFieldSetsTest`); `npm run
  admin:build`; on the jtcom trial in headless Chrome with a throwaway
  administrator (removed after, with its sessions): Fields in the nav,
  New Field Set with a label (key `claude-check-set`), Posts ticked,
  and a field, created as `user/data/fields/claude-check-set.yaml` and
  opened at its screen; its help saved as one new line; the Posts
  type's screen listing it; then deleted from its Danger Zone, the file
  gone. Files written in the container reach the host late (Mutagen),
  so they were read with `ddev exec`.
- **Why:** D-337's phase 3, as planned.

### D-341: The Fields API, phase 4 (media): field sets on media kinds
- **Date:** 2026-10-01
- **Supersedes:** D-287's extension (`MediaFieldSource`), data file
  (`user/data/media-fields`), and config (`MediaConfig::$fields`)
  layers, and its rule that a site's field replaces a built-in one.
- **Decision:** The first of phase 4's consumers, in D-337's order.
  - **Targets are generic:** a `FieldTarget` gives its own schema
    (`schema()`, its fields before any set's), and
    `FieldSets::schemaFor()` adds the attached sets' fields (the clash
    and `accepts()` checks, once, for every consumer; messages name the
    target key, `type:recipe can't take field set "kitchen": …`).
    `FieldSets::clashes()` tries every set, for lint. Each consumer tags
    a `FieldTargetSource` (`field.targets`: a `kind()`, a `label()` for
    the admin, and its targets), collected by `FieldTargets`. Content
    types' is `ContentTypeTargets` ("Content types"; `ContentTypes::
    ownSchema()` is a type's schema before sets).
  - **Media kinds are targets:** `MediaKindTarget`, `media:image`,
    `media:video`, `media:audio`, and `media:file` (`MediaKind::label()`:
    Images, Videos, Sound, Other files), from `MediaKindTargets` ("Media
    files"); each takes every field. `MediaSchemas::schema()` is the
    kind's built-in fields (`builtIn()`: an image's `alt`, then `title`,
    `caption`, `credit`, `description`) then its sets' (`setsFor()`).
    There's no `media:all`; a set for every file lists the four kinds.
  - **Gone:** `MediaFieldSet`, `MediaFieldSource`,
    `user/data/media-fields`, and `MediaConfig`'s `fields`. A site's
    or extension's media fields are field sets; a set can't reuse a
    built-in field's name (so `credit` can't be made required for now).
    jtcom used none of them.
  - **`FieldSets`** is a container singleton, the sets compiled with
    the content types (`ContentTypes::$sets`), so media reads them
    without loading `user/data/fields` per request.
  - **Checks:** `FieldSetCheck` takes every kind of target: notices for
    a target the site doesn't have or a kind it has none of, and errors
    for a set that doesn't fit a target (a media clash; a type's stops
    the site at load). `DataFieldSetWriter` builds every target's schema
    with the new sets, so the admin refuses a media clash and puts the
    file back.
  - **The admin:** `GET fields/sets` targets have a `group` ("Content
    types", "Media files"), and the set editor's Added To groups its
    checkboxes by it; a set's screen and the list say "the site doesn't
    have this" for a missing target. A media file's Details panel
    (`GET media/{path}` adds `sets`) shows each set's fields under its
    label after the built-in ones, as the entry editor's panel does.
  - **`docs/`:** Details about a file (sets aimed at media kinds), the
    media config's `fields` row removed, Media fields from an extension
    folded into Field sets from an extension, and Fields in the admin.
- **Checked:** `composer check` (`AdminMediaFieldsTest` rewritten for
  sets, `AdminFieldSetsTest`'s media clash and target groups, a
  `LinterTest` media clash); `npm run admin:build`; on the jtcom trial
  in headless Chrome with a throwaway administrator and a throwaway set
  on images (both removed after, with the account's sessions): an
  image's Details shows the set's group after the built-in fields, and
  the set's screen groups Added To as Content types and Media files.
- **Why:** D-337's phase 4 starts with media; one way to add fields
  replaces three, and a set can now add the same fields to posts and to
  images. Next in the order: theme settings (on hold, D-342), site
  settings, accounts.

### D-342: Theme settings stay off the Fields API for now
- **Date:** 2026-10-01
- **Decision:** Theme settings, next in D-337's phase 4 order after
  media (D-341), are held for a later date at the author's call. D-307
  stands: no theme settings in the admin, and the manifest `settings`
  mechanism is unchanged. Nothing is built toward them until the author
  picks them up again.
- **Why:** the author wants to hold off on theme settings.

### D-343: The Fields API, phase 4 (settings): the Settings screens on fields
- **Date:** 2026-10-01
- **Decision:** The second of phase 4's consumers (theme settings are
  on hold, D-342). The author's calls: built-in settings as fields **and**
  field sets on the screens; a separate `$template->site()` for themes
  and `SiteSettings` for extensions, keeping `$template->setting()`
  theme settings'.
  - **Built-ins as fields:** `Setting::field()` describes each setting
    as a field named by its key (`name`, `locale`, `timezone`, `home`,
    `trailingSlash`, `formats`, `content`, `limit`, `enabled`,
    `disallow`), with its label, help, and control (locale `mono`;
    formats `checks`; time zone and home page `select`), `choices()`
    naming options (zones without underscores, "The latest posts",
    feed formats' names), and `caption()` for a checkbox's text or an
    empty choice's ("The page at user/content/index.md"). Each is on a
    screen (`Setting::screen()`, `SettingsScreen`: general, reading,
    search; System is all code). `Setting::normalize()` still checks
    values. A site with no collection with addresses has no home page
    to choose, so it's shown read-only. `homeChoices()` replaces the
    controller's `homeOptions()`.
  - **Targets:** `settings:general`, `settings:reading`, and
    `settings:search` (`SettingsTarget`, from `SettingsTargets`,
    "Settings screens"), each taking every field, its own schema its
    built-in settings. `FieldTargetSource` gains `conflicts()`: the
    screens share one store, so two sets using one name (or alias) on
    any screens conflict; one set on several screens is one setting.
    `FieldTargets::problems()` gathers clashes and conflicts for
    `content:lint` and `DataFieldSetWriter`.
  - **Storage:** `user/data/settings.json`'s `site` section, by field
    name, values as sent (raw, like front matter). The bootstrap reads
    the file before sets load, so `Settings` keeps them unchecked
    (`withSite()`, `site()`); the admin checks each by its field on
    save (a `422` naming its label; required ones refused empty; an
    empty one removed), and `SiteSettings` reads them through the
    fields (`Schema::resolve()` then `hydrate()`), leaving out what no
    longer fits and filling in defaults.
  - **Reading:** `SiteSettings` (`Blush\Settings`, a singleton from the
    new `SettingsServiceProvider`): `get($name, $default)` and `all()`;
    `$template->site($name, $default)` through `ViewServices::$site`.
  - **The API:** `GET settings/{screen}` items that the admin changes
    have a `field` (`toForm()`, with `choices` and `caption`) in place
    of `control` and `options`, then a group per set on the screen
    (`set-{name}`; items `site-{name}`, `setting` `site.{name}`, the
    field, the saved value or the field's default as `input`, no
    `file`). `PATCH settings` takes `site.{name}` in `set` and `unset`.
  - **The admin:** `FieldInput`, the control alone (label, help, and
    error left to the caller), split from `FieldControl`, which now
    wraps it; a field's `caption` is a checkbox's text and an empty
    choice's label. The Settings screens draw each editable setting
    with `FieldInput` in their rows (`toForm`/`fromForm`; a built-in
    list left empty is `[]`), options in a row; the hard-coded checkbox
    texts and number limits are gone. A set's setting says **Saved
    here. Clear it** or **Not saved yet.**
  - **`docs/`:** Your own settings in Themes (with the helper),
    `settings:` targets in Content types and Admin, the `site` section
    in Configuration, and `SiteSettings` in Extending.
- **Checked:** `composer check` (`AdminSettingsTest`: fields on the
  built-ins, a set's group, saving, checks, clearing, `SiteSettings`;
  `ThemeSystemTest`: the helper with a default, a fallback, and a saved
  value; `LinterTest`: two sets on one name); `npm run admin:build`; on
  the jtcom trial in headless Chrome with a throwaway administrator and
  a throwaway set on General (both removed after, with the account's
  sessions): General, Reading, and Addresses and Search drawn with the
  shared controls (menus, feed formats in a row, the number limits,
  checkbox captions, lines), the set's panel on General, a tagline
  saved as `{"site": {"tagline": …}}`, then cleared, which removed the
  file.
- **Why:** the Fields API's goal is adding fields to screens easily;
  settings were the last hand-built form with their own controls.

### D-344: Account fields wait with the authors work
- **Date:** 2026-10-01
- **Decision:** Accounts, the last of D-337's phase 4 consumers, are
  held at the author's call, with the authors, People, and Your Profile
  work they'd be edited in (D-333). Nothing is built toward an `account`
  target until that's picked up again. Public profile fields already
  work: authors are a content type, so a field set aimed at
  `type:author` adds fields to every author page, edited in the entry
  editor. What's held is private, admin-only data per account (saved
  in the account files).
- **Why:** account fields would be edited on screens D-333 may change,
  and the author recommended against building on that design until
  it's settled.

### D-345: A field set can go in the editor's main column
- **Date:** 2026-10-01
- **Status:** The `placement` key (`panel`, `main`) is superseded by
  D-346's `role` (`details`, `content`); the editor still shows content
  below the body.
- **Decision:** Settles the placement part of D-337's open question, at
  the author's call: a set may be shown below the body.
  - **`placement`** on a set (`FieldSetPlacement`: `panel`, the default,
    or `main`), in `FieldSet` (`fromArray()` refuses anything else,
    `toArray()` leaves the default out), its editor JSON Schema, and
    `DataFieldSetWriter`'s keys. `GET fields/sets` and an entry's
    `type.sets` carry it.
  - **The entry editor:** a `main` set's fields are below the body in
    the writing column (`.editor__set`: past a rule, headed by the
    set's label like the panel's groups, its help under it), not in the
    document panel; a required one left empty is focused there without
    opening the panel (`main-field-{name}` ids). The body keeps its
    40vh writing area, so the fields start below it.
  - **Elsewhere:** media files' Details and the Settings screens have
    one column, so they show every set the same way.
  - **The set editor:** "In the entry editor" radio buttons (in the
    document panel, beside the text; below the body, in the main
    column), with a note that it's for content types.
  - **`docs/`:** Field sets in Content types (with a recipe example)
    and Admin. A departure from the direction (custom fields in the
    panel) is in `admin-design/departures.md`.
- **Checked:** `composer check` (`FieldSetTest`, `AdminFieldSetsTest`
  with a `main` set written and a bad placement refused,
  `AdminEditingTest`); `npm run admin:build`; on the jtcom trial in
  headless Chrome with a throwaway administrator and a throwaway `main`
  set on posts (both removed after, with the account's sessions): a new
  post shows the set below the body (a required number, radio buttons,
  and a list), not in the panel.
- **Why:** some fields are part of the writing, such as a recipe's
  ingredients, and read better beside the text than in a sidebar.

### D-346: A field set says what its fields are, not where they go
- **Date:** 2026-10-01
- **Status:** The fixed `role` is superseded by D-347's slots, which
  each kind of place declares; the rule (name a purpose, never a place)
  and the fallback stand.
- **Supersedes:** D-345's `placement` key and its values.
- **Decision:** The author's call, after noting that "In the entry
  editor: the document panel / below the body" ties set files to
  today's layout, which is likely to change (D-252). A set's `role`
  (`FieldSetRole`) says what its fields are to the places they're added
  to: `details` about them (the default) or part of their `content`,
  read with their text (a recipe's ingredients). Screens map roles to
  places; only the entry editor uses it today (details in the document
  panel, content below the body), and screens with one column show both
  alike. When the editor changes, only that mapping changes.
  - **Unknown roles fall back:** a role this version doesn't know is
    treated as details, kept as written (`FieldSet::$unknownRole`, so it
    survives the compiled cache and the admin's edits), and
    `content:lint` notes it. A set written for a later Blush still
    loads. (D-345's `placement` refused unknown values.)
  - **Renamed throughout:** the API's `placement` is `role`; the set
    editor asks "These fields are": "Details about it, such as notes or
    search engine settings" or "Part of its content, read with its text,
    such as a recipe's ingredients", with "Each screen shows them where
    they belong." The JSON Schema describes the roles, not places.
  - **The rule for definitions:** say what a field is or how it's
    chosen, never where it sits. (`control` stays: its vocabulary is
    closer to intent than to layout.)
  - The jtcom trial's Recipes example uses `role: content`.
- **Checked:** `composer check` (`FieldSetTest`: roles and the
  fallback kept as written; `AdminFieldSetsTest`; `LinterTest`: the
  notice); `npm run admin:build`; the jtcom trial's `content:lint`.
- **Why:** a baseline for set presentation that outlasts a redesign.
  The author has larger concerns about how the Fields API is
  architected for the future (see `open-questions.md`); this isn't
  meant to settle them.

### D-347: Slots, declared by each kind of place, and one kind a set
- **Date:** 2026-10-01
- **Status:** Content types' `content` slot is removed by D-348; every
  kind offers only `details` for now. The mechanism stands.
- **Supersedes:** D-346's `role` (a fixed `details`/`content` enum for
  every kind) and D-337's sets mixing kinds of target.
- **Decision:** The author's calls, after asking whether admin locations
  should register slots that fields attach to. Slots are declared by
  the kinds of place (PHP), not by admin screens, so set files name
  purposes and a replacement admin (D-222) reads the same names; and a
  set's targets are all one kind, since no use case mixes them (a field
  is per entry on a type, site-wide on a Settings screen).
  - **`FieldSlot`** (`name`, `label`, `description`):
    `FieldTargetSource::slots()` lists a kind's, the first its default.
    Content types: `details` ("Facts about each entry…") and `content`
    ("Part of each entry, read with its text…"); media files and
    Settings screens: `details`.
  - **A set's `slot`** (a name, or left out for its kind's default)
    replaces `role` and `FieldSetRole`. `FieldTargets::slotFor()` gives
    the slot a set is in: the one it names when its kind offers it,
    else the default, so a set written for a later Blush still loads;
    `content:lint` notes a slot the kind doesn't offer.
  - **One kind a set:** `FieldSet` refuses targets of more than one
    kind ("make a set for each"); `FieldSet::kind()` names it.
  - **The API:** a set's `kind` and `slot` (the slot it's in); targets
    carry their `kind`; `kinds` lists each kind with its `slots`. An
    entry's `type.sets` carry the `slot`. The writer's keys and the
    JSON Schema have `slot`.
  - **The admin:** the set editor's Added To asks the kind of place
    first (radio buttons, when the site has more than one kind with
    places), then its places, then "These fields are" with the kind's
    slots (label and description) when it offers more than one;
    choosing another kind keeps only that kind's places and its default
    slot. A read-only set's screen names its slot. The entry editor
    maps `content` below the body and every other slot to the panel.
  - The jtcom trial's Recipes example uses `slot: content`.
- **Checked:** `composer check` (`FieldSetTest`: slots and one kind;
  `AdminFieldSetsTest`: kinds and slots, a mixed set refused, `slot:
  content` written; `LinterTest`: an unknown slot, unknown kinds split
  into their own sets); `npm run admin:build`; on the jtcom trial in
  headless Chrome with a throwaway administrator (removed after, with
  its sessions): the Recipe set's screen shows Kind of place, its
  content types, and Content chosen; the Weeknight Chili editor shows
  the set below the body; `content:lint` clean.
- **Why:** one concept (slots) generalizes roles per kind, extensions
  that add kinds of place declare their own, and the Fields screen can
  offer them instead of a fixed pair. The larger architecture questions
  remain open (`open-questions.md`).

### D-348: Fields stay out of the writing area; the Fields API pauses
- **Date:** 2026-10-01
- **Supersedes:** D-345's fields below the body, and D-347's `content`
  slot for content types.
- **Decision:** At the author's call, the entry editor has no fields
  section in its writing area: every field set is a group in the
  document panel. Content types offer only the `details` slot, as media
  files and Settings screens do, so nothing offers a slot no screen
  draws; the slot mechanism (`FieldSlot`, `FieldTargetSource::slots()`,
  a set's `slot`, the fallback and its lint notice) stays for later.
  The set editor asks no slot question while each kind has one. The
  jtcom trial's Recipes example drops `slot: content` and shows both
  its sets in the panel.
  - **The Fields API pauses here.** The author thinks there's more to
    get right in its design, so D-337 to D-348 are a baseline, not
    settled; nothing more is built on fields until the author picks it
    up again (see `open-questions.md`).
- **Checked:** `composer check`; `npm run admin:build`; the jtcom
  trial's `content:lint`.
- **Why:** the author wants the writing area to stay the text's.

### D-349: The admin changes code types through a file over them
- **Date:** 2026-10-01
- **Supersedes:** D-042's "defining the same type name in both places
  is an error" and "locked" for code collections and taxonomies, and
  D-311's "config, extension, and built-in types stay read-only".
- **Decision:** The author asked that every content type's settings can
  be changed from the admin, except the pages and authors types.
  - **Where:** a file in `user/data/types` named for a collection or
    taxonomy defined in `config/content.php` or an extension changes it
    instead of being an error: each option the file sets replaces the
    code's whole option (by its 2.x or 1.x name;
    `ContentType::overriddenBy()`), and the rest stay the code's. The
    type keeps its origin; `ContentTypes::isOverridden()` says a file
    changes it (kept in the compiled types), and
    `ContentTypes::isEditable()` covers data types and these. The file
    can't change the name, kind, or folder (entries are filed by them).
    A file named for a code pages or authors type is still an error
    (`TypeKind::isOverridable()`); a data type still replaces a built-in
    type whole, as before. `dataTypes` off turns overrides off with data
    types, and `dataTypeUrls` off keeps their `urls` out too.
  - **The saved value wins**, as with `settings.json` (D-324).
  - **Writing** (`DataTypeWriter`): a change to a code type is applied
    to the type as the code and the file make it, and the file keeps
    only options that differ from the code's: an option set back to
    the code's value is removed, one set to a default the code doesn't
    have is written out (`feed: false`, `description: ""`), and the file
    is deleted once it's empty. **Fields** are included, as a whole
    list, unless a code field is a class of its own (not the registry's
    for its type), when the fields stay in code (`fieldsEditable()`).
    `reset()` deletes the file; deleting or creating a code type here is
    refused.
  - **The API:** `POST types/{name}/reset`; `GET types/{name}` adds
    `overridden`, `overrides` (the options the file sets),
    `fieldsEditable`, and `file` for an overridden type.
  - **The screen:** a code collection or taxonomy gets the type editor,
    saying where it's from and where changes are saved; its Danger Zone
    is **Reset to config/content.php** (or an extension) instead of
    Delete; fields from code classes are listed read-only. The header
    notes the file once there is one.
- **Checked:** `composer check` (`ContentTypeLoaderTest`: an override
  replacing whole options and keeping the rest, 1.x names, kind and
  folder refused, the code's pages type refused, the cache round trip;
  `AdminTypeEditTest`: only differences written, an explicit default,
  fields as a whole list, the file removed when back at the code's
  values, reset, delete and create refused, code field classes kept,
  the pages and authors types not editable); `npm run admin:build`; the
  jtcom trial in headless Chrome with a throwaway administrator (its
  account and sessions removed after): the Posts screen edited (the
  description and the single path) and saved to
  `user/data/types/post.yaml` with only those options, `/archives/2026/forty-two`
  served at the new address, then **Reset** removing the file; the Pages
  screen still read-only.
- **Why:** the author's call: the site owner can change any type from
  the admin, with code as the default under it. The author picked the
  file in `user/data/types` over a `types` section in `settings.json`
  or a separate overrides file, and fields as a whole list.

### D-350: Every URL path of a type is editable in the admin
- **Date:** 2026-10-01
- **Decision:** "Routes editable from the admin" means each route key's
  path of a type (the author's pick, over a Routes screen, redirects,
  or data-defined routes, which may come later).
  - **`TypeRouteKeys`** lists the keys a type answers at (its listing
    and `.paged` unless it's the home type, date archives at its
    granularity, `single` and a taxonomy's `single.paged`, the feed
    keys for the site's feed formats when it has a feed, and author
    archives when it has them) and what each path needs and may hold:
    `{name}` for `single` (a collection's may add the date's parts and
    any term type's name, `{author}` included), `{name}` for a term's
    keys, the date down to an archive's level, `{author}` for author
    archives, and `{page}` for `.paged`. `check()` refuses anything but
    letters, digits, `-`, `_`, `.`, `/`, and placeholders (so no inline
    constraints), a pattern that doesn't parse, a missing placeholder,
    and one the key can't fill.
  - **Writing:** `paths` in `set` maps keys to paths (`null` or `''`
    for the default); the `single` and `collection` shortcuts move into
    `urls.paths`, which they'd otherwise win over; paths are checked
    after the type is built. Works for data types and code types
    (through D-349's file) alike.
  - **The API:** `GET types/{name}` adds `routes`: each key's `path`
    and `default` (relative to the prefix), `requires`, `allows`, and
    `root` (the home type's feeds sit at the site root).
  - **The screen:** an **Addresses** panel on the type editor, after
    Behavior: a field per key, labeled for people (Listing, Year
    archive, Entry or Term, Feed (Atom), Author archive, "later pages"),
    the default as its placeholder, the whole address and what it needs
    and may hold under it, and a note that moving addresses wants
    redirects in `user/data/redirects`. Author keys follow the form's
    author word, and hide while author archives are off. The URL prefix
    stays in Behavior.
- **Checked:** `composer check` (`AdminTypeEditTest`: the keys and their
  placeholders, each refusal writing nothing, the shortcut moved into
  `paths`, defaults left out, the home type's root feeds); `npm run
  admin:build`; the jtcom trial (D-349's check), including a refused
  `{year}/{slug}` and `routes:list` showing the new `post.single`.
- **Why:** the author's call; it fills the prototype's absent
  "permalink structure" (D-309) per type, where Blush keeps URLs.

### D-351: Accounts, profiles, and bylines (planned)
- **Date:** 2026-10-01
- **Status:** Built: step 1 in D-352 (which also amends it: the line under a profile's name is `subtitle`, not `tagline`) and step 2 in D-353. Its "one name" is D-370's: the account's own name, else the profile's title, else the username.
- **Decision:** Supersedes D-329 to D-333, from the author's sketch
  `.claude/docs/admin-design/meridian-profiles.html` (a sketch, kept as
  uploaded like the direction). The sketch's model is adopted; its
  admin locations aren't (the author's call: People stays its own
  section, D-326, and Your Profile keeps its name and stays the editor
  of your public profile, D-332).
  - **Three nouns.** An **account** signs in (`storage/accounts/`,
    private, D-217). A **profile** is a public identity: an entry of
    the site's one `profiles`-kind type, with a slug, a status, pending
    changes (unlike a term, since it's public prose), and a Markdown
    body edited like a page. A **byline** is the relation between an
    entry and a profile: a people field on the entry, never a screen.
    An account with no public presence has no profile; a guest is a
    profile with no account.
  - **The link** goes one way, from the account to a profile slug
    (D-329 kept), so content never points at accounts. Unlinking keeps
    the profile and its bylines (it becomes a guest profile).
    Usernames never suggest slugs (D-329 kept).
  - **The `profiles` kind** replaces D-330's `authors` kind: at most
    one per site, the built-in named `profile`, folder
    `user/content/profiles/`. A profile's own front matter: `title`
    (the display name on every byline), `tagline` (the sketch's "byline
    title", such as "Food editor"; optional), and `avatar` (a
    `user/media` path; falls back to initials). These are the kind's
    built-in keys, not field sets (the Fields API is paused, D-348).
  - **One canonical URL per profile:** `{base}/{slug}`, the base set on
    the profiles type (`urls.base`, default `profiles`), showing the
    profile's body, then every listed entry of every type that credits
    them, paged, with feeds. Nothing answers at `/{base}` itself (a
    container, not an archive). This reverses D-330's "no routes".
    A site may point the type at another folder and base (jtcom's
    `authors`, which keeps 1.x's `/authors/justintadlock`).
  - **People fields, plural from the start.** A type declares a list
    of people fields, each a reference to the profiles type with its
    own key (front matter name), aliases, plural and singular labels,
    archive word (or `false` for none), arity (one or many), and
    whether it's required (gating publishing like any required field).
    Collections get one by default, `authors` (reading `author`, so
    jtcom's front matter keeps working, D-078), word `authors`, many,
    optional; pages and taxonomies get none. `authors: true|false`
    stays a shorthand for that default field. This replaces D-329's
    single `authors` option and `urls.authors`.
  - **Archives per type and field.** A people field with a word gets
    `{prefix}/{word}` (the profiles credited by that field on that
    type, by name, D-304) and `{prefix}/{word}/{slug}` (paged, with
    feeds when the type has them). The list keeps D-331's intro page,
    named after the field: `{folder}/_{field}.md` (`_authors.md` stays
    valid). Turning a field's archive off stops the routing and deletes
    nothing.
  - **How an archive's body resolves:** (1) its own index page,
    `{folder}/_{field}/{slug}.md`, when published; (2) the profile's
    body; (3) the name, avatar, and entries, with no prose. Step 1
    pages are ordinary entries of the type, pinned to that URL, created
    on demand only, never listed or counted, not duplicable, and not
    trashable (deleting one is "Use the profile's" on the profile's
    screen). Canonical and structured links stay with each archive;
    bylines link to the entry's type's archive for that field, else
    the profile's canonical URL.
  - **Lint:** a credited slug without a profile still warns (D-330).
  - **The admin.** People's panel: Your Profile, Accounts, Profiles,
    and Roles. Two lists, one link, not D-332's merged People list:
    - **Accounts** (with `accounts.manage`) lists only people who can
      sign in, with a Profile column (the profile's name, and its
      status pill when it isn't published). An account's screen has a
      **Public profile** panel in three states: linked (open, unlink),
      not linked (link an existing one, create one), and linked but not
      yet public (open, publish). Create prefills the name from the
      account and makes the link.
    - **Profiles** is the profiles type's entry list with type-driven
      columns: Name, Status, Account (a **Guest** tag and dashed avatar
      without one), Bylines (entries of every type naming the profile),
      and Updated; no pinned index row. A profile opens a screen with
      Identity (name, slug, tagline, avatar), **Where this profile
      appears** (a row per people field with archives: the archive's
      address and whether its body is written or inherited, with Edit
      or Write one), and the linked account (read-only, Open account,
      Unlink); Edit profile opens the editor.
    - **Your Profile** stays the editor for your own profile with the
      Account drawer tab (D-332); without a profile, it's the account
      settings as panels with Create your profile. ⌘K finds people on
      either side.
    - **The type editor** gets a **People** panel (replacing Behavior's
      Authors group): each field's label, singular, word with its
      address, arity, and required, an archive switch per field, and
      Add a people field. The profiles type's own screen sets its base.
  - **One name** stays D-332's: the profile's title, else the account's
    name, else the username.
- **Build order:** (1) content and routing: the `profiles` kind, its
  canonical route and keys, people fields and their archives, intro
  and per-profile index pages, resolution, templates, feeds, sitemap,
  export, lint, `docs/`, and the jtcom trial; (2) the admin: the People
  panel's two lists, the profile and account screens, the type editor's
  People panel, and creating index pages on demand.
- **Left open** (`open-questions.md`): capabilities for editing your
  own profile versus anyone's (D-215's set has neither, today it's
  ownership through the link and `content.edit.others`); template
  names for per-field archives; whether the Profiles list should keep
  a Bylines count per field.
- **Why:** the author's sketch: one collection of people instead of a
  taxonomy per type joined by a slug string, the per-type noun owned by
  the type, a profile that's real content with its own address, and
  archives that fall back to it. The author chose the profile's own
  URL, kept the per-field list and intro page, kept the admin's
  locations (People, and Your Profile as the editor), and named the
  kind `profiles`.

### D-352: Profiles and people fields in content and routing (D-351's step 1)
- **Date:** 2026-10-01
- **Status:** The profiles prefix no longer follows the folder (D-357), so the jtcom trial's profiles are at `/profiles/{name}`.
- **Decision:** Builds D-351's first step, and settles what it left to
  the build.
  - **`Profiles`** (`TypeKind::Profiles`, `kind: profiles`) replaces
    `Authors`: options `folder`, `urls`, `listing`, `feed`, `public`,
    `sitemap`, `fields`, `closed`, `labels`, `description`, and `icon`
    (no `field` or `aliases`; people fields belong to the crediting
    types). The built-in is `profile`, folder `profiles`
    (`BuiltInType::Profile`); `hasTerms()` is true with no
    `termField()`. Its schema adds `avatar` (`MediaField`). D-351's
    `tagline` is the entry's existing `subtitle`, which already means "a
    line shown under the title".
  - **`PeopleField`** (`Content\Type`): `field`, `aliases`, `plural`,
    `singular` (from the plural: "Cooks" → "Cook"), `archive` (a word,
    default the field's name, or `false`), `multiple`, and `required`;
    `collection` and `single` are refused as field names. Its
    `referenceField()` is the schema's field, `termKey()` its index key
    (`profile.cooks`), `paths()` its route keys' defaults under the
    word (`{field}.collection`, `{field}.single` with `{profile}`,
    `.paged`, and the feed keys), and `listPage()`/`personPage()` the
    `_cooks` and `_cooks/jane` keys.
  - **`people` on types** (`ContentType::$people`, keyed by field):
    `true` (the `authors` field, reading `author`), `false`, or a map of
    fields to settings (`true` for defaults). Collections default to
    `true`, the others to `false`. `authors: true|false` is short for
    it (both at once is an error). `toArray()` writes it only off the
    kind's default. `TypeUrls` loses `authors` (`urls.authors` is now an
    unknown option); people paths come from the fields, and `urls.paths`
    can move them. `ContentType::routePattern()` falls back to them.
  - **A taxonomy wins a clash:** a people field reading a key a
    taxonomy's field reads (a 1.x `author` taxonomy with `authors` and
    `author`) is dropped at load (`withoutPeopleReading()`), since
    otherwise the default `authors` field would break those sites.
  - **The index** keeps each people field's credits under its
    `termKey()` and all of them under the profiles type's name, so a
    field's archive and a profile's page are each one term lookup.
  - **1.x's `author`** (the query argument, `whereAuthor()`, and
    `orderby: author`) reads the profiles type, whatever it's named,
    unless a type is named `author` (`IndexedRepository::resolved()`,
    `Query::withTaxonomyRenamed()`).
  - **Routes:** the profiles type has only `{type}.single` and
    `.single.paged` (`ProfileController`) and, with a feed, the
    `single.feed` keys; nothing at its prefix. Each people field with
    archives has `{type}.{field}.collection` (`PeopleController`) and
    `.single`, `.single.paged` (`PersonController`), and feeds; the
    routes pass `field`. A person's archive 404s when no listed entry of
    the type credits them through that field.
  - **Resolution** (D-351): `_{field}/{slug}` in the type's folder when
    published, else the profile; `ContentPage` gains `people` and
    `profile`, and `$entry` is whichever introduces the page.
  - **Templates** (D-351 left them open): `people-{type}-{field}` →
    `people-{field}` → `people` → `collection`; `person-{type}-{field}`
    → `person-{field}` → `person` → `profile` → `collection`; and
    `profile-{slug}` → `profile` → `collection`. `PageKind` has
    `People`, `Person`, and `Profile`. The default theme's `authors.php`
    is `people.php`, its byline shows the first people field as "By"
    and the rest by label ("Photographer: Sam"), and its catalog's
    `authors` keys are `people`.
  - **Template API:** `people($entry, ?$field)`, `bylineUrl()`,
    `personUrl()`, and `peopleUrl()` replace `authors()`,
    `authorUrl()`, and `authorsUrl()`; a profile's page is
    `permalink()`.
  - **Bylines elsewhere:** a feed item's authors and the head's
    `article:author` are the type's first people field (its main
    byline), each linking to the archive under it, else the profile's
    page. A person's feed is titled "{name} | {field} | {type}".
  - **Sitemap and export:** the profiles type's sitemap is every
    profile with a page (`PeopleArchives::profiles()`: published ones
    with files, and virtual ones a listed entry credits); each type's
    adds its people fields' lists and archives.
  - **The admin, until step 2:** its API keeps its shape. A type's
    `authors` is whether it credits anyone, `authorsWord` and
    `authorsPage` are the `authors` field's, and `DataTypeWriter` takes
    `people` and the `authors`/`authorsWord` shortcuts (written as
    `people`). Entry lists leave out every list page and person page
    (`PeoplePage`, `Query::exceptIn()`). Ownership, the new-entry
    default, and "you can't take yourself off" use the profiles type
    and the type's first people field. Accounts keep `author` as the
    link's name.
  - **jtcom trial:** `new Profiles(folder: 'authors')`, so a profile's
    page is `/authors/justintadlock` as in 1.x, and the posts archive
    stays `/archives/authors/justintadlock`.
- **Checked:** `composer check` (`PeopleArchivesTest`: lists, list
  pages, archives and written pages, feeds, profile pages and their
  base and feed, bylines with and without archives, a second people
  field, templates, export, and the sitemap; the people-field cases in
  `ContentTypeTest`; the theme's new routes in `DefaultThemeTest`);
  `npm run admin:build`; on the jtcom trial, `content:lint` clean,
  `routes:list`, and `/authors/justintadlock` (and page 2),
  `/archives/authors`, `/archives/authors/justintadlock` and its JSON
  feed, and `/writing/authors/justintadlock` answering 200, with
  `/authors` a 404.
- **Why:** the author asked for D-351's first step.

### D-353: The admin's side of profiles (D-351's step 2)
- **Date:** 2026-10-01
- **Decision:** Builds D-351's admin step, from the profiles sketch, in
  People's locations (the author's call).
  - **People's panel:** Your Profile, **Accounts** (`accounts.manage`),
    **Profiles** (the profiles type's entry list, with `content.edit`),
    and Roles. `/people` redirects to Accounts, or Your Profile without
    `accounts.manage`. `GET people` and `PeopleView` (D-332's merged
    list) are gone; Accounts is `AccountsView` again, with status tabs,
    a search, and a **Profile** column (the name, its status pill when
    it isn't published, or the slug with **No profile yet**).
  - **The Profiles list** is `EntriesView` with the type's columns
    (`EntryTable`'s `profiles`): Name, Status, Account (the linked
    account's name and username, or **Guest**), Bylines (`uses`), and
    Updated. `GET entries` adds each profile's `linked` and, with
    `accounts.manage`, its `account`. A name opens the profile's screen
    (`listRoute()`, also the command palette's), not the editor.
  - **A profile's screen** (`/profiles/{slug}`, `ProfileDetailView`,
    `GET profiles/{slug}`, `ProfilesController`): a header with its
    address, status, bylines, and account, **View** and **Edit profile**;
    **Identity** (name, slug, the line under the name, avatar);
    **Linked Account** (Open account, Unlink); and **Where This Profile
    Appears**: the profile's own page, then each people field of each
    crediting type, with its archive (or none), how many entries credit
    them there, and what introduces it. **Write one** (`POST
    profiles/{slug}/pages`) writes `_{field}/{slug}` in the type's
    folder as a draft titled with the profile's name and opens it;
    **Use the profile's** (`DELETE profiles/{slug}/pages/{type}/{field}`)
    moves it to the trash. Visible to whoever may edit the profile
    (their own, or anyone's with `content.edit.others`; one with no file
    needs the latter).
  - **`ContentWriter::createAt()`** writes a page at a fixed, undated key
    in a type's folder (slug segments, each may start with `_`), refusing
    one that exists in any format.
  - **Person pages in the editor:** `peoplePage` (`{field, label,
    profile, profileTitle}`; `profile` is `null` for a list page)
    describes both people pages; a person's page is edited like an
    index page, can't be renamed, duplicated, or trashed there, and says
    whose archive it introduces, linking to the profile. Entry lists
    leave person pages out (`Query::exceptIn()`), and tag a list page
    with its field's name (`peopleLabel`).
  - **An account's Public Profile panel:** linked (avatar, name,
    address, status and bylines; **Open profile**, **Unlink**), linked
    but a draft (**Publish**, with `content.publish`), linked to a slug
    with no file (**Create it**: a draft at that slug, opened), or none
    (**Link an existing one**, picking a profile, or **Create one**: a
    draft from a name you give, slugged from it, linked, and opened).
    `GET accounts` gives each account's `profile` (`{id, handle, slug,
    title, status, url, uses}`) in place of `authorPage`. The Author
    field is gone from the account's screen.
  - **The type editor's People panel** (`TypePeopleFields`): a card per
    people field, the first marked **Main byline**: name, the name for
    one, the front matter key (editable until saved), one or more,
    required, archives and their word with the addresses, and a list
    page (`listPages` on `POST`/`PATCH types`, D-352's `authorsPage`
    generalized: `_{field}.md` titled with its name); **Add a people
    field** and **Remove**. `GET types/{name}` adds `people`. The form
    sends `people` whole when it changes; the Behavior panel's Authors
    group stays only in the new-type wizard. Addresses follows each
    field's word and hides a field's keys while it has no archives.
  - **The editor's document panel** has a group per people field,
    labeled with its name, and only the main byline (or a required
    field) keeps its last person (`ReferencePicker`'s `keepLast`).
  - **Wording:** "author page" is "profile" across Your Profile, the
    account screens, and the docs.
  - **Not built:** the sketch's **Import from accounts** (creating
    profiles for accounts without one), and capabilities for editing
    your own profile versus anyone's (still open).
- **Checked:** `composer check` (`AdminPeopleTest`: accounts' profiles,
  the profile list's linked accounts, the profile screen, an author
  seeing only their own, writing and removing an archive's page;
  `AdminTypeEditTest::testEditsATypesPeopleFields`;
  `FilesystemWriterTest::testCreatesPagesAtTheirKeys`); `npm run
  admin:build`; on the jtcom trial in headless Chrome with two
  throwaway accounts (removed after, with their sessions): Accounts,
  Profiles, a profile's screen, writing a page for the posts archive
  (opened in the editor) and using the bio again (its trash entry and
  empty folder removed after), the Public Profile panel's states, the
  type editor's People panel, and the profile screen at 390px with no
  sideways scroll. No console errors beyond the sign-in screen's
  unauthenticated session request.
- **Why:** the author asked for D-351's second step.

### D-354: The People section is named Users, and Accounts has its own icon
- **Date:** 2026-10-01
- **Decision:** The section rail's People section (D-326) is labeled
  **Users** everywhere it's named: the rail, the panel's heading, the
  breadcrumb, and the docs. Its rail icon is Lucide's `user` (added to
  the admin's set), a single person, and **Accounts** uses `key-round`
  (signing in) in the panel, the command palette, and its empty
  states, so no two of the section's icons match: Your Profile keeps
  `circle-user-round` (its buttons too) and Profiles `user-round`. Code
  keeps `people` (the route area, keys, and file names) for now; the
  author will rename those later. The type editor's **People** panel
  (people fields, D-353) keeps its name, since it's about crediting
  people, not the section.
- **Checked:** `npm run admin:build`.
- **Why:** the author asked for Users, `key-round` for Accounts, and a
  person icon on the rail rather than an ID card or `users`.

### D-355: Account settings live on Your Profile, not in the editor
- **Date:** 2026-10-01
- **Decision:** Supersedes D-332's "Your Profile is the editor" (and
  D-329's). Your Profile is always the account's own settings as
  panels (`AccountSettings`: name, what it is, password, theme, color
  scheme), with a **Public Profile** panel first: the linked profile's
  name, address, and status, **Edit your profile** (the editor, like
  any entry) and **View**; **Create your profile** when it's linked to
  one with no file (a draft, opened in the editor); or why there's
  none. The editor loses its profile mode: no `profile` prop, no
  `account` slot or **Account** drawer tab, and Your Profile no longer
  fills the work area (`screenBleed` is gone; only routes bleed).
  Since a profile is now edited at its own address, a profile an
  account is linked to can't be renamed: `can.rename` is false and a
  rename is a `422` (`EntryController::isLinked()`).
- **Checked:** `composer check` (`AdminPeopleTest::
  testALinkedProfileKeepsItsSlug`); `npm run admin:build`; the jtcom
  trial in headless Chrome with a throwaway administrator linked to
  `zadie` (removed after, with its session): Your Profile's panels and
  Edit your profile opening `/content/profile/zadie`.
- **Why:** the author: account management belongs with the account, not
  under the profile's content editor.

### D-356: A profile belongs to one account, picked from a list
- **Date:** 2026-10-01
- **Decision:** Supersedes D-332's allowance of a second account linked
  to one author.
  - **The rule:** `Accounts` refuses to link an account (`create()`,
    `setAuthor()`) to a profile another account is linked to
    (`linkedTo()`), with "The "jane" profile is Jane Author's already; a
    profile belongs to one account." It holds for the admin (a `422`
    with `field: author`) and the CLI alike. Unlink it there first.
  - **`GET profiles`** (`accounts.manage`): every profile by name, real
    or credited without a file, with its status and the account linked
    to it, for pickers.
  - **`ProfilePicker`** replaces `AuthorField` (a typed slug with a
    native suggestion list) on New Account and an account's Public
    Profile panel: the admin's drawn select (`AdminSelect`), listing
    profiles by name with their status when they aren't live, and those
    another account has shown but disabled ("· linked to Jane Author").
- **Checked:** `composer check` (`AccountsTest::
  testLinksAProfileToOneAccount`, `AdminPeopleTest::
  testAProfileBelongsToOneAccount`); `npm run admin:build`; the jtcom
  trial in headless Chrome with two throwaway accounts (removed after,
  with their sessions): the picker open on an account's Public Profile
  panel, and New Account's.
- **Why:** the author: one account shouldn't be able to use another's
  profile, and the typed field didn't match the admin.

### D-357: Profiles are at /profiles/{name} whatever their folder
- **Date:** 2026-10-01
- **Decision:** The profiles type's URL prefix is `profiles`
  (`Profiles::BASE`) unless its URLs set one; unlike other types, it
  doesn't follow the folder (`Profiles::prefix()`). A site keeping its
  profiles elsewhere (jtcom's `authors`) still has `/profiles/jane`,
  and moves them only with `urls: new TypeUrls(prefix: …)`. The jtcom
  trial keeps `new Profiles(folder: 'authors')`, so its profiles are at
  `/profiles/justintadlock` (`/authors/justintadlock`, 1.x's address,
  is now a 404).
- **Checked:** `composer check` (`ContentTypeTest::testBuiltInTypes`);
  on the jtcom trial, `routes:list` and `/profiles/justintadlock`
  answering 200.
- **Why:** the author: profiles should be at `/profiles/{name}` by
  default.

### D-358: Your Profile is named Your Account
- **Date:** 2026-10-01
- **Decision:** The screen with the account's own settings (D-355) is
  labeled **Your Account** everywhere it's named: the Users panel, the
  Home panel's shortcuts, the account menu, the command palette ("Go to
  Your Account", also found by "profile"), its heading and title, and
  the buttons and notes that point to it, and the docs. "Your profile"
  stays where it means the public profile (Edit your profile, Create
  your profile). The address stays `/profile` and the route `profile`
  for now; the author will rename what's under the hood later. Brings
  back the sketch's name, which D-351 had kept as Your Profile.
- **Checked:** `npm run admin:build`.
- **Why:** the author asked for it.


### D-359: Per-type capabilities, and the role screen as capability sections
- **Date:** 2026-10-02
- **Decision:** Applies the capability sections sketch
  (`admin-design/meridian-role-capabilities.html`) to a role's screen,
  and, with it, what D-217 left for later: what a role may do to entries
  is per content type. The author's call: per-type capabilities, using
  the actions that make sense in Blush.
  - **Capabilities:** `content.{type}.{action}`, the action one of
    `create`, `edit`, `publish`, `delete`, and the last three's
    `.others` (`ContentAction`). D-217's site-wide `content.*`
    capabilities are gone (`Capability` keeps the site ones, each with
    a `group()`: Media, Structure, Site, People). The sketch's `view`
    isn't taken: the editor has no read-only mode, so a type is shown
    to an account that can edit its entries, and "No access" is having
    none of its capabilities.
  - **Every type:** a `*` word in a role's capability matches any one
    word (`Role::allows()`), so `content.*.edit` grants each type's,
    including types added later; `Capabilities::NAME` allows `*` words
    after the first. The built-in roles use these, so they cover new
    types as before. A role naming types one by one gives a new type
    nothing (the sketch's safe default).
  - **The registry:** `Capabilities` lists each type's capabilities
    (label "Recipes: Edit anyone's", group the type's plural) and every
    type's ("Every type"), from `ContentTypes`, after the registered
    ones; `register()` takes an optional group (else the first word).
  - **`Permissions`:** `can()` takes a `ContentAction` on an entry, a
    type's name, or (neither) any type; D-219's ownership and
    live-entry rules are per type (editing a live post needs
    `content.post.publish`). A content capability's name with an entry
    still works. `restrict()` takes a `ContentAction` and builds
    alternatives per type of the query (or every type). Every admin
    check moved to the entry's or request's type; Health and the media
    list need the action on any type; a trashed file whose type is gone
    needs `content.*.delete.others`.
  - **The API:** `GET roles` adds each capability's `group` (and a
    content one's `type` and `action`) and the content `types`
    (`name`, `label`, `kind`, `icon`).
  - **The admin:** `canType()` and `canAnyType()` in `session.ts`; the
    rail, palette, dashboard, lists, pickers, and profile screens ask
    per type, and routes take `meta.contentAction` (checked on the
    address's type, else any type). A role's screen
    (`CapabilitySections`, also on New Role): Site Capabilities, one
    section per group; Content Capabilities, one per type (content
    types, then taxonomies, alphabetical) and **Every Type** pinned at
    the foot, marked "Includes new types", whose grants show ticked and
    fixed in each type. Each section: icon, name, a sentence (verbs
    shared, a scope said once when edit, publish, and delete agree,
    "drafts" where publishing is missing), a **Changes** pill, and a
    ⋮ (Full access, Their own only, Drafts only, No access; a group's
    everything or nothing; Every Type's also **Set each type
    separately**). Anyone's ticks their own; clearing their own clears
    anyone's. **Expand all**/**Collapse all** and **Show keys** (kept
    while the admin's open). The header's facts strip (key, origin,
    holders' faces and a link) replaces the About and Held By panels;
    its ⋮ has Rename (a custom role), Copy as JSON, and Reset or
    Delete, replacing the Danger Zone. The save bar (moved to
    `admin.css`, shared with Settings) counts each capability ticked or
    cleared. The administrator gets the sketch's one statement.
    `CapabilityChecks` is gone; Roles counts what each role grants.
- **Departures** are in `admin-design/departures.md`.
- **Checked:** `composer check` (`PermissionsTest`: every type's
  capabilities granting each type's, a one-type role, and `restrict()`
  matching `can()` for it; `AdminEditingTest::
  testCapabilitiesAreEachTypes`; `GET roles`' groups and types);
  `npm run admin:build`; the jtcom trial with a throwaway administrator
  (removed after): `GET roles`, a custom role saved with per-type and
  every-type capabilities, an unknown type's refused, then put back;
  Editor (1280px, sections opened, inherited ticks), Subscriber
  (390px, dark: a preset, the pill, the save bar), and Administrator,
  none scrolling sideways.
- **Open:** in `open-questions.md`.
- **Status:** Every Type moved first, and stays additive (D-360).
- **Why:** the author added the sketch and asked to apply it, with
  per-type capabilities.

### D-360: Every Type stays additive, comes first, and marks what it seals
- **Date:** 2026-10-02
- **Decision:** Settles D-359's open question: **Every Type** grants on
  every type (additive), not a default a type's own settings override.
  It moves from the foot of Content Capabilities to the top, above the
  types, since what it grants is fixed in each type below and the
  cause should be read before its effect. A type it grants every
  action on is marked **Set by Every Type** (an `index-mark`) and has
  no ⋮, since nothing in it can change; a type it grants some of
  keeps the "· every type" note on those checkboxes. No lock icon: a
  lock on a type's name would say the whole section is closed when
  usually only some actions are, and the admin has no lock icon.
- **Checked:** `npm run admin:build`; the jtcom trial's Editor role at
  1280px with a throwaway administrator (removed after).
- **Why:** the author: keep Every Type additive; asked whether it
  should come first and whether locked types need a mark.

### D-361: The Roles list: a description column and two capability readouts
- **Date:** 2026-10-02
- **Decision:** The Roles list's columns are Role, Description (the
  wide one; hidden under 640px), Capabilities, and Accounts. The
  description was a second line under the count. Capabilities isn't
  "N of M" any more, since per-type capabilities (D-359) make the total
  grow by seven with each type. It's two readouts: the types the role
  reaches (**Every type** for a `content.*.…` capability, else
  "N of M types", or "No types"), and "N of M site" for the site
  capabilities. The administrator says **Everything**. The panel's hint
  drops its capability total. Settles the sketch's question of what
  the list shows; an account's screen is still open.
- **Checked:** `npm run admin:build`; the jtcom trial's Roles at 1280px
  and 390px with a throwaway administrator (removed after).
- **Why:** the author asked for a capabilities column and the
  description as the long column.

### D-362: Seven capabilities for managing accounts and roles; the Users group
- **Date:** 2026-10-02
- **Decision:** `accounts.manage` is split, the author's pick of the
  finer of two sets:
  - `accounts.view`: see Accounts and Roles (read-only), each account's
    screen, and which account a profile is linked to (the profile list
    for linking, `GET profiles`, and entries' `account`). Every other
    account action also needs it.
  - `accounts.create`: New Account, with its first roles (still within
    "no more than you", D-312).
  - `accounts.edit`: an account's name, its profile link, and password
    links.
  - `accounts.roles`: giving and taking roles.
  - `accounts.suspend`: suspending and reinstating.
  - `accounts.delete`: removing accounts.
  - `roles.manage`: New Role, Duplicate, and changing, resetting, and
    deleting roles (`GET roles`' `editable` needs it).
  `PATCH accounts/{username}` checks the capability of each thing it
  changes. "Someone stays in charge" (D-312) now means an account that
  isn't suspended holds all seven (`Capability::users()`). The admin
  shows each control only with its capability. Only the administrator
  has them built in. The capabilities' group on a role's screen is
  **Users** (was People), matching the section (D-354).
- **Checked:** `composer check` (`AdminPeopleEditTest::
  testEachChangeNeedsItsCapability`: a role with `accounts.view` and
  `accounts.suspend` sees accounts and roles and suspends, and is
  refused creating, giving roles, renaming, password links, removing,
  and making roles); `npm run admin:build`.
- **Why:** the author asked for account management to be more
  granular.

### D-363: New Role's description is a text area
- **Date:** 2026-10-02
- **Status:** The text area is superseded by D-364 (a text input on its own line).
- **Decision:** On New Role, Name and Key sit side by side at the same
  height (`--ctl`, whatever their fonts), tops aligned, and the
  description is a full-width text area (three rows) below them; one
  column under 640px. Whether `accounts.create` should also need
  `accounts.roles` for a new account's first roles is left open (in
  `open-questions.md`).
- **Checked:** `npm run admin:build`; New Role on the jtcom trial at
  1280px and 390px with a throwaway administrator (removed after).
- **Why:** the author asked for it; unsure about the roles question.

### D-364: A role's name, key, and description, the same on New Role and Rename
- **Date:** 2026-10-02
- **Decision:** Supersedes D-363's text area: the description is a text
  input again, on its own full-width line below Name and Key. A role's
  **Rename this role** panel has the same three fields in the same
  layout, with the key shown and disabled (and its help, "Fixed once
  it's made"), in place of the panel's "Its key stays" hint. Disabled
  text inputs, selects, and text areas now look it everywhere
  (`admin.css`): the sunk surface, a quiet border and text, and a
  not-allowed cursor.
- **Checked:** `npm run admin:build`; Rename on the jtcom trial's
  Subscriber at 1280px (light) and 390px (dark) with a throwaway
  administrator (removed after).
- **Why:** the author asked for it.

### D-365: The Member role, what holding no other role means
- **Date:** 2026-10-02
- **Decision:** A built-in `member` role (Member: "Signs in and looks
  after their own account. Nothing else.") that never has a
  capability, the mirror of the administrator: the admin can't change
  or delete it (`RoleOrigin::editable()`), `storage/roles.json` and
  `config/auth.php` can't redefine it (`Roles` skips it). Signing in
  and Your Account need no capability, so a member manages their own
  name, password, and preferences.
  - **Held alone:** `Accounts::settle()` keeps each role once and the
    member only when there's nothing else; no roles is `[member]`. So
    D-312's "an account always keeps one role" is gone: taking the last
    role leaves a member (`create()` and `setRoles()` settle).
  - **Creating without giving roles:** `POST accounts` needs
    `accounts.roles` for any first roles but the member's; without it,
    the account is a member. Settles D-362's open question.
  - **The admin:** New Account ticks Member to start, always; without
    `accounts.roles` the checkboxes are locked, with a line saying so.
    `RoleChecks`: ticking another role unticks Member, unticking the
    last ticks it, and ticking Member takes the rest; Member alone
    can't be unticked. Member's screen is one statement ("Nothing but
    Their Own Account"), like the administrator's, with no Duplicate;
    the Roles list says "Their own account".
  - The jtcom trial's custom Subscriber role is the same idea and is
    left for the author to remove.
- **Checked:** `composer check` (`AccountsTest::testNoRolesIsTheMember`,
  config can't give the member anything; `AdminPeopleEditTest::
  testCreatingWithoutGivingRolesMakesMembers` and
  `testMembersHaveNothing`); `npm run admin:build`; on the jtcom trial
  with a throwaway administrator (removed after): New Account with
  Member ticked and Editor replacing it, Member's screen, and Roles.
- **Why:** the author proposed it, and asked for it.

### D-366: Home's rail icon is a house; the Dashboard's is a gauge
- **Date:** 2026-10-02
- **Decision:** The rail's Home section uses `house` (it was `gauge`), and
  the Dashboard's panel item uses `gauge` (it was `layout-dashboard`).
  The rail's icon matches its label, and the gauge means the Dashboard
  screen wherever it appears: the panel and the command palette's "Go to
  the dashboard", which already used it. `layout-dashboard` stays on the
  Neutral admin theme option and the Editorial palette entry. `house`
  also tags the site as a source in the inserters; that's a label inside
  an inserter, not navigation, so the overlap was accepted.
- **Why:** the author disliked the Dashboard's icon and proposed the swap.

### D-367: The section crumb toggles the panel when it's already shown
- **Date:** 2026-10-02
- **Decision:** Supersedes D-317's "the section crumb opens that
  section's panel (never closes it)". The top bar's section crumb shows
  its section in the panel, as before, but when the panel already shows
  that section it closes it, like that section's rail button (remembered
  in this browser the same way). Its `aria-expanded` says whether the
  panel shows it, and its tooltip is "Hide the panel" then. On a narrow
  screen the open drawer covers the top bar, so there the crumb only
  opens it.
- **Why:** the author found that pressing `Home` on the Dashboard with
  Home already in the panel did nothing; it should act as the rail
  toggle instead.

### D-368: A calendar on Home
- **Date:** 2026-10-02
- **Decision:** Home gets a **Calendar** screen (`/calendar`, after the
  Dashboard in Home's panel, `calendar-days` icon, and "Go to the
  calendar" in the command palette), for any account that edits some
  type's entries. It's the first of the screens the author and Claude
  talked over for Home, as screens about the whole site rather than one
  type: Activity (who changed what) was judged extension territory, and
  Publishing (cache state, the last export, and buttons to clear and
  export) is of interest but not decided.
  - **What it shows** (the author's choice): every entry the account may
    edit that has a published date, on that day in the site's timezone:
    published, scheduled, and drafts with a date, so what went out and
    what's queued read together. Undated drafts aren't on it. Without a
    type filter, it holds pages and collections only: the trial showed
    that the admin writes a date on the profiles (and terms) it makes,
    and adding a person or a topic isn't publishing something on a day.
    Asking for a taxonomy or profiles type by name shows its dated
    entries. Landing pages are never on it.
  - **Read-only** (the author's choice): a Monday-first month grid
    (as the date picker's), today ringed, out-of-month days shaded and
    empty, previous / next / **Today**, a status control (All,
    Published, Scheduled, Drafts), and a type select (pages and
    collections the account edits, by menu label). Each entry is its
    status icon (a check, a clock, a pen), title, and time on a 12-hour
    clock, with the status and type in words for screen readers and the
    tooltip; scheduled entries are tinted too, but never by color
    alone. An entry opens the editor. The month, status, and type are in
    the address. At 760px and below the grid becomes a list of the days
    with entries. The calendar never reschedules: the author doesn't
    plan to add dragging to a new day, so a date is changed in the
    editor.
  - **API:** `GET calendar` (`CalendarController`): `month`
    (`YYYY-MM`, the site's current month by default), `status`, and
    `type`; answers `{"month", "today", "status", "type", "total",
    "entries"}`, each entry `{"id", "handle", "title", "type", "status",
    "published", "day", "time"}` with `day` and `time` in the site's
    timezone, whatever the browser's. One index query
    (`Query::date()`, `Permissions::restrict()`), in date order, at
    most 500 a month (`total` says how many there were).
- **Why:** the author asked what Home screens should exist and chose to
  start with a calendar: the one view across types that no list gives.

### D-369: The revised profiles sketch: accounts have no name, and its cleanups
- **Date:** 2026-10-02
- **Status:** Your Account's address is `/accounts/{you}` since D-371. Amended by D-370: accounts keep their own name (first in
  `displayName`), every account has an email, a written archive page
  goes to the trash again, and the screens are drawn as the sketch is.
- **Decision:** Applies the author's revised profiles sketch
  (`admin-design/meridian-profiles.html`, replacing the one D-351 came
  from) "to clarify how accounts and profiles should work and behave".
  Its model is D-351's (three nouns, one Profiles collection, archives
  that fall back to the profile); what's new:
  - **An account has no name of its own** (supersedes D-322). Its name
    is its profile's title, everywhere; without a profile, its username
    (`Accounts::displayName()`). `Account::$name`, `withName()`,
    `tidyName()`, `isValidName()`, and `NAME_LENGTH` are gone, as are
    `Accounts::setName()`, `PATCH profile` (`Admin\ProfileController`),
    and `account:name` and `account:add --name`. A `name` already in an
    account's file is ignored and dropped on its next save; `POST` and
    `PATCH accounts` ignore one. `account:list`'s Name column is the
    profile's title (its Author column is Profile).
  - **Your Account is the account screen on your own row**: one screen,
    two doors (`/profile`, and `/accounts/{you}`, which goes there). The
    content never differs by how you arrived; only the trail does
    (Users / Your Account). `ProfileView` and `AccountSettings` are gone;
    `AccountView` shows your own from the session (which now carries the
    account's `profile` and `created`), so it works without
    `accounts.view`, with **Change password** and **Theme and Color
    Scheme** (`AccountPreferences`) on your own.
  - **The account screen**: **All accounts** above the title; an avatar
    (dashed with no profile), the name, and a line of username,
    standing pill, roles, and last sign-in; an **Actions** menu (open
    the profile, make a password link, suspend or reinstate, delete),
    replacing the Danger Zone. A neutral note on your own account ("This
    is your account…", no command-line path) or one you can't change.
    An invited account's link waits in a notice with **Make a new
    link**. **Account** (username and dates, and a note on where the
    name comes from) beside **Roles**, which are now ticked and then
    saved (**Save roles**, **Discard**), not saved as ticked. **Public
    Profile** spans the width, in its four states.
  - **Accounts** takes the entries list's shape (§7): status tabs under
    the header (as links, `?status=`), then a row with a search, a role
    select, a profile select (Has a profile / No profile), and the count;
    a line names the filters in force with **Clear filters**. Rows have
    an avatar, the name (the username in mono and "No profile, so no
    name" when there's none), a **You** tag, the standing pill, and a ⋮
    (Open account, Open profile or Create a profile, Make a password
    link). **New account** is in sentence case.
  - **New Account**: the Name field is gone. Profile is None, **Create a
    new profile…** (Display name and Slug, the slug following the name
    when left empty; usernames never suggest slugs, D-329), or an
    existing one, with a note saying what the account will be named.
    The account is made first, linked to the slug, then the profile as a
    draft, so a refusal leaves an account its screen can **Create it**
    for.
  - **Profiles list**: an **Any account** filter (Linked to an account,
    Guest; `GET entries`'s `account=linked|guest` for the profiles
    type), an avatar on each name (dashed for a guest), **You** on your
    own (one marker: "Yours" stays on entries crediting you), and a ⋮
    with **Open** and **Edit profile** (no Duplicate). The status tab is
    **Draft**, not Drafts, on every list: a tab is named for its status.
  - **A profile's screen**: **All profiles** above the title, and the
    trail Users / Profiles / the name; **Publish** for a draft beside
    View and Edit profile, and a ⋮ (unlink or link an account, move to
    trash). Identity's "Under the name" is **Byline title**. **Linked
    Account** ("At most one, and optional") shows the account's standing
    and last sign-in; a guest profile can be linked to an account with
    no profile there. **Where This Profile Appears** has a **Content**
    column of pills, **Written** (a filled dot) and **Inherited** (a
    ring), neutral since neither is a status; a written page's **Edit**
    menu has **Delete the page**, which deletes it for good (`DELETE
    profiles/{slug}/pages/…` purges it from the trash too: such a page
    has no trash); a field whose archive is off keeps its row, with a
    written page marked **Unreachable** (`appears` now sends the page
    whatever the archive) and **Type settings**; types that credit no one
    are listed last ("No profile field").
  - **The type editor's** People panel is named for the profiles type
    (**Profiles**), "Each field credits a profile, under this type's own
    word for it", with **Add a profile field**, Label and Singular, and
    the sketch's wording for removing one.
  - **One back button, above the title**, on every detail screen
    (`.page-back`: accounts, profiles, types, roles, field sets, media,
    a trashed entry, New Account, New Field Set), not among the actions.
  - Shared styles: `.avatar` (`--large`, `--guest`), `.pill--written`
    and `--inherited`, `.panel__note`, `.page-header__id`, and the
    status tabs (`.status-tabs`, from the entries list). Two Lucide
    icons: `unlink` and `circle-pause`.
  - **Departures kept** (in `departures.md`): no email (so no Email
    column, Change email, or emailed invitations); linking picks from
    every profile with linked ones disabled (D-356); the browser's
    confirmations; Reinstate, not Reactivate; no Copy links in the
    profiles bulk bar; archive switches inside each field's card.
  - The sketch's open list is in `open-questions.md` (a third kind for
    profiles, a byline with no profile, `/` in list search, merging
    profiles, the fourth rail section).
- **Checked:** `composer check` (`AccountsTest::
  testNamesAccountsByTheirProfiles`, `AdminApiTest::
  testTheSessionNamesAccountsByTheirProfiles`, `AdminPeopleEditTest::
  testAccountsHaveNoNameOfTheirOwn`, `AccountCommandsTest::
  testListsAccountsByTheirProfilesNames`, the profiles list's account
  filter and a written page deleted for good in `AdminPeopleTest`);
  `npm run admin:build`; on the jtcom trial in headless Chrome with two
  throwaway accounts (an administrator and a member, removed after with
  their sessions): Your Account for both, Accounts and its tabs, an
  invited account, New Account making an account with a new profile
  (its link shown, the profile a draft, opened from Public Profile),
  deleting it from Actions (its profile left a guest, then removed),
  the Profiles list and its Guest filter, a profile's screen and its
  trail, the type editor's Profiles panel, and Accounts, an account, and
  a profile at 390px. No console errors.
- **Why:** the author added the revised sketch and asked for it to be
  implemented.

### D-370: Accounts keep their name, need an email, and the Users screens look like the sketch
- **Date:** 2026-10-02
- **Decision:** The author's corrections to D-369.
  - **Accounts keep their own display name** (D-322 restored, D-369's
    removal reversed): `Account::$name`, `Accounts::setName()`, `PATCH
    profile`, `account:name`, and `--name` are back. It now comes
    first: `displayName` is the account's name, else its profile's
    title, else its username. The profile's title stays the person's
    name on the site. New Account has an optional **Display name**; an
    account's screen shows it and edits it (**Edit details**, or
    **Change name or email** on Your Account).
  - **Every account needs an email address.** `Account::$email`
    (written as `email` when set; `isValidEmail()`: PHP's email filter,
    Unicode allowed, up to 254 characters). `Accounts::create()` and
    `invite()` take a required `email` (last, so the other arguments
    keep their places) and `checkEmail()` refuses one that's missing,
    invalid, or another account's in any case; `setEmail()` changes it.
    An account saved before emails loads with `null`, and the admin
    marks it **No email** (Accounts) and asks for one (a warning notice
    with **Add an email address**); nothing else changes for it.
    `POST accounts` requires `email` (`422`, `field: email`); `PATCH
    accounts/{username}` (`accounts.edit`) and `PATCH profile` (your
    own) change it. `account:add` asks for it unless `--email=` gives
    it, `init` asks for the first administrator's, `account:email`
    changes one, and `account:list` shows it. Blush still sends no
    email: it's for the people who manage accounts (the Accounts list's
    Email column and **Copy email address**, the account's facts, a
    profile's Linked Account), and password links are still copied.
  - **A written archive page goes to the trash again** (D-369's purge
    reversed): **Move to trash** in the row's Edit menu, restorable
    from the type's Trash tab.
  - **The Users screens are drawn as the sketch is**, under a `.people`
    root (Accounts, an account and Your Account, New Account, the
    Profiles list, a profile), so other screens keep the direction's
    look: 36px between sections, panel headers without a rule, the
    sketch's status tabs (words with a plain count), filter row (a
    240px search, compact selects, the count at its end), smaller
    pills, caps tags and the **You** marker (`.tag--you`), neutral
    notices with a ruled edge, unadorned links (`.lnk`), muted
    secondary cells, facts as a labeled grid (`.kv`) or ruled rows
    (`.fact-rows`), the link box, `.sub-fields` and `.will` on New
    Account, and a `.submit-row`. Roles are rows with a drawn checkbox
    and a filled selection (`RoleChecks`), not cards. Theme choices
    are swatches of each theme in the current scheme, from new
    `--preview-{theme}-{bg,surface,accent,fg}` tokens (light and dark,
    not redefined by an admin theme). The profile's header menu is a
    vertical ellipsis, its Where This Profile Appears tints the
    profile's own row and fades a field whose archive is off. The type
    editor's Profiles panel is the sketch's rows (Label with its
    singular and key under it, Archive base, Entries take as one
    choice of four, and ×), with **Add a profile field** in its header,
    and a separate **Archives** panel of switches (with each field's
    list page).
  - Not drawn: the section panel's counts beside Accounts, Profiles,
    and Roles (the shell is the direction's, which has none, and the
    content panel would need them too).
- **Checked:** `composer check` (`AccountsTest::
  testEveryAccountNeedsAnEmailAddress` and the restored names test,
  `AccountCommandsTest::testSetsAnAccountsEmail`, `SetupCommandsTest`
  asking for an email, `AdminApiTest` changing your own email,
  `AdminPeopleEditTest` refusing a missing, invalid, or taken email,
  and the archive page trashed again in `AdminPeopleTest`); `npm run
  admin:build`; on the jtcom trial in headless Chrome, beside the
  sketch rendered at the same size, with throwaway accounts (removed
  after, with their sessions and profile): Your Account in light and
  dark (changing its name and email), Accounts (the trial's three
  accounts show **No email**), New Account refusing a taken email then
  making an account with a new profile, editing another account's
  email, deleting it, the Profiles list, a profile, and the type
  editor. No console errors beyond the refused request.
- **Why:** the author: written archive pages stay trashable, accounts
  still need their own display name, every account needs an email,
  and the design must match the sketch.

### D-371: Counts in the section panel, and Your Account at its own address
- **Date:** 2026-10-02
- **Decision:** The author's follow-ups to D-370.
  - **The section panel shows counts**, as the profiles sketch does:
    beside each content type's link (and its nested taxonomies, and
    shared ones), Accounts, Profiles, Roles, Content Types, Fields, and
    Extensions, in mono at the link's end (a comma before it for screen
    readers). Media, Themes (D-372 adds them), and the screens that aren't lists
    have none. `GET counts` (`CountsController`) answers `{"types": {name:
    n}}`, each type the account may edit counted as its list counts
    it for the account (any status, without the index page and people
    pages; `Permissions::restrict()`), with `accounts` and `roles` for
    `accounts.view` and `contentTypes`, `fieldSets`, and `extensions`
    (installed) for `site.settings`; a count the account may not see is
    left out. The admin loads it with the shell and again after every
    change of screen (`counts.ts`), so a made or removed thing shows on
    the next screen; one load at a time, and a failed one keeps the
    counts it had.
  - **Your Account is `/accounts/{username}`** for your own username,
    replacing D-369's `/profile` screen. `/profile` (the `profile`
    route, which other links still name) redirects there. The account
    route no longer needs `accounts.view` for your own account (its
    `beforeEnter`), so every account reaches it; others' still need it.
    On your own, the panel marks Your Account (not Accounts), the trail
    is Users / Your Account, and the title is Your Account;
    `AccountView` knows it's yours by the username.
- **Checked:** `composer check` (`AdminPeopleTest::
  testCountsTheSectionPanelsLists` and `testCountsOnlyWhatTheAccountMaySee`);
  `npm run admin:build`; on the jtcom trial in headless Chrome with a
  throwaway administrator and member (removed after, with their
  sessions): every section's counts (Posts' matching its list), Your
  Account from the Home panel and from `/profile` both at
  `/accounts/{username}` with its trail and panel marking, another
  account's trail, and the member reaching their own account but sent
  to the dashboard from another's. No console errors.
- **Why:** the author asked for the counts, and for Your Account to be
  the account's own address rather than a special page.

### D-372: Counts beside Media and Themes too
- **Date:** 2026-10-02
- **Decision:** Amends D-371's "Media, Themes … have none": the section
  panel counts them too. `GET counts` adds `media` (the files in the
  library, from its index: `MediaLibrary::query()`'s total) for
  `media.upload`, the permission the Media link needs, and `themes`
  (installed, valid themes, `Themes::all()`) for `site.settings`. Every
  link to a list in the panel now has a count.
- **Checked:** `composer check` (`AdminPeopleTest`'s counts tests: the
  two new counts, and both left out for a contributor); `npm run
  admin:build`; on the jtcom trial in headless Chrome with a throwaway
  administrator (removed after, with its session): Media 291 and Themes
  2 in their panels. No console errors.
- **Why:** the author asked for them.

### D-373: Modal confirmations everywhere, linking as the sketch does, and three fixes
- **Date:** 2026-10-02
- **Decision:** The author's follow-ups to D-370.
  - **Every confirmation is a modal drawn as the profiles sketch's**,
    never the browser's `confirm()`: `confirmAction({title, body,
    confirm, cancel, danger})` (`confirm.ts`) resolves `true` or
    `false`, shown one at a time by `ConfirmHost` in the layout. A title
    is a question in title case ("Delete Jane Doe?"); the body is
    paragraphs, where `**…**` marks words to stand out and nothing else
    is markup, so typed names stay text; the confirming button names
    the act ("Delete the account"), red with the focus on Cancel when
    it destroys something. `confirmLeave()` is the usual "Leave Without
    Saving?" for unsaved changes (route guards may wait on it). All of
    the admin's 28 `window.confirm()` calls are converted; the browser's
    own leave-the-page prompt (`beforeunload`) can't be drawn and
    stays. `AdminModal` is the shell (a native `<dialog>`, shown
    modally, Escape or the backdrop to close, focus back where it was),
    styled as `.prompt` (`.modal` was the library pickers' already).
  - **Linking is the sketch's modals.** On an account's screen, **Link
    an existing one** opens **Link a Profile**: a list of the profiles
    no account has, each picked by pressing it, then **Link the
    profile** (or, with none free, **No Profile to Link** and **Create
    a profile**); **Create one** opens **Create a Profile** (display
    name, prefilled from the account's, and slug), which creates the
    draft, links it, and opens the profile's screen; **Unlink** asks in
    a modal. On a profile's screen, **Link an account** opens **Link an
    Account** (the accounts with no profile, with their roles), and
    **Unlink** asks the same way. The inline forms and select are gone.
  - **Your own profile link is yours to change**: with `accounts.edit`,
    `PATCH accounts/{you}` with only `author` is allowed
    (`AccountEditController::ownLink()`), so Your Account has **Open
    profile**, **Unlink**, **Link an existing one**, and **Create one**,
    and a profile's screen can unlink or link you. Your roles and
    standing stay another's to change.
  - **Fixes:** notices on the Users screens have the admin's usual
    rounded corners (the sketch's are square on the ruled edge), as do
    New Account's sub-fields; a table at the top of its panel (Accounts,
    Profiles) is clipped to the panel's rounded corners; and the
    Profiles list's Bylines header isn't drawn in the count column's
    mono (only its cells are).
- **Checked:** `composer check` (`AdminPeopleEditTest`: your own link
  changed alone, and refused with your roles); `npm run admin:build`;
  on the jtcom trial in headless Chrome with a throwaway administrator
  (removed after, with its sessions, the profile it made, and that
  profile's trash entry): Link a Profile, Create a Profile (landing on
  the new profile's screen), Unlink on the profile's screen, Link an
  Account, Unlink on Your Account, and Move to trash from the Profiles
  list, all in modals, with no native dialog shown and no console
  errors; the Accounts and Profiles tables' rounded corners.
- **Why:** the author asked for them.

### D-374: No browser warning on leaving the editor; reload shortcuts ask in a modal
- **Date:** 2026-10-02
- **Decision:** The browser's leave-the-page warning (`beforeunload`)
  can't be drawn as the admin's modals (D-373), nor replaced: a page
  can only ask for it, and closing a tab, reloading from the toolbar,
  or typing an address can't be intercepted. The author's call, both
  of the options offered:
  - **No warning.** The editor stops asking for it. Nothing is lost:
    unsaved changes are kept in this browser as they're made (D-240),
    and now also on `pagehide`, so the last keystrokes before leaving
    are kept too (`keepNow()`, which the 400ms keep also uses). The
    offer when the entry opens again says so: "Your unsaved changes to
    this post from … were kept in this browser when you left. Restore
    them to carry on where you were."
  - **The reload shortcuts ask in the admin's modal**: ⌘R, Ctrl+R, or
    F5 with unsaved changes keeps them at once, then asks **Reload
    Without Saving?** (Reload / Stay), and reloads on Reload.
  - Going to another admin screen still asks **Leave Without Saving?**
    (D-375 keeps the changes there too).
- **Checked:** `npm run admin:build`; on the jtcom trial in headless
  Chrome with a throwaway administrator (removed after, with its
  sessions; nothing saved): ⌘R in an edited post showed the modal and
  reloaded to the offer; typing and then going to another address
  showed no native dialog, and the offer had the last words typed.
- **Why:** the author asked for both.
- **Asked, not decided:** autosave (see `open-questions.md`).

### D-375: Leaving the editor keeps unsaved changes in the browser too
- **Date:** 2026-10-02
- **Decision:** Amends D-374. Going to another admin screen with unsaved
  changes no longer throws away the copy kept in this browser: it's
  kept (at once, `keepNow()`) and offered back when the entry opens
  again, as after leaving the page. The question says so: **Leave
  Without Saving?** "Your unsaved changes to this post stay in this
  browser only, and are offered back when you open it here again."
  (Leave / Stay). The reload shortcuts' question says "stay in this
  browser only" too. "Only" is the point: they aren't on the server,
  so another browser or device won't see them.
  - The pending-changes store and autosave (D-374's question) are on
    hold, by the author's call; `open-questions.md` keeps them.
- **Checked:** `npm run admin:build`; on the jtcom trial in headless
  Chrome with a throwaway administrator (removed after, with its
  sessions; nothing saved): typing in a post, going back in the admin
  showed the question with its new words, Leave, then reopening the post
  offered the changes back.
- **Why:** the author asked whether the question should say the
  changes stay in this browser, and wanted them kept.

### D-376: Notices on the Users screens lose their ruled edge
- **Date:** 2026-10-02
- **Decision:** The notices on the Users screens (`.people .notice`:
  info, warning, and error) drop the sketch's darker 2px left border;
  they're a quiet box (or the warning's or error's soft ground) with
  the admin's usual rounded corners (D-373). New Account's sub-fields
  keep their accent edge, since they aren't a notice.
- **Checked:** `npm run admin:build`.
- **Why:** the author asked for it.

### D-377: The admin's other notices lose their darker edge too
- **Date:** 2026-10-02
- **Decision:** Follows D-376 across the admin. The other notices never
  had a left border; their darker edge was a full 1px outline in the
  state's dot color on success, warning, and error notices
  (`.notice--success`, `--warn`, `--error`). That outline is gone
  (transparent), so they're the soft colored ground alone, as on the
  Users screens. The neutral notice keeps its quiet 1px border.
- **Checked:** `npm run admin:build`.
- **Why:** the author asked for the left border gone on the admin's
  other notices too.

### D-378: Extensions are a type system: plugins, themes, icon packs, and admin themes
- **Date:** 2026-10-02
- **Status:** Built (plugins, themes, and icon packs) by D-379; admin
  themes and installing from the admin are still planned (D-388
  decides the admin installs into `user/`). Icon packs
  can be turned off since D-385. Supersedes
  D-041's naming (its
  "extensions" are now **plugins**) and D-171's rule for where a theme's
  or extension's component namespace comes from; amends D-058 and
  D-187. D-020 stands (themes add no content types, routes, or
  commands), but themes can run PHP (a `provider`, templates).
- **Decision:** **Extensions** is the umbrella term for everything a
  site installs. Each extension is one of a few **kinds**:
  - **Plugin:** today's extensions (D-041): a manifest plus a service
    provider, many on at once.
  - **Theme:** as now (D-020): one active chain.
  - **Icon pack:** SVG icons in its namespace, many on at once.
    Today icons come only from the framework, themes, and plugins'
    `IconRegistry` folders (D-187); a pack ships them on its own.
  - **Admin theme:** planned, not first. It would add to the admin's
    theme choice (`AdminTheme`, neutral and Editorial, D-317), one per
    account, so build the shared pieces with it in mind.
  - Other kinds (language packs, starter kits) are on hold.
  - **Every manifest has `name` and `label`.** `name` is always the
    key: a required `vendor/name` (`justintadlock/jtcom`, `acme/tabs`),
    the same as the Composer package name for a Composer package.
    `label` is always the readable title ("Justin Tadlock"). That
    changes theme manifests, whose `name` is the label today.
  - **Every manifest declares its `namespace`,** explicitly, not read
    from `composer.json` or the folder: the namespace its components,
    icons, and translation domain use (`jtcom`, for `::jtcom/…` and
    `jtcom/github`). Blush checks that no two installed extensions
    claim the same one, and `blush` and `app` are reserved. This
    answers D-171's open clash question.
  - **One folder per kind, one level deep:** `user/plugins/{folder}`,
    `user/themes/{folder}`, `user/icons/{folder}`, and later
    `user/admin-themes/{folder}`. The folder is only where it lives
    (conventionally the package's short name); its identity is the
    manifest's `name`, and two with the same `name` are an error.
    `user/extensions/{kind}/` may come later.
  - **Each kind's manifest is named for it** (`plugin.json`,
    `theme.json`, and so on, or `.yaml`), and Composer package types
    likewise (`blush-plugin`, `blush-theme`, `blush-icons`).
  - **Shared pieces:** one discovery pipeline (local folders and
    Composer's `installed.json`, by kind), one manifest base (`name`,
    `label`, `namespace`, `version`, `description`, `requires`), one
    namespace check, one compiled cache, provenance and what each adds,
    and a check command per kind.
  - **Kinds say whether they run code.** Plugins and themes do; icon
    packs (and admin themes, if they stay CSS and data) don't. Every
    extension will be installable from the admin eventually, but that's
    future work; for now the kinds carry the flag, and the admin may
    show placeholders for installing. D-039 and D-166 stand until that
    work decides how code installs from a browser.
- **Why:** the author expects more kinds of extensions than plugins,
  and the code already has two package systems (extensions and themes)
  with icons attached to both. Naming the kinds and sharing the
  machinery makes a new kind cheap, and a required `vendor/name` plus a
  declared namespace gives every package one identity and every
  namespace one owner.

### D-379: Extension kinds, built
- **Date:** 2026-10-02
- **Status:** Plugins and icon packs can be turned on and off and deleted
  in the admin, plugins' `requires` are enforced, and icon packs can be
  turned off (`config/icons.php`), since D-385.
- **Decision:** Implements D-378 for plugins, themes, and icon packs,
  and settles the details `open-questions.md` listed.
  - **Themes are referred to by name** (the author's call): config's
    `active`, a theme's `parent`, `?theme=`, `theme:*` arguments and
    `--theme=`, asset URLs (`/themes/{vendor}/{name}/…`), published
    assets (`public/themes/{vendor}/{name}/`), and site overrides
    (`resources/views/themes/{vendor}/{name}/`). The default theme is
    `blush/default` (label Default, namespace `default`). A theme's
    namespace replaces its slug wherever the slug was a namespace:
    components, icons, variants, and `{namespace}-card.php`.
  - **Reserved namespaces:** `blush`, `app`, `theme` (the chain's
    translation domain), and `default` (the default theme's).
  - **Namespace clashes:** two plugins sharing one fail discovery, like
    two sharing a name (D-058); two themes, or two icon packs, are both
    broken; across kinds, installed plugins (even turned off) claim
    first, then themes, then icon packs, and `Bootstrap` records each
    loser as broken ("Its namespace, "x", is the plugin acme/x's.").
  - **Folders:** a folder is only where an extension lives. Two in one
    kind's folder with the same name are both broken (plugins: an
    error). A Composer package's name is the extension's: a manifest
    without `name` takes it, and one naming another is broken
    (`extra.blush.slug` is gone). Broken themes and packs are listed by
    where they were found (`user/themes/{folder}`, or the package
    name), so `Themes::find()` no longer throws.
  - **Plugins** (`Blush\Plugin`, was `Blush\Extension`): `user/plugins`,
    `plugin.json`, `blush-plugin` (with `extra.blush` holding `label`,
    `namespace`, `provider`, `requires`), `config/plugins.php`
    (`PluginConfig`), `storage/cache/plugins.php`, `cache:clear
    --plugins`. A plugin's translation domain and the components and
    icons the admin attributes to it go by its namespace, not its
    vendor. `Blush\Extension` keeps what every kind shares
    (`ExtensionKind`, `ExtensionName`, `ExtensionNamespace`,
    `ManifestFile`, `LocalAutoloader`, `ExtensionException`). Content
    types' and field sets' `extension` origin keeps its value (only
    plugins add them, and the admin says "A plugin").
  - **Icon packs** (`Icon\IconPack`): `user/icons/{folder}/icons.json`
    (`name`, `label`, `namespace`, optional `version`, `description`, and
    `folder`, the subfolder its SVGs are in) or `blush-icons` packages.
    Every installed pack is on. Their folders seed `IconRegistry`, so
    lookup is site, themes, packs and plugins, core, and a theme
    restyles one with `icons/{namespace}/{icon}.svg`; labels come from
    the pack's `lang/`, its namespace's domain. Cached in
    `storage/cache/icon-packs.php` outside development (`cache:clear
    --icon-packs`). Schema `icons.schema.json`; `plugin.schema.json`
    replaces `extension.schema.json`, and every manifest schema shares
    `name`, `label`, and `namespace`.
  - **Admin:** Customize is Themes, Plugins (`/plugins`, `/extensions`
    redirects; `GET plugins`), and Icon Packs (`/icon-packs`; `GET
    icon-packs`), each read-only with a disabled **Install** button as
    the placeholder for installing from the admin. Rows show the label,
    then the name. Counts add `plugins` (was `extensions`) and
    `iconPacks`. Provenance kinds are `theme`, `site`, `icon-pack`, and
    `plugin` (was `extension`). `GET appearance` gives each theme's
    `name`, `label`, and `namespace`, the chain by name, and broken
    themes by `where`.
  - **CLI:** `theme:new <vendor/name> [--parent] [--label]
    [--namespace]` (the folder and namespace default to the part after
    the `/`), `theme:activate <name>`, `theme:check [name]`, and
    `theme:list` shows Name, Label, Namespace, Version, Parent, Source.
- **Checked:** `composer check` (1,232 tests; new `IconPacksTest`,
  `ExtensionNamespacesTest`, `AdminIconPacksTest`); `npm run
  admin:build`; the jtcom trial (its manifest is now
  `justintadlock/jtcom`, namespace `jtcom`, and `config/theme.php`
  names it): pages and theme assets serve at the new URLs, its icons
  render, `theme:check` is clean, and Themes, Plugins, and Icon Packs
  look right in headless Chrome with a throwaway administrator and pack
  (removed after).
- **Why:** D-378, with the author choosing names over namespaces or
  folders for theme references.


### D-380: The Config panel's Customize group is Extensions
- **Date:** 2026-10-02
- **Decision:** Amends D-327 and D-379. The Config panel's third group
  is headed **Extensions** (was Customize), holding Themes, Plugins, and
  Icon Packs, so the admin uses the umbrella term D-378 settled.
  - The jtcom trial (never committed) has an example of each new kind:
    the `example/word-count` plugin (`user/plugins/word-count`: a
    `wordcount:report` command, a **Count words** dashboard action, and
    a `wordcount/tally` icon) and the `example/weather` icon pack
    (`user/icons/weather`: five Lucide icons in `svg/`, labeled from its
    `lang/`). The plugin has no component, since plugin views (D-174)
    aren't built.
- **Checked:** `npm run admin:build`; on the jtcom trial, `wordcount:report`,
  `icon:list`, `GET plugins`, the action through `POST actions/count-words`,
  and the Plugins screen in headless Chrome with a throwaway
  administrator (removed after, with its session).
- **Why:** the author asked for both.

### D-381: The Themes screen from the themes sketch
- **Date:** 2026-10-02
- **Status:** Its theme details screen is built in D-383.
- **Decision:** Builds the list half of the author's themes sketch
  (`admin-design/blush-themes-screen.html`); its theme details screen
  comes later. Amends D-306 (Activate) and D-379 (Install Theme). The
  author chose all three of the parts the sketch needed that Blush
  didn't have:
  - **Activate saves in `user/data/settings.json`** (option 1 of
    two; the other kept the screen read-only). `Setting::Theme`,
    `theme.active`, on no Settings screen (`screen()` is `null`), lays
    over `ThemeConfig` as every owner setting does (D-324), so D-039
    holds: the admin still never writes `config/`. The screen saves it
    with `PATCH settings`, which now refuses a theme that isn't
    installed or whose chain can't be built (`422`). It counts as
    needing a refresh, since a theme's provider runs at boot.
    `theme:activate` clears a saved theme, so the command (a deploy's
    way) always takes effect, and says so.
  - **Delete is built:** `DELETE themes/{folder}`
    (`ThemeEditController`, `site.settings`) removes a folder in
    `user/themes` holding a theme or a broken one
    (`Filesystem::removeDirectory()`, which removes symlinks without
    following them), and clears the theme cache. The active theme, and
    any theme it falls back to, are refused (`409`), which is wider than
    the sketch's rule (the sketch only refuses the active one).
    Composer themes and the default theme aren't folders in
    `user/themes`, so they're never deleted. A theme that fell back to
    the deleted one keeps naming it and can't be activated (the
    sketch's rule: no quiet re-parenting).
  - **Previews are drawn from a `preview` key in `theme.json`**
    (`ThemePreview`, `PreviewLayout`): `layout` (`centered`, `sidebar`,
    `wide`), a `type` line, and a `palette` of six roles, each a hex
    color or a `[light, dark]` pair, kept as six-digit lowercase hex.
    The sketch's `bg` is `background`. All six roles are required once
    there's a palette; a malformed `preview` breaks the manifest, as
    other keys do. A theme without a palette is sketched in the admin's
    own quiet tokens. The default theme declares its palette (from its
    stylesheet's `light-dark()` colors); the schema has it.
  - **`GET appearance`** adds each theme's `folder`, `preview`, why
    it's `blocked` (its chain's error), and whether it's `deletable`;
    invalid themes their `deletable`; and `saved` (the active theme is
    in `settings.json`) and `problem` (the active chain's error; the
    screen still loads, so another theme can be activated). The nav
    count for Themes includes broken themes, as the screen lists them.
  - **The screen:** cards as the sketch draws them (`ThemeSketch`
    draws the preview with container units instead of a scaled
    1200-pixel canvas, so it needs no measuring), the active one first;
    **Activate** asks, shows **Activating…**, and a failure says the
    site is unchanged with **Try again**; a missing parent is a warn
    fact and a message saying what to do; broken themes are cards with
    no preview, titled by where they were found. The menu has
    **Preview on the site** (development), **Copy folder path**,
    **Copy activate command**, and **Delete theme**; with nothing in it
    (the active default theme), it isn't shown. The bottom note says
    where the active theme is set, with **Use `config/theme.php`'s
    theme** when it's saved. **Install Theme** opens the sketch's modal,
    with uploading marked as coming and **Upload** disabled (D-378).
    Departures are in `admin-design/departures.md`.
- **Checked:** `composer check` (1,250 tests; `AdminAppearanceTest`:
  the new fields, activating over the config, refusals, deleting, and
  the themes the site uses kept; `ThemePreviewTest`; `FilesystemTest`
  for links; `ThemeCommandsTest` for clearing the saved theme);
  `npm run admin:build`; on the jtcom trial (whose theme now declares a
  preview) in headless Chrome with a throwaway administrator and three
  throwaway themes (a sidebar layout, a missing parent, a broken
  manifest): light and dark, phone width, the menu, the confirmations,
  activating, **Use `config/theme.php`'s theme**, deleting, and the
  install modal (all removed after, with the sessions).
- **Why:** the author asked for the sketch's primary screen, and chose
  settings.json, building Delete, and a `preview` key.

### D-382: Components render themselves
- **Date:** 2026-10-02
- **Decision:** Every component class has a `render()` (abstract on
  `Component`), its own markup, used when no template for it is in the
  theme chain (or the site's views). The author's idea, as proposed:
  required of classes, not of template-only components.
  - **What it returns:** a string of HTML (printed as is, so the class
    escapes it); a `ComponentView` (`$this->view($path, ...$data)`), a
    template file it ships, by absolute path, rendered as a chain
    template is (`$template`, `$component`, the data; the view name
    stays `components/{key}`, so context providers still apply); or
    `null`, no markup of its own (`TemplateComponent`'s, and a class
    whose template is only a theme's).
  - **Order:** a variant's template, then `template()` or
    `components/{key}`, in the chain, nearest first; then `render()`;
    then, for `null`, `ViewNotFound` as before.
  - **Core components:** each class's `render()` returns its file in
    `resources/components/`, the templates moved out of the default
    theme (which has no `components/` folder now). A theme still
    overrides one by name. The menu's and table of contents' nested
    lists are drawn inside their templates (`menu.php`, `toc.php`) by a
    recursive closure, each level in its own scope, since a file outside
    the chain can't be included by view name. The author chose one file
    each over separate list files: the lists can't be overridden on
    their own any more (the `components/toc/list` and
    `components/menu/list` partials are gone), so a theme overrides the
    whole component.
  - **Listings:** `ComponentListing::rendersItself()` is true when the
    class's `render()` can't return `null` (its declared return type),
    and such a component is never missing a template, for
    `component:list` (whose Template column says `(its own)`) and
    `theme:check`.
  - **Themes outside the chain:** amends D-381's fix. A directive for a
    component in the namespace of an installed theme outside the chain
    (the active one's, while another is previewed with `?theme=`) renders
    with `render()`; one that can't render (`ViewNotFound`) is plain
    content, as an unknown directive is. The jtcom trial's
    `PostArchives` and `EntryTerms` return their theme template files,
    so the archives render while Default is previewed.
  - D-174 (plugin views in the chain) stays on hold; a plugin's
    components render themselves meanwhile.
- **Checked:** `composer check`; `ComponentsTest` (a string `render()`, a
  core component from its own file, a chain template winning, listings,
  and an outside theme's component rendering itself or nothing);
  existing menu and table of contents tests for nesting; on the jtcom
  trial, the home and archive pages, and the archives with
  `?theme=blush/default`.
- **Why:** the author asked for it, so a component renders wherever its
  content is, with themes overriding a default instead of having to
  supply every template.

### D-383: A theme's details screen
- **Date:** 2026-10-02
- **Status:** Its Author row is added in D-384.
- **Decision:** Builds the themes sketch's detail screen, which D-381
  left for later, at `/themes/{vendor}/{name}` (`ThemeView`, route
  `theme`, under Themes in the trail and the panel). It reads `GET
  appearance`, so no new API.
  - **The screen:** the back link, the theme's label and description,
    and its action (**Active** and **View site**; **Can't activate**;
    or **Activate {label}**, with a failure said under the header, as
    on the cards). A Preview panel with two sketches pinned to light and
    dark (`ThemeSketch`'s `scheme`), the blocked reason above them; a
    Details panel (name, version, who installed it, folder with a copy
    button, namespace, type, the theme it falls back to, and the themes
    that fall back to it, each linked); a Palette panel of swatches,
    each inked in whichever of the theme's text or background colors
    contrasts more (WCAG luminance), and one **Light and dark** group
    when the halves match; then the delete zone, the Composer note, or
    why a theme the site uses can't be deleted. Deleting goes back to
    the list.
  - **On the list:** a card's label and its fallback link to details,
    and the menu has **Theme details** first, so it's never empty.
  - **Shared code:** loading, activating, deleting, and the blocked
    message moved to `themes.ts` (`useThemes()`), used by both screens.
  - Broken themes have no details screen (they have no name), and
    departures are in `admin-design/departures.md`.
- **Checked:** `npm run admin:build`; on the jtcom trial in headless
  Chrome with a throwaway administrator and three throwaway themes: a
  palette theme with a child, a theme with a missing parent and no
  palette, the active jtcom theme (one palette group), an unknown name,
  dark mode, phone width, and the flows (a card's link, activating from
  details, the parent's in-use note, **Use `config/theme.php`'s
  theme**, deleting back to the list), all removed after, with the
  sessions.
- **Why:** the author asked for the sketch's detail view.

### D-384: Themes list their authors, as composer.json does
- **Date:** 2026-10-02
- **Status:** Plugins and icon packs take `authors` too since D-385.
- **Decision:** `theme.json` takes `authors`, in `composer.json`'s shape
  (the author's call): a list of objects, each with a `name`, and an
  optional `email`, `homepage`, and `role`.
  - **`ExtensionAuthor`** (in `Blush\Extension`, so plugins and icon
    packs can take it up later) reads an entry strictly: unknown keys, a
    missing or empty name, a bad email, or a homepage that isn't an
    `http`/`https` URL break the manifest, as other keys do.
  - **The fallback:** a manifest without `authors` takes them from the
    `composer.json` in its folder (a Composer package's, or a
    `user/themes` theme kept as a package), read leniently: entries that
    don't fit are skipped, and an unreadable file is no authors, since
    it isn't the manifest. `ThemeDiscovery` fills them into the
    manifest's data, so the theme cache keeps them. A manifest's own
    `authors`, even `[]`, wins.
  - The schema has `authors`; `GET appearance` gives each theme's
    `authors` (empty values left out); a theme's details screen has an
    Author (or Authors) row: each name, linked to their homepage, their
    role, and an email link. The default theme lists its author.
  - Plugins and icon packs don't have `authors` yet.
- **Checked:** `composer check` (`ExtensionAuthorTest`; `ThemesTest`
  for broken `authors`; `AdminAppearanceTest` for a manifest's own and
  `composer.json`'s); `npm run admin:build`; the default theme's details
  on the jtcom trial in headless Chrome with a throwaway administrator
  (removed after, with its sessions). The trial's theme lists its
  author too.
- **Why:** the author asked for it, matching composer.json so a theme
  that's also a package says it once.

### D-385: The Plugins and Icon Packs screens from the extensions sketch
- **Date:** 2026-10-02
- **Status:** The packs and plugins turned off (`icons.disabled`,
  `plugins.disabled`) are superseded by D-390: lists of what's on.
- **Decision:** Builds the author's extensions sketch
  (`admin-design/blush-extensions.html`) for plugins and icon packs; its
  themes part is already built (D-381, D-383), and the author asked to
  leave Themes as it is. Amends D-308 and D-379 (both screens were
  read-only), D-378 ("every installed pack is on"), and D-384 (plugins
  and packs had no authors). The author chose each part Blush didn't
  have:
  - **Plugins turn on and off in `user/data/settings.json`**
    (`Setting::Plugins`, `plugins.disabled`, on no Settings screen),
    over `config/plugins.php`'s `disabled`, as Activate does for themes
    (D-381); D-039 holds. `PUT plugins/{vendor}/{name}` with
    `{"enabled"}` saves it, refusing a plugin `config/plugins.php`'s
    `enabled` list leaves out (`409`) or whose requirements aren't met
    (`422`), and answers which other plugins `started` or `stopped`
    with it. It needs a refresh, since providers run at boot.
  - **Requirements are enforced at boot** (the author's call), and
    other plugins are required by their `vendor/name` in `requires`
    (the author asked that `plugin.json` can name required plugins;
    `requires` already mapped names to constraints, so a `vendor/name`
    key is a plugin). `PluginRequirements` checks `blush`
    (`Framework::VERSION`), `php`, `ext-{name}` (loaded, at a fitting
    version), and plugins (installed at a fitting version, and
    running); anything else isn't met. `Plugins::enabled()` runs only
    the enabled plugins whose requirements are met, repeating until
    none is left out (so turning one off stops what needs it, all the
    way down), and keeps the rest with what they don't meet
    (`unmet()`). Providers register a plugin's requirements before it.
    Constraints are Composer's (`VersionConstraint`: `^`, `~`,
    comparisons, wildcards, hyphen ranges, `||`), ignoring stability, so
    `2.0.0-dev` satisfies `^2.0`; there's no Composer dependency.
  - **The plugin cache holds every installed plugin** (`compile()`
    wrote only the enabled ones), so one turned on in the admin is found
    on a compiled site. `Plugins::installed()` lists them.
  - **Icon packs can be turned off** (the author's call):
    `IconConfig` (`config/icons.php`, `disabled`) and
    `Setting::IconPacks` (`icons.disabled`) over it. `IconPacks` keeps
    every pack, with `enabled()` and `isEnabled()`; only packs that are
    on seed `IconRegistry` and the translator. `PUT
    icon-packs/{vendor}/{name}`. Blush's own icons are a locked **Core**
    card, always on. Only packs are listed, not icons themes and plugins
    carry (the author's note).
  - **Delete:** `DELETE plugins/{folder}` removes a folder plugin from
    `user/plugins` only when it isn't running (the author's call) and
    `config/plugins.php` doesn't turn it on by name (the site would fail
    without it); `DELETE icon-packs/{folder}` removes a folder pack, or a
    broken one, from `user/icons`. Each clears its cache and drops the
    name from the saved list.
  - **Manifests:** plugins take `authors` (D-384's shape) and `license`
    (a string); packs take `authors`. Either comes from the
    `composer.json` beside the manifest when it's left out
    (`ComposerJson`), or, for a Composer plugin, its package entry; a
    list of licenses reads "MIT or GPL-2.0-or-later". The schemas have
    them.
  - **The API:** `GET plugins` gives each plugin's `authors`,
    `license`, `folder`, `enabled`, `running`, `requirements` (each
    checked: `{"name", "constraint", "kind", "met", "note", "label"}`,
    as if turned on for one that's off), `blocked` (why it can't run),
    `requiredBy`, `locked`, and `deletable`, plus `saved`; it no longer
    lists what each plugin `adds` (the sketch: what a plugin registers
    shows on its own screens, and one that's off registers nothing).
    `GET icon-packs` gives each pack's `authors`, `folder`, `enabled`,
    `deletable`, and its first twelve icons as `{"name", "svg"}`, the
    `core` set, broken packs' `deletable`, and `saved`; `GET
    icon-packs/{vendor}/{name}` and `GET icon-packs/core` send every
    icon. The nav count for Icon Packs includes broken packs and the core
    set, as the screen lists them.
  - **The screens:** Plugins as rows with a switch (`ToggleSwitch`, a
    checkbox with the `switch` role and an On/Off word), a menu
    (**Plugin details**, **Copy folder path**, **Delete plugin**), why a
    plugin can't be turned on, and a toast naming the plugins that
    started or stopped with it; a plugin's details screen
    (`/plugins/{vendor}/{name}`) with Details and Requires panels.
    Icon Packs as cards of two rows of six glyphs (CSS masks, so nothing
    in a pack's SVG runs) with a switch, then Core, then broken packs; a
    pack's details screen (`/icon-packs/{vendor}/{name}`, and
    `/icon-packs/core`) with a filterable browser that copies an icon's
    reference. **Install Plugin** and **Install Icon Pack** open the
    sketch's modal, uploading marked as coming. Departures are in
    `admin-design/departures.md`.
- **Checked:** `composer check` (1,301 tests; new
  `VersionConstraintTest`, `PluginRequirementsTest`, the rewritten
  `AdminPluginsTest` and `AdminIconPacksTest`, and `BootstrapTest` for
  a compiled site finding a plugin turned on later); `npm run
  admin:build`; on the jtcom trial in headless Chrome with a throwaway
  administrator, two throwaway plugins (one needing Word Count, one
  needing Blush 3), a throwaway pack of twenty icons, and a broken one:
  both lists and every details screen, turning Word Count off (Word
  Report stopped with it) and on, turning Weather off and on, deleting
  a plugin and a broken pack, the install modal, the icon filter and
  copying, dark mode, and phone width (all removed after, with the
  account).
- **Why:** the author asked for the sketch's plugin and icon pack
  screens, and chose settings.json, enforcing requirements at boot,
  switching packs off, and deleting only plugins that are off.

### D-386: The Tree kind replaces Pages
- **Date:** 2026-10-02
- **Decision:** Answers D-257's open question (a kind for nesting
  entries, such as a manual with chapters), and supersedes D-157's
  `Pages` kind. The kind whose entries nest by folder is **Tree**
  (`kind: tree`, `TypeKind::Tree`, the final `Tree` class), and the
  built-in `page` type is a tree. "Page" stays the type; "Tree" is the
  kind, so the two aren't confused.
  - **Pages as they were:** a tree's entries nest by folder
    (`parentKey()`), the page catch-all serves them at their folder
    paths, the admin lists them as a tree, and lint doesn't ask a folder
    for an entry of its own. `kind: pages` is gone (no 1.x content used
    it).
  - **Trees in folders:** beside the root one, a site may add trees of
    its own (`new Tree('doc', folder: '_docs')`, or `kind: tree` in
    `user/data/types`). `Tree`'s folder defaults to the content root
    only for `page`, and `_` and the name otherwise (D-258).
    - **URLs:** a type served as pages is served under its folder
      without the `_` that starts its folder names
      (`ContentType::pagePath()`, as `prefix()` drops it for routed
      types): `_docs/install.md` at `/docs/install`.
      `ContentTypes::folderPath()` turns a catch-all path back into a
      folder path, longest match first. This also moves a `urls: false`
      collection in a `_` folder from its `_`-prefixed path (which was a
      404 anyway, since `_` segments are private).
    - **The index page:** a tree in a folder has its folder's `index`
      as its index page (D-255), pinned in its list like a
      collection's. Only the root tree (`Tree::atRoot()`) has none.
      `IndexPage`, `EntriesController`, and `CountsController` ask
      `atRoot()`, not the kind.
  - **The admin creates and changes trees:** **New Content Type** offers
    Tree beside Content and Taxonomy (`POST types` with `kind: tree`;
    only a second profiles type is refused now), and a tree in a folder
    from code is changed through `user/data/types` like a collection
    (`ContentType::isOverridable()`, which replaces
    `TypeKind::isOverridable()`: `false` for the root tree and the
    profiles type, whose messages name them by `role()`, "the site's
    pages"). A tree's forms have no URL prefix, feed, or author
    archives (`changesOf()` sends neither `prefix` nor `feed`), an
    Addresses note in their place, and an index page (`addIndex()`
    allows trees).
  - **The name:** "Tree", a noun beside `collection` and `taxonomy`.
    Chosen over "Sections" (which also names the admin's rail
    sections, D-244, and layout sections in templates) and
    "Hierarchical" (an adjective, and a taxonomy's `hierarchical: true`
    already means something else). The admin's tree lists (D-263) are
    the same idea. A site's labels ("Pages", "Docs") are what editors
    see; the name may still change.
- **Open:** see `open-questions.md` → Hierarchy (sibling order and
  previous/next through a tree).
- **Checked:** `composer check` (1,306 tests; new tests for a tree in
  a folder: its URLs and pages, its index page and tree in the admin
  list, one from `user/data/types`, creating one through `POST types`,
  and changing a code tree in a data file); `npm run admin:build`; on the
  jtcom trial, a test `doc` tree in `_docs` (`config/content.php`, five
  entries): `/docs`, `/docs/install`, and `/docs/install/requirements`
  serve, `/_docs/install` is a 404, `/about` still serves, and
  `content:lint` is clean. The new-type wizard and type editor for
  trees weren't checked in a browser.
- **Why:** the author asked for a hierarchical, page-like kind in a
  content subfolder, with its own name to tell the kind apart from the
  Page type; chose Tree; asked to move the page type onto it as pages
  work now; asked for a test tree type in the jtcom trial; and asked
  for trees on the new-type screen and through `user/data/types`.

### D-387: Toasts from the toast sketch
- **Date:** 2026-10-02
- **Decision:** Supersedes D-248's toasts (one at a time, a dark pill).
  Every toast in the admin is drawn and behaves as the toast sketch
  (`.claude/docs/admin-design/toast-sketch.html`) has it.
  - **The chip:** on `--surface` with the ordinary overlay hairline
    (`--border`), `--shadow-2`, and `--r-2`; the kind's glyph, the
    message at `--base`, and, where the action can be put back, a
    divider and **Undo**. A 2px bar along the bottom edge in the kind's
    color counts it down (`scaleX` from 1 to 0, so its rate is the same
    on any chip); the sketch records why it isn't a draining border.
  - **Kinds:** `good` (a confirmation, the default; `circle-check`),
    `warn` (a refusal, or a failure with nowhere else to say it;
    `triangle-alert`), `danger` (something deleted, trashed, removed,
    turned off, or suspended; `triangle-alert`), and `info` (not a thing
    that happened: "Uploading …", "The preview shows the last saved
    version"; `info`). Every caller now says which.
  - **Timing:** 2.6 s, or 7 s with an Undo. Hovering a toast or focusing
    inside it holds the count, the holds counted so the pointer and the
    keyboard each have to let go; the clock is a timer, never the
    animation ending, so under reduced motion the bar stays whole and
    the toast still goes. Escape inside a toast dismisses that one.
  - **Stacking:** a plain toast replaces the plain one standing but never
    one carrying an Undo; three at most, the oldest going first.
  - **Announcing:** one shared polite `role="status"` region reads the
    message (with "Undo is available"); the chips carry buttons, so
    they aren't the live region.
  - **API:** `toast(message, { kind, undo, life })` in `toast.ts`;
    `ToastHost` draws them. `undo` runs at most once, after the toast is
    gone.
  - **Undo where the reverse is exact:** turning a plugin on or off
    (only when no other plugin started or stopped with it, since turning
    the one back wouldn't put those back) and an icon pack on or off.
    The reverse's own toast offers none.
- **Departs from the sketch:** Undo is `--text-sm` (12px), not 12.5px,
  since no type size is a literal (D-231). Not yet undoable, though the
  sketch's examples are: moving to the trash (restoring always makes a
  draft, D-237, so it isn't an exact reverse until restore keeps the
  status), bulk status changes, and theme activation.
- **Checked:** `npm run admin:build` (type-checked); `ToastHost` alone
  in headless Chrome, light and dark: the chip and kinds as the sketch
  draws them, a plain toast replacing the plain one but not one with an
  Undo, an 8 s hover holding a 7 s toast, Undo running once and taking
  the toast, Escape on a focused Undo, the cap of three, and the live
  region's text. Not driven in the admin itself, so the plugin and pack
  Undos weren't run against the API.
- **Why:** the author added the toast sketch as how every toast in the
  admin should look.

### D-388: The admin installs extensions into `user/`
- **Date:** 2026-10-02
- **Status:** Installing and replacing from a zip built by D-392. Amends
  D-039 and D-166 and answers D-378's "how code installs from a
  browser". Where updates come from and discovery are still open. Its
  capabilities are D-389.
- **Decision:** The admin can install extensions of every kind,
  including the ones that run code (plugins and themes), into their
  folders in `user/` (`user/plugins`, `user/themes`, `user/icons`).
  D-039's rule narrows to what it was protecting: content, media, data,
  and uploads through the media library never carry executable files.
  Installing an extension is its own action, not an upload.
  Composer-installed extensions stay Composer's; the admin never runs
  Composer.
- **Still open** (`open-questions.md`): whether a local extension's
  update source is declared in its manifest or inferred from its
  `name`, and whether a catalog to discover extensions from (Packagist,
  by package type, is the likeliest first source) shows everything or
  an allowlist until a first-party catalog exists.
- **Why:** the author wants extensions installable from the admin, as
  D-378 anticipated, rather than only by people with repo or filesystem
  access.

### D-389: A capability for each extension action, per kind
- **Date:** 2026-10-02
- **Decision:** Ahead of installing (D-388), extensions get their own
  capabilities in place of `site.settings`: **`extensions.{kind}.{action}`**,
  for each kind (`themes`, `plugins`, `icon-packs`) and each action:
  - `view`: see the kind's screens and its count in the section panel.
    Every other action also needs it (`Permissions::can()` enforces
    this, as `accounts.*` needs `accounts.view`, D-362).
  - `install` and `update`: registered now, gating nothing until
    installing and replacing are built. Uploading a newer version of an
    installed extension replaces it (the author's call), which is
    `update`.
  - `activate`: activate a theme, or turn plugins and icon packs on and
    off.
  - `delete`: delete one.
  `extensions.*.{action}` grants an action on every kind (a role's `*`
  words, D-359). Only the administrator has them built in. On a role's
  screen they're three groups of Site Capabilities, Themes, Plugins,
  and Icon Packs (in the rail's order), with the rail's icons.
  - **Code:** `ExtensionAction` (as `ContentAction`: `on()`, `label()`,
    `group()`, `parse()`, `kinds()`), registered by
    `Capabilities::withBuiltIns()`.
  - **Settings the extension screens save:** `PATCH settings` needs, for
    each key it sets or unsets, `extensions.themes.activate` for
    `theme.active`, `extensions.plugins.activate` for
    `plugins.disabled`, `extensions.icon-packs.activate` for
    `icons.disabled`, and `site.settings` for the rest. `POST
    settings/refresh` takes any of the four.
  - **The admin** shows each screen, nav link, and palette command with
    its kind's `view`, and each Activate button, switch, and Delete with
    its capability (a switch is locked, saying why).
- **Checked:** `composer check` (`PermissionsTest`, and each kind's
  admin test: a role with `view` and `activate` turns on and off but
  can't delete or change other settings; without `view`, nothing);
  `npm run admin:build`.
- **Why:** the author asked for a full suite of `extensions.*`
  capabilities before building installing, so installing code can be
  given apart from settings.

### D-390: Nothing local is on until it's named; config lists only what's on
- **Date:** 2026-10-02
- **Status:** Composer extensions being always on, with a locked
  switch, is superseded by D-391: the admin's saved list can turn them
  off.
- **Decision:** The author's rules for which extensions are active.
  Supersedes D-058's "every discovered extension is enabled by default",
  D-041's "enabled or disabled in site config", and D-385's lists of
  what's off.
  - **Nothing is on by default, wherever it lives, except a Composer
    install.** A plugin in `user/plugins` or an icon pack in
    `user/icons` is off until it's turned on in the admin or named in
    config. A Composer plugin or pack is always on: installing it is the
    decision, and `composer remove` takes it away, so the admin's switch
    for one is locked and says so (`locked`, and `409` on `PUT`).
  - **Config only says what's on.** `PluginConfig` and `IconConfig` each
    have one list, `enabled` (local extensions, by name); `disabled` is
    gone, and a config that still has it is an error (unknown key). The
    admin saves its own list as `plugins.enabled` and `icons.enabled` in
    `user/data/settings.json`, which replaces the config file's, as
    before ("Use `config/plugins.php`'s list" goes back).
  - `enabled` is no longer an allow-list that locks the admin out of the
    rest; the admin can turn on any local plugin or pack. Naming a plugin
    that isn't installed still fails boot (D-058), so one the config
    file's list names (when the admin hasn't saved its own) can't be
    deleted; deleting one in the admin's list takes it out.
  - **Themes** are unchanged: one active, set by name (`theme.active`),
    with `blush/default` when none is.
  - The jtcom trial's `user/data/settings.json` now names
    `example/word-count` and `example/weather`, which were on by default.
- **Checked:** `composer check` (local plugins and packs off until
  named, Composer ones on, `disabled` refused, the admin's lists);
  `npm run admin:build`; the jtcom trial boots with both on.
- **Why:** the author's call: anything under `user/` is an explicit
  choice, and so is config.

### D-391: The admin's saved list names everything that's on, Composer's included
- **Date:** 2026-10-02
- **Decision:** Supersedes D-390's "a Composer plugin or pack is always
  on". The author wants Composer-installed extensions to be able to be
  turned off from the admin, without a list of what's off.
  - **By default** (no list saved in the admin) nothing changes: a
    Composer plugin or pack is on, and a local one only when config's
    `enabled` names it.
  - **Once the admin saves a list** (`plugins.enabled`,
    `icons.enabled` in `user/data/settings.json`), it is all of what's
    on: Composer's included. The first save starts from what's on by
    default, so only the switched extension changes. From then on, one
    the list doesn't name is off, **including one Composer installs
    later** (the author's call): telling a new install from one turned
    off would need a record of what the list has seen, which is a list
    of what's off by another name. The Plugins and Icon Packs screens
    say why an off Composer extension is off.
  - **Config files never name Composer extensions** to turn them off;
    their `enabled` stays local extensions only. "Use
    `config/plugins.php`'s list" (unsetting the saved list) goes back to
    the defaults.
  - **Code:** the saved list is laid over a separate key,
    `PluginConfig::$saved` / `IconConfig::$saved` (`Setting::configKey()`),
    so config's `enabled` keeps its meaning; `PluginConfig::named()` is
    whichever list is in use (for the boot check that every name is
    installed). `IconPacks` takes the `IconConfig`. The `locked` field
    and the `409` from D-390 are gone.
- **Checked:** `composer check` (a Composer plugin on by default, the
  first save starting from what was on, turning it off and on again;
  the saved list replacing config's for plugins and packs);
  `npm run admin:build`.
- **Why:** the author's call, so a site owner can switch off a
  Composer extension from the admin.

### D-392: Installing extensions from a zip
- **Date:** 2026-10-02
- **Status:** Backups last as long as their extension and can be
  rolled back to since D-393.
- **Decision:** Builds D-388's installing, from the extensions sketch's
  uploader (`admin-design/blush-extensions.html`, updated by the author),
  for themes, plugins, and icon packs alike. Departures from the sketch
  are in `departures.md` (**Installing**).
  - **Endpoints:** `POST themes`, `POST plugins`, `POST icon-packs`, the
    archive as the multipart field `file`; `replace=1` replaces an
    installed one with its name. Installing needs
    `extensions.{kind}.install`, replacing `extensions.{kind}.update`
    (D-389). `201` with what was installed, the version it replaced, the
    backup, and whether to refresh; `409` with the clash (both
    versions); `422` saying why otherwise, with the other `kind` for an
    archive of another kind. Nothing is written unless it's installed.
  - **Limits:** 25 MB, or PHP's upload limit when lower; at most 5,000
    files and 100 MB unpacked. Each list sends `upload` (the limit, and
    a `problem`: no zip extension, or a folder the web server can't
    write).
  - **The archive:** checked before anything is written (no absolute
    paths, `..`, or symbolic links); a zip whose files sit in one folder
    (GitHub's) is unpacked from inside it; macOS's `__MACOSX` and
    `.DS_Store` are left out.
  - **Checks**, read as discovery would: the kind (an archive of another
    kind says which), the manifest, the namespace (not reserved, not
    another installed extension's of any kind), not a name Composer
    installed, no `composer.json` requirements besides PHP, `ext-*`,
    `lib-*`, `composer/installers`, and the framework (a folder
    extension has no `vendor/`), and, for plugins and themes, every PHP
    file parsing (`token_get_all()` with `TOKEN_PARSE`, so nothing runs).
  - **Where it goes:** unpacked into a hidden folder in the kind's
    folder (theme and icon pack discovery now skip hidden folders, as
    plugin discovery did), then renamed to `user/{kind}/{short name}`,
    refused when that exists. Nothing is turned on (D-390).
  - **Replacing** (the author's call that a newer version replaces):
    the installed folder is swapped for the new one, refused for a git
    checkout, and the old folder is kept in
    `storage/backups/{kind}/{folder}` until the next replace. A running
    plugin or the active theme being replaced clears the compiled
    content types and routes and asks the admin to refresh.
  - **The admin:** one `InstallModal` for all three kinds, as the
    sketch draws it: drop anywhere on the modal or choose a file; real
    upload progress; the receipt with the one next step (Activate, Turn
    on); the clash with Replace naming both versions; refusals with
    Choose another file and, for another kind, Go to its screen
    (`?install=1` opens its modal). **Install** shows only with the
    kind's `install` capability.
- **Checked:** `composer check` (`AdminInstallTest`: a plugin installed
  from GitHub's one-folder layout and arriving off; the clash, then
  replacing with a backup; another kind, no manifest, not a zip, a
  damaged zip, `../`, a taken namespace, Composer requirements, a PHP
  syntax error, each writing nothing; a theme and a pack; install
  without update); `npm run admin:build`. The modal wasn't driven in a
  browser.
- **Why:** the author added the uploader to the extensions sketch as the
  next step of installing.

### D-393: A backup lasts as long as its extension, and can be rolled back to
- **Date:** 2026-10-02
- **Decision:** Settles pruning the backups D-392's replacing keeps (the
  author's pick of the options, for now: to revisit if Blush gets a
  scheduler, which would make expiring by age or a size cap possible).
  - **One backup per extension** (`storage/backups/{kind}/{folder}`),
    as before: the next replace overwrites it.
  - **Deleting an extension deletes its backup**, so none is left
    behind.
  - **Rolling back** (`POST {kind}/{vendor}/{name}/rollback`, the kind's
    `update` capability) swaps the backup in and keeps the version it
    replaces as the backup, so rolling back again undoes it. It's
    checked as an archive is (D-392), and refused, the author's call,
    when the earlier version wouldn't run: a plugin's requirements not
    met, or a theme in the active chain falling back to one that isn't
    installed. One that runs changes the live site at once, so the
    admin asks first (the author's call), and the toast offers Undo.
  - **Discarding** (`DELETE {kind}/{vendor}/{name}/backup`, the kind's
    `delete` capability) removes it.
  - Each extension in its kind's list has `backup` (`{"version"}`, or
    `null`); the details screens show a **Previous version** row
    (`PreviousVersion`) with Roll back and Discard.
- **Checked:** `composer check` (`AdminInstallTest`: rolling back and
  forth, the backup swapping each time; discarding; a version whose
  requirements aren't met refused, nothing changed; deleting a plugin
  deleting its backup; the capabilities); `npm run admin:build`. Not
  driven in a browser.
- **Why:** with one backup per extension, nothing accumulates once a
  deleted extension's goes too, and a backup is only worth keeping if
  the admin can use it.

