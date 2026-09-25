# Paths

## Local repositories

| What | Path |
|---|---|
| Blush framework (this repo, `2.x` branch) | `/Applications/XAMPP/xamppfiles/htdocs/blush-framework` |
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
  bin/                  CLI entry for framework development
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
    Content/            Source, Parser, Schema, Index, Entry, Query, Writer
    Markdown/           Parser interface + adapter
    Media/              Media resolution, image derivatives
    View/               Engine, View, Hierarchy, Components, Head, Escaper
    Theme/              Theme manifest, loader, inheritance, tokens
    Cache/              Stores, PageCache, CacheVersion
    Feed/  Sitemap/
    Publish/            Webhook, deployer, static export
    Console/            In-house console framework + built-in commands
    Admin/              (later milestone)
    Support/            Registry base, Filesystem, PhpArrayFile, Str, Arr, etc.
    Support/Attributes/ Cached attribute reader (from x3p0-attributes)
  resources/            Framework default views/theme
  tests/
    Fixtures/site/      Fixture site: .env, config/, local + Composer extensions
  .claude/
    docs/               ← this folder
    skills/blush-code-style-php/
  AGENTS.md  CLAUDE.md  .phpcs.xml  phpstan.neon  phpunit.xml
```

## Site layout (planned; jtcom follows this)

```
site/
  bin/blush             Site CLI (name will follow the product name)
  config/               Typed config objects (app, content, cache, theme, …); never under user/ (D-039)
  user/
    content/            Markdown, HTML, and data entries
    media/              Uploaded and co-located media
    data/               Other user data: menus, redirects, theme.json, types/ (D-042); JSON or YAML
    themes/             Local themes (Composer-installed themes may live in vendor/)
    extensions/         Local extensions (Composer extensions live in vendor/)
  public/               Web root: index.php, .htaccess, and published assets ONLY
                        (themes/, media/). Relocatable (e.g. cPanel public_html, D-046)
  resources/views/      Site-level view overrides (resources/views/themes/{slug}/ for theme-scoped ones)
  src/                  App\ namespace: providers, components, controllers
  storage/
    cache/              Compiled config.php, extensions.php, container.php (D-060)
    index/  logs/  sessions/  export/
  tests/
```
