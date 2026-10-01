# Paths

## Local repositories

| What | Path |
|---|---|
| Blush framework (this repo, `2.x` branch) | `/Applications/XAMPP/xamppfiles/htdocs/blush-framework` |
| Blush site skeleton, `blush-dev/blush` (default install; 1.x on `master`; the M8 trial on `jtcom-trial`, D-142) | `/Applications/XAMPP/xamppfiles/htdocs/blush` |
| jtcom (first site built on Blush) | `/Applications/XAMPP/xamppfiles/htdocs/jtcom` |
| jtcom content (separate git repo) | `/Applications/XAMPP/xamppfiles/htdocs/jtcom/user` |
| x3p0-framework (container, application) | `/Applications/XAMPP/xamppfiles/htdocs/wp/wp-content/x3p0-framework` |
| x3p0-event | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-event` |
| x3p0-class-registry | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-class-registry` |
| x3p0-attributes | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-attributes` |
| x3p0-asset | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-asset` |
| x3p0-hooks (WordPress-only; reference only) | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-hooks` |
| x3p0-prelude | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-prelude` |
| x3p0-skills (not used, D-037; reference only) | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-skills` |
| x3p0-breadcrumbs (reference for `.phpcs.xml`, AGENTS.md patterns) | `/Applications/XAMPP/xamppfiles/htdocs/wp/wp-content/plugins/x3p0-breadcrumbs` |

## Framework layout (planned)

```
blush-framework/
  bin/                  CLI entry for framework development (none yet; sites have bin/blush)
  src/
    Core/               Application, ServiceProvider, Bootable, Bootstrap, Paths,
                        Environment, AppConfig, BlushException, Kernel wiring
    Container/          DI container (from x3p0-framework)
    Container/Plan/     Compiled resolution plans (D-052)
    Event/              Event system (from x3p0-event)
    Config/  Env/  Error/  Log/  Clock/
    Data/               DataLoader + JSON/YAML parser registry (D-032)
    Extension/          Extension manifests, discovery, local autoloading (D-041)
    Translation/        Translator, catalogs, formatters (D-028)
    Http/               Request, Response, Uri, Headers, factories, Emitter
    Http/Middleware/
    Routing/            Route, compiler, matcher, UrlGenerator, attributes
    Content/            Source, Parser, Schema, Type, Index, Entry, Query, Lint, Writer (ContentWriter,
                        FilesystemWriter, DocumentEditor, YamlMap, EntryChanges, D-228)
    Markdown/           Parser interface + adapter; CommonMark/Directive/ (D-112)
    Media/              MediaConfig, resolver, streaming controller (M4c); image derivatives later
    View/               Views, Template, ViewFinder, ViewFactory, Hierarchy, Head, Escaper,
                        functions.php (the escaping helpers), themed renderers,
                        context providers
    Component/          Component base, registry, factory, registrar, slots,
                        ComponentDirectives, built-ins (Layout/, Media/, Inline/) (D-192)
    Theme/              Themes, ThemeDiscovery, ThemeCache, ThemeManifest, ThemeChain,
                        ThemeConfig, ThemeResolver, ThemeAssets, settings, ThemeChecker,
                        the theme asset route
    Cache/              Store base + drivers, registry, CacheConfig, Caches, ContentVersion,
                        ContentCache, RenderedBodies, PageCache (D-127 to D-130)
    Embed/              oEmbed providers, registry, EmbedData, Embeds, Fetcher, EmbedConfig (D-184)
    Feed/               Feed formats, config, builder, controller, routes, head links (D-122)
    Icon/               IconName, Icons (lookup through site, themes, extensions, core), IconRegistry (D-187)
    Sitemap/            Sitemap config, builder, controller, robots.txt, routes (D-123)
    Publish/            Publisher, PublishConfig, Puller + GitPuller, webhook (D-131, D-132)
    Setup/              SetupChecks, CheckResult, CheckStatus, SetupPage (init, doctor, D-218)
    Session/            Session, SessionStore + FileSessionStore, SessionConfig, StartSession (D-219)
    Auth/               Account, AccountStore + FileAccountStore, Accounts, Passwords, Roles,
                        Role, BuiltInRole, Capabilities, Capability, Permissions, Authenticator,
                        LoginThrottle, AuthConfig; Middleware/ (VerifyCsrf, Authenticate) (D-219)
    Preview/            PreviewConfig, PreviewLinks, PreviewLink, PreviewController,
                        PreviewRoutes (signed preview links, D-226)
    Admin/              AdminConfig, AdminRoutes, AdminApp (the built front end), ShellController,
                        AssetController, SessionController, DashboardController, ActionController,
                        EntriesController, HealthController, PreviewLinkController,
                        EntryController (the editing API, D-229), InvalidEdit;
                        Action/ (AdminAction, ActionResult, AdminActionType, registry,
                        AdminActions, the built-ins) (D-219, D-223)
    Export/             Static export: Exporter, ExportSite, Crawler, UrlSource, ExportLayout,
                        ExportWriter, ExportAssets, ExportManifest, ExportFingerprint,
                        ExportRedirect (D-135 to D-139)
    Export/Host/        Host file formats: HostFormat, HostFiles, registry, factory,
                        registrar, ApacheFiles, NetlifyFiles (D-140)
    Console/            In-house console framework + built-in commands
    Support/            Registry base, Filesystem, PhpArrayFile, Str, Arr, etc.
    Support/Attributes/ Cached attribute reader (from x3p0-attributes)
  resources/            server.php (`serve` router); static-server.php (`serve --static`,
                        D-138); lang/ (the `blush` catalog domain); icons/blush/ (the core
                        Lucide subset, D-187)
    schemas/            Editor JSON Schemas: theme, extension, menu, region, and entry
                        (*.schema.json, generated by `composer schemas`, D-206, D-207, D-211)
    themes/default/     The framework default theme (D-110): theme.json, style.css,
                        lang/, views/ (incl. components/)
    admin/              The admin app's sources (Vue 3 + TypeScript, D-221, D-224):
                        vite.config.ts, tsconfig.json (references tsconfig.app.json for js/ and
                        tsconfig.node.json for vite.config.ts, with @types/node), js/ (admin.ts, App.vue, api.ts,
                        session.ts, router.ts, icons.ts, color-scheme.ts, fields.ts, types.ts, screen.ts, views/, components/), css/ (admin.css,
                        the entry, importing tokens.css, fonts.css, base.css; D-231), fonts/
                        (IBM Plex, OFL). Built with `npm run admin:build`
  public/admin/         The built admin app (committed; plain names, D-224): .vite/manifest.json,
                        js/admin.js, css/admin.css, fonts/
  package.json          npm scripts for the admin build (admin:build, admin:watch, admin:check)
  docs/                 User documentation: installing, content, themes, config, CLI (D-141)
  benchmarks/           PHPBench suite + the generated jtcom-sized site (D-101)
  scripts/              Framework dev scripts: build-schemas.php (`composer schemas`, D-206)
  tests/
    Fixtures/site/      Fixture site: .env, config/, local + Composer extensions
  .claude/
    docs/               ← this folder; admin-design/ holds the admin's design direction
                        (admin.md) and the original prototype tokens (D-231)
    skills/blush-code-style-php/
  AGENTS.md  CLAUDE.md  .phpcs.xml  phpstan.neon  phpunit.xml  phpbench.json
