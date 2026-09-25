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
| x3p0-skills | `/Applications/XAMPP/xamppfiles/htdocs/x3p0-skills` |
| x3p0-breadcrumbs (reference for `.phpcs.xml`, AGENTS.md patterns) | `/Applications/XAMPP/xamppfiles/htdocs/wp/wp-content/plugins/x3p0-breadcrumbs` |

## Framework layout (planned)

```
blush-framework/
  bin/                  CLI entry for framework development
  src/
    Core/               Application, ServiceProvider, Bootable, Kernel wiring
    Container/          DI container (from x3p0-framework)
    Event/              Event system (from x3p0-event)
    Config/  Env/  Error/  Log/  Clock/
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
    Support/            Str, Arr, Path helpers, Registry base, etc.
  resources/            Framework default views/theme
  tests/
  .claude/
    docs/               ← this folder
    skills/blush-code-style-php/
  AGENTS.md  CLAUDE.md  .phpcs.xml  phpstan.neon  phpunit.xml
```

## Site layout (planned; jtcom follows this)

```
site/
  bin/blush             Site CLI (name will follow the product name)
  config/               Typed config objects (app, content, cache, theme, …)
  user/
    content/            Markdown, HTML, and data entries
    media/              Uploaded and co-located media
    data/               Other user data (menus, authors, …)
  themes/               Local themes (installed themes may come via Composer)
  public/               Web root: index.php + built/published assets ONLY
  src/                  App\ namespace: providers, components, controllers
  storage/
    cache/  index/  logs/  sessions/  export/
  tests/
```
