# AGENTS.md

Guidance for agents working in this repository.

## Status

The `2.x` branch is a **full rewrite** of Blush, a flat-file CMS, targeting
PHP 8.5. Planning and milestones M0 (setup), M1 (core), M2 (HTTP +
Console), M3 (Routing), M4 (Content), M5 (Views + theming), M6 (Caching +
publishing), and M7 (Static export) are **complete**. **Milestone M8
(Port jtcom)** is on hold after a trial port on the `jtcom-trial` branch
of `../blush` (a test bed the author uses; never commit its site files).
**The current focus is the admin** (M9 and M10), grown out of the setup
DX/UX work (D-156): first-run setup (D-218), accounts and auth (D-215 to
D-219), the Vue admin app (D-220 to D-227), the editor's write path and
editing API (D-228, D-229), and, built from the admin design direction
(`.claude/docs/admin-design/admin.md`, D-231): the tokens and shell,
per-account preferences on Your profile (D-235), a list per content type
with status and Trash tabs (D-230, D-234, D-236, D-237), and the first
editor (D-233); and, from the clickable prototype
(`.claude/docs/admin-design/blush-admin.html`), the full navigation with
stub screens and a Markdown source editor (D-241), and the component
inserter (D-243); then the section rail (D-244) and the editor as a
writing surface with component options (D-245), and its three
inserters (components, media, icons; D-246, D-247); toasts and the
command palette (D-248); and read-only Roles and Accounts, Content
types, and Media screens (D-249 to D-251); then the updated design's
space scale, four inserters, icon categories, and marked source
(D-265); component variants (D-266); `:::figure` as a container (D-267);
every block as an object, images edited as Markdown, and uploads
(D-268); and alt text and captions in the media library, filled in on
insert (D-269, D-272); and, from the updated direction, every element as
an object with an outline, a breadcrumb, lists and definition lists,
Enter carrying list and quote markers, drawn selects, and a calendar
for dates (D-280); and the reference picker, with the document panel's
groups (D-281, D-283); and the Markdown editing experience's first set
(D-284, D-285); and media metadata fields by kind (D-287) and the
media index (D-288), embedded image metadata (D-289), titles (D-290),
sound and video metadata (D-291), and metadata files in
`content:lint` (D-293); page bundle media is removed, so media lives
only in `user/media` (D-294); and the Appearance, Extensions, and Settings screens, read-only
(D-306, D-308, D-309; the default theme's `excerpts` setting is gone,
D-307), so every screen in the navigation is built; and editing,
creating, and deleting `user/data/types` types (D-311); and editing
accounts and roles, with one-time password links and suspension
(D-312). Next: more of media (roadmap); and
more of the Markdown editing experience (live preview waits); see
`.claude/docs/roadmap.md`.
Admin app sources are in `resources/admin/`; rebuild with
`npm run admin:build` (D-221, D-224). Admin CSS reads design tokens
from `resources/admin/css/tokens.css` only: no literal colors, fonts,
type sizes, or radii elsewhere (D-231).
The dev site is `../blush` (`ddev start`, https://blush.ddev.site), on
`jtcom-trial` for now (the skeleton itself is its `2.x` branch). Code on
`master` (1.x) is not a reference implementation, with one exception:
every content convention 1.x supports must keep working (D-078), because
jtcom's content won't change.

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
| [`.claude/docs/admin-design/admin.md`](.claude/docs/admin-design/admin.md) | The admin's design direction (prototype stage, D-231), kept exactly as the author uploads it, with its prototype `blush-admin.html`. Read before writing admin UI; don't edit it. `tokens.css` beside it is the original prototype, not the build source. |
| [`.claude/docs/admin-design/departures.md`](.claude/docs/admin-design/departures.md) | The project's side of the direction: file paths, every departure from it and why, and its settled questions. Read with `admin.md`; record a departure here, not there. |

When the user makes a decision in conversation, record it in `decisions.md`
(and update any affected doc) in the same session.

## User documentation lives in `docs/`

`docs/` is the documentation for people installing and using Blush (D-141).
Keep it plain and task-first, and document only what's implemented. When
you change user-facing behavior (config options, front matter, content
conventions, CLI commands, the theming API), update `docs/` in the same
session.

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
| `composer bench` | PHPBench against the generated jtcom-sized site (`benchmarks/`, D-101) |
| `composer schemas` | Regenerate the editor JSON Schemas in `resources/schemas/` (D-206) |
| `npm run admin:build` | Type-check and build the admin app (`resources/admin/` → `public/admin/`, committed; D-221). Run after changing admin sources |
| `npm run admin:watch` | Rebuild the admin app on every change |

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