```

## Site layout (planned; jtcom follows this)

```
site/
  .htaccess             Forwards every request into public/, so the whole project can sit in
                        a host's public_html with no setup (D-071)
  nginx.conf.example    Sample nginx server block, root at public/ (D-072)
  bin/blush             Site CLI (name will follow the product name)
  config/               Typed config objects (app, content, cache, theme, …); never under user/ (D-039)
  user/                 What the owner writes or installs, like wp-content (D-166). May be
                        its own repo that ignores themes/ and extensions/
    content/            Markdown, HTML, and data entries
    media/              Uploaded and co-located media
    data/               Other user data: menus/ and regions/ (one file each, D-199, D-201),
                        redirects, theme.json, types/ (D-042), media/ (metadata
                        mirroring media paths, planned, D-238); JSON or YAML;
                        settings.json, the admin's saved settings by config section
                        (JSON only, D-324, D-325)
    themes/             Local themes, each optionally its own repo (Composer themes may
                        live in vendor/). A built theme keeps sources in {slug}/resources/
                        (never served), its build in {slug}/public/, and its build
                        config (package.json, vite.config.js) in {slug}/ (D-155, D-167)
    extensions/         Local extensions, each optionally its own repo (Composer
                        extensions live in vendor/)
  public/               Web root: index.php, .htaccess, and published assets ONLY
                        (themes/, and media at MediaConfig::$url, D-099). Relocatable
                        (e.g. cPanel public_html, D-046)
  resources/views/      Site-level view overrides (resources/views/themes/{slug}/ for theme-scoped ones)
  resources/lang/       The site's `app` translation domain, e.g. its components' text (D-173)
  resources/icons/      The site's own icons (`app/{name}`), and `{ns}/{name}.svg` overrides (D-187)
  src/                  App\ namespace: providers, components, controllers
  storage/
    cache/              Compiled config.php, extensions.php, container.php, routes.php,
                        content-types.php, themes.php (D-060, D-077, D-092, D-115);
                        content-version.json (D-128); store/{namespace}/ (D-127);
                        publish.lock (D-131); export/ (the export application's cache
                        and manifest.json) and export.lock (D-135, D-137)
    index/              content.php, the content index (D-087)
    export/             Static export output (`build`, D-137)
    logs/
    sessions/           One JSON file per session, named by the id's SHA-256 (D-219)
    accounts/           {username}.json admin accounts (D-217, D-219), with any non-default
                        preferences (D-235), suspension, and password link hash (D-312);
                        never cleared
    roles.json          Roles made or changed in the admin (D-312); never cleared
    trash/              One folder per deleted entry, {Ymd-His}-{6 hex}/: trash.json (entry,
                        bundle, trashed) and the file or bundle folder at user/content/...
                        (D-228, D-237; older folders have no manifest); never cleared
  tests/
```
