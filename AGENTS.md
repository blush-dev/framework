# AGENTS.md

Guidance for agents working in this repository.

## Status

The `2.x` branch is a **full rewrite** of Blush, a flat-file CMS, targeting
PHP 8.5. It is in the **planning stage** (milestone M0 has not started). Code on
`master` (1.x) is not a reference implementation. Only the general concepts of
a flat-file CMS carry over.

"Blush" is a working name. Keep product-name references centralized so a rename
stays mechanical.

## Project knowledge lives in `.claude/docs/`

Read these before making architectural changes, and **keep them current**:

| File | Purpose |
|---|---|
| [`.claude/docs/decisions.md`](.claude/docs/decisions.md) | Numbered decision log. Append a new entry for every decision; never silently change a past one (supersede it instead). |
| [`.claude/docs/paths.md`](.claude/docs/paths.md) | Framework layout, site layout, and paths to related local repos. |
| [`.claude/docs/architecture.md`](.claude/docs/architecture.md) | Subsystem-by-subsystem design. |
| [`.claude/docs/theming.md`](.claude/docs/theming.md) | Theming system exploration (in progress). |
| [`.claude/docs/cli.md`](.claude/docs/cli.md) | Custom CLI design. |
| [`.claude/docs/roadmap.md`](.claude/docs/roadmap.md) | Milestones and exit criteria. |
| [`.claude/docs/open-questions.md`](.claude/docs/open-questions.md) | Unresolved questions. Move each to `decisions.md` once answered. |

When the user makes a decision in conversation, record it in `decisions.md`
(and update any affected doc) in the same session.

## Commands

None yet. Tooling (PHPCS, PHPStan, PHPUnit) is set up in milestone M0. Update
this section when it lands.

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
Where it conflicts with the general `x3p0-code-style-php` skill, the Blush skill
wins. Key points:

- PHP 8.5 minimum. Use modern features freely.
- `declare(strict_types=1);` in every PHP file.
- Tabs, and no spaces inside parentheses.
- MIT license in file headers.
- Avoid Laravel-isms in naming (no facades, "Illuminate"-style names,
  `make:` commands, "Foundation", and so on). Prefer `Core`.
