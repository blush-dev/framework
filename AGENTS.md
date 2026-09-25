# AGENTS.md

Guidance for agents working in this repository.

## Status

The `2.x` branch is a **full rewrite** of Blush, a flat-file CMS, targeting
PHP 8.5. Planning and milestone M0 (setup) are **complete**. The next step is
**milestone M1 (core)**; see `.claude/docs/roadmap.md`. Code on
`master` (1.x) is not a reference implementation. Only the general concepts of
a flat-file CMS carry over.

"Blush" is a working name. Keep product-name references centralized so a rename
stays mechanical.

## Git

**Never commit or push.** The user reviews and commits all changes. Leave work
uncommitted and summarize what changed.

## Project knowledge lives in `.claude/docs/`

At the start of a session, read `roadmap.md` (current milestone) and skim
`decisions.md`. Read the other docs before touching their subsystems, and
**keep them all current**:

| File | Purpose |
|---|---|
| [`.claude/docs/decisions.md`](.claude/docs/decisions.md) | Numbered decision log. Append a new entry for every decision; never silently change a past one (supersede it instead). |
| [`.claude/docs/paths.md`](.claude/docs/paths.md) | Framework layout, site layout, and paths to related local repos. |
| [`.claude/docs/architecture.md`](.claude/docs/architecture.md) | Subsystem-by-subsystem design. |
| [`.claude/docs/theming.md`](.claude/docs/theming.md) | Theming system design. |
| [`.claude/docs/cli.md`](.claude/docs/cli.md) | Custom CLI design. |
| [`.claude/docs/roadmap.md`](.claude/docs/roadmap.md) | Milestones and exit criteria. |
| [`.claude/docs/open-questions.md`](.claude/docs/open-questions.md) | Unresolved questions. Move each to `decisions.md` once answered. |

When the user makes a decision in conversation, record it in `decisions.md`
(and update any affected doc) in the same session.

## Commands

Run on PHP 8.5 (`php -v`). Run `composer check` before handing work back.

| Command | What it does |
|---|---|
| `composer install` | Install dev tools |
| `composer check` | Lint, analyse, and test (all three) |
| `composer lint` | PHPCS (`.phpcs.xml`) |
| `composer fix` | PHPCBF auto-fix |
| `composer analyse` | PHPStan, level max (`phpstan.neon`) |
| `composer test` | PHPUnit 12 (`phpunit.xml`) |

Single test: `vendor/bin/phpunit --filter FrameworkTest`.

## Architecture patterns

- **Container + service providers.** The application registers service
  providers that wire each subsystem into the DI container. Nothing is
  instantiated until needed. The container, application, and event system
  live in-tree (copied from the author's x3p0 packages); they are not Composer
  dependencies.
- **No global state.** No static facades, no `define()` constants, and no global
  helper functions except template escaping helpers. Use constructor
  injection.
- **Type enum + Registry + Factory + Registrar** for extensible subsystems
  (parsers, field types, cache drivers, components, commands). A backed enum
  maps canonical keys to built-in classes. The registry stores
  `key => class-string`. The factory instantiates through the container. The
  registrar seeds the built-ins without overwriting extension registrations.
- **Abstract base + final concrete types.** Typehint the abstract base or an
  interface. Concrete implementations are `final`.
- **Immutable, typed configuration objects**, never associative arrays passed
  around. Each also offers a `fromArray()` factory.
- **Render anywhere.** Nothing reads superglobals except the request factory,
  so `Kernel::handle(Request)` works from the web, the CLI, tests, static
  export, and admin preview.
- **Flat files are the default, not the only option.** Content storage goes
  through `ContentSource` / `ContentIndex` / `ContentRepository` interfaces.

## Coding conventions

Follow the `blush-code-style-php` skill (`.claude/skills/blush-code-style-php`).
This project uses only its own skills (D-037). Key points:

- PHP 8.5 minimum. Use modern features freely.
- `declare(strict_types=1);` in every PHP file.
- Tabs, and no spaces inside parentheses.
- MIT license in file headers.
- Avoid Laravel-isms in naming (no facades, "Illuminate"-style names,
  `make:` commands, "Foundation", and so on). Prefer `Core`.
