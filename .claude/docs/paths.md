# Paths

## Local repositories

| What | Path |
|---|---|
| Blush framework (this repo, `2.x` branch) | `/Applications/XAMPP/xamppfiles/htdocs/blush-framework` |
| Blush site skeleton, `blush-dev/blush` (default install; 1.x on `master`) | `/Applications/XAMPP/xamppfiles/htdocs/blush` |
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
    Content/            Source, Parser, Schema, Type, Index, Entry, Query, Lint, Writer
    Markdown/           Parser interface + adapter; CommonMark/Directive/ (D-112)
    Media/              MediaConfig, resolver, streaming controller (M4c); image derivatives later
    View/               Views, Template, ViewFinder, ViewFactory, Hierarchy, Head, Escaper,
                        functions.php (the escaping helpers), themed renderers,
                        context providers, ComponentDirectives
    View/Component/     Component base, registry, factory, registrar, slots, Embed
    Theme/              Themes, ThemeDiscovery, ThemeCache, ThemeManifest, ThemeChain,
                        ThemeConfig, ThemeResolver, ThemeAssets, settings, ThemeChecker,
                        the theme asset route
    Theme/Token/        DTCG TokenSet, TokenResolver, Contrast
    Cache/              Stores, PageCache, CacheVersion
    Feed/               Feed formats, config, builder, controller, routes, head links (D-122)
    Sitemap/            Sitemap config, builder, controller, robots.txt, routes (D-123)
    Publish/            Webhook, deployer, static export
    Console/            In-house console framework + built-in commands
    Admin/              (later milestone)
    Support/            Registry base, Filesystem, PhpArrayFile, Str, Arr, etc.
    Support/Attributes/ Cached attribute reader (from x3p0-attributes)
  resources/            server.php (`serve` router); lang/ (the `blush` catalog domain)
    themes/default/     The framework default theme (D-110): theme.json, tokens.json,
                        style.css, lang/, views/ (incl. components/)
  benchmarks/           PHPBench suite + the generated jtcom-sized site (D-101)
  tests/
    Fixtures/site/      Fixture site: .env, config/, local + Composer extensions
  .claude/
    docs/               ← this folder
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
  user/
    content/            Markdown, HTML, and data entries
    media/              Uploaded and co-located media
    data/               Other user data: menus, redirects, theme.json, types/ (D-042); JSON or YAML
    themes/             Local themes (Composer-installed themes may live in vendor/)
    extensions/         Local extensions (Composer extensions live in vendor/)
  public/               Web root: index.php, .htaccess, and published assets ONLY
                        (themes/, and media at MediaConfig::$url, D-099). Relocatable
                        (e.g. cPanel public_html, D-046)
  resources/views/      Site-level view overrides (resources/views/themes/{slug}/ for theme-scoped ones)
  src/                  App\ namespace: providers, components, controllers
  storage/
    cache/              Compiled config.php, extensions.php, container.php, routes.php,
                        content-types.php, themes.php (D-060, D-077, D-092, D-115)
    index/              content.php, the content index (D-087)
    logs/  sessions/  export/
  tests/
```
